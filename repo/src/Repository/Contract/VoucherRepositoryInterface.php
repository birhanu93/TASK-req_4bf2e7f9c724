<?php

declare(strict_types=1);

namespace App\Repository\Contract;

use App\Entity\Voucher;

interface VoucherRepositoryInterface
{
    public function save(Voucher $voucher): void;

    public function find(string $id): ?Voucher;

    /** @return Voucher[] */
    public function findAll(): array;

    public function delete(string $id): void;

    public function findByCode(string $code): ?Voucher;

    public function findForUpdate(string $id): ?Voucher;
}
