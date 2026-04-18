<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class CertificateApiTest extends ApiTestCase
{
    public function testIssueVerifyDownloadRevoke(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $rank = $this->kernel->assessmentService->createRank('Bronze', 5, 0, 1);
        $traineeId = $this->kernel->users->findByUsername('t1')->getId();

        $issue = $this->call('POST', '/api/certificates', [
            'traineeId' => $traineeId,
            'rankId' => $rank->getId(),
        ], $admin);
        self::assertSame(201, $issue->getStatus());
        $cid = $issue->getBody()['id'];
        $code = $issue->getBody()['verificationCode'];

        $verify = $this->call('GET', "/api/certificates/verify/{$code}", [], $trainee);
        self::assertSame(200, $verify->getStatus());
        self::assertTrue($verify->getBody()['valid']);

        $download = $this->call('GET', "/api/certificates/{$cid}/download", [], $trainee);
        self::assertSame(200, $download->getStatus());
        $pdf = base64_decode($download->getBody()['pdf']);
        self::assertStringContainsString('PDF', $pdf);

        $revoke = $this->call('POST', "/api/certificates/{$cid}/revoke", [], $admin);
        self::assertSame(200, $revoke->getStatus());
    }

    public function testUnauthenticated(): void
    {
        self::assertSame(401, $this->call('POST', '/api/certificates')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/certificates/verify/X')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/certificates/x/revoke')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/certificates/x/download')->getStatus());
    }
}
