<?php

declare(strict_types=1);

namespace App\Repository\Contract;

interface SystemStateRepositoryInterface
{
    /**
     * Atomically claim a one-shot marker. Returns true only to the first
     * caller; subsequent calls return false even under concurrent access.
     */
    public function claim(string $marker, string $value, \DateTimeImmutable $at): bool;

    public function get(string $marker): ?string;
}
