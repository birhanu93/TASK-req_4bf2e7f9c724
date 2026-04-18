<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Repository\Contract\UserRepositoryInterface;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository implements UserRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?User
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByUsername(string $username): ?User
    {
        foreach ($this->items as $user) {
            if ($user->getUsername() === $username) {
                return $user;
            }
        }
        return null;
    }

    public function countAll(): int
    {
        return count($this->items);
    }
}
