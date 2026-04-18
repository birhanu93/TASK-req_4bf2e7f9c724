<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SupervisorLeave;
use App\Entity\TrainingSession;
use App\Exception\ConflictException;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Persistence\Database;
use App\Repository\Contract\LeaveRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;

final class SchedulingService
{
    public function __construct(
        private SessionRepositoryInterface $sessions,
        private LeaveRepositoryInterface $leaves,
        private IdGenerator $ids,
        private Clock $clock,
        private AuditLogger $audit,
        private Database $db,
        private ?ResourceService $resources = null,
    ) {
    }

    /**
     * @param string[] $resourceIds
     */
    public function create(
        string $supervisorId,
        string $title,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        int $capacity,
        int $bufferMinutes = 10,
        array $resourceIds = [],
    ): TrainingSession {
        if ($endsAt <= $startsAt) {
            throw new ValidationException('endsAt must be after startsAt');
        }
        if ($capacity <= 0) {
            throw new ValidationException('capacity must be positive');
        }
        if ($bufferMinutes < 0) {
            throw new ValidationException('buffer must be non-negative');
        }

        return $this->db->transactional(function () use ($supervisorId, $title, $startsAt, $endsAt, $capacity, $bufferMinutes, $resourceIds): TrainingSession {
            // Per-supervisor serialization: conflict checks and the subsequent
            // insert commit atomically so two concurrent creates can never
            // both see an empty conflict set.
            $this->db->lock("supervisor:{$supervisorId}");

            foreach ($this->sessions->findBySupervisor($supervisorId) as $existing) {
                if (!$existing->isOpen()) {
                    continue;
                }
                if ($this->overlaps($existing, $startsAt, $endsAt, $bufferMinutes)) {
                    throw new ConflictException('session overlaps with an existing session for supervisor');
                }
            }

            foreach ($this->leaves->findBySupervisor($supervisorId) as $leave) {
                if ($leave->overlaps($startsAt, $endsAt)) {
                    throw new ConflictException('session overlaps with supervisor leave');
                }
            }

            $session = new TrainingSession(
                $this->ids->generate(),
                $supervisorId,
                $title,
                $startsAt,
                $endsAt,
                $capacity,
                $bufferMinutes,
            );
            $this->sessions->save($session);

            if ($resourceIds !== []) {
                if ($this->resources === null) {
                    throw new ConflictException('resource scheduling is not configured');
                }
                // Reserving inside the outer transaction means a resource
                // conflict rolls back the whole session create — we never
                // leave a half-scheduled session without its resources.
                $this->resources->reserveMany($resourceIds, $session->getId(), $startsAt, $endsAt, $supervisorId);
            }

            $this->audit->record(
                $supervisorId,
                'session.create',
                'session',
                $session->getId(),
                [],
                [
                    'title' => $title,
                    'startsAt' => $startsAt->format(DATE_ATOM),
                    'resourceIds' => array_values($resourceIds),
                ],
            );
            return $session;
        });
    }

    public function close(string $sessionId, string $actorId = 'system'): void
    {
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            throw new NotFoundException('session not found');
        }
        $before = ['status' => $session->getStatus()];
        $session->close();
        $this->sessions->save($session);
        $this->audit->record($actorId, 'session.close', 'session', $session->getId(), $before, ['status' => $session->getStatus()]);
    }

    /**
     * @return TrainingSession[]
     */
    public function listAvailable(\DateTimeImmutable $from): array
    {
        return array_values(array_filter(
            $this->sessions->findAll(),
            fn (TrainingSession $s) => $s->isOpen() && $s->getStartsAt() >= $from,
        ));
    }

    public function addLeave(
        string $supervisorId,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $endsAt,
        string $rule = SupervisorLeave::RULE_ONE_OFF,
        ?string $reason = null,
    ): SupervisorLeave {
        return $this->db->transactional(function () use ($supervisorId, $startsAt, $endsAt, $rule, $reason): SupervisorLeave {
            $this->db->lock("supervisor:{$supervisorId}");

            $leave = new SupervisorLeave(
                $this->ids->generate(),
                $supervisorId,
                $startsAt,
                $endsAt,
                $rule,
                $reason,
                $this->clock->now(),
            );
            foreach ($this->sessions->findBySupervisor($supervisorId) as $session) {
                if (!$session->isOpen()) {
                    continue;
                }
                if ($leave->overlaps($session->getStartsAt(), $session->getEndsAt())) {
                    throw new ConflictException('leave overlaps existing session for supervisor');
                }
            }
            $this->leaves->save($leave);
            $this->audit->record(
                $supervisorId,
                'session.leave.add',
                'leave',
                $leave->getId(),
                [],
                ['rule' => $rule, 'startsAt' => $startsAt->format(DATE_ATOM), 'endsAt' => $endsAt->format(DATE_ATOM)],
            );
            return $leave;
        });
    }

    /**
     * @return SupervisorLeave[]
     */
    public function leavesOf(string $supervisorId): array
    {
        return $this->leaves->findBySupervisor($supervisorId);
    }

    private function overlaps(
        TrainingSession $existing,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        int $incomingBufferMinutes,
    ): bool {
        // The conflict window must respect the larger of the existing and
        // incoming buffers so either party's padding is honoured. A tight new
        // session cannot ignore an existing session's cool-down, and a wide
        // new session cannot encroach on an existing one whose buffer is
        // smaller.
        $buffer = max($existing->getBufferMinutes(), $incomingBufferMinutes);
        $bufferedStart = $existing->getStartsAt()->modify("-{$buffer} minutes");
        $bufferedEnd = $existing->getEndsAt()->modify("+{$buffer} minutes");
        return $start < $bufferedEnd && $end > $bufferedStart;
    }

    public function findSession(string $sessionId): TrainingSession
    {
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            throw new NotFoundException('session not found');
        }
        return $session;
    }
}
