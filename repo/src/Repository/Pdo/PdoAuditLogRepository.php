<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\AuditLog;
use App\Repository\Contract\AuditLogRepositoryInterface;

final class PdoAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(AuditLog $log): void
    {
        $sql = 'INSERT INTO audit_log (id, actor_id, action, entity_type, entity_id, occurred_at, before_json, after_json)
                VALUES (:id, :a, :ac, :t, :e, :o, :b, :af)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $log->getId(),
            'a' => $log->getActorId(),
            'ac' => $log->getAction(),
            't' => $log->getEntityType(),
            'e' => $log->getEntityId(),
            'o' => $log->getOccurredAt()->format('Y-m-d H:i:s'),
            'b' => json_encode($log->getBefore(), JSON_UNESCAPED_UNICODE),
            'af' => json_encode($log->getAfter(), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function find(string $id): ?AuditLog
    {
        $stmt = $this->pdo->prepare('SELECT * FROM audit_log WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM audit_log ORDER BY occurred_at DESC LIMIT 1000')->fetchAll());
    }

    public function findByEntity(string $entityType, string $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM audit_log WHERE entity_type = :t AND entity_id = :e ORDER BY occurred_at ASC'
        );
        $stmt->execute(['t' => $entityType, 'e' => $entityId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findByActor(string $actorId, int $limit = 100): array
    {
        $limit = max(1, min(1000, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM audit_log WHERE actor_id = :a ORDER BY occurred_at DESC LIMIT {$limit}"
        );
        $stmt->execute(['a' => $actorId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): AuditLog
    {
        return new AuditLog(
            (string) $row['id'],
            (string) $row['actor_id'],
            (string) $row['action'],
            (string) $row['entity_type'],
            (string) $row['entity_id'],
            new \DateTimeImmutable((string) $row['occurred_at']),
            $row['before_json'] ? json_decode((string) $row['before_json'], true) : [],
            $row['after_json'] ? json_decode((string) $row['after_json'], true) : [],
        );
    }
}
