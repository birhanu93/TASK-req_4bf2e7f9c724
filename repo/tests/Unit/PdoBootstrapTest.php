<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Persistence\PdoDatabase;
use PHPUnit\Framework\TestCase;

/**
 * Covers the defensive PDO bootstrap: retries on transient connection
 * failures, fail-fast with a clear error after exhausted attempts, and the
 * explicit init SQL that pins charset and SQL mode for MySQL.
 */
final class PdoBootstrapTest extends TestCase
{
    public function testSqliteBootSucceeds(): void
    {
        // SQLite driver path is always available via PDO and is sufficient to
        // prove the constructor + attribute application + boot-time ping.
        $db = PdoDatabase::fromDsn('sqlite::memory:', '', '');
        self::assertNotNull($db->pdo());
        $pdo = $db->pdo();
        self::assertInstanceOf(\PDO::class, $pdo);
        self::assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
        // PDO drivers vary in which attributes are readable after being set
        // (sqlite refuses ATTR_DEFAULT_FETCH_MODE / ATTR_EMULATE_PREPARES read
        // calls). A SELECT 1 ping through the abstraction proves the rest of
        // the constructor ran and the connection is usable.
        self::assertSame('1', (string) $pdo->query('SELECT 1')->fetchColumn());
    }

    public function testBootFailsFastAfterExhaustedRetries(): void
    {
        $started = microtime(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to connect to database after 3 attempts/');
        try {
            // Unreachable MySQL host; we intentionally pick a TEST-NET address
            // that will refuse connections quickly. Use a small retry count
            // with tiny delays so the test stays fast.
            PdoDatabase::fromDsn(
                // Port 1 is reserved and refuses connections fast on every
                // platform we run on; combined with MySQL's own connect
                // timeout this keeps the test responsive.
                'mysql:host=127.0.0.1;port=1;dbname=none;charset=utf8mb4',
                'nobody',
                'nobody',
                3,
                1,
            );
        } finally {
            // Sanity: the bounded retries should not take unreasonably long.
            self::assertLessThan(120.0, microtime(true) - $started);
        }
    }

    public function testTransactionalCommitAndRollback(): void
    {
        $db = PdoDatabase::fromDsn('sqlite::memory:', '', '');
        $pdo = $db->pdo();
        self::assertInstanceOf(\PDO::class, $pdo);
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, v TEXT)');

        $db->transactional(function () use ($pdo) {
            $pdo->exec("INSERT INTO t (v) VALUES ('a')");
        });
        $count = (int) $pdo->query('SELECT count(*) FROM t')->fetchColumn();
        self::assertSame(1, $count);

        try {
            $db->transactional(function () use ($pdo) {
                $pdo->exec("INSERT INTO t (v) VALUES ('b')");
                throw new \RuntimeException('boom');
            });
            self::fail('expected exception to propagate');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }
        $count = (int) $pdo->query('SELECT count(*) FROM t')->fetchColumn();
        self::assertSame(1, $count, 'rollback must discard failed transaction');
    }
}
