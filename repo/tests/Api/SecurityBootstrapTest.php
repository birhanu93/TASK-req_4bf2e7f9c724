<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Bootstrap-admin must be a strictly one-shot operation. These tests lock in
 * the replay-prevention invariant.
 */
final class SecurityBootstrapTest extends ApiTestCase
{
    public function testReplayWithSamePayloadBlocked(): void
    {
        $ok = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
        ]);
        self::assertSame(201, $ok->getStatus());

        $replay = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
        ]);
        self::assertSame(409, $replay->getStatus());
    }

    public function testReplayWithDifferentPayloadBlocked(): void
    {
        $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
        ]);

        $replay = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin2',
            'password' => 'different-pass',
        ]);
        self::assertSame(409, $replay->getStatus());
    }

    public function testBootstrapRequiresUsernameAndPassword(): void
    {
        $r = $this->call('POST', '/api/auth/bootstrap', ['username' => 'x']);
        self::assertSame(401, $r->getStatus());
    }

    public function testRegisterDoesNotBypassBootstrap(): void
    {
        // Legacy "roles=admin + bootstrap=true in body" bypass must be gone.
        $r = $this->call('POST', '/api/auth/register', [
            'username' => 'admin',
            'password' => 'pw-12345',
            'roles' => [Roles::ADMIN],
            'bootstrap' => true,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testServiceLevelReplayAlsoBlocked(): void
    {
        // Replay at the service layer (no HTTP involved) must also fail.
        $this->kernel->auth->bootstrapAdmin('root', 'rootpass-1234');
        $this->expectException(\App\Exception\ConflictException::class);
        $this->kernel->auth->bootstrapAdmin('root2', 'rootpass-5678');
    }
}
