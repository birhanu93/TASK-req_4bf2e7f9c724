<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class SchedulingServiceTest extends TestCase
{
    public function testCreateAndClose(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $s = $k->scheduling->create('sup1', 'Lift', $start, $start->modify('+1 hour'), 5);
        self::assertTrue($s->isOpen());
        $k->scheduling->close($s->getId());
        self::assertFalse($s->isOpen());
        self::assertSame($s, $k->scheduling->findSession($s->getId()));
    }

    public function testEndBeforeStart(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $this->expectException(ValidationException::class);
        $k->scheduling->create('sup1', 'X', $start, $start, 5);
    }

    public function testCapacityMustBePositive(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $this->expectException(ValidationException::class);
        $k->scheduling->create('sup1', 'X', $start, $start->modify('+1 hour'), 0);
    }

    public function testNegativeBuffer(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $this->expectException(ValidationException::class);
        $k->scheduling->create('sup1', 'X', $start, $start->modify('+1 hour'), 5, -1);
    }

    public function testOverlapThrows(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $k->scheduling->create('sup1', 'A', $start, $start->modify('+1 hour'), 5);
        $this->expectException(ConflictException::class);
        $k->scheduling->create('sup1', 'B', $start->modify('+30 minutes'), $start->modify('+90 minutes'), 5);
    }

    public function testClosedSessionDoesNotBlockNew(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $s = $k->scheduling->create('sup1', 'A', $start, $start->modify('+1 hour'), 5);
        $k->scheduling->close($s->getId());
        $k->scheduling->create('sup1', 'B', $start, $start->modify('+1 hour'), 5);
        self::assertCount(2, $k->sessions->findBySupervisor('sup1'));
    }

    public function testListAvailable(): void
    {
        $k = Factory::kernel();
        $start = new \DateTimeImmutable('2026-05-01T10:00:00+00:00');
        $k->scheduling->create('sup1', 'A', $start, $start->modify('+1 hour'), 5);
        $past = $k->scheduling->create('sup2', 'Old', new \DateTimeImmutable('2024-01-01'), new \DateTimeImmutable('2024-01-01T01:00:00+00:00'), 5);
        self::assertCount(1, $k->scheduling->listAvailable(new \DateTimeImmutable('2025-01-01')));
        $k->scheduling->close($past->getId());
    }

    public function testCloseMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->scheduling->close('nope');
    }

    public function testFindMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->scheduling->findSession('nope');
    }
}
