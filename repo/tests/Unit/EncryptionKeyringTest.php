<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class EncryptionKeyringTest extends TestCase
{
    public function testRoundTripEncrypt(): void
    {
        $k = Factory::kernel();
        $blob = $k->profileCipher->encrypt('hello world');
        self::assertNotSame('hello world', $blob);
        self::assertSame('hello world', $k->profileCipher->decrypt($blob));
    }

    public function testRotationPreservesOldCiphertext(): void
    {
        $k = Factory::kernel();
        $v1 = $k->keyring->currentVersion();
        $blob = $k->profileCipher->encrypt('alpha');
        $newKey = $k->keyring->rotate();
        self::assertGreaterThan($v1, $newKey->getVersion());
        self::assertSame('alpha', $k->profileCipher->decrypt($blob));

        $fresh = $k->profileCipher->encrypt('beta');
        self::assertSame('beta', $k->profileCipher->decrypt($fresh));
    }

    public function testProfileServiceRoundTrip(): void
    {
        $k = Factory::kernel();
        $user = $k->auth->register('alice', 'pw-12345', ['trainee']);
        $k->profileService->write($user->getId(), ['fullName' => 'Alice Example', 'pronouns' => 'she/her'], 'admin');
        $read = $k->profileService->read($user->getId());
        self::assertSame('Alice Example', $read['fullName']);
        self::assertSame('she/her', $read['pronouns']);
    }

    public function testTamperedCiphertextRejected(): void
    {
        $k = Factory::kernel();
        // Use enough plaintext to guarantee bytes past the 34-byte envelope
        // header (2 magic + 4 version + 12 IV + 16 tag).
        $blob = $k->profileCipher->encrypt(str_repeat('secret-', 20));
        $raw = base64_decode($blob, true);
        $pos = 40;
        self::assertGreaterThan($pos, strlen($raw));
        $raw[$pos] = chr(ord($raw[$pos]) ^ 0x01);
        $tampered = base64_encode($raw);
        $this->expectException(\RuntimeException::class);
        $k->profileCipher->decrypt($tampered);
    }
}
