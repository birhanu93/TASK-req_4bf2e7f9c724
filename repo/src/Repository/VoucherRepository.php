<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Voucher;
use App\Repository\Contract\VoucherRepositoryInterface;

/**
 * @extends Repository<Voucher>
 */
final class VoucherRepository extends Repository implements VoucherRepositoryInterface
{
    protected function idOf(object $entity): string
    {
        return $entity->getId();
    }

    public function find(string $id): ?Voucher
    {
        return $this->items[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->items);
    }

    public function findByCode(string $code): ?Voucher
    {
        foreach ($this->items as $v) {
            if ($v->getCode() === $code) {
                return $v;
            }
        }
        return null;
    }

    public function findForUpdate(string $id): ?Voucher
    {
        return $this->find($id);
    }
}
