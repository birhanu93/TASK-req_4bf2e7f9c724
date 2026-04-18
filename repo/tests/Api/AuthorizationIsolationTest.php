<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Cross-user authorization and tenant/isolation rules. Each test asserts a
 * specific "access by another actor is denied" case.
 */
final class AuthorizationIsolationTest extends ApiTestCase
{
    public function testTraineeCannotSeeAnotherTraineesBooking(): void
    {
        $this->seedAdmin();
        $supToken = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $session = $this->createSession($supToken, '2026-05-01T09:00:00+00:00', '2026-05-01T10:00:00+00:00', 2);

        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);

        $aliceToken = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);
        $bobToken = $this->loginAs('bob', 'pw-12345', Roles::TRAINEE);

        $aliceBooking = $this->call('POST', '/api/bookings', ['sessionId' => $session['id']], $aliceToken);
        self::assertSame(201, $aliceBooking->getStatus());

        $bobRead = $this->call('GET', '/api/bookings/' . $aliceBooking->getBody()['id'], [], $bobToken);
        self::assertSame(403, $bobRead->getStatus());

        $bobCancel = $this->call('POST', '/api/bookings/' . $aliceBooking->getBody()['id'] . '/cancel', [
            'reason' => 'nope',
        ], $bobToken);
        self::assertSame(403, $bobCancel->getStatus());
    }

    public function testTraineeCannotReadAnotherTraineesAssessmentProgress(): void
    {
        $adminToken = $this->seedAdmin();
        $this->createUser('sup', 'pw-12345', [Roles::SUPERVISOR]);
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);

        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $bobToken = $this->loginAs('bob', 'pw-12345', Roles::TRAINEE);

        $r = $this->call('GET', '/api/assessments/progress/' . $aliceId, [], $bobToken);
        self::assertSame(403, $r->getStatus());
    }

    public function testGuardianCanOnlySeeLinkedChildrenProgress(): void
    {
        $adminToken = $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('guard', 'pw-12345', [Roles::GUARDIAN]);

        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $bobId = $this->kernel->users->findByUsername('bob')->getId();
        $guardId = $this->kernel->users->findByUsername('guard')->getId();

        // Admin links guard -> alice only.
        $linkRes = $this->call('POST', '/api/guardians/links', [
            'guardianId' => $guardId,
            'childId' => $aliceId,
        ], $adminToken);
        self::assertSame(201, $linkRes->getStatus());

        $guardToken = $this->loginAs('guard', 'pw-12345', Roles::GUARDIAN);
        $aliceView = $this->call('GET', '/api/guardians/children/' . $aliceId . '/progress', [], $guardToken);
        self::assertSame(200, $aliceView->getStatus());

        $bobView = $this->call('GET', '/api/guardians/children/' . $bobId . '/progress', [], $guardToken);
        self::assertSame(404, $bobView->getStatus());
    }

    public function testSupervisorCannotCloseAnotherSupervisorsSession(): void
    {
        $adminToken = $this->seedAdmin();
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $session = $this->createSession($sup1, '2026-05-01T09:00:00+00:00', '2026-05-01T10:00:00+00:00', 2);

        $close = $this->call('POST', '/api/sessions/' . $session['id'] . '/close', [], $sup2);
        self::assertSame(403, $close->getStatus());
    }

    public function testNonAdminCannotAccessAuditHistory(): void
    {
        $this->seedAdmin();
        $token = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $r = $this->call('GET', '/api/admin/audit/user/any', [], $token);
        self::assertSame(403, $r->getStatus());
    }

    public function testNonAdminCannotTriggerSnapshot(): void
    {
        $this->seedAdmin();
        $token = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $r = $this->call('POST', '/api/admin/snapshots', [], $token);
        self::assertSame(403, $r->getStatus());
    }

    public function testNonAdminCannotRotateKey(): void
    {
        $this->seedAdmin();
        $token = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $r = $this->call('POST', '/api/admin/keys/rotate', [], $token);
        self::assertSame(403, $r->getStatus());
    }

    public function testProfileAccessIsolation(): void
    {
        $this->seedAdmin();
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);
        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $bobToken = $this->loginAs('bob', 'pw-12345', Roles::TRAINEE);
        $r = $this->call('GET', '/api/profile', [], $bobToken, ['userId' => $aliceId]);
        self::assertSame(403, $r->getStatus());
    }

    /**
     * @return array<string,mixed>
     */
    private function createSession(string $supToken, string $startsAt, string $endsAt, int $capacity): array
    {
        $r = $this->call('POST', '/api/sessions', [
            'title' => 'Session',
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'capacity' => $capacity,
        ], $supToken);
        self::assertSame(201, $r->getStatus(), (string) json_encode($r->getBody()));
        return $r->getBody();
    }
}
