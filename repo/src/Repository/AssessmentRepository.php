<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Assessment;
use App\Repository\Contract\AssessmentRepositoryInterface;

/**
 * @extends Repository<Assessment>
 */
final class AssessmentRepository extends Repository implements AssessmentRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Assessment
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return Assessment[]
     */
    public function findByTrainee(string $traineeId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (Assessment $a) => $a->getTraineeId() === $traineeId,
        ));
    }
}
