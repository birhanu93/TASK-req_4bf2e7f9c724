# Test Coverage Audit

## Project Type Detection

- README does **not** explicitly declare `backend|fullstack|web|android|ios|desktop` as a top-level type label.
- Light inspection indicates **fullstack**:
  - Backend routes and controllers in `src/App/Kernel.php`, `src/Controller/*`.
  - Frontend SPA in `frontend/src/*` and `frontend/package.json`.
- Strict-mode inferred type: **fullstack**.

## Backend Endpoint Inventory

Source of truth: route registrations in `src/App/Kernel.php` (`registerRoutes()`).

1. `POST /api/auth/bootstrap`
2. `POST /api/auth/register`
3. `POST /api/auth/login`
4. `POST /api/auth/select-role`
5. `POST /api/auth/switch-role`
6. `POST /api/auth/logout`
7. `POST /api/auth/change-password`
8. `GET /api/auth/me`
9. `POST /api/sessions`
10. `GET /api/sessions`
11. `POST /api/sessions/{id}/close`
12. `GET /api/sessions/{id}/availability`
13. `POST /api/sessions/leaves`
14. `GET /api/sessions/leaves`
15. `POST /api/bookings`
16. `GET /api/bookings`
17. `GET /api/bookings/{id}`
18. `POST /api/bookings/{id}/confirm`
19. `POST /api/bookings/{id}/cancel`
20. `POST /api/bookings/{id}/reschedule`
21. `POST /api/assessments/templates`
22. `POST /api/assessments/ranks`
23. `GET /api/assessments/ranks`
24. `POST /api/assessments`
25. `GET /api/assessments/progress/{traineeId}`
26. `POST /api/vouchers`
27. `GET /api/vouchers`
28. `GET /api/vouchers/{code}`
29. `POST /api/vouchers/claims`
30. `POST /api/vouchers/claims/{id}/redeem`
31. `POST /api/vouchers/claims/{id}/void`
32. `POST /api/vouchers/{id}/void`
33. `POST /api/moderation`
34. `POST /api/moderation/{id}/attachments`
35. `POST /api/moderation/{id}/approve`
36. `POST /api/moderation/{id}/reject`
37. `POST /api/moderation/bulk-approve`
38. `POST /api/moderation/bulk-reject`
39. `GET /api/moderation/pending`
40. `POST /api/guardians/links`
41. `POST /api/guardians/devices`
42. `POST /api/guardians/devices/{id}/logout`
43. `GET /api/guardians/children`
44. `GET /api/guardians/children/{childId}/progress`
45. `GET /api/guardians/children/{childId}/devices`
46. `POST /api/certificates`
47. `GET /api/certificates`
48. `GET /api/certificates/mine`
49. `GET /api/certificates/verify/{code}`
50. `POST /api/certificates/{id}/revoke`
51. `GET /api/certificates/{id}/download`
52. `GET /api/profile`
53. `PUT /api/profile`
54. `GET /api/resources`
55. `POST /api/resources`
56. `POST /api/resources/{id}/retire`
57. `GET /api/resources/{id}/reservations`
58. `GET /api/admin/bookings`
59. `GET /api/admin/audit/{type}/{id}`
60. `POST /api/admin/storage/tier`
61. `POST /api/admin/snapshots`
62. `POST /api/admin/keys/rotate`

## API Test Mapping Table

