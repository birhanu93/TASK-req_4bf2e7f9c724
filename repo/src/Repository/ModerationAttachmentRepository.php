<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ModerationAttachment;
use App\Repository\Contract\ModerationAttachmentRepositoryInterface;

/**
 * @extends Repository<ModerationAttachment>
 */
final class ModerationAttachmentRepository extends Repository implements ModerationAttachmentRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?ModerationAttachment
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return ModerationAttachment[]
     */
    public function findByItem(string $itemId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (ModerationAttachment $a) => $a->getItemId() === $itemId,
        ));
    }
}
