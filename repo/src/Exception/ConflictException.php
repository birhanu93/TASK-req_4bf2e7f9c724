<?php

declare(strict_types=1);

namespace App\Exception;

final class ConflictException extends DomainException
{
    public function __construct(string $message = 'conflict')
    {
        parent::__construct($message, 409);
    }
}
