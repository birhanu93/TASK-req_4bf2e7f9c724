<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\AccessDeniedException;

final class RbacService
{
    /** @var array<string,string[]> */
    private array $actionRoles;

    public function __construct()
    {
        $this->actionRoles = [
            'booking.create' => [Roles::TRAINEE],
            'booking.cancel.self' => [Roles::TRAINEE],
            'booking.cancel.override' => [Roles::ADMIN],
            'booking.confirm' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::ADMIN],
            'session.create' => [Roles::SUPERVISOR, Roles::ADMIN],
            'session.close' => [Roles::SUPERVISOR, Roles::ADMIN],
            'assessment.record' => [Roles::SUPERVISOR, Roles::ADMIN],
            'assessment.view.self' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::ADMIN, Roles::GUARDIAN],
            'voucher.create' => [Roles::ADMIN],
            'voucher.claim' => [Roles::TRAINEE, Roles::EMPLOYER],
            'voucher.redeem' => [Roles::TRAINEE, Roles::EMPLOYER, Roles::ADMIN],
            'voucher.void' => [Roles::ADMIN],
            'moderation.review' => [Roles::ADMIN],
            'moderation.submit' => [Roles::TRAINEE, Roles::SUPERVISOR],
            'guardian.link' => [Roles::ADMIN, Roles::GUARDIAN],
            'guardian.approve_device' => [Roles::GUARDIAN],
            'guardian.remote_logout' => [Roles::GUARDIAN],
            'certificate.issue' => [Roles::SUPERVISOR, Roles::ADMIN],
            'certificate.revoke' => [Roles::ADMIN],
            'certificate.verify' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::ADMIN, Roles::EMPLOYER],
            'audit.read' => [Roles::ADMIN],
            'storage.tier' => [Roles::ADMIN],
            'user.register' => [Roles::ADMIN],
            // Certificate browsing actions — every authenticated role may
            // read their own view; object-level ownership is enforced
            // separately by AuthorizationService.
            'certificate.view.own' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::GUARDIAN, Roles::ADMIN],
            // Admin-only administrative endpoints. Callers still funnel
            // through the controller requireAdmin() wrapper that pins
            // ADMIN via this RBAC check rather than a bespoke role
            // comparison.
            'admin.snapshot.export' => [Roles::ADMIN],
            'admin.keys.rotate' => [Roles::ADMIN],
            'admin.booking.list' => [Roles::ADMIN],
            'admin.booking.override' => [Roles::ADMIN],
            // Resources.
            'resource.manage' => [Roles::ADMIN],
            'resource.view' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::GUARDIAN, Roles::EMPLOYER, Roles::ADMIN],
            // Profile access — any authenticated role can hit the self
            // endpoint; AuthorizationService enforces that the target
            // userId matches the actor (or they are admin).
            'profile.read' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::GUARDIAN, Roles::EMPLOYER, Roles::ADMIN],
            'profile.update' => [Roles::TRAINEE, Roles::SUPERVISOR, Roles::GUARDIAN, Roles::EMPLOYER, Roles::ADMIN],
        ];
    }

    public function authorize(SessionContext $ctx, string $action): void
    {
        if (!isset($this->actionRoles[$action])) {
            throw new AccessDeniedException("unknown action: {$action}");
        }
        if (!in_array($ctx->getActiveRole(), $this->actionRoles[$action], true)) {
            throw new AccessDeniedException("role {$ctx->getActiveRole()} not permitted for {$action}");
        }
    }

    /**
     * @return string[]
     */
    public function rolesFor(string $action): array
    {
        return $this->actionRoles[$action] ?? [];
    }
}
