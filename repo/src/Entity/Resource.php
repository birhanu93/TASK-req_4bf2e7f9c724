<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * A shared schedulable asset — a training room, a piece of equipment, a
 * vehicle. Resources are reserved alongside a training session so the
 * scheduler can detect double-booking against non-staff assets.
 */
final class Resource
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RETIRED = 'retired';

    public function __construct(
        private string $id,
        private string $name,
        private string $kind,
        private \DateTimeImmutable $createdAt,
        private string $status = self::STATUS_ACTIVE,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function retire(): void
    {
        $this->status = self::STATUS_RETIRED;
    }
}
