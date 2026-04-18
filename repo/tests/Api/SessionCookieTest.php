<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\App\HttpApplication;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Session tokens are carried to browsers in an HttpOnly + SameSite=Strict
 * cookie, not a JSON value that lives in localStorage. The backend must
 *
 * - set the cookie on select-role and switch-role responses,
 * - clear it on logout,
 * - accept the cookie as authentication on subsequent requests (so the
 *   Authorization header is no longer required), and
 * - continue to accept Authorization: Bearer for API clients that can't
 *   manage cookies (existing server-to-server tests).
 */
final class SessionCookieTest extends TestCase
{
    public function testSelectRoleSetsHttpOnlyCookie(): void
    {
        $kernel = Factory::kernel();
        Factory::seedAdmin($kernel);
        // Use a plain trainee so the cookie path is exercised fresh.
        $kernel->auth->register('alice', 'pass-1234', [Roles::TRAINEE]);

        $app = new HttpApplication($kernel);
        $req = SymfonyRequest::create(
            '/api/auth/select-role',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'alice', 'password' => 'pass-1234', 'role' => Roles::TRAINEE]),
        );
        $res = $app->handle($req);

        self::assertSame(200, $res->getStatusCode());
        $cookies = $res->headers->getCookies();
        $named = array_values(array_filter($cookies, fn ($c) => $c->getName() === 'workforce_session'));
        self::assertCount(1, $named, 'select-role must set the session cookie');
        $cookie = $named[0];
        self::assertTrue($cookie->isHttpOnly(), 'cookie must be HttpOnly');
        self::assertSame('strict', $cookie->getSameSite());
        self::assertNotEmpty($cookie->getValue(), 'cookie must carry the token');
        $body = json_decode((string) $res->getContent(), true);
        self::assertSame($cookie->getValue(), $body['token']);
    }

    public function testCookieAloneAuthenticatesSubsequentRequests(): void
    {
        $kernel = Factory::kernel();
        $app = new HttpApplication($kernel);

        Factory::seedAdmin($kernel);
        $kernel->auth->register('alice', 'pass-1234', [Roles::TRAINEE]);
        $select = $app->handle(SymfonyRequest::create(
            '/api/auth/select-role',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'alice', 'password' => 'pass-1234', 'role' => Roles::TRAINEE]),
        ));
        $cookie = $this->sessionCookie($select);

        // Second request uses ONLY the cookie — no Authorization header.
        $me = $app->handle(SymfonyRequest::create(
            '/api/auth/me',
            'GET',
            [],
            ['workforce_session' => $cookie],
        ));
        self::assertSame(200, $me->getStatusCode());
        $body = json_decode((string) $me->getContent(), true);
        self::assertSame(Roles::TRAINEE, $body['role']);
    }

    public function testLogoutClearsCookie(): void
    {
        $kernel = Factory::kernel();
        $app = new HttpApplication($kernel);

        Factory::seedAdmin($kernel);
        $kernel->auth->register('alice', 'pass-1234', [Roles::TRAINEE]);
        $select = $app->handle(SymfonyRequest::create(
            '/api/auth/select-role',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => 'alice', 'password' => 'pass-1234', 'role' => Roles::TRAINEE]),
        ));
        $cookie = $this->sessionCookie($select);

        $logout = $app->handle(SymfonyRequest::create(
            '/api/auth/logout',
            'POST',
            [],
            ['workforce_session' => $cookie],
        ));
        self::assertSame(204, $logout->getStatusCode());
        $cleared = array_values(array_filter(
            $logout->headers->getCookies(),
            fn ($c) => $c->getName() === 'workforce_session',
        ));
        self::assertCount(1, $cleared);
        self::assertTrue($cleared[0]->isCleared(), 'logout must clear the cookie');
    }

    private function sessionCookie(SymfonyResponse $res): string
    {
        foreach ($res->headers->getCookies() as $c) {
            if ($c->getName() === 'workforce_session') {
                return (string) $c->getValue();
            }
        }
        self::fail('session cookie was not set on response');
    }
}
