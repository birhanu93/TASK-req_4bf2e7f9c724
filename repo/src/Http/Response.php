<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Transport-level response adapter. Produces HttpFoundation responses at the
 * edge while letting controllers keep returning the plain Response value they
 * already construct via {@see self::json()}. Supports attaching HttpOnly
 * cookies — used by the auth controller to carry the session token in a
 * form that JavaScript cannot read.
 */
final class Response
{
    /** @var Cookie[] */
    private array $cookies = [];

    /**
     * @param array<string,mixed>|list<mixed>|null $body
     * @param array<string,string> $headers
     */
    public function __construct(
        private int $status,
        private array|null $body = null,
        private array $headers = [],
    ) {
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string,mixed>|list<mixed>|null
     */
    public function getBody(): array|null
    {
        return $this->body;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function toJson(): string
    {
        return json_encode($this->body ?? new \stdClass(), JSON_THROW_ON_ERROR);
    }

    public function withCookie(Cookie $cookie): self
    {
        $this->cookies[] = $cookie;
        return $this;
    }

    /**
     * @return Cookie[]
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function toSymfony(): SymfonyResponse
    {
        $headers = $this->headers + ['X-Content-Type-Options' => 'nosniff'];
        if ($this->status === 204 || $this->body === null) {
            $response = new SymfonyResponse('', $this->status, $headers + ['Content-Type' => 'application/json']);
        } else {
            $response = new JsonResponse($this->body, $this->status, $headers);
        }
        foreach ($this->cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }
        return $response;
    }

    /**
     * @param array<string,mixed>|list<mixed>|null $body
     */
    public static function json(array|null $body, int $status = 200): self
    {
        return new self($status, $body);
    }

    public static function noContent(): self
    {
        return new self(204, null);
    }

    public static function error(string $message, int $status): self
    {
        return new self($status, ['error' => $message]);
    }
}
