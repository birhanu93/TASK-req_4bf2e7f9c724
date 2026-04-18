<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\GuardianLink;

interface GuardianLinkRepositoryInterface
{
    public function save(GuardianLink $link): void;

    public function find(string $id): ?GuardianLink;

    /** @return GuardianLink[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return GuardianLink[] */
    public function findByGuardian(string $guardianId): array;

    public function findLink(string $guardianId, string $childId): ?GuardianLink;
}
