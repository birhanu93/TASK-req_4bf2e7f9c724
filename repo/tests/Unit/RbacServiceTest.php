<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\AccessDeniedException;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SessionContext;
use PHPUnit\Framework\TestCase;

final class RbacServiceTest extends TestCase
{
    private function ctx(string $role): SessionContext
    {
        return new SessionContext('u1', $role, 'tok', new \DateTimeImmutable());
    }

    public function testAuthorizesPermittedRole(): void
    {
        $r = new RbacService();
        $r->authorize($this->ctx(Roles::ADMIN), 'voucher.create');
        self::assertContains(Roles::ADMIN, $r->rolesFor('voucher.create'));
    }

    public function testDeniesWrongRole(): void
    {
        $r = new RbacService();
        $this->expectException(AccessDeniedException::class);
        $r->authorize($this->ctx(Roles::TRAINEE), 'voucher.create');
    }

    public function testDeniesUnknownAction(): void
    {
        $r = new RbacService();
        $this->expectException(AccessDeniedException::class);
        $r->authorize($this->ctx(Roles::ADMIN), 'unknown.action');
    }

    public function testRolesForUnknownAction(): void
    {
        self::assertSame([], (new RbacService())->rolesFor('ghost'));
    }
}
