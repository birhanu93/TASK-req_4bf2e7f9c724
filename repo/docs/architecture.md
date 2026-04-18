# Architecture

## HTTP stack

The entry point is `public/index.php`, which bootstraps the `App\App\Kernel`
service container and hands off request handling to `App\App\HttpApplication`
— a Symfony `HttpKernelInterface` implementation.

Request/response flow:

1. `Symfony\Component\HttpFoundation\Request::createFromGlobals()` builds the
   edge request.
2. `HttpApplication::handle()` converts it to the internal `App\Http\Request`
   and dispatches through `App\Http\Router`.
3. `Router` uses `symfony/routing`'s `RouteCollection` + `UrlMatcher`
   internally; route declarations are still added through
   `Router::add($method, $pattern, $handler)` so controllers and tests
   continue to work unchanged.
4. Controllers return an `App\Http\Response` which is serialised to a
   `Symfony\Component\HttpFoundation\JsonResponse` at the edge.

Domain exceptions (`App\Exception\*`) map to HTTP status codes via
`DomainException::getHttpStatus()`; `InvalidArgumentException` becomes 400.

## Dependency injection

`App\App\Kernel` is the composition root. It wires:

- persistence: `InMemoryDatabase` (tests) or `PdoDatabase` (production),
  behind the `App\Persistence\Database` interface
- repositories: each backend (InMemory / Pdo) implements
  `App\Repository\Contract\*RepositoryInterface`; services only depend on the
  interface
- services: `Auth`, `Authorization`, `Booking`, `Scheduling`, `Voucher`,
  `Moderation`, `Guardian`, `Certificate`, `Profile`, `StorageTiering`,
  `SnapshotExporter`, `AuditLogger`, `Keyring`, utility services

`App\App\Container` exposes a PSR-11 container backed by
`Symfony\Component\DependencyInjection\Container`. Every interface → concrete
binding is registered so callers can resolve services by interface name or by
concrete class name.

## Persistence

The `Database` interface abstracts over MySQL (production) and an in-memory
driver (tests). `PdoDatabase::fromDsn` retries transient connection errors,
pins `utf8mb4`/strict SQL mode via an init command, and pings with
`SELECT 1` at boot so connectivity issues surface before the first request.

Transactions nest via savepoints (`PdoDatabase::beginTransaction`), and
`Database::lock($resource)` issues a named MySQL advisory lock so services
can serialise per-key sections without explicit row-level locks.

## Directory map

| Path | Contents |
|------|----------|
| `src/App/` | Kernel, Container, HttpApplication |
| `src/Controller/` | HTTP controllers |
| `src/Entity/` | POPO domain entities |
| `src/Http/` | Request/Response adapters, Router (symfony/routing) |
| `src/Persistence/` | Database interface, PDO + in-memory drivers, Migrator |
| `src/Repository/Contract/` | Repository interfaces |
| `src/Repository/Pdo/` | MySQL repositories |
| `src/Repository/` | In-memory repositories |
| `src/Service/` | Business services |
| `src/Exception/` | Domain exceptions |
