<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function find(string $id): ?User;

    /** @return User[] */
    public function findAll(): array;

    public function delete(string $id): void;

    public function findByUsername(string $username): ?User;

    public function countAll(): int;
}
