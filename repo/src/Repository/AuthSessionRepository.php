<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthSessionRecord;
use App\Repository\Contract\AuthSessionRepositoryInterface;

final class AuthSessionRepository implements AuthSessionRepositoryInterface
{
    /** @var array<string,AuthSessionRecord> */
    private array $byToken = [];

    public function save(AuthSessionRecord $session): void
    {
        $this->byToken[$session->getToken()] = $session;
    }

    public function findByToken(string $token): ?AuthSessionRecord
    {
        return $this->byToken[$token] ?? null;
    }

    public function revoke(string $token): void
    {
        if (isset($this->byToken[$token])) {
            $this->byToken[$token]->revoke();
        }
    }

    public function revokeByUser(string $userId): int
    {
        $count = 0;
        foreach ($this->byToken as $s) {
            if ($s->getUserId() === $userId && !$s->isRevoked()) {
                $s->revoke();
                $count++;
            }
        }
        return $count;
    }

    public function deleteExpired(\DateTimeImmutable $before): int
    {
        $count = 0;
        foreach ($this->byToken as $t => $s) {
            if ($s->getExpiresAt() < $before) {
                unset($this->byToken[$t]);
                $count++;
            }
        }
        return $count;
    }
}
