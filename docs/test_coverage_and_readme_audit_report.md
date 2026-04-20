# Test Coverage Audit

## Project Type Detection

- Declared in README top: `fullstack web application` (`README.md`).
- Light structure confirmation: backend in `src/*` + frontend in `frontend/src/*`.
- Effective audit type: **fullstack**.

## Backend Endpoint Inventory

Source of truth: `src/App/Kernel.php::registerRoutes()`

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

HTTP transport-path evidence (real request path, not direct controller call):
- `tests/Api/ApiTestCase.php::call()` creates `SymfonyRequest` and calls `HttpApplication::handle()`.
- `src/App/HttpApplication.php::handle()` dispatches through `Kernel->router->dispatch()`.

| Endpoint | covered | test type | test files | evidence (file + test function) |
|---|---|---|---|---|
| `POST /api/auth/bootstrap` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testBootstrapCreatesFirstAdmin` |
| `POST /api/auth/register` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testAdminCreatesUser` |
| `POST /api/auth/login` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testLoginFlow` |
| `POST /api/auth/select-role` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testLoginFlow` |
| `POST /api/auth/switch-role` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testLoginFlow` |
| `POST /api/auth/logout` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testLoginFlow` |
| `POST /api/auth/change-password` | yes | true no-mock HTTP | `tests/Api/AuthApiTest.php` | `testChangePassword` |
| `GET /api/auth/me` | yes | true no-mock HTTP | `tests/Api/SessionCookieTest.php` | `testCookieAloneAuthenticatesSubsequentRequests` |
| `POST /api/sessions` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks` |
| `GET /api/sessions` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks` |
| `POST /api/sessions/{id}/close` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks` |
| `GET /api/sessions/{id}/availability` | yes | true no-mock HTTP | `tests/Api/SessionApiTest.php` | `testSupervisorCreatesSessionAndTraineeBooks` |
| `POST /api/sessions/leaves` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testSupervisorCanAddAndListLeaves` |
| `GET /api/sessions/leaves` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testSupervisorCanAddAndListLeaves` |
| `POST /api/bookings` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testBookConfirmCancel` |
| `GET /api/bookings` | yes | true no-mock HTTP | `tests/Api/BookingCrossOwnershipTest.php` | `testSupervisorListBookingsHidesOtherSupervisorsBookings` |
| `GET /api/bookings/{id}` | yes | true no-mock HTTP | `tests/Api/BookingCrossOwnershipTest.php` | `testSupervisorCannotViewBookingOnAnotherSupervisorsSession` |
| `POST /api/bookings/{id}/confirm` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testBookConfirmCancel` |
| `POST /api/bookings/{id}/cancel` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testBookConfirmCancel` |
| `POST /api/bookings/{id}/reschedule` | yes | true no-mock HTTP | `tests/Api/BookingApiTest.php` | `testReschedule` |
| `POST /api/assessments/templates` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php` | `testFlow` |
| `POST /api/assessments/ranks` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php` | `testFlow` |
| `GET /api/assessments/ranks` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testListRanksReturnsSeededRanks` |
| `POST /api/assessments` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php` | `testFlow` |
| `GET /api/assessments/progress/{traineeId}` | yes | true no-mock HTTP | `tests/Api/AssessmentApiTest.php` | `testFlow` |
| `POST /api/vouchers` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `GET /api/vouchers` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testListAllVouchersExposesFullCatalog` |
| `GET /api/vouchers/{code}` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims/{id}/redeem` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testIssueClaimRedeem` |
| `POST /api/vouchers/claims/{id}/void` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testVoidClaimAndVoucher` |
| `POST /api/vouchers/{id}/void` | yes | true no-mock HTTP | `tests/Api/VoucherApiTest.php` | `testVoidClaimAndVoucher` |
| `POST /api/moderation` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/{id}/attachments` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testAttachPngToModerationItem` |
| `POST /api/moderation/{id}/approve` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/{id}/reject` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/moderation/bulk-approve` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testBulkEndpoints` |
| `POST /api/moderation/bulk-reject` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testBulkEndpoints` |
| `GET /api/moderation/pending` | yes | true no-mock HTTP | `tests/Api/ModerationApiTest.php` | `testSubmitApproveRejectFlow` |
| `POST /api/guardians/links` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php` | `testLinkApproveRevoke` |
| `POST /api/guardians/devices` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php` | `testLinkApproveRevoke` |
| `POST /api/guardians/devices/{id}/logout` | yes | true no-mock HTTP | `tests/Api/GuardianRemoteLogoutTest.php` | `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `GET /api/guardians/children` | yes | true no-mock HTTP | `tests/Api/GuardianApiTest.php` | `testLinkApproveRevoke` |
| `GET /api/guardians/children/{childId}/progress` | yes | true no-mock HTTP | `tests/Api/AuthorizationIsolationTest.php` | `testGuardianCanOnlySeeLinkedChildrenProgress` |
| `GET /api/guardians/children/{childId}/devices` | yes | true no-mock HTTP | `tests/Api/GuardianRemoteLogoutTest.php` | `testApprovedDeviceCanBeRemoteLoggedOutAndShowsRevokedStatus` |
| `POST /api/certificates` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/certificates` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testListAllCertificatesAsAdmin` |
| `GET /api/certificates/mine` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testListMyCertificatesScopedToCaller` |
| `GET /api/certificates/verify/{code}` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php` | `testIssueVerifyDownloadRevoke` |
| `POST /api/certificates/{id}/revoke` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/certificates/{id}/download` | yes | true no-mock HTTP | `tests/Api/CertificateApiTest.php` | `testIssueVerifyDownloadRevoke` |
| `GET /api/profile` | yes | true no-mock HTTP | `tests/Api/SensitiveDataLeakageTest.php` | `testProfileReadDoesNotLeakEncryptionEnvelope` |
| `PUT /api/profile` | yes | true no-mock HTTP | `tests/Api/SensitiveDataLeakageTest.php` | `testProfileReadDoesNotLeakEncryptionEnvelope` |
| `GET /api/resources` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testResourceListIsVisibleToAnyAuthenticatedUser` |
| `POST /api/resources` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testOnlyAdminCanCreateResources` |
| `POST /api/resources/{id}/retire` | yes | true no-mock HTTP | `tests/Api/ResourceCalendarTest.php` | `testRetiredResourceCannotBeReserved` |
| `GET /api/resources/{id}/reservations` | yes | true no-mock HTTP | `tests/Api/ProductionReadinessApiTest.php` | `testListResourceReservationsMatchesSessionBookings` |
| `GET /api/admin/bookings` | yes | true no-mock HTTP | `tests/Api/AdminBookingOverrideTest.php` | `testAdminCanListAllBookings` |
| `GET /api/admin/audit/{type}/{id}` | yes | true no-mock HTTP | `tests/Api/AdminApiTest.php` | `testAuditHistoryAndTiering` |
| `POST /api/admin/storage/tier` | yes | true no-mock HTTP | `tests/Api/AdminApiTest.php` | `testAuditHistoryAndTiering` |
| `POST /api/admin/snapshots` | yes | true no-mock HTTP | `tests/Api/AdminAuditLoggingTest.php` | `testSnapshotExportIsAudited` |
| `POST /api/admin/keys/rotate` | yes | true no-mock HTTP | `tests/Api/AuthorizationIsolationTest.php` | `testNonAdminCannotRotateKey` |

