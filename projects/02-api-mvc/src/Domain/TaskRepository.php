<?php

namespace App\Domain;

interface TaskRepository
{
    public function save(Task $task): Task;

    // Sempre com userId: a task do João não aparece para a Maria.
    public function findByIdForUser(int $id, int $userId): ?Task;

    /** @return list<Task> */
    public function allForUser(int $userId): array;

    public function deleteForUser(int $id, int $userId): bool;
}
