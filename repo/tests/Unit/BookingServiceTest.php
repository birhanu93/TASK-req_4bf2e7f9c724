<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Booking;
use App\Entity\TrainingSession;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\FixedClock;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class BookingServiceTest extends TestCase
{
    private function kernelAt(string $now = '2026-04-18T10:00:00+00:00'): array
    {
        $k = Factory::kernel(new \DateTimeImmutable($now));
        /** @var FixedClock $clock */
        $clock = $k->clock;
        return [$k, $clock];
    }

    private function makeSession(\App\App\Kernel $k, string $offset = '+2 days', int $capacity = 2): TrainingSession
    {
        $start = $k->clock->now()->modify($offset);
        return $k->scheduling->create('sup1', 'S', $start, $start->modify('+1 hour'), $capacity);
    }

    public function testBookAndConfirm(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        self::assertSame(Booking::STATUS_RESERVED, $b->getStatus());
        $b2 = $k->bookingService->confirm($b->getId(), 'u1');
        self::assertSame(Booking::STATUS_CONFIRMED, $b2->getStatus());
    }

    public function testBookingMissingSession(): void
    {
        [$k] = $this->kernelAt();
        $this->expectException(NotFoundException::class);
        $k->bookingService->book('nope', 'u1');
    }

    public function testBookingClosedSession(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $k->scheduling->close($s->getId());
        $this->expectException(ConflictException::class);
        $k->bookingService->book($s->getId(), 'u1');
    }

    public function testBookingSessionAlreadyStarted(): void
    {
        [$k, $clock] = $this->kernelAt();
        $s = $this->makeSession($k, '+1 hour');
        $clock->advance(7200);
        $this->expectException(ConflictException::class);
        $k->bookingService->book($s->getId(), 'u1');
    }

    public function testCapacityReached(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 days', 1);
        $k->bookingService->book($s->getId(), 'u1');
        $this->expectException(ConflictException::class);
        $k->bookingService->book($s->getId(), 'u2');
    }

    public function testDuplicateBookingByTrainee(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 days', 2);
        $k->bookingService->book($s->getId(), 'u1');
        $this->expectException(ConflictException::class);
        $k->bookingService->book($s->getId(), 'u1');
    }

    public function testReservationExpiresOnConfirm(): void
    {
        [$k, $clock] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $clock->advance(901);
        try {
            $k->bookingService->confirm($b->getId(), 'u1');
            self::fail('expected conflict');
        } catch (ConflictException $e) {
            self::assertStringContainsString('expired', $e->getMessage());
        }
        self::assertSame(Booking::STATUS_EXPIRED, $k->bookings->find($b->getId())->getStatus());
    }

    public function testConfirmNotReserved(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $k->bookingService->confirm($b->getId(), 'u1');
        $this->expectException(ConflictException::class);
        $k->bookingService->confirm($b->getId(), 'u1');
    }

    public function testConfirmMissing(): void
    {
        [$k] = $this->kernelAt();
        $this->expectException(NotFoundException::class);
        $k->bookingService->confirm('nope', 'u1');
    }

    public function testCancelRequiresReason(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $this->expectException(ValidationException::class);
        $k->bookingService->cancel($b->getId(), 'u1', '');
    }

    public function testCancelOutsideWindow(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 days');
        $b = $k->bookingService->book($s->getId(), 'u1');
        $cancelled = $k->bookingService->cancel($b->getId(), 'u1', 'changed mind');
        self::assertSame(Booking::STATUS_CANCELLED, $cancelled->getStatus());
        self::assertSame('changed mind', $cancelled->getCancellationReason());
    }

    public function testCancelInsideWindowBlocked(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 hours');
        $b = $k->bookingService->book($s->getId(), 'u1');
        $this->expectException(ConflictException::class);
        $k->bookingService->cancel($b->getId(), 'u1', 'reason');
    }

    public function testCancelInsideWindowAdminOverride(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 hours');
        $b = $k->bookingService->book($s->getId(), 'u1');
        $cancelled = $k->bookingService->cancel($b->getId(), 'admin1', 'safety', true);
        self::assertSame('admin1', $cancelled->getOverrideActorId());
    }

    public function testCancelInactiveBooking(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $k->bookingService->cancel($b->getId(), 'u1', 'x');
        $this->expectException(ConflictException::class);
        $k->bookingService->cancel($b->getId(), 'u1', 'y');
    }

    public function testCancelMissingBooking(): void
    {
        [$k] = $this->kernelAt();
        $this->expectException(NotFoundException::class);
        $k->bookingService->cancel('nope', 'u1', 'r');
    }

    public function testCancelMissingSession(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $k->sessions->delete($s->getId());
        $this->expectException(NotFoundException::class);
        $k->bookingService->cancel($b->getId(), 'u1', 'r');
    }

    public function testAvailabilityAndSweep(): void
    {
        [$k, $clock] = $this->kernelAt();
        $s = $this->makeSession($k, '+2 days', 3);
        $k->bookingService->book($s->getId(), 'u1');
        $k->bookingService->book($s->getId(), 'u2');
        self::assertSame(1, $k->bookingService->availability($s->getId()));
        $clock->advance(901);
        self::assertSame(3, $k->bookingService->availability($s->getId()));
    }

    public function testAvailabilityMissing(): void
    {
        [$k] = $this->kernelAt();
        $this->expectException(NotFoundException::class);
        $k->bookingService->availability('missing');
    }

    public function testReschedule(): void
    {
        [$k] = $this->kernelAt();
        $s1 = $this->makeSession($k, '+2 days', 2);
        $s2 = $this->makeSession($k, '+3 days', 2);
        $b = $k->bookingService->book($s1->getId(), 'u1');
        $b2 = $k->bookingService->reschedule($b->getId(), $s2->getId(), 'u1');
        self::assertSame($s2->getId(), $b2->getSessionId());
        self::assertSame(Booking::STATUS_CANCELLED, $k->bookings->find($b->getId())->getStatus());
    }

    public function testRescheduleInactiveBooking(): void
    {
        [$k] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $k->bookingService->cancel($b->getId(), 'u1', 'r');
        $s2 = $this->makeSession($k, '+3 days');
        $this->expectException(ConflictException::class);
        $k->bookingService->reschedule($b->getId(), $s2->getId(), 'u1');
    }

    public function testSweepExpiredDirectly(): void
    {
        [$k, $clock] = $this->kernelAt();
        $s = $this->makeSession($k);
        $b = $k->bookingService->book($s->getId(), 'u1');
        $k->bookingService->confirm($b->getId(), 'u1');
        $k->bookingService->book($s->getId(), 'u2');
        $clock->advance(901);
        $expired = $k->bookingService->sweepExpired($s);
        self::assertSame(1, $expired);
    }
}
