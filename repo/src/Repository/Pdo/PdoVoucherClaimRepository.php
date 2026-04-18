<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\VoucherClaim;
use App\Repository\Contract\VoucherClaimRepositoryInterface;

final class PdoVoucherClaimRepository implements VoucherClaimRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(VoucherClaim $c): void
    {
        $sql = 'INSERT INTO voucher_claims
                (id, voucher_id, user_id, idempotency_key, status, created_at, redeemed_at,
                 redemption_idempotency_key, redeemed_order_amount_cents, redeemed_discount_cents)
                VALUES (:id, :v, :u, :k, :s, :c, :r, :rk, :ra, :rd)
                ON DUPLICATE KEY UPDATE
                  status = VALUES(status),
                  redeemed_at = VALUES(redeemed_at),
                  redemption_idempotency_key = VALUES(redemption_idempotency_key),
                  redeemed_order_amount_cents = VALUES(redeemed_order_amount_cents),
                  redeemed_discount_cents = VALUES(redeemed_discount_cents)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $c->getId(),
            'v' => $c->getVoucherId(),
            'u' => $c->getUserId(),
            'k' => $c->getIdempotencyKey(),
            's' => $c->getStatus(),
            'c' => $c->getCreatedAt()->format('Y-m-d H:i:s'),
            'r' => $c->getRedeemedAt()?->format('Y-m-d H:i:s'),
            'rk' => $c->getRedemptionIdempotencyKey(),
            'ra' => $c->getRedeemedOrderAmountCents(),
            'rd' => $c->getRedeemedDiscountCents(),
        ]);
    }

    public function find(string $id): ?VoucherClaim
    {
        return $this->one('SELECT * FROM voucher_claims WHERE id = :id', ['id' => $id]);
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM voucher_claims')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM voucher_claims WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByIdempotencyKey(string $key): ?VoucherClaim
    {
        return $this->one('SELECT * FROM voucher_claims WHERE idempotency_key = :k', ['k' => $key]);
    }

    public function findByRedemptionKey(string $key): ?VoucherClaim
    {
        return $this->one(
            'SELECT * FROM voucher_claims WHERE redemption_idempotency_key = :k',
            ['k' => $key],
        );
    }

    public function findByVoucher(string $voucherId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voucher_claims WHERE voucher_id = :v');
        $stmt->execute(['v' => $voucherId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    public function findActiveForUser(string $voucherId, string $userId): ?VoucherClaim
    {
        return $this->one(
            "SELECT * FROM voucher_claims WHERE voucher_id = :v AND user_id = :u AND status <> 'void'",
            ['v' => $voucherId, 'u' => $userId],
        );
    }

    public function markRedeemedIfLocked(
        string $claimId,
        \DateTimeImmutable $redeemedAt,
        string $redemptionKey,
        int $orderAmountCents,
        int $discountCents,
    ): bool {
        // Conditional UPDATE: the `status = 'locked'` predicate is the atomic
        // transition guard. A concurrent request that already moved the row
        // to 'redeemed' (or 'void') leaves our WHERE clause no match, so
        // rowCount() returns 0 and we report no-op.
        $stmt = $this->pdo->prepare(
            'UPDATE voucher_claims
                SET status = :newStatus,
                    redeemed_at = :r,
                    redemption_idempotency_key = :rk,
                    redeemed_order_amount_cents = :ra,
                    redeemed_discount_cents = :rd
              WHERE id = :id
                AND status = :expected'
        );
        $stmt->execute([
            'newStatus' => VoucherClaim::STATUS_REDEEMED,
            'r' => $redeemedAt->format('Y-m-d H:i:s'),
            'rk' => $redemptionKey,
            'ra' => $orderAmountCents,
            'rd' => $discountCents,
            'id' => $claimId,
            'expected' => VoucherClaim::STATUS_LOCKED,
        ]);
        return $stmt->rowCount() === 1;
    }

    /**
     * @param array<string,mixed> $p
     */
    private function one(string $sql, array $p): ?VoucherClaim
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($p);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): VoucherClaim
    {
        return new VoucherClaim(
            (string) $row['id'],
            (string) $row['voucher_id'],
            (string) $row['user_id'],
            (string) $row['idempotency_key'],
            new \DateTimeImmutable((string) $row['created_at']),
            (string) $row['status'],
            $row['redeemed_at'] !== null ? new \DateTimeImmutable((string) $row['redeemed_at']) : null,
            $row['redemption_idempotency_key'] !== null ? (string) $row['redemption_idempotency_key'] : null,
            $row['redeemed_order_amount_cents'] !== null ? (int) $row['redeemed_order_amount_cents'] : null,
            $row['redeemed_discount_cents'] !== null ? (int) $row['redeemed_discount_cents'] : null,
        );
    }
}
