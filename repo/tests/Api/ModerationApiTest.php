<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ModerationItem;
use App\Service\Roles;

final class ModerationApiTest extends ApiTestCase
{
    public function testSubmitApproveRejectFlow(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);

        $submit = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_NOTE,
            'content' => 'session went well',
        ], $trainee);
        self::assertSame(201, $submit->getStatus());
        $id = $submit->getBody()['id'];

        $pending = $this->call('GET', '/api/moderation/pending', [], $admin);
        self::assertCount(1, $pending->getBody()['items']);

        $approve = $this->call('POST', "/api/moderation/{$id}/approve", [
            'score' => 80,
            'reason' => 'clear',
        ], $admin);
        self::assertSame(200, $approve->getStatus());

        $submit2 = $this->call('POST', '/api/moderation', [
            'kind' => ModerationItem::KIND_EVIDENCE,
            'content' => 'some evidence detail',
        ], $trainee);
        $id2 = $submit2->getBody()['id'];

        $reject = $this->call('POST', "/api/moderation/{$id2}/reject", ['reason' => 'unclear'], $admin);
        self::assertSame(200, $reject->getStatus());
    }

    public function testBulkEndpoints(): void
    {
        $admin = $this->seedUser('admin', 'pass-1234', [Roles::ADMIN], Roles::ADMIN);
        $trainee = $this->seedUser('t1', 'pass-1234', [Roles::TRAINEE], Roles::TRAINEE);
        $ids = [];
        foreach (['A', 'B'] as $c) {
            $res = $this->call('POST', '/api/moderation', [
                'kind' => ModerationItem::KIND_NOTE,
                'content' => "content {$c}",
            ], $trainee);
            $ids[] = $res->getBody()['id'];
        }
        $approve = $this->call('POST', '/api/moderation/bulk-approve', [
            'ids' => [$ids[0]],
            'score' => 70,
        ], $admin);
        self::assertCount(1, $approve->getBody()['approved']);

        $reject = $this->call('POST', '/api/moderation/bulk-reject', [
            'ids' => [$ids[1]],
            'reason' => 'low',
        ], $admin);
        self::assertCount(1, $reject->getBody()['rejected']);
    }

    public function testUnauthenticated(): void
    {
        self::assertSame(401, $this->call('POST', '/api/moderation')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/moderation/x/approve')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/moderation/x/reject')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/moderation/bulk-approve')->getStatus());
        self::assertSame(401, $this->call('POST', '/api/moderation/bulk-reject')->getStatus());
        self::assertSame(401, $this->call('GET', '/api/moderation/pending')->getStatus());
    }
}
