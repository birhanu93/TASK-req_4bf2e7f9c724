<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\ModerationItem;
use App\Repository\Contract\ModerationRepositoryInterface;

final class PdoModerationRepository implements ModerationRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(ModerationItem $m): void
    {
        $sql = 'INSERT INTO moderation_items
                (id, author_id, kind, content, checksum, submitted_at, status, reviewer_id, reason, quality_score)
                VALUES (:id, :a, :k, :c, :cs, :s, :st, :r, :re, :q)
                ON DUPLICATE KEY UPDATE
                  status = VALUES(status),
                  reviewer_id = VALUES(reviewer_id),
                  reason = VALUES(reason),
                  quality_score = VALUES(quality_score)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $m->getId(),
            'a' => $m->getAuthorId(),
            'k' => $m->getKind(),
            'c' => $m->getContent(),
            'cs' => $m->getChecksum(),
            's' => $m->getSubmittedAt()->format('Y-m-d H:i:s'),
            'st' => $m->getStatus(),
            'r' => $m->getReviewerId(),
            're' => $m->getReason(),
            'q' => $m->getQualityScore(),
        ]);
    }

    public function find(string $id): ?ModerationItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM moderation_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM moderation_items')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM moderation_items WHERE id = :id')->execute(['id' => $id]);
    }

    public function findPending(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM moderation_items WHERE status = 'pending' ORDER BY submitted_at ASC");
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findByChecksum(string $checksum): ?ModerationItem
    {
        $stmt = $this->pdo->prepare('SELECT * FROM moderation_items WHERE checksum = :c');
        $stmt->execute(['c' => $checksum]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): ModerationItem
    {
        return new ModerationItem(
            (string) $row['id'],
            (string) $row['author_id'],
            (string) $row['kind'],
            (string) $row['content'],
            (string) $row['checksum'],
            new \DateTimeImmutable((string) $row['submitted_at']),
            (string) $row['status'],
            $row['reviewer_id'] !== null ? (string) $row['reviewer_id'] : null,
            $row['reason'] !== null ? (string) $row['reason'] : null,
            $row['quality_score'] !== null ? (int) $row['quality_score'] : null,
        );
    }
}
