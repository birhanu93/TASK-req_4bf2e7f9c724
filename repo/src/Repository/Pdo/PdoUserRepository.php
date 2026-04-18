<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\User;
use App\Repository\Contract\UserRepositoryInterface;

final class PdoUserRepository implements UserRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(User $user): void
    {
        $sql = 'INSERT INTO users (id, username, password_hash, roles, encrypted_profile_blob, encrypted_profile_key_version, active, created_at, updated_at)
                VALUES (:id, :username, :password_hash, :roles, :blob, :kv, :active, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                  username = VALUES(username),
                  password_hash = VALUES(password_hash),
                  roles = VALUES(roles),
                  encrypted_profile_blob = VALUES(encrypted_profile_blob),
                  encrypted_profile_key_version = VALUES(encrypted_profile_key_version),
                  active = VALUES(active),
                  updated_at = NOW()';
        $profile = $user->getEncryptedProfile();
        $blob = null;
        $keyVersion = null;
        if ($profile !== null) {
            $decoded = base64_decode($profile, true);
            if ($decoded !== false && str_starts_with($decoded, "v\0")) {
                // Versioned envelope: "v\0" + 4-byte version BE + ciphertext
                $keyVersion = unpack('N', substr($decoded, 2, 4))[1] ?? null;
                $blob = substr($decoded, 6);
            } else {
                $blob = $profile;
            }
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'password_hash' => $user->getPasswordHash(),
            'roles' => implode(',', $user->getRoles()),
            'blob' => $blob,
            'kv' => $keyVersion,
            'active' => $user->isActive() ? 1 : 0,
        ]);
    }

    public function find(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM users')->fetchAll();
        return array_map(fn ($r) => $this->hydrate($r), $rows);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): User
    {
        $roles = $row['roles'] === '' ? [] : explode(',', (string) $row['roles']);
        $profile = null;
        if ($row['encrypted_profile_blob'] !== null) {
            $version = $row['encrypted_profile_key_version'] !== null ? (int) $row['encrypted_profile_key_version'] : 0;
            $profile = base64_encode("v\0" . pack('N', $version) . (string) $row['encrypted_profile_blob']);
        }
        return new User(
            (string) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            $roles,
            $profile,
            (bool) $row['active'],
        );
    }
}
