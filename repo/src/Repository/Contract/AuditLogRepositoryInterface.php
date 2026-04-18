<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $log): void;

    public function find(string $id): ?AuditLog;

    /** @return AuditLog[] */
    public function findAll(): array;

    /** @return AuditLog[] */
    public function findByEntity(string $entityType, string $entityId): array;

    /** @return AuditLog[] */
    public function findByActor(string $actorId, int $limit = 100): array;
}
