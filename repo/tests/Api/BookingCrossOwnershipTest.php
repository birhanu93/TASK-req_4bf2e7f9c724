<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Verifies that supervisors cannot act on bookings belonging to another
 * supervisor's session. Complements AuthorizationIsolationTest which
 * covered trainee-to-trainee leaks.
 */
final class BookingCrossOwnershipTest extends ApiTestCase
{
    public function testSupervisorCannotViewBookingOnAnotherSupervisorsSession(): void
    {
        $this->seedAdmin();
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('trainee', 'pw-12345', [Roles::TRAINEE]);
        $traineeToken = $this->loginAs('trainee', 'pw-12345', Roles::TRAINEE);

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'Pulls',
            'startsAt' => '2026-05-01T09:00:00+00:00',
            'endsAt' => '2026-05-01T10:00:00+00:00',
            'capacity' => 2,
        ], $sup1);
        self::assertSame(201, $session->getStatus());

        $booking = $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $traineeToken);
        self::assertSame(201, $booking->getStatus());
        $bid = $booking->getBody()['id'];

        // sup2 does not own this session -> 403.
        $r = $this->call('GET', '/api/bookings/' . $bid, [], $sup2);
        self::assertSame(403, $r->getStatus());
    }

    public function testSupervisorCannotCancelBookingOnAnotherSupervisorsSession(): void
    {
        $this->seedAdmin();
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('trainee', 'pw-12345', [Roles::TRAINEE]);
        $traineeToken = $this->loginAs('trainee', 'pw-12345', Roles::TRAINEE);

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'Pulls',
            'startsAt' => '2026-06-01T09:00:00+00:00',
            'endsAt' => '2026-06-01T10:00:00+00:00',
            'capacity' => 2,
        ], $sup1);
        $booking = $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $traineeToken);
        $bid = $booking->getBody()['id'];

        $r = $this->call('POST', '/api/bookings/' . $bid . '/cancel', ['reason' => 'no'], $sup2);
        self::assertSame(403, $r->getStatus());
    }

    public function testSupervisorListBookingsHidesOtherSupervisorsBookings(): void
    {
        $this->seedAdmin();
        $sup1 = $this->seedUser('sup1', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $sup2 = $this->seedUser('sup2', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->createUser('trainee', 'pw-12345', [Roles::TRAINEE]);
        $traineeId = $this->kernel->users->findByUsername('trainee')->getId();
        $traineeToken = $this->loginAs('trainee', 'pw-12345', Roles::TRAINEE);

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'Pulls',
            'startsAt' => '2026-07-01T09:00:00+00:00',
            'endsAt' => '2026-07-01T10:00:00+00:00',
            'capacity' => 2,
        ], $sup1);
        $this->call('POST', '/api/bookings', ['sessionId' => $session->getBody()['id']], $traineeToken);

        // sup2 queries the same trainee's bookings; list must be filtered.
        $listing = $this->call('GET', '/api/bookings', [], $sup2, ['traineeId' => $traineeId]);
        self::assertSame(200, $listing->getStatus());
        self::assertSame([], $listing->getBody()['bookings']);
    }
}
