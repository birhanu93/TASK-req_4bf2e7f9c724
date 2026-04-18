<?php

declare(strict_types=1);

namespace App\Service;

final class PasswordHasher
{
    public function hash(string $password): string
    {
        if ($password === '') {
            throw new \InvalidArgumentException('password must not be empty');
        }
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
