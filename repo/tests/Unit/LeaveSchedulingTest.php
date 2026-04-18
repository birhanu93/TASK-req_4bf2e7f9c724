<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\SupervisorLeave;
use App\Exception\ConflictException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class LeaveSchedulingTest extends TestCase
{
    public function testOneOffLeaveBlocksOverlappingSession(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', ['supervisor']);
        $supId = $k->users->findByUsername('sup')->getId();
        $k->scheduling->addLeave(
            $supId,
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T17:00:00+00:00'),
        );
        $this->expectException(ConflictException::class);
        $k->scheduling->create(
            $supId,
            'overlap',
            new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T11:00:00+00:00'),
            5,
        );
    }

    public function testNonOverlappingSessionAllowed(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', ['supervisor']);
        $supId = $k->users->findByUsername('sup')->getId();
        $k->scheduling->addLeave(
            $supId,
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T17:00:00+00:00'),
        );
        $session = $k->scheduling->create(
            $supId,
            'after leave',
            new \DateTimeImmutable('2026-05-02T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-02T10:00:00+00:00'),
            5,
        );
        self::assertNotNull($session->getId());
    }

    public function testWeeklyRecurrenceCoversFutureDays(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', ['supervisor']);
        $supId = $k->users->findByUsername('sup')->getId();
        // Friday leave 2026-05-01 recurring weekly.
        $k->scheduling->addLeave(
            $supId,
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T17:00:00+00:00'),
            SupervisorLeave::RULE_WEEKLY,
        );
        $this->expectException(ConflictException::class);
        $k->scheduling->create(
            $supId,
            'same weekday',
            new \DateTimeImmutable('2026-05-15T10:00:00+00:00'),
            new \DateTimeImmutable('2026-05-15T11:00:00+00:00'),
            5,
        );
    }

    public function testLeaveCannotOverlapExistingSession(): void
    {
        $k = Factory::kernel();
        $k->auth->register('sup', 'pw-12345', ['supervisor']);
        $supId = $k->users->findByUsername('sup')->getId();
        $k->scheduling->create(
            $supId,
            'existing',
            new \DateTimeImmutable('2026-05-01T09:00:00+00:00'),
            new \DateTimeImmutable('2026-05-01T10:00:00+00:00'),
            5,
        );
        $this->expectException(ConflictException::class);
        $k->scheduling->addLeave(
            $supId,
            new \DateTimeImmutable('2026-05-01T09:30:00+00:00'),
            new \DateTimeImmutable('2026-05-01T10:30:00+00:00'),
        );
    }
}
