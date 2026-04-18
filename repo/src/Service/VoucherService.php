<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Voucher;
use App\Entity\VoucherClaim;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Persistence\Database;
use App\Repository\Contract\VoucherClaimRepositoryInterface;
use App\Repository\Contract\VoucherRepositoryInterface;

final class VoucherService
{
    public function __construct(
        private VoucherRepositoryInterface $vouchers,
        private VoucherClaimRepositoryInterface $claims,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
        private Database $db,
    ) {
    }

    public function issue(
        string $actorId,
        string $code,
        int $discountCents,
        int $minSpendCents,
        int $claimLimit,
        \DateTimeImmutable $expiresAt,
    ): Voucher {
        if ($code === '' || strlen($code) > 32) {
            throw new ValidationException('invalid code length');
        }
        if ($discountCents <= 0 || $minSpendCents < 0 || $claimLimit <= 0) {
            throw new ValidationException('invalid voucher parameters');
        }
        if ($discountCents > $minSpendCents && $minSpendCents > 0) {
            throw new ValidationException('discount cannot exceed min spend');
        }
        if ($expiresAt <= $this->clock->now()) {
            throw new ValidationException('expiresAt must be in future');
        }
        if ($this->vouchers->findByCode($code) !== null) {
            throw new ConflictException('voucher code already exists');
        }
        $voucher = new Voucher(
            $this->ids->generate(),
            $code,
            $discountCents,
            $minSpendCents,
            $claimLimit,
            $expiresAt,
        );
        try {
            $this->vouchers->save($voucher);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                throw new ConflictException('voucher code already exists');
            }
            throw $e;
        }
        $this->audit->record($actorId, 'voucher.issue', 'voucher', $voucher->getId(), [], [
            'code' => $code,
            'discountCents' => $discountCents,
            'claimLimit' => $claimLimit,
        ]);
        return $voucher;
    }

    public function claim(string $code, string $userId, string $idempotencyKey): VoucherClaim
    {
        if ($idempotencyKey === '') {
            throw new ValidationException('idempotency key required');
        }
        // Fast path: if we've already seen this key, replay the stored claim
        // without touching the voucher row. Reuse by a different user is a
        // conflict regardless of whether we saw it first here or inside the
        // lock below.
        $existing = $this->claims->findByIdempotencyKey($idempotencyKey);
        if ($existing !== null) {
            if ($existing->getUserId() !== $userId) {
                throw new ConflictException('idempotency key reused by different user');
            }
            return $existing;
        }
        return $this->db->transactional(function () use ($code, $userId, $idempotencyKey): VoucherClaim {
            $voucher = $this->vouchers->findByCode($code);
            if ($voucher === null) {
                throw new NotFoundException('voucher not found');
            }
            $this->db->lock("voucher:{$voucher->getId()}");

            // Re-check idempotency inside the lock. Two concurrent callers
            // can both pass the fast-path check with neither yet persisted;
            // the voucher lock serialises them, and whichever runs second
            // must replay the first winner's claim instead of allocating a
            // new slot or raising 'already claimed'.
            $prior = $this->claims->findByIdempotencyKey($idempotencyKey);
            if ($prior !== null) {
                if ($prior->getUserId() !== $userId) {
                    throw new ConflictException('idempotency key reused by different user');
                }
                return $prior;
            }

            $locked = $this->vouchers->findForUpdate($voucher->getId()) ?? $voucher;
            if (!$locked->isActive($this->clock->now())) {
                throw new ConflictException('voucher is not active');
            }
            if ($locked->remaining() <= 0) {
                throw new ConflictException('voucher claim limit reached');
            }
            if ($this->claims->findActiveForUser($locked->getId(), $userId) !== null) {
                throw new ConflictException('user already claimed this voucher');
            }
            $claim = new VoucherClaim(
                $this->ids->generate(),
                $locked->getId(),
                $userId,
                $idempotencyKey,
                $this->clock->now(),
            );
            $locked->incrementClaimed();
            $this->vouchers->save($locked);
            try {
                $this->claims->save($claim);
            } catch (\PDOException $e) {
                // Defence in depth: if we still collide on the unique
                // idempotency_key index despite the in-lock re-check (e.g.
                // a driver running without the advisory lock, or the lock
                // boundary misconfigured), replay the winning row instead of
                // surfacing a misleading generic conflict.
                if (str_contains($e->getMessage(), '1062') || str_contains(strtolower($e->getMessage()), 'unique')) {
                    $dupe = $this->claims->findByIdempotencyKey($idempotencyKey);
                    if ($dupe !== null) {
                        if ($dupe->getUserId() !== $userId) {
                            throw new ConflictException('idempotency key reused by different user');
                        }
                        return $dupe;
                    }
                    throw new ConflictException('concurrent claim detected');
                }
                throw $e;
            }
            $this->audit->record($userId, 'voucher.claim', 'voucher', $locked->getId(), [], [
                'claimId' => $claim->getId(),
                'idempotencyKey' => $idempotencyKey,
            ]);
            return $claim;
        });
    }

    /**
     * Redeem a claim. Callers MUST pass `$redemptionIdempotencyKey`; retries
     * carrying the same key replay the stored outcome instead of mutating
     * state. Concurrent redeems against the same claim are serialised via a
     * GET_LOCK on the claim id and a conditional UPDATE that only succeeds
     * when the row is still in STATUS_LOCKED — the pair together guarantees
     * at most one caller wins, even under a flood of duplicate requests.
     *
     * @return array{claim:VoucherClaim,discountCents:int,replayed:bool}
     */
    public function redeem(
        string $claimId,
        string $userId,
        int $orderAmountCents,
        string $redemptionIdempotencyKey,
    ): array {
        if ($redemptionIdempotencyKey === '') {
            throw new ValidationException('redemption idempotency key required');
        }
        if (strlen($redemptionIdempotencyKey) > 128) {
            throw new ValidationException('redemption idempotency key too long');
        }

        // Fast path: if we've seen this key before, replay the stored outcome
        // outside any transaction. Belongs-to-user check is enforced so the
        // key cannot be used to observe another user's redemption.
        $prior = $this->claims->findByRedemptionKey($redemptionIdempotencyKey);
        if ($prior !== null) {
            if ($prior->getId() !== $claimId) {
                throw new ConflictException('redemption idempotency key reused for different claim');
            }
            if ($prior->getUserId() !== $userId) {
                throw new ConflictException('redemption idempotency key reused by different user');
            }
            return [
                'claim' => $prior,
                'discountCents' => (int) $prior->getRedeemedDiscountCents(),
                'replayed' => true,
            ];
        }

        return $this->db->transactional(function () use ($claimId, $userId, $orderAmountCents, $redemptionIdempotencyKey) {
            // Serialise concurrent redeems on this claim. In MySQL this is
            // GET_LOCK; in the in-memory driver this is a per-process mutex.
            // Either way only one caller proceeds at a time, so the
            // subsequent conditional UPDATE is guaranteed to observe the
            // authoritative status.
            $this->db->lock("claim:{$claimId}");

            // Re-check idempotency inside the lock — another request may have
            // just finished redeeming with the same key while we were
            // waiting.
            $prior = $this->claims->findByRedemptionKey($redemptionIdempotencyKey);
            if ($prior !== null) {
                if ($prior->getId() !== $claimId) {
                    throw new ConflictException('redemption idempotency key reused for different claim');
                }
                if ($prior->getUserId() !== $userId) {
                    throw new ConflictException('redemption idempotency key reused by different user');
                }
                return [
                    'claim' => $prior,
                    'discountCents' => (int) $prior->getRedeemedDiscountCents(),
                    'replayed' => true,
                ];
            }

            $claim = $this->claims->find($claimId);
            if ($claim === null) {
                throw new NotFoundException('claim not found');
            }
            if ($claim->getUserId() !== $userId) {
                throw new ConflictException('claim does not belong to user');
            }
            if ($claim->getStatus() !== VoucherClaim::STATUS_LOCKED) {
                throw new ConflictException('claim not redeemable');
            }
            $voucher = $this->vouchers->find($claim->getVoucherId());
            if ($voucher === null) {
                throw new NotFoundException('voucher not found');
            }
            if (!$voucher->isActive($this->clock->now())) {
                throw new ConflictException('voucher is not active');
            }
            if ($orderAmountCents < $voucher->getMinSpendCents()) {
                throw new ConflictException('order below min spend');
            }

            $now = $this->clock->now();
            $discountCents = $voucher->getDiscountCents();

            // Atomic LOCKED -> REDEEMED via affected-row check. If the
            // UPDATE did not match a single row the claim was concurrently
            // moved out of LOCKED (another in-flight redeem or a void); we
            // must not proceed.
            $ok = $this->claims->markRedeemedIfLocked(
                $claim->getId(),
                $now,
                $redemptionIdempotencyKey,
                $orderAmountCents,
                $discountCents,
            );
            if (!$ok) {
                throw new ConflictException('claim not redeemable');
            }

            $claim->redeem($now, $redemptionIdempotencyKey, $orderAmountCents, $discountCents);

            $this->audit->record($userId, 'voucher.redeem', 'voucher', $voucher->getId(), [
                'status' => VoucherClaim::STATUS_LOCKED,
            ], [
                'claimId' => $claim->getId(),
                'amount' => $orderAmountCents,
                'discountCents' => $discountCents,
                'redemptionIdempotencyKey' => $redemptionIdempotencyKey,
                'status' => $claim->getStatus(),
            ]);
            return [
                'claim' => $claim,
                'discountCents' => $discountCents,
                'replayed' => false,
            ];
        });
    }

    public function voidClaim(string $claimId, string $actorId): VoucherClaim
    {
        return $this->db->transactional(function () use ($claimId, $actorId): VoucherClaim {
            $claim = $this->claims->find($claimId);
            if ($claim === null) {
                throw new NotFoundException('claim not found');
            }
            if ($claim->getStatus() === VoucherClaim::STATUS_REDEEMED) {
                throw new ConflictException('cannot void redeemed claim');
            }
            $before = ['status' => $claim->getStatus()];
            $claim->voidClaim();
            $this->claims->save($claim);
            $voucher = $this->vouchers->findForUpdate($claim->getVoucherId()) ?? $this->vouchers->find($claim->getVoucherId());
            if ($voucher !== null) {
                $voucher->decrementClaimed();
                $this->vouchers->save($voucher);
            }
            $this->audit->record($actorId, 'voucher.void_claim', 'voucherClaim', $claim->getId(), $before, ['status' => VoucherClaim::STATUS_VOID]);
            return $claim;
        });
    }

    public function voidVoucher(string $voucherId, string $actorId): Voucher
    {
        $voucher = $this->vouchers->find($voucherId);
        if ($voucher === null) {
            throw new NotFoundException('voucher not found');
        }
        $before = ['status' => $voucher->getStatus()];
        $voucher->voidVoucher();
        $this->vouchers->save($voucher);
        $this->audit->record($actorId, 'voucher.void', 'voucher', $voucher->getId(), $before, ['status' => Voucher::STATUS_VOID]);
        return $voucher;
    }

    public function describe(string $code): Voucher
    {
        $voucher = $this->vouchers->findByCode($code);
        if ($voucher === null) {
            throw new NotFoundException('voucher not found');
        }
        return $voucher;
    }

    /**
     * @return Voucher[]
     */
    public function listAll(): array
    {
        return $this->vouchers->findAll();
    }
}
