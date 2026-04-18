<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Device;
use App\Repository\Contract\DeviceRepositoryInterface;

final class PdoDeviceRepository implements DeviceRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Device $d): void
    {
        $sql = 'INSERT INTO devices (id, user_id, device_name, fingerprint, approved_at, status, session_token)
                VALUES (:id, :u, :n, :f, :a, :s, :t)
                ON DUPLICATE KEY UPDATE
                  device_name = VALUES(device_name),
                  status = VALUES(status),
                  session_token = VALUES(session_token)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $d->getId(),
            'u' => $d->getUserId(),
            'n' => $d->getDeviceName(),
            'f' => $d->getFingerprint(),
            'a' => $d->getApprovedAt()->format('Y-m-d H:i:s'),
            's' => $d->getStatus(),
            't' => $d->getSessionToken(),
        ]);
    }

    public function find(string $id): ?Device
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devices WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM devices')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM devices WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devices WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findByFingerprint(string $userId, string $fingerprint): ?Device
    {
        $stmt = $this->pdo->prepare('SELECT * FROM devices WHERE user_id = :u AND fingerprint = :f');
        $stmt->execute(['u' => $userId, 'f' => $fingerprint]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Device
    {
        return new Device(
            (string) $row['id'],
            (string) $row['user_id'],
            (string) $row['device_name'],
            (string) $row['fingerprint'],
            new \DateTimeImmutable((string) $row['approved_at']),
            (string) $row['status'],
            $row['session_token'] !== null ? (string) $row['session_token'] : null,
        );
    }
}
