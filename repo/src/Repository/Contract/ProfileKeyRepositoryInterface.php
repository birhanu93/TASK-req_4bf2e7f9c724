<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\ProfileKey;

interface ProfileKeyRepositoryInterface
{
    public function save(ProfileKey $key): void;

    public function findByVersion(int $version): ?ProfileKey;

    /** @return ProfileKey[] */
    public function findAll(): array;

    public function latestActive(): ?ProfileKey;
}
