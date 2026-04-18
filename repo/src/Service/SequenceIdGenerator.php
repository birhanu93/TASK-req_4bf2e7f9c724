<?php

declare(strict_types=1);

namespace App\Service;

final class SequenceIdGenerator implements IdGenerator
{
    private int $seq = 0;

    public function __construct(private string $prefix = 'id')
    {
    }

    public function generate(): string
    {
        $this->seq++;
        return sprintf('%s-%06d', $this->prefix, $this->seq);
    }
}
