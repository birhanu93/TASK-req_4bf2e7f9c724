<?php

declare(strict_types=1);

namespace App\Exception;

final class AccessDeniedException extends DomainException
{
    public function __construct(string $message = 'access denied')
    {
        parent::__construct($message, 403);
    }
}
