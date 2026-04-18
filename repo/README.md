# Workforce Training & Operations Hub

Multi-role web application for managing training sessions, bookings, assessments, vouchers, moderation, guardian oversight, and certificates.

- **Backend**: PHP 8.2+, Symfony HTTP stack (HttpFoundation, HttpKernel, Routing, DependencyInjection), MySQL 8 persistence (with in-memory fallback for tests).
- **Frontend**: Vite + React + TypeScript, one SPA with five role portals (trainee / supervisor / guardian / employer / admin).
- **Auth**: password-proved role tokens with 8-hour TTL, persistent session store, one-time bootstrap admin.
- **Crypto**: AES-256-GCM profile encryption with a local, versioned keyring (DEK + KEK).

## Repository layout

Paths are relative to the repository root. Backend code lives at the top
level (no `backend/` subdirectory); the React SPA is under `frontend/`.

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
bin/console          CLI: migrate, snapshot:export, keys:rotate, tiering:run, maintain:partitions
migrations/          SQL schema migrations (idempotent, numbered)
tests/               PHPUnit tests: Unit/, Api/, Support/
frontend/            Vite + React + TypeScript SPA
  src/routes/        Role portals (trainee, supervisor, guardian, employer, admin)
  src/lib/           API client + auth state
  src/components/    Shell (sidebar + topbar)
docs/                Architecture, API, authorization, operations, testing guides
Dockerfile           PHP 8.2 + pdo_mysql + pcov image
docker-compose.yml   Local MySQL + app containers
run_tests.sh         One-shot runner: builds the tests image and runs phpunit
```

## Quick start (local, without Docker)

All commands run from the repository root unless noted.

```bash
# 1. Install PHP deps (vendor/ populated at the repo root)
composer install

# 2. Run the test suite against the in-memory kernel (no DB needed)
vendor/bin/phpunit

# 3. Start a live MySQL (via Docker) and run migrations
docker compose up -d db
DB_HOST=127.0.0.1 DB_USER=workforce DB_PASS=workforce DB_NAME=workforce \
  php bin/console migrate

# 4. Serve the API (document root is public/, index.php acts as front controller)
php -S 127.0.0.1:8080 -t public public/index.php

# 5. Launch the frontend (from frontend/, proxies /api back to :8080)
cd frontend
npm install
npm run dev   # http://localhost:5173
```

## First-time bootstrap

The backend refuses to issue any tokens until a bootstrap admin exists. Create one via the dedicated endpoint — this is a **one-shot** operation enforced by a unique marker in `system_state`.

```bash
curl -X POST http://localhost:8080/api/auth/bootstrap \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"choose-a-strong-password"}'
```

Replay attempts return `409 Conflict`. The legacy `roles: ["admin"], bootstrap: true` payload on `/api/auth/register` has been removed.

From the React app, visit the sign-in page and use **First-time setup**.

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

`booking.cancel.override` rows carry the override reason in the `after`
payload — admin cancel/reschedule flows require that reason before the
policy window is bypassed. `admin.snapshot.export` records the written
path and manifest; `admin.storage.tier` records the before/after store
snapshot plus the list of moved artifacts; `resource.reserve` records the
reservation id and the bound session.

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

## Running the tests

```bash
# full suite (in-memory)
vendor/bin/phpunit

# targeted suites
vendor/bin/phpunit tests/Api/SecurityBootstrapTest.php
vendor/bin/phpunit tests/Api/TokenAbuseTest.php
vendor/bin/phpunit tests/Api/AuthorizationIsolationTest.php
vendor/bin/phpunit tests/Api/BookingCrossOwnershipTest.php
vendor/bin/phpunit tests/Api/EmployerCertificateAccessTest.php
vendor/bin/phpunit tests/Unit/ConcurrencyTest.php
vendor/bin/phpunit tests/Unit/PdoBootstrapTest.php
vendor/bin/phpunit tests/Unit/InterfaceWiringTest.php
vendor/bin/phpunit tests/Unit/ReschedulePolicyTest.php
vendor/bin/phpunit tests/Unit/RecurrenceEdgeCasesTest.php
vendor/bin/phpunit tests/Unit/SchedulingConcurrencyTest.php
vendor/bin/phpunit tests/Unit/EncryptionKeyringTest.php
vendor/bin/phpunit tests/Unit/ModerationAttachmentTest.php
```

The test kernel uses `Factory::kernel()` — fixed clock, deterministic KEK, fresh temp storage per test. No MySQL required.

For deeper reading see `docs/architecture.md`, `docs/api.md`,
`docs/authorization.md`, `docs/auth.md` (session-cookie design),
`docs/operations.md`, and `docs/testing.md`.

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
| `SESSION_COOKIE_SECURE` | `true`/`false` — attach `Secure` flag to the session cookie (default true; disable only for plain-HTTP dev) |

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
