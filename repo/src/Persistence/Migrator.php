<?php

declare(strict_types=1);

namespace App\Persistence;

final class Migrator
{
    public function __construct(
        private \PDO $pdo,
        private string $directory,
    ) {
    }

    public function migrate(): array
    {
        $this->ensureMigrationsTable();
        $applied = $this->listApplied();
        $files = $this->listFiles();
        $ran = [];
        foreach ($files as $name => $path) {
            if (isset($applied[$name])) {
                continue;
            }
            $sql = (string) file_get_contents($path);
            // MySQL implicitly commits on DDL (CREATE/ALTER/DROP), so wrapping a
            // migration in an explicit transaction is unsound — the first DDL
            // ends the transaction silently and a later commit/rollBack then
            // raises "no active transaction". Execute statements directly; the
            // migrations are authored to be idempotent (CREATE TABLE IF NOT
            // EXISTS, etc.) so partial failures are safe to retry. The
            // schema_migrations row is only inserted after every statement
            // succeeds, so a mid-file failure leaves the migration un-recorded
            // and the next run picks up from the same file.
            try {
                foreach ($this->splitStatements($sql) as $stmt) {
                    if (trim($stmt) === '') {
                        continue;
                    }
                    $this->pdo->exec($stmt);
                }
                $ins = $this->pdo->prepare('INSERT INTO schema_migrations(name, applied_at) VALUES(:n, NOW())');
                $ins->execute(['n' => $name]);
                $ran[] = $name;
            } catch (\Throwable $e) {
                throw new \RuntimeException("migration {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }
        return $ran;
    }

    /**
     * @return array<string,string>
     */
    private function listFiles(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.sql') ?: [];
        sort($files);
        $out = [];
        foreach ($files as $f) {
            $out[basename($f)] = $f;
        }
        return $out;
    }

    /**
     * @return array<string,bool>
     */
    private function listApplied(): array
    {
        $rows = $this->pdo->query('SELECT name FROM schema_migrations')->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['name']] = true;
        }
        return $out;
    }

    private function ensureMigrationsTable(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            name VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    /**
     * Split a multi-statement SQL file on top-level semicolons, preserving any
     * statements that define procedures or use DELIMITER directives.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*--[^\n]*\n/m', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($c === "'" && !$inDouble && !$inBacktick && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inSingle = !$inSingle;
            } elseif ($c === '"' && !$inSingle && !$inBacktick && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $inDouble = !$inDouble;
            } elseif ($c === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }
            if ($c === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $c;
        }
        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }
        return $statements;
    }
}
