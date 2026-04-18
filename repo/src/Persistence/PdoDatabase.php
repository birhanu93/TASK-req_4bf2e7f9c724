<?php

declare(strict_types=1);

namespace App\Persistence;

final class PdoDatabase implements Database
{
    private int $depth = 0;

    public function __construct(private \PDO $pdo)
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
    }

    /**
     * Build a PDO connection with defensive defaults and bounded retry. Explicit
     * init SQL pins charset + strict SQL mode so the application never runs on
     * a MySQL server with a permissive mode that would silently truncate data.
     */
    public static function fromDsn(string $dsn, string $user, string $password, int $maxAttempts = 5, int $retryDelayMs = 200): self
    {
        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false,
        ];
        if (str_starts_with($dsn, 'mysql:')) {
            // PHP 8.5 moved MYSQL_ATTR_INIT_COMMAND to the Pdo\Mysql namespace
            // and deprecated the legacy constant. Resolve whichever is
            // available so the app works on 8.2+ without warnings.
            $initKey = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
                ? constant('Pdo\\Mysql::ATTR_INIT_COMMAND')
                : \PDO::MYSQL_ATTR_INIT_COMMAND;
            $options[$initKey] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION', time_zone='+00:00'";
        }

        $lastError = null;
        for ($attempt = 1; $attempt <= max(1, $maxAttempts); $attempt++) {
            try {
                $pdo = new \PDO($dsn, $user, $password, $options);
                // Boot-time ping so connectivity failures surface now, not on
                // the first request.
                $pdo->query('SELECT 1');
                return new self($pdo);
            } catch (\PDOException $e) {
                $lastError = $e;
                if ($attempt >= $maxAttempts) {
                    break;
                }
                usleep(max(1, $retryDelayMs) * 1000 * $attempt);
            }
        }
        throw new \RuntimeException(
            'failed to connect to database after ' . $maxAttempts . ' attempts: '
            . ($lastError?->getMessage() ?? 'unknown error'),
            0,
            $lastError,
        );
    }

    public function transactional(callable $fn): mixed
    {
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->depth === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT sp_{$this->depth}");
        }
        $this->depth++;
    }

    public function commit(): void
    {
        if ($this->depth <= 0) {
            throw new \RuntimeException('commit without active transaction');
        }
        $this->depth--;
        if ($this->depth === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT sp_{$this->depth}");
        }
    }

    public function rollBack(): void
    {
        if ($this->depth <= 0) {
            throw new \RuntimeException('rollBack without active transaction');
        }
        $this->depth--;
        if ($this->depth === 0) {
            $this->pdo->rollBack();
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT sp_{$this->depth}");
        }
    }

    public function inTransaction(): bool
    {
        return $this->depth > 0;
    }

    public function lock(string $resource): void
    {
        $name = substr('wf_' . hash('sha1', $resource), 0, 64);
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:n, 10)');
        $stmt->execute(['n' => $name]);
        $ok = $stmt->fetchColumn();
        if ((int) $ok !== 1) {
            throw new \RuntimeException("failed to acquire lock for {$resource}");
        }
    }

    public function pdo(): ?\PDO
    {
        return $this->pdo;
    }
}
