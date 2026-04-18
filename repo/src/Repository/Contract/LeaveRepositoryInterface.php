<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\SupervisorLeave;

interface LeaveRepositoryInterface
{
    public function save(SupervisorLeave $leave): void;

    public function find(string $id): ?SupervisorLeave;

    /** @return SupervisorLeave[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return SupervisorLeave[] */
    public function findBySupervisor(string $supervisorId): array;
}
