<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\AuthSessionRecord;
use App\Repository\Contract\AuthSessionRepositoryInterface;

final class PdoAuthSessionRepository implements AuthSessionRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(AuthSessionRecord $session): void
    {
        $sql = 'INSERT INTO auth_sessions (token, user_id, active_role, issued_at, expires_at, revoked)
                VALUES (:t, :u, :r, :i, :e, :rev)
                ON DUPLICATE KEY UPDATE
                  active_role = VALUES(active_role),
                  expires_at = VALUES(expires_at),
                  revoked = VALUES(revoked)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            't' => $session->getToken(),
            'u' => $session->getUserId(),
            'r' => $session->getActiveRole(),
            'i' => $session->getIssuedAt()->format('Y-m-d H:i:s'),
            'e' => $session->getExpiresAt()->format('Y-m-d H:i:s'),
            'rev' => $session->isRevoked() ? 1 : 0,
        ]);
    }

    public function findByToken(string $token): ?AuthSessionRecord
    {
        $stmt = $this->pdo->prepare('SELECT * FROM auth_sessions WHERE token = :t');
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return new AuthSessionRecord(
            (string) $row['token'],
            (string) $row['user_id'],
            (string) $row['active_role'],
            new \DateTimeImmutable((string) $row['issued_at']),
            new \DateTimeImmutable((string) $row['expires_at']),
            (bool) $row['revoked'],
        );
    }

    public function revoke(string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET revoked = 1 WHERE token = :t');
        $stmt->execute(['t' => $token]);
    }

    public function revokeByUser(string $userId): int
    {
        $stmt = $this->pdo->prepare('UPDATE auth_sessions SET revoked = 1 WHERE user_id = :u AND revoked = 0');
        $stmt->execute(['u' => $userId]);
        return $stmt->rowCount();
    }

    public function deleteExpired(\DateTimeImmutable $before): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM auth_sessions WHERE expires_at < :b');
        $stmt->execute(['b' => $before->format('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }
}
