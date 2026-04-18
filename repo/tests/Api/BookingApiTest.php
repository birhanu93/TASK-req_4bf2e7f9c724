<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class BookingApiTest extends ApiTestCase
{
    private function makeSession(string $supToken, string $offset = '+2 days', int $capacity = 2): string
    {
        $start = $this->kernel->clock->now()->modify($offset);
        $r = $this->call('POST', '/api/sessions', [
            'title' => 'S',
            'startsAt' => $start->format(DATE_ATOM),
            'endsAt' => $start->modify('+1 hour')->format(DATE_ATOM),
            'capacity' => $capacity,
        ], $supToken);
        return $r->getBody()['id'];
    }

    public function testBookConfirmCancel(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $sid = $this->makeSession($supToken);

        $book = $this->call('POST', '/api/bookings', ['sessionId' => $sid], $traineeToken);
        self::assertSame(201, $book->getStatus());
        $bid = $book->getBody()['id'];

        $confirm = $this->call('POST', "/api/bookings/{$bid}/confirm", [], $traineeToken);
        self::assertSame(200, $confirm->getStatus());
        self::assertSame('confirmed', $confirm->getBody()['status']);

        $cancel = $this->call('POST', "/api/bookings/{$bid}/cancel", ['reason' => 'family event'], $traineeToken);
        self::assertSame(200, $cancel->getStatus());
        self::assertSame('cancelled', $cancel->getBody()['status']);
    }

    public function testCancelAdminOverride(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $adminToken = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $sid = $this->makeSession($supToken, '+2 hours');

        $book = $this->call('POST', '/api/bookings', ['sessionId' => $sid], $traineeToken);
        $bid = $book->getBody()['id'];

        $blocked = $this->call('POST', "/api/bookings/{$bid}/cancel", ['reason' => 'change'], $traineeToken);
        self::assertSame(409, $blocked->getStatus());

        $override = $this->call('POST', "/api/bookings/{$bid}/cancel", ['reason' => 'emergency', 'override' => true], $adminToken);
        self::assertSame(200, $override->getStatus());
        self::assertNotNull($override->getBody()['overrideActorId']);
    }

    public function testReschedule(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $s1 = $this->makeSession($supToken, '+2 days');
        $s2 = $this->makeSession($supToken, '+3 days');
        $book = $this->call('POST', '/api/bookings', ['sessionId' => $s1], $traineeToken);
        $bid = $book->getBody()['id'];
        $r = $this->call('POST', "/api/bookings/{$bid}/reschedule", ['newSessionId' => $s2], $traineeToken);
        self::assertSame(201, $r->getStatus());
    }

    public function testCreateRequiresAuth(): void
    {
        $r = $this->call('POST', '/api/bookings', ['sessionId' => 'x']);
        self::assertSame(401, $r->getStatus());
    }

    public function testCreateRequiresRole(): void
    {
        $sup = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $r = $this->call('POST', '/api/bookings', ['sessionId' => 'x'], $sup);
        self::assertSame(403, $r->getStatus());
    }

    public function testCancelUnauthorized(): void
    {
        $r = $this->call('POST', '/api/bookings/x/cancel', ['reason' => 'r']);
        self::assertSame(401, $r->getStatus());
    }

    public function testConfirmUnauthorized(): void
    {
        $r = $this->call('POST', '/api/bookings/x/confirm');
        self::assertSame(401, $r->getStatus());
    }

    public function testRescheduleUnauthorized(): void
    {
        $r = $this->call('POST', '/api/bookings/x/reschedule', ['newSessionId' => 'y']);
        self::assertSame(401, $r->getStatus());
    }

    public function testNonAdminOverrideForbidden(): void
    {
        $traineeToken = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $r = $this->call('POST', '/api/bookings/x/cancel', ['reason' => 'r', 'override' => true], $traineeToken);
        self::assertSame(403, $r->getStatus());
    }
}
