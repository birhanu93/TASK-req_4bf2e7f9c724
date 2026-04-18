<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Device;

interface DeviceRepositoryInterface
{
    public function save(Device $device): void;

    public function find(string $id): ?Device;

    /** @return Device[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return Device[] */
    public function findByUser(string $userId): array;

    public function findByFingerprint(string $userId, string $fingerprint): ?Device;
}
