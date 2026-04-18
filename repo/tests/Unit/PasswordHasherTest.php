<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class PasswordHasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $h = new PasswordHasher();
        $hash = $h->hash('s3cret');
        self::assertTrue($h->verify('s3cret', $hash));
        self::assertFalse($h->verify('wrong', $hash));
    }

    public function testEmptyPasswordThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PasswordHasher())->hash('');
    }
}
