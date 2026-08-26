<?php

namespace App\Presentation;

use App\Application\LoginUser;
use App\Application\RegisterUser;

// HTTP in, JSON out. SQL fica no repository.
final class AuthController
{
    public function __construct(
        private readonly RegisterUser $register,
        private readonly LoginUser $login,
    ) {
    }

    public function register(): never
    {
        $body = Json::body();
        $user = $this->register->handle(
            trim((string) ($body['name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            (string) ($body['password'] ?? ''),
        );

        Json::send(201, $user->toPublicArray());
    }

    public function login(): never
    {
        $body = Json::body();
        Json::send(200, $this->login->handle(
            trim((string) ($body['email'] ?? '')),
            (string) ($body['password'] ?? ''),
        ));
    }
}
