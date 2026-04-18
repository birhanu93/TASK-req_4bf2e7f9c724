<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Exception\DomainException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use PHPUnit\Framework\TestCase;

final class HttpTest extends TestCase
{
    public function testRequestAccessors(): void
    {
        $req = new Request(
            'post',
            '/api/x',
            ['Authorization' => 'Bearer abc', 'X-Env' => 'test'],
            ['a' => 1],
            ['q' => 'v'],
        );
        self::assertSame('post', $req->getMethod());
        self::assertSame('/api/x', $req->getPath());
        self::assertSame('Bearer abc', $req->header('authorization'));
        self::assertSame('abc', $req->bearerToken());
        self::assertSame(1, $req->input('a'));
        self::assertSame('default', $req->input('missing', 'default'));
        self::assertSame(['a' => 1], $req->all());
        self::assertSame('v', $req->query('q'));
        self::assertSame('fallback', $req->query('none', 'fallback'));
        self::assertNull($req->header('missing'));
    }

    public function testBearerMissing(): void
    {
        $req = new Request('GET', '/x');
        self::assertNull($req->bearerToken());
        $req2 = new Request('GET', '/x', ['Authorization' => 'Basic xxx']);
        self::assertNull($req2->bearerToken());
    }

    public function testResponseJson(): void
    {
        $r = Response::json(['k' => 1], 201);
        self::assertSame(201, $r->getStatus());
        self::assertSame(['k' => 1], $r->getBody());
        self::assertStringContainsString('"k":1', $r->toJson());

        $noContent = Response::noContent();
        self::assertSame(204, $noContent->getStatus());
        self::assertNull($noContent->getBody());
        self::assertSame('{}', $noContent->toJson());

        $err = Response::error('bad', 400);
        self::assertSame(400, $err->getStatus());
        self::assertSame(['error' => 'bad'], $err->getBody());
    }

    public function testRouterDispatch(): void
    {
        $router = new Router();
        $router->add('GET', '/api/hello/{name}', function (Request $req, array $vars) {
            return Response::json(['hi' => $vars['name']]);
        });
        $response = $router->dispatch(new Request('GET', '/api/hello/alice?foo=bar'));
        self::assertSame(['hi' => 'alice'], $response->getBody());
    }

    public function testRouterMethodMismatch(): void
    {
        $router = new Router();
        $router->add('GET', '/api/x', fn () => Response::json([]));
        $r = $router->dispatch(new Request('POST', '/api/x'));
        // Symfony routing distinguishes method mismatch from a missing route;
        // 405 is the correct status when the path exists but the method does
        // not match.
        self::assertSame(405, $r->getStatus());
    }

    public function testRouterUnknown(): void
    {
        $router = new Router();
        $r = $router->dispatch(new Request('GET', '/unknown'));
        self::assertSame(404, $r->getStatus());
    }

    public function testRouterDomainExceptionConverted(): void
    {
        $router = new Router();
        $router->add('GET', '/boom', fn () => throw new DomainException('nope', 418));
        $r = $router->dispatch(new Request('GET', '/boom'));
        self::assertSame(418, $r->getStatus());
        self::assertSame(['error' => 'nope'], $r->getBody());
    }

    public function testRouterInvalidArgumentConverted(): void
    {
        $router = new Router();
        $router->add('GET', '/bad', fn () => throw new \InvalidArgumentException('nope'));
        $r = $router->dispatch(new Request('GET', '/bad'));
        self::assertSame(400, $r->getStatus());
    }
}
