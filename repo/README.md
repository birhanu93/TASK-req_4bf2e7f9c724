# Workforce Training & Operations Hub

**Project type:** fullstack web application (PHP backend API + React SPA).

Multi-role web application for managing training sessions, bookings, assessments, vouchers, moderation, guardian oversight, and certificates.

- **Backend:** PHP 8.2+, Symfony HTTP stack (HttpFoundation, HttpKernel, Routing, DependencyInjection), MySQL 8 persistence (with in-memory fallback for tests).
- **Frontend:** Vite + React + TypeScript, one SPA with five role portals (trainee / supervisor / guardian / employer / admin).
- **Auth:** password-proved role tokens with 8-hour TTL, HttpOnly session cookie, one-time bootstrap admin.
- **Crypto:** AES-256-GCM profile encryption with a local, versioned keyring (DEK + KEK).

## Quick start — Docker-first

Everything runs in containers. You do **not** need PHP, Composer, Node, or npm on the host. The only prerequisite is **Docker 24+ with Docker Compose** (either the modern `docker compose` plugin or the legacy `docker-compose` binary).

```bash
# From the repository root — primary startup command:
docker-compose up

# Equivalent on hosts that ship the Docker Compose v2 plugin:
docker compose up --build
```

Either form is a valid primary entrypoint for the full stack; pick whichever your Docker installation provides. Both run the exact same Compose file (`docker-compose.yml`) and produce an identical running environment. On a cold checkout, pass `--build` (shown above for the v2 plugin) or run `docker-compose build` first so the backend and frontend images are compiled before `docker-compose up` starts them.

That single command:

1. Starts MySQL 8 (`db`) and waits for it to pass its healthcheck.
2. Runs `php bin/console migrate` in the `migrate` service to apply all SQL migrations.
3. Runs `php bin/console seed:demo` in the `seed` service, which bootstraps the admin and registers one demo user per role.
4. Starts the PHP backend (`app`) on `http://localhost:8080`.
5. Starts the Vite dev server (`frontend`) on `http://localhost:5173`, proxying `/api` to the backend container.

Leave the terminal running — Compose streams logs from every service. Use `Ctrl+C` (or `docker-compose down` / `docker compose down` in another terminal) to stop.

### Access URLs

| Surface            | URL                                        |
|--------------------|--------------------------------------------|
| React SPA (UI)     | http://localhost:5173                      |
| Backend API        | http://localhost:8080/api                  |
| Health probe       | http://localhost:8080/api/auth/me (401 = healthy, no session) |
| MySQL (host port)  | `127.0.0.1:3306` user=`workforce` pw=`workforce` db=`workforce` |

### Demo credentials

The `seed` service creates one user per role with the same password. All five accounts exist after `docker compose up` finishes.

| Role       | Username     | Password       |
|------------|--------------|----------------|
| admin      | `admin`      | `Demo!pass-1`  |
| supervisor | `supervisor` | `Demo!pass-1`  |
| trainee    | `trainee`    | `Demo!pass-1`  |
| guardian   | `guardian`   | `Demo!pass-1`  |
| employer   | `employer`   | `Demo!pass-1`  |

Override the shared password by setting `DEMO_PASSWORD` before startup (e.g. `DEMO_PASSWORD=MyStrongPass1! docker-compose up` or `DEMO_PASSWORD=MyStrongPass1! docker compose up --build`). The seed command is idempotent: re-running it on an already seeded database skips existing users.

### End-to-end verification

After `docker-compose up` (or `docker compose up --build`) reports all services healthy, run these from the **host**:

```bash
# 1. API is up — unauthenticated /me must return 401 with JSON.
curl -i http://localhost:8080/api/auth/me
# → HTTP/1.1 401 Unauthorized, body {"error":"..."}

# 2. Log in with a seeded user. Save the session cookie.
curl -i -c cookies.txt -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"Demo!pass-1"}'
# → 200, body {"userId":"...","username":"admin","availableRoles":["admin"]}

curl -i -b cookies.txt -c cookies.txt -X POST http://localhost:8080/api/auth/select-role \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"Demo!pass-1","role":"admin"}'
# → 200 + Set-Cookie: session=...; HttpOnly

# 3. Session-backed call succeeds.
curl -i -b cookies.txt http://localhost:8080/api/auth/me
# → 200, body {"userId":"...","role":"admin"}

# 4. Open http://localhost:5173, sign in as any demo user, and confirm the
#    role-specific sidebar loads (Admin sees Moderation / Vouchers / Ops;
#    Trainee sees Bookings / Vouchers / Certificates; etc.).
```

### Running the test suites inside Docker

```bash
# Backend — PHPUnit against the in-memory kernel:
docker-compose --profile tests run --rm tests
# or, on Compose v2:
docker compose run --rm --build tests

# Frontend — Vitest + React Testing Library:
docker-compose run --rm --entrypoint '' frontend npm test
# or, on Compose v2:
docker compose run --rm --build --entrypoint '' frontend npm test
```

Both suites run fully inside containers; no host-side Composer or npm install is required.

## First-time bootstrap (if you skip the seed service)

