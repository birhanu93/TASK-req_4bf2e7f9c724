<?php

declare(strict_types=1);

namespace App\Service;

final class SessionContext
{
    public function __construct(
        private string $userId,
        private string $activeRole,
        private string $token,
        private \DateTimeImmutable $issuedAt,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getActiveRole(): string
    {
        return $this->activeRole;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }
}
