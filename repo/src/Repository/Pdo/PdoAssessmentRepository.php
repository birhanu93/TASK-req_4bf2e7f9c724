<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Assessment;
use App\Repository\Contract\AssessmentRepositoryInterface;

final class PdoAssessmentRepository implements AssessmentRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Assessment $a): void
    {
        $sql = 'INSERT INTO assessments (id, template_id, trainee_id, supervisor_id, reps, seconds, recorded_at, rank_achieved)
                VALUES (:id, :t, :tr, :s, :r, :sec, :rec, :ra)
                ON DUPLICATE KEY UPDATE
                  reps = VALUES(reps),
                  seconds = VALUES(seconds),
                  rank_achieved = VALUES(rank_achieved)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $a->getId(),
            't' => $a->getTemplateId(),
            'tr' => $a->getTraineeId(),
            's' => $a->getSupervisorId(),
            'r' => $a->getReps(),
            'sec' => $a->getSeconds(),
            'rec' => $a->getRecordedAt()->format('Y-m-d H:i:s'),
            'ra' => $a->getRankAchieved(),
        ]);
    }

    public function find(string $id): ?Assessment
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assessments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM assessments')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM assessments WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByTrainee(string $traineeId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM assessments WHERE trainee_id = :t ORDER BY recorded_at ASC');
        $stmt->execute(['t' => $traineeId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Assessment
    {
        return new Assessment(
            (string) $row['id'],
            (string) $row['template_id'],
            (string) $row['trainee_id'],
            (string) $row['supervisor_id'],
            (int) $row['reps'],
            (int) $row['seconds'],
            new \DateTimeImmutable((string) $row['recorded_at']),
            $row['rank_achieved'] !== null ? (string) $row['rank_achieved'] : null,
        );
    }
}
