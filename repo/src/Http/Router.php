<?php

declare(strict_types=1);

namespace App\Http;

use App\Exception\DomainException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * Thin router backed by symfony/routing. Preserves the pre-Symfony
 * {@see add()} signature so existing route declarations and tests keep
 * working, while matching and dispatch are handled by the Symfony
 * UrlMatcher.
 */
final class Router
{
    private RouteCollection $collection;

    /** @var array<string,callable(Request,array<string,string>):Response> */
    private array $handlers = [];

    private int $seq = 0;

    public function __construct()
    {
        $this->collection = new RouteCollection();
    }

    /**
     * @param callable(Request,array<string,string>):Response $handler
     */
    public function add(string $method, string $pattern, callable $handler): void
    {
        $name = sprintf('r_%s_%04d', strtolower($method), $this->seq++);
        $symPath = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '{$1}', $pattern);
        $route = new Route($symPath, [], [], [], '', [], [strtoupper($method)]);
        $this->collection->add($name, $route);
        $this->handlers[$name] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $context = new RequestContext();
        $context->setMethod($request->getMethod());
        $path = parse_url($request->getPath(), PHP_URL_PATH) ?: $request->getPath();

        $matcher = new UrlMatcher($this->collection, $context);
        try {
            $match = $matcher->match($path);
        } catch (ResourceNotFoundException) {
            return Response::error('route not found', 404);
        } catch (MethodNotAllowedException) {
            return Response::error('method not allowed', 405);
        }

        $name = (string) ($match['_route'] ?? '');
        $handler = $this->handlers[$name] ?? null;
        if ($handler === null) {
            return Response::error('route not found', 404);
        }

        $vars = [];
        foreach ($match as $k => $v) {
            if ($k === '_route' || str_starts_with((string) $k, '_')) {
                continue;
            }
            $vars[(string) $k] = (string) $v;
        }

        try {
            return $handler($request, $vars);
        } catch (DomainException $e) {
            return Response::error($e->getMessage(), $e->getHttpStatus());
        } catch (\InvalidArgumentException $e) {
            return Response::error($e->getMessage(), 400);
        }
    }

    public function dispatchSymfony(SymfonyRequest $symfony): Response
    {
        return $this->dispatch(Request::fromSymfony($symfony));
    }

    public function collection(): RouteCollection
    {
        return $this->collection;
    }
}
