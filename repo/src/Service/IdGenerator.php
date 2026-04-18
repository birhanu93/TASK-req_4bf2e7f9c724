<?php

declare(strict_types=1);

namespace App\Service;

interface IdGenerator
{
    public function generate(): string;
}
