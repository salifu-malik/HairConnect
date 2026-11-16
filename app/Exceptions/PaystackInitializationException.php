<?php

namespace App\Exceptions;

use Exception;

class PaystackInitializationException extends Exception
{
    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}