<?php

declare(strict_types=1);

namespace App\Entity;

final class GuardianLink
{
    public const MAX_CHILDREN = 5;

    public function __construct(
        private string $id,
        private string $guardianId,
        private string $childId,
        private \DateTimeImmutable $linkedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getGuardianId(): string
    {
        return $this->guardianId;
    }

    public function getChildId(): string
    {
        return $this->childId;
    }

    public function getLinkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
