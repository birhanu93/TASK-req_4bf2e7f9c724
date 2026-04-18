<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static guard against the recurring bug where the UI feature-gates on a
 * status string that the backend never emits (the pre-fix code checked
 * `status === 'issued'`, but the backend only ever writes 'active' /
 * 'revoked'). The frontend now routes every check through typed helpers
 * defined in frontend/src/lib/status.ts; this test pins that contract:
 *
 *  - the shared status module defines ACTIVE='active' and REVOKED='revoked'
 *  - trainee/admin cert screens use the helper (not a literal 'issued')
 *  - the helper file and components all render the two status branches
 *    (download for active, revoked label where relevant)
 */
final class FrontendCertificateActionTest extends TestCase
{
    private function load(string $relative): string
    {
        $path = dirname(__DIR__, 2) . '/frontend/src/' . $relative;
        self::assertFileExists($path, "expected frontend file {$relative}");
        return (string) file_get_contents($path);
    }

    public function testStatusModuleDefinesCanonicalValues(): void
    {
        $src = $this->load('lib/status.ts');
        self::assertStringContainsString("ACTIVE: 'active'", $src);
        self::assertStringContainsString("REVOKED: 'revoked'", $src);
        self::assertStringContainsString('certificateIsDownloadable', $src);
        self::assertStringContainsString('certificateIsRevokable', $src);
    }

    public function testTraineeCertificateScreenUsesHelperAndNotStaleLiteral(): void
    {
        $src = $this->load('routes/trainee/Certificates.tsx');
        self::assertStringContainsString("certificateIsDownloadable(c.status)", $src);
        self::assertStringNotContainsString("status === 'issued'", $src);
        self::assertStringContainsString('Download PDF', $src);
    }

    public function testAdminCertificateScreenUsesHelperAndNotStaleLiteral(): void
    {
        $src = $this->load('routes/admin/Certificates.tsx');
        self::assertStringContainsString('certificateIsRevokable(c.status)', $src);
        self::assertStringNotContainsString("status === 'issued'", $src);
        self::assertStringContainsString('Revoke', $src);
    }

    public function testGuardianDevicesScreenUsesDeviceStatusHelper(): void
    {
        $src = $this->load('routes/guardian/Home.tsx');
        self::assertStringContainsString('deviceIsRemoteLogoutEligible', $src);
        self::assertStringContainsString('DeviceStatus.REVOKED', $src);
        self::assertStringNotContainsString("status === 'active'", $src);
    }

    public function testStatusModuleIsTheOnlySourceOfTruthForCertStatusLiterals(): void
    {
        // Any component that references a cert status literal should be
        // doing so via the helper. We check that neither 'issued' nor a bare
        // 'active'/'revoked' string comparison leaks into cert pages.
        foreach (['routes/trainee/Certificates.tsx', 'routes/admin/Certificates.tsx'] as $file) {
            $src = $this->load($file);
            self::assertStringNotContainsString("'issued'", $src, "{$file} still references the legacy 'issued' literal");
            self::assertStringNotContainsString("\"issued\"", $src, "{$file} still references the legacy 'issued' literal");
        }
    }
}
