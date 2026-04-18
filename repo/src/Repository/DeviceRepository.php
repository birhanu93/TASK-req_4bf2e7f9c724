<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Device;
use App\Repository\Contract\DeviceRepositoryInterface;

/**
 * @extends Repository<Device>
 */
final class DeviceRepository extends Repository implements DeviceRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Device
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    /**
     * @return Device[]
     */
    public function findByUser(string $userId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (Device $d) => $d->getUserId() === $userId,
        ));
    }

    public function findByFingerprint(string $userId, string $fingerprint): ?Device
    {
        foreach ($this->items as $d) {
            if ($d->getUserId() === $userId && $d->getFingerprint() === $fingerprint) {
                return $d;
            }
        }
        return null;
    }
}
