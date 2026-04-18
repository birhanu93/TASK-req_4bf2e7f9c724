<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Voucher;
use App\Entity\VoucherClaim;
use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthService;
use App\Service\RbacService;
use App\Service\VoucherService;

final class VoucherController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private VoucherService $vouchers,
    ) {
    }

    public function issue(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.create');
        $voucher = $this->vouchers->issue(
            $ctx->getUserId(),
            (string) $req->input('code', ''),
            (int) $req->input('discountCents', 0),
            (int) $req->input('minSpendCents', 0),
            (int) $req->input('claimLimit', 0),
            new \DateTimeImmutable((string) $req->input('expiresAt', 'now')),
        );
        return Response::json($this->serializeVoucher($voucher), 201);
    }

    public function claim(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.claim');
        $claim = $this->vouchers->claim(
            (string) $req->input('code', ''),
            $ctx->getUserId(),
            (string) $req->input('idempotencyKey', ''),
        );
        return Response::json($this->serializeClaim($claim), 201);
    }

    public function redeem(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.redeem');
        $result = $this->vouchers->redeem(
            $vars['id'],
            $ctx->getUserId(),
            (int) $req->input('orderAmountCents', 0),
            (string) $req->input('redemptionIdempotencyKey', ''),
        );
        return Response::json([
            'claim' => $this->serializeClaim($result['claim']),
            'discountCents' => $result['discountCents'],
            'replayed' => $result['replayed'],
        ]);
    }

    public function voidClaim(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.void');
        $claim = $this->vouchers->voidClaim($vars['id'], $ctx->getUserId());
        return Response::json($this->serializeClaim($claim));
    }

    public function voidVoucher(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.void');
        $voucher = $this->vouchers->voidVoucher($vars['id'], $ctx->getUserId());
        return Response::json($this->serializeVoucher($voucher));
    }

    public function describe(Request $req, array $vars): Response
    {
        $this->context($req);
        $voucher = $this->vouchers->describe($vars['code']);
        return Response::json($this->serializeVoucher($voucher));
    }

    public function listAll(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'voucher.create');
        $vouchers = array_map(
            fn ($v) => $this->serializeVoucher($v),
            $this->vouchers->listAll(),
        );
        return Response::json(['vouchers' => $vouchers]);
    }

    private function context(Request $req)
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeVoucher(Voucher $v): array
    {
        return [
            'id' => $v->getId(),
            'code' => $v->getCode(),
            'discountCents' => $v->getDiscountCents(),
            'minSpendCents' => $v->getMinSpendCents(),
            'claimLimit' => $v->getClaimLimit(),
            'claimed' => $v->getClaimed(),
            'remaining' => $v->remaining(),
            'expiresAt' => $v->getExpiresAt()->format(DATE_ATOM),
            'status' => $v->getStatus(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeClaim(VoucherClaim $c): array
    {
        return [
            'id' => $c->getId(),
            'voucherId' => $c->getVoucherId(),
            'userId' => $c->getUserId(),
            'status' => $c->getStatus(),
            'idempotencyKey' => $c->getIdempotencyKey(),
            'redeemedAt' => $c->getRedeemedAt()?->format(DATE_ATOM),
            'redemptionIdempotencyKey' => $c->getRedemptionIdempotencyKey(),
            'redeemedOrderAmountCents' => $c->getRedeemedOrderAmountCents(),
            'redeemedDiscountCents' => $c->getRedeemedDiscountCents(),
        ];
    }
}