## API Test Classification

1. **True No-Mock HTTP**
   - API suites in `tests/Api/*.php` using `ApiTestCase::call()` through Symfony request + kernel path.
2. **HTTP with Mocking**
   - None detected in `tests/Api/*`.
3. **Non-HTTP (unit/integration without HTTP)**
   - `tests/Unit/*.php`.

## Mock Detection

Static scan findings:
- No `jest.mock`, `vi.mock`, `sinon.stub` in backend tests.
- No PHPUnit collaborator-mock patterns in API tests (`->expects`, `->method`) used to bypass route/service execution.

Conclusion: API endpoint tests in `tests/Api/*` qualify as **true no-mock HTTP** under the static evidence available.

## Coverage Summary

- Total endpoints: **62**
- Endpoints with HTTP tests: **62**
- Endpoints with true no-mock HTTP tests: **62**
- HTTP coverage %: **100.00%**
- True API coverage %: **100.00%**

## Unit Test Summary

### Backend Unit Tests

Evidence of backend unit coverage:
- Controllers/HTTP primitives: `tests/Unit/HttpTest.php::testRouterDispatch`.
- Service/domain logic: `tests/Unit/BookingServiceTest.php`, `tests/Unit/AssessmentServiceTest.php`, `tests/Unit/VoucherServiceTest.php`, `tests/Unit/GuardianServiceTest.php`, `tests/Unit/AuthServiceTest.php`.
- Persistence/transaction paths: `tests/Unit/PdoTransactionIntegrationTest.php`, `tests/Unit/PdoVoucherClaimIdempotencyRaceTest.php`, `tests/Unit/PdoRescheduleRollbackSafetyTest.php`.
- Security/crypto/audit: `tests/Unit/EncryptionKeyringTest.php`, `tests/Unit/ProfileEncryptorTest.php`, `tests/Unit/AuditLoggerTest.php`.

Important backend modules not directly unit-tested (controller level mostly API-covered):
- `src/Controller/AdminController.php`
- `src/Controller/ProfileController.php`
- `src/Controller/ResourceController.php`

### Frontend Unit Tests (STRICT REQUIREMENT)

Frontend test files (direct evidence):
- `frontend/src/App.test.tsx`
- `frontend/src/components/Shell.test.tsx`
- `frontend/src/routes/Login.test.tsx`
- `frontend/src/routes/roles.test.tsx`
- `frontend/src/lib/api.test.ts`
- `frontend/src/lib/auth.test.tsx`
- `frontend/src/e2e/trainee-flow.test.tsx`

Frameworks/tools detected:
- Vitest (`frontend/package.json` script `test: vitest run`)
- React Testing Library (`@testing-library/react`)
- Testing interactions via `@testing-library/user-event`

