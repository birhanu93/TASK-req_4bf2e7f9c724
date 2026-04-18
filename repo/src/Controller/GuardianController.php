<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Device;
use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AssessmentService;
use App\Service\AuthorizationService;
use App\Service\AuthService;
use App\Service\GuardianService;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SessionContext;

final class GuardianController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private AuthorizationService $authz,
        private GuardianService $guardians,
        private ?AssessmentService $assessments = null,
    ) {
    }

    public function link(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'guardian.link');
        $guardianId = $ctx->getActiveRole() === Roles::ADMIN
            ? (string) $req->input('guardianId', $ctx->getUserId())
            : $ctx->getUserId();
        $link = $this->guardians->linkChild(
            $guardianId,
            (string) $req->input('childId', ''),
        );
        return Response::json([
            'id' => $link->getId(),
            'guardianId' => $link->getGuardianId(),
            'childId' => $link->getChildId(),
            'linkedAt' => $link->getLinkedAt()->format(DATE_ATOM),
        ], 201);
    }

    public function approveDevice(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'guardian.approve_device');
        $childId = (string) $req->input('childId', '');
        $this->authz->assertChildAccess($ctx, $childId);
        $device = $this->guardians->approveDevice(
            $ctx->getUserId(),
            $childId,
            (string) $req->input('deviceName', ''),
            (string) $req->input('fingerprint', ''),
        );
        return Response::json($this->serialize($device), 201);
    }

    public function remoteLogout(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'guardian.remote_logout');
        $device = $this->guardians->remoteLogout($ctx->getUserId(), $vars['id']);
        return Response::json($this->serialize($device));
    }

    public function children(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'guardian.link');
        $links = $this->guardians->childrenOf($ctx->getUserId());
        $out = [];
        foreach ($links as $l) {
            $out[] = [
                'id' => $l->getId(),
                'childId' => $l->getChildId(),
                'linkedAt' => $l->getLinkedAt()->format(DATE_ATOM),
            ];
        }
        return Response::json(['children' => $out]);
    }

    public function listDevices(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'guardian.approve_device');
        $childId = (string) $vars['childId'];
        $this->authz->assertChildAccess($ctx, $childId);
        $devices = array_map(fn ($d) => $this->serialize($d), $this->guardians->devicesOf($childId));
        return Response::json(['devices' => $devices]);
    }

    public function childProgress(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $childId = (string) $vars['childId'];
        $this->authz->assertChildAccess($ctx, $childId);
        if ($this->assessments === null) {
            throw new \RuntimeException('assessment service unavailable');
        }
        return Response::json($this->assessments->progress($childId));
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
    private function serialize(Device $d): array
    {
        return [
            'id' => $d->getId(),
            'userId' => $d->getUserId(),
            'deviceName' => $d->getDeviceName(),
            'fingerprint' => $d->getFingerprint(),
            'status' => $d->getStatus(),
            'approvedAt' => $d->getApprovedAt()->format(DATE_ATOM),
        ];
    }
}
