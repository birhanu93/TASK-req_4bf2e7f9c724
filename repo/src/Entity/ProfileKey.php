<?php

declare(strict_types=1);

namespace App\Entity;

final class ProfileKey
{
    public function __construct(
        private int $version,
        private string $wrappedKey,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $retiredAt = null,
    ) {
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getWrappedKey(): string
    {
        return $this->wrappedKey;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRetiredAt(): ?\DateTimeImmutable
    {
        return $this->retiredAt;
    }

    public function retire(\DateTimeImmutable $at): void
    {
        $this->retiredAt = $at;
    }

    public function isActive(): bool
    {
        return $this->retiredAt === null;
    }
}
