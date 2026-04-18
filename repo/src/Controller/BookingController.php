<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Booking;
use App\Exception\AuthException;
use App\Exception\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\Contract\BookingRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;
use App\Service\AuthorizationService;
use App\Service\AuthService;
use App\Service\BookingService;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SessionContext;

final class BookingController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private AuthorizationService $authz,
        private BookingService $bookings,
        private BookingRepositoryInterface $bookingRepo,
        private SessionRepositoryInterface $sessionRepo,
    ) {
    }

    public function create(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'booking.create');
        $booking = $this->bookings->book(
            (string) $req->input('sessionId', ''),
            $ctx->getUserId(),
            self::nullIfEmpty((string) $req->input('idempotencyKey', '')),
        );
        return Response::json($this->serialize($booking), 201);
    }

    public function adminList(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'admin.booking.list');
        $traineeId = $req->query('traineeId');
        $sessionId = $req->query('sessionId');
        $status = $req->query('status');

        if ($traineeId !== null) {
            $list = $this->bookingRepo->findByTrainee($traineeId);
        } elseif ($sessionId !== null) {
            $list = $this->bookingRepo->findActiveBySession($sessionId);
        } else {
            $list = $this->bookingRepo->findAll();
        }
        if ($status !== null) {
            $list = array_values(array_filter($list, fn ($b) => $b->getStatus() === $status));
        }
        return Response::json(['bookings' => array_map(fn ($b) => $this->serialize($b), $list)]);
    }

    public function list(Request $req): Response
    {
        $ctx = $this->context($req);
        $role = $ctx->getActiveRole();
        $queryTraineeId = $req->query('traineeId');

        if ($role === Roles::SUPERVISOR) {
            // Documented semantics: a supervisor sees bookings on sessions
            // they own — not bookings where they are the trainee. We
            // enumerate their own sessions first, pull the bookings on each,
            // and optionally narrow by traineeId. Bookings on other
            // supervisors' sessions are never loaded, so cross-ownership
            // data cannot leak through this endpoint.
            $ownSessions = $this->sessionRepo->findBySupervisor($ctx->getUserId());
            $ownSessionIds = [];
            foreach ($ownSessions as $session) {
                $ownSessionIds[$session->getId()] = true;
            }
            $seen = [];
            $list = [];
            $source = $queryTraineeId !== null
                ? $this->bookingRepo->findByTrainee($queryTraineeId)
                : $this->bookingRepo->findAll();
            foreach ($source as $booking) {
                if (!isset($ownSessionIds[$booking->getSessionId()])) {
                    continue;
                }
                if (isset($seen[$booking->getId()])) {
                    continue;
                }
                $seen[$booking->getId()] = true;
                $list[] = $booking;
            }
            return Response::json(['bookings' => array_map(fn ($b) => $this->serialize($b), $list)]);
        }

        $requested = (string) ($queryTraineeId ?? $ctx->getUserId());
        if ($requested !== $ctx->getUserId() && $role !== Roles::ADMIN) {
            throw new \App\Exception\AccessDeniedException('cannot list bookings for another user');
        }
        $list = $this->bookingRepo->findByTrainee($requested);
        return Response::json(['bookings' => array_map(fn ($b) => $this->serialize($b), $list)]);
    }

    public function show(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $booking = $this->bookingRepo->find($vars['id']);
        if ($booking === null) {
            throw new NotFoundException('booking not found');
        }
        $this->authz->assertBookingOwnership($ctx, $booking);
        return Response::json($this->serialize($booking));
    }

    public function confirm(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'booking.confirm');
        $existing = $this->bookingRepo->find($vars['id']);
        if ($existing === null) {
            throw new NotFoundException('booking not found');
        }
        $this->authz->assertBookingOwnership($ctx, $existing);
        $booking = $this->bookings->confirm($vars['id'], $ctx->getUserId());
        return Response::json($this->serialize($booking));
    }

    public function cancel(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $override = (bool) $req->input('override', false);
        if ($override) {
            $this->rbac->authorize($ctx, 'booking.cancel.override');
        } else {
            $this->rbac->authorize($ctx, 'booking.cancel.self');
        }
        $existing = $this->bookingRepo->find($vars['id']);
        if ($existing === null) {
            throw new NotFoundException('booking not found');
        }
        $this->authz->assertBookingOwnership($ctx, $existing);
        $booking = $this->bookings->cancel(
            $vars['id'],
            $ctx->getUserId(),
            (string) $req->input('reason', ''),
            $override && $ctx->getActiveRole() === Roles::ADMIN,
        );
        return Response::json($this->serialize($booking));
    }

    public function reschedule(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $override = (bool) $req->input('override', false);
        if ($override) {
            // Reschedule with override is an administrative action and must be
            // authorized the same way as a cancellation override.
            $this->rbac->authorize($ctx, 'booking.cancel.override');
        } else {
            $this->rbac->authorize($ctx, 'booking.create');
        }
        $existing = $this->bookingRepo->find($vars['id']);
        if ($existing === null) {
            throw new NotFoundException('booking not found');
        }
        $this->authz->assertBookingAccess($ctx, $existing);
        $this->authz->assertBookingOwnership($ctx, $existing);
        $booking = $this->bookings->reschedule(
            $vars['id'],
            (string) $req->input('newSessionId', ''),
            $ctx->getUserId(),
            self::nullIfEmpty((string) $req->input('idempotencyKey', '')),
            $override && $ctx->getActiveRole() === Roles::ADMIN,
            self::nullIfEmpty((string) $req->input('reason', '')),
        );
        return Response::json($this->serialize($booking), 201);
    }

    private function context(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }

    private static function nullIfEmpty(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return array<string,mixed>
     */
    private function serialize(Booking $b): array
    {
        return [
            'id' => $b->getId(),
            'sessionId' => $b->getSessionId(),
            'traineeId' => $b->getTraineeId(),
            'status' => $b->getStatus(),
            'createdAt' => $b->getCreatedAt()->format(DATE_ATOM),
            'cancellationReason' => $b->getCancellationReason(),
            'overrideActorId' => $b->getOverrideActorId(),
            'idempotencyKey' => $b->getIdempotencyKey(),
        ];
    }
}
