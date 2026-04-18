<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\TrainingSession;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Persistence\Database;
use App\Repository\Contract\BookingRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;

final class BookingService
{
    public const CANCEL_BLOCK_WINDOW_HOURS = 12;

    public function __construct(
        private SessionRepositoryInterface $sessions,
        private BookingRepositoryInterface $bookings,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
        private Database $db,
    ) {
    }

    public function book(string $sessionId, string $traineeId, ?string $idempotencyKey = null): Booking
    {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->bookings->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                if ($existing->getTraineeId() !== $traineeId) {
                    throw new ConflictException('idempotency key reused by different trainee');
                }
                return $existing;
            }
        }

        return $this->db->transactional(function () use ($sessionId, $traineeId, $idempotencyKey): Booking {
            $this->db->lock("session:{$sessionId}");
            $session = $this->sessions->findForUpdate($sessionId);
            if ($session === null) {
                throw new NotFoundException('session not found');
            }
            if (!$session->isOpen()) {
                throw new ConflictException('session is not open');
            }
            if ($session->getStartsAt() <= $this->clock->now()) {
                throw new ConflictException('session already started');
            }

            $this->sweepExpired($session);

            $active = $this->bookings->findActiveBySession($session->getId());
            if (count($active) >= $session->getCapacity()) {
                throw new ConflictException('session at capacity');
            }

            foreach ($active as $existing) {
                if ($existing->getTraineeId() === $traineeId) {
                    throw new ConflictException('trainee already booked this session');
                }
            }

            $booking = new Booking(
                $this->ids->generate(),
                $session->getId(),
                $traineeId,
                $this->clock->now(),
                Booking::STATUS_RESERVED,
                null,
                null,
                ($idempotencyKey !== null && $idempotencyKey !== '') ? $idempotencyKey : null,
            );
            try {
                $this->bookings->save($booking);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), '1062') || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                    throw new ConflictException('concurrent booking detected');
                }
                throw $e;
            }
            $this->audit->record(
                $traineeId,
                'booking.create',
                'booking',
                $booking->getId(),
                [],
                ['status' => $booking->getStatus(), 'sessionId' => $session->getId()],
            );
            return $booking;
        });
    }

    public function confirm(string $bookingId, string $actorId): Booking
    {
        $booking = $this->requireBooking($bookingId);
        if ($booking->getStatus() !== Booking::STATUS_RESERVED) {
            throw new ConflictException('booking not in reserved state');
        }
        $elapsed = $this->clock->now()->getTimestamp() - $booking->getCreatedAt()->getTimestamp();
        if ($elapsed > Booking::RESERVATION_TTL_SECONDS) {
            $booking->expire();
            $this->bookings->save($booking);
            $this->audit->record($actorId, 'booking.expire', 'booking', $booking->getId(), ['status' => Booking::STATUS_RESERVED], ['status' => Booking::STATUS_EXPIRED]);
            throw new ConflictException('reservation expired');
        }
        $before = ['status' => $booking->getStatus()];
        $booking->confirm();
        $this->bookings->save($booking);
        $this->audit->record($actorId, 'booking.confirm', 'booking', $booking->getId(), $before, ['status' => $booking->getStatus()]);
        return $booking;
    }

    public function cancel(string $bookingId, string $actorId, string $reason, bool $adminOverride = false): Booking
    {
        if ($reason === '') {
            throw new ValidationException('reason is required');
        }
        $booking = $this->requireBooking($bookingId);
        if (!$booking->isActive()) {
            throw new ConflictException('booking not active');
        }
        $session = $this->sessions->find($booking->getSessionId());
        if ($session === null) {
            throw new NotFoundException('session not found');
        }
        $hoursUntil = ($session->getStartsAt()->getTimestamp() - $this->clock->now()->getTimestamp()) / 3600;
        if ($hoursUntil < self::CANCEL_BLOCK_WINDOW_HOURS && !$adminOverride) {
            throw new ConflictException('cancellation within 12 hour window requires admin override');
        }
        $before = ['status' => $booking->getStatus()];
        $booking->cancel($reason, $adminOverride ? $actorId : null);
        $this->bookings->save($booking);
        $this->audit->record(
            $actorId,
            $adminOverride ? 'booking.cancel.override' : 'booking.cancel',
            'booking',
            $booking->getId(),
            $before,
            ['status' => $booking->getStatus(), 'reason' => $reason],
        );
        return $booking;
    }

    public function sweepExpired(TrainingSession $session): int
    {
        $expired = 0;
        foreach ($this->bookings->findActiveBySession($session->getId()) as $booking) {
            if ($booking->getStatus() !== Booking::STATUS_RESERVED) {
                continue;
            }
            $elapsed = $this->clock->now()->getTimestamp() - $booking->getCreatedAt()->getTimestamp();
            if ($elapsed > Booking::RESERVATION_TTL_SECONDS) {
                $booking->expire();
                $this->bookings->save($booking);
                $this->audit->record('system', 'booking.expire', 'booking', $booking->getId(), ['status' => Booking::STATUS_RESERVED], ['status' => Booking::STATUS_EXPIRED]);
                $expired++;
            }
        }
        return $expired;
    }

    public function availability(string $sessionId): int
    {
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            throw new NotFoundException('session not found');
        }
        $this->sweepExpired($session);
        return $session->getCapacity() - count($this->bookings->findActiveBySession($sessionId));
    }

    public function reschedule(
        string $bookingId,
        string $newSessionId,
        string $actorId,
        ?string $idempotencyKey = null,
        bool $adminOverride = false,
        ?string $reason = null,
    ): Booking {
        $booking = $this->requireBooking($bookingId);
        if (!$booking->isActive()) {
            throw new ConflictException('booking not active');
        }
        $session = $this->sessions->find($booking->getSessionId());
        if ($session === null) {
            throw new NotFoundException('session not found');
        }
        // Reschedule must honour the same window as direct cancellation so the
        // policy cannot be side-stepped by calling the reschedule endpoint.
        $hoursUntil = ($session->getStartsAt()->getTimestamp() - $this->clock->now()->getTimestamp()) / 3600;
        if ($hoursUntil < self::CANCEL_BLOCK_WINDOW_HOURS && !$adminOverride) {
            throw new ConflictException('reschedule within 12 hour window requires admin override');
        }
        // Admin overrides *must* carry a non-empty reason so the audit log
        // explains why policy was bypassed. Non-override reschedules use the
        // supplied reason if any, otherwise a conventional default.
        $cancelReason = $reason !== null && trim($reason) !== '' ? trim($reason) : 'reschedule';
        if ($adminOverride && ($reason === null || trim($reason) === '')) {
            throw new ValidationException('admin override requires reason');
        }

        // Idempotency short-circuit for the new booking must run outside the
        // atomic wrapper — a replayed reschedule whose new booking already
        // exists should not re-cancel the original.
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->bookings->findByIdempotencyKey($idempotencyKey);
            if ($existing !== null) {
                if ($existing->getTraineeId() !== $booking->getTraineeId()) {
                    throw new ConflictException('idempotency key reused by different trainee');
                }
                return $existing;
            }
        }

        // Cancel+book must be a single atomic unit: if the new booking fails
        // (target session full, closed, started, capacity race, etc.) the
        // cancellation of the original booking must roll back so the trainee
        // never ends up with neither slot. PdoDatabase nests the inner
        // transactions of cancel() and book() as savepoints inside this outer
        // transaction, so a throw from book() reverts both.
        return $this->db->transactional(function () use (
            $bookingId,
            $newSessionId,
            $actorId,
            $idempotencyKey,
            $adminOverride,
            $cancelReason,
            $booking,
        ): Booking {
            $this->cancel($bookingId, $actorId, $cancelReason, $adminOverride);
            return $this->book($newSessionId, $booking->getTraineeId(), $idempotencyKey);
        });
    }

    private function requireBooking(string $bookingId): Booking
    {
        $booking = $this->bookings->find($bookingId);
        if ($booking === null) {
            throw new NotFoundException('booking not found');
        }
        return $booking;
    }
}
