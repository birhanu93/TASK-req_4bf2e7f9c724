<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Rank;
use App\Repository\Contract\RankRepositoryInterface;

/**
 * @extends Repository<Rank>
 */
final class RankRepository extends Repository implements RankRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Rank
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return Rank[]
     */
    public function findAllOrdered(): array
    {
        $ranks = array_values($this->items);
        usort($ranks, fn (Rank $a, Rank $b) => $a->getOrder() <=> $b->getOrder());
        return $ranks;
    }
}
