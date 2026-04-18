# Workforce Training & Operations Hub - System Design

## Overview

The system is an internal-network workforce training platform for a US-based organization. It provides role-specific React workspaces for trainees, supervisors, guardians, employers, and administrators, backed by Symfony REST APIs and MySQL. The platform handles secure local authentication, role-based operations, booking/scheduling, assessments and rank progress, certificate generation and verification, moderation workflows, and controlled voucher issuance/redemption with strict auditability.

## Goals and non-goals

### Goals
- Enforce strict RBAC at route and action levels across all role workspaces.
- Support deterministic scheduling with conflict-safe booking, hold expiration (15 minutes), buffer enforcement (default 10 minutes), recurrence, and leave exceptions.
- Deliver configurable assessments (time-based, rep-based, combined) with real-time trainee progress and certificate lifecycle/status tracking.
- Enable guardian controls for youth programs (up to 5 linked children, approved devices, remote logout).
- Provide admin-grade moderation with bulk decisions, reason capture, and immutable audit history.
- Implement voucher lifecycle controls (`claim`, `lock`, `redeem`, `void`) with idempotent redemption and concurrency safety.
- Operate fully offline on local LAN with local persistence, storage, and operational reporting.

### Non-goals
- No cloud identity, cloud scanning, or third-party moderation services.
- No internet-dependent background workers or managed queues.
- No mobile-native client in v1 (web only).

## Architecture

- **Frontend:** React SPA with role-scoped modules and shared design system.
- **Backend:** Symfony API application exposing REST-style endpoints over internal LAN.
- **Database:** MySQL for transactional entities and audit/log records.
- **File storage:** Local filesystem for PDFs, attachments, evidence snapshots, and exports with hot/cold tiers.
- **Security boundary:** LAN-only deployment with server-side RBAC enforcement on every protected operation.

High-level components:
1. **Identity and Access Service**
   - Username/password verification (local DB).
   - Password hashing using Argon2id (preferred) or bcrypt fallback.
   - Session issuance, role-scoped context, and logout/session invalidation.
2. **Scheduling Service**
   - Slot generation from staff/resource calendars with recurrence and ad-hoc leave.
   - Booking hold/reserve/confirm/cancel/reschedule state transitions.
   - Transactional conflict checks and row-level locking.
3. **Assessment and Rank Service**
   - Assessment template configuration and assignment.
   - Metric ingestion and rank progression computation.
   - Certificate generation as printable PDF with verification code and lifecycle status.
4. **Guardian Device Service**
   - Guardian-child relationship management (max 5 children/guardian).
   - Child approved-device registry.
   - Remote session revocation for lost devices.
5. **Moderation Service**
   - Automated pre-checks (type, size, checksum duplicate, prohibited words).
   - Manual queue, scoring, and bulk moderation decisions.
6. **Voucher Service**
   - Voucher creation and eligibility policy definitions.
   - Claim/lock/redeem/void transitions with idempotency keys.
7. **Audit and Logging Service**
   - Sensitive operation audit records with actor, timestamp, before/after snapshots.
   - Monthly partitioned high-volume logs for offline query performance.
8. **Artifact Lifecycle Service**
   - Hot/cold tier movement (older than 180 days -> cold path).
   - Scheduled local snapshot exports for reporting.

## Data model

Core entities (MySQL):
- `accounts`: local identity, password hash, status flags, lockouts.
- `roles` and `account_roles`: role assignment and activation.
- `sessions`: authenticated sessions, device metadata, revocation state.
- `guardian_child_links`: guardian-to-child mapping (max five children per guardian).
- `approved_devices`: child-approved devices and trust metadata.
- `training_sessions`: offerings, capacity, eligibility, policies.
- `staff_calendars`, `resource_calendars`, `calendar_rules`, `leave_rules`.
- `time_slots`: generated slot instances with lifecycle state.
- `bookings`: reservation/confirmation/cancellation history.
- `assessment_templates`, `assessment_runs`, `assessment_metrics`.
- `rank_levels`, `rank_progress`.
- `certificates`: verification code, status (`active`, `revoked`, `expired`), PDF path.
- `ugc_items`: session notes, portfolio evidence, feedback artifacts.
- `moderation_decisions`: decision, score, reasons, reviewer metadata.
- `vouchers`, `voucher_claims`, `voucher_redemptions`.
- `audit_events`: sensitive operation trail with before/after JSON.
- `system_logs_YYYY_MM`: partitioned monthly operational logs.

