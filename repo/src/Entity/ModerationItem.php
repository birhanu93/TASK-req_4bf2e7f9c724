<?php

declare(strict_types=1);

namespace App\Entity;

final class ModerationItem
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const KIND_NOTE = 'note';
    public const KIND_EVIDENCE = 'evidence';
    public const KIND_FEEDBACK = 'feedback';

    public function __construct(
        private string $id,
        private string $authorId,
        private string $kind,
        private string $content,
        private string $checksum,
        private \DateTimeImmutable $submittedAt,
        private string $status = self::STATUS_PENDING,
        private ?string $reviewerId = null,
        private ?string $reason = null,
        private ?int $qualityScore = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAuthorId(): string
    {
        return $this->authorId;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function getSubmittedAt(): \DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReviewerId(): ?string
    {
        return $this->reviewerId;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function getQualityScore(): ?int
    {
        return $this->qualityScore;
    }

    public function approve(string $reviewerId, int $score, ?string $reason = null): void
    {
        $this->status = self::STATUS_APPROVED;
        $this->reviewerId = $reviewerId;
        $this->qualityScore = $score;
        $this->reason = $reason;
    }

    public function reject(string $reviewerId, string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->reviewerId = $reviewerId;
        $this->reason = $reason;
    }
}
