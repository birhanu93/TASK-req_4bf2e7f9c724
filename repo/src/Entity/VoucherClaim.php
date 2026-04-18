<?php

declare(strict_types=1);

namespace App\Entity;

final class VoucherClaim
{
    public const STATUS_LOCKED = 'locked';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_VOID = 'void';

    public function __construct(
        private string $id,
        private string $voucherId,
        private string $userId,
        private string $idempotencyKey,
        private \DateTimeImmutable $createdAt,
        private string $status = self::STATUS_LOCKED,
        private ?\DateTimeImmutable $redeemedAt = null,
        private ?string $redemptionIdempotencyKey = null,
        private ?int $redeemedOrderAmountCents = null,
        private ?int $redeemedDiscountCents = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getVoucherId(): string
    {
        return $this->voucherId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRedeemedAt(): ?\DateTimeImmutable
    {
        return $this->redeemedAt;
    }

    public function getRedemptionIdempotencyKey(): ?string
    {
        return $this->redemptionIdempotencyKey;
    }

    public function getRedeemedOrderAmountCents(): ?int
    {
        return $this->redeemedOrderAmountCents;
    }

    public function getRedeemedDiscountCents(): ?int
    {
        return $this->redeemedDiscountCents;
    }

    /**
     * Mark the claim redeemed. The outcome (redemption key + amount + discount)
     * is stored on the claim so future retries carrying the same idempotency
     * key can replay the original result without mutating state again.
     */
    public function redeem(
        \DateTimeImmutable $at,
        string $redemptionKey,
        int $orderAmountCents,
        int $discountCents,
    ): void {
        $this->status = self::STATUS_REDEEMED;
        $this->redeemedAt = $at;
        $this->redemptionIdempotencyKey = $redemptionKey;
        $this->redeemedOrderAmountCents = $orderAmountCents;
        $this->redeemedDiscountCents = $discountCents;
    }

    public function voidClaim(): void
    {
        $this->status = self::STATUS_VOID;
    }
}
