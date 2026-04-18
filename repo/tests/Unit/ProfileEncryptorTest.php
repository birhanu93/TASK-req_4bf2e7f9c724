<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\ProfileEncryptor;
use PHPUnit\Framework\TestCase;

final class ProfileEncryptorTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $enc = new ProfileEncryptor(str_repeat('K', 32));
        $blob = $enc->encrypt('hello');
        self::assertNotSame('hello', $blob);
        self::assertSame('hello', $enc->decrypt($blob));
    }

    public function testInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProfileEncryptor('short');
    }

    public function testDecryptBadBlob(): void
    {
        $enc = new ProfileEncryptor(str_repeat('K', 32));
        $this->expectException(\RuntimeException::class);
        $enc->decrypt('not-base64-!@#');
    }

    public function testDecryptTampered(): void
    {
        $enc = new ProfileEncryptor(str_repeat('K', 32));
        $blob = $enc->encrypt('hello');
        $raw = base64_decode($blob);
        $raw[0] = chr(ord($raw[0]) ^ 0xFF);
        $this->expectException(\RuntimeException::class);
        $enc->decrypt(base64_encode($raw));
    }
}
