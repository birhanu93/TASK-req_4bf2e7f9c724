<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\VoucherClaim;

interface VoucherClaimRepositoryInterface
{
    public function save(VoucherClaim $claim): void;

    public function find(string $id): ?VoucherClaim;

    /** @return VoucherClaim[] */
    public function findAll(): array;

    public function delete(string $id): void;

    public function findByIdempotencyKey(string $key): ?VoucherClaim;

    public function findByRedemptionKey(string $key): ?VoucherClaim;

    /** @return VoucherClaim[] */
    public function findByVoucher(string $voucherId): array;

    public function findActiveForUser(string $voucherId, string $userId): ?VoucherClaim;

    /**
     * Atomically transition a claim from STATUS_LOCKED to STATUS_REDEEMED and
     * persist the redemption outcome. Returns true only if this call did the
     * transition; false when the claim was not in STATUS_LOCKED at the time
     * the UPDATE ran (another request won the race, or the claim was voided).
     */
    public function markRedeemedIfLocked(
        string $claimId,
        \DateTimeImmutable $redeemedAt,
        string $redemptionKey,
        int $orderAmountCents,
        int $discountCents,
    ): bool;
}
