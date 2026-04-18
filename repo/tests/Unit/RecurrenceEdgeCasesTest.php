<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\SupervisorLeave;
use PHPUnit\Framework\TestCase;

/**
 * Exact recurrence-overlap math. The hour-sampled implementation that
 * preceded this change missed short leaves and mis-handled boundary cases
 * (edges, DST-like jumps, monthly rollover). These tests lock the semantics
 * down.
 */
final class RecurrenceEdgeCasesTest extends TestCase
{
    public function testWeeklyLeaveShorterThanSampleInterval(): void
    {
        // 15-minute weekly leave — the prior "+1 hour" sampler would miss
        // occurrences entirely. Exact occurrence walk must catch it.
        $start = new \DateTimeImmutable('2026-04-06T09:00:00+00:00'); // Monday
        $leave = new SupervisorLeave(
            'l1',
            'sup1',
            $start,
            $start->modify('+15 minutes'),
            SupervisorLeave::RULE_WEEKLY,
        );

        // A session the following Monday at 09:05 for 10 minutes must conflict.
        $from = new \DateTimeImmutable('2026-04-13T09:05:00+00:00');
        $to = new \DateTimeImmutable('2026-04-13T09:15:00+00:00');
        self::assertTrue($leave->overlaps($from, $to));

        // Tuesday same week must not conflict.
        $from2 = new \DateTimeImmutable('2026-04-14T09:00:00+00:00');
        $to2 = new \DateTimeImmutable('2026-04-14T10:00:00+00:00');
        self::assertFalse($leave->overlaps($from2, $to2));
    }

    public function testBoundaryConditionIsHalfOpen(): void
    {
        // Leave ends at 10:00; a session that starts exactly at 10:00 must not
        // conflict (the interval is half-open: [start, end)).
        $leave = new SupervisorLeave(
            'l2',
            'sup1',
            new \DateTimeImmutable('2026-04-06T09:00:00+00:00'),
            new \DateTimeImmutable('2026-04-06T10:00:00+00:00'),
            SupervisorLeave::RULE_ONE_OFF,
        );
        $from = new \DateTimeImmutable('2026-04-06T10:00:00+00:00');
        $to = new \DateTimeImmutable('2026-04-06T11:00:00+00:00');
        self::assertFalse($leave->overlaps($from, $to));

        // One second earlier overlaps.
        $from2 = new \DateTimeImmutable('2026-04-06T09:59:59+00:00');
        $to2 = new \DateTimeImmutable('2026-04-06T11:00:00+00:00');
        self::assertTrue($leave->overlaps($from2, $to2));
    }

    public function testMonthlyLeaveRollsOverCorrectly(): void
    {
        $leave = new SupervisorLeave(
            'l3',
            'sup1',
            new \DateTimeImmutable('2026-01-15T09:00:00+00:00'),
            new \DateTimeImmutable('2026-01-15T10:00:00+00:00'),
            SupervisorLeave::RULE_MONTHLY,
        );
        // June 15 must match at the same wall clock time.
        $from = new \DateTimeImmutable('2026-06-15T09:30:00+00:00');
        $to = new \DateTimeImmutable('2026-06-15T09:45:00+00:00');
        self::assertTrue($leave->overlaps($from, $to));

        // June 16 must not match.
        $from2 = new \DateTimeImmutable('2026-06-16T09:30:00+00:00');
        $to2 = new \DateTimeImmutable('2026-06-16T09:45:00+00:00');
        self::assertFalse($leave->overlaps($from2, $to2));
    }

    public function testWeeklyLeaveDoesNotSpillToOtherDays(): void
    {
        $leave = new SupervisorLeave(
            'l4',
            'sup1',
            new \DateTimeImmutable('2026-04-06T09:00:00+00:00'), // Monday
            new \DateTimeImmutable('2026-04-06T10:00:00+00:00'),
            SupervisorLeave::RULE_WEEKLY,
        );

        $from = new \DateTimeImmutable('2026-04-07T09:00:00+00:00'); // Tuesday
        $to = new \DateTimeImmutable('2026-04-07T10:00:00+00:00');
        self::assertFalse($leave->overlaps($from, $to));
    }

    public function testOccurrencesInStopsAtWindow(): void
    {
        $leave = new SupervisorLeave(
            'l5',
            'sup1',
            new \DateTimeImmutable('2026-04-06T09:00:00+00:00'),
            new \DateTimeImmutable('2026-04-06T10:00:00+00:00'),
            SupervisorLeave::RULE_WEEKLY,
        );
        $occ = iterator_to_array($leave->occurrencesIn(
            new \DateTimeImmutable('2026-04-06T00:00:00+00:00'),
            new \DateTimeImmutable('2026-04-28T00:00:00+00:00'),
        ));
        // Expect occurrences on April 6, 13, 20, 27 — 4 in total.
        self::assertCount(4, $occ);
    }
}
