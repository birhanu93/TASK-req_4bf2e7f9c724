<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\ResourceReservation;

interface ResourceReservationRepositoryInterface
{
    public function save(ResourceReservation $reservation): void;

    public function find(string $id): ?ResourceReservation;

    /** @return ResourceReservation[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return ResourceReservation[] */
    public function findByResource(string $resourceId): array;

    /** @return ResourceReservation[] */
    public function findBySession(string $sessionId): array;

    /**
     * Return reservations on {@see $resourceId} whose window overlaps the
     * [start, end) interval. Implementations are free to short-circuit if
     * their backend supports range indexes.
     *
     * @return ResourceReservation[]
     */
    public function findOverlapping(string $resourceId, \DateTimeImmutable $start, \DateTimeImmutable $end): array;
}
