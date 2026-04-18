<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Repository\Contract\AuditLogRepositoryInterface;

/**
 * @extends Repository<AuditLog>
 */
final class AuditLogRepository extends Repository implements AuditLogRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?AuditLog
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return AuditLog[]
     */
    public function findByEntity(string $entityType, string $entityId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (AuditLog $log) => $log->getEntityType() === $entityType && $log->getEntityId() === $entityId,
        ));
    }

    /**
     * @return AuditLog[]
     */
    public function findByActor(string $actorId, int $limit = 100): array
    {
        $matched = array_values(array_filter(
            $this->items,
            fn (AuditLog $log) => $log->getActorId() === $actorId,
        ));
        usort($matched, fn (AuditLog $a, AuditLog $b) => $b->getOccurredAt() <=> $a->getOccurredAt());
        return array_slice($matched, 0, $limit);
    }
}
