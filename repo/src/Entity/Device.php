<?php

declare(strict_types=1);

namespace App\Entity;

final class Device
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED = 'revoked';

    public function __construct(
        private string $id,
        private string $userId,
        private string $deviceName,
        private string $fingerprint,
        private \DateTimeImmutable $approvedAt,
        private string $status = self::STATUS_APPROVED,
        private ?string $sessionToken = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getApprovedAt(): \DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function revoke(): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->sessionToken = null;
    }

    public function setSessionToken(?string $token): void
    {
        $this->sessionToken = $token;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
