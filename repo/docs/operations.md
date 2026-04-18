# Operations

## Boot-time requirements

`Kernel::fromEnv()` reads these variables:

| Var | Meaning |
|-----|---------|
| `DB_DSN` | Full PDO DSN — takes precedence if set |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | Used to compose a DSN when `DB_DSN` is unset |
| `DB_CONNECT_RETRIES` | Boot-time connect attempts (default 5) |
| `DB_CONNECT_RETRY_MS` | Base delay (ms) between attempts, linear backoff (default 200) |
| `STORAGE_ROOT` | Base for certs, uploads, snapshots, keys, hot/cold tiers |
| `KEK_PATH` | Override path to the KEK file |

On MySQL, the connection runs an init command pinning `utf8mb4`
/`utf8mb4_unicode_ci`, `STRICT_ALL_TABLES`, `NO_ZERO_DATE`, and UTC. The
boot also issues a `SELECT 1` ping so misconfiguration surfaces before the
first request.

## Storage tiering

`App\Service\StorageTieringService` owns three named stores:

| Store | Hot path | Cold path |
|-------|----------|-----------|
| `default` | `STORAGE_ROOT/hot` | `STORAGE_ROOT/cold` |
| `certificates` | `STORAGE_ROOT/certs` | `STORAGE_ROOT/certs-cold` |
| `uploads` | `STORAGE_ROOT/uploads` | `STORAGE_ROOT/uploads-cold` |

`POST /api/admin/storage/tier` sweeps every store, moving any artifact
whose `mtime` is older than `ageDaysThreshold` days (default 180) to the
cold counterpart. `CertificateService::readPdf` and
`ModerationService::readAttachmentBytes` transparently fall back to the
cold path so downstream code is tier-agnostic.

## Resource calendars

Shared assets (training rooms, equipment, vehicles) are modelled as
`resources` with time-bounded reservations in `resource_reservations`.
Session creation accepts a `resourceIds` array; each listed resource is
reserved atomically inside the session's transaction. Overlap on any
named resource aborts the whole create. Admins manage the resource list
at `/admin/resources`; reservations for a resource can be inspected at
`GET /api/resources/{id}/reservations`.

## Audit log partitioning

`audit_log` is `PARTITION BY RANGE COLUMNS(occurred_at)`. Run
`bin/console maintain:partitions` daily (cron/systemd) to add the next six
months of partitions.

## Snapshot exports

`POST /api/admin/snapshots` (or `bin/console snapshot:export`) writes a
timestamped directory under `STORAGE_ROOT/snapshots/YYYYMMDD-HHMMSS/` with
one JSON per entity section plus a `manifest.json` of SHA-256 checksums.

## Key rotation

`POST /api/admin/keys/rotate` (or `bin/console keys:rotate`) retires the
active DEK and generates a new one. Old ciphertext remains decryptable via
the DEK version carried in the envelope.

## Backups and restores

- DB: use your standard MySQL dump — schema is defined by the
  `migrations/` directory (idempotent, re-runnable).
- Storage: archive `STORAGE_ROOT` as a whole; KEK under
  `STORAGE_ROOT/keys/kek.bin` is the crown jewel.
