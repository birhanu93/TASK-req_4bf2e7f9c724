<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\ModerationItem;

interface ModerationRepositoryInterface
{
    public function save(ModerationItem $item): void;

    public function find(string $id): ?ModerationItem;

    /** @return ModerationItem[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return ModerationItem[] */
    public function findPending(): array;

    public function findByChecksum(string $checksum): ?ModerationItem;
}
