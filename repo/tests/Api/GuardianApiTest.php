<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class GuardianApiTest extends ApiTestCase
{
    public function testLinkApproveRevoke(): void
    {
        $guardian = $this->seedUser('gg', 'pass-1234', [Roles::GUARDIAN], Roles::GUARDIAN);
        $this->createUser('child1', 'pass-1234', [Roles::TRAINEE]);
        $childId = $this->kernel->users->findByUsername('child1')->getId();

        $link = $this->call('POST', '/api/guardians/links', ['childId' => $childId], $guardian);
        self::assertSame(201, $link->getStatus());

        $children = $this->call('GET', '/api/guardians/children', [], $guardian);
        self::assertCount(1, $children->getBody()['children']);

        $device = $this->call('POST', '/api/guardians/devices', [
            'childId' => $childId,
            'deviceName' => 'iPad',
            'fingerprint' => 'fp-1',
        ], $guardian);
        self::assertSame(201, $device->getStatus());
        $did = $device->getBody()['id'];

        $logout = $this->call('POST', "/api/guardians/devices/{$did}/logout", [], $guardian);
        self::assertSame(200, $logout->getStatus());
        self::assertSame('revoked', $logout->getBody()['status']);
    }

    public function testUnauthenticated(): void
    {
        self::assertSame(401, $this->call('POST', '/api/guardians/links')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/guardians/devices')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/guardians/devices/x/logout')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/guardians/children')->getStatus());
    }
}
