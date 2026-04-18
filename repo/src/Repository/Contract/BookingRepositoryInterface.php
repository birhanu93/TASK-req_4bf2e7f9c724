<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Booking;

interface BookingRepositoryInterface
{
    public function save(Booking $booking): void;

    public function find(string $id): ?Booking;

    /** @return Booking[] */
    public function findAll(): array;

    public function delete(string $id): void;

    /** @return Booking[] */
    public function findActiveBySession(string $sessionId): array;

    /** @return Booking[] */
    public function findByTrainee(string $traineeId): array;

    public function findByIdempotencyKey(string $key): ?Booking;
}
