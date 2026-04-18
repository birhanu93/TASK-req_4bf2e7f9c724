<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\GuardianLink;
use App\Repository\Contract\GuardianLinkRepositoryInterface;

final class PdoGuardianLinkRepository implements GuardianLinkRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(GuardianLink $l): void
    {
        $sql = 'INSERT IGNORE INTO guardian_links (id, guardian_id, child_id, linked_at) VALUES (:id, :g, :c, :l)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $l->getId(),
            'g' => $l->getGuardianId(),
            'c' => $l->getChildId(),
            'l' => $l->getLinkedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?GuardianLink
    {
        $stmt = $this->pdo->prepare('SELECT * FROM guardian_links WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM guardian_links')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM guardian_links WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByGuardian(string $guardianId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM guardian_links WHERE guardian_id = :g');
        $stmt->execute(['g' => $guardianId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findLink(string $guardianId, string $childId): ?GuardianLink
    {
        $stmt = $this->pdo->prepare('SELECT * FROM guardian_links WHERE guardian_id = :g AND child_id = :c');
        $stmt->execute(['g' => $guardianId, 'c' => $childId]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): GuardianLink
    {
        return new GuardianLink(
            (string) $row['id'],
            (string) $row['guardian_id'],
            (string) $row['child_id'],
            new \DateTimeImmutable((string) $row['linked_at']),
        );
    }
}
