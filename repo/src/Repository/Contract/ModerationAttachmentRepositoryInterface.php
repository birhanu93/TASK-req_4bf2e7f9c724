<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\ModerationAttachment;

interface ModerationAttachmentRepositoryInterface
{
    public function save(ModerationAttachment $attachment): void;

    public function find(string $id): ?ModerationAttachment;

    /** @return ModerationAttachment[] */
    public function findAll(): array;

    /** @return ModerationAttachment[] */
    public function findByItem(string $itemId): array;

    public function delete(string $id): void;
}
