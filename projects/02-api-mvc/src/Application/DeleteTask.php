<?php

namespace App\Application;

use App\Domain\TaskRepository;

final class DeleteTask
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function handle(int $id, int $userId): void
    {
        if (!$this->tasks->deleteForUser($id, $userId)) {
            throw new AppException(404, 'Task não encontrada.');
        }
    }
}
