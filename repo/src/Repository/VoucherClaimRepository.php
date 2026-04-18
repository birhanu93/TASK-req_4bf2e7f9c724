<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VoucherClaim;
use App\Repository\Contract\VoucherClaimRepositoryInterface;

/**
 * @extends Repository<VoucherClaim>
 */
final class VoucherClaimRepository extends Repository implements VoucherClaimRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?VoucherClaim
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByIdempotencyKey(string $key): ?VoucherClaim
    {
        foreach ($this->items as $claim) {
            if ($claim->getIdempotencyKey() === $key) {
                return $claim;
            }
        }
        return null;
    }

    /**
     * @return VoucherClaim[]
     */
    public function findByVoucher(string $voucherId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (VoucherClaim $c) => $c->getVoucherId() === $voucherId,
        ));
    }

    public function findActiveForUser(string $voucherId, string $userId): ?VoucherClaim
    {
        foreach ($this->items as $c) {
            if ($c->getVoucherId() === $voucherId
                && $c->getUserId() === $userId
                && $c->getStatus() !== VoucherClaim::STATUS_VOID) {
                return $c;
            }
        }
        return null;
    }

    public function findByRedemptionKey(string $key): ?VoucherClaim
    {
        foreach ($this->items as $claim) {
            if ($claim->getRedemptionIdempotencyKey() === $key) {
                return $claim;
            }
        }
        return null;
    }

    public function markRedeemedIfLocked(
        string $claimId,
        \DateTimeImmutable $redeemedAt,
        string $redemptionKey,
        int $orderAmountCents,
        int $discountCents,
    ): bool {
        $claim = $this->items[$claimId] ?? null;
        if ($claim === null) {
            return false;
        }
        if ($claim->getStatus() !== VoucherClaim::STATUS_LOCKED) {
            return false;
        }
        $claim->redeem($redeemedAt, $redemptionKey, $orderAmountCents, $discountCents);
        return true;
    }
}
