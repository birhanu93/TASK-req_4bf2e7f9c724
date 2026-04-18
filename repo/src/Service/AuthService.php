<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuthSessionRecord;
use App\Entity\User;
use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\AuthSessionRepositoryInterface;
use App\Repository\Contract\SystemStateRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;

/**
 * Authentication, authenticated-session, and bootstrap-admin management.
 *
 * Security rules enforced here:
 *   - Role tokens require proof of password on every issuance. An authenticated
 *     user cannot be impersonated by knowing their userId alone.
 *   - Sessions are persistent records with a hard expiry and revocation flag.
 *   - The first admin can only be bootstrapped once, enforced by an atomic
 *     system_state marker claim. Replay attempts fail with ConflictException.
 */
final class AuthService
{
    public const SESSION_TTL_SECONDS = 3600 * 8;
    public const BOOTSTRAP_MARKER = 'bootstrap.admin.created';

    public function __construct(
        private UserRepositoryInterface $users,
        private AuthSessionRepositoryInterface $sessions,
        private SystemStateRepositoryInterface $systemState,
        private PasswordHasher $hasher,
        private Clock $clock,
        private IdGenerator $ids,
        private ?AuditLogger $audit = null,
    ) {
    }

    /**
     * @param string[] $roles
     */
    public function register(string $username, string $password, array $roles, ?string $actorId = null): User
    {
        if ($this->users->findByUsername($username) !== null) {
            throw new ValidationException('username already exists');
        }
        if (count($roles) === 0) {
            throw new ValidationException('at least one role is required');
        }
        foreach ($roles as $r) {
            if (!Roles::isValid($r)) {
                throw new ValidationException("invalid role: {$r}");
            }
        }
        $user = new User(
            $this->ids->generate(),
            $username,
            $this->hasher->hash($password),
            $roles,
        );
        $this->users->save($user);
        $this->audit?->record(
            $actorId ?? 'system',
            'user.register',
            'user',
            $user->getId(),
            [],
            ['username' => $username, 'roles' => $user->getRoles()],
        );
        return $user;
    }

    /**
     * Claim the bootstrap-admin marker atomically and create the initial admin.
     * Subsequent calls throw ConflictException even under concurrent access:
     * the claim() primitive is an atomic insert that only one writer wins.
     */
    public function bootstrapAdmin(string $username, string $password): User
    {
        if (!$this->systemState->claim(
            self::BOOTSTRAP_MARKER,
            'claimed',
            $this->clock->now(),
        )) {
            throw new ConflictException('bootstrap admin already created');
        }
        try {
            $user = $this->register($username, $password, [Roles::ADMIN], 'bootstrap');
        } catch (\Throwable $e) {
            // A failure mid-bootstrap would otherwise leave the marker set.
            // Deliberately do not release the marker — the operator must fix
            // the underlying cause and resume manually to avoid enabling a
            // replay window between the marker claim and user insert.
            throw $e;
        }
        $this->audit?->record('bootstrap', 'system.bootstrap', 'user', $user->getId(), [], ['username' => $username]);
        return $user;
    }

    /**
     * @return array{user:User,availableRoles:string[]}
     */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername($username);
        if ($user === null || !$user->isActive() || !$this->hasher->verify($password, $user->getPasswordHash())) {
            $this->audit?->record('anon', 'auth.login.fail', 'user', $username, [], []);
            throw new AuthException('invalid credentials');
        }
        $this->audit?->record($user->getId(), 'auth.login', 'user', $user->getId(), [], []);
        return ['user' => $user, 'availableRoles' => $user->getRoles()];
    }

    /**
     * Issue a role token. Requires re-proof of the password. This blocks
     * attacks where a userId is exposed (logs, URLs, another subsystem) and
     * reused to mint a role token without re-authenticating.
     */
    public function selectRole(string $username, string $password, string $role): SessionContext
    {
        $result = $this->login($username, $password);
        $user = $result['user'];
        if (!$user->hasRole($role)) {
            throw new AccessDeniedException('role not assigned');
        }
        return $this->issue($user, $role);
    }

    public function authenticate(string $token): SessionContext
    {
        $record = $this->sessions->findByToken($token);
        if ($record === null) {
            throw new AuthException('invalid or expired session');
        }
        $now = $this->clock->now();
        if (!$record->isActive($now)) {
            throw new AuthException('invalid or expired session');
        }
        return new SessionContext(
            $record->getUserId(),
            $record->getActiveRole(),
            $record->getToken(),
            $record->getIssuedAt(),
        );
    }

    /**
     * Switch to a different role the user holds. Requires password re-proof to
     * prevent session-token-only role escalation.
     */
    public function switchRole(string $token, string $password, string $role): SessionContext
    {
        $ctx = $this->authenticate($token);
        $user = $this->users->find($ctx->getUserId());
        if ($user === null) {
            throw new NotFoundException('user not found');
        }
        if (!$this->hasher->verify($password, $user->getPasswordHash())) {
            $this->audit?->record($user->getId(), 'auth.role.switch.fail', 'user', $user->getId(), [], []);
            throw new AuthException('password re-proof required');
        }
        if (!$user->hasRole($role)) {
            throw new AccessDeniedException('role not assigned');
        }
        $this->sessions->revoke($token);
        $this->audit?->record($user->getId(), 'auth.role.switch', 'user', $user->getId(),
            ['role' => $ctx->getActiveRole()], ['role' => $role]);
        return $this->issue($user, $role);
    }

    public function logout(string $token): void
    {
        $record = $this->sessions->findByToken($token);
        if ($record === null) {
            return;
        }
        $this->sessions->revoke($token);
        $this->audit?->record($record->getUserId(), 'auth.logout', 'user', $record->getUserId(), [], []);
    }

    public function changePassword(string $userId, string $oldPassword, string $newPassword): void
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new NotFoundException('user not found');
        }
        if (!$this->hasher->verify($oldPassword, $user->getPasswordHash())) {
            $this->audit?->record($userId, 'auth.password.change.fail', 'user', $userId, [], []);
            throw new AuthException('old password mismatch');
        }
        $user->setPasswordHash($this->hasher->hash($newPassword));
        $this->users->save($user);
        // Password change is a session-invalidation event for the actor.
        $this->sessions->revokeByUser($userId);
        $this->audit?->record($userId, 'auth.password.change', 'user', $userId, [], []);
    }

    private function issue(User $user, string $role): SessionContext
    {
        $now = $this->clock->now();
        $expires = $now->modify('+' . self::SESSION_TTL_SECONDS . ' seconds');
        $token = bin2hex(random_bytes(24));
        $record = new AuthSessionRecord($token, $user->getId(), $role, $now, $expires, false);
        $this->sessions->save($record);
        $this->audit?->record($user->getId(), 'auth.role.issue', 'user', $user->getId(), [], ['role' => $role]);
        return new SessionContext($user->getId(), $role, $token, $now);
    }
}
