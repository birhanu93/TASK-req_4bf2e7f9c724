<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\FixedClock;
use App\Service\SequenceIdGenerator;
use App\Service\SystemClock;
use App\Service\UuidGenerator;
use PHPUnit\Framework\TestCase;

final class ClockAndIdTest extends TestCase
{
    public function testSystemClock(): void
    {
        $clock = new SystemClock();
        self::assertInstanceOf(\DateTimeImmutable::class, $clock->now());
    }

    public function testFixedClockAdvance(): void
    {
        $start = new \DateTimeImmutable('2026-04-18T10:00:00+00:00');
        $clock = new FixedClock($start);
        self::assertSame($start, $clock->now());
        $clock->advance(60);
        self::assertSame($start->getTimestamp() + 60, $clock->now()->getTimestamp());
        $new = new \DateTimeImmutable('2027-01-01');
        $clock->set($new);
        self::assertSame($new, $clock->now());
    }

    public function testUuid(): void
    {
        $gen = new UuidGenerator();
        $u1 = $gen->generate();
        $u2 = $gen->generate();
        self::assertNotSame($u1, $u2);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $u1);
    }

    public function testSequenceId(): void
    {
        $gen = new SequenceIdGenerator('z');
        self::assertSame('z-000001', $gen->generate());
        self::assertSame('z-000002', $gen->generate());
    }
}
