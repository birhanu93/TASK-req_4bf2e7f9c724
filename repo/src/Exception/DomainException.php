<?php

declare(strict_types=1);

namespace App\Exception;

class DomainException extends \RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 400)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
