<?php

namespace App\Application;

use App\Domain\Task;
use App\Domain\TaskRepository;

final class CreateTask
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function handle(int $userId, string $title): Task
    {
        $title = trim($title);
        if ($title === '') {
            throw new AppException(422, 'Título obrigatório.');
        }

        // userId vem do token, não do body. O client não escolhe o dono.
        return $this->tasks->save(new Task(null, $userId, $title, false));
    }
}
