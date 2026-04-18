<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Certificate;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Repository\Contract\CertificateRepositoryInterface;
use App\Repository\Contract\RankRepositoryInterface;
use App\Repository\Contract\UserRepositoryInterface;

final class CertificateService
{
    public function __construct(
        private CertificateRepositoryInterface $certs,
        private RankRepositoryInterface $ranks,
        private UserRepositoryInterface $users,
        private Clock $clock,
        private IdGenerator $ids,
        private AuditLogger $audit,
        private string $storageRoot,
        private ?StorageTieringService $tiering = null,
    ) {
        if (!is_dir($this->storageRoot) && !@mkdir($this->storageRoot, 0777, true) && !is_dir($this->storageRoot)) {
            throw new \RuntimeException('failed to create storage root');
        }
        if ($this->tiering !== null) {
            $this->tiering->registerStore('certificates', $this->storageRoot, $this->storageRoot . '-cold');
        }
    }

    public function issue(string $traineeId, string $rankId, string $actorId): Certificate
    {
        $rank = $this->ranks->find($rankId);
        if ($rank === null) {
            throw new NotFoundException('rank not found');
        }
        $trainee = $this->users->find($traineeId);
        $traineeLabel = $trainee?->getUsername() ?? $traineeId;

        $verification = strtoupper(bin2hex(random_bytes(6)));
        $path = rtrim($this->storageRoot, '/') . "/{$verification}.pdf";
        $pdf = PdfCertificate::render(
            $traineeLabel,
            $rank->getName(),
            $verification,
            $this->clock->now(),
            Certificate::STATUS_ACTIVE,
        );
        file_put_contents($path, $pdf);
        $cert = new Certificate(
            $this->ids->generate(),
            $traineeId,
            $rankId,
            $verification,
            $path,
            $this->clock->now(),
        );
        $this->certs->save($cert);
        $this->audit->record(
            $actorId,
            'certificate.issue',
            'certificate',
            $cert->getId(),
            [],
            ['traineeId' => $traineeId, 'rankId' => $rankId, 'verificationCode' => $verification],
        );
        return $cert;
    }

    public function verify(string $code): Certificate
    {
        $cert = $this->certs->findByVerificationCode($code);
        if ($cert === null) {
            throw new NotFoundException('certificate not found');
        }
        return $cert;
    }

    public function revoke(string $certificateId, string $actorId): Certificate
    {
        $cert = $this->certs->find($certificateId);
        if ($cert === null) {
            throw new NotFoundException('certificate not found');
        }
        $before = ['status' => $cert->getStatus()];
        $cert->revoke();
        $this->certs->save($cert);
        // Re-render the stored PDF so it reflects the revoked status — a
        // download after revocation now visibly says "Status: REVOKED" in
        // red, not a historical snapshot that lies about validity.
        $rank = $this->ranks->find($cert->getRankId());
        $trainee = $this->users->find($cert->getTraineeId());
        if ($rank !== null && is_writable(dirname($cert->getPdfPath())) && is_file($cert->getPdfPath())) {
            $pdf = PdfCertificate::render(
                $trainee?->getUsername() ?? $cert->getTraineeId(),
                $rank->getName(),
                $cert->getVerificationCode(),
                $cert->getIssuedAt(),
                Certificate::STATUS_REVOKED,
            );
            file_put_contents($cert->getPdfPath(), $pdf);
        }
        $this->audit->record($actorId, 'certificate.revoke', 'certificate', $cert->getId(), $before, ['status' => $cert->getStatus()]);
        return $cert;
    }

    public function readPdf(string $certificateId): string
    {
        $cert = $this->certs->find($certificateId);
        if ($cert === null) {
            throw new NotFoundException('certificate not found');
        }
        $path = $cert->getPdfPath();
        if (!is_file($path) && $this->tiering !== null) {
            // Fall through to cold tier when the artifact has been moved.
            $resolved = $this->tiering->resolve('certificates', basename($path));
            if ($resolved !== null) {
                $path = $resolved;
            }
        }
        if (!is_file($path)) {
            throw new ValidationException('certificate pdf missing');
        }
        return (string) file_get_contents($path);
    }

    public function findById(string $id): ?Certificate
    {
        return $this->certs->find($id);
    }

    /** @return Certificate[] */
    public function findByTrainee(string $traineeId): array
    {
        return $this->certs->findByTrainee($traineeId);
    }

    /** @return Certificate[] */
    public function listAll(): array
    {
        return $this->certs->findAll();
    }
}