Test transport evidence (real HTTP layer): `tests/Api/ApiTestCase.php::call()` builds `SymfonyRequest` and dispatches via `HttpApplication::handle()` -> kernel router.

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| `POST /api/auth/bootstrap` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/SecurityBootstrapTest.php` | `testBootstrapCreatesFirstAdmin`, `testReplayWithSamePayloadBlocked` |
| `POST /api/auth/register` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/SecurityBootstrapTest.php` | `testAdminCreatesUser`, `testRegisterDoesNotBypassBootstrap` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/SensitiveDataLeakageTest.php` | `testLoginFlow`, `testAuthResponsesDoNotLeakPasswordHash` |
| `POST /api/auth/select-role` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/TokenAbuseTest.php` | `testLoginFlow`, `testSelectRoleWithWrongPasswordRejected` |
| `POST /api/auth/switch-role` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/TokenAbuseTest.php` | `testLoginFlow`, `testSwitchRoleWithoutPasswordRejected` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/TokenAbuseTest.php` | `testLoginFlow`, `testLogoutInvalidatesToken` |
| `POST /api/auth/change-password` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php`, `tests/Api/TokenAbuseTest.php` | `testChangePassword`, `testPasswordChangeRevokesExistingSessions` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `tests/Api/TokenAbuseTest.php`, `tests/Api/SensitiveDataLeakageTest.php` | `testForgedTokenRejected`, `testAuthResponsesDoNotLeakPasswordHash` |
| `POST /api/sessions` | yes | true no-mock HTTP | multiple API suites | `tests/Api/SessionApiTest.php::testSupervisorCreatesSessionAndTraineeBooks` |
| `GET /api/sessions` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks`, `testListRequiresAuth` |
| `POST /api/sessions/{id}/close` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testSupervisorCreatesSessionAndTraineeBooks`, `testSupervisorCannotCloseAnotherSupervisorsSession` |
| `GET /api/sessions/{id}/availability` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks` |
| `POST /api/sessions/leaves` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `GET /api/sessions/leaves` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `POST /api/bookings` | yes | true no-mock HTTP | multiple API suites | `tests/Api/BookingApiTest.php::testBookConfirmCancel` |
| `GET /api/bookings` | yes | true no-mock HTTP | `tests/Api/BookingCrossOwnershipTest.php` | `testSupervisorListBookingsHidesOtherSupervisorsBookings` |
| `GET /api/bookings/{id}` | yes | true no-mock HTTP | `tests/Api/BookingCrossOwnershipTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testSupervisorCannotViewBookingOnAnotherSupervisorsSession` |
| `POST /api/bookings/{id}/confirm` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testBookConfirmCancel` |
| `POST /api/bookings/{id}/cancel` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testBookConfirmCancel`, `testTraineeCannotSeeAnotherTraineesBooking` |
| `POST /api/bookings/{id}/reschedule` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testReschedule` |
| `POST /api/assessments/templates` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php`, `tests/Api/AssessmentCertCrossTenantTest.php` | `testFlow` |
| `POST /api/assessments/ranks` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php` | `testFlow` |
| `GET /api/assessments/ranks` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `POST /api/assessments` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php`, `tests/Api/AssessmentCertCrossTenantTest.php` | `testFlow` |
| `GET /api/assessments/progress/{traineeId}` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testFlow`, `testTraineeCannotReadAnotherTraineesAssessmentProgress` |
| `POST /api/vouchers` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `GET /api/vouchers` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `GET /api/vouchers/{code}` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims/{id}/redeem` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims/{id}/void` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testVoidClaimAndVoucher` |
| `POST /api/vouchers/{id}/void` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testVoidClaimAndVoucher` |
| `POST /api/moderation` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/{id}/attachments` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `POST /api/moderation/{id}/approve` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/{id}/reject` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/bulk-approve` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testBulkEndpoints` |
| `POST /api/moderation/bulk-reject` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testBulkEndpoints` |
| `GET /api/moderation/pending` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/guardians/links` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php`, `tests/Api/GuardianRemoteLogoutTest.php` | `testLinkApproveRevoke`, `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `POST /api/guardians/devices` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php`, `tests/Api/GuardianRemoteLogoutTest.php` | `testLinkApproveRevoke`, `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `POST /api/guardians/devices/{id}/logout` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php`, `tests/Api/GuardianRemoteLogoutTest.php` | `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `GET /api/guardians/children` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php` | `testLinkApproveRevoke` |
| `GET /api/guardians/children/{childId}/progress` | yes | true no-mock HTTP | `tests/Api/AuthorizationIsolationTest.php` | `testGuardianCanOnlySeeLinkedChildrenProgress` |
| `GET /api/guardians/children/{childId}/devices` | yes | true no-mock HTTP | `tests/Api/GuardianRemoteLogoutTest.php` | `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `POST /api/certificates` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php`, `tests/Api/AssessmentCertCrossTenantTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/certificates` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `GET /api/certificates/mine` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `GET /api/certificates/verify/{code}` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php`, `tests/Api/EmployerCertificateAccessTest.php` | `testIssueVerifyDownloadRevoke`, `testEmployerCanVerifyButNotDownload` |
| `POST /api/certificates/{id}/revoke` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/certificates/{id}/download` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php`, `tests/Api/EmployerCertificateAccessTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/profile` | yes | true no-mock HTTP | `tests/Api/SensitiveDataLeakageTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testProfileReadDoesNotLeakEncryptionEnvelope` |
| `PUT /api/profile` | yes | true no-mock HTTP | `tests/Api/SensitiveDataLeakageTest.php` | `testProfileReadDoesNotLeakEncryptionEnvelope` |
| `GET /api/resources` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testResourceListIsVisibleToAnyAuthenticatedUser` |
| `POST /api/resources` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testOnlyAdminCanCreateResources` |
| `POST /api/resources/{id}/retire` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testRetiredResourceCannotBeReserved` |
| `GET /api/resources/{id}/reservations` | no | unit-only / indirect | none | no API request evidence in `tests/Api/*` |
| `GET /api/admin/bookings` | yes | true no-mock HTTP | `tests/Api/AdminBookingOverrideTest.php` | `testAdminCanListAllBookings` |
| `GET /api/admin/audit/{type}/{id}` | yes | true no-mock HTTP | `tests/Api/AdminApiTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testUnauthenticatedAndForbidden`, `testNonAdminCannotAccessAuditHistory` |
| `POST /api/admin/storage/tier` | yes | true no-mock HTTP | `tests/Api/AdminApiTest.php`, `tests/Api/AdminAuditLoggingTest.php` | `testAuditHistoryAndTiering`, `testTieringIsAudited` |
| `POST /api/admin/snapshots` | yes | true no-mock HTTP | `tests/Api/AdminAuditLoggingTest.php`, `tests/Api/AuthorizationIsolationTest.php` | `testSnapshotExportIsAudited` |
| `POST /api/admin/keys/rotate` | yes | true no-mock HTTP | `tests/Api/AuthorizationIsolationTest.php` | `testNonAdminCannotRotateKey` |

