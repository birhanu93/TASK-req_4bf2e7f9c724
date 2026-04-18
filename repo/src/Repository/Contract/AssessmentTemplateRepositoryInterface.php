<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\AssessmentTemplate;

interface AssessmentTemplateRepositoryInterface
{
    public function save(AssessmentTemplate $template): void;

    public function find(string $id): ?AssessmentTemplate;

    /** @return AssessmentTemplate[] */
    public function findAll(): array;

    public function delete(string $id): void;
}
