<?php

declare(strict_types=1);

namespace App\Persistence;

/**
 * Connection abstraction used by services that need transactional or row-level
 * lock semantics. The in-memory implementation is a no-op so unit tests keep
 * running without a database; the PDO implementation provides real MySQL
 * transactions and SELECT ... FOR UPDATE support.
 */
interface Database
{
    public function transactional(callable $fn): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;

    /**
     * Acquire a row-level lock for the given logical resource. The in-memory
     * implementation falls back to a process-local mutex that still protects
     * single-process test concurrency.
     */
    public function lock(string $resource): void;

    public function pdo(): ?\PDO;
}
