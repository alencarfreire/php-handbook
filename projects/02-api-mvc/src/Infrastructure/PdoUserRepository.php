<?php

namespace App\Infrastructure;

use App\Domain\User;
use App\Domain\UserRepository;
use PDO;

// PDO implementa o contrato. O caso de uso não vê SQL.
final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(User $user): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );
        $stmt->execute([
            'name'  => $user->name,
            'email' => $user->email,
            'hash'  => $user->passwordHash,
        ]);

        return new User(
            (int) $this->pdo->lastInsertId(),
            $user->name,
            $user->email,
            $user->passwordHash,
        );
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    private function map(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['name'],
            $row['email'],
            $row['password_hash'],
        );
    }
}
