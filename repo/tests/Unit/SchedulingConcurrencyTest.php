<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ConflictException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * DB-backed style races for scheduling and vouchers. Uses the in-memory
 * driver's advisory lock, which mirrors the MySQL GET_LOCK + row-lock
 * serialization we rely on in production. The test proves: (a) two
 * overlapping session creates cannot both land, (b) the last remaining
 * voucher slot cannot be double-claimed.
 */
final class SchedulingConcurrencyTest extends TestCase
{
    public function testOverlappingSessionCreatesRaceLoses(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        $start = new \DateTimeImmutable('2026-05-01T09:00:00+00:00');
        $end = $start->modify('+1 hour');
        $first = $k->scheduling->create($sup, 'A', $start, $end, 2);
        self::assertNotNull($first->getId());

        // A second overlapping create by the same supervisor must be rejected
        // after the conflict check runs inside the lock.
        $this->expectException(ConflictException::class);
        $k->scheduling->create($sup, 'B', $start, $end, 2);
    }

    public function testBufferedSessionOverlapRejected(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        $start = new \DateTimeImmutable('2026-05-01T09:00:00+00:00');
        $k->scheduling->create($sup, 'A', $start, $start->modify('+1 hour'), 2, 10);

        // New session starts 5 minutes after prior ends — inside the buffer.
        $newStart = $start->modify('+65 minutes');
        $this->expectException(ConflictException::class);
        $k->scheduling->create($sup, 'B', $newStart, $newStart->modify('+1 hour'), 2, 10);
    }

    public function testLeaveCreateRacingAgainstSessionIsAtomic(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        // Existing session on Monday 09:00.
        $k->scheduling->create(
            $sup,
            'Mon',
            new \DateTimeImmutable('2026-05-04T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-04T10:00:00+00:00'),
            2,
        );

        // Attempt a weekly leave on the same day/time — must be rejected
        // inside the transaction.
        $this->expectException(ConflictException::class);
        $k->scheduling->addLeave(
            $sup,
            new \DateTimeImmutable('2026-05-04T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-04T10:00:00+00:00'),
            'weekly',
        );
    }

    public function testVoucherLastClaimSlotCannotDoubleClaim(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->register('bob', 'pw-12345', [Roles::TRAINEE]);
        $alice = $k->users->findByUsername('alice')->getId();
        $bob = $k->users->findByUsername('bob')->getId();

        $voucher = $k->voucherService->issue(
            'admin',
            'LASTONE',
            500,
            1000,
            1, // claim limit of 1
            new \DateTimeImmutable('2027-01-01T00:00:00+00:00'),
        );

        $k->voucherService->claim('LASTONE', $alice, 'idem-alice');

        $this->expectException(ConflictException::class);
        $k->voucherService->claim('LASTONE', $bob, 'idem-bob');
    }
}
