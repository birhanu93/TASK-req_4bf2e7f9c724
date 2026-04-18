<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * In-memory connection. Transactions are savepoints tracked by depth; rollback
 * is a best-effort no-op (in-memory repositories mutate objects by reference,
 * so true rollback semantics are not available without snapshots). This is
 * acceptable for unit tests where failure paths are exercised explicitly and
 * the kernel is disposed between cases.
 */
final class InMemoryDatabase implements Database
{
    private int $depth = 0;

    /** @var array<string,bool> */
    private array $locks = [];

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
        $this->depth++;
    }

    public function commit(): void
    {
        if ($this->depth <= 0) {
            throw new \RuntimeException('commit without active transaction');
        }
        $this->depth--;
        if ($this->depth === 0) {
            $this->locks = [];
        }
    }

    public function rollBack(): void
    {
        if ($this->depth <= 0) {
            throw new \RuntimeException('rollBack without active transaction');
        }
        $this->depth--;
        if ($this->depth === 0) {
            $this->locks = [];
        }
    }

    public function inTransaction(): bool
    {
        return $this->depth > 0;
    }

    public function lock(string $resource): void
    {
        // Per-process advisory lock: guards logical resource contention within
        // a single test run. Real isolation is still enforced by MySQL row
        // locks in production.
        if (isset($this->locks[$resource])) {
            return;
        }
        $this->locks[$resource] = true;
    }

    public function pdo(): ?\PDO
    {
        return null;
    }
}
