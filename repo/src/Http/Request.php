<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\UnsupportedMediaTypeException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Transport-level request adapter. Internally backed by
 * Symfony\Component\HttpFoundation\Request so the backend runs on the Symfony
 * HTTP stack while controllers continue to consume the narrow accessors below.
 */
final class Request
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $body
     * @param array<string,string> $query
     */
    public const SESSION_COOKIE = 'workforce_session';

    /**
     * @param array<string,string> $cookies
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $headers = [],
        private array $body = [],
        private array $query = [],
        private ?SymfonyRequest $symfony = null,
        private array $cookies = [],
    ) {
    }

    public static function fromSymfony(SymfonyRequest $request): self
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = is_array($values) ? (string) ($values[0] ?? '') : (string) $values;
        }

        // Mutating endpoints are JSON-only per the documented API contract.
        // A request that sends a non-empty body with a non-JSON Content-Type
        // is rejected with 415 rather than being silently form-decoded —
        // that would let a client-side XSS that can submit form posts reach
        // state-changing endpoints a browser's fetch(...) would not.
        // GETs and HEADs are read-only and skip the check; bodyless mutating
        // requests (e.g. POST /confirm, POST /logout) carry no payload and
        // therefore have nothing to validate.
        $body = [];
        $method = strtoupper($request->getMethod());
        $contentType = (string) ($request->headers->get('Content-Type') ?? '');
        $raw = (string) $request->getContent();
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $hasBody = $raw !== '' || $request->request->count() > 0;
        if ($isMutating && $hasBody && !str_contains($contentType, 'application/json')) {
            throw new UnsupportedMediaTypeException('Content-Type must be application/json');
        }
        if (str_contains($contentType, 'application/json')) {
            if ($raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $body = $decoded;
                    }
                } catch (\JsonException $e) {
                    throw new \InvalidArgumentException('invalid JSON body');
                }
            }
        } else {
            $body = $request->request->all();
        }

        $query = array_map('strval', $request->query->all());
        $cookies = array_map('strval', $request->cookies->all());

        return new self(
            strtoupper($request->getMethod()),
            $request->getPathInfo(),
            $headers,
            $body,
            $query,
            $request,
            $cookies,
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $key) {
                return $v;
            }
        }
        return null;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Resolve the session token from either the Authorization header or the
     * HttpOnly session cookie. Browsers hold the cookie only — server-to-
     * server clients and tests can still pass the token as a bearer header.
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth !== null && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        $cookie = $this->cookie(self::SESSION_COOKIE);
        if ($cookie !== null && $cookie !== '') {
            return $cookie;
        }
        return null;
    }

    public function symfony(): ?SymfonyRequest
    {
        return $this->symfony;
    }
}
