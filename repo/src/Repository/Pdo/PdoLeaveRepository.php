<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\SupervisorLeave;
use App\Repository\Contract\LeaveRepositoryInterface;

final class PdoLeaveRepository implements LeaveRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(SupervisorLeave $l): void
    {
        $sql = 'INSERT INTO supervisor_leaves (id, supervisor_id, starts_at, ends_at, recurrence_rule, reason, created_at)
                VALUES (:id, :s, :st, :en, :rr, :re, :ca)
                ON DUPLICATE KEY UPDATE
                  starts_at = VALUES(starts_at),
                  ends_at = VALUES(ends_at),
                  recurrence_rule = VALUES(recurrence_rule),
                  reason = VALUES(reason)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $l->getId(),
            's' => $l->getSupervisorId(),
            'st' => $l->getStartsAt()->format('Y-m-d H:i:s'),
            'en' => $l->getEndsAt()->format('Y-m-d H:i:s'),
            'rr' => $l->getRecurrenceRule(),
            're' => $l->getReason(),
            'ca' => $l->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?SupervisorLeave
    {
        $stmt = $this->pdo->prepare('SELECT * FROM supervisor_leaves WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM supervisor_leaves')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM supervisor_leaves WHERE id = :id')->execute(['id' => $id]);
    }

    public function findBySupervisor(string $supervisorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM supervisor_leaves WHERE supervisor_id = :s');
        $stmt->execute(['s' => $supervisorId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): SupervisorLeave
    {
        return new SupervisorLeave(
            (string) $row['id'],
            (string) $row['supervisor_id'],
            new \DateTimeImmutable((string) $row['starts_at']),
            new \DateTimeImmutable((string) $row['ends_at']),
            (string) $row['recurrence_rule'],
            $row['reason'] !== null ? (string) $row['reason'] : null,
            new \DateTimeImmutable((string) $row['created_at']),
        );
    }
}
