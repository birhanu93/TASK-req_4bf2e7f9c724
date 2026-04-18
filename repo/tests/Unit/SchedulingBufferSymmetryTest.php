<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ConflictException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * The buffer between two sessions must respect the larger of the existing
 * session's buffer and the incoming session's buffer. A tight new session
 * cannot disregard an existing session's cool-down; a wide new session
 * cannot ignore an existing session whose buffer is smaller.
 */
final class SchedulingBufferSymmetryTest extends TestCase
{
    public function testIncomingTightBufferCannotIgnoreExistingBuffer(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        // Existing session with a 30-minute buffer.
        $k->scheduling->create(
            $sup,
            'Existing',
            new \DateTimeImmutable('2026-06-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-06-01T10:00:00+00:00'),
            2,
            30,
        );

        // New session starts 15 min after existing ends, with its own buffer
        // of 0 — must still conflict because the existing 30 min wins.
        $this->expectException(ConflictException::class);
        $k->scheduling->create(
            $sup,
            'New',
            new \DateTimeImmutable('2026-06-01T10:15:00+00:00'),
            new \DateTimeImmutable('2026-06-01T11:00:00+00:00'),
            2,
            0,
        );
    }

    public function testIncomingWiderBufferCannotIgnoreSmallerExistingBuffer(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        // Existing session with only a 5-minute buffer.
        $k->scheduling->create(
            $sup,
            'Existing',
            new \DateTimeImmutable('2026-06-02T09:00:00+00:00'),
            new \DateTimeImmutable('2026-06-02T10:00:00+00:00'),
            2,
            5,
        );

        // New session starts 20 min after existing ends; on its own a
        // 30-minute buffer would not conflict at 20 min gap — but with the
        // incoming buffer of 30 min the 20 min gap is still inside its
        // padding, so this conflict must fire.
        $this->expectException(ConflictException::class);
        $k->scheduling->create(
            $sup,
            'New',
            new \DateTimeImmutable('2026-06-02T10:20:00+00:00'),
            new \DateTimeImmutable('2026-06-02T11:00:00+00:00'),
            2,
            30,
        );
    }

    public function testGapLargerThanBothBuffersIsAccepted(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $sup = $k->users->findByUsername('sup')->getId();

        $k->scheduling->create(
            $sup,
            'Existing',
            new \DateTimeImmutable('2026-06-03T09:00:00+00:00'),
            new \DateTimeImmutable('2026-06-03T10:00:00+00:00'),
            2,
            15,
        );

        // 40-min gap, both buffers 15 — clear of both.
        $second = $k->scheduling->create(
            $sup,
            'New',
            new \DateTimeImmutable('2026-06-03T10:40:00+00:00'),
            new \DateTimeImmutable('2026-06-03T11:30:00+00:00'),
            2,
            15,
        );
        self::assertNotNull($second->getId());
    }
}
