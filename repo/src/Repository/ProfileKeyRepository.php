<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProfileKey;
use App\Repository\Contract\ProfileKeyRepositoryInterface;

final class ProfileKeyRepository implements ProfileKeyRepositoryInterface
{
    /** @var array<int,ProfileKey> */
    private array $keys = [];

    public function save(ProfileKey $key): void
    {
        $this->keys[$key->getVersion()] = $key;
    }

    public function findByVersion(int $version): ?ProfileKey
    {
        return $this->keys[$version] ?? null;
    }

    /** @return ProfileKey[] */
    public function findAll(): array
    {
        return array_values($this->keys);
    }

    public function latestActive(): ?ProfileKey
    {
        $active = array_filter($this->keys, fn (ProfileKey $k) => $k->isActive());
        if ($active === []) {
            return null;
        }
        uasort($active, fn (ProfileKey $a, ProfileKey $b) => $b->getVersion() <=> $a->getVersion());
        return array_values($active)[0];
    }
}
