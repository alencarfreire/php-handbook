<?php

namespace App\Infrastructure;

use App\Domain\Task;
use App\Domain\TaskRepository;
use PDO;

final class PdoTaskRepository implements TaskRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Task $task): Task
    {
        if ($task->id === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tasks (user_id, title, done) VALUES (:user_id, :title, :done)'
            );
            $stmt->execute([
                'user_id' => $task->userId,
                'title'   => $task->title,
                'done'    => $task->done ? 1 : 0,
            ]);

            return new Task(
                (int) $this->pdo->lastInsertId(),
                $task->userId,
                $task->title,
                $task->done,
            );
        }

        $stmt = $this->pdo->prepare(
            'UPDATE tasks SET title = :title, done = :done WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([
            'title'   => $task->title,
            'done'    => $task->done ? 1 : 0,
            'id'      => $task->id,
            'user_id' => $task->userId,
        ]);

        return $task;
    }

    public function findByIdForUser(int $id, int $userId): ?Task
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tasks WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM tasks WHERE user_id = :user_id ORDER BY id'
        );
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->map(...), $stmt->fetchAll());
    }

    public function deleteForUser(int $id, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM tasks WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    private function map(array $row): Task
    {
        return new Task(
            (int) $row['id'],
            (int) $row['user_id'],
            $row['title'],
            (bool) $row['done'],
        );
    }
}
