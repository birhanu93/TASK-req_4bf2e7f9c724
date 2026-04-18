<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Exception\AccessDeniedException;
use App\Exception\NotFoundException;
use App\Repository\Contract\AssessmentRepositoryInterface;
use App\Repository\Contract\BookingRepositoryInterface;
use App\Repository\Contract\GuardianLinkRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;

/**
 * Object-level authorization. Complements the action-to-role RBAC with
 * resource-ownership checks (a trainee may only see their own bookings; a
 * guardian may only see linked children; supervisors only their own
 * sessions).
 */
final class AuthorizationService
{
    public function __construct(
        private RbacService $rbac,
        private BookingRepositoryInterface $bookings,
        private AssessmentRepositoryInterface $assessments,
        private GuardianLinkRepositoryInterface $guardianLinks,
        private SessionRepositoryInterface $sessions,
    ) {
    }

    public function assertAction(SessionContext $ctx, string $action): void
    {
        $this->rbac->authorize($ctx, $action);
    }

    /**
     * Full ownership check for a booking object. Admins bypass; trainees must
     * own the booking; supervisors must own the underlying session; guardians
     * must be linked to the trainee. Used on every mutating endpoint.
     */
    public function assertBookingOwnership(SessionContext $ctx, Booking $booking): void
    {
        $role = $ctx->getActiveRole();
        if ($role === Roles::ADMIN) {
            return;
        }
        if ($role === Roles::TRAINEE) {
            if ($booking->getTraineeId() === $ctx->getUserId()) {
                return;
            }
            throw new AccessDeniedException('booking not owned by trainee');
        }
        if ($role === Roles::SUPERVISOR) {
            $session = $this->sessions->find($booking->getSessionId());
            if ($session === null) {
                throw new NotFoundException('session not found');
            }
            if ($session->getSupervisorId() === $ctx->getUserId()) {
                return;
            }
            throw new AccessDeniedException('supervisor does not own this booking');
        }
        if ($role === Roles::GUARDIAN) {
            if ($this->guardianLinks->findLink($ctx->getUserId(), $booking->getTraineeId()) !== null) {
                return;
            }
            throw new AccessDeniedException('guardian not linked to booking trainee');
        }
        throw new AccessDeniedException('booking not accessible to actor');
    }

    /**
     * @deprecated use {@see assertBookingOwnership()} — kept to preserve
     *             existing callers that only need read access.
     */
    public function assertBookingAccess(SessionContext $ctx, Booking $booking): void
    {
        $this->assertBookingOwnership($ctx, $booking);
    }

    public function assertAssessmentProgressAccess(SessionContext $ctx, string $traineeId): void
    {
        $role = $ctx->getActiveRole();
        if ($role === Roles::ADMIN) {
            return;
        }
        if ($role === Roles::SUPERVISOR) {
            // Supervisors may only view trainees who have booked one of their
            // sessions. Otherwise a supervisor could enumerate any trainee.
            foreach ($this->bookings->findByTrainee($traineeId) as $booking) {
                $session = $this->sessions->find($booking->getSessionId());
                if ($session !== null && $session->getSupervisorId() === $ctx->getUserId()) {
                    return;
                }
            }
            throw new AccessDeniedException('supervisor has no session with this trainee');
        }
        if ($role === Roles::TRAINEE && $traineeId === $ctx->getUserId()) {
            return;
        }
        if ($role === Roles::GUARDIAN) {
            $link = $this->guardianLinks->findLink($ctx->getUserId(), $traineeId);
            if ($link !== null) {
                return;
            }
        }
        throw new AccessDeniedException('assessment progress not accessible to actor');
    }

    public function assertChildAccess(SessionContext $ctx, string $childId): void
    {
        if ($ctx->getActiveRole() !== Roles::GUARDIAN) {
            throw new AccessDeniedException('guardian role required');
        }
        if ($this->guardianLinks->findLink($ctx->getUserId(), $childId) === null) {
            throw new NotFoundException('child not linked to actor');
        }
    }

    public function assertSupervisorForSession(SessionContext $ctx, string $sessionSupervisorId): void
    {
        if ($ctx->getActiveRole() === Roles::ADMIN) {
            return;
        }
        if ($ctx->getActiveRole() === Roles::SUPERVISOR && $ctx->getUserId() === $sessionSupervisorId) {
            return;
        }
        throw new AccessDeniedException('supervisor does not own this session');
    }

    public function assertProfileAccess(SessionContext $ctx, string $userId): void
    {
        if ($ctx->getActiveRole() === Roles::ADMIN) {
            return;
        }
        if ($ctx->getUserId() === $userId) {
            return;
        }
        throw new AccessDeniedException('profile access denied');
    }

    /**
     * A supervisor may only write against a trainee they actually work with —
     * at least one booking by that trainee in a session they own. Admins
     * bypass. Every other role is rejected. Used before assessment writes and
     * certificate issuance so a supervisor cannot pluck an arbitrary
     * traineeId out of thin air.
     */
    public function assertSupervisorActsOnKnownTrainee(SessionContext $ctx, string $traineeId): void
    {
        if ($ctx->getActiveRole() === Roles::ADMIN) {
            return;
        }
        if ($ctx->getActiveRole() !== Roles::SUPERVISOR) {
            throw new AccessDeniedException('supervisor or admin role required');
        }
        foreach ($this->bookings->findByTrainee($traineeId) as $booking) {
            $session = $this->sessions->find($booking->getSessionId());
            if ($session !== null && $session->getSupervisorId() === $ctx->getUserId()) {
                return;
            }
        }
        throw new AccessDeniedException('supervisor has no session with this trainee');
    }

    public function assertCertificateAccess(SessionContext $ctx, string $traineeId): void
    {
        $role = $ctx->getActiveRole();
        if ($role === Roles::ADMIN) {
            return;
        }
        if ($role === Roles::SUPERVISOR) {
            // Supervisors may download certificates only for trainees they have
            // worked with (at least one booking in a session they own).
            foreach ($this->bookings->findByTrainee($traineeId) as $booking) {
                $session = $this->sessions->find($booking->getSessionId());
                if ($session !== null && $session->getSupervisorId() === $ctx->getUserId()) {
                    return;
                }
            }
            throw new AccessDeniedException('supervisor has no session with this trainee');
        }
        if ($role === Roles::TRAINEE && $traineeId === $ctx->getUserId()) {
            return;
        }
        if ($role === Roles::GUARDIAN && $this->guardianLinks->findLink($ctx->getUserId(), $traineeId) !== null) {
            return;
        }
        if ($role === Roles::EMPLOYER) {
            // Employers can verify by code but never download arbitrary
            // trainees' certificate artifacts.
            throw new AccessDeniedException('employer may verify but not download');
        }
        throw new AccessDeniedException('certificate not accessible to actor');
    }
}
