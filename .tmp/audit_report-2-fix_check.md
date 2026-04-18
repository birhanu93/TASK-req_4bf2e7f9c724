# Scoped Fix Check Audit (Static-Only)

## Verdict

- **Scoped verdict: Pass (with minor static test-gap notes)**
- All previously reported material issues were addressed in code and/or docs with traceable static evidence.

## Scope Boundary

- This re-evaluation is strictly scoped to previously reported issues:
  1. Booking reschedule atomicity
  2. Voucher claim idempotency re-check under concurrency
  3. JSON-only mutating request contract mismatch
  4. Supervisor booking-list semantics mismatch
  5. Insufficient DB-backed coverage for rollback/concurrency-sensitive paths
- Static analysis only. No runtime/test/docker execution.

## Prior Issues Re-check

### 1) Reschedule atomicity (previously High)

- **Status:** Fixed
- **What changed:** `BookingService::reschedule()` now wraps cancel+book in an outer transaction, with explicit comments describing rollback guarantees; idempotency replay short-circuit is handled before cancellation to avoid repeat-cancel side effects.
- **Evidence:** `src/Service/BookingService.php:220`, `src/Service/BookingService.php:226`, `src/Service/BookingService.php:235`, `src/Service/BookingService.php:210`
- **Assessment:** The old “cancel first, then potentially fail to rebook” non-atomic path is no longer present.

### 2) Voucher claim idempotency in-lock re-check (previously High)

- **Status:** Fixed
- **What changed:** Claim flow now performs a fast-path replay check and a second idempotency check inside the lock/transaction before user-active claim checks; duplicate insert handling replays the winning row where possible.
- **Evidence:** `src/Service/VoucherService.php:80`, `src/Service/VoucherService.php:91`, `src/Service/VoucherService.php:98`, `src/Service/VoucherService.php:103`, `src/Service/VoucherService.php:138`
- **Assessment:** This addresses the prior race window where same-key retries could incorrectly return non-idempotent conflicts.

### 3) JSON-only mutating contract mismatch (previously Medium)

- **Status:** Fixed
- **What changed:** Request adapter now rejects mutating requests with bodies unless `Content-Type` is JSON (`415 Unsupported Media Type`), and docs were updated to reflect enforcement details (including bodyless mutating exceptions).
- **Evidence:** `src/Http/Request.php:45`, `src/Http/Request.php:57`, `src/Http/Request.php:59`, `src/Exception/UnsupportedMediaTypeException.php:7`, `docs/api.md:6`
- **Assessment:** Previous docs/code mismatch is resolved.

### 4) Supervisor booking-list semantics mismatch (previously Medium)

- **Status:** Fixed
- **What changed:** Supervisor listing now explicitly returns bookings for sessions they own (with optional `traineeId` narrowing), rather than defaulting to trainee-centric behavior.
- **Evidence:** `src/Controller/BookingController.php:72`, `src/Controller/BookingController.php:79`, `src/Controller/BookingController.php:86`, `src/Controller/BookingController.php:99`, `docs/api.md:41`
- **Assessment:** Controller behavior now aligns with documented semantics.

### 5) DB-backed rollback/concurrency coverage gaps (previously Medium)

- **Status:** Fixed (for scoped critical paths)
- **What changed:** New PDO-backed tests were added for:
  - reschedule rollback safety under failed replacement insert / unique collision
  - idempotency race and duplicate insert handling on voucher claims
- **Evidence:** `tests/Unit/PdoRescheduleRollbackSafetyTest.php:34`, `tests/Unit/PdoRescheduleRollbackSafetyTest.php:67`, `tests/Unit/PdoRescheduleRollbackSafetyTest.php:112`, `tests/Unit/PdoVoucherClaimIdempotencyRaceTest.php:32`, `tests/Unit/PdoVoucherClaimIdempotencyRaceTest.php:63`, `tests/Unit/PdoVoucherClaimIdempotencyRaceTest.php:90`
- **Assessment:** The previously missing DB-backed coverage for the exact high-risk defects is now present.

## Residual Notes (Non-blocking)

- **Static-only caveat:** Runtime behavior is still **Manual Verification Required**.
- **Test breadth note:** I found strong new targeted DB-backed tests for the scoped defects; I did not identify equivalent new API-level assertions for `415` enforcement in this pass.

## Final Conclusion

- For the scoped prior findings, the implementation now provides sufficient static evidence of remediation.
