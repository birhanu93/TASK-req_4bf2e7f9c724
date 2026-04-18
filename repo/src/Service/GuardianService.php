<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Device;
use App\Entity\GuardianLink;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\DeviceRepositoryInterface;
use App\Repository\Contract\GuardianLinkRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;

final class GuardianService
{
    public function __construct(
        private GuardianLinkRepositoryInterface $links,
        private DeviceRepositoryInterface $devices,
        private UserRepositoryInterface $users,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
    ) {
    }

    public function linkChild(string $guardianId, string $childId): GuardianLink
    {
        if ($guardianId === $childId) {
            throw new ValidationException('guardian cannot link themselves');
        }
        if ($this->users->find($guardianId) === null) {
            throw new NotFoundException('guardian not found');
        }
        if ($this->users->find($childId) === null) {
            throw new NotFoundException('child not found');
        }
        if ($this->links->findLink($guardianId, $childId) !== null) {
            throw new ConflictException('link already exists');
        }
        $existing = $this->links->findByGuardian($guardianId);
        if (count($existing) >= GuardianLink::MAX_CHILDREN) {
            throw new ConflictException('guardian has reached maximum child links');
        }
        $link = new GuardianLink(
            $this->ids->generate(),
            $guardianId,
            $childId,
            $this->clock->now(),
        );
        $this->links->save($link);
        $this->audit->record($guardianId, 'guardian.link', 'guardianLink', $link->getId(), [], ['child' => $childId]);
        return $link;
    }

    public function approveDevice(string $guardianId, string $childId, string $deviceName, string $fingerprint): Device
    {
        $link = $this->links->findLink($guardianId, $childId);
        if ($link === null) {
            throw new NotFoundException('guardian link not found');
        }
        if ($this->devices->findByFingerprint($childId, $fingerprint) !== null) {
            throw new ConflictException('device already approved');
        }
        $device = new Device(
            $this->ids->generate(),
            $childId,
            $deviceName,
            $fingerprint,
            $this->clock->now(),
        );
        $token = bin2hex(random_bytes(16));
        $device->setSessionToken($token);
        $this->devices->save($device);
        $this->audit->record($guardianId, 'guardian.approve_device', 'device', $device->getId(), [], ['child' => $childId]);
        return $device;
    }

    public function remoteLogout(string $guardianId, string $deviceId): Device
    {
        $device = $this->devices->find($deviceId);
        if ($device === null) {
            throw new NotFoundException('device not found');
        }
        $link = $this->links->findLink($guardianId, $device->getUserId());
        if ($link === null) {
            throw new NotFoundException('guardian link not found');
        }
        $before = ['status' => $device->getStatus()];
        $device->revoke();
        $this->devices->save($device);
        $this->audit->record($guardianId, 'guardian.remote_logout', 'device', $device->getId(), $before, ['status' => $device->getStatus()]);
        return $device;
    }

    /**
     * @return GuardianLink[]
     */
    public function childrenOf(string $guardianId): array
    {
        return $this->links->findByGuardian($guardianId);
    }

    /**
     * @return Device[]
     */
    public function devicesOf(string $childId): array
    {
        return $this->devices->findByUser($childId);
    }
}
