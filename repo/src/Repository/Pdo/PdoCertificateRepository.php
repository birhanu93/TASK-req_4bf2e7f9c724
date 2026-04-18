<?php

declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Entity\Certificate;
use App\Repository\Contract\CertificateRepositoryInterface;

final class PdoCertificateRepository implements CertificateRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function save(Certificate $c): void
    {
        $sql = 'INSERT INTO certificates (id, trainee_id, rank_id, verification_code, pdf_path, issued_at, status)
                VALUES (:id, :t, :r, :v, :p, :i, :s)
                ON DUPLICATE KEY UPDATE status = VALUES(status), pdf_path = VALUES(pdf_path)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'id' => $c->getId(),
            't' => $c->getTraineeId(),
            'r' => $c->getRankId(),
            'v' => $c->getVerificationCode(),
            'p' => $c->getPdfPath(),
            'i' => $c->getIssuedAt()->format('Y-m-d H:i:s'),
            's' => $c->getStatus(),
        ]);
    }

    public function find(string $id): ?Certificate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM certificates WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map(fn ($r) => $this->hydrate($r), $this->pdo->query('SELECT * FROM certificates')->fetchAll());
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM certificates WHERE id = :id')->execute(['id' => $id]);
    }

    public function findByVerificationCode(string $code): ?Certificate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM certificates WHERE verification_code = :c');
        $stmt->execute(['c' => $code]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    public function findByTrainee(string $traineeId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM certificates WHERE trainee_id = :t');
        $stmt->execute(['t' => $traineeId]);
        return array_map(fn ($r) => $this->hydrate($r), $stmt->fetchAll());
    }

    /**
     * @param array<string,mixed> $row
     */
    private function hydrate(array $row): Certificate
    {
        return new Certificate(
            (string) $row['id'],
            (string) $row['trainee_id'],
            (string) $row['rank_id'],
            (string) $row['verification_code'],
            (string) $row['pdf_path'],
            new \DateTimeImmutable((string) $row['issued_at']),
            (string) $row['status'],
        );
    }
}
