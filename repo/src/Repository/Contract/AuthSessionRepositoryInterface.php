<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\AuthSessionRecord;

interface AuthSessionRepositoryInterface
{
    public function save(AuthSessionRecord $session): void;

    public function findByToken(string $token): ?AuthSessionRecord;

    public function revoke(string $token): void;

    public function revokeByUser(string $userId): int;

    public function deleteExpired(\DateTimeImmutable $before): int;
}
