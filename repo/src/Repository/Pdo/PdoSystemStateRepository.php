<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Repository\Contract\SystemStateRepositoryInterface;

final class PdoSystemStateRepository implements SystemStateRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function claim(string $marker, string $value, \DateTimeImmutable $at): bool
    {
        // INSERT IGNORE against a PRIMARY KEY gives us an atomic first-writer-
        // wins primitive. Duplicate marker rows raise 1062 which IGNORE turns
        // into a zero rowCount; we observe that signal as "already claimed".
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO system_state (marker, value, updated_at) VALUES (:m, :v, :t)'
        );
        $stmt->execute([
            'm' => $marker,
            'v' => $value,
            't' => $at->format('Y-m-d H:i:s'),
        ]);
        return $stmt->rowCount() === 1;
    }

    public function get(string $marker): ?string
    {
        $stmt = $this->pdo->prepare('SELECT value FROM system_state WHERE marker = :m');
        $stmt->execute(['m' => $marker]);
        $row = $stmt->fetch();
        return $row ? (string) $row['value'] : null;
    }
}
