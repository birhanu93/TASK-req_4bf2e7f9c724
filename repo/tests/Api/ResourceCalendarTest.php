<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * End-to-end coverage of the resource calendar: admins mint and retire
 * resources, supervisors reserve them alongside sessions, and the scheduler
 * rejects overlapping claims on the same resource window even across
 * different supervisors.
 */
final class ResourceCalendarTest extends ApiTestCase
{
    public function testOnlyAdminCanCreateResources(): void
    {
        $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $res = $this->call('POST', '/api/resources', ['name' => 'Room A', 'kind' => 'room'], $sup);
        self::assertSame(403, $res->getStatus());

        $unauth = $this->call('POST', '/api/resources', ['name' => 'Room A', 'kind' => 'room']);
        self::assertSame(401, $unauth->getStatus());
    }

    public function testResourceConflictBlocksSecondSupervisor(): void
    {
        $admin = $this->seedAdmin();
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $r = $this->call('POST', '/api/resources', ['name' => 'Room A', 'kind' => 'room'], $admin);
        self::assertSame(201, $r->getStatus());
        $resourceId = $r->getBody()['id'];

        $first = $this->call('POST', '/api/sessions', [
            'title' => 'Morning',
            'startsAt' => '2026-06-01T09:00:00+00:00',
            'endsAt' => '2026-06-01T10:00:00+00:00',
            'capacity' => 2,
            'resourceIds' => [$resourceId],
        ], $sup1);
        self::assertSame(201, $first->getStatus());

        // sup2 tries to book the same resource in an overlapping window.
        $second = $this->call('POST', '/api/sessions', [
            'title' => 'Same slot',
            'startsAt' => '2026-06-01T09:30:00+00:00',
            'endsAt' => '2026-06-01T10:30:00+00:00',
            'capacity' => 2,
            'resourceIds' => [$resourceId],
        ], $sup2);
        self::assertSame(409, $second->getStatus());
        self::assertStringContainsString('Room A', (string) $second->getBody()['error']);
    }

    public function testNonOverlappingResourceBookingsAreAllowed(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $resId = $this->call('POST', '/api/resources', ['name' => 'Room B', 'kind' => 'room'], $admin)->getBody()['id'];

        $a = $this->call('POST', '/api/sessions', [
            'title' => 'Morning',
            'startsAt' => '2026-06-02T09:00:00+00:00',
            'endsAt' => '2026-06-02T10:00:00+00:00',
            'capacity' => 2,
            'resourceIds' => [$resId],
        ], $sup);
        self::assertSame(201, $a->getStatus());

        $b = $this->call('POST', '/api/sessions', [
            'title' => 'Afternoon',
            'startsAt' => '2026-06-02T14:00:00+00:00',
            'endsAt' => '2026-06-02T15:00:00+00:00',
            'capacity' => 2,
            'resourceIds' => [$resId],
        ], $sup);
        self::assertSame(201, $b->getStatus());
    }

    public function testRetiredResourceCannotBeReserved(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $resId = $this->call('POST', '/api/resources', ['name' => 'Old Van', 'kind' => 'vehicle'], $admin)->getBody()['id'];
        $retire = $this->call('POST', "/api/resources/{$resId}/retire", [], $admin);
        self::assertSame(200, $retire->getStatus());

        $reserve = $this->call('POST', '/api/sessions', [
            'title' => 'Field trip',
            'startsAt' => '2026-06-03T09:00:00+00:00',
            'endsAt' => '2026-06-03T10:00:00+00:00',
            'capacity' => 2,
            'resourceIds' => [$resId],
        ], $sup);
        self::assertSame(409, $reserve->getStatus());
    }

    public function testMissingResourceIdGives404InSessionCreate(): void
    {
        $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);

        $res = $this->call('POST', '/api/sessions', [
            'title' => 'Missing',
            'startsAt' => '2026-06-04T09:00:00+00:00',
            'endsAt' => '2026-06-04T10:00:00+00:00',
            'capacity' => 2,
            'resourceIds' => ['no-such-resource'],
        ], $sup);
        self::assertSame(404, $res->getStatus());
    }

    public function testResourceListIsVisibleToAnyAuthenticatedUser(): void
    {
        $admin = $this->seedAdmin();
        $this->call('POST', '/api/resources', ['name' => 'Public Room', 'kind' => 'room'], $admin);

        $supToken = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $listed = $this->call('GET', '/api/resources', [], $supToken);
        self::assertSame(200, $listed->getStatus());
        self::assertCount(1, $listed->getBody()['resources']);

        // Unauthenticated should still be rejected.
        self::assertSame(401, $this->call('GET', '/api/resources')->getStatus());
    }

    public function testDuplicateResourceNameIsRejected(): void
    {
        $admin = $this->seedAdmin();
        $this->call('POST', '/api/resources', ['name' => 'Dup', 'kind' => 'room'], $admin);
        $res = $this->call('POST', '/api/resources', ['name' => 'Dup', 'kind' => 'room'], $admin);
        self::assertSame(409, $res->getStatus());
    }
}
