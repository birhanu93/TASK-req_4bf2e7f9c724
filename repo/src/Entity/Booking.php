<?php

declare(strict_types=1);

namespace App\Entity;

final class Booking
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const RESERVATION_TTL_SECONDS = 900;

    public function __construct(
        private string $id,
        private string $sessionId,
        private string $traineeId,
        private \DateTimeImmutable $createdAt,
        private string $status = self::STATUS_RESERVED,
        private ?string $cancellationReason = null,
        private ?string $overrideActorId = null,
        private ?string $idempotencyKey = null,
    ) {
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getTraineeId(): string
    {
        return $this->traineeId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->status = self::STATUS_CONFIRMED;
    }

    public function cancel(string $reason, ?string $overrideActorId = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancellationReason = $reason;
        $this->overrideActorId = $overrideActorId;
    }

    public function expire(): void
    {
        $this->status = self::STATUS_EXPIRED;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function getOverrideActorId(): ?string
    {
        return $this->overrideActorId;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RESERVED, self::STATUS_CONFIRMED], true);
    }
}
