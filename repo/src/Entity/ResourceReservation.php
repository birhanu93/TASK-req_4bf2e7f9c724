<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * A time-bounded reservation of a {@see Resource}. Most reservations are
 * created in the same transaction as a training session and carry its id,
 * but administrators may also hold a resource independently (e.g., for
 * maintenance) by passing a null session id.
 */
final class ResourceReservation
{
    public function __construct(
        private string $id,
        private string $resourceId,
        private ?string $sessionId,
        private \DateTimeImmutable $startsAt,
        private \DateTimeImmutable $endsAt,
        private string $reservedByUserId,
        private \DateTimeImmutable $createdAt,
    ) {
        if ($endsAt <= $startsAt) {
            throw new \InvalidArgumentException('reservation end must be after start');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getEndsAt(): \DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function getReservedByUserId(): string
    {
        return $this->reservedByUserId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function overlaps(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        return $start < $this->endsAt && $end > $this->startsAt;
    }
}
