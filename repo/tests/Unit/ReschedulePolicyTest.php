<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ConflictException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Reschedule must honour the same 12-hour window as direct cancellation so
 * the endpoint cannot be used to quietly bypass the policy.
 */
final class ReschedulePolicyTest extends TestCase
{
    public function testRescheduleWithinWindowWithoutOverrideFails(): void
    {
        $k = Factory::kernel(new \DateTimeImmutable('2026-04-18T10:00:00+00:00'));
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $sup = $k->users->findByUsername('sup')->getId();
        $alice = $k->users->findByUsername('alice')->getId();

        // Session starts in 4 hours — inside the 12h block window.
        $soon = $k->scheduling->create(
            $sup,
            'soon',
            new \DateTimeImmutable('2026-04-18T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-18T15:00:00+00:00'),
            2,
        );
        // Another session well outside the window to reschedule into.
        $later = $k->scheduling->create(
            $sup,
            'later',
            new \DateTimeImmutable('2026-04-20T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-20T15:00:00+00:00'),
            2,
        );

        $booking = $k->bookingService->book($soon->getId(), $alice);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessageMatches('/12 hour/');
        $k->bookingService->reschedule($booking->getId(), $later->getId(), $alice);
    }

    public function testRescheduleWithinWindowRequiresAdminOverride(): void
    {
        $k = Factory::kernel(new \DateTimeImmutable('2026-04-18T10:00:00+00:00'));
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $k->auth->bootstrapAdmin('root', 'pw-12345');
        $sup = $k->users->findByUsername('sup')->getId();
        $alice = $k->users->findByUsername('alice')->getId();
        $admin = $k->users->findByUsername('root')->getId();

        $soon = $k->scheduling->create(
            $sup,
            'soon',
            new \DateTimeImmutable('2026-04-18T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-18T15:00:00+00:00'),
            2,
        );
        $later = $k->scheduling->create(
            $sup,
            'later',
            new \DateTimeImmutable('2026-04-20T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-20T15:00:00+00:00'),
            2,
        );

        $booking = $k->bookingService->book($soon->getId(), $alice);
        $rescheduled = $k->bookingService->reschedule(
            $booking->getId(),
            $later->getId(),
            $admin,
            null,
            true,
            'operational override',
        );
        self::assertSame($later->getId(), $rescheduled->getSessionId());
    }

    public function testRescheduleOutsideWindowSucceedsWithoutOverride(): void
    {
        $k = Factory::kernel(new \DateTimeImmutable('2026-04-18T10:00:00+00:00'));
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $sup = $k->users->findByUsername('sup')->getId();
        $alice = $k->users->findByUsername('alice')->getId();

        // 48 hours out — safely outside the 12h block.
        $far = $k->scheduling->create(
            $sup,
            'far',
            new \DateTimeImmutable('2026-04-20T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-20T15:00:00+00:00'),
            2,
        );
        $other = $k->scheduling->create(
            $sup,
            'other',
            new \DateTimeImmutable('2026-04-21T14:00:00+00:00'),
            new \DateTimeImmutable('2026-04-21T15:00:00+00:00'),
            2,
        );

        $booking = $k->bookingService->book($far->getId(), $alice);
        $rescheduled = $k->bookingService->reschedule($booking->getId(), $other->getId(), $alice);
        self::assertSame($other->getId(), $rescheduled->getSessionId());
    }
}
