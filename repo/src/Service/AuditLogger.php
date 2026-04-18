<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Repository\Contract\AuditLogRepositoryInterface;

final class AuditLogger
{
    public function __construct(
        private AuditLogRepositoryInterface $logs,
        private Clock $clock,
        private IdGenerator $ids,
    ) {
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public function record(string $actorId, string $action, string $entityType, string $entityId, array $before = [], array $after = []): AuditLog
    {
        $log = new AuditLog(
            $this->ids->generate(),
            $actorId,
            $action,
            $entityType,
            $entityId,
            $this->clock->now(),
            $before,
            $after,
        );
        $this->logs->save($log);
        return $log;
    }

    /**
     * @return AuditLog[]
     */
    public function history(string $entityType, string $entityId): array
    {
        return $this->logs->findByEntity($entityType, $entityId);
    }
}
