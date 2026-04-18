<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Admin booking management: list + policy override with required reason and
 * audit row. Non-admins must not reach the admin list. Override without a
 * reason must fail. Every override writes an auditable 'booking.cancel.override'
 * row carrying the caller id and the reason text.
 */
final class AdminBookingOverrideTest extends ApiTestCase
{
    public function testAdminCanListAllBookings(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->createUser('bob', 'pw-12345', [Roles::TRAINEE]);
        $alice = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);
        $bob = $this->loginAs('bob', 'pw-12345', Roles::TRAINEE);

        $s = $this->call('POST', '/api/sessions', [
            'title' => 'Morning',
            'startsAt' => '2026-06-01T09:00:00+00:00',
            'endsAt' => '2026-06-01T10:00:00+00:00',
            'capacity' => 5,
        ], $sup);
        $sessionId = $s->getBody()['id'];
        $this->call('POST', '/api/bookings', ['sessionId' => $sessionId], $alice);
        $this->call('POST', '/api/bookings', ['sessionId' => $sessionId], $bob);

        $all = $this->call('GET', '/api/admin/bookings', [], $admin);
        self::assertSame(200, $all->getStatus());
        self::assertCount(2, $all->getBody()['bookings']);

        // Filter by trainee.
        $aliceId = $this->kernel->users->findByUsername('alice')->getId();
        $filtered = $this->call('GET', '/api/admin/bookings', [], $admin, ['traineeId' => $aliceId]);
        self::assertCount(1, $filtered->getBody()['bookings']);
        self::assertSame($aliceId, $filtered->getBody()['bookings'][0]['traineeId']);
    }

    public function testNonAdminCannotReachAdminList(): void
    {
        $this->seedAdmin();
        $supToken = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $res = $this->call('GET', '/api/admin/bookings', [], $supToken);
        self::assertSame(403, $res->getStatus());
        self::assertSame(401, $this->call('GET', '/api/admin/bookings')->getStatus());
    }

    public function testAdminOverrideCancelRequiresReasonAndIsAudited(): void
    {
        $admin = $this->seedAdmin();
        $adminId = $this->kernel->users->findByUsername('admin')->getId();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $alice = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);

        $s = $this->call('POST', '/api/sessions', [
            'title' => 'Soon',
            // Near start so we're inside the 12h block window relative to the
            // fixed test clock (2026-04-18T10:00).
            'startsAt' => '2026-04-18T14:00:00+00:00',
            'endsAt' => '2026-04-18T15:00:00+00:00',
            'capacity' => 2,
        ], $sup);
        $sessionId = $s->getBody()['id'];
        $booking = $this->call('POST', '/api/bookings', ['sessionId' => $sessionId], $alice);
        $bid = $booking->getBody()['id'];

        // Missing reason must fail validation.
        $missing = $this->call('POST', "/api/bookings/{$bid}/cancel", ['override' => true], $admin);
        self::assertSame(422, $missing->getStatus());

        // With reason, the override succeeds.
        $ok = $this->call('POST', "/api/bookings/{$bid}/cancel", [
            'override' => true,
            'reason' => 'Weather event — entire session cancelled',
        ], $admin);
        self::assertSame(200, $ok->getStatus());
        self::assertSame('cancelled', $ok->getBody()['status']);

        // Audit row captures the override.
        $hit = array_values(array_filter(
            $this->kernel->auditLogs->findAll(),
            fn ($l) => $l->getAction() === 'booking.cancel.override' && $l->getEntityId() === $bid,
        ));
        self::assertCount(1, $hit);
        self::assertSame($adminId, $hit[0]->getActorId());
        self::assertSame('Weather event — entire session cancelled', $hit[0]->getAfter()['reason']);
    }

    public function testAdminRescheduleOverrideRequiresReason(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $alice = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);

        $a = $this->call('POST', '/api/sessions', [
            'title' => 'A',
            'startsAt' => '2026-04-18T14:00:00+00:00',
            'endsAt' => '2026-04-18T15:00:00+00:00',
            'capacity' => 2,
        ], $sup);
        $b = $this->call('POST', '/api/sessions', [
            'title' => 'B',
            'startsAt' => '2026-04-25T14:00:00+00:00',
            'endsAt' => '2026-04-25T15:00:00+00:00',
            'capacity' => 2,
        ], $sup);
        $booking = $this->call('POST', '/api/bookings', ['sessionId' => $a->getBody()['id']], $alice);
        $bid = $booking->getBody()['id'];

        // No reason: rejected.
        $missing = $this->call('POST', "/api/bookings/{$bid}/reschedule", [
            'newSessionId' => $b->getBody()['id'],
            'override' => true,
            'idempotencyKey' => 'k1',
        ], $admin);
        self::assertSame(422, $missing->getStatus());

        // With reason: succeeds and audited with override metadata.
        $ok = $this->call('POST', "/api/bookings/{$bid}/reschedule", [
            'newSessionId' => $b->getBody()['id'],
            'override' => true,
            'idempotencyKey' => 'k2',
            'reason' => 'Trainee flight delayed',
        ], $admin);
        self::assertSame(201, $ok->getStatus());

        $overrides = array_values(array_filter(
            $this->kernel->auditLogs->findAll(),
            fn ($l) => $l->getAction() === 'booking.cancel.override' && $l->getEntityId() === $bid,
        ));
        self::assertCount(1, $overrides);
        self::assertSame('Trainee flight delayed', $overrides[0]->getAfter()['reason']);
    }
}
