# Workforce Training & Operations Hub - API Specification

## API style and base contract

- **Protocol:** HTTP/1.1 over internal LAN.
- **Base URL:** `/api/v1`.
- **Content type:** `application/json` (except file download endpoints).
- **Auth:** Session cookie (`HttpOnly`, `SameSite=Strict`) + CSRF token for mutating requests.
- **Time format:** ISO 8601 with offset (example: `2026-04-18T14:30:00-05:00`).
- **Currency format:** integer cents (`amount_cents`).

## RBAC model

Roles:
- `trainee`
- `supervisor`
- `guardian`
- `employer`
- `admin`

Authorization:
- Route-level middleware ensures role access.
- Action-level policy checks validate resource-scoped permissions (ownership, override authority, moderation authority, voucher authority).

## Error model

Error response shape:

```json
{
  "error": {
    "code": "BOOKING_POLICY_BLOCKED",
    "message": "Cancellation within 12 hours requires admin override.",
    "details": {
      "booking_id": "bk_123",
      "policy_window_hours": 12
    },
    "request_id": "req_2f8a..."
  }
}
```

Common error codes:
- `AUTH_INVALID_CREDENTIALS`
- `AUTH_ACCOUNT_LOCKED`
- `AUTH_ROLE_NOT_ALLOWED`
- `VALIDATION_FAILED`
- `BOOKING_CONFLICT`
- `BOOKING_HOLD_EXPIRED`
- `BOOKING_POLICY_BLOCKED`
- `ASSESSMENT_TEMPLATE_INVALID`
- `VOUCHER_NOT_APPLICABLE`
- `VOUCHER_LIMIT_REACHED`
- `VOUCHER_ALREADY_REDEEMED`
- `MODERATION_DECISION_REQUIRED_REASON`
- `CONCURRENCY_RETRY_REQUIRED`

## Versioning strategy

- URI versioning via `/api/v1`.
- Backward-compatible additions allowed in minor releases (new optional fields).
- Breaking changes require `/api/v2`.
- Responses include `schema_version` where payload evolution is expected (`assessment`, `certificate`, `voucher` views).

## Authentication and session APIs

### `POST /auth/login`

Request:

```json
{
  "username": "jsmith",
  "password": "********"
}
```

Response:

```json
{
  "account_id": "acc_001",
  "available_roles": ["trainee", "guardian"],
  "default_role": null
}
```

Validation:
- Username/password required.
- Account lockout policy enforced.

### `POST /auth/select-role`

Request:

```json
{
  "role": "trainee"
}
```

Response:

```json
{
  "active_role": "trainee",
  "dashboard": {
    "menus": ["sessions", "assessments", "certificates"],
    "actions": ["booking.create", "booking.cancel.self"]
  }
}
```

### `POST /auth/logout`
- Invalidates current session.

### `POST /auth/logout-device`
- Guardian/admin remote session revocation.

Request:

```json
{
  "session_id": "ses_abc123",
  "reason": "Device reported lost"
}
```

## Scheduling and booking APIs

Slot states:
- `available`
- `reserved`
- `booked`
- `blocked`
- `expired`

Booking states:
- `pending_confirmation`
- `confirmed`
- `cancelled`
- `rescheduled`
- `expired`

### `GET /sessions`
Query sessions/offerings with filters:
- `date_from`, `date_to`, `location_id`, `skill_track`, `role_eligibility`.

### `GET /slots`
Returns slot availability derived from calendars, recurrence, leave, and buffer rules.

Query params:
- `session_id` (required)
- `date`
- `timezone`

Response excerpt:

```json
{
  "session_id": "sess_1001",
  "buffer_minutes": 10,
  "slots": [
    {
      "slot_id": "slot_1",
      "start_at": "2026-04-19T09:00:00-05:00",
      "end_at": "2026-04-19T09:45:00-05:00",
      "state": "available",
      "capacity_remaining": 3
    }
  ]
}
```

### `POST /bookings/reserve`
Creates a temporary hold (15 minutes).

Request:

```json
{
  "slot_id": "slot_1"
}
```

Response:

```json
{
  "booking_id": "bk_123",
  "state": "pending_confirmation",
  "hold_expires_at": "2026-04-19T08:15:00-05:00"
}
```

Validation and concurrency:
- `SELECT ... FOR UPDATE` on slot row.
- Reject if slot not `available` or capacity exhausted.

### `POST /bookings/{booking_id}/confirm`
Confirms held booking if hold is still valid.

### `POST /bookings/{booking_id}/cancel`

Request:

```json
{
  "reason": "Schedule conflict"
}
```

Policy:
- Cancellation inside 12 hours blocked for non-admin.
- Admin override endpoint allows override with mandatory reason.

### `POST /bookings/{booking_id}/admin-override-cancel`

Request:

```json
{
  "override_reason": "Medical emergency exception"
}
```

### `POST /bookings/{booking_id}/reschedule`

Request:

```json
{
  "new_slot_id": "slot_99",
  "reason": "Shift change"
}
```

Behavior:
- Atomic transition under single DB transaction.
- Old booking marked `rescheduled`; new booking created `confirmed`.

## Assessment, rank, and certificate APIs

Assessment template types:
- `time_based`
- `rep_based`
- `combined`

### `POST /assessments/templates` (supervisor/admin)
Create/update template with metric definitions.

Request excerpt:

```json
{
  "name": "Forklift Basic Level 1",
  "type": "combined",
  "time_limit_seconds": 900,
  "rep_target": 20,
  "pass_rules": {
    "min_rep_accuracy_pct": 90,
    "max_time_seconds": 840
  }
}
```

