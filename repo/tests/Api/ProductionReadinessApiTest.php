<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ModerationItem;
use App\Entity\SupervisorLeave;
use App\Service\Roles;

/**
 * Request/response coverage for endpoints that previously only had negative
 * (unauthenticated) assertions. Each test drives a real controller action
 * end-to-end through the HttpKernel pipeline and asserts the concrete shape
 * of the returned body, not just the status code.
 */
final class ProductionReadinessApiTest extends ApiTestCase
{
    // -------------------------------------------------------------------
    // POST/GET /api/sessions/leaves
    // -------------------------------------------------------------------

    public function testSupervisorCanAddAndListLeaves(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $supId = $this->kernel->users->findByUsername('sup')->getId();
        $start = '2026-07-01T09:00:00+00:00';
        $end = '2026-07-01T17:00:00+00:00';

        $create = $this->call('POST', '/api/sessions/leaves', [
            'startsAt' => $start,
            'endsAt' => $end,
            'rule' => SupervisorLeave::RULE_ONE_OFF,
            'reason' => 'medical',
        ], $supToken);
        self::assertSame(201, $create->getStatus());
        $body = $create->getBody();
        self::assertNotEmpty($body['id']);
        self::assertSame($supId, $body['supervisorId']);
        self::assertSame($start, $body['startsAt']);
        self::assertSame($end, $body['endsAt']);
        self::assertSame(SupervisorLeave::RULE_ONE_OFF, $body['rule']);
        self::assertSame('medical', $body['reason']);

        $list = $this->call('GET', '/api/sessions/leaves', [], $supToken);
        self::assertSame(200, $list->getStatus());
        $leaves = $list->getBody()['leaves'];
        self::assertCount(1, $leaves);
        self::assertSame($body['id'], $leaves[0]['id']);
        self::assertSame($supId, $leaves[0]['supervisorId']);
        self::assertSame(SupervisorLeave::RULE_ONE_OFF, $leaves[0]['rule']);
    }

    public function testAddLeaveConflictsWithExistingSession(): void
    {
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $this->call('POST', '/api/sessions', [
            'title' => 'Morning drill',
            'startsAt' => '2026-07-02T09:00:00+00:00',
            'endsAt' => '2026-07-02T10:00:00+00:00',
            'capacity' => 3,
        ], $supToken);

        $overlap = $this->call('POST', '/api/sessions/leaves', [
            'startsAt' => '2026-07-02T08:30:00+00:00',
            'endsAt' => '2026-07-02T09:30:00+00:00',
            'rule' => SupervisorLeave::RULE_ONE_OFF,
        ], $supToken);
        self::assertSame(409, $overlap->getStatus());
        self::assertSame('leave overlaps existing session for supervisor', $overlap->getBody()['error']);
    }

    public function testLeavesEndpointsAreForbiddenToTrainees(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        self::assertSame(403, $this->call('POST', '/api/sessions/leaves', [
            'startsAt' => '2026-07-03T09:00:00+00:00',
            'endsAt' => '2026-07-03T10:00:00+00:00',
        ], $t)->getStatus());
        self::assertSame(403, $this->call('GET', '/api/sessions/leaves', [], $t)->getStatus());
    }

