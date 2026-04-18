<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class AuthApiTest extends ApiTestCase
{
    public function testBootstrapCreatesFirstAdmin(): void
    {
        $r = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
        ]);
        self::assertSame(201, $r->getStatus());
        self::assertSame('admin', $r->getBody()['username']);
        self::assertContains(Roles::ADMIN, $r->getBody()['roles']);
    }

    public function testBootstrapReplayBlocked(): void
    {
        $first = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
        ]);
        self::assertSame(201, $first->getStatus());

        // Replay: second bootstrap call must be rejected even with different
        // credentials. The marker is claimed atomically on first success.
        $replay = $this->call('POST', '/api/auth/bootstrap', [
            'username' => 'admin2',
            'password' => 'admin-pass-2',
        ]);
        self::assertSame(409, $replay->getStatus());
    }

    public function testRegisterRequiresAuth(): void
    {
        $r = $this->call('POST', '/api/auth/register', [
            'username' => 'alice',
            'password' => 'pass-1',
            'roles' => [Roles::TRAINEE],
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testRegisterWithBootstrapFlagNoLongerCreatesAdmin(): void
    {
        // Legacy "bootstrap=true in body" path is removed — only /bootstrap
        // can create the first admin. This request should 401.
        $r = $this->call('POST', '/api/auth/register', [
            'username' => 'admin',
            'password' => 'admin-pass-1',
            'roles' => [Roles::ADMIN],
            'bootstrap' => true,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testAdminCreatesUser(): void
    {
        $token = $this->seedAdmin();
        $r = $this->call('POST', '/api/auth/register', [
            'username' => 'alice',
            'password' => 'pass-1',
            'roles' => [Roles::TRAINEE],
        ], $token);
        self::assertSame(201, $r->getStatus());
    }

    public function testLoginFlow(): void
    {
        $this->createUser('alice', 'pass-1234', [Roles::TRAINEE, Roles::GUARDIAN]);
        $login = $this->call('POST', '/api/auth/login', ['username' => 'alice', 'password' => 'pass-1234']);
        self::assertSame(200, $login->getStatus());
        self::assertCount(2, $login->getBody()['availableRoles']);

        $select = $this->call('POST', '/api/auth/select-role', [
            'username' => 'alice',
            'password' => 'pass-1234',
            'role' => Roles::GUARDIAN,
        ]);
        self::assertSame(200, $select->getStatus());
        $token = $select->getBody()['token'];

        $switch = $this->call('POST', '/api/auth/switch-role', [
            'password' => 'pass-1234',
            'role' => Roles::TRAINEE,
        ], $token);
        self::assertSame(200, $switch->getStatus());
        self::assertSame(Roles::TRAINEE, $switch->getBody()['role']);

        $newToken = $switch->getBody()['token'];
        $logout = $this->call('POST', '/api/auth/logout', [], $newToken);
        self::assertSame(204, $logout->getStatus());
    }

    public function testSelectRoleRequiresCurrentPassword(): void
    {
        $this->createUser('alice', 'pass-1234', [Roles::TRAINEE]);
        // Without password — a raw userId is no longer enough to mint a token.
        $r = $this->call('POST', '/api/auth/select-role', [
            'username' => 'alice',
            'role' => Roles::TRAINEE,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testSwitchRoleRequiresToken(): void
    {
        $r = $this->call('POST', '/api/auth/switch-role', [
            'password' => 'x',
            'role' => Roles::TRAINEE,
        ]);
        self::assertSame(401, $r->getStatus());
    }

    public function testSwitchRoleRequiresPassword(): void
    {
        $this->createUser('alice', 'pass-1234', [Roles::TRAINEE, Roles::GUARDIAN]);
        $token = $this->loginAs('alice', 'pass-1234', Roles::TRAINEE);
        $r = $this->call('POST', '/api/auth/switch-role', ['role' => Roles::GUARDIAN], $token);
        self::assertSame(401, $r->getStatus());
    }

    public function testLogoutRequiresToken(): void
    {
        $r = $this->call('POST', '/api/auth/logout');
        self::assertSame(401, $r->getStatus());
    }

    public function testChangePassword(): void
    {
        $this->createUser('alice', 'pass-1234', [Roles::TRAINEE]);
        $token = $this->loginAs('alice', 'pass-1234', Roles::TRAINEE);
        $r = $this->call('POST', '/api/auth/change-password', [
            'oldPassword' => 'pass-1234',
            'newPassword' => 'new-pass-5678',
        ], $token);
        self::assertSame(204, $r->getStatus());
    }

    public function testChangePasswordNoToken(): void
    {
        $r = $this->call('POST', '/api/auth/change-password', ['oldPassword' => 'a', 'newPassword' => 'b']);
        self::assertSame(401, $r->getStatus());
    }
}
