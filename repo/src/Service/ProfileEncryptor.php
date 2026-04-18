<?php

declare(strict_types=1);

namespace App\Service;

final class ProfileEncryptor
{
    private const CIPHER = 'aes-256-gcm';

    public function __construct(private string $key)
    {
        if (strlen($key) !== 32) {
            throw new \InvalidArgumentException('encryption key must be 32 bytes');
        }
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ct);
    }

    public function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new \RuntimeException('invalid ciphertext');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);
        $pt = openssl_decrypt($ct, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new \RuntimeException('decryption failed');
        }
        return $pt;
    }
}
