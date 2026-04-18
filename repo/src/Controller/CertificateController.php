<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Certificate;
use App\Exception\AuthException;
use App\Exception\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthorizationService;
use App\Service\AuthService;
use App\Service\CertificateService;
use App\Service\RbacService;
use App\Service\SessionContext;

final class CertificateController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private AuthorizationService $authz,
        private CertificateService $certs,
    ) {
    }

    public function issue(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'certificate.issue');
        $traineeId = (string) $req->input('traineeId', '');
        // Object-level guard: supervisors can only issue certificates to
        // trainees they have actually worked with. Admins bypass.
        $this->authz->assertSupervisorActsOnKnownTrainee($ctx, $traineeId);
        $cert = $this->certs->issue(
            $traineeId,
            (string) $req->input('rankId', ''),
            $ctx->getUserId(),
        );
        return Response::json($this->serialize($cert), 201);
    }

    public function verify(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'certificate.verify');
        $cert = $this->certs->verify($vars['code']);
        return Response::json($this->serialize($cert) + ['valid' => $cert->isValid()]);
    }

    public function revoke(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'certificate.revoke');
        $cert = $this->certs->revoke($vars['id'], $ctx->getUserId());
        return Response::json($this->serialize($cert));
    }

    public function listMine(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'certificate.view.own');
        $certs = array_map(
            fn ($c) => $this->serialize($c),
            $this->certs->findByTrainee($ctx->getUserId()),
        );
        return Response::json(['certificates' => $certs]);
    }

    public function listAll(Request $req): Response
    {
        $ctx = $this->context($req);
        // The admin-only list action reuses the same key as revoke so the
        // RBAC matrix stays compact.
        $this->rbac->authorize($ctx, 'certificate.revoke');
        $certs = array_map(
            fn ($c) => $this->serialize($c),
            $this->certs->listAll(),
        );
        return Response::json(['certificates' => $certs]);
    }

    public function download(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'certificate.verify');
        $cert = $this->certs->findById($vars['id']);
        if ($cert === null) {
            throw new NotFoundException('certificate not found');
        }
        $this->authz->assertCertificateAccess($ctx, $cert->getTraineeId());
        $pdf = $this->certs->readPdf($vars['id']);
        return Response::json(['pdf' => base64_encode($pdf)]);
    }

    private function context(Request $req): SessionContext
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
    private function serialize(Certificate $c): array
    {
        return [
            'id' => $c->getId(),
            'traineeId' => $c->getTraineeId(),
            'rankId' => $c->getRankId(),
            'verificationCode' => $c->getVerificationCode(),
            'status' => $c->getStatus(),
            'issuedAt' => $c->getIssuedAt()->format(DATE_ATOM),
        ];
    }
}
