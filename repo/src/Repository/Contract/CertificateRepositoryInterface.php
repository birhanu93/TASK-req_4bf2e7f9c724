<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Certificate;

interface CertificateRepositoryInterface
{
    public function save(Certificate $cert): void;

    public function find(string $id): ?Certificate;

    /** @return Certificate[] */
    public function findAll(): array;

    public function delete(string $id): void;

    public function findByVerificationCode(string $code): ?Certificate;

    /** @return Certificate[] */
    public function findByTrainee(string $traineeId): array;
}
