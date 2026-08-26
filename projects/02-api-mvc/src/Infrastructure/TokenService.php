<?php

namespace App\Infrastructure;

use PDO;

final class TokenService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tokens (token, user_id, created_at) VALUES (:token, :user_id, :created_at)'
        );
        $stmt->execute([
            'token'      => $token,
            'user_id'    => $userId,
            'created_at' => gmdate('c'),
        ]);

        return $token;
    }

    public function userIdFor(string $token): ?int
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM tokens WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ? (int) $row['user_id'] : null;
    }
}
