<?php

namespace App\Domain;

final class Task
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly bool $done,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id'      => $this->id,
            'user_id' => $this->userId,
            'title'   => $this->title,
            'done'    => $this->done,
        ];
    }
}
