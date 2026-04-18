<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * In-memory storage base class. The public save/find/findAll/delete methods
 * are intentionally declared without strict return types so that each concrete
 * subclass can implement a repository interface that tightens the types
 * (e.g. UserRepository::find: ?User). PHP accepts the narrower declarations
 * in the interfaces because the base is more permissive.
 *
 * @template T of object
 */
abstract class Repository
{
    /**
     * @var array<string,T>
     */
    protected array $items = [];

    /**
     * @param T $entity
     */
    public function save(object $entity): void
    {
        $id = $this->idOf($entity);
        $this->items[$id] = $entity;
    }

    /**
     * @return T|null
     */
    public function find(string $id)
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @return T[]
     */
    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function delete(string $id): void
    {
        unset($this->items[$id]);
    }

    /**
     * @param T $entity
     */
    abstract protected function idOf(object $entity): string;
}
