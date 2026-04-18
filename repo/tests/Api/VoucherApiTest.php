<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Service\Roles;

final class VoucherApiTest extends ApiTestCase
{
    public function testIssueClaimRedeem(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);

        $expires = $this->kernel->clock->now()->modify('+30 days')->format(DATE_ATOM);

        $issue = $this->call('POST', '/api/vouchers', [
            'code' => 'SAVE25',
            'discountCents' => 2500,
            'minSpendCents' => 15000,
            'claimLimit' => 3,
            'expiresAt' => $expires,
        ], $admin);
        self::assertSame(201, $issue->getStatus());

        $describe = $this->call('GET', '/api/vouchers/SAVE25', [], $admin);
        self::assertSame(200, $describe->getStatus());

        $claim = $this->call('POST', '/api/vouchers/claims', [
            'code' => 'SAVE25',
            'idempotencyKey' => 'idem-1',
        ], $trainee);
        self::assertSame(201, $claim->getStatus());
        $cid = $claim->getBody()['id'];

        $redeem = $this->call('POST', "/api/vouchers/claims/{$cid}/redeem", [
            'orderAmountCents' => 20000,
            'redemptionIdempotencyKey' => 'rk-1',
        ], $trainee);
        self::assertSame(200, $redeem->getStatus());
        self::assertSame(2500, $redeem->getBody()['discountCents']);
        self::assertFalse($redeem->getBody()['replayed']);

        // Retrying with the same redemption idempotency key must replay the
        // stored outcome without throwing a conflict.
        $retry = $this->call('POST', "/api/vouchers/claims/{$cid}/redeem", [
            'orderAmountCents' => 20000,
            'redemptionIdempotencyKey' => 'rk-1',
        ], $trainee);
        self::assertSame(200, $retry->getStatus());
        self::assertSame(2500, $retry->getBody()['discountCents']);
        self::assertTrue($retry->getBody()['replayed']);
    }

    public function testVoidClaimAndVoucher(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $expires = $this->kernel->clock->now()->modify('+30 days')->format(DATE_ATOM);
        $this->call('POST', '/api/vouchers', [
            'code' => 'SAVE25',
            'discountCents' => 2500,
            'minSpendCents' => 15000,
            'claimLimit' => 3,
            'expiresAt' => $expires,
        ], $admin);
        $claim = $this->call('POST', '/api/vouchers/claims', [
            'code' => 'SAVE25',
            'idempotencyKey' => 'idem-1',
        ], $trainee);
        $cid = $claim->getBody()['id'];

        $voidClaim = $this->call('POST', "/api/vouchers/claims/{$cid}/void", [], $admin);
        self::assertSame(200, $voidClaim->getStatus());

        $vid = $this->kernel->vouchers->findByCode('SAVE25')->getId();
        $voidVoucher = $this->call('POST', "/api/vouchers/{$vid}/void", [], $admin);
        self::assertSame(200, $voidVoucher->getStatus());
    }

    public function testIssueRequiresAuth(): void
    {
        self::assertSame(401, $this->call('POST', '/api/vouchers')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/vouchers/claims')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/vouchers/claims/x/redeem')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/vouchers/claims/x/void')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/vouchers/x/void')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/vouchers/X')->getStatus());
    }

    public function testTraineeCannotIssue(): void
    {
        $t = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $r = $this->call('POST', '/api/vouchers', [
            'code' => 'X',
            'discountCents' => 100,
            'minSpendCents' => 500,
            'claimLimit' => 1,
            'expiresAt' => $this->kernel->clock->now()->modify('+1 day')->format(DATE_ATOM),
        ], $t);
        self::assertSame(403, $r->getStatus());
    }
}
