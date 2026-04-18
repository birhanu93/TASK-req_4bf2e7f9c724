<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SupervisorLeave;
use App\Repository\Contract\LeaveRepositoryInterface;

/**
 * @extends Repository<SupervisorLeave>
 */
final class LeaveRepository extends Repository implements LeaveRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?SupervisorLeave
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return SupervisorLeave[]
     */
    public function findBySupervisor(string $supervisorId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (SupervisorLeave $l) => $l->getSupervisorId() === $supervisorId,
        ));
    }
}
