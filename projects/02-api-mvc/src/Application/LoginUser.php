<?php

namespace App\Application;

use App\Domain\UserRepository;
use App\Infrastructure\NativePasswordHasher;
use App\Infrastructure\TokenService;

final class LoginUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly NativePasswordHasher $hasher,
        private readonly TokenService $tokens,
    ) {
    }

    public function handle(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new AppException(401, 'Credenciais inválidas.');
        }

        return [
            'token' => $this->tokens->issue((int) $user->id),
            'user'  => $user->toPublicArray(),
        ];
    }
}
