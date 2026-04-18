<?php

declare(strict_types=1);

namespace App\Entity;

final class AuditLog
{
    public function __construct(
        private string $id,
        private string $actorId,
        private string $action,
        private string $entityType,
        private string $entityId,
        private \DateTimeImmutable $occurredAt,
        /** @var array<string,mixed> */
        private array $before = [],
        /** @var array<string,mixed> */
        private array $after = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getActorId(): string
    {
        return $this->actorId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string,mixed>
     */
    public function getBefore(): array
    {
        return $this->before;
    }

    /**
     * @return array<string,mixed>
     */
    public function getAfter(): array
    {
        return $this->after;
    }
}
