# Testing

## Running

```bash
vendor/bin/phpunit
```

Everything runs against the in-memory kernel (`Factory::kernel()`); no MySQL
is required. The fixed clock and deterministic KEK keep results stable.

## Suite layout

- `tests/Unit/` — service and entity tests
- `tests/Api/` — HTTP-level tests that exercise the full router + services
- `tests/Support/Factory.php` — in-memory kernel with fixed clock and deterministic KEK

## Notable focus areas

| File | Focus |
|------|-------|
| `tests/Unit/PdoBootstrapTest.php` | PDO construction, retry + fail-fast, nested transactions |
| `tests/Unit/ReschedulePolicyTest.php` | 12-hour window enforced on reschedule; admin override required |
| `tests/Unit/RecurrenceEdgeCasesTest.php` | Exact occurrence math — short leaves, boundaries, monthly rollover |
| `tests/Unit/SchedulingConcurrencyTest.php` | Commit-time locking for scheduling and voucher races |
| `tests/Unit/ConcurrencyTest.php` | Booking capacity + idempotency under contention |
| `tests/Api/AuthorizationIsolationTest.php` | Cross-user leaks (trainee/guardian/supervisor/profile) |
| `tests/Api/BookingCrossOwnershipTest.php` | Supervisor-vs-supervisor booking isolation |
| `tests/Api/EmployerCertificateAccessTest.php` | Employer can verify but cannot download |
| `tests/Api/AssessmentCertCrossTenantTest.php` | Supervisor can only write assessments/certs for trainees they actually work with |
| `tests/Api/ResourceCalendarTest.php` | Resource creation, overlap rejection, retired-resource blocks, non-overlap acceptance |
| `tests/Api/GuardianRemoteLogoutTest.php` | Link → approve device → list → remote-logout → revoked; cross-guardian denial |
| `tests/Unit/PdoTransactionIntegrationTest.php` | Transactional commit/rollback, savepoint nesting, atomic status transition on a real PDO (sqlite) |

**How the API tests run.** `ApiTestCase::call()` builds a
`Symfony\Component\HttpFoundation\Request` and dispatches it through
`App\App\HttpApplication::handle()` — the same entrypoint as production.
Set-Cookie headers emitted by one call are captured and replayed on the
next, so the HttpOnly session-cookie round-trip is exercised end-to-end.

## Writing new tests

Extend `App\Tests\Api\ApiTestCase` for HTTP tests — it exposes `call()`,
`seedAdmin()`, `createUser()`, `loginAs()`. For unit tests, construct a
kernel via `Factory::kernel()` and drive services directly.
