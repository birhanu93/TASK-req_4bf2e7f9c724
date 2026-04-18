<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\VoucherClaim;
use App\Exception\ConflictException;
use App\Exception\ValidationException;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Redemption idempotency contract:
 *
 * - The caller MUST supply a non-empty redemption idempotency key.
 * - Repeat calls with the same key replay the stored outcome instead of
 *   mutating state.
 * - Reusing a key on a different claim or user is a 409 conflict.
 * - The atomic LOCKED -> REDEEMED transition blocks concurrent redeems of
 *   the same claim — only one of them succeeds, the rest see 'claim not
 *   redeemable'.
 */
final class VoucherRedemptionIdempotencyTest extends TestCase
{
    private function seedVoucher(\App\App\Kernel $k): VoucherClaim
    {
        $k->voucherService->issue(
            'admin',
            'SAVE25',
            2500,
            15000,
            3,
            $k->clock->now()->modify('+30 days'),
        );
        return $k->voucherService->claim('SAVE25', 'u1', 'idem-1');
    }

    public function testRedemptionKeyIsRequired(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $this->expectException(ValidationException::class);
        $k->voucherService->redeem($claim->getId(), 'u1', 20000, '');
    }

    public function testRetryWithSameKeyReplaysResultWithoutMutating(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);

        $first = $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-1');
        self::assertFalse($first['replayed']);
        self::assertSame(2500, $first['discountCents']);
        self::assertSame(VoucherClaim::STATUS_REDEEMED, $first['claim']->getStatus());

        $second = $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-1');
        self::assertTrue($second['replayed']);
        self::assertSame(2500, $second['discountCents']);
        self::assertSame($first['claim']->getRedeemedAt(), $second['claim']->getRedeemedAt());
    }

    public function testRetryWithSameKeyButDifferentClaimIsRejected(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $claim2 = $k->voucherService->claim('SAVE25', 'u2', 'idem-2');

        $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-shared');

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessageMatches('/different claim/');
        $k->voucherService->redeem($claim2->getId(), 'u2', 20000, 'rk-shared');
    }

    public function testRetryWithSameKeyByDifferentUserIsRejected(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-userbound');

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessageMatches('/different user/');
        $k->voucherService->redeem($claim->getId(), 'u2', 20000, 'rk-userbound');
    }

    public function testAtomicTransitionBlocksSecondRedeemWithDifferentKey(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-first');

        // A new call with a different redemption key but for the same (now
        // redeemed) claim must not flip the claim back or double-discount.
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessageMatches('/not redeemable/');
        $k->voucherService->redeem($claim->getId(), 'u1', 20000, 'rk-second');
    }

    public function testOutcomePersistedOnClaim(): void
    {
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $k->voucherService->redeem($claim->getId(), 'u1', 22500, 'rk-persist');

        $reloaded = $k->claims->find($claim->getId());
        self::assertNotNull($reloaded);
        self::assertSame('rk-persist', $reloaded->getRedemptionIdempotencyKey());
        self::assertSame(22500, $reloaded->getRedeemedOrderAmountCents());
        self::assertSame(2500, $reloaded->getRedeemedDiscountCents());
    }

    public function testConditionalTransitionIsNoOpOnAlreadyRedeemedClaim(): void
    {
        // Direct call to the repository-level atomic method, bypassing
        // the service. Proves the affected-row guard itself rejects a
        // second transition even under the wildest race.
        $k = Factory::kernel();
        $claim = $this->seedVoucher($k);
        $now = $k->clock->now();

        self::assertTrue($k->claims->markRedeemedIfLocked(
            $claim->getId(),
            $now,
            'rk-first-atomic',
            20000,
            2500,
        ));
        self::assertFalse($k->claims->markRedeemedIfLocked(
            $claim->getId(),
            $now,
            'rk-second-atomic',
            20000,
            2500,
        ));

        $reloaded = $k->claims->find($claim->getId());
        self::assertSame('rk-first-atomic', $reloaded?->getRedemptionIdempotencyKey());
    }
}
