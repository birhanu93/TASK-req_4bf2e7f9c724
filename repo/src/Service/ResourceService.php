<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Resource;
use App\Entity\ResourceReservation;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Persistence\Database;
use App\Repository\Contract\ResourceRepositoryInterface;
use App\Repository\Contract\ResourceReservationRepositoryInterface;

/**
 * Owns resource lifecycle (create / retire / list) and the reservation
 * primitive used by the scheduler. Reservation checks share the same
 * transactional lock discipline as staff scheduling: per-resource advisory
 * lock, overlap query, then insert.
 */
final class ResourceService
{
    public function __construct(
        private ResourceRepositoryInterface $resources,
        private ResourceReservationRepositoryInterface $reservations,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
        private Database $db,
    ) {
    }

    public function create(string $name, string $kind, string $actorId): Resource
    {
        $name = trim($name);
        $kind = trim($kind);
        if ($name === '' || strlen($name) > 128) {
            throw new ValidationException('resource name must be 1..128 characters');
        }
        if ($kind === '' || strlen($kind) > 32) {
            throw new ValidationException('resource kind must be 1..32 characters');
        }
        if ($this->resources->findByName($name) !== null) {
            throw new ConflictException('resource name already exists');
        }
        $resource = new Resource(
            $this->ids->generate(),
            $name,
            $kind,
            $this->clock->now(),
        );
        try {
            $this->resources->save($resource);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                throw new ConflictException('resource name already exists');
            }
            throw $e;
        }
        $this->audit->record(
            $actorId,
            'resource.create',
            'resource',
            $resource->getId(),
            [],
            ['name' => $name, 'kind' => $kind],
        );
        return $resource;
    }

    public function retire(string $resourceId, string $actorId): Resource
    {
        $resource = $this->resources->find($resourceId);
        if ($resource === null) {
            throw new NotFoundException('resource not found');
        }
        $before = ['status' => $resource->getStatus()];
        $resource->retire();
        $this->resources->save($resource);
        $this->audit->record($actorId, 'resource.retire', 'resource', $resource->getId(), $before, ['status' => $resource->getStatus()]);
        return $resource;
    }

    /** @return Resource[] */
    public function list(): array
    {
        return $this->resources->findAll();
    }

    /**
     * Atomically reserve one or more resources for a training session.
     * Called inside the scheduler's outer transaction; each resource is
     * individually locked and its overlap list is consulted before the
     * insert. Returns the reservations in the same order as the input ids.
     *
     * @param string[] $resourceIds
     * @return ResourceReservation[]
     */
    public function reserveMany(
        array $resourceIds,
        ?string $sessionId,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        string $reservedByUserId,
    ): array {
        $out = [];
        foreach ($resourceIds as $rid) {
            $resource = $this->resources->find($rid);
            if ($resource === null) {
                throw new NotFoundException("resource not found: {$rid}");
            }
            if (!$resource->isActive()) {
                throw new ConflictException("resource is retired: {$resource->getName()}");
            }
            $this->db->lock("resource:{$rid}");
            $conflicts = $this->reservations->findOverlapping($rid, $startsAt, $endsAt);
            if ($conflicts !== []) {
                throw new ConflictException("resource {$resource->getName()} is already reserved in that window");
            }
            $reservation = new ResourceReservation(
                $this->ids->generate(),
                $rid,
                $sessionId,
                $startsAt,
                $endsAt,
                $reservedByUserId,
                $this->clock->now(),
            );
            $this->reservations->save($reservation);
            $this->audit->record(
                $reservedByUserId,
                'resource.reserve',
                'resource',
                $rid,
                [],
                [
                    'reservationId' => $reservation->getId(),
                    'sessionId' => $sessionId,
                    'startsAt' => $startsAt->format(DATE_ATOM),
                    'endsAt' => $endsAt->format(DATE_ATOM),
                ],
            );
            $out[] = $reservation;
        }
        return $out;
    }

    /** @return ResourceReservation[] */
    public function reservationsOf(string $resourceId): array
    {
        return $this->reservations->findByResource($resourceId);
    }
}
