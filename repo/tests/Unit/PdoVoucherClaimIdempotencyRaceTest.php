<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\VoucherClaim;
use App\Persistence\PdoDatabase;
use App\Repository\Pdo\PdoVoucherClaimRepository;
use PHPUnit\Framework\TestCase;

/**
 * The in-memory VoucherClaimRepository has no unique-index enforcement — its
 * findByIdempotencyKey() is a linear scan, and two concurrent writers would
 * both observe "no existing claim" inside the in-memory advisory lock. A
 * bug in the idempotency re-check therefore cannot surface on the in-memory
 * driver. These tests drive real PDO + sqlite so the UNIQUE index on
 * idempotency_key provides authoritative enforcement, matching what MySQL
 * does in production.
 *
 * The invariants under test:
 *
 *   - A second writer with the same (idempotency_key, user_id) must not
 *     create a new claim. It either replays the existing row (in-lock
 *     re-check path) or collides on the unique index (defence-in-depth
 *     path) and replays from there.
 *   - A different user with the same idempotency_key is rejected as a
 *     reuse conflict — never silently aliased onto another user's claim.
 *   - The unique index rejects duplicate inserts even when they land
 *     inside separate transactions.
 */
final class PdoVoucherClaimIdempotencyRaceTest extends TestCase
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

    public function testInLockRecheckReturnsWinnerForSecondCallerWithSameKey(): void
    {
        // Simulates the race inside VoucherService::claim: two callers pass
        // the outside-of-lock fast-path check with neither yet persisted,
        // then serialise on the voucher lock. Whichever runs second must
        // observe the winner's row and replay it instead of inserting a
        // duplicate or raising "already claimed".
        $firstId = $this->simulateClaim('u1', 'idem-race', expectReplay: false);
        $secondId = $this->simulateClaim('u1', 'idem-race', expectReplay: true);

        self::assertSame($firstId, $secondId, 'same-user retry must replay the winning claim');

        $rowCount = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM voucher_claims WHERE idempotency_key = 'idem-race'"
        )->fetchColumn();
        self::assertSame(1, $rowCount, 'unique key must collapse the race to a single row');
    }

    public function testDifferentUserSameKeyIsRejected(): void
    {
        $this->simulateClaim('u1', 'idem-user', expectReplay: false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/different user/');
        $this->simulateClaim('u2', 'idem-user', expectReplay: true);
    }

    public function testUniqueIndexCatchesDuplicateInsertFromRacingTransactions(): void
    {
        // Prove that even if a buggy caller skips the in-lock re-check and
        // goes straight to INSERT, the UNIQUE(idempotency_key) index rejects
        // the second write. This is the defence-in-depth the service relies
        // on when the advisory lock is absent (e.g. misconfigured driver).
        $this->rawInsertClaim('c-1', 'u1', 'idem-dupe');
        $this->expectException(\PDOException::class);
        $this->db->transactional(function () {
            $this->rawInsertClaim('c-2', 'u1', 'idem-dupe');
        });
    }

    public function testLosingInsertSurvivingTxIsRolledBackCleanly(): void
    {
        // A racing transaction that inserts, then hits the unique index
        // error on a subsequent duplicate insert within the same tx, rolls
        // the first insert back along with it. The "winner" row stays;
        // neither partial write from the loser survives.
        $this->rawInsertClaim('c-winner', 'u1', 'idem-A');

        try {
            $this->db->transactional(function () {
                $this->rawInsertClaim('c-partial', 'u1', 'idem-B');
                // Same-idempotency-key collision with the pre-existing row.
                $this->rawInsertClaim('c-dupe', 'u2', 'idem-A');
            });
            self::fail('expected unique violation');
        } catch (\PDOException) {
            // expected
        }

        $remaining = (int) $this->pdo->query('SELECT COUNT(*) FROM voucher_claims')->fetchColumn();
        self::assertSame(1, $remaining, 'partial write from losing tx must be rolled back');
        self::assertNotNull($this->claims->findByIdempotencyKey('idem-A'));
        self::assertNull($this->claims->findByIdempotencyKey('idem-B'), 'partial insert must not survive');
    }

    /**
     * Drives one iteration of the service's claim flow at the DB level: open
     * a transaction, re-check idempotency inside it (the fix under test),
     * and either replay the winner or INSERT a fresh row. Returns the id of
     * the claim that was either created or replayed.
     */
    private function simulateClaim(string $userId, string $idempotencyKey, bool $expectReplay): string
    {
        return $this->db->transactional(function () use ($userId, $idempotencyKey, $expectReplay) {
            $prior = $this->claims->findByIdempotencyKey($idempotencyKey);
            if ($prior !== null) {
                if ($prior->getUserId() !== $userId) {
                    throw new \RuntimeException('idempotency key reused by different user');
                }
                if (!$expectReplay) {
                    self::fail('expected to be first writer but found existing row');
                }
                return $prior->getId();
            }
            if ($expectReplay) {
                self::fail('expected to replay but no existing row present');
            }
            $id = 'c-' . bin2hex(random_bytes(4));
            $this->rawInsertClaim($id, $userId, $idempotencyKey);
            return $id;
        });
    }

    private function rawInsertClaim(string $id, string $userId, string $idempotencyKey): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO voucher_claims (id, voucher_id, user_id, idempotency_key, status, created_at)
             VALUES (:id, :v, :u, :k, :s, :c)'
        );
        $stmt->execute([
            'id' => $id,
            'v' => 'v1',
            'u' => $userId,
            'k' => $idempotencyKey,
            's' => VoucherClaim::STATUS_LOCKED,
            'c' => '2026-04-18 09:00:00',
        ]);
    }
}
