<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Booking;
use App\Repository\Contract\BookingRepositoryInterface;

/**
 * @extends Repository<Booking>
 */
final class BookingRepository extends Repository implements BookingRepositoryInterface
{
    /** @var array<string,string> idempotencyKey => bookingId */
    private array $idempotencyIndex = [];

    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Booking
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function save(object $entity): void
    {
        parent::save($entity);
        if (!$entity instanceof Booking) {
            return;
        }
        $key = $entity->getIdempotencyKey();
        if ($key !== null && $key !== '') {
            $this->idempotencyIndex[$key] = $entity->getId();
        }
    }

    /**
     * @return Booking[]
     */
    public function findActiveBySession(string $sessionId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (Booking $b) => $b->getSessionId() === $sessionId && $b->isActive(),
        ));
    }

    /**
     * @return Booking[]
     */
    public function findByTrainee(string $traineeId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (Booking $b) => $b->getTraineeId() === $traineeId,
        ));
    }

    public function findByIdempotencyKey(string $key): ?Booking
    {
        $id = $this->idempotencyIndex[$key] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->items[$id] ?? null;
    }
}