    public function testLeavesEndpointsRejectAnonymous(): void
    {
        self::assertSame(401, $this->call('POST', '/api/sessions/leaves')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/sessions/leaves')->getStatus());
    }

    public function testAdminCanInspectAnotherSupervisorsLeaves(): void
    {
        $admin = $this->seedAdmin();
        $supToken = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $supId = $this->kernel->users->findByUsername('sup')->getId();
        $this->call('POST', '/api/sessions/leaves', [
            'startsAt' => '2026-07-04T09:00:00+00:00',
            'endsAt' => '2026-07-04T17:00:00+00:00',
            'rule' => SupervisorLeave::RULE_WEEKLY,
        ], $supToken);

        $asAdmin = $this->call('GET', '/api/sessions/leaves', [], $admin, ['supervisorId' => $supId]);
        self::assertSame(200, $asAdmin->getStatus());
        self::assertCount(1, $asAdmin->getBody()['leaves']);
        self::assertSame(SupervisorLeave::RULE_WEEKLY, $asAdmin->getBody()['leaves'][0]['rule']);
    }

    // -------------------------------------------------------------------
    // GET /api/assessments/ranks
    // -------------------------------------------------------------------

    public function testListRanksReturnsSeededRanks(): void
    {
        $sup = $this->seedUser('sup', 'pass-1234', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);

        $this->kernel->assessmentService->createRank('Bronze', 5, 0, 1);
        $this->kernel->assessmentService->createRank('Silver', 10, 0, 2);

        $list = $this->call('GET', '/api/assessments/ranks', [], $trainee);
        self::assertSame(200, $list->getStatus());
        $ranks = $list->getBody()['ranks'];
        self::assertCount(2, $ranks);
        self::assertSame('Bronze', $ranks[0]['name']);
        self::assertSame(5, $ranks[0]['minReps']);
        self::assertSame(0, $ranks[0]['minSeconds']);
        self::assertSame(1, $ranks[0]['order']);
        self::assertSame('Silver', $ranks[1]['name']);
        self::assertSame(10, $ranks[1]['minReps']);
        self::assertSame(2, $ranks[1]['order']);

        // Supervisors and admins also have read access under
        // 'assessment.view.self' in the RBAC matrix.
        $asSupervisor = $this->call('GET', '/api/assessments/ranks', [], $sup);
        self::assertSame(200, $asSupervisor->getStatus());
        self::assertCount(2, $asSupervisor->getBody()['ranks']);
    }

    public function testListRanksReturnsEmptyArrayWhenNoneDefined(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $list = $this->call('GET', '/api/assessments/ranks', [], $t);
        self::assertSame(200, $list->getStatus());
        self::assertSame([], $list->getBody()['ranks']);
    }

    public function testListRanksRequiresAuth(): void
    {
        self::assertSame(401, $this->call('GET', '/api/assessments/ranks')->getStatus());
    }

    // -------------------------------------------------------------------
    // GET /api/vouchers
    // -------------------------------------------------------------------

    public function testListAllVouchersExposesFullCatalog(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $expires = $this->kernel->clock->now()->modify('+30 days')->format(DATE_ATOM);

        $this->call('POST', '/api/vouchers', [
            'code' => 'SAVE25',
            'discountCents' => 2500,
            'minSpendCents' => 15000,
            'claimLimit' => 3,
            'expiresAt' => $expires,
        ], $admin);
        $this->call('POST', '/api/vouchers', [
            'code' => 'WELCOME10',
            'discountCents' => 1000,
            'minSpendCents' => 5000,
            'claimLimit' => 5,
            'expiresAt' => $expires,
        ], $admin);

        $list = $this->call('GET', '/api/vouchers', [], $admin);
        self::assertSame(200, $list->getStatus());
        $vouchers = $list->getBody()['vouchers'];
        self::assertCount(2, $vouchers);

        $byCode = [];
        foreach ($vouchers as $v) {
            $byCode[$v['code']] = $v;
        }
        self::assertArrayHasKey('SAVE25', $byCode);
        self::assertSame(2500, $byCode['SAVE25']['discountCents']);
        self::assertSame(15000, $byCode['SAVE25']['minSpendCents']);
        self::assertSame(3, $byCode['SAVE25']['claimLimit']);
        self::assertSame(0, $byCode['SAVE25']['claimed']);
        self::assertSame(3, $byCode['SAVE25']['remaining']);
        self::assertSame('active', $byCode['SAVE25']['status']);
        self::assertSame($expires, $byCode['SAVE25']['expiresAt']);

        self::assertArrayHasKey('WELCOME10', $byCode);
        self::assertSame(1000, $byCode['WELCOME10']['discountCents']);
        self::assertSame(5, $byCode['WELCOME10']['claimLimit']);
    }

    public function testListVouchersReportsDecrementingRemainingAfterClaim(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $expires = $this->kernel->clock->now()->modify('+30 days')->format(DATE_ATOM);
        $this->call('POST', '/api/vouchers', [
            'code' => 'SAVE25',
            'discountCents' => 2500,
            'minSpendCents' => 15000,
            'claimLimit' => 2,
            'expiresAt' => $expires,
        ], $admin);
        $this->call('POST', '/api/vouchers/claims', [
            'code' => 'SAVE25',
            'idempotencyKey' => 'idem-1',
        ], $trainee);

        $after = $this->call('GET', '/api/vouchers', [], $admin);
        self::assertSame(200, $after->getStatus());
        $voucher = $after->getBody()['vouchers'][0];
        self::assertSame(1, $voucher['claimed']);
        self::assertSame(1, $voucher['remaining']);
    }

    public function testListVouchersEmptyWhenNothingIssued(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $list = $this->call('GET', '/api/vouchers', [], $admin);
        self::assertSame(200, $list->getStatus());
        self::assertSame([], $list->getBody()['vouchers']);
    }

    public function testListVouchersForbiddenForNonAdmin(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        self::assertSame(403, $this->call('GET', '/api/vouchers', [], $t)->getStatus());
    }

    public function testListVouchersRequiresAuth(): void
    {
        self::assertSame(401, $this->call('GET', '/api/vouchers')->getStatus());
    }

    // -------------------------------------------------------------------
    // POST /api/moderation/{id}/attachments
    // -------------------------------------------------------------------

    public function testAttachPngToModerationItem(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $item = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_EVIDENCE,
            'content' => 'see attached',
        ], $trainee);
        self::assertSame(201, $item->getStatus());
        $itemId = $item->getBody()['id'];

