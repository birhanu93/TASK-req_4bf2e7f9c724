<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PdfCertificate;
use PHPUnit\Framework\TestCase;

final class PdfCertificateTest extends TestCase
{
    public function testOutputIsValidPdfShell(): void
    {
        $pdf = PdfCertificate::render(
            'Alice Example',
            'Gold',
            'ABCDEF123456',
            new \DateTimeImmutable('2026-04-18T10:00:00+00:00'),
        );
        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertStringContainsString('/Type /Catalog', $pdf);
        self::assertStringContainsString('/Type /Pages', $pdf);
        self::assertStringContainsString('/Type /Page', $pdf);
        self::assertStringContainsString('Helvetica-Bold', $pdf);
        self::assertStringContainsString('startxref', $pdf);
        self::assertGreaterThan(500, strlen($pdf));
    }

    public function testEscapesSpecialChars(): void
    {
        $pdf = PdfCertificate::render(
            'Name (parens) and \\ backslash',
            'Rank',
            'DEADBEEF1234',
            new \DateTimeImmutable('2026-04-18T10:00:00+00:00'),
        );
        // Escapes: ( => \(, ) => \), \ => \\
        self::assertStringContainsString('\\\\ backslash', $pdf);
        self::assertStringContainsString('\\(parens\\)', $pdf);
    }
}
