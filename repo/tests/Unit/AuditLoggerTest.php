<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    public function testRecordAndHistory(): void
    {
        $k = Factory::kernel();
        $log = $k->audit->record('actor', 'action', 'T', 'id', ['a' => 1], ['a' => 2]);
        self::assertSame('T', $log->getEntityType());
        $history = $k->audit->history('T', 'id');
        self::assertCount(1, $history);
    }
}
