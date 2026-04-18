<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\AssessmentTemplate;
use App\Service\Roles;

final class AssessmentApiTest extends ApiTestCase
{
    public function testFlow(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $trainToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $traineeId = $this->kernel->users->findByUsername('t1')->getId();

        // The supervisor must have an existing relationship with the trainee
        // (at least one booking in a session they own) before they can record
        // an assessment — mirror the real-world sequence here.
        $session = $this->call('POST', '/api/sessions', [
            'title' => 'Push session',
            'startsAt' => '2026-05-01T09:00:00+00:00',
            'endsAt' => '2026-05-01T10:00:00+00:00',
            'capacity' => 2,
        ], $supToken);
        self::assertSame(201, $session->getStatus());
        $book = $this->call('POST', '/api/bookings', [
            'sessionId' => $session->getBody()['id'],
        ], $trainToken);
        self::assertSame(201, $book->getStatus());

        $tpl = $this->call('POST', '/api/assessments/templates', [
            'name' => 'Push',
            'mode' => AssessmentTemplate::MODE_REP,
            'targetReps' => 10,
        ], $supToken);
        self::assertSame(201, $tpl->getStatus());

        $rank = $this->call('POST', '/api/assessments/ranks', [
            'name' => 'Bronze',
            'minReps' => 5,
            'minSeconds' => 0,
            'order' => 1,
        ], $supToken);
        self::assertSame(201, $rank->getStatus());

        $record = $this->call('POST', '/api/assessments', [
            'templateId' => $tpl->getBody()['id'],
            'traineeId' => $traineeId,
            'reps' => 12,
            'seconds' => 0,
        ], $supToken);
        self::assertSame(201, $record->getStatus());

        $progress = $this->call('GET', "/api/assessments/progress/{$traineeId}", [], $trainToken);
        self::assertSame(200, $progress->getStatus());
        self::assertSame(12, $progress->getBody()['reps']);
    }

    public function testUnauthenticated(): void
    {
        self::assertSame(401, $this->call('POST', '/api/assessments/templates')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/assessments/ranks')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/assessments')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/assessments/progress/u1')->getStatus());
    }

    public function testForbidden(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        self::assertSame(403, $this->call('POST', '/api/assessments/templates', [
            'name' => 'X', 'mode' => AssessmentTemplate::MODE_REP, 'targetReps' => 5,
        ], $t)->getStatus());
    }
}
