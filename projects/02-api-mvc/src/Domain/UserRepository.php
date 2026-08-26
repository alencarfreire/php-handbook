<?php

namespace App\Domain;

// Contrato. Quem implementa (PDO, memória, MySQL) fica na Infrastructure.
interface UserRepository
{
    public function save(User $user): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;
}