If you ran `docker-compose up` / `docker compose up` without the `seed` step (or you're bringing up an empty production database), the backend refuses to issue any tokens until a bootstrap admin exists. Create one via the dedicated endpoint — this is a **one-shot** operation enforced by a unique marker in `system_state`:

```bash
curl -X POST http://localhost:8080/api/auth/bootstrap \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"choose-a-strong-password"}'
```

Replay attempts return `409 Conflict`. From the React app, visit the sign-in page and use **First-time setup**.

## Repository layout

```
src/                 PHP source — entities, services, controllers, repositories
  App/               Kernel (DI composition), HttpApplication (Symfony HttpKernel driver), Container (PSR-11)
  Controller/        HTTP controllers (consume App\Http\Request, return App\Http\Response)
  Entity/            POPO entities
  Http/              Request + Response adapters; Router (symfony/routing under the hood)
  Persistence/       Database interface, InMemory and PDO drivers (retry + init SQL), Migrator
  Repository/        In-memory repositories (default implementations)
    Contract/        Repository interfaces — every service depends on these, never a concrete class
    Pdo/             MySQL-backed repositories
  Service/           Business services (auth, booking, voucher, moderation, ...)
  Exception/         Domain exception types (mapped to HTTP status by Router)
public/              index.php — HTTP entrypoint (Symfony HttpFoundation + HttpKernel)
bin/console          CLI: migrate, seed:demo, snapshot:export, keys:rotate, tiering:run, maintain:partitions
migrations/          SQL schema migrations (idempotent, numbered)
tests/               PHPUnit tests: Unit/, Api/, Support/
frontend/            Vite + React + TypeScript SPA
  src/routes/        Role portals (trainee, supervisor, guardian, employer, admin)
  src/lib/           API client + auth state
  src/components/    Shell (sidebar + topbar)
  src/**/*.test.*    Vitest + React Testing Library unit + e2e tests
docs/                Architecture, API, authorization, operations, testing guides
Dockerfile           Backend image (PHP 8.2 + pdo_mysql + pcov)
Dockerfile.frontend  Frontend image (Node 20 + Vite dev server)
docker-compose.yml   db, migrate, seed, app, frontend, tests
run_tests.sh         One-shot runner: builds the tests image and runs phpunit
```

## Authentication model

```
  POST /api/auth/bootstrap     one-shot: create first admin (blocked after)
  POST /api/auth/register      admin-only: create any user
  POST /api/auth/login         returns userId + availableRoles (no token)
  POST /api/auth/select-role   username + password + role → session token
  POST /api/auth/switch-role   existing token + password + role → new token
  POST /api/auth/change-password  revokes ALL existing sessions for the actor
  POST /api/auth/logout
  GET  /api/auth/me            current session info
```

Key guarantees:

- **Password re-proof on role issuance.** A leaked `userId` cannot be used to mint a token. Every `select-role` and `switch-role` call re-validates the password.
- **Session TTL.** Tokens expire 8 hours after issue (`AuthService::SESSION_TTL_SECONDS`). Expired tokens return 401.
- **Password change is a session-kill event.** Every live token for the user is revoked.

## Authorization model

Two-layer authorization:

1. **Role → action (RBAC).** `RbacService` maps `booking.cancel.override`, `voucher.create`, `moderation.review`, etc. to role sets.
2. **Role + resource ownership.** `AuthorizationService` enforces object-level access:
   - trainees see only their own bookings and assessment progress
   - supervisors close only sessions they own and can only act on bookings whose underlying session they own
   - guardians see only linked children, and remote-logout only their children's devices
   - employers may *verify* but not *download* arbitrary certificates
   - admins bypass both layers for audit/snapshot/key endpoints

Both layers run on every mutating route.

## Concurrency & idempotency

- **Bookings.** `BookingService::book` runs inside `Database::transactional`, `SELECT ... FOR UPDATE`s the session, and relies on a unique `(session_id, trainee_id, active_tag)` index to block duplicate active bookings. Clients can supply `idempotencyKey` to make retries safe; the unique constraint on `bookings.idempotency_key` catches replays.
- **Reschedule.** `BookingService::reschedule` enforces the same 12-hour window as direct cancellation; admin override is required to override the window and requires the `booking.cancel.override` role.
- **Scheduling.** `SchedulingService::create` and `addLeave` run in transactions and acquire a per-supervisor advisory lock so two concurrent mutations cannot both pass conflict checks. `SupervisorLeave::overlaps` computes exact recurrence occurrences (weekly/monthly) inside the target window — short or boundary-aligned leaves are handled correctly.
- **Voucher claims.** Same pattern: lock the voucher, check `remaining() > 0` + per-user uniqueness, save claim under unique `idempotency_key`. Duplicate key ⇒ return the original claim (idempotent); reused key by a different user ⇒ 409.
- **In-memory tests** exercise the same `Database` interface; the `InMemoryDatabase` driver provides a process-local advisory lock so the call graph is identical to production.

## Audit logging

Every sensitive action writes an `audit_log` row with actor / time / before / after JSON:

```
auth.login, auth.login.fail, auth.role.issue, auth.role.switch, auth.logout,
auth.password.change, system.bootstrap
user.register, profile.update
session.create, session.close, session.leave.add
booking.create, booking.confirm, booking.cancel, booking.cancel.override, booking.expire
voucher.issue, voucher.claim, voucher.redeem, voucher.void, voucher.void_claim
assessment.template.create, assessment.rank.create, assessment.record
moderation.submit, moderation.attach, moderation.approve, moderation.reject
certificate.issue, certificate.revoke
guardian.link, guardian.approve_device, guardian.remote_logout
resource.create, resource.retire, resource.reserve
admin.snapshot.export, admin.storage.tier
keyring.rotate
```

`audit_log` is partitioned monthly (`PARTITION BY RANGE COLUMNS(occurred_at)`). The `bin/console maintain:partitions` job adds the next 6 months of partitions; wire it to cron/systemd daily.

## Encryption at rest

- A 32-byte **KEK** is generated once at `STORAGE_ROOT/keys/kek.bin` with `0600` perms. Do not commit it. Rotate by replacing the file out-of-band.
- Each **DEK** is wrapped by the KEK and stored in `profile_keys`. `Keyring::rotate()` (or `bin/console keys:rotate`) retires the active DEK and generates a new one. Old ciphertext is still decryptable because every envelope carries the DEK version.
- The `User.encryptedProfile` field and all profile PUTs through `/api/profile` run through `ProfileCipher` (AES-256-GCM, 12-byte IV, 16-byte tag).

## Moderation attachments

`POST /api/moderation/{id}/attachments` accepts `filename`, `mimeType`, `contentBase64`. Validations:

- MIME ∈ {`text/plain`, `image/png`, `image/jpeg`, `application/pdf`}
- Size ≤ 5 MB
- Magic bytes match the declared MIME (PNG, JPEG, PDF)
- Filename stripped via `basename()`; path traversal rejected

Stored under `STORAGE_ROOT/uploads/{sha256}-{filename}` with `0600` perms. The SHA-256 doubles as a dedup key.

## Scheduling & leaves

Supervisors own a timetable of `training_sessions` (with overlap + buffer checks) and `supervisor_leaves` (one-off, weekly, or monthly recurrence). Attempting to create a session that overlaps any leave — including recurring occurrences projected forward — returns `409 Conflict`. Adding a leave that conflicts with an existing session is also rejected.

## Snapshot exports

`POST /api/admin/snapshots` (or `bin/console snapshot:export`) writes a timestamped directory under `STORAGE_ROOT/snapshots/YYYYMMDD-HHMMSS/` containing one JSON file per entity section plus a `manifest.json` with SHA-256 checksums. Wire to cron for daily archives.

## PDF certificates

`CertificateService::issue` renders a valid PDF-1.4 landscape certificate using `PdfCertificate` (pure PHP, no external libraries). The file is stored at `STORAGE_ROOT/certs/{verificationCode}.pdf` and served base64-encoded by `GET /api/certificates/{id}/download`.

## Configuration

Environment variables consumed by `Kernel::fromEnv()` and `bin/console`:

| Variable        | Purpose                                                |
|-----------------|--------------------------------------------------------|
| `DB_DSN`        | Full PDO DSN — takes precedence                        |
| `DB_HOST`       | Used if `DB_DSN` unset                                 |
| `DB_NAME`       |                                                        |
| `DB_PORT`       | Optional — used when composing a DSN from parts        |
| `DB_USER`       |                                                        |
| `DB_PASS`       |                                                        |
| `DB_CONNECT_RETRIES` | Boot-time connect attempts (default 5)            |
| `DB_CONNECT_RETRY_MS` | Base delay between attempts, linear backoff (default 200) |
| `STORAGE_ROOT`  | Base dir for certs, uploads, snapshots, keys, hot/cold |
| `KEK_PATH`      | Override path to the KEK file                          |
| `SESSION_COOKIE_SECURE` | `true`/`false` — attach `Secure` flag to the session cookie (Compose sets `false` for plain-HTTP dev; production must be `true`) |
| `DEMO_PASSWORD` | Password assigned to every account created by `seed:demo` (default `Demo!pass-1`) |
| `VITE_API_BASE` | Backend origin the Vite dev server proxies `/api` to (Compose sets `http://app:8080`) |

If `DB_DSN`/`DB_HOST` is unset the kernel falls back to the in-memory database, which is appropriate for tests but not for production.

## Security summary

- Password-proved role issuance (no raw-userId token minting).
- One-shot atomic bootstrap admin enforced by `SystemStateRepository::claim`.
- Per-actor RBAC + object-level ownership checks.
- Bookings and voucher claims run inside DB transactions with row locks + unique constraints.
- Audit log covers auth/session/role/password/assessment/certificate/profile/moderation.
- AES-256-GCM profile encryption with versioned DEKs and KEK on disk.
- Moderation attachments validated by MIME, size, and magic bytes; filenames canonicalised.
- Session TTL + revocation on password change.

## License

Internal project — no external license applied.
