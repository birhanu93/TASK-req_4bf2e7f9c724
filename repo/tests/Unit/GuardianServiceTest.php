<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\GuardianLink;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class GuardianServiceTest extends TestCase
{
    public function testLinkAndList(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'guardian', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'child', 'password-1', [Roles::TRAINEE]);
        $link = $k->guardianService->linkChild($g->getId(), $c->getId());
        self::assertSame($c->getId(), $link->getChildId());
        self::assertCount(1, $k->guardianService->childrenOf($g->getId()));
    }

    public function testLinkSelfBlocked(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->guardianService->linkChild('same', 'same');
    }

    public function testLinkMissingGuardian(): void
    {
        $k = Factory::kernel();
        $c = Factory::user($k, 'child', 'password-1', [Roles::TRAINEE]);
        $this->expectException(NotFoundException::class);
        $k->guardianService->linkChild('ghost', $c->getId());
    }

    public function testLinkMissingChild(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'guardian', 'password-1', [Roles::GUARDIAN]);
        $this->expectException(NotFoundException::class);
        $k->guardianService->linkChild($g->getId(), 'ghost');
    }

    public function testLinkDuplicate(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'c', 'password-1', [Roles::TRAINEE]);
        $k->guardianService->linkChild($g->getId(), $c->getId());
        $this->expectException(ConflictException::class);
        $k->guardianService->linkChild($g->getId(), $c->getId());
    }

    public function testMaxChildren(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        for ($i = 0; $i < GuardianLink::MAX_CHILDREN; $i++) {
            $c = Factory::user($k, "c{$i}", 'password-1', [Roles::TRAINEE]);
            $k->guardianService->linkChild($g->getId(), $c->getId());
        }
        $extra = Factory::user($k, 'extra', 'password-1', [Roles::TRAINEE]);
        $this->expectException(ConflictException::class);
        $k->guardianService->linkChild($g->getId(), $extra->getId());
    }

    public function testApproveDevice(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'c', 'password-1', [Roles::TRAINEE]);
        $k->guardianService->linkChild($g->getId(), $c->getId());
        $d = $k->guardianService->approveDevice($g->getId(), $c->getId(), 'iPad', 'fp-1');
        self::assertTrue($d->isApproved());
        self::assertNotNull($d->getSessionToken());
        self::assertCount(1, $k->guardianService->devicesOf($c->getId()));
    }

    public function testApproveDeviceNoLink(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->guardianService->approveDevice('g', 'c', 'iPad', 'fp');
    }

    public function testApproveDeviceDuplicate(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'c', 'password-1', [Roles::TRAINEE]);
        $k->guardianService->linkChild($g->getId(), $c->getId());
        $k->guardianService->approveDevice($g->getId(), $c->getId(), 'iPad', 'fp-1');
        $this->expectException(ConflictException::class);
        $k->guardianService->approveDevice($g->getId(), $c->getId(), 'iPad', 'fp-1');
    }

    public function testRemoteLogout(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'c', 'password-1', [Roles::TRAINEE]);
        $k->guardianService->linkChild($g->getId(), $c->getId());
        $d = $k->guardianService->approveDevice($g->getId(), $c->getId(), 'iPad', 'fp-1');
        $revoked = $k->guardianService->remoteLogout($g->getId(), $d->getId());
        self::assertFalse($revoked->isApproved());
    }

    public function testRemoteLogoutMissingDevice(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->guardianService->remoteLogout('g', 'd');
    }

    public function testRemoteLogoutNoLink(): void
    {
        $k = Factory::kernel();
        $g = Factory::user($k, 'g', 'password-1', [Roles::GUARDIAN]);
        $c = Factory::user($k, 'c', 'password-1', [Roles::TRAINEE]);
        $other = Factory::user($k, 'other', 'password-1', [Roles::GUARDIAN]);
        $k->guardianService->linkChild($g->getId(), $c->getId());
        $d = $k->guardianService->approveDevice($g->getId(), $c->getId(), 'iPad', 'fp-1');
        $this->expectException(NotFoundException::class);
        $k->guardianService->remoteLogout($other->getId(), $d->getId());
    }
}
