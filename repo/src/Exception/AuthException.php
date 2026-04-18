<?php

declare(strict_types=1);

namespace App\Exception;

final class AuthException extends DomainException
{
    public function __construct(string $message = 'authentication failed')
    {
        parent::__construct($message, 401);
    }
}
