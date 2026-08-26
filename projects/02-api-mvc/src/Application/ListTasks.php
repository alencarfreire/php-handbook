<?php

namespace App\Application;

use App\Domain\TaskRepository;

final class ListTasks
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    /** @return list<array<string, mixed>> */
    public function handle(int $userId): array
    {
        return array_map(
            static fn ($task) => $task->toArray(),
            $this->tasks->allForUser($userId),
        );
    }
}
