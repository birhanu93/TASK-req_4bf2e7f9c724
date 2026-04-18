<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\AssessmentTemplate;
use App\Repository\Contract\AssessmentTemplateRepositoryInterface;

final class PdoAssessmentTemplateRepository implements AssessmentTemplateRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(AssessmentTemplate $t): void
    {
        $sql = 'INSERT INTO assessment_templates (id, name, mode, target_reps, target_seconds, created_at)
                VALUES (:id, :n, :m, :tr, :ts, NOW())
                ON DUPLICATE KEY UPDATE name = VALUES(name), mode = VALUES(mode),
                  target_reps = VALUES(target_reps), target_seconds = VALUES(target_seconds)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $t->getId(),
            'n' => $t->getName(),
            'm' => $t->getMode(),
            'tr' => $t->getTargetReps(),
            'ts' => $t->getTargetSeconds(),
        ]);
    }

    public function find(string $id): ?AssessmentTemplate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assessment_templates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM assessment_templates')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM assessment_templates WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): AssessmentTemplate
    {
        return new AssessmentTemplate(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['mode'],
            (int) $row['target_reps'],
            (int) $row['target_seconds'],
        );
    }
}
