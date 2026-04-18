<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Employers can verify a certificate by code but must never be able to
 * download the actual PDF artifact. Verify + download authorization is
 * enforced by AuthorizationService::assertCertificateAccess.
 */
final class EmployerCertificateAccessTest extends ApiTestCase
{
    public function testEmployerCanVerifyButNotDownload(): void
    {
        $adminToken = $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('emp', 'pw-12345', [Roles::EMPLOYER]);
        $empToken = $this->loginAs('emp', 'pw-12345', Roles::EMPLOYER);

        // Admin issues a rank + certificate for alice.
        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $rank = $this->kernel->assessmentService->createRank('Bronze', 1, 1, 1);
        $cert = $this->kernel->certService->issue($aliceId, $rank->getId(), 'admin');

        // Verify-by-code is allowed.
        $verify = $this->call('GET', '/api/certificates/verify/' . $cert->getVerificationCode(), [], $empToken);
        self::assertSame(200, $verify->getStatus());
        self::assertTrue($verify->getBody()['valid']);

        // Download is forbidden for employers.
        $download = $this->call('GET', '/api/certificates/' . $cert->getId() . '/download', [], $empToken);
        self::assertSame(403, $download->getStatus());
    }

    public function testTraineeCanDownloadOwnCertificate(): void
    {
        $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $aliceToken = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);

        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $rank = $this->kernel->assessmentService->createRank('Bronze', 1, 1, 1);
        $cert = $this->kernel->certService->issue($aliceId, $rank->getId(), 'admin');

        $download = $this->call('GET', '/api/certificates/' . $cert->getId() . '/download', [], $aliceToken);
        self::assertSame(200, $download->getStatus());
        self::assertNotEmpty($download->getBody()['pdf']);
    }

    public function testTraineeCannotDownloadOtherTraineeCertificate(): void
    {
        $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);
        $bobToken = $this->loginAs('bob', 'pw-12345', Roles::TRAINEE);

        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $rank = $this->kernel->assessmentService->createRank('Bronze', 1, 1, 1);
        $cert = $this->kernel->certService->issue($aliceId, $rank->getId(), 'admin');

        $download = $this->call('GET', '/api/certificates/' . $cert->getId() . '/download', [], $bobToken);
        self::assertSame(403, $download->getStatus());
    }
}
