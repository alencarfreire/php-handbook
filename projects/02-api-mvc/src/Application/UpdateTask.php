<?php

namespace App\Application;

use App\Domain\Task;
use App\Domain\TaskRepository;

final class UpdateTask
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    public function handle(int $id, int $userId, ?string $title, ?bool $done): Task
    {
        $current = $this->tasks->findByIdForUser($id, $userId);
        if ($current === null) {
            throw new AppException(404, 'Task não encontrada.');
        }

        $newTitle = $title !== null ? trim($title) : $current->title;
        if ($newTitle === '') {
            throw new AppException(422, 'Título obrigatório.');
        }

        return $this->tasks->save(new Task(
            $current->id,
            $current->userId,
            $newTitle,
            $done ?? $current->done,
        ));
    }
}
