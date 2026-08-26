<?php

namespace App\Presentation;

use App\Infrastructure\TokenService;

final class Auth
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function userId(): int
    {
        // Authorization: Bearer <token> — não é cookie de sessão.
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)/', $header, $m)) {
            Json::error(401, 'Token ausente.');
        }

        $userId = $this->tokens->userIdFor($m[1]);
        if ($userId === null) {
            Json::error(401, 'Token inválido.');
        }

        return $userId;
    }
}
