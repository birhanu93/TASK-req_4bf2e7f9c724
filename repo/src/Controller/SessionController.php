<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SupervisorLeave;
use App\Entity\TrainingSession;
use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Exception\NotFoundException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuthService;
use App\Service\BookingService;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SchedulingService;
use App\Service\SessionContext;

final class SessionController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private SchedulingService $scheduling,
        private BookingService $bookings,
    ) {
    }

    public function create(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'session.create');
        $resourceIds = array_values(array_map('strval', (array) $req->input('resourceIds', [])));
        $session = $this->scheduling->create(
            $ctx->getUserId(),
            (string) $req->input('title', ''),
            new \DateTimeImmutable((string) $req->input('startsAt', 'now')),
            new \DateTimeImmutable((string) $req->input('endsAt', 'now')),
            (int) $req->input('capacity', 0),
            (int) $req->input('bufferMinutes', 10),
            $resourceIds,
        );
        return Response::json($this->serialize($session), 201);
    }

    public function close(Request $req, array $vars): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'session.close');
        $session = $this->scheduling->findSession($vars['id']);
        if ($ctx->getActiveRole() !== Roles::ADMIN && $session->getSupervisorId() !== $ctx->getUserId()) {
            throw new AccessDeniedException('supervisor does not own this session');
        }
        $this->scheduling->close($vars['id'], $ctx->getUserId());
        return Response::noContent();
    }

    public function list(Request $req): Response
    {
        $this->context($req);
        $fromStr = $req->query('from');
        $from = $fromStr !== null ? new \DateTimeImmutable($fromStr) : new \DateTimeImmutable('1970-01-01');
        $sessions = $this->scheduling->listAvailable($from);
        $out = [];
        foreach ($sessions as $s) {
            $out[] = $this->serialize($s) + ['availability' => $this->bookings->availability($s->getId())];
        }
        return Response::json(['sessions' => $out]);
    }

    public function availability(Request $req, array $vars): Response
    {
        $this->context($req);
        return Response::json(['sessionId' => $vars['id'], 'availability' => $this->bookings->availability($vars['id'])]);
    }

    public function addLeave(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'session.create');
        $leave = $this->scheduling->addLeave(
            $ctx->getUserId(),
            new \DateTimeImmutable((string) $req->input('startsAt', 'now')),
            new \DateTimeImmutable((string) $req->input('endsAt', 'now')),
            (string) $req->input('rule', SupervisorLeave::RULE_ONE_OFF),
            $req->input('reason') !== null ? (string) $req->input('reason') : null,
        );
        return Response::json($this->serializeLeave($leave), 201);
    }

    public function listLeaves(Request $req): Response
    {
        $ctx = $this->context($req);
        $this->rbac->authorize($ctx, 'session.create');
        $supervisorId = (string) ($req->query('supervisorId') ?? $ctx->getUserId());
        if ($supervisorId !== $ctx->getUserId() && $ctx->getActiveRole() !== Roles::ADMIN) {
            throw new AccessDeniedException('cannot view another supervisor\'s leaves');
        }
        return Response::json([
            'leaves' => array_map(fn ($l) => $this->serializeLeave($l), $this->scheduling->leavesOf($supervisorId)),
        ]);
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
    private function serialize(TrainingSession $s): array
    {
        return [
            'id' => $s->getId(),
            'supervisorId' => $s->getSupervisorId(),
            'title' => $s->getTitle(),
            'startsAt' => $s->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $s->getEndsAt()->format(DATE_ATOM),
            'capacity' => $s->getCapacity(),
            'bufferMinutes' => $s->getBufferMinutes(),
            'status' => $s->getStatus(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeLeave(SupervisorLeave $l): array
    {
        return [
            'id' => $l->getId(),
            'supervisorId' => $l->getSupervisorId(),
            'startsAt' => $l->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $l->getEndsAt()->format(DATE_ATOM),
            'rule' => $l->getRecurrenceRule(),
            'reason' => $l->getReason(),
        ];
    }
}
