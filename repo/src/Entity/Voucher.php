<?php

declare(strict_types=1);

namespace App\Entity;

final class Voucher
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_VOID = 'void';

    public function __construct(
        private string $id,
        private string $code,
        private int $discountCents,
        private int $minSpendCents,
        private int $claimLimit,
        private \DateTimeImmutable $expiresAt,
        private string $status = self::STATUS_ACTIVE,
        private int $claimed = 0,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDiscountCents(): int
    {
        return $this->discountCents;
    }

    public function getMinSpendCents(): int
    {
        return $this->minSpendCents;
    }

    public function getClaimLimit(): int
    {
        return $this->claimLimit;
    }

    public function getClaimed(): int
    {
        return $this->claimed;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(\DateTimeImmutable $at): bool
    {
        return $this->status === self::STATUS_ACTIVE && $at <= $this->expiresAt;
    }

    public function remaining(): int
    {
        return max(0, $this->claimLimit - $this->claimed);
    }

    public function incrementClaimed(): void
    {
        $this->claimed++;
    }

    public function decrementClaimed(): void
    {
        if ($this->claimed > 0) {
            $this->claimed--;
        }
    }

    public function voidVoucher(): void
    {
        $this->status = self::STATUS_VOID;
    }
}
