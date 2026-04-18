<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Resource;
use App\Entity\ResourceReservation;
use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthService;
use App\Service\RbacService;
use App\Service\ResourceService;
use App\Service\Roles;
use App\Service\SessionContext;

final class ResourceController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private ResourceService $resources,
    ) {
    }

    public function create(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'resource.manage');
        $resource = $this->resources->create(
            (string) $req->input('name', ''),
            (string) $req->input('kind', ''),
            $ctx->getUserId(),
        );
        return Response::json($this->serialize($resource), 201);
    }

    public function list(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'resource.view');
        $out = array_map(fn ($r) => $this->serialize($r), $this->resources->list());
        return Response::json(['resources' => $out]);
    }

    public function retire(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'resource.manage');
        $resource = $this->resources->retire($vars['id'], $ctx->getUserId());
        return Response::json($this->serialize($resource));
    }

    public function reservations(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'resource.view');
        $list = array_map(fn ($r) => $this->serializeReservation($r), $this->resources->reservationsOf($vars['id']));
        return Response::json(['reservations' => $list]);
    }

    private function context(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }

    private function requireAdmin(Request $req): SessionContext
    {
        $ctx = $this->context($req);
        if ($ctx->getActiveRole() !== Roles::ADMIN) {
            throw new AccessDeniedException('admin role required');
        }
        return $ctx;
    }

    /** @return array<string,mixed> */
    private function serialize(Resource $r): array
    {
        return [
            'id' => $r->getId(),
            'name' => $r->getName(),
            'kind' => $r->getKind(),
            'status' => $r->getStatus(),
            'createdAt' => $r->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeReservation(ResourceReservation $r): array
    {
        return [
            'id' => $r->getId(),
            'resourceId' => $r->getResourceId(),
            'sessionId' => $r->getSessionId(),
            'startsAt' => $r->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $r->getEndsAt()->format(DATE_ATOM),
            'reservedByUserId' => $r->getReservedByUserId(),
            'createdAt' => $r->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
