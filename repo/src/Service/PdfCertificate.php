<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Pure-PHP PDF 1.4 certificate renderer. Produces a single-page landscape
 * document with a decorative border and centered text. The output is a valid
 * PDF accepted by standard readers (Preview, Acrobat, Chrome); no external
 * libraries required.
 *
 * Layout: 842 x 595 pt (A4 landscape). Coordinates in PDF points, origin at
 * bottom-left.
 */
final class PdfCertificate
{
    public static function render(
        string $traineeLabel,
        string $rankName,
        string $verificationCode,
        \DateTimeImmutable $issuedAt,
        string $status = \App\Entity\Certificate::STATUS_ACTIVE,
    ): string {
        $title = self::escape('Certificate of Achievement');
        $subtitle = self::escape('This certifies that');
        $name = self::escape($traineeLabel);
        $bodyPrefix = self::escape('has achieved the rank of');
        $rank = self::escape($rankName);
        $verify = self::escape('Verification: ' . $verificationCode);
        $issued = self::escape('Issued: ' . $issuedAt->format('Y-m-d H:i T'));
        // Status is rendered visibly alongside the verification code so a
        // revoked certificate is legibly marked if someone later retrieves
        // the stored PDF. Upper-case for legibility in print.
        $statusLabel = self::escape('Status: ' . strtoupper($status));

        // Centering: the PDF page is 842pt wide. We approximate centering by
        // estimating string widths (Helvetica ~ 0.5 * fontSize per char).
        $centerX = fn (string $s, float $fontSize): int => (int) round((842 - strlen($s) * $fontSize * 0.5) / 2);

        $contentStream = "";
        $contentStream .= "q\n";
        // Decorative border: a rectangle with 2pt stroke, 20pt margin
        $contentStream .= "1 0 0 1 0 0 cm\n";
        $contentStream .= "2 w\n";
        $contentStream .= "0.2 0.2 0.5 RG\n";
        $contentStream .= "20 20 802 555 re\nS\n";
        $contentStream .= "0.4 0.4 0.7 RG\n";
        $contentStream .= "30 30 782 535 re\nS\n";
        $contentStream .= "0 0 0 rg\n";

        $contentStream .= "BT /F1 28 Tf " . $centerX($title, 28) . " 480 Td (" . $title . ") Tj ET\n";
        $contentStream .= "BT /F2 14 Tf " . $centerX($subtitle, 14) . " 420 Td (" . $subtitle . ") Tj ET\n";
        $contentStream .= "BT /F1 24 Tf " . $centerX($name, 24) . " 370 Td (" . $name . ") Tj ET\n";
        $contentStream .= "BT /F2 14 Tf " . $centerX($bodyPrefix, 14) . " 310 Td (" . $bodyPrefix . ") Tj ET\n";
        $contentStream .= "BT /F1 22 Tf " . $centerX($rank, 22) . " 260 Td (" . $rank . ") Tj ET\n";
        $contentStream .= "BT /F2 10 Tf " . $centerX($verify, 10) . " 140 Td (" . $verify . ") Tj ET\n";
        // REVOKED is drawn in red so the status is unmistakeable at a glance.
        $statusIsRevoked = $status === \App\Entity\Certificate::STATUS_REVOKED;
        $statusColor = $statusIsRevoked ? "0.8 0.0 0.0 rg\n" : "0 0 0 rg\n";
        $contentStream .= $statusColor;
        $contentStream .= "BT /F1 12 Tf " . $centerX($statusLabel, 12) . " 118 Td (" . $statusLabel . ") Tj ET\n";
        $contentStream .= "0 0 0 rg\n";
        $contentStream .= "BT /F2 10 Tf " . $centerX($issued, 10) . " 96 Td (" . $issued . ") Tj ET\n";
        $contentStream .= "Q\n";

        // 7 indirect objects + 1 stream length object
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Contents 4 0 R "
            . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>";
        $objects[4] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";

        // Assemble PDF
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $count = count($objects) + 1; // +1 for object 0
        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($objects as $num => $_body) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
        return $pdf;
    }

    /**
     * Escape a string for inclusion inside a PDF literal string `(...)`.
     * PDF literal strings must escape `\`, `(`, `)`, and newlines. Non-ASCII
     * characters are transliterated to approximate ASCII for WinAnsi fonts.
     */
    private static function escape(string $s): string
    {
        $s = iconv('UTF-8', 'Windows-1252//TRANSLIT', $s) ?: $s;
        return strtr($s, [
            '\\' => '\\\\',
            '(' => '\\(',
            ')' => '\\)',
            "\r" => '\\r',
            "\n" => '\\n',
        ]);
    }
}
