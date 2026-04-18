<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class AdminApiTest extends ApiTestCase
{
    public function testAuditHistoryAndTiering(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);

        // Produce a booking to generate audit log
        $sup = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $start = $this->kernel->clock->now()->modify('+2 days');
        $session = $this->call('POST', '/api/sessions', [
            'title' => 'S',
            'startsAt' => $start->format(DATE_ATOM),
            'endsAt' => $start->modify('+1 hour')->format(DATE_ATOM),
            'capacity' => 2,
        ], $sup);
        $sid = $session->getBody()['id'];
        $book = $this->call('POST', '/api/bookings', ['sessionId' => $sid], $trainee);
        $bid = $book->getBody()['id'];

        $audit = $this->call('GET', "/api/admin/audit/booking/{$bid}", [], $admin);
        self::assertSame(200, $audit->getStatus());
        self::assertNotEmpty($audit->getBody()['logs']);

        $tier = $this->call('POST', '/api/admin/storage/tier', [], $admin);
        self::assertSame(200, $tier->getStatus());
        self::assertArrayHasKey('movedCount', $tier->getBody());
    }

    public function testUnauthenticatedAndForbidden(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        self::assertSame(401, $this->call('GET', '/api/admin/audit/booking/x')->getStatus());
        self::assertSame(403, $this->call('GET', '/api/admin/audit/booking/x', [], $trainee)->getStatus());
        self::assertSame(401, $this->call('POST', '/api/admin/storage/tier')->getStatus());
        self::assertSame(403, $this->call('POST', '/api/admin/storage/tier', [], $trainee)->getStatus());
    }
}
