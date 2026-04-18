<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\TrainingSession;
use App\Repository\Contract\SessionRepositoryInterface;

final class PdoSessionRepository implements SessionRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(TrainingSession $session): void
    {
        $sql = 'INSERT INTO training_sessions
                (id, supervisor_id, title, starts_at, ends_at, capacity, buffer_minutes, status, created_at, updated_at)
                VALUES (:id, :sup, :t, :s, :e, :cap, :buf, :st, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                  title = VALUES(title),
                  starts_at = VALUES(starts_at),
                  ends_at = VALUES(ends_at),
                  capacity = VALUES(capacity),
                  buffer_minutes = VALUES(buffer_minutes),
                  status = VALUES(status),
                  updated_at = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $session->getId(),
            'sup' => $session->getSupervisorId(),
            't' => $session->getTitle(),
            's' => $session->getStartsAt()->format('Y-m-d H:i:s'),
            'e' => $session->getEndsAt()->format('Y-m-d H:i:s'),
            'cap' => $session->getCapacity(),
            'buf' => $session->getBufferMinutes(),
            'st' => $session->getStatus(),
        ]);
    }

    public function find(string $id): ?TrainingSession
    {
        return $this->fetch('SELECT * FROM training_sessions WHERE id = :id', ['id' => $id]);
    }

    public function findForUpdate(string $id): ?TrainingSession
    {
        return $this->fetch('SELECT * FROM training_sessions WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM training_sessions')->fetchAll();
        return array_map(fn ($r) => $this->hydrate($r), $rows);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findBySupervisor(string $supervisorId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_sessions WHERE supervisor_id = :s');
        $stmt->execute(['s' => $supervisorId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $params
     */
    private function fetch(string $sql, array $params): ?TrainingSession
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): TrainingSession
    {
        return new TrainingSession(
            (string) $row['id'],
            (string) $row['supervisor_id'],
            (string) $row['title'],
            new \DateTimeImmutable((string) $row['starts_at']),
            new \DateTimeImmutable((string) $row['ends_at']),
            (int) $row['capacity'],
            (int) $row['buffer_minutes'],
            (string) $row['status'],
        );
    }
}