Sensitive data handling:
- PII/credential-verification artifacts encrypted at rest using local key material.
- Password hashes never reversible; no plain-text storage.

## Core flows

1. **Authentication and role workspace**
   - User submits username/password -> backend verifies hash and account state.
   - Role-scoped session is issued and dashboard payload includes authorized menus/actions only.
2. **Session booking**
   - Trainee views slots with availability and buffer constraints.
   - `reserve` places temporary hold (15-minute expiry).
   - `confirm` finalizes booking; timeout auto-release reverts to `available`.
   - `cancel` within 12 hours is blocked unless admin override with reason.
3. **Reschedule**
   - Existing confirmed booking transitioned atomically (`old -> cancelled/replaced`, `new -> confirmed`) under conflict checks.
4. **Assessment and rank progression**
   - Supervisor runs configured template.
   - Real-time metric updates compute progress toward next rank.
   - Completion can issue certificate PDF with verification code/status.
5. **Guardian controls**
   - Guardian links child accounts (enforced max 5).
   - Guardian manages approved devices and can force remote logout.
6. **Moderation**
   - UGC enters automated pre-check pipeline.
   - Items routed to manual queue with scoring and approve/reject decisions.
   - Bulk operations require reason capture and produce audit events.
7. **Voucher redemption**
   - Voucher claimed/locked during checkout.
   - Redeem uses idempotency key and row lock to prevent double redemption.
   - Expiration, limits, and applicability evaluated before final charge adjustment.

## Security and privacy considerations

- Server-side RBAC is authoritative; UI authorization is advisory only.
- Action-level permission checks on sensitive endpoints (override cancellations, moderation bulk actions, voucher voids).
- Account security controls: lockout/backoff, password policy, session revocation.
- Audit immutable writes for sensitive mutations with before/after values.
- Encryption at rest for sensitive verification artifacts with local key access restricted to backend runtime user.
- LAN-only assumptions still require CSRF protection, secure cookies, and input validation.
- File upload controls: MIME/type/size whitelist, checksum de-duplication, and path traversal defenses.

## Performance and scalability constraints

- Conflict-safe booking and voucher redemption rely on transaction boundaries and indexed lock targets.
- Monthly partitioning for high-volume logs keeps read latency predictable.
- Slot querying optimized by date-range and resource/staff indexes.
- Hot/cold artifact storage avoids high-cost scans in active directories.
- Real-time rank progress updates target sub-second recomputation for active assessments.

## Reliability and failure handling

- Core state transitions are transactional and idempotent where replay risk exists.
- Timeout release of reserved slots handled by DB-driven timeout checks on access/write paths and periodic in-process scheduler (no external service dependency).
- Voucher redemption retries use idempotency keys to return consistent results after transient failures.
- Artifact operations use write-then-commit metadata patterns to avoid dangling pointers.
- Snapshot exports are append-only and resumable when interrupted.

## Observability and analytics

- Structured logs with request IDs and actor IDs for traceability.
- Audit viewer for sensitive operation histories and before/after diffs.
- Metrics:
  - Booking conversion rate and timeout release counts.
  - Assessment completion and rank progression throughput.
  - Moderation queue age and decision turnaround.
  - Voucher issuance/claim/redeem/void rates and rejection reasons.
- Scheduled local reporting exports for offline analysis.

## Deployment/runtime assumptions

- Single internal LAN deployment (no public ingress).
- Symfony API and React static assets served from internal infrastructure.
- MySQL reachable on local network with regular local backups.
- Local filesystem has separate hot and cold mount points and backup policy.
- Time source synchronized via internal NTP to ensure policy windows (12-hour cancellation, expirations) are consistent.
