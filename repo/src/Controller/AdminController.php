<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\AccessDeniedException;
use App\Exception\AuthException;
use App\Http\Request;
use App\Http\Response;
use App\Service\AuditLogger;
use App\Service\AuthService;
use App\Service\Clock;
use App\Service\Keyring;
use App\Service\RbacService;
use App\Service\Roles;
use App\Service\SessionContext;
use App\Service\SnapshotExporter;
use App\Service\StorageTieringService;

final class AdminController
{
    public function __construct(
        private AuthService $auth,
        private RbacService $rbac,
        private AuditLogger $audit,
        private StorageTieringService $tiering,
        private SnapshotExporter $snapshots,
        private Keyring $keyring,
        private Clock $clock,
    ) {
    }

    public function auditHistory(Request $req, array $vars): Response
    {
        $ctx = $this->requireAdmin($req);
        $this->rbac->authorize($ctx, 'audit.read');
        $logs = $this->audit->history($vars['type'], $vars['id']);
        $out = [];
        foreach ($logs as $log) {
            $out[] = [
                'id' => $log->getId(),
                'actorId' => $log->getActorId(),
                'action' => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'entityId' => $log->getEntityId(),
                'occurredAt' => $log->getOccurredAt()->format(DATE_ATOM),
                'before' => $log->getBefore(),
                'after' => $log->getAfter(),
            ];
        }
        return Response::json(['logs' => $out]);
    }

    public function runTiering(Request $req): Response
    {
        $ctx = $this->requireAdmin($req);
        $this->rbac->authorize($ctx, 'storage.tier');
        $before = $this->tiering->snapshot();
        $result = $this->tiering->tier();
        $after = $this->tiering->snapshot();
        // Unique audit row id per run — tiering is not tied to a single
        // entity so we synthesise a timestamped id for traceability.
        $runId = 'tier-' . $this->clock->now()->format('Ymd\THis') . '-' . bin2hex(random_bytes(3));
        $this->audit->record(
            $ctx->getUserId(),
            'admin.storage.tier',
            'storage',
            $runId,
            ['snapshot' => $before],
            [
                'snapshot' => $after,
                'movedCount' => count($result['moved']),
                'keptCount' => count($result['kept']),
                'movedPaths' => array_map('basename', $result['moved']),
            ],
        );
        return Response::json([
            'movedCount' => count($result['moved']),
            'keptCount' => count($result['kept']),
            'snapshot' => $after,
        ]);
    }

    public function snapshot(Request $req): Response
    {
        $ctx = $this->requireAdmin($req);
        $this->rbac->authorize($ctx, 'admin.snapshot.export');
        $result = $this->snapshots->export();
        $this->audit->record(
            $ctx->getUserId(),
            'admin.snapshot.export',
            'snapshot',
            (string) $result['path'],
            [],
            [
                'path' => $result['path'],
                'manifest' => $result['manifest'],
            ],
        );
        return Response::json([
            'path' => $result['path'],
            'manifest' => $result['manifest'],
        ]);
    }


    public function rotateKey(Request $req): Response
    {
        $ctx = $this->requireAdmin($req);
        $this->rbac->authorize($ctx, 'admin.keys.rotate');
        $key = $this->keyring->rotate();
        $this->audit->record($ctx->getUserId(), 'keyring.rotate', 'profile_key', (string) $key->getVersion(), [], [
            'newVersion' => $key->getVersion(),
        ]);
        return Response::json([
            'newKeyVersion' => $key->getVersion(),
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

    private function requireAdmin(Request $req): SessionContext
    {
        $ctx = $this->context($req);
        if ($ctx->getActiveRole() !== Roles::ADMIN) {
            throw new AccessDeniedException('admin role required');
        }
        return $ctx;
    }
}
