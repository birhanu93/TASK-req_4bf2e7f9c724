<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Certificate;
use App\Service\PdfCertificate;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * The rendered PDF must carry the verification code *and* a human-legible
 * status string. Revocation re-renders the stored file so a downloaded PDF
 * always reflects the current state — a forensic reader should never see a
 * revoked certificate claiming to be active.
 */
final class PdfCertificateContentTest extends TestCase
{
    public function testActiveCertificatePdfContainsVerificationAndStatus(): void
    {
        $pdf = PdfCertificate::render(
            'alice',
            'Bronze',
            'ABCDEF123456',
            new \DateTimeImmutable('2026-04-18T10:00:00+00:00'),
            Certificate::STATUS_ACTIVE,
        );
        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('Verification: ABCDEF123456', $pdf);
        self::assertStringContainsString('Status: ACTIVE', $pdf);
        self::assertStringNotContainsString('Status: REVOKED', $pdf);
    }

    public function testRevokedCertificatePdfAdvertisesRevokedStatus(): void
    {
        $pdf = PdfCertificate::render(
            'alice',
            'Bronze',
            'ABCDEF123456',
            new \DateTimeImmutable('2026-04-18T10:00:00+00:00'),
            Certificate::STATUS_REVOKED,
        );
        self::assertStringContainsString('Verification: ABCDEF123456', $pdf);
        self::assertStringContainsString('Status: REVOKED', $pdf);
        self::assertStringNotContainsString('Status: ACTIVE', $pdf);
    }

    public function testRevokeRewritesStoredPdfWithRevokedStatus(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', ['trainee']);
        $aliceId = $k->users->findByUsername('alice')->getId();
        $rank = $k->assessmentService->createRank('Bronze', 1, 0, 1);

        $cert = $k->certService->issue($aliceId, $rank->getId(), 'admin');
        $activePdf = (string) file_get_contents($cert->getPdfPath());
        self::assertStringContainsString('Status: ACTIVE', $activePdf);

        $k->certService->revoke($cert->getId(), 'admin');
        $revokedPdf = (string) file_get_contents($cert->getPdfPath());
        self::assertStringContainsString('Status: REVOKED', $revokedPdf);
        self::assertStringContainsString('Verification: ' . $cert->getVerificationCode(), $revokedPdf);
    }

    public function testDownloadedPdfAfterRevokeShowsRevokedStatus(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', ['trainee']);
        $aliceId = $k->users->findByUsername('alice')->getId();
        $rank = $k->assessmentService->createRank('Bronze', 1, 0, 1);
        $cert = $k->certService->issue($aliceId, $rank->getId(), 'admin');
        $k->certService->revoke($cert->getId(), 'admin');

        $pdf = $k->certService->readPdf($cert->getId());
        self::assertStringContainsString('Status: REVOKED', $pdf);
    }
}
