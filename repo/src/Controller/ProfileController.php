<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AuthException;
use App\Exception\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthorizationService;
use App\Service\AuthService;
use App\Service\ProfileService;
use App\Service\RbacService;
use App\Service\SessionContext;

final class ProfileController
{
    public function __construct(
        private AuthService $auth,
        private AuthorizationService $authz,
        private ProfileService $profiles,
        private RbacService $rbac,
    ) {
    }

    public function get(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'profile.read');
        $userId = (string) ($req->query('userId') ?? $ctx->getUserId());
        $this->authz->assertProfileAccess($ctx, $userId);
        return Response::json(['profile' => $this->profiles->read($userId)]);
    }

    public function update(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'profile.update');
        $userId = (string) ($req->query('userId') ?? $ctx->getUserId());
        $this->authz->assertProfileAccess($ctx, $userId);
        $data = $req->input('profile');
        if (!is_array($data)) {
            throw new ValidationException('profile must be an object');
        }
        $this->profiles->write($userId, $data, $ctx->getUserId());
        return Response::noContent();
    }

    private function context(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }
}
