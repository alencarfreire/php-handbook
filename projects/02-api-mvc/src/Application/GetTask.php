<?php

namespace App\Application;

use App\Domain\Task;
use App\Domain\TaskRepository;

final class GetTask
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function handle(int $id, int $userId): Task
    {
        $task = $this->tasks->findByIdForUser($id, $userId);
        // 404, não 403: você nem admite que a task do outro existe.
        if ($task === null) {
            throw new AppException(404, 'Task não encontrada.');
        }

        return $task;
    }
}