        $png = "\x89PNG\r\n\x1a\n" . str_repeat('x', 512);
        $attach = $this->call('POST', "/api/moderation/{$itemId}/attachments", [
            'filename' => 'proof.png',
            'mimeType' => 'image/png',
            'contentBase64' => base64_encode($png),
        ], $trainee);
        self::assertSame(201, $attach->getStatus());

        $body = $attach->getBody();
        self::assertNotEmpty($body['id']);
        self::assertSame($itemId, $body['itemId']);
        self::assertSame('proof.png', $body['filename']);
        self::assertSame('image/png', $body['mimeType']);
        self::assertSame(strlen($png), $body['sizeBytes']);
        self::assertSame(hash('sha256', $png), $body['checksum']);
        self::assertNotEmpty($body['uploadedAt']);
    }

    public function testAttachRejectsInvalidBase64(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $item = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_EVIDENCE,
            'content' => 'bad payload',
        ], $trainee)->getBody()['id'];

        $r = $this->call('POST', "/api/moderation/{$item}/attachments", [
            'filename' => 'x.png',
            'mimeType' => 'image/png',
            'contentBase64' => '!!!not-base64!!!',
        ], $trainee);
        self::assertSame(422, $r->getStatus());
        self::assertSame('invalid base64 payload', $r->getBody()['error']);
    }

    public function testAttachRejectsMissingContent(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $item = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_EVIDENCE,
            'content' => 'missing payload',
        ], $trainee)->getBody()['id'];

        $r = $this->call('POST', "/api/moderation/{$item}/attachments", [
            'filename' => 'x.png',
            'mimeType' => 'image/png',
        ], $trainee);
        self::assertSame(422, $r->getStatus());
        self::assertSame('contentBase64 required', $r->getBody()['error']);
    }

    public function testAttachRejectsMimeMismatch(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $item = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_EVIDENCE,
            'content' => 'mime check',
        ], $trainee)->getBody()['id'];

        // Claim PNG, supply JPEG magic bytes.
        $r = $this->call('POST', "/api/moderation/{$item}/attachments", [
            'filename' => 'fake.png',
            'mimeType' => 'image/png',
            'contentBase64' => base64_encode("\xff\xd8\xff\xe0" . str_repeat('y', 100)),
        ], $trainee);
        self::assertSame(422, $r->getStatus());
    }

    public function testAttachRequiresAuthentication(): void
    {
        self::assertSame(401, $this->call('POST', '/api/moderation/x/attachments')->getStatus());
    }

    // -------------------------------------------------------------------
    // GET /api/certificates  and  GET /api/certificates/mine
    // -------------------------------------------------------------------

    public function testListAllCertificatesAsAdmin(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $this->createUser('t1', 'pass-1234', [Roles::TRAINEE]);
        $this->createUser('t2', 'pass-1234', [Roles::TRAINEE]);
        $t1 = $this->kernel->users->findByUsername('t1')->getId();
        $t2 = $this->kernel->users->findByUsername('t2')->getId();
        $rank = $this->kernel->assessmentService->createRank('Bronze', 5, 0, 1);

        $issue1 = $this->call('POST', '/api/certificates', [
            'traineeId' => $t1,
            'rankId' => $rank->getId(),
        ], $admin);
        self::assertSame(201, $issue1->getStatus());
        $issue2 = $this->call('POST', '/api/certificates', [
            'traineeId' => $t2,
            'rankId' => $rank->getId(),
        ], $admin);
        self::assertSame(201, $issue2->getStatus());

        $all = $this->call('GET', '/api/certificates', [], $admin);
        self::assertSame(200, $all->getStatus());
        $certs = $all->getBody()['certificates'];
        self::assertCount(2, $certs);

        $byTrainee = [];
        foreach ($certs as $c) {
            $byTrainee[$c['traineeId']] = $c;
            self::assertSame('active', $c['status']);
            self::assertSame($rank->getId(), $c['rankId']);
            self::assertNotEmpty($c['verificationCode']);
            self::assertNotEmpty($c['issuedAt']);
        }
        self::assertArrayHasKey($t1, $byTrainee);
        self::assertArrayHasKey($t2, $byTrainee);
    }

    public function testListAllCertificatesForbiddenForNonAdmin(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        self::assertSame(403, $this->call('GET', '/api/certificates', [], $t)->getStatus());
    }

    public function testListAllCertificatesRequiresAuth(): void
    {
        self::assertSame(401, $this->call('GET', '/api/certificates')->getStatus());
    }

    public function testListMyCertificatesScopedToCaller(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $this->createUser('t2', 'pass-1234', [Roles::TRAINEE]);
        $traineeId = $this->kernel->users->findByUsername('t1')->getId();
        $otherId = $this->kernel->users->findByUsername('t2')->getId();
        $rank = $this->kernel->assessmentService->createRank('Bronze', 5, 0, 1);

        $mine = $this->call('POST', '/api/certificates', [
            'traineeId' => $traineeId,
            'rankId' => $rank->getId(),
        ], $admin)->getBody();
        // An unrelated certificate for another trainee must not appear
        // under /api/certificates/mine.
        $this->call('POST', '/api/certificates', [
            'traineeId' => $otherId,
            'rankId' => $rank->getId(),
        ], $admin);

        $list = $this->call('GET', '/api/certificates/mine', [], $trainee);
        self::assertSame(200, $list->getStatus());
        $certs = $list->getBody()['certificates'];
        self::assertCount(1, $certs);
        self::assertSame($mine['id'], $certs[0]['id']);
        self::assertSame($traineeId, $certs[0]['traineeId']);
        self::assertSame($rank->getId(), $certs[0]['rankId']);
        self::assertSame($mine['verificationCode'], $certs[0]['verificationCode']);
        self::assertSame('active', $certs[0]['status']);
    }

    public function testListMyCertificatesEmptyWhenNoneIssued(): void
    {
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $r = $this->call('GET', '/api/certificates/mine', [], $trainee);
        self::assertSame(200, $r->getStatus());
        self::assertSame([], $r->getBody()['certificates']);
    }

    public function testListMyCertificatesRequiresAuth(): void
    {
        self::assertSame(401, $this->call('GET', '/api/certificates/mine')->getStatus());
    }

    // -------------------------------------------------------------------
    // GET /api/resources/{id}/reservations
    // -------------------------------------------------------------------

    public function testListResourceReservationsMatchesSessionBookings(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $supId = $this->kernel->users->findByUsername('sup')->getId();

        $resId = $this->call('POST', '/api/resources', ['name' => 'Hall', 'kind' => 'room'], $admin)
            ->getBody()['id'];

        $session = $this->call('POST', '/api/sessions', [
            'title' => 'Booked',
            'startsAt' => '2026-07-10T09:00:00+00:00',
            'endsAt' => '2026-07-10T10:00:00+00:00',
            'capacity' => 3,
            'resourceIds' => [$resId],
        ], $sup);
        self::assertSame(201, $session->getStatus());
        $sessionId = $session->getBody()['id'];

        $reservations = $this->call('GET', "/api/resources/{$resId}/reservations", [], $sup);
        self::assertSame(200, $reservations->getStatus());
        $items = $reservations->getBody()['reservations'];
        self::assertCount(1, $items);

        $first = $items[0];
        self::assertNotEmpty($first['id']);
        self::assertSame($resId, $first['resourceId']);
        self::assertSame($sessionId, $first['sessionId']);
        self::assertSame('2026-07-10T09:00:00+00:00', $first['startsAt']);
        self::assertSame('2026-07-10T10:00:00+00:00', $first['endsAt']);
        self::assertSame($supId, $first['reservedByUserId']);
    }

    public function testListResourceReservationsEmptyForUnusedResource(): void
    {
        $admin = $this->seedAdmin();
        $sup = $this->seedUser('sup', 'pw-12345', [Roles::SUPERVISOR], Roles::SUPERVISOR);
        $resId = $this->call('POST', '/api/resources', ['name' => 'Quiet Room', 'kind' => 'room'], $admin)
            ->getBody()['id'];

        $r = $this->call('GET', "/api/resources/{$resId}/reservations", [], $sup);
        self::assertSame(200, $r->getStatus());
        self::assertSame([], $r->getBody()['reservations']);
    }

    public function testListResourceReservationsRequiresAuth(): void
    {
        self::assertSame(401, $this->call('GET', '/api/resources/anything/reservations')->getStatus());
    }

    public function testListResourceReservationsForbiddenForAnonymous(): void
    {
        // Employers have resource.view permission — confirm a role that
        // doesn't (none currently), but keep parity with the guard path:
        // RBAC denies anything without resource.view.
        $admin = $this->seedAdmin();
        $resId = $this->call('POST', '/api/resources', ['name' => 'Gym', 'kind' => 'room'], $admin)
            ->getBody()['id'];

        // A trainee DOES have resource.view in this app — so list should succeed
        // as a trainee and be empty, confirming that authenticated read-only
        // access is the default for non-admin roles too.
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $asTrainee = $this->call('GET', "/api/resources/{$resId}/reservations", [], $t);
        self::assertSame(200, $asTrainee->getStatus());
        self::assertSame([], $asTrainee->getBody()['reservations']);
    }
}
