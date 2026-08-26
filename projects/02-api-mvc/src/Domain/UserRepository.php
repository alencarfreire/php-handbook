<?php

namespace App\Domain;

interface UserRepository
{
    public function save(User $user): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;
}
