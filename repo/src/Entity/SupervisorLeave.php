<?php

declare(strict_types=1);

namespace App\Entity;

final class SupervisorLeave
{
    public const RULE_WEEKLY = 'weekly';
    public const RULE_MONTHLY = 'monthly';
    public const RULE_ONE_OFF = 'one_off';

    public function __construct(
        private string $id,
        private string $supervisorId,
        private \DateTimeImmutable $startsAt,
        private \DateTimeImmutable $endsAt,
        private string $recurrenceRule = self::RULE_ONE_OFF,
        private ?string $reason = null,
        private ?\DateTimeImmutable $createdAt = null,
    ) {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('leave end must be after start');
        }
        if (!in_array($recurrenceRule, [self::RULE_WEEKLY, self::RULE_MONTHLY, self::RULE_ONE_OFF], true)) {
            throw new \InvalidArgumentException('invalid recurrence rule');
        }
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSupervisorId(): string
    {
        return $this->supervisorId;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getRecurrenceRule(): string
    {
        return $this->recurrenceRule;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * True when the leave covers the instant, projecting recurrence. Preserved
     * for callers that only want a point check.
     */
    public function covers(\DateTimeImmutable $at): bool
    {
        foreach ($this->occurrencesIn($at, $at->modify('+1 second')) as [$start, $end]) {
            if ($at >= $start && $at < $end) {
                return true;
            }
        }
        return false;
    }

    /**
     * Exact overlap between the leave (including all projected occurrences
     * within the window [from, to)) and the interval [from, to). Weekly and
     * monthly leaves project forward indefinitely; one-off leaves evaluate a
     * single window.
     */
    public function overlaps(\DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        if ($from >= $to) {
            return false;
        }
        foreach ($this->occurrencesIn($from, $to) as [$occStart, $occEnd]) {
            if ($occStart < $to && $occEnd > $from) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return iterable<int,array{0:\DateTimeImmutable,1:\DateTimeImmutable}>
     */
    public function occurrencesIn(\DateTimeImmutable $from, \DateTimeImmutable $to): iterable
    {
        $durationSeconds = $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp();

        if ($this->recurrenceRule === self::RULE_ONE_OFF) {
            yield [$this->startsAt, $this->endsAt];
            return;
        }

        // Walk occurrences starting from the anchor. Skip forward to the first
        // occurrence whose end is on or after $from so we don't iterate from
        // 1970 for every check. A hard cap prevents pathological infinite
        // loops if a rule were ever misconfigured.
        $cursor = $this->startsAt;
        $step = $this->recurrenceRule === self::RULE_WEEKLY ? '+7 days' : '+1 month';

        $iter = 0;
        // First, fast-forward up to the window.
        while ($cursor->getTimestamp() + $durationSeconds <= $from->getTimestamp()) {
            if ($iter++ > 2000) {
                return;
            }
            $cursor = $cursor->modify($step);
        }

        while ($cursor < $to) {
            if ($iter++ > 2000) {
                return;
            }
            $occEnd = $cursor->modify("+{$durationSeconds} seconds");
            yield [$cursor, $occEnd];
            $cursor = $cursor->modify($step);
        }
    }
}
