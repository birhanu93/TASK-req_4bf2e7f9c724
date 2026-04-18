<?php

declare(strict_types=1);

namespace App\Service;

final class Roles
{
    public const TRAINEE = 'trainee';
    public const SUPERVISOR = 'supervisor';
    public const GUARDIAN = 'guardian';
    public const EMPLOYER = 'employer';
    public const ADMIN = 'admin';

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return [self::TRAINEE, self::SUPERVISOR, self::GUARDIAN, self::EMPLOYER, self::ADMIN];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }
}
