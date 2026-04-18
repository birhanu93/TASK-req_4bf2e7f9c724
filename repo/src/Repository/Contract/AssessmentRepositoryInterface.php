<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Assessment;

interface AssessmentRepositoryInterface
{
    public function save(Assessment $assessment): void;

    public function find(string $id): ?Assessment;

    /** @return Assessment[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return Assessment[] */
    public function findByTrainee(string $traineeId): array;
}
