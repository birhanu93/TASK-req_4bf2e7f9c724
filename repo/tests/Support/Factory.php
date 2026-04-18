<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\App\Kernel;
use App\Entity\User;
use App\Service\FixedClock;
use App\Service\Roles;
use App\Service\SequenceIdGenerator;

final class Factory
{
    public static function kernel(?\DateTimeImmutable $now = null): Kernel
    {
        $clock = new FixedClock($now ?? new \DateTimeImmutable('2026-04-18T10:00:00+00:00'));
        $ids = new SequenceIdGenerator('t');
        $root = sys_get_temp_dir() . '/workforce-test-' . bin2hex(random_bytes(4));
        // Deterministic KEK for tests — still 32 bytes, still exercises the
        // real AES-256-GCM path.
        return new Kernel($clock, $ids, $root, null, str_repeat("\x01", 32));
    }

    /**
     * @param string[] $roles
     */
    public static function user(Kernel $k, string $username, string $password, array $roles): User
    {
        return $k->auth->register($username, $password, $roles, 'test');
    }

    public static function login(Kernel $k, string $username, string $password, string $role): string
    {
        return $k->auth->selectRole($username, $password, $role)->getToken();
    }

    public static function seedAdmin(Kernel $k): string
    {
        $k->auth->bootstrapAdmin('admin', 'admin-pass-1');
        return $k->auth->selectRole('admin', 'admin-pass-1', Roles::ADMIN)->getToken();
    }
}