## API Test Classification

1. **True No-Mock HTTP**
   - All files in `tests/Api/*.php` that use `ApiTestCase::call()`.
   - Transport + real handlers evidence: `tests/Api/ApiTestCase.php::call`, `buildSymfonyRequest`, `HttpApplication::handle`.

2. **HTTP with Mocking**
   - **None detected** by static scan.

3. **Non-HTTP (unit/integration without HTTP)**
   - All `tests/Unit/*.php` files (e.g., `tests/Unit/BookingServiceTest.php`, `tests/Unit/GuardianServiceTest.php`, `tests/Unit/ReschedulePolicyTest.php`, etc.).

## Mock Detection

Static search in `tests/` found **no evidence** of:
- `jest.mock`
- `vi.mock`
- `sinon.stub`
- PHPUnit mock expectations (`->expects`, `->method`) on mocked collaborators

Conclusion: API tests are predominantly real HTTP-path tests against in-memory kernel, not mocked HTTP tests.

## Coverage Summary

- Total endpoints: **62**
- Endpoints with HTTP tests: **53**
- Endpoints with true no-mock HTTP tests: **53**
- HTTP coverage: **53 / 62 = 85.48%**
- True API coverage: **53 / 62 = 85.48%**

Uncovered endpoints (9):
- `POST /api/sessions/leaves`
- `GET /api/sessions/leaves`
- `GET /api/assessments/ranks`
- `GET /api/vouchers`
- `POST /api/moderation/{id}/attachments`
- `GET /api/certificates`
- `GET /api/certificates/mine`
- `GET /api/resources/{id}/reservations`
- one additional gap represented by partial endpoint behavior coverage (no explicit success-path HTTP test for list endpoints above despite auth-failure checks elsewhere)

## Unit Test Summary

### Backend Unit Tests

Backend unit tests present in `tests/Unit/*.php` (selected evidence):
- Controllers/HTTP-related: `tests/Unit/HttpTest.php`
- Services: `BookingServiceTest.php`, `AssessmentServiceTest.php`, `VoucherServiceTest.php`, `GuardianServiceTest.php`, `AuthServiceTest.php`, `SchedulingServiceTest.php`, `CertificateServiceTest.php`, `RbacServiceTest.php`
- Repositories/Persistence: `RepositoryTest.php`, `PdoBootstrapTest.php`, `PdoTransactionIntegrationTest.php`, `PdoVoucherClaimIdempotencyRaceTest.php`, `PdoRescheduleRollbackSafetyTest.php`
- Security/Auth/Crypto: `PasswordHasherTest.php`, `ProfileEncryptorTest.php`, `EncryptionKeyringTest.php`, `AuditLoggerTest.php`
- Concurrency/policy/edge: `ConcurrencyTest.php`, `SchedulingConcurrencyTest.php`, `ReschedulePolicyTest.php`, `RecurrenceEdgeCasesTest.php`, `LeaveSchedulingTest.php`

Important backend modules with weak/missing direct unit evidence:
- `src/Controller/SessionController.php` (mostly API-covered, not direct unit-focused)
- `src/Controller/ResourceController.php`
- `src/Controller/ProfileController.php`
- `src/Controller/AdminController.php`

### Frontend Unit Tests (STRICT REQUIREMENT)

Detection result for `fullstack` project:
- Frontend test files in `frontend/`: **NONE** (no `*.test.*` / `*.spec.*`)
- Framework/tooling evidence for frontend unit tests: **NONE**
- Evidence of importing/rendering frontend components in tests: **NONE**

Frontend components/modules covered:
- **NONE (no frontend unit test evidence)**

Important frontend modules/components not tested:
- `frontend/src/App.tsx`
- `frontend/src/components/Shell.tsx`
- `frontend/src/lib/api.ts`
- `frontend/src/lib/auth.tsx`
- `frontend/src/routes/Login.tsx`
- `frontend/src/routes/trainee/*`
- `frontend/src/routes/supervisor/*`
- `frontend/src/routes/admin/*`
- `frontend/src/routes/guardian/Home.tsx`
- `frontend/src/routes/employer/Home.tsx`

