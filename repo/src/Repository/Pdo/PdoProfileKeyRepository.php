<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\ProfileKey;
use App\Repository\Contract\ProfileKeyRepositoryInterface;

final class PdoProfileKeyRepository implements ProfileKeyRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(ProfileKey $k): void
    {
        $sql = 'INSERT INTO profile_keys (key_version, wrapped_key_blob, created_at, retired_at)
                VALUES (:v, :b, :c, :r)
                ON DUPLICATE KEY UPDATE wrapped_key_blob = VALUES(wrapped_key_blob),
                  retired_at = VALUES(retired_at)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':v', $k->getVersion(), \PDO::PARAM_INT);
        $stmt->bindValue(':b', $k->getWrappedKey(), \PDO::PARAM_LOB);
        $stmt->bindValue(':c', $k->getCreatedAt()->format('Y-m-d H:i:s'));
        $stmt->bindValue(':r', $k->getRetiredAt()?->format('Y-m-d H:i:s'));
        $stmt->execute();
    }

    public function findByVersion(int $version): ?ProfileKey
    {
        $stmt = $this->pdo->prepare('SELECT * FROM profile_keys WHERE key_version = :v');
        $stmt->execute(['v' => $version]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM profile_keys ORDER BY key_version DESC')->fetchAll());
    }

    public function latestActive(): ?ProfileKey
    {
        $stmt = $this->pdo->query('SELECT * FROM profile_keys WHERE retired_at IS NULL ORDER BY key_version DESC LIMIT 1');
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): ProfileKey
    {
        return new ProfileKey(
            (int) $row['key_version'],
            (string) $row['wrapped_key_blob'],
            new \DateTimeImmutable((string) $row['created_at']),
            $row['retired_at'] !== null ? new \DateTimeImmutable((string) $row['retired_at']) : null,
        );
    }
}
