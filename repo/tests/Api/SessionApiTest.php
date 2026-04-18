<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class SessionApiTest extends ApiTestCase
{
    public function testSupervisorCreatesSessionAndTraineeBooks(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);

        $start = $this->kernel->clock->now()->modify('+2 days');
        $end = $start->modify('+1 hour');
        $create = $this->call('POST', '/api/sessions', [
            'title' => 'Strength',
            'startsAt' => $start->format(DATE_ATOM),
            'endsAt' => $end->format(DATE_ATOM),
            'capacity' => 3,
        ], $supToken);
        self::assertSame(201, $create->getStatus());
        $sessionId = $create->getBody()['id'];

        $list = $this->call('GET', '/api/sessions', [], $traineeToken, ['from' => $this->kernel->clock->now()->format(DATE_ATOM)]);
        self::assertSame(200, $list->getStatus());
        self::assertCount(1, $list->getBody()['sessions']);

        $avail = $this->call('GET', "/api/sessions/{$sessionId}/availability", [], $traineeToken);
        self::assertSame(3, $avail->getBody()['availability']);

        $close = $this->call('POST', "/api/sessions/{$sessionId}/close", [], $supToken);
        self::assertSame(204, $close->getStatus());
    }

    public function testSessionCreateRbac(): void
    {
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $r = $this->call('POST', '/api/sessions', [
            'title' => 'X',
            'startsAt' => '2026-05-01T10:00:00+00:00',
            'endsAt' => '2026-05-01T11:00:00+00:00',
            'capacity' => 5,
        ], $traineeToken);
        self::assertSame(403, $r->getStatus());
    }

    public function testListRequiresAuth(): void
    {
        $r = $this->call('GET', '/api/sessions');
        self::assertSame(401, $r->getStatus());
    }

    public function testListDefaultFrom(): void
    {
        $tok = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $r = $this->call('GET', '/api/sessions', [], $tok);
        self::assertSame(200, $r->getStatus());
    }

    public function testAvailabilityRequiresAuth(): void
    {
        $r = $this->call('GET', '/api/sessions/x/availability');
        self::assertSame(401, $r->getStatus());
    }

    public function testCloseRequiresAuth(): void
    {
        $r = $this->call('POST', '/api/sessions/x/close');
        self::assertSame(401, $r->getStatus());
    }

    public function testCreateRequiresAuth(): void
    {
        $r = $this->call('POST', '/api/sessions', ['capacity' => 1]);
        self::assertSame(401, $r->getStatus());
    }

    public function testSessionCreateValidation(): void
    {
        $tok = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $r = $this->call('POST', '/api/sessions', [
            'title' => 'X',
            'startsAt' => '2026-05-01T10:00:00+00:00',
            'endsAt' => '2026-05-01T09:00:00+00:00',
            'capacity' => 5,
        ], $tok);
        self::assertSame(422, $r->getStatus());
    }
}
