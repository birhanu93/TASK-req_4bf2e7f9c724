<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\FixedClock;
use App\Service\SequenceIdGenerator;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class CertificateServiceTest extends TestCase
{
    public function testIssueAndVerify(): void
    {
        $k = Factory::kernel();
        $rank = $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $cert = $k->certService->issue('u1', $rank->getId(), 'admin');
        self::assertTrue($cert->isValid());
        self::assertSame(
            $cert->getId(),
            $k->certService->verify($cert->getVerificationCode())->getId(),
        );
        self::assertFileExists($cert->getPdfPath());
    }

    public function testIssueMissingRank(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->certService->issue('u1', 'none', 'admin');
    }

    public function testVerifyMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->certService->verify('missing');
    }

    public function testRevoke(): void
    {
        $k = Factory::kernel();
        $rank = $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $cert = $k->certService->issue('u1', $rank->getId(), 'admin');
        $revoked = $k->certService->revoke($cert->getId(), 'admin');
        self::assertFalse($revoked->isValid());
    }

    public function testRevokeMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->certService->revoke('nope', 'admin');
    }

    public function testReadPdf(): void
    {
        $k = Factory::kernel();
        $rank = $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $cert = $k->certService->issue('u1', $rank->getId(), 'admin');
        $data = $k->certService->readPdf($cert->getId());
        self::assertStringStartsWith('%PDF-', $data);
        self::assertStringEndsWith("%%EOF\n", $data);
    }

    public function testReadPdfMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->certService->readPdf('nope');
    }

    public function testReadPdfFileMissing(): void
    {
        $k = Factory::kernel();
        $rank = $k->assessmentService->createRank('Bronze', 5, 0, 1);
        $cert = $k->certService->issue('u1', $rank->getId(), 'admin');
        unlink($cert->getPdfPath());
        $this->expectException(ValidationException::class);
        $k->certService->readPdf($cert->getId());
    }

    public function testStorageCreationFailure(): void
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-04-18'));
        $ids = new SequenceIdGenerator('z');
        $badPath = sys_get_temp_dir() . '/cert-file-conflict-' . bin2hex(random_bytes(4));
        touch($badPath);
        try {
            new \App\Service\CertificateService(
                new \App\Repository\CertificateRepository(),
                new \App\Repository\RankRepository(),
                new \App\Repository\UserRepository(),
                $clock,
                $ids,
                new \App\Service\AuditLogger(
                    new \App\Repository\AuditLogRepository(),
                    $clock,
                    $ids,
                ),
                $badPath,
            );
            self::fail('expected runtime exception');
        } catch (\RuntimeException) {
            self::assertTrue(true);
        } finally {
            @unlink($badPath);
        }
    }
}
