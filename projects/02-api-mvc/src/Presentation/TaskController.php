<?php

namespace App\Presentation;

use App\Application\CreateTask;
use App\Application\DeleteTask;
use App\Application\GetTask;
use App\Application\ListTasks;
use App\Application\UpdateTask;

final class TaskController
{
    public function __construct(
        private readonly Auth $auth,
        private readonly ListTasks $list,
        private readonly CreateTask $create,
        private readonly GetTask $get,
        private readonly UpdateTask $update,
        private readonly DeleteTask $delete,
    ) {
    }

    public function index(): never
    {
        Json::send(200, $this->list->handle($this->auth->userId()));
    }

    public function store(): never
    {
        $body = Json::body();
        // userId do token. Se vier user_id no JSON, ignora.
        $task = $this->create->handle(
            $this->auth->userId(),
            (string) ($body['title'] ?? ''),
        );

        Json::send(201, $task->toArray());
    }

    public function show(string $id): never
    {
        $task = $this->get->handle((int) $id, $this->auth->userId());
        Json::send(200, $task->toArray());
    }

    public function patch(string $id): never
    {
        $body = Json::body();
        $done = array_key_exists('done', $body) ? (bool) $body['done'] : null;
        $title = array_key_exists('title', $body) ? (string) $body['title'] : null;

        $task = $this->update->handle((int) $id, $this->auth->userId(), $title, $done);
        Json::send(200, $task->toArray());
    }

    public function destroy(string $id): never
    {
        $this->delete->handle((int) $id, $this->auth->userId());
        http_response_code(204);
        exit;
    }
}
