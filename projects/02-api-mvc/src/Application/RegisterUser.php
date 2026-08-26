<?php

namespace App\Application;

use App\Domain\User;
use App\Domain\UserRepository;
use App\Infrastructure\NativePasswordHasher;

// Um handle = um caso de uso. Sem echo, sem SQL.
final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly NativePasswordHasher $hasher,
    ) {
    }

    public function handle(string $name, string $email, string $password): User
    {
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new AppException(422, 'Nome, e-mail válido e senha com 8+ caracteres.');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new AppException(409, 'E-mail já cadastrado.');
        }

        return $this->users->save(new User(
            null,
            $name,
            $email,
            $this->hasher->hash($password),
        ));
    }
}
