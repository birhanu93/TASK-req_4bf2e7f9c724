<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\TrainingSession;

interface SessionRepositoryInterface
{
    public function save(TrainingSession $session): void;

    public function find(string $id): ?TrainingSession;

    /** @return TrainingSession[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return TrainingSession[] */
    public function findBySupervisor(string $supervisorId): array;

    public function findForUpdate(string $id): ?TrainingSession;
}
