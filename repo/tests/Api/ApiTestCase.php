<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\App\HttpApplication;
use App\App\Kernel;
use App\Http\Response;
use App\Service\Roles;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class ApiTestCase extends TestCase
{
    protected Kernel $kernel;
    protected HttpApplication $app;

    /**
     * Cookie jar reused across calls inside a single test case. Any
     * Set-Cookie emitted by one call is echoed back on the next, mirroring
     * how a browser actually behaves — this means tests exercise the full
     * HttpOnly session-cookie round-trip end-to-end.
     *
     * @var array<string,string>
     */
    protected array $cookies = [];

    protected function setUp(): void
    {
        $this->kernel = Factory::kernel();
        $this->app = new HttpApplication($this->kernel);
        $this->cookies = [];
    }

    /**
     * Dispatch a request through the full Symfony HttpKernel pipeline — the
     * exact same path a real browser request takes in production. The raw
     * HttpFoundation response is adapted back into an {@see Response} for
     * assertion ergonomics, and any Set-Cookie that came back is stashed so
     * the next call carries it automatically.
     *
     * @param array<string,mixed> $body
     * @param array<string,string> $query
     * @param array<string,string> $headers
     */
    protected function call(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null,
        array $query = [],
        array $headers = [],
    ): Response {
        $symReq = $this->buildSymfonyRequest($method, $path, $body, $token, $query, $headers);
        $symRes = $this->app->handle($symReq);
        $this->captureCookies($symRes);
        return $this->adaptResponse($symRes);
    }

    /**
     * @param array<string,mixed> $body
     * @param array<string,string> $query
     * @param array<string,string> $headers
     */
    protected function buildSymfonyRequest(
        string $method,
        string $path,
        array $body = [],
        ?string $token = null,
        array $query = [],
        array $headers = [],
    ): SymfonyRequest {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        foreach ($headers as $name => $value) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            if (strcasecmp($name, 'Content-Type') === 0) {
                $server['CONTENT_TYPE'] = $value;
            } else {
                $server[$key] = $value;
            }
        }
        $content = '';
        if ($body !== []) {
            $content = json_encode($body, JSON_THROW_ON_ERROR);
        }
        $qs = $query !== [] ? '?' . http_build_query($query) : '';

        return SymfonyRequest::create(
            $path . $qs,
            strtoupper($method),
            [],
            $this->cookies,
            [],
            $server,
            $content !== '' ? $content : null,
        );
    }

    private function captureCookies(SymfonyResponse $res): void
    {
        foreach ($res->headers->getCookies() as $cookie) {
            if ($cookie->getValue() === null || $cookie->isCleared()) {
                unset($this->cookies[$cookie->getName()]);
                continue;
            }
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
    }

    private function adaptResponse(SymfonyResponse $res): Response
    {
        $status = $res->getStatusCode();
        $raw = (string) $res->getContent();
        if ($raw === '' || $status === 204) {
            return new Response($status, null);
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new Response($status, null);
        }
        return new Response($status, $decoded);
    }

    /**
     * @param string[] $roles
     */
    protected function createUser(string $username, string $password, array $roles): string
    {
        $user = $this->kernel->auth->register($username, $password, $roles);
        return $user->getId();
    }

    /**
     * @param string[] $roles
     */
    protected function loginAs(string $username, string $password, string $role): string
    {
        return Factory::login($this->kernel, $username, $password, $role);
    }

    protected function seedAdmin(): string
    {
        return Factory::seedAdmin($this->kernel);
    }

    protected function seedUser(string $username, string $password, array $roles, string $role): string
    {
        $this->createUser($username, $password, $roles);
        return $this->loginAs($username, $password, $role);
    }

    protected function roles(): array
    {
        return [
            'admin' => Roles::ADMIN,
            'trainee' => Roles::TRAINEE,
            'supervisor' => Roles::SUPERVISOR,
            'guardian' => Roles::GUARDIAN,
            'employer' => Roles::EMPLOYER,
        ];
    }
}
