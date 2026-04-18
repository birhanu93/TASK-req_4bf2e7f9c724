<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AssessmentTemplate;
use App\Repository\Contract\AssessmentTemplateRepositoryInterface;

/**
 * @extends Repository<AssessmentTemplate>
 */
final class AssessmentTemplateRepository extends Repository implements AssessmentTemplateRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?AssessmentTemplate
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }
}
