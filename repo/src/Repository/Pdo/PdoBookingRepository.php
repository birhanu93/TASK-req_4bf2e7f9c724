<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Booking;
use App\Repository\Contract\BookingRepositoryInterface;

final class PdoBookingRepository implements BookingRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Booking $b): void
    {
        $sql = 'INSERT INTO bookings
                (id, session_id, trainee_id, status, cancellation_reason, override_actor_id, idempotency_key, created_at, updated_at)
                VALUES (:id, :s, :t, :st, :reason, :oa, :ik, :ca, NOW())
                ON DUPLICATE KEY UPDATE
                  status = VALUES(status),
                  cancellation_reason = VALUES(cancellation_reason),
                  override_actor_id = VALUES(override_actor_id),
                  updated_at = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $b->getId(),
            's' => $b->getSessionId(),
            't' => $b->getTraineeId(),
            'st' => $b->getStatus(),
            'reason' => $b->getCancellationReason(),
            'oa' => $b->getOverrideActorId(),
            'ik' => $b->getIdempotencyKey(),
            'ca' => $b->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?Booking
    {
        return $this->fetchOne('SELECT * FROM bookings WHERE id = :id', ['id' => $id]);
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM bookings')->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bookings WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findActiveBySession(string $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM bookings WHERE session_id = :s AND status IN ('reserved','confirmed')"
        );
        $stmt->execute(['s' => $sessionId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findByTrainee(string $traineeId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bookings WHERE trainee_id = :t ORDER BY created_at DESC');
        $stmt->execute(['t' => $traineeId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findByIdempotencyKey(string $key): ?Booking
    {
        return $this->fetchOne('SELECT * FROM bookings WHERE idempotency_key = :k', ['k' => $key]);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function fetchOne(string $sql, array $params): ?Booking
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Booking
    {
        return new Booking(
            (string) $row['id'],
            (string) $row['session_id'],
            (string) $row['trainee_id'],
            new \DateTimeImmutable((string) $row['created_at']),
            (string) $row['status'],
            $row['cancellation_reason'] !== null ? (string) $row['cancellation_reason'] : null,
            $row['override_actor_id'] !== null ? (string) $row['override_actor_id'] : null,
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
        );
    }
}
