<?php

namespace App\Domain;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {
    }

    public function toPublicArray(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
        ];
    }
}
