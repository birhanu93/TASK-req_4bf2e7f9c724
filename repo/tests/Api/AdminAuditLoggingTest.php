<?php

declare(strict_types=1);

namespace App\Tests\Api;

/**
 * Sensitive admin operations — snapshot export and storage tiering — must
 * leave an audit trail with actor, action, timestamp, and meaningful
 * metadata about the operation (paths moved, manifest, etc.).
 */
final class AdminAuditLoggingTest extends ApiTestCase
{
    public function testTieringIsAudited(): void
    {
        $adminToken = $this->seedAdmin();
        $adminId = $this->kernel->users->findByUsername('admin')->getId();

        $res = $this->call('POST', '/api/admin/storage/tier', [], $adminToken);
        self::assertSame(200, $res->getStatus());

        $logs = array_values(array_filter(
            $this->kernel->auditLogs->findAll(),
            fn ($l) => $l->getAction() === 'admin.storage.tier',
        ));
        self::assertCount(1, $logs);
        $log = $logs[0];
        self::assertSame($adminId, $log->getActorId());
        self::assertSame('storage', $log->getEntityType());
        self::assertNotNull($log->getOccurredAt());
        self::assertArrayHasKey('snapshot', $log->getBefore());
        self::assertArrayHasKey('snapshot', $log->getAfter());
        self::assertArrayHasKey('movedCount', $log->getAfter());
        self::assertArrayHasKey('keptCount', $log->getAfter());
        self::assertArrayHasKey('movedPaths', $log->getAfter());
    }

    public function testSnapshotExportIsAudited(): void
    {
        $adminToken = $this->seedAdmin();
        $adminId = $this->kernel->users->findByUsername('admin')->getId();

        $res = $this->call('POST', '/api/admin/snapshots', [], $adminToken);
        self::assertSame(200, $res->getStatus());

        $logs = array_values(array_filter(
            $this->kernel->auditLogs->findAll(),
            fn ($l) => $l->getAction() === 'admin.snapshot.export',
        ));
        self::assertCount(1, $logs);
        $log = $logs[0];
        self::assertSame($adminId, $log->getActorId());
        self::assertSame('snapshot', $log->getEntityType());
        self::assertNotEmpty($log->getEntityId());
        self::assertArrayHasKey('path', $log->getAfter());
        self::assertArrayHasKey('manifest', $log->getAfter());
    }

    public function testNonAdminCannotTriggerTiering(): void
    {
        $this->seedAdmin();
        $token = $this->seedUser('sup', 'pw-12345', [\App\Service\Roles::SUPERVISOR], \App\Service\Roles::SUPERVISOR);
        $res = $this->call('POST', '/api/admin/storage/tier', [], $token);
        self::assertSame(403, $res->getStatus());

        $hits = array_filter(
            $this->kernel->auditLogs->findAll(),
            fn ($l) => $l->getAction() === 'admin.storage.tier',
        );
        self::assertCount(0, $hits, 'denied requests must not write an audit row');
    }
}
