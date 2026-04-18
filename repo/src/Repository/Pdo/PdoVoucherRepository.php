<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Voucher;
use App\Repository\Contract\VoucherRepositoryInterface;

final class PdoVoucherRepository implements VoucherRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Voucher $v): void
    {
        $sql = 'INSERT INTO vouchers
                (id, code, discount_cents, min_spend_cents, claim_limit, claimed, expires_at, status, created_at, updated_at)
                VALUES (:id, :c, :d, :m, :l, :cl, :e, :s, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                  discount_cents = VALUES(discount_cents),
                  min_spend_cents = VALUES(min_spend_cents),
                  claim_limit = VALUES(claim_limit),
                  claimed = VALUES(claimed),
                  expires_at = VALUES(expires_at),
                  status = VALUES(status),
                  updated_at = NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $v->getId(),
            'c' => $v->getCode(),
            'd' => $v->getDiscountCents(),
            'm' => $v->getMinSpendCents(),
            'l' => $v->getClaimLimit(),
            'cl' => $v->getClaimed(),
            'e' => $v->getExpiresAt()->format('Y-m-d H:i:s'),
            's' => $v->getStatus(),
        ]);
    }

    public function find(string $id): ?Voucher
    {
        return $this->one('SELECT * FROM vouchers WHERE id = :id', ['id' => $id]);
    }

    public function findForUpdate(string $id): ?Voucher
    {
        return $this->one('SELECT * FROM vouchers WHERE id = :id FOR UPDATE', ['id' => $id]);
    }

    public function findByCode(string $code): ?Voucher
    {
        return $this->one('SELECT * FROM vouchers WHERE code = :c', ['c' => $code]);
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM vouchers')->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vouchers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param array<string,mixed> $p
     */
    private function one(string $sql, array $p): ?Voucher
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($p);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Voucher
    {
        return new Voucher(
            (string) $row['id'],
            (string) $row['code'],
            (int) $row['discount_cents'],
            (int) $row['min_spend_cents'],
            (int) $row['claim_limit'],
            new \DateTimeImmutable((string) $row['expires_at']),
            (string) $row['status'],
            (int) $row['claimed'],
        );
    }
}
