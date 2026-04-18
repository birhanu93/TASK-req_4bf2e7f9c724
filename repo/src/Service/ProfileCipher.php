<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Versioned profile data cipher. Ciphertext envelope:
 *   base64( "v\0" || u32be(version) || IV(12) || TAG(16) || CIPHERTEXT )
 *
 * The version lets records written under an older DEK be read after rotation.
 */
final class ProfileCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const HEADER = "v\0";

    public function __construct(private Keyring $keyring)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $version = $this->keyring->currentVersion();
        $dek = $this->keyring->dekForVersion($version);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plaintext, self::CIPHER, $dek, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('profile encrypt failed');
        }
        return base64_encode(self::HEADER . pack('N', $version) . $iv . $tag . $ct);
    }

    public function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || !str_starts_with($raw, self::HEADER)) {
            throw new \RuntimeException('invalid profile ciphertext envelope');
        }
        $version = unpack('N', substr($raw, 2, 4))[1] ?? 0;
        $iv = substr($raw, 6, 12);
        $tag = substr($raw, 18, 16);
        $ct = substr($raw, 34);
        $dek = $this->keyring->dekForVersion($version);
        $pt = openssl_decrypt($ct, self::CIPHER, $dek, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new \RuntimeException('profile decrypt failed — wrong key version, corrupted payload, or KEK mismatch');
        }
        return $pt;
    }
}
