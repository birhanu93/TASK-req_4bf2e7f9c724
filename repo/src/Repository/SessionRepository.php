<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TrainingSession;
use App\Repository\Contract\SessionRepositoryInterface;

/**
 * @extends Repository<TrainingSession>
 */
final class SessionRepository extends Repository implements SessionRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?TrainingSession
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return TrainingSession[]
     */
    public function findBySupervisor(string $supervisorId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (TrainingSession $s) => $s->getSupervisorId() === $supervisorId,
        ));
    }

    public function findForUpdate(string $id): ?TrainingSession
    {
        // In-memory has no lock semantics beyond the caller's own advisory
        // lock; returns the live object.
        return $this->find($id);
    }
}
