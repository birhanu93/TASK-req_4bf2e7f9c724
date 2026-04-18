<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\ResourceReservation;
use App\Repository\Contract\ResourceReservationRepositoryInterface;

final class PdoResourceReservationRepository implements ResourceReservationRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(ResourceReservation $r): void
    {
        $sql = 'INSERT INTO resource_reservations
                  (id, resource_id, session_id, starts_at, ends_at, reserved_by_user_id, created_at)
                VALUES (:id, :r, :s, :sa, :ea, :u, :c)
                ON DUPLICATE KEY UPDATE
                  starts_at = VALUES(starts_at),
                  ends_at = VALUES(ends_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $r->getId(),
            'r' => $r->getResourceId(),
            's' => $r->getSessionId(),
            'sa' => $r->getStartsAt()->format('Y-m-d H:i:s'),
            'ea' => $r->getEndsAt()->format('Y-m-d H:i:s'),
            'u' => $r->getReservedByUserId(),
            'c' => $r->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?ResourceReservation
    {
        return $this->one('SELECT * FROM resource_reservations WHERE id = :id', ['id' => $id]);
    }

    public function findAll(): array
    {
        return array_map(fn ($row) => $this->hydrate($row), $this->pdo->query('SELECT * FROM resource_reservations')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM resource_reservations WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByResource(string $resourceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resource_reservations WHERE resource_id = :r');
        $stmt->execute(['r' => $resourceId]);
        return array_map(fn ($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findBySession(string $sessionId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resource_reservations WHERE session_id = :s');
        $stmt->execute(['s' => $sessionId]);
        return array_map(fn ($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function findOverlapping(string $resourceId, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        // Half-open interval overlap: existing.starts_at < :end AND existing.ends_at > :start.
        $stmt = $this->pdo->prepare(
            'SELECT * FROM resource_reservations
              WHERE resource_id = :r
                AND starts_at < :end
                AND ends_at > :start
                FOR UPDATE'
        );
        $stmt->execute([
            'r' => $resourceId,
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ]);
        return array_map(fn ($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): ResourceReservation
    {
        return new ResourceReservation(
            (string) $row['id'],
            (string) $row['resource_id'],
            $row['session_id'] !== null ? (string) $row['session_id'] : null,
            new \DateTimeImmutable((string) $row['starts_at']),
            new \DateTimeImmutable((string) $row['ends_at']),
            (string) $row['reserved_by_user_id'],
            new \DateTimeImmutable((string) $row['created_at']),
        );
    }

    /** @param array<string,mixed> $p */
    private function one(string $sql, array $p): ?ResourceReservation
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($p);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }
}
