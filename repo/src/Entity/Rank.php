<?php

declare(strict_types=1);

namespace App\Entity;

final class Rank
{
    public function __construct(
        private string $id,
        private string $name,
        private int $minReps,
        private int $minSeconds,
        private int $order,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMinReps(): int
    {
        return $this->minReps;
    }

    public function getMinSeconds(): int
    {
        return $this->minSeconds;
    }

    public function getOrder(): int
    {
        return $this->order;
    }
}