**Frontend unit tests: MISSING**

**CRITICAL GAP**: project inferred as `fullstack`, but frontend unit tests are absent.

### Cross-Layer Observation

- Testing is backend-heavy (broad API + substantial backend unit tests).
- Frontend has no unit-test safety net.
- This is unbalanced fullstack quality coverage.

## API Observability Check

Observability quality: **moderate**.

Strong examples:
- Method/path/input/output asserted explicitly in `tests/Api/BookingApiTest.php::testBookConfirmCancel`.
- Security behavior with response assertions in `tests/Api/TokenAbuseTest.php`.

Weak areas:
- Several endpoints only have auth-denial assertions, not full success response assertions (e.g., some list/read endpoints).
- Uncovered endpoints have zero request/response observability.

## Tests Check

- `run_tests.sh` is Docker-based (`docker compose build tests`, `docker compose run --rm tests`): **OK**.
- Local dependency requirement still appears in README test instructions (`vendor/bin/phpunit`): this affects README compliance, not test script capability.

## End-to-End Expectations (Fullstack)

- Expected: real FE ↔ BE integration tests for fullstack.
- Found: none in frontend test suite.
- Partial compensation: backend API suite is relatively strong, but does **not** satisfy fullstack E2E expectation.

## Test Coverage Score (0–100)

**Score: 61 / 100**

## Score Rationale

- + strong real HTTP backend API coverage and no heavy mocking detected
- + substantial backend unit suite depth
- - 9 endpoint-level HTTP gaps
- - critical absence of frontend unit tests for fullstack project
- - missing fullstack FE↔BE end-to-end coverage
- - uneven observability depth across endpoints

## Key Gaps

1. Missing HTTP coverage for `/api/sessions/leaves` create/list.
2. Missing HTTP coverage for `/api/moderation/{id}/attachments`.
3. Missing list/read endpoint coverage (`/api/vouchers`, `/api/certificates`, `/api/certificates/mine`, `/api/resources/{id}/reservations`, `/api/assessments/ranks`).
4. No frontend unit tests (critical for inferred fullstack type).
5. No FE↔BE end-to-end tests.

## Confidence & Assumptions

- Confidence: **high** for endpoint inventory (direct from `Kernel::registerRoutes`).
- Confidence: **medium-high** for coverage mapping (dynamic path interpolation handled via static file inspection; no runtime execution performed).
- Static-only constraints respected (no tests run).

## Test Coverage Verdict

**PARTIAL PASS (backend strong, fullstack incomplete; critical frontend gap).**

---

# README Audit

## README Location

- `README.md` exists at repo root: **PASS**

## Hard Gate Failures

1. **Startup instruction gate (backend/fullstack) — FAIL**
   - Required explicitly: `docker-compose up`
   - README uses `docker compose up -d db` and local non-Docker flow in quick start.

2. **Environment rules gate (no runtime/manual installs) — FAIL**
   - Forbidden commands present:
     - `composer install` (line in quick start)
     - `npm install` (line in quick start)
   - This violates strict "everything Docker-contained" requirement.

3. **Demo credentials gate (auth exists) — FAIL**
   - Auth clearly exists (`/api/auth/*`, role model documented).
   - README does not provide concrete demo credentials for **all roles** (admin/trainee/supervisor/guardian/employer).

4. **Project type declaration gate — FAIL**
   - Required explicit type declaration at top is missing.

## High Priority Issues

- Missing explicit project type label at top (`fullstack` expected).
- Docker-only startup policy not satisfied by provided instructions.
- Auth-enabled system lacks explicit demo credentials across all roles.
- Verification guidance exists but is backend-leaning; fullstack verification flow is incomplete.

## Medium Priority Issues

- Access instructions are split between backend (`:8080`) and frontend (`:5173`) but not presented as strict canonical startup path.
- Testing section focuses on local `vendor/bin/phpunit`; Docker test entrypoint is not positioned as required default.

## Low Priority Issues

- README is structurally clear and detailed, but strict compliance fails due to hard-gate policy mismatches.

## Engineering Quality

- Tech stack clarity: strong.
- Architecture explanation: strong.
- Testing instructions: detailed but policy-conflicting (local-first).
- Security/roles explanation: strong conceptual coverage.
- Presentation quality: high readability.

## README Verdict

**FAIL**

---

# Final Verdicts

- **Test Coverage Audit Verdict:** **PARTIAL PASS** (score **61/100**) with **CRITICAL GAP** on frontend unit testing.
- **README Audit Verdict:** **FAIL** (multiple hard-gate violations).
