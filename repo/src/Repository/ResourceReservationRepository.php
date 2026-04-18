<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResourceReservation;
use App\Repository\Contract\ResourceReservationRepositoryInterface;

/**
 * @extends Repository<ResourceReservation>
 */
final class ResourceReservationRepository extends Repository implements ResourceReservationRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?ResourceReservation
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByResource(string $resourceId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (ResourceReservation $r) => $r->getResourceId() === $resourceId,
        ));
    }

    public function findBySession(string $sessionId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (ResourceReservation $r) => $r->getSessionId() === $sessionId,
        ));
    }

    public function findOverlapping(string $resourceId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return array_values(array_filter(
            $this->items,
            fn (ResourceReservation $r) => $r->getResourceId() === $resourceId && $r->overlaps($start, $end),
        ));
    }
}
