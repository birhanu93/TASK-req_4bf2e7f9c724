<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Resource;
use App\Repository\Contract\ResourceRepositoryInterface;

/**
 * @extends Repository<Resource>
 */
final class ResourceRepository extends Repository implements ResourceRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Resource
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByName(string $name): ?Resource
    {
        foreach ($this->items as $r) {
            if ($r->getName() === $name) {
                return $r;
            }
        }
        return null;
    }
}
