<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ModerationItem;
use App\Repository\Contract\ModerationRepositoryInterface;

/**
 * @extends Repository<ModerationItem>
 */
final class ModerationRepository extends Repository implements ModerationRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?ModerationItem
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return ModerationItem[]
     */
    public function findPending(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (ModerationItem $m) => $m->getStatus() === ModerationItem::STATUS_PENDING,
        ));
    }

    public function findByChecksum(string $checksum): ?ModerationItem
    {
        foreach ($this->items as $m) {
            if ($m->getChecksum() === $checksum) {
                return $m;
            }
        }
        return null;
    }
}
