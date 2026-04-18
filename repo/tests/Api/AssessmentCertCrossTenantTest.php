<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\AssessmentTemplate;
use App\Service\Roles;

/**
 * Object-level authorisation for writes. A supervisor must not be able to
 * record an assessment against, or issue a certificate to, a trainee they
 * have never worked with. Admins bypass. Wrong roles and unauthenticated
 * requests are also covered here so the 401/403/404/409 quadrant is
 * locked down on these endpoints.
 */
final class AssessmentCertCrossTenantTest extends ApiTestCase
{
    public function testSupervisorCannotRecordAssessmentForUnknownTrainee(): void
    {
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('t1', 'pw-12345', [Roles::TRAINEE]);
        $t1 = $this->loginAs('t1', 'pw-12345', Roles::TRAINEE);
        $t1Id = $this->kernel->users->findByUsername('t1')->getId();

        // sup1 owns a session that t1 books into.
        $session = $this->call('POST', '/api/sessions', [
            'title' => 'session',
            'startsAt' => '2026-05-01T09:00:00+00:00',
            'endsAt' => '2026-05-01T10:00:00+00:00',
            'capacity' => 2,
        ], $sup1);
        self::assertSame(201, $session->getStatus());
        $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $t1);

        // Shared template.
        $tpl = $this->call('POST', '/api/assessments/templates', [
            'name' => 'Push',
            'mode' => AssessmentTemplate::MODE_REP,
            'targetReps' => 10,
        ], $sup2);

        // sup2 tries to record an assessment against t1 — they have no
        // session with that trainee and must be rejected.
        $res = $this->call('POST', '/api/assessments', [
            'templateId' => $tpl->getBody()['id'],
            'traineeId' => $t1Id,
            'reps' => 12,
            'seconds' => 0,
        ], $sup2);
        self::assertSame(403, $res->getStatus());
    }

    public function testSupervisorCannotIssueCertificateForUnknownTrainee(): void
    {
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('t1', 'pw-12345', [Roles::TRAINEE]);
        $t1 = $this->loginAs('t1', 'pw-12345', Roles::TRAINEE);
        $t1Id = $this->kernel->users->findByUsername('t1')->getId();

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'session',
            'startsAt' => '2026-05-02T09:00:00+00:00',
            'endsAt' => '2026-05-02T10:00:00+00:00',
            'capacity' => 2,
        ], $sup1);
        $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $t1);

        $rank = $this->kernel->assessmentService->createRank('Bronze', 1, 0, 1);

        $res = $this->call('POST', '/api/certificates', [
            'traineeId' => $t1Id,
            'rankId' => $rank->getId(),
        ], $sup2);
        self::assertSame(403, $res->getStatus());
    }

    public function testSupervisorCanIssueCertificateForOwnTrainee(): void
    {
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('t1', 'pw-12345', [Roles::TRAINEE]);
        $t1 = $this->loginAs('t1', 'pw-12345', Roles::TRAINEE);
        $t1Id = $this->kernel->users->findByUsername('t1')->getId();

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'session',
            'startsAt' => '2026-05-03T09:00:00+00:00',
            'endsAt' => '2026-05-03T10:00:00+00:00',
            'capacity' => 2,
        ], $sup);
        $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $t1);

        $rank = $this->kernel->assessmentService->createRank('Bronze', 1, 0, 1);

        $res = $this->call('POST', '/api/certificates', [
            'traineeId' => $t1Id,
            'rankId' => $rank->getId(),
        ], $sup);
        self::assertSame(201, $res->getStatus());
    }

    public function testTraineeCannotRecordAssessmentAgainstAnyone(): void
    {
        $this->seedAdmin();
        $t = $this->seedUser('t', 'pw-12345', [Roles::TRAINEE], Roles::TRAINEE);
        $this->createUser('other', 'pw-12345', [Roles::TRAINEE]);
        $otherId = $this->kernel->users->findByUsername('other')->getId();

        $res = $this->call('POST', '/api/assessments', [
            'templateId' => 'any',
            'traineeId' => $otherId,
            'reps' => 1,
            'seconds' => 0,
        ], $t);
        self::assertSame(403, $res->getStatus());
    }

    public function testUnauthenticatedAssessmentAndCertWrite(): void
    {
        self::assertSame(401, $this->call('POST', '/api/assessments', ['traineeId' => 'x', 'reps' => 1])->getStatus());
        self::assertSame(401, $this->call('POST', '/api/certificates', ['traineeId' => 'x', 'rankId' => 'y'])->getStatus());
    }
}
