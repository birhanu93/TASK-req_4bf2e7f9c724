<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\ModerationAttachment;
use App\Repository\Contract\ModerationAttachmentRepositoryInterface;

final class PdoModerationAttachmentRepository implements ModerationAttachmentRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(ModerationAttachment $a): void
    {
        $sql = 'INSERT INTO moderation_attachments
                (id, item_id, filename, mime_type, size_bytes, checksum, storage_path, uploaded_at)
                VALUES (:id, :i, :f, :m, :s, :cs, :p, :u)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $a->getId(),
            'i' => $a->getItemId(),
            'f' => $a->getFilename(),
            'm' => $a->getMimeType(),
            's' => $a->getSizeBytes(),
            'cs' => $a->getChecksum(),
            'p' => $a->getStoragePath(),
            'u' => $a->getUploadedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(string $id): ?ModerationAttachment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM moderation_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM moderation_attachments')->fetchAll());
    }

    public function findByItem(string $itemId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM moderation_attachments WHERE item_id = :i');
        $stmt->execute(['i' => $itemId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM moderation_attachments WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): ModerationAttachment
    {
        return new ModerationAttachment(
            (string) $row['id'],
            (string) $row['item_id'],
            (string) $row['filename'],
            (string) $row['mime_type'],
            (int) $row['size_bytes'],
            (string) $row['checksum'],
            (string) $row['storage_path'],
            new \DateTimeImmutable((string) $row['uploaded_at']),
        );
    }
}
