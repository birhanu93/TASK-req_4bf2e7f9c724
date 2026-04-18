<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Rank;

interface RankRepositoryInterface
{
    public function save(Rank $rank): void;

    public function find(string $id): ?Rank;

    /** @return Rank[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return Rank[] */
    public function findAllOrdered(): array;
}
