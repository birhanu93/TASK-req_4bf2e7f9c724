<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\ValidationException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class ModerationAttachmentTest extends TestCase
{
    public function testAcceptsValidPng(): void
    {
        $k = Factory::kernel();
        $k->auth->register('trainee', 'pw-12345', ['trainee']);
        $id = $k->users->findByUsername('trainee')->getId();
        $item = $k->moderationService->submit($id, 'evidence', 'my evidence text');
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("x", 1000);
        $attachment = $k->moderationService->attach($item->getId(), $id, 'proof.png', 'image/png', $png);
        self::assertSame('image/png', $attachment->getMimeType());
        self::assertSame(hash('sha256', $png), $attachment->getChecksum());
        self::assertFileExists($attachment->getStoragePath());
    }

    public function testRejectsUnknownMime(): void
    {
        $k = Factory::kernel();
        $k->auth->register('trainee', 'pw-12345', ['trainee']);
        $id = $k->users->findByUsername('trainee')->getId();
        $item = $k->moderationService->submit($id, 'evidence', 'my evidence 2');
        $this->expectException(ValidationException::class);
        $k->moderationService->attach($item->getId(), $id, 'a.exe', 'application/x-dosexec', str_repeat('x', 100));
    }

    public function testRejectsOversizedFile(): void
    {
        $k = Factory::kernel();
        $k->auth->register('trainee', 'pw-12345', ['trainee']);
        $id = $k->users->findByUsername('trainee')->getId();
        $item = $k->moderationService->submit($id, 'evidence', 'my evidence 3');
        $this->expectException(ValidationException::class);
        $k->moderationService->attach($item->getId(), $id, 'large.png', 'image/png', str_repeat('x', 10_000_000));
    }

    public function testRejectsMimeMismatch(): void
    {
        $k = Factory::kernel();
        $k->auth->register('trainee', 'pw-12345', ['trainee']);
        $id = $k->users->findByUsername('trainee')->getId();
        $item = $k->moderationService->submit($id, 'evidence', 'my evidence 4');
        // Claim PNG but supply JPEG magic bytes.
        $this->expectException(ValidationException::class);
        $k->moderationService->attach($item->getId(), $id, 'fake.png', 'image/png', "\xff\xd8\xff\xe0" . str_repeat('x', 100));
    }

    public function testRejectsPathTraversalFilename(): void
    {
        $k = Factory::kernel();
        $k->auth->register('trainee', 'pw-12345', ['trainee']);
        $id = $k->users->findByUsername('trainee')->getId();
        $item = $k->moderationService->submit($id, 'evidence', 'my evidence 5');
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("x", 100);
        $attachment = $k->moderationService->attach($item->getId(), $id, '../../etc/passwd', 'image/png', $png);
        // basename() strips path separators; the stored filename must not
        // escape the upload directory.
        self::assertStringNotContainsString('..', $attachment->getFilename());
        self::assertStringNotContainsString('/', $attachment->getFilename());
    }

    public function testAuthorBindingEnforced(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', ['trainee']);
        $k->auth->register('bob', 'pw-12345', ['trainee']);
        $alice = $k->users->findByUsername('alice')->getId();
        $bob = $k->users->findByUsername('bob')->getId();
        $item = $k->moderationService->submit($alice, 'evidence', 'alice-evidence');
        $png = "\x89PNG\r\n\x1a\n" . str_repeat("x", 100);
        $this->expectException(ValidationException::class);
        $k->moderationService->attach($item->getId(), $bob, 'x.png', 'image/png', $png);
    }
}
