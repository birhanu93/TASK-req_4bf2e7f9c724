<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Persistent auth-session record. Distinct from the transient
 * SessionContext held in memory during a single request.
 */
final class AuthSessionRecord
{
    public function __construct(
        private string $token,
        private string $userId,
        private string $activeRole,
        private \DateTimeImmutable $issuedAt,
        private \DateTimeImmutable $expiresAt,
        private bool $revoked = false,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getActiveRole(): string
    {
        return $this->activeRole;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function revoke(): void
    {
        $this->revoked = true;
    }

    public function isActive(\DateTimeImmutable $at): bool
    {
        return !$this->revoked && $at < $this->expiresAt;
    }
}
