<?php

namespace App\Infrastructure;

final class NativePasswordHasher
{
    // Nunca md5. PASSWORD_DEFAULT acompanha o PHP (hoje bcrypt/argon).
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
