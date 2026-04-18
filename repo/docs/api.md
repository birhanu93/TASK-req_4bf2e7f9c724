# HTTP API reference

All endpoints return JSON; `X-Content-Type-Options: nosniff` is set. Bearer
token required unless flagged otherwise.

Request bodies on mutating methods (`POST`, `PUT`, `PATCH`, `DELETE`) must be
sent with `Content-Type: application/json`. A non-JSON content type on a
request that carries a body is rejected with `415 Unsupported Media Type`
before auth or routing runs. Bodyless mutating requests (e.g. confirm /
logout / revoke) do not require a Content-Type header.

## Auth

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/auth/bootstrap` | one-shot admin creation |
| POST | `/api/auth/register` | admin-only |
| POST | `/api/auth/login` | returns `userId` + `availableRoles` |
| POST | `/api/auth/select-role` | password + role ⇒ token |
| POST | `/api/auth/switch-role` | new role token with password re-proof |
| POST | `/api/auth/change-password` | revokes existing sessions |
| POST | `/api/auth/logout` | |
| GET  | `/api/auth/me` | |

## Sessions & scheduling

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/sessions` | supervisor/admin |
| GET  | `/api/sessions` | |
| POST | `/api/sessions/{id}/close` | supervisor-owner/admin |
| GET  | `/api/sessions/{id}/availability` | |
| POST | `/api/sessions/leaves` | supervisor/admin |
| GET  | `/api/sessions/leaves` | |

## Bookings

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/bookings` | trainee; idempotency via `idempotencyKey` |
| GET  | `/api/bookings` | trainee self / supervisor own sessions / admin |
| GET  | `/api/bookings/{id}` | access filtered by `assertBookingOwnership` |
| POST | `/api/bookings/{id}/confirm` | |
| POST | `/api/bookings/{id}/cancel` | 12-hour window unless `override=true` (admin); `reason` required; overrides always carry an audit row with the reason |
| POST | `/api/bookings/{id}/reschedule` | same 12-hour rule as cancel; `override=true` (admin) required to bypass; `reason` is required when `override=true` |
| GET  | `/api/admin/bookings` | admin — optional `traineeId`/`sessionId`/`status` filters for administrative search |

## Assessments

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/assessments/templates` | supervisor/admin |
| POST | `/api/assessments/ranks` | supervisor/admin |
| POST | `/api/assessments` | supervisor/admin |
| GET  | `/api/assessments/progress/{traineeId}` | trainee self / supervisor with prior booking / guardian linked / admin |

## Vouchers

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/vouchers` | admin |
| GET  | `/api/vouchers/{code}` | describe (code, discount, min spend, remaining, status) |
| POST | `/api/vouchers/claims` | trainee/employer; idempotent via `idempotencyKey` |
| POST | `/api/vouchers/claims/{id}/redeem` | enforces `orderAmountCents ≥ minSpendCents` |
| POST | `/api/vouchers/claims/{id}/void` | |
| POST | `/api/vouchers/{id}/void` | admin |

## Moderation

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/moderation` | submit |
| POST | `/api/moderation/{id}/attachments` | attach file (MIME + magic bytes + size) |
| POST | `/api/moderation/{id}/approve` | admin |
| POST | `/api/moderation/{id}/reject` | admin, reason required |
| POST | `/api/moderation/bulk-approve` | admin, `{ ids, score }` |
| POST | `/api/moderation/bulk-reject` | admin, `{ ids, reason }` |
| GET  | `/api/moderation/pending` | admin |

## Guardians

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/guardians/links` | admin creates links, or guardian links own child |
| GET  | `/api/guardians/children` | guardian lists linked children |
| GET  | `/api/guardians/children/{childId}/progress` | guardian only for linked children |
| GET  | `/api/guardians/children/{childId}/devices` | guardian only for linked children |
| POST | `/api/guardians/devices` | approve a child's device |
| POST | `/api/guardians/devices/{id}/logout` | remote logout a device |

## Certificates

| Method | Path | Notes |
|--------|------|-------|
| POST | `/api/certificates` | supervisor must have a prior booking by this trainee in a session they own; admin bypasses |
| GET  | `/api/certificates` | admin — full list |
| GET  | `/api/certificates/mine` | current user — their own certificates |
| GET  | `/api/certificates/verify/{code}` | verify-by-code (trainee/supervisor/admin/employer) |
| POST | `/api/certificates/{id}/revoke` | admin |
| GET  | `/api/certificates/{id}/download` | trainee self / supervisor with prior session / guardian linked / admin — **employers are denied** |

## Resources (shared calendars)

| Method | Path | Notes |
|--------|------|-------|
| GET  | `/api/resources` | any authenticated user |
| POST | `/api/resources` | admin — `{ name, kind }`; unique name |
| POST | `/api/resources/{id}/retire` | admin |
| GET  | `/api/resources/{id}/reservations` | any authenticated user |

Session create accepts `resourceIds: string[]` — each id is atomically
reserved for the session's `[startsAt, endsAt)` window inside the same
transaction. Overlap on any listed resource fails the entire create with
409.

## Profile

| Method | Path | Notes |
|--------|------|-------|
| GET  | `/api/profile` | |
| PUT  | `/api/profile` | AES-256-GCM encrypted at rest |

## Admin

| Method | Path | Notes |
|--------|------|-------|
| GET  | `/api/admin/audit/{type}/{id}` | |
| POST | `/api/admin/storage/tier` | move aged hot artifacts to cold tier (default, certificates, uploads) |
| POST | `/api/admin/snapshots` | write a JSON snapshot export |
| POST | `/api/admin/keys/rotate` | rotate profile DEK |
