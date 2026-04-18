<?php

declare(strict_types=1);

namespace App\Entity;

final class Certificate
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    public function __construct(
        private string $id,
        private string $traineeId,
        private string $rankId,
        private string $verificationCode,
        private string $pdfPath,
        private \DateTimeImmutable $issuedAt,
        private string $status = self::STATUS_ACTIVE,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTraineeId(): string
    {
        return $this->traineeId;
    }

    public function getRankId(): string
    {
        return $this->rankId;
    }

    public function getVerificationCode(): string
    {
        return $this->verificationCode;
    }

    public function getPdfPath(): string
    {
        return $this->pdfPath;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function revoke(): void
    {
        $this->status = self::STATUS_REVOKED;
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
