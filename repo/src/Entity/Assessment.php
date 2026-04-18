<?php

declare(strict_types=1);

namespace App\Entity;

final class Assessment
{
    public function __construct(
        private string $id,
        private string $templateId,
        private string $traineeId,
        private string $supervisorId,
        private int $reps,
        private int $seconds,
        private \DateTimeImmutable $recordedAt,
        private ?string $rankAchieved = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getTraineeId(): string
    {
        return $this->traineeId;
    }

    public function getSupervisorId(): string
    {
        return $this->supervisorId;
    }

    public function getReps(): int
    {
        return $this->reps;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getRankAchieved(): ?string
    {
        return $this->rankAchieved;
    }

    public function setRankAchieved(?string $rank): void
    {
        $this->rankAchieved = $rank;
    }
}
