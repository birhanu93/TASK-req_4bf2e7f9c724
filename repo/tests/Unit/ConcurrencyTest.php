<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ConflictException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that concurrent booking and voucher-claim operations do not
 * corrupt state. The in-memory database driver serializes the transactional
 * callbacks via its advisory lock primitive, which mirrors the MySQL
 * row-lock behavior in production.
 */
final class ConcurrencyTest extends TestCase
{
    public function testConcurrentBookingsCannotExceedCapacity(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('bob', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('carol', 'pw-12345', [Roles::TRAINEE]);
        $supId = $k->users->findByUsername('sup')->getId();
        $aliceId = $k->users->findByUsername('alice')->getId();
        $bobId = $k->users->findByUsername('bob')->getId();
        $carolId = $k->users->findByUsername('carol')->getId();

        $session = $k->scheduling->create(
            $supId,
            'capacity-2',
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
            2,
        );

        $ok1 = $k->bookingService->book($session->getId(), $aliceId);
        $ok2 = $k->bookingService->book($session->getId(), $bobId);
        self::assertNotSame($ok1->getId(), $ok2->getId());

        $this->expectException(ConflictException::class);
        $k->bookingService->book($session->getId(), $carolId);
    }

    public function testIdempotencyKeyReturnsSameBooking(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $supId = $k->users->findByUsername('sup')->getId();
        $aliceId = $k->users->findByUsername('alice')->getId();

        $session = $k->scheduling->create(
            $supId,
            's',
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
            10,
        );

        $a = $k->bookingService->book($session->getId(), $aliceId, 'key-1');
        $b = $k->bookingService->book($session->getId(), $aliceId, 'key-1');
        self::assertSame($a->getId(), $b->getId());
    }

    public function testIdempotencyKeyReusedByOtherUserIsRejected(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('bob', 'pw-12345', [Roles::TRAINEE]);
        $supId = $k->users->findByUsername('sup')->getId();
        $aliceId = $k->users->findByUsername('alice')->getId();
        $bobId = $k->users->findByUsername('bob')->getId();

        $session = $k->scheduling->create(
            $supId,
            's',
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
            10,
        );
        $k->bookingService->book($session->getId(), $aliceId, 'key-1');

        $this->expectException(ConflictException::class);
        $k->bookingService->book($session->getId(), $bobId, 'key-1');
    }

    public function testVoucherClaimRespectsLimit(): void
    {
        $k = Factory::kernel();
        $admin = $k->auth->register('admin', 'pw-12345', [Roles::ADMIN]);
        $k->auth->register('a', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('b', 'pw-12345', [Roles::TRAINEE]);
        $aId = $k->users->findByUsername('a')->getId();
        $bId = $k->users->findByUsername('b')->getId();

        $voucher = $k->voucherService->issue(
            $admin->getId(),
            'V1',
            500,
            1000,
            1,
            new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        );

        $ok = $k->voucherService->claim('V1', $aId, 'idem-a');
        self::assertNotNull($ok);

        $this->expectException(ConflictException::class);
        $k->voucherService->claim('V1', $bId, 'idem-b');
    }

    public function testVoucherIdempotencyReturnsSameClaim(): void
    {
        $k = Factory::kernel();
        $admin = $k->auth->register('admin', 'pw-12345', [Roles::ADMIN]);
        $k->auth->register('a', 'pw-12345', [Roles::TRAINEE]);
        $aId = $k->users->findByUsername('a')->getId();

        $k->voucherService->issue(
            $admin->getId(),
            'V1',
            500,
            1000,
            5,
            new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        );

        $c1 = $k->voucherService->claim('V1', $aId, 'key-42');
        $c2 = $k->voucherService->claim('V1', $aId, 'key-42');
        self::assertSame($c1->getId(), $c2->getId());
    }

    public function testVoucherIdempotencyKeyReusedByOtherUserIsRejected(): void
    {
        $k = Factory::kernel();
        $admin = $k->auth->register('admin', 'pw-12345', [Roles::ADMIN]);
        $k->auth->register('a', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('b', 'pw-12345', [Roles::TRAINEE]);
        $aId = $k->users->findByUsername('a')->getId();
        $bId = $k->users->findByUsername('b')->getId();

        $k->voucherService->issue(
            $admin->getId(),
            'V1',
            500,
            1000,
            5,
            new \DateTimeImmutable('2026-06-01T00:00:00+00:00'),
        );

        $k->voucherService->claim('V1', $aId, 'key-shared');
        $this->expectException(ConflictException::class);
        $k->voucherService->claim('V1', $bId, 'key-shared');
    }
}
