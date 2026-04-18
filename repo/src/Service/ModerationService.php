<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ModerationAttachment;
use App\Entity\ModerationItem;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\ModerationAttachmentRepositoryInterface;
use App\Repository\Contract\ModerationRepositoryInterface;

final class ModerationService
{
    public function __construct(
        private ModerationRepositoryInterface $items,
        private ModerationAttachmentRepositoryInterface $attachments,
        private ContentChecker $checker,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
        private string $uploadRoot,
        private ?StorageTieringService $tiering = null,
    ) {
        if (!is_dir($this->uploadRoot) && !@mkdir($this->uploadRoot, 0700, true) && !is_dir($this->uploadRoot)) {
            throw new \RuntimeException('failed to create upload root');
        }
        if ($this->tiering !== null) {
            $this->tiering->registerStore('uploads', $this->uploadRoot, $this->uploadRoot . '-cold');
        }
    }

    /**
     * Read the raw bytes of an attachment by id. Transparently resolves cold
     * tier paths so callers never need to know where the artifact lives.
     */
    public function readAttachmentBytes(string $attachmentId): string
    {
        $attachment = $this->attachments->find($attachmentId);
        if ($attachment === null) {
            throw new NotFoundException('attachment not found');
        }
        $path = $attachment->getStoragePath();
        if (!is_file($path) && $this->tiering !== null) {
            $resolved = $this->tiering->resolve('uploads', basename($path));
            if ($resolved !== null) {
                $path = $resolved;
            }
        }
        if (!is_file($path)) {
            throw new ValidationException('attachment artifact missing');
        }
        return (string) file_get_contents($path);
    }

    public function submit(string $authorId, string $kind, string $content): ModerationItem
    {
        if (!in_array($kind, [ModerationItem::KIND_NOTE, ModerationItem::KIND_EVIDENCE, ModerationItem::KIND_FEEDBACK], true)) {
            throw new ValidationException('invalid moderation kind');
        }
        $this->checker->checkText($content);
        $checksum = $this->checker->checksum($content);
        if ($this->items->findByChecksum($checksum) !== null) {
            throw new ConflictException('duplicate content detected');
        }
        $item = new ModerationItem(
            $this->ids->generate(),
            $authorId,
            $kind,
            $content,
            $checksum,
            $this->clock->now(),
        );
        $this->items->save($item);
        $this->audit->record($authorId, 'moderation.submit', 'moderationItem', $item->getId(), [], ['kind' => $kind]);
        return $item;
    }

    public function attach(
        string $itemId,
        string $authorId,
        string $filename,
        string $mimeType,
        string $content,
    ): ModerationAttachment {
        $item = $this->items->find($itemId);
        if ($item === null) {
            throw new NotFoundException('moderation item not found');
        }
        if ($item->getAuthorId() !== $authorId) {
            throw new ValidationException('attachment author must match item author');
        }
        if ($item->getStatus() !== ModerationItem::STATUS_PENDING) {
            throw new ConflictException('cannot attach to decided item');
        }

        $sizeBytes = strlen($content);
        $this->checker->checkFile($mimeType, $sizeBytes);
        $this->checker->checkMagicBytes($mimeType, $content);
        $filename = basename($filename);
        if ($filename === '' || strpbrk($filename, "/\\\0") !== false) {
            throw new ValidationException('invalid attachment filename');
        }

        $checksum = hash('sha256', $content);
        $storagePath = rtrim($this->uploadRoot, '/') . '/' . $checksum . '-' . $filename;
        if (!is_file($storagePath)) {
            file_put_contents($storagePath, $content);
            @chmod($storagePath, 0600);
        }

        $attachment = new ModerationAttachment(
            $this->ids->generate(),
            $itemId,
            $filename,
            $mimeType,
            $sizeBytes,
            $checksum,
            $storagePath,
            $this->clock->now(),
        );
        $this->attachments->save($attachment);
        $this->audit->record(
            $authorId,
            'moderation.attach',
            'moderationAttachment',
            $attachment->getId(),
            [],
            ['itemId' => $itemId, 'mimeType' => $mimeType, 'sizeBytes' => $sizeBytes, 'checksum' => $checksum],
        );
        return $attachment;
    }

    public function approve(string $itemId, string $reviewerId, int $score, ?string $reason = null): ModerationItem
    {
        if ($score < 0 || $score > 100) {
            throw new ValidationException('score must be between 0 and 100');
        }
        $item = $this->items->find($itemId);
        if ($item === null) {
            throw new NotFoundException('moderation item not found');
        }
        if ($item->getStatus() !== ModerationItem::STATUS_PENDING) {
            throw new ConflictException('item already decided');
        }
        $before = ['status' => $item->getStatus()];
        $item->approve($reviewerId, $score, $reason);
        $this->items->save($item);
        $this->audit->record($reviewerId, 'moderation.approve', 'moderationItem', $item->getId(), $before, ['status' => $item->getStatus(), 'score' => $score]);
        return $item;
    }

    public function reject(string $itemId, string $reviewerId, string $reason): ModerationItem
    {
        if ($reason === '') {
            throw new ValidationException('reason is required for rejection');
        }
        $item = $this->items->find($itemId);
        if ($item === null) {
            throw new NotFoundException('moderation item not found');
        }
        if ($item->getStatus() !== ModerationItem::STATUS_PENDING) {
            throw new ConflictException('item already decided');
        }
        $before = ['status' => $item->getStatus()];
        $item->reject($reviewerId, $reason);
        $this->items->save($item);
        $this->audit->record($reviewerId, 'moderation.reject', 'moderationItem', $item->getId(), $before, ['status' => $item->getStatus(), 'reason' => $reason]);
        return $item;
    }

    /**
     * @param string[] $itemIds
     * @return array{approved:ModerationItem[], failed:string[]}
     */
    public function bulkApprove(array $itemIds, string $reviewerId, int $score): array
    {
        $approved = [];
        $failed = [];
        foreach ($itemIds as $id) {
            try {
                $approved[] = $this->approve($id, $reviewerId, $score);
            } catch (\Throwable) {
                $failed[] = $id;
            }
        }
        return ['approved' => $approved, 'failed' => $failed];
    }

    /**
     * @param string[] $itemIds
     * @return array{rejected:ModerationItem[], failed:string[]}
     */
    public function bulkReject(array $itemIds, string $reviewerId, string $reason): array
    {
        $rejected = [];
        $failed = [];
        foreach ($itemIds as $id) {
            try {
                $rejected[] = $this->reject($id, $reviewerId, $reason);
            } catch (\Throwable) {
                $failed[] = $id;
            }
        }
        return ['rejected' => $rejected, 'failed' => $failed];
    }

    /**
     * @return ModerationItem[]
     */
    public function pending(): array
    {
        return $this->items->findPending();
    }

    /**
     * @return ModerationAttachment[]
     */
    public function attachmentsOf(string $itemId): array
    {
        return $this->attachments->findByItem($itemId);
    }
}
