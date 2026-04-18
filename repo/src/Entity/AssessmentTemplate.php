<?php

declare(strict_types=1);

namespace App\Entity;

final class AssessmentTemplate
{
    public const MODE_TIME = 'time';
    public const MODE_REP = 'rep';
    public const MODE_COMBINED = 'combined';

    public function __construct(
        private string $id,
        private string $name,
        private string $mode,
        private int $targetReps = 0,
        private int $targetSeconds = 0,
    ) {
        if (!in_array($mode, [self::MODE_TIME, self::MODE_REP, self::MODE_COMBINED], true)) {
            throw new \InvalidArgumentException('invalid assessment mode');
        }
        if ($mode === self::MODE_REP && $targetReps <= 0) {
            throw new \InvalidArgumentException('rep mode requires positive targetReps');
        }
        if ($mode === self::MODE_TIME && $targetSeconds <= 0) {
            throw new \InvalidArgumentException('time mode requires positive targetSeconds');
        }
        if ($mode === self::MODE_COMBINED && ($targetReps <= 0 || $targetSeconds <= 0)) {
            throw new \InvalidArgumentException('combined mode requires positive targets');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getTargetReps(): int
    {
        return $this->targetReps;
    }

    public function getTargetSeconds(): int
    {
        return $this->targetSeconds;
    }
}
