<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    public function testRegisterAndLogin(): void
    {
        $k = Factory::kernel();
        $user = $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        self::assertSame(['trainee'], $user->getRoles());
        $res = $k->auth->login('alice', 'pw-12345');
        self::assertSame($user->getId(), $res['user']->getId());
        self::assertSame(['trainee'], $res['availableRoles']);
    }

    public function testRegisterDuplicateUsername(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->expectException(ValidationException::class);
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
    }

    public function testRegisterRequiresRole(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->auth->register('alice', 'pw-12345', []);
    }

    public function testRegisterInvalidRole(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->auth->register('alice', 'pw-12345', ['spy']);
    }

    public function testLoginWrongPassword(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->expectException(AuthException::class);
        $k->auth->login('alice', 'wrong-pass');
    }

    public function testLoginUnknownUser(): void
    {
        $k = Factory::kernel();
        $this->expectException(AuthException::class);
        $k->auth->login('ghost', 'pw-12345');
    }

    public function testLoginInactiveUser(): void
    {
        $k = Factory::kernel();
        $user = $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $user->deactivate();
        $k->users->save($user);
        $this->expectException(AuthException::class);
        $k->auth->login('alice', 'pw-12345');
    }

    public function testSelectRoleAndAuthenticate(): void
    {
        $k = Factory::kernel();
        $user = $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE, Roles::GUARDIAN]);
        $ctx = $k->auth->selectRole('alice', 'pw-12345', Roles::GUARDIAN);
        self::assertSame(Roles::GUARDIAN, $ctx->getActiveRole());
        self::assertSame($user->getId(), $ctx->getUserId());
        self::assertNotEmpty($ctx->getToken());
        self::assertSame($k->clock->now()->getTimestamp(), $ctx->getIssuedAt()->getTimestamp());
        $again = $k->auth->authenticate($ctx->getToken());
        self::assertSame($ctx->getToken(), $again->getToken());
    }

    public function testSelectRoleUnknownUser(): void
    {
        $k = Factory::kernel();
        $this->expectException(AuthException::class);
        $k->auth->selectRole('missing', 'nope', Roles::TRAINEE);
    }

    public function testSelectRoleNotAssigned(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->expectException(AccessDeniedException::class);
        $k->auth->selectRole('alice', 'pw-12345', Roles::ADMIN);
    }

    public function testSelectRoleWrongPasswordRejected(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $this->expectException(AuthException::class);
        $k->auth->selectRole('alice', 'wrong', Roles::TRAINEE);
    }

    public function testAuthenticateInvalid(): void
    {
        $k = Factory::kernel();
        $this->expectException(AuthException::class);
        $k->auth->authenticate('bogus');
    }

    public function testSwitchRole(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE, Roles::GUARDIAN]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $ctx2 = $k->auth->switchRole($t, 'pw-12345', Roles::GUARDIAN);
        self::assertSame(Roles::GUARDIAN, $ctx2->getActiveRole());
        self::assertNotSame($t, $ctx2->getToken());
        $this->expectException(AuthException::class);
        $k->auth->authenticate($t);
    }

    public function testSwitchRoleUserMissing(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $k->users->delete($k->users->findByUsername('alice')->getId());
        $this->expectException(NotFoundException::class);
        $k->auth->switchRole($t, 'pw-12345', Roles::TRAINEE);
    }

    public function testSwitchRoleNotAssigned(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $this->expectException(AccessDeniedException::class);
        $k->auth->switchRole($t, 'pw-12345', Roles::ADMIN);
    }

    public function testSwitchRoleWrongPasswordRejected(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE, Roles::GUARDIAN]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $this->expectException(AuthException::class);
        $k->auth->switchRole($t, 'wrong', Roles::GUARDIAN);
    }

    public function testLogout(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $k->auth->logout($t);
        $this->expectException(AuthException::class);
        $k->auth->authenticate($t);
    }

    public function testChangePassword(): void
    {
        $k = Factory::kernel();
        $u = $k->auth->register('alice', 'old-pass-1', [Roles::TRAINEE]);
        $k->auth->changePassword($u->getId(), 'old-pass-1', 'new-pass-2');
        $this->expectException(AuthException::class);
        $k->auth->login('alice', 'old-pass-1');
    }

    public function testChangePasswordWrongOld(): void
    {
        $k = Factory::kernel();
        $u = $k->auth->register('alice', 'old-pass-1', [Roles::TRAINEE]);
        $this->expectException(AuthException::class);
        $k->auth->changePassword($u->getId(), 'wrong', 'new-pass-2');
    }

    public function testChangePasswordUnknown(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->auth->changePassword('nope', 'x', 'y');
    }

    public function testChangePasswordRevokesSessions(): void
    {
        $k = Factory::kernel();
        $k->auth->register('alice', 'pw-12345', [Roles::TRAINEE]);
        $t = $k->auth->selectRole('alice', 'pw-12345', Roles::TRAINEE)->getToken();
        $k->auth->changePassword($k->users->findByUsername('alice')->getId(), 'pw-12345', 'new-pw-678');
        $this->expectException(AuthException::class);
        $k->auth->authenticate($t);
    }

    public function testRolesStatics(): void
    {
        self::assertTrue(Roles::isValid(Roles::ADMIN));
        self::assertFalse(Roles::isValid('owner'));
        self::assertContains(Roles::TRAINEE, Roles::all());
    }
}
