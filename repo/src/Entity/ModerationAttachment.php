<?php

declare(strict_types=1);

namespace App\Entity;

final class ModerationAttachment
{
    public function __construct(
        private string $id,
        private string $itemId,
        private string $filename,
        private string $mimeType,
        private int $sizeBytes,
        private string $checksum,
        private string $storagePath,
        private \DateTimeImmutable $uploadedAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }
}
