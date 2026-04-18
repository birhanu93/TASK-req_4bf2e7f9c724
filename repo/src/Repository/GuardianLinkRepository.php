<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GuardianLink;
use App\Repository\Contract\GuardianLinkRepositoryInterface;

/**
 * @extends Repository<GuardianLink>
 */
final class GuardianLinkRepository extends Repository implements GuardianLinkRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?GuardianLink
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return GuardianLink[]
     */
    public function findByGuardian(string $guardianId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (GuardianLink $l) => $l->getGuardianId() === $guardianId,
        ));
    }

    public function findLink(string $guardianId, string $childId): ?GuardianLink
    {
        foreach ($this->items as $link) {
            if ($link->getGuardianId() === $guardianId && $link->getChildId() === $childId) {
                return $link;
            }
        }
        return null;
    }
}
