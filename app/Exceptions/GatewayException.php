<?php

namespace App\Exceptions;

use RuntimeException;

class GatewayException extends RuntimeException
{
    public function __construct(string $message, public bool $ambiguous = false)
    {
        parent::__construct($message);
    }
}
