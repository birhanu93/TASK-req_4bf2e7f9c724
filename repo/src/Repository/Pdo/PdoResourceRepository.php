<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Resource;
use App\Repository\Contract\ResourceRepositoryInterface;

final class PdoResourceRepository implements ResourceRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Resource $r): void
    {
        $sql = 'INSERT INTO resources (id, name, kind, status, created_at)
                VALUES (:id, :n, :k, :s, :c)
                ON DUPLICATE KEY UPDATE name = VALUES(name), kind = VALUES(kind), status = VALUES(status)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $r->getId(),
            'n' => $r->getName(),
            'k' => $r->getKind(),
            's' => $r->getStatus(),
            'c' => $r->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?Resource
    {
        return $this->one('SELECT * FROM resources WHERE id = :id', ['id' => $id]);
    }

    public function findByName(string $name): ?Resource
    {
        return $this->one('SELECT * FROM resources WHERE name = :n', ['n' => $name]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM resources');
        return array_map(fn ($row) => $this->hydrate($row), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM resources WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string,mixed> $p */
    private function one(string $sql, array $p): ?Resource
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($p);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): Resource
    {
        return new Resource(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['kind'],
            new \DateTimeImmutable((string) $row['created_at']),
            (string) $row['status'],
        );
    }
}
