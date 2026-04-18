<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\VoucherClaim;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class VoucherServiceTest extends TestCase
{
    private function issue(\App\App\Kernel $k, string $code = 'SAVE25', int $discount = 2500, int $min = 15000, int $limit = 2): \App\Entity\Voucher
    {
        return $k->voucherService->issue(
            'admin1',
            $code,
            $discount,
            $min,
            $limit,
            $k->clock->now()->modify('+30 days'),
        );
    }

    public function testIssueAndDescribe(): void
    {
        $k = Factory::kernel();
        $v = $this->issue($k);
        self::assertSame('SAVE25', $v->getCode());
        self::assertSame($v, $k->voucherService->describe('SAVE25'));
    }

    public function testDescribeMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->voucherService->describe('none');
    }

    public function testIssueDuplicateCode(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $this->expectException(ConflictException::class);
        $this->issue($k);
    }

    public function testIssueInvalidCode(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->voucherService->issue('a', '', 100, 500, 1, $k->clock->now()->modify('+1 day'));
    }

    public function testIssueInvalidParameters(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->voucherService->issue('a', 'X', 0, 500, 1, $k->clock->now()->modify('+1 day'));
    }

    public function testIssueDiscountExceedsMin(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->voucherService->issue('a', 'X', 1000, 500, 1, $k->clock->now()->modify('+1 day'));
    }

    public function testIssueExpiredInPast(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->voucherService->issue('a', 'X', 100, 0, 1, $k->clock->now()->modify('-1 day'));
    }

    public function testIssueAllowsZeroMinSpend(): void
    {
        $k = Factory::kernel();
        $v = $k->voucherService->issue('admin', 'GIFT', 100, 0, 1, $k->clock->now()->modify('+1 day'));
        self::assertSame(0, $v->getMinSpendCents());
    }

    public function testClaimIdempotent(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c1 = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $c2 = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        self::assertSame($c1->getId(), $c2->getId());
    }

    public function testClaimNoKey(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $this->expectException(ValidationException::class);
        $k->voucherService->claim('SAVE25', 'u1', '');
    }

    public function testClaimMissingVoucher(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->voucherService->claim('MISS', 'u1', 'idem');
    }

    public function testClaimLimitReached(): void
    {
        $k = Factory::kernel();
        $this->issue($k, limit: 1);
        $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $this->expectException(ConflictException::class);
        $k->voucherService->claim('SAVE25', 'u2', 'idem-2');
    }

    public function testDuplicateClaimBySameUser(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $this->expectException(ConflictException::class);
        $k->voucherService->claim('SAVE25', 'u1', 'idem-2');
    }

    public function testClaimVoided(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->voucherService->voidClaim($c->getId(), 'admin');
        $k->voucherService->claim('SAVE25', 'u1', 'idem-2');
        self::assertTrue(true);
    }

    public function testClaimOnInactiveVoucher(): void
    {
        $k = Factory::kernel();
        $v = $this->issue($k);
        $k->voucherService->voidVoucher($v->getId(), 'admin');
        $this->expectException(ConflictException::class);
        $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
    }

    public function testRedeem(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $res = $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
        self::assertSame(2500, $res['discountCents']);
        self::assertSame(VoucherClaim::STATUS_REDEEMED, $res['claim']->getStatus());
    }

    public function testRedeemMissingClaim(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->voucherService->redeem('nope', 'u1', 100, 'rk-nope');
    }

    public function testRedeemWrongUser(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $this->expectException(ConflictException::class);
        $k->voucherService->redeem($c->getId(), 'u2', 20000, 'rk-wronguser');
    }

    public function testRedeemNonLocked(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
        $this->expectException(ConflictException::class);
        $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
    }

    public function testRedeemMissingVoucher(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->vouchers->delete($c->getVoucherId());
        $this->expectException(NotFoundException::class);
        $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
    }

    public function testRedeemInactiveVoucher(): void
    {
        $k = Factory::kernel();
        $v = $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->voucherService->voidVoucher($v->getId(), 'admin');
        $this->expectException(ConflictException::class);
        $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
    }

    public function testRedeemBelowMinSpend(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $this->expectException(ConflictException::class);
        $k->voucherService->redeem($c->getId(), 'u1', 10000, 'rk-' . bin2hex(random_bytes(3)));
    }

    public function testVoidClaimMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->voucherService->voidClaim('nope', 'admin');
    }

    public function testVoidRedeemedClaimBlocked(): void
    {
        $k = Factory::kernel();
        $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->voucherService->redeem($c->getId(), 'u1', 20000, 'rk-' . bin2hex(random_bytes(3)));
        $this->expectException(ConflictException::class);
        $k->voucherService->voidClaim($c->getId(), 'admin');
    }

    public function testVoidClaimCleansVoucherCount(): void
    {
        $k = Factory::kernel();
        $v = $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $before = $v->getClaimed();
        $k->voucherService->voidClaim($c->getId(), 'admin');
        self::assertSame($before - 1, $v->getClaimed());
    }

    public function testVoidClaimWhenVoucherMissing(): void
    {
        $k = Factory::kernel();
        $v = $this->issue($k);
        $c = $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
        $k->vouchers->delete($v->getId());
        $claim = $k->voucherService->voidClaim($c->getId(), 'admin');
        self::assertSame(VoucherClaim::STATUS_VOID, $claim->getStatus());
    }

    public function testVoidVoucherMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->voucherService->voidVoucher('nope', 'admin');
    }
}
