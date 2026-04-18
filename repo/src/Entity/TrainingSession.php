<?php

declare(strict_types=1);

namespace App\Entity;

final class TrainingSession
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public function __construct(
        private string $id,
        private string $supervisorId,
        private string $title,
        private \DateTimeImmutable $startsAt,
        private \DateTimeImmutable $endsAt,
        private int $capacity,
        private int $bufferMinutes = 10,
        private string $status = self::STATUS_OPEN,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSupervisorId(): string
    {
        return $this->supervisorId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function getBufferMinutes(): int
    {
        return $this->bufferMinutes;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function close(): void
    {
        $this->status = self::STATUS_CLOSED;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
