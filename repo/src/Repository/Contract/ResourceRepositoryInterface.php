<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Resource;

interface ResourceRepositoryInterface
{
    public function save(Resource $resource): void;

    public function find(string $id): ?Resource;

    public function findByName(string $name): ?Resource;

    /** @return Resource[] */
    public function findAll(): array;

    public function delete(string $id): void;
}
