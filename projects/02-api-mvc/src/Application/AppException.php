<?php

namespace App\Application;

use RuntimeException;

// Erro de regra (422, 401, 404…). O front controller vira JSON. Sem echo aqui.
final class AppException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message, $status);
    }
}
