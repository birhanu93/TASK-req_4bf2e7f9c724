<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ProfileKey;
use App\Repository\Contract\ProfileKeyRepositoryInterface;

/**
 * Local key management with versioned data encryption keys (DEKs). Each DEK is
 * wrapped with a long-lived Key Encryption Key (KEK) read from a file owned by
 * the app user. Rotations add a new DEK version; all ciphertext records carry
 * the version so old data remains readable after rotation.
 */
final class Keyring
{
    private const WRAPPED_HEADER = "kw1\0";

    public function __construct(
        private ProfileKeyRepositoryInterface $store,
        private string $kek,
        private Clock $clock,
    ) {
        if (strlen($kek) !== 32) {
            throw new \InvalidArgumentException('KEK must be exactly 32 bytes');
        }
    }

    /**
     * Read or create the KEK file. The kek is created with 0o600 permissions
     * and must not be committed to the repository.
     */
    public static function loadKek(string $path): string
    {
        if (is_file($path)) {
            $data = (string) file_get_contents($path);
            if (strlen($data) !== 32) {
                throw new \RuntimeException("kek file {$path} must be exactly 32 bytes");
            }
            return $data;
        }
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("failed to create kek directory {$dir}");
        }
        $data = random_bytes(32);
        file_put_contents($path, $data);
        @chmod($path, 0600);
        return $data;
    }

    public function currentVersion(): int
    {
        $active = $this->store->latestActive();
        if ($active === null) {
            $active = $this->rotate();
        }
        return $active->getVersion();
    }

    public function rotate(): ProfileKey
    {
        $existing = $this->store->findAll();
        $nextVersion = 1;
        foreach ($existing as $k) {
            $nextVersion = max($nextVersion, $k->getVersion() + 1);
            if ($k->isActive()) {
                $k->retire($this->clock->now());
                $this->store->save($k);
            }
        }
        $dek = random_bytes(32);
        $wrapped = $this->wrap($dek);
        $key = new ProfileKey($nextVersion, $wrapped, $this->clock->now());
        $this->store->save($key);
        return $key;
    }

    public function dekForVersion(int $version): string
    {
        $record = $this->store->findByVersion($version);
        if ($record === null) {
            throw new \RuntimeException("profile key version {$version} not found");
        }
        return $this->unwrap($record->getWrappedKey());
    }

    private function wrap(string $dek): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($dek, 'aes-256-gcm', $this->kek, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('failed to wrap DEK');
        }
        return self::WRAPPED_HEADER . $iv . $tag . $ct;
    }

    private function unwrap(string $wrapped): string
    {
        if (!str_starts_with($wrapped, self::WRAPPED_HEADER)) {
            throw new \RuntimeException('invalid wrapped key header');
        }
        $body = substr($wrapped, strlen(self::WRAPPED_HEADER));
        $iv = substr($body, 0, 12);
        $tag = substr($body, 12, 16);
        $ct = substr($body, 28);
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $this->kek, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new \RuntimeException('failed to unwrap DEK — bad KEK or corrupted payload');
        }
        return $pt;
    }
}