Frontend components/modules covered:
- `App`, route guards, redirect behavior (`App.test.tsx`).
- `Shell` role navigation + logout behavior (`Shell.test.tsx`).
- `LoginPage` including bootstrap admin flow (`Login.test.tsx`).
- Role pages: `TraineeHome`, `SupervisorHome`, `AdminHome`, `EmployerHome`, `GuardianHome` (`routes/roles.test.tsx`).
- API client and error handling (`lib/api.test.ts`).
- Auth provider/session state (`lib/auth.test.tsx`).

Important frontend components/modules not explicitly tested:
- Deeper feature screens under `frontend/src/routes/admin/*`, `frontend/src/routes/supervisor/*`, `frontend/src/routes/trainee/*` beyond current core/home/auth flows.

**Frontend unit tests: PRESENT**

### Cross-Layer Observation

- Backend test coverage is broader in volume.
- Frontend now has concrete unit/integration coverage and one FE↔BE flow contract test (`frontend/src/e2e/trainee-flow.test.tsx`).
- Balance is acceptable for fullstack baseline, but still backend-heavy in depth.

## API Observability Check

Status: **mixed-strong**

Strong evidence (method + input + response assertions):
- `tests/Api/ProductionReadinessApiTest.php::testAttachPngToModerationItem`
- `tests/Api/ProductionReadinessApiTest.php::testListAllVouchersExposesFullCatalog`
- `tests/Api/BookingApiTest.php::testBookConfirmCancel`

Weak spots:
- Some endpoints are validated mostly through auth/forbidden assertions rather than rich success payload validation (example: `POST /api/admin/keys/rotate` via `tests/Api/AuthorizationIsolationTest.php::testNonAdminCannotRotateKey`).

## Test Quality & Sufficiency

- Success/failure/auth/validation coverage exists across core domains.
- Edge and resilience tests exist for concurrency/idempotency in units (`PdoVoucherClaimIdempotencyRaceTest`, `PdoRescheduleRollbackSafetyTest`).
- Over-mocking risk is low (no API-layer mocking detected).
- `run_tests.sh` is Docker-based (`docker compose`/`docker-compose`): **OK**.

## End-to-End Expectations

- Fullstack expectation: FE↔BE integration evidence present in `frontend/src/e2e/trainee-flow.test.tsx::it('logs in, lands on /trainee, and renders progress from the API', ...)`.
- Missing: browser-driven full-stack E2E (Playwright/Cypress against running stack). Not a hard failure here, but still a quality gap.

## Tests Check

- Static inspection only; no code/test/container execution performed.
- Endpoint mapping, classification, and gaps are based on file-level evidence only.

## Test Coverage Score (0–100)

**94 / 100**

## Score Rationale

- + 62/62 endpoint HTTP coverage with direct test evidence.
- + true no-mock HTTP route path in API tests.
- + strong backend unit depth and meaningful assertions.
- + frontend unit/component tests are present and real.
- - API observability is uneven on a subset of admin/security endpoints.
- - full browser-level E2E matrix is still absent.

## Key Gaps

1. Expand payload-level assertions for endpoints currently covered mainly by deny-path tests (e.g., admin key rotation access paths).
2. Add browser-level multi-role fullstack E2E beyond fetch-mocked SPA integration tests.
3. Add deeper frontend feature-page tests beyond current auth/shell/home coverage.

## Confidence & Assumptions

- Confidence: **high** on endpoint inventory and coverage mapping (`src/App/Kernel.php`, `tests/Api/*`).
- Confidence: **high** on frontend test presence and framework detection (`frontend/src/*.test.*`, `frontend/package.json`).
- Assumption boundary: “true no-mock HTTP” judged statically from available code patterns and request path wiring.

## Test Coverage Verdict

**PASS**

---

# README Audit

## README Location

- `README.md` exists at repository root: **PASS**.

## Hard Gate Failures

- **None**.

## Hard Gate Compliance

- Formatting/readability: **PASS** (`README.md` is structured, clean markdown).
- Startup instruction for fullstack includes required literal: **PASS** (`docker-compose up` appears explicitly).
- Access method for web/backend includes URL + ports: **PASS** (`http://localhost:5173`, `http://localhost:8080/api`).
- Verification method present: **PASS** (curl flows + UI role checks).
- Environment rules (no host runtime install requirement): **PASS** (Docker-first, no required `npm install`/`pip install`/manual DB setup).
- Demo credentials (auth exists): **PASS** (admin/supervisor/trainee/guardian/employer credentials listed).

## High Priority Issues

- None.

## Medium Priority Issues

- None required by strict gates.

## Low Priority Issues

- README is long; operationally dense sections could be split into quickstart vs deep-dive docs for scan speed.

## Engineering Quality

- Tech stack clarity: strong.
- Architecture explanation: strong.
- Testing instructions: clear and containerized.
- Security/roles/workflows: explicit and role-complete.
- Presentation quality: high.

## README Verdict (PASS / PARTIAL PASS / FAIL)

**PASS**

---

# Final Verdicts

- **Test Coverage Audit Verdict:** **PASS** (`94/100`)
- **README Audit Verdict:** **PASS**
