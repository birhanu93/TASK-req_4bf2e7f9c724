# Authorization

Two layers, both enforced on every mutating request.

## Layer 1 — RBAC (role → action)

`App\Service\RbacService` maps action strings (e.g.
`booking.cancel.override`, `moderation.review`, `guardian.remote_logout`) to
the set of roles allowed to invoke them. Unknown actions always fail closed
with 403.

Every protected route goes through `RbacService::authorize` with an
explicit action key — there is no bespoke role comparison hidden inside a
controller. Notable keys added for administrative and read-side paths:

| Action | Allowed roles |
|--------|---------------|
| `admin.snapshot.export` | admin |
| `admin.keys.rotate` | admin |
| `admin.booking.list` | admin |
| `admin.booking.override` | admin |
| `resource.manage` | admin |
| `resource.view` | any authenticated role |
| `profile.read` / `profile.update` | any authenticated role (+ ownership check) |
| `certificate.view.own` | trainee / supervisor / guardian / admin |

## Layer 2 — Object ownership

`App\Service\AuthorizationService` checks that the actor *owns* the specific
resource being mutated. Key rules:

### Assessment writes and certificate issuance

`assertSupervisorActsOnKnownTrainee(ctx, traineeId)` — before a supervisor
records an assessment or issues a certificate, they must already have at
least one booking by that trainee in a session they own. Admins bypass.
Every other role is rejected outright.

### Bookings

`assertBookingOwnership(ctx, booking)`:

| Role | Rule |
|------|------|
| admin | pass |
| trainee | `booking.traineeId == ctx.userId` |
| supervisor | looks up the session; `session.supervisorId == ctx.userId` |
| guardian | `guardianLinks.findLink(ctx.userId, booking.traineeId) != null` |
| other | deny |

The list endpoint (`GET /api/bookings`) applies the same check to filter the
result set so cross-ownership rows never leak.

### Sessions

`assertSupervisorForSession(ctx, supervisorId)` — supervisors may only close
or manage sessions they own. Admins bypass.

### Certificates

`assertCertificateAccess(ctx, traineeId)`:

| Role | Rule |
|------|------|
| admin | pass |
| supervisor | must have at least one booking by the trainee in a session they own |
| trainee | self only |
| guardian | must be linked |
| employer | denied (verify-by-code only) |

### Guardians

`assertChildAccess(ctx, childId)` — requires a guardian link. Used by
device approval, device listing, and remote logout endpoints.

### Profiles

`assertProfileAccess(ctx, userId)` — self or admin.

## Cancellation and reschedule policies

`BookingService::CANCEL_BLOCK_WINDOW_HOURS = 12`. Both cancellation **and
reschedule** enforce a 12-hour cutoff before the session start. The only
escape hatch is `adminOverride=true`, which requires the
`booking.cancel.override` RBAC action (admin role only). Reschedule cannot
bypass the window by forwarding `override` — the controller maps
`override=true` to the admin action and rejects it for non-admin callers.
