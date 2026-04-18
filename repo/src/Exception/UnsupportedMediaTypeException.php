<?php

declare(strict_types=1);

namespace App\Exception;

final class UnsupportedMediaTypeException extends DomainException
{
    public function __construct(string $message = 'unsupported media type')
    {
        parent::__construct($message, 415);
    }
}
