<?php

namespace App\Application;

use RuntimeException;

final class AppException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message, $status);
    }
}