### `POST /assessments/runs`
Start an assessment run for a trainee.

### `POST /assessments/runs/{run_id}/events`
Submit progress events in near real time.

Request:

```json
{
  "event_type": "rep_completed",
  "value": 1,
  "recorded_at": "2026-04-18T14:35:18-05:00"
}
```

### `GET /trainees/{trainee_id}/rank-progress`
Returns current rank and delta to next rank.

### `POST /certificates/generate`
Generates printable PDF and verification code.

Response:

```json
{
  "certificate_id": "cert_200",
  "verification_code": "WFH-26-4F8A9C",
  "status": "active",
  "pdf_path": "/artifacts/hot/certificates/2026/04/cert_200.pdf"
}
```

### `GET /certificates/{certificate_id}/pdf`
Returns PDF bytes (`application/pdf`).

### `GET /certificates/verify/{verification_code}`
Returns certificate status and summary metadata.

## Guardian and device-control APIs

### `POST /guardians/{guardian_id}/children/link`

Request:

```json
{
  "child_account_id": "acc_child_01"
}
```

Validation:
- Max 5 linked children per guardian.
- Child cannot be linked to unauthorized guardian.

### `GET /guardians/{guardian_id}/children`
List linked children and approved device counts.

### `POST /children/{child_id}/approved-devices`
Add approved device.

### `DELETE /children/{child_id}/approved-devices/{device_id}`
Remove approved device and optionally revoke active session.

### `POST /children/{child_id}/sessions/remote-logout`
Forces logout across one or all active child sessions.

## Moderation APIs

Moderation states:
- `pending_auto_check`
- `pending_manual_review`
- `approved`
- `rejected`

### `POST /moderation/items`
Upload user-generated content metadata (binary handled via multipart endpoint if needed).

Automated checks:
- Allowed file types.
- File size limits.
- Checksum duplicate detection.
- Prohibited-word scan in text payloads.

### `GET /moderation/queue`
Filter by state, content type, assignee, age.

### `POST /moderation/decisions/bulk`

Request:

```json
{
  "item_ids": ["ugc_1", "ugc_2", "ugc_3"],
  "decision": "rejected",
  "reason": "Inappropriate language",
  "quality_score": 20
}
```

Validation:
- `reason` required for rejections.
- Bulk size capped (for example, max 200 items/request).

## Voucher APIs

Voucher states:
- `issued`
- `claimed`
- `locked`
- `redeemed`
- `void`
- `expired`

### `POST /vouchers`
Admin creates voucher policy.

Request:

```json
{
  "code": "CERT25",
  "discount_type": "fixed_amount",
  "discount_cents": 2500,
  "minimum_spend_cents": 15000,
  "max_claims": 100,
  "expires_at": "2026-12-31T23:59:59-06:00",
  "applies_to": ["certification_fee"]
}
```

### `POST /vouchers/{code}/claim`
Claims voucher for an account if eligible and within limit.

### `POST /vouchers/{code}/lock`
Locks voucher during checkout attempt.

### `POST /vouchers/{code}/redeem`

Headers:
- `Idempotency-Key: <uuid>`

Request:

```json
{
  "order_id": "ord_500",
  "account_id": "acc_001",
  "subtotal_cents": 18000,
  "line_items": [
    { "type": "certification_fee", "amount_cents": 18000 }
  ]
}
```

Response:

```json
{
  "voucher_code": "CERT25",
  "state": "redeemed",
  "discount_applied_cents": 2500,
  "final_total_cents": 15500
}
```

Concurrency controls:
- Row-level lock on voucher and claim counters.
- Idempotency table keyed by (`idempotency_key`, `account_id`, `endpoint`).

### `POST /vouchers/{code}/void`
Admin-only void with reason and audit event.

## Audit APIs

### `GET /audit/events`
Filters:
- `actor_id`, `entity_type`, `entity_id`, `action`, `date_from`, `date_to`

Response fields:
- `timestamp`, `actor`, `action`, `entity_ref`, `before_json`, `after_json`, `source_ip`

### `GET /audit/events/{event_id}`
Detailed event view for traceability.

## Artifact lifecycle and reporting APIs

### `POST /artifacts/tiering/run`
Runs hot->cold move logic for artifacts older than 180 days.

### `POST /reports/snapshots/export`
Generates local snapshot exports for operational reports.

### `GET /reports/snapshots`
Lists generated snapshots and file paths.

## Validation constraints summary

- Guardian child links: max 5.
- Booking hold timeout: 15 minutes.
- Default scheduling buffer: 10 minutes.
- Cancellation policy block: inside 12 hours unless admin override.
- Voucher redemption requires applicable item + minimum spend + non-expired + claim limits not exceeded.
- Moderation decision requires reason on reject and for bulk action traceability.
- All sensitive mutations emit audit events with before/after payloads.

## Critical flow examples

### Example: booking hold expires and slot auto-releases
1. Trainee reserves slot at 10:00 -> hold expires at 10:15.
2. No confirmation by 10:15.
3. Next read/write touching slot or scheduler tick marks booking `expired` and slot `available`.

### Example: concurrent voucher redemption
1. Two checkout requests hit `/vouchers/CERT25/redeem` simultaneously.
2. First transaction acquires voucher row lock and redeems.
3. Second transaction either:
   - Returns idempotent prior result (same idempotency key), or
   - Fails with `VOUCHER_LIMIT_REACHED`/`VOUCHER_ALREADY_REDEEMED` based on policy and ownership.
