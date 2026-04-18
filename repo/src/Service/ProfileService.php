<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\UserRepositoryInterface;

/**
 * Encrypted profile facade. Reads/writes the opaque `encryptedProfile` blob on
 * the User entity through the versioned ProfileCipher + Keyring. Ciphertext
 * never leaves this service as plaintext without first passing through the
 * authorization layer in the controller.
 */
final class ProfileService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ProfileCipher $cipher,
        private Keyring $keyring,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function read(string $userId): array
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new NotFoundException('user not found');
        }
        $blob = $user->getEncryptedProfile();
        if ($blob === null) {
            return [];
        }
        $json = $this->cipher->decrypt($blob);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string,mixed> $data
     */
    public function write(string $userId, array $data, string $actorId): void
    {
        $user = $this->users->find($userId);
        if ($user === null) {
            throw new NotFoundException('user not found');
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new ValidationException('profile must be JSON-encodable');
        }
        $blob = $this->cipher->encrypt($json);
        $user->setEncryptedProfile($blob);
        $this->users->save($user);
        $this->audit->record($actorId, 'profile.update', 'user', $userId, [], [
            'keyVersion' => $this->keyring->currentVersion(),
            'fields' => array_keys($data),
        ]);
    }
}
