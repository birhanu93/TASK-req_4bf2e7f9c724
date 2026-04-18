<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class SnapshotExporterTest extends TestCase
{
    public function testExportCreatesManifestAndSections(): void
    {
        $k = Factory::kernel();
        $k->auth->register('admin', 'pw-12345', ['admin']);
        $k->auth->register('alice', 'pw-12345', ['trainee']);

        $result = $k->snapshotExporter->export();
        self::assertDirectoryExists($result['path']);
        self::assertFileExists($result['path'] . '/manifest.json');
        self::assertFileExists($result['path'] . '/users.json');
        self::assertFileExists($result['path'] . '/audit_log.json');

        $manifest = $result['manifest'];
        self::assertSame(2, $manifest['sections']['users']['count']);
        self::assertArrayHasKey('sha256', $manifest['sections']['users']);
    }

    public function testExportSectionsAreJson(): void
    {
        $k = Factory::kernel();
        $k->auth->register('admin', 'pw-12345', ['admin']);
        $result = $k->snapshotExporter->export();
        $users = json_decode((string) file_get_contents($result['path'] . '/users.json'), true);
        self::assertIsArray($users);
        self::assertSame('admin', $users[0]['username']);
    }
}
