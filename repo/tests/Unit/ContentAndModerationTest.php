<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\ModerationItem;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Service\ContentChecker;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class ContentAndModerationTest extends TestCase
{
    public function testCheckTextEmpty(): void
    {
        $c = new ContentChecker();
        $this->expectException(ValidationException::class);
        $c->checkText('   ');
    }

    public function testCheckTextProhibited(): void
    {
        $c = new ContentChecker(['badword']);
        $this->expectException(ValidationException::class);
        $c->checkText('Some BadWord here');
    }

    public function testCheckTextOk(): void
    {
        $c = new ContentChecker(['badword']);
        $c->checkText('good content');
        self::assertSame(64, strlen($c->checksum('x')));
    }

    public function testCheckFile(): void
    {
        $c = new ContentChecker();
        $c->checkFile('image/png', 1000);
        self::assertTrue(true);
    }

    public function testCheckFileBadMime(): void
    {
        $c = new ContentChecker();
        $this->expectException(ValidationException::class);
        $c->checkFile('application/exe', 100);
    }

    public function testCheckFileBadSize(): void
    {
        $c = new ContentChecker();
        $this->expectException(ValidationException::class);
        $c->checkFile('text/plain', 0);
    }

    public function testSubmitAndApprove(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hello world');
        self::assertSame(ModerationItem::STATUS_PENDING, $item->getStatus());
        $approved = $k->moderationService->approve($item->getId(), 'admin', 80, 'nice');
        self::assertSame(ModerationItem::STATUS_APPROVED, $approved->getStatus());
        self::assertCount(0, $k->moderationService->pending());
    }

    public function testSubmitInvalidKind(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->moderationService->submit('u1', 'other', 'x');
    }

    public function testSubmitProhibitedWord(): void
    {
        $k = Factory::kernel();
        $this->expectException(ValidationException::class);
        $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'forbidden content here');
    }

    public function testSubmitDuplicate(): void
    {
        $k = Factory::kernel();
        $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'same');
        $this->expectException(ConflictException::class);
        $k->moderationService->submit('u2', ModerationItem::KIND_NOTE, 'same');
    }

    public function testApproveInvalidScore(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hi');
        $this->expectException(ValidationException::class);
        $k->moderationService->approve($item->getId(), 'admin', 200);
    }

    public function testApproveMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->moderationService->approve('nope', 'admin', 50);
    }

    public function testApproveAlreadyDecided(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hi');
        $k->moderationService->approve($item->getId(), 'admin', 50);
        $this->expectException(ConflictException::class);
        $k->moderationService->approve($item->getId(), 'admin', 50);
    }

    public function testReject(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hi');
        $rej = $k->moderationService->reject($item->getId(), 'admin', 'low quality');
        self::assertSame(ModerationItem::STATUS_REJECTED, $rej->getStatus());
    }

    public function testRejectReasonRequired(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hi');
        $this->expectException(ValidationException::class);
        $k->moderationService->reject($item->getId(), 'admin', '');
    }

    public function testRejectMissing(): void
    {
        $k = Factory::kernel();
        $this->expectException(NotFoundException::class);
        $k->moderationService->reject('nope', 'admin', 'r');
    }

    public function testRejectAlreadyDecided(): void
    {
        $k = Factory::kernel();
        $item = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'hi');
        $k->moderationService->reject($item->getId(), 'admin', 'no');
        $this->expectException(ConflictException::class);
        $k->moderationService->reject($item->getId(), 'admin', 'no');
    }

    public function testBulkApproveMixed(): void
    {
        $k = Factory::kernel();
        $a = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'one');
        $b = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'two');
        $res = $k->moderationService->bulkApprove([$a->getId(), $b->getId(), 'bad'], 'admin', 70);
        self::assertCount(2, $res['approved']);
        self::assertSame(['bad'], $res['failed']);
    }

    public function testBulkRejectMixed(): void
    {
        $k = Factory::kernel();
        $a = $k->moderationService->submit('u1', ModerationItem::KIND_NOTE, 'one');
        $res = $k->moderationService->bulkReject([$a->getId(), 'bad'], 'admin', 'bad');
        self::assertCount(1, $res['rejected']);
        self::assertSame(['bad'], $res['failed']);
    }
}
