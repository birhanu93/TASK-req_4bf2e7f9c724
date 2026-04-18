<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\VoucherClaim;
use App\Persistence\PdoDatabase;
use App\Repository\Pdo\PdoVoucherClaimRepository;
use PHPUnit\Framework\TestCase;

/**
 * The in-memory database driver is intentionally best-effort on rollback —
 * it advances a depth counter but does not undo repository mutations,
 * because in-memory repositories hold object references that cannot be
 * reliably snapshotted without invasive deep cloning. To cover transactional
 * correctness under realistic conditions we drive a real PDO connection
 * (sqlite — always available, supports savepoints) through the same
 * {@see PdoDatabase} abstraction production uses.
 *
 * The goals are:
 *
 *  - Nested transactions commit and rollback at the correct savepoint.
 *  - A failed inner transaction leaves the outer state untouched after
 *    rollback, and the outer commit persists only the good work.
 *  - The conditional LOCKED -> REDEEMED transition used by the voucher
 *    service is atomic: a second caller cannot race the status change.
 *  - Unique-index-backed idempotency catches duplicate inserts even when
 *    they run inside overlapping transactions.
 */
final class PdoTransactionIntegrationTest extends TestCase
{
    private PdoDatabase $db;
    private \PDO $pdo;
    private PdoVoucherClaimRepository $claims;

    protected function setUp(): void
    {
        $this->db = PdoDatabase::fromDsn('sqlite::memory:', '', '');
        /** @var \PDO $pdo */
        $pdo = $this->db->pdo();
        $this->pdo = $pdo;
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE voucher_claims (
                id TEXT PRIMARY KEY,
                voucher_id TEXT NOT NULL,
                user_id TEXT NOT NULL,
                idempotency_key TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                redeemed_at TEXT,
                redemption_idempotency_key TEXT UNIQUE,
                redeemed_order_amount_cents INTEGER,
                redeemed_discount_cents INTEGER
            )
            SQL,
        );
        $this->claims = new PdoVoucherClaimRepository($this->pdo);
    }

    public function testOuterRollbackDiscardsNestedCommit(): void
    {
        $this->insertClaim('c-outer', 'idem-outer');
        try {
            $this->db->transactional(function () {
                // Savepoint-based inner transaction commits its work.
                $this->db->transactional(function () {
                    $this->insertClaim('c-inner', 'idem-inner');
                });
                // Outer blows up — both outer and inner must roll back.
                throw new \RuntimeException('boom');
            });
            self::fail('expected exception');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
        self::assertNotNull($this->claims->findByIdempotencyKey('idem-outer'));
        self::assertNull($this->claims->findByIdempotencyKey('idem-inner'));
    }

    public function testInnerRollbackPreservesOuterWork(): void
    {
        $this->db->transactional(function () {
            $this->insertClaim('c-keep', 'idem-keep');
            try {
                $this->db->transactional(function () {
                    $this->insertClaim('c-drop', 'idem-drop');
                    throw new \RuntimeException('inner');
                });
                self::fail('expected inner exception');
            } catch (\RuntimeException) {
                // swallow — outer continues
            }
            $this->insertClaim('c-keep-2', 'idem-keep-2');
        });

        self::assertNotNull($this->claims->findByIdempotencyKey('idem-keep'));
        self::assertNull($this->claims->findByIdempotencyKey('idem-drop'));
        self::assertNotNull($this->claims->findByIdempotencyKey('idem-keep-2'));
    }

    public function testUniqueIdempotencyKeyRejectsDuplicateInsertInTransaction(): void
    {
        $this->insertClaim('c-first', 'idem-shared');
        $this->expectException(\PDOException::class);
        $this->db->transactional(function () {
            $this->insertClaim('c-second', 'idem-shared');
        });
    }

    public function testAtomicRedeemTransitionBlocksSecondCaller(): void
    {
        $this->insertClaim('c-atomic', 'idem-atomic');
        $now = new \DateTimeImmutable('2026-04-18T10:00:00+00:00');

        self::assertTrue($this->claims->markRedeemedIfLocked('c-atomic', $now, 'rk-1', 20000, 2500));
        // Second caller must observe the row is no longer in status=locked.
        self::assertFalse($this->claims->markRedeemedIfLocked('c-atomic', $now, 'rk-2', 30000, 2500));

        $reloaded = $this->claims->find('c-atomic');
        self::assertNotNull($reloaded);
        self::assertSame(VoucherClaim::STATUS_REDEEMED, $reloaded->getStatus());
        self::assertSame('rk-1', $reloaded->getRedemptionIdempotencyKey());
        self::assertSame(20000, $reloaded->getRedeemedOrderAmountCents());
    }

    public function testAtomicRedeemTransitionIsTransactionSafe(): void
    {
        // Transition inside a transaction, then roll the outer back — the
        // row must revert to locked, proving the savepoint/transaction
        // boundary also guards atomic DML, not only inserts.
        $this->insertClaim('c-tx', 'idem-tx');
        $now = new \DateTimeImmutable('2026-04-18T10:00:00+00:00');

        try {
            $this->db->transactional(function () use ($now) {
                self::assertTrue($this->claims->markRedeemedIfLocked('c-tx', $now, 'rk-tx', 20000, 2500));
                throw new \RuntimeException('rollback');
            });
            self::fail('expected exception');
        } catch (\RuntimeException) {
            // expected
        }

        $reloaded = $this->claims->find('c-tx');
        self::assertNotNull($reloaded);
        self::assertSame(VoucherClaim::STATUS_LOCKED, $reloaded->getStatus());
        self::assertNull($reloaded->getRedemptionIdempotencyKey());
    }

    private function insertClaim(string $id, string $idemKey): void
    {
        // Raw insert to avoid the MySQL-specific ON DUPLICATE KEY UPDATE
        // syntax used by PdoVoucherClaimRepository::save. The goal here is
        // to drive the transaction/savepoint machinery in PdoDatabase, not
        // the MySQL dialect.
        $stmt = $this->pdo->prepare(
            'INSERT INTO voucher_claims (id, voucher_id, user_id, idempotency_key, status, created_at)
             VALUES (:id, :v, :u, :k, :s, :c)'
        );
        $stmt->execute([
            'id' => $id,
            'v' => 'v1',
            'u' => 'u1',
            'k' => $idemKey,
            's' => VoucherClaim::STATUS_LOCKED,
            'c' => '2026-04-18 09:00:00',
        ]);
    }
}
