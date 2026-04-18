<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Service\FixedClock;
use App\Service\StorageTieringService;
use PHPUnit\Framework\TestCase;

final class StorageTieringTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/tier-' . bin2hex(random_bytes(4));
        mkdir($this->base, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->base)) {
            $this->rrmdir($this->base);
        }
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $p = $dir . '/' . $entry;
            if (is_dir($p)) {
                $this->rrmdir($p);
            } else {
                unlink($p);
            }
        }
        rmdir($dir);
    }

    public function testTierMovesOldFiles(): void
    {
        $hot = $this->base . '/hot';
        $cold = $this->base . '/cold';
        $clock = new FixedClock(new \DateTimeImmutable('2026-06-01'));
        $svc = new StorageTieringService($hot, $cold, $clock);

        $oldFile = $hot . '/old.txt';
        $newFile = $hot . '/new.txt';
        file_put_contents($oldFile, 'old');
        file_put_contents($newFile, 'new');
        touch($oldFile, strtotime('2025-01-01'));
        touch($newFile, strtotime('2026-05-25'));

        $result = $svc->tier();
        self::assertCount(1, $result['moved']);
        self::assertCount(1, $result['kept']);
        self::assertFileExists($cold . '/old.txt');

        $snap = $svc->snapshot();
        self::assertSame(1, $snap['hot']);
        self::assertSame(1, $snap['cold']);
    }

    public function testInvalidAgeThreshold(): void
    {
        $this->expectException(ValidationException::class);
        new StorageTieringService($this->base . '/h', $this->base . '/c', new FixedClock(new \DateTimeImmutable()), 0);
    }

    public function testIgnoresSubdirectories(): void
    {
        $hot = $this->base . '/hot';
        $cold = $this->base . '/cold';
        $clock = new FixedClock(new \DateTimeImmutable('2026-06-01'));
        $svc = new StorageTieringService($hot, $cold, $clock);
        mkdir($hot . '/sub');
        $result = $svc->tier();
        self::assertSame(0, count($result['moved']));
    }

    public function testDirectoryCreationFailure(): void
    {
        $conflict = $this->base . '/file-not-dir';
        touch($conflict);
        $this->expectException(\RuntimeException::class);
        new StorageTieringService($conflict, $this->base . '/cold', new FixedClock(new \DateTimeImmutable()));
    }
}
