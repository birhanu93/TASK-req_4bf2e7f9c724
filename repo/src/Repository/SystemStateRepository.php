<?php

declare(strict_types=1);

namespace App\Repository;

use App\Repository\Contract\SystemStateRepositoryInterface;

final class SystemStateRepository implements SystemStateRepositoryInterface
{
    /** @var array<string,string> */
    private array $state = [];

    public function claim(string $marker, string $value, \DateTimeImmutable $at): bool
    {
        if (isset($this->state[$marker])) {
            return false;
        }
        $this->state[$marker] = $value;
        return true;
    }

    public function get(string $marker): ?string
    {
        return $this->state[$marker] ?? null;
    }
}
