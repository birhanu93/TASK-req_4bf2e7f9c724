<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Full guardian device lifecycle over HTTP: link child, approve a device,
 * list devices, remote-logout, observe revoked state. Cross-tenant
 * denial is also covered so one guardian cannot revoke another guardian's
 * child's device.
 */
final class GuardianRemoteLogoutTest extends ApiTestCase
{
    public function testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus(): void
    {
        $admin = $this->seedAdmin();
        $this->createUser('guard', 'pw-12345', [Roles::GUARDIAN]);
        $this->createUser('child', 'pw-12345', [Roles::TRAINEE]);
        $guardToken = $this->loginAs('guard', 'pw-12345', Roles::GUARDIAN);
        $childId = $this->kernel->users->findByUsername('child')->getId();

        // Admin wires the link first so the guardian can act on the child.
        $link = $this->call('POST', '/api/guardians/links', [
            'guardianId' => $this->kernel->users->findByUsername('guard')->getId(),
            'childId' => $childId,
        ], $admin);
        self::assertSame(201, $link->getStatus());

        $approve = $this->call('POST', '/api/guardians/devices', [
            'childId' => $childId,
            'deviceName' => 'Phone',
            'fingerprint' => str_repeat('a', 40),
        ], $guardToken);
        self::assertSame(201, $approve->getStatus());
        $deviceId = $approve->getBody()['id'];
        self::assertSame('approved', $approve->getBody()['status']);

        $list = $this->call('GET', "/api/guardians/children/{$childId}/devices", [], $guardToken);
        self::assertSame(200, $list->getStatus());
        self::assertSame('approved', $list->getBody()['devices'][0]['status']);

        $logout = $this->call('POST', "/api/guardians/devices/{$deviceId}/logout", [], $guardToken);
        self::assertSame(200, $logout->getStatus());
        self::assertSame('revoked', $logout->getBody()['status']);

        $after = $this->call('GET', "/api/guardians/children/{$childId}/devices", [], $guardToken);
        self::assertSame('revoked', $after->getBody()['devices'][0]['status']);
    }

    public function testOtherGuardianCannotRemoteLogoutDevice(): void
    {
        $admin = $this->seedAdmin();
        $this->createUser('g1', 'pw-12345', [Roles::GUARDIAN]);
        $this->createUser('g2', 'pw-12345', [Roles::GUARDIAN]);
        $this->createUser('child', 'pw-12345', [Roles::TRAINEE]);
        $g1 = $this->loginAs('g1', 'pw-12345', Roles::GUARDIAN);
        $g2 = $this->loginAs('g2', 'pw-12345', Roles::GUARDIAN);
        $childId = $this->kernel->users->findByUsername('child')->getId();

        $this->call('POST', '/api/guardians/links', [
            'guardianId' => $this->kernel->users->findByUsername('g1')->getId(),
            'childId' => $childId,
        ], $admin);

        $approve = $this->call('POST', '/api/guardians/devices', [
            'childId' => $childId,
            'deviceName' => 'Phone',
            'fingerprint' => str_repeat('b', 40),
        ], $g1);
        $deviceId = $approve->getBody()['id'];

        // g2 has no link to child — the remote logout must be rejected.
        $res = $this->call('POST', "/api/guardians/devices/{$deviceId}/logout", [], $g2);
        self::assertSame(404, $res->getStatus());
    }

    public function testUnknownDeviceReturns404(): void
    {
        $this->seedAdmin();
        $this->createUser('g', 'pw-12345', [Roles::GUARDIAN]);
        $g = $this->loginAs('g', 'pw-12345', Roles::GUARDIAN);
        $res = $this->call('POST', '/api/guardians/devices/never-existed/logout', [], $g);
        self::assertSame(404, $res->getStatus());
    }

    public function testGuardianCannotListDevicesForUnlinkedChild(): void
    {
        $this->seedAdmin();
        $this->createUser('g', 'pw-12345', [Roles::GUARDIAN]);
        $this->createUser('child', 'pw-12345', [Roles::TRAINEE]);
        $g = $this->loginAs('g', 'pw-12345', Roles::GUARDIAN);
        $childId = $this->kernel->users->findByUsername('child')->getId();
        $res = $this->call('GET', "/api/guardians/children/{$childId}/devices", [], $g);
        self::assertSame(404, $res->getStatus());
    }

    public function testNonGuardianCannotApproveDevice(): void
    {
        $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $res = $this->call('POST', '/api/guardians/devices', [
            'childId' => 'any',
            'deviceName' => 'Phone',
            'fingerprint' => str_repeat('c', 40),
        ], $sup);
        self::assertSame(403, $res->getStatus());
    }
}
