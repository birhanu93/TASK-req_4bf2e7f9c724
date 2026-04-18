<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

/**
 * Exercises the anti-token-abuse story: role tokens are only minted against a
 * fresh password proof, sessions expire, revoked sessions stop working, and
 * changing a password invalidates every live session for the actor.
 */
final class TokenAbuseTest extends ApiTestCase
{
    public function testSelectRoleWithoutPasswordRejected(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        // userId is no longer sufficient — without password, no token issues.
        $r = $this->call('POST', '/api/auth/select-role', [
            'username' => 'alice',
            'role' => Roles::TRAINEE,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testSelectRoleWithWrongPasswordRejected(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $r = $this->call('POST', '/api/auth/select-role', [
            'username' => 'alice',
            'password' => 'wrong',
            'role' => Roles::TRAINEE,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testSelectRoleForUnassignedRoleRejected(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $r = $this->call('POST', '/api/auth/select-role', [
            'username' => 'alice',
            'password' => 'pw-12345',
            'role' => Roles::ADMIN,
        ]);
        self::assertSame(403, $r->getStatus());
    }

    public function testSwitchRoleWithoutPasswordRejected(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE, Roles::GUARDIAN]);
        $token = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);
        $r = $this->call('POST', '/api/auth/switch-role', [
            'role' => Roles::GUARDIAN,
        ], $token);
        self::assertSame(401, $r->getStatus());
    }

    public function testPasswordChangeRevokesExistingSessions(): void
    {
        $this->createUser('alice', 'old-pass-1', [Roles::TRAINEE]);
        $token = $this->loginAs('alice', 'old-pass-1', Roles::TRAINEE);
        $change = $this->call('POST', '/api/auth/change-password', [
            'oldPassword' => 'old-pass-1',
            'newPassword' => 'new-pass-2',
        ], $token);
        self::assertSame(204, $change->getStatus());
        $me = $this->call('GET', '/api/auth/me', [], $token);
        self::assertSame(401, $me->getStatus());
    }

    public function testSessionExpiresAfterTtl(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $token = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);
        $clock = $this->kernel->clock;
        if (method_exists($clock, 'advance')) {
            $clock->advance(\App\Service\AuthService::SESSION_TTL_SECONDS + 60);
        }
        $r = $this->call('GET', '/api/auth/me', [], $token);
        self::assertSame(401, $r->getStatus());
    }

    public function testForgedTokenRejected(): void
    {
        $r = $this->call('GET', '/api/auth/me', [], bin2hex(random_bytes(24)));
        self::assertSame(401, $r->getStatus());
    }

    public function testLogoutInvalidatesToken(): void
    {
        $this->createUser('alice', 'pw-12345', [Roles::TRAINEE]);
        $token = $this->loginAs('alice', 'pw-12345', Roles::TRAINEE);
        $this->call('POST', '/api/auth/logout', [], $token);
        $r = $this->call('GET', '/api/auth/me', [], $token);
        self::assertSame(401, $r->getStatus());
    }
}
