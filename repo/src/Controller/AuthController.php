<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\Contract\SystemStateRepositoryInterface;
use App\Service\AuthService;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SessionContext;
use Symfony\Component\HttpFoundation\Cookie;

final class AuthController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private SystemStateRepositoryInterface $systemState,
        private bool $cookieSecure = true,
    ) {
    }

    /**
     * One-shot bootstrap endpoint: creates the first admin. Succeeds at most
     * once; replay attempts return 409 regardless of payload.
     */
    public function bootstrap(Request $req): Response
    {
        $username = (string) $req->input('username', '');
        $password = (string) $req->input('password', '');
        if ($username === '' || $password === '') {
            throw new AuthException('username and password required');
        }
        $user = $this->auth->bootstrapAdmin($username, $password);
        return Response::json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ], 201);
    }

    /**
     * Register a user. Requires an authenticated admin. The legacy "bootstrap
     * in body" flow has been removed — the dedicated /bootstrap endpoint is
     * the only way to create the first admin.
     */
    public function register(Request $req): Response
    {
        $actor = $this->requireAdmin($req);
        $username = (string) $req->input('username', '');
        $password = (string) $req->input('password', '');
        $roles = (array) $req->input('roles', []);
        $user = $this->auth->register($username, $password, array_map('strval', $roles), $actor->getUserId());
        return Response::json([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ], 201);
    }

    public function login(Request $req): Response
    {
        $username = (string) $req->input('username', '');
        $password = (string) $req->input('password', '');
        $result = $this->auth->login($username, $password);
        return Response::json([
            'userId' => $result['user']->getId(),
            'username' => $result['user']->getUsername(),
            'availableRoles' => $result['availableRoles'],
        ]);
    }

    /**
     * Select an active role. Requires re-proof of password on every call.
     * The userId the caller once saw in a login response is never enough —
     * we re-authenticate the credentials end-to-end before minting a token.
     */
    public function selectRole(Request $req): Response
    {
        $username = (string) $req->input('username', '');
        $password = (string) $req->input('password', '');
        $role = (string) $req->input('role', '');
        if ($username === '' || $password === '' || $role === '') {
            throw new AuthException('username, password, and role required');
        }
        $ctx = $this->auth->selectRole($username, $password, $role);
        return $this->withSessionCookie(
            Response::json([
                'token' => $ctx->getToken(),
                'role' => $ctx->getActiveRole(),
                'userId' => $ctx->getUserId(),
            ]),
            $ctx->getToken(),
        );
    }

    /**
     * Switch role, requires password re-proof.
     */
    public function switchRole(Request $req): Response
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        $password = (string) $req->input('password', '');
        $role = (string) $req->input('role', '');
        if ($password === '' || $role === '') {
            throw new AuthException('password and role required');
        }
        $ctx = $this->auth->switchRole($token, $password, $role);
        return $this->withSessionCookie(
            Response::json([
                'token' => $ctx->getToken(),
                'role' => $ctx->getActiveRole(),
            ]),
            $ctx->getToken(),
        );
    }

    public function logout(Request $req): Response
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        $this->auth->logout($token);
        $response = Response::noContent();
        $response->withCookie($this->clearSessionCookie());
        return $response;
    }

    private function withSessionCookie(Response $response, string $token): Response
    {
        $expires = time() + AuthService::SESSION_TTL_SECONDS;
        $cookie = Cookie::create(Request::SESSION_COOKIE)
            ->withValue($token)
            ->withExpires($expires)
            ->withPath('/')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
        $response->withCookie($cookie);
        return $response;
    }

    private function clearSessionCookie(): Cookie
    {
        return Cookie::create(Request::SESSION_COOKIE)
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function changePassword(Request $req): Response
    {
        $ctx = $this->requireContext($req);
        $this->auth->changePassword(
            $ctx->getUserId(),
            (string) $req->input('oldPassword', ''),
            (string) $req->input('newPassword', ''),
        );
        return Response::noContent();
    }

    public function me(Request $req): Response
    {
        $ctx = $this->requireContext($req);
        return Response::json([
            'userId' => $ctx->getUserId(),
            'role' => $ctx->getActiveRole(),
        ]);
    }

    private function requireContext(Request $req): SessionContext
    {
        $token = $req->bearerToken();
        if ($token === null) {
            throw new AuthException('missing bearer token');
        }
        return $this->auth->authenticate($token);
    }

    private function requireAdmin(Request $req): SessionContext
    {
        $ctx = $this->requireContext($req);
        if ($ctx->getActiveRole() !== Roles::ADMIN) {
            throw new AccessDeniedException('admin role required');
        }
        return $ctx;
    }
}
