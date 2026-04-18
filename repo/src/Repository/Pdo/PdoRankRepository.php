<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Rank;
use App\Repository\Contract\RankRepositoryInterface;

final class PdoRankRepository implements RankRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Rank $r): void
    {
        $sql = 'INSERT INTO ranks (id, name, min_reps, min_seconds, display_order)
                VALUES (:id, :n, :r, :s, :o)
                ON DUPLICATE KEY UPDATE name = VALUES(name), min_reps = VALUES(min_reps),
                  min_seconds = VALUES(min_seconds), display_order = VALUES(display_order)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $r->getId(),
            'n' => $r->getName(),
            'r' => $r->getMinReps(),
            's' => $r->getMinSeconds(),
            'o' => $r->getOrder(),
        ]);
    }

    public function find(string $id): ?Rank
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ranks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM ranks')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM ranks WHERE id = :id')->execute(['id' => $id]);
    }

    public function findAllOrdered(): array
    {
        $rows = $this->pdo->query('SELECT * FROM ranks ORDER BY display_order ASC')->fetchAll();
        return array_map(fn ($r) => $this->hydrate($r), $rows);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Rank
    {
        return new Rank(
            (string) $row['id'],
            (string) $row['name'],
            (int) $row['min_reps'],
            (int) $row['min_seconds'],
            (int) $row['display_order'],
        );
    }
}
