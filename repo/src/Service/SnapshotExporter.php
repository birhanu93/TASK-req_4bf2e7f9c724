<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\Contract\AssessmentRepositoryInterface;
use App\Repository\Contract\AuditLogRepositoryInterface;
use App\Repository\Contract\BookingRepositoryInterface;
use App\Repository\Contract\CertificateRepositoryInterface;
use App\Repository\Contract\ModerationRepositoryInterface;
use App\Repository\Contract\SessionRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;
use App\Repository\Contract\VoucherClaimRepositoryInterface;
use App\Repository\Contract\VoucherRepositoryInterface;

/**
 * Exports denormalized snapshots of the main entities to a timestamped
 * directory, with a manifest summarising counts and checksums. Intended to be
 * invoked by `bin/console snapshot:export` on a cron schedule (daily). The
 * output is safe for archival but is not a full DB dump — audit content and
 * encrypted profile blobs are preserved verbatim.
 */
final class SnapshotExporter
{
    public function __construct(
        private string $snapshotRoot,
        private UserRepositoryInterface $users,
        private SessionRepositoryInterface $sessions,
        private BookingRepositoryInterface $bookings,
        private AssessmentRepositoryInterface $assessments,
        private VoucherRepositoryInterface $vouchers,
        private VoucherClaimRepositoryInterface $claims,
        private ModerationRepositoryInterface $moderation,
        private CertificateRepositoryInterface $certificates,
        private AuditLogRepositoryInterface $audit,
        private Clock $clock,
    ) {
        if (!is_dir($this->snapshotRoot) && !@mkdir($this->snapshotRoot, 0750, true) && !is_dir($this->snapshotRoot)) {
            throw new \RuntimeException('failed to create snapshot root');
        }
    }

    /**
     * @return array{path:string,manifest:array<string,mixed>}
     */
    public function export(): array
    {
        $now = $this->clock->now();
        $stamp = $now->format('Ymd-His');
        $path = rtrim($this->snapshotRoot, '/') . '/' . $stamp;
        if (!@mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException('failed to create snapshot directory');
        }

        $sections = [
            'users' => $this->serializeUsers(),
            'training_sessions' => array_map(fn ($s) => [
                'id' => $s->getId(),
                'supervisorId' => $s->getSupervisorId(),
                'title' => $s->getTitle(),
                'startsAt' => $s->getStartsAt()->format(DATE_ATOM),
                'endsAt' => $s->getEndsAt()->format(DATE_ATOM),
                'capacity' => $s->getCapacity(),
                'bufferMinutes' => $s->getBufferMinutes(),
                'status' => $s->getStatus(),
            ], $this->sessions->findAll()),
            'bookings' => array_map(fn ($b) => [
                'id' => $b->getId(),
                'sessionId' => $b->getSessionId(),
                'traineeId' => $b->getTraineeId(),
                'status' => $b->getStatus(),
                'createdAt' => $b->getCreatedAt()->format(DATE_ATOM),
                'cancellationReason' => $b->getCancellationReason(),
                'overrideActorId' => $b->getOverrideActorId(),
                'idempotencyKey' => $b->getIdempotencyKey(),
            ], $this->bookings->findAll()),
            'assessments' => array_map(fn ($a) => [
                'id' => $a->getId(),
                'templateId' => $a->getTemplateId(),
                'traineeId' => $a->getTraineeId(),
                'supervisorId' => $a->getSupervisorId(),
                'reps' => $a->getReps(),
                'seconds' => $a->getSeconds(),
                'recordedAt' => $a->getRecordedAt()->format(DATE_ATOM),
                'rankAchieved' => $a->getRankAchieved(),
            ], $this->assessments->findAll()),
            'vouchers' => array_map(fn ($v) => [
                'id' => $v->getId(),
                'code' => $v->getCode(),
                'discountCents' => $v->getDiscountCents(),
                'minSpendCents' => $v->getMinSpendCents(),
                'claimLimit' => $v->getClaimLimit(),
                'claimed' => $v->getClaimed(),
                'expiresAt' => $v->getExpiresAt()->format(DATE_ATOM),
                'status' => $v->getStatus(),
            ], $this->vouchers->findAll()),
            'voucher_claims' => array_map(fn ($c) => [
                'id' => $c->getId(),
                'voucherId' => $c->getVoucherId(),
                'userId' => $c->getUserId(),
                'idempotencyKey' => $c->getIdempotencyKey(),
                'status' => $c->getStatus(),
                'createdAt' => $c->getCreatedAt()->format(DATE_ATOM),
                'redeemedAt' => $c->getRedeemedAt()?->format(DATE_ATOM),
            ], $this->claims->findAll()),
            'moderation_items' => array_map(fn ($m) => [
                'id' => $m->getId(),
                'authorId' => $m->getAuthorId(),
                'kind' => $m->getKind(),
                'checksum' => $m->getChecksum(),
                'status' => $m->getStatus(),
                'reviewerId' => $m->getReviewerId(),
                'qualityScore' => $m->getQualityScore(),
                'reason' => $m->getReason(),
                'submittedAt' => $m->getSubmittedAt()->format(DATE_ATOM),
            ], $this->moderation->findAll()),
            'certificates' => array_map(fn ($c) => [
                'id' => $c->getId(),
                'traineeId' => $c->getTraineeId(),
                'rankId' => $c->getRankId(),
                'verificationCode' => $c->getVerificationCode(),
                'status' => $c->getStatus(),
                'issuedAt' => $c->getIssuedAt()->format(DATE_ATOM),
            ], $this->certificates->findAll()),
            'audit_log' => array_map(fn ($l) => [
                'id' => $l->getId(),
                'actorId' => $l->getActorId(),
                'action' => $l->getAction(),
                'entityType' => $l->getEntityType(),
                'entityId' => $l->getEntityId(),
                'occurredAt' => $l->getOccurredAt()->format(DATE_ATOM),
                'before' => $l->getBefore(),
                'after' => $l->getAfter(),
            ], $this->audit->findAll()),
        ];

        $manifest = [
            'generatedAt' => $now->format(DATE_ATOM),
            'sections' => [],
        ];
        foreach ($sections as $name => $rows) {
            $encoded = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '[]';
            $file = $path . '/' . $name . '.json';
            file_put_contents($file, $encoded);
            $manifest['sections'][$name] = [
                'count' => count($rows),
                'sha256' => hash('sha256', $encoded),
                'bytes' => strlen($encoded),
            ];
        }
        file_put_contents($path . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return ['path' => $path, 'manifest' => $manifest];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function serializeUsers(): array
    {
        return array_map(fn ($u) => [
            'id' => $u->getId(),
            'username' => $u->getUsername(),
            'roles' => $u->getRoles(),
            'active' => $u->isActive(),
            'hasEncryptedProfile' => $u->getEncryptedProfile() !== null,
        ], $this->users->findAll());
    }
}
