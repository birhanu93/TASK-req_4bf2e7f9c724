<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Certificate;
use App\Repository\Contract\CertificateRepositoryInterface;

/**
 * @extends Repository<Certificate>
 */
final class CertificateRepository extends Repository implements CertificateRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Certificate
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByVerificationCode(string $code): ?Certificate
    {
        foreach ($this->items as $cert) {
            if ($cert->getVerificationCode() === $code) {
                return $cert;
            }
        }
        return null;
    }

    /**
     * @return Certificate[]
     */
    public function findByTrainee(string $traineeId): array
    {
        return array_values(array_filter(
            $this->items,
            fn (Certificate $c) => $c->getTraineeId() === $traineeId,
        ));
    }
}
