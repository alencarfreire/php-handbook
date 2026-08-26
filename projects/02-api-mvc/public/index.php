<?php

declare(strict_types=1);

use App\Application\AppException;
use App\Application\CreateTask;
use App\Application\DeleteTask;
use App\Application\GetTask;
use App\Application\ListTasks;
use App\Application\LoginUser;
use App\Application\RegisterUser;
use App\Application\UpdateTask;
use App\Infrastructure\Connection;
use App\Infrastructure\NativePasswordHasher;
use App\Infrastructure\PdoTaskRepository;
use App\Infrastructure\PdoUserRepository;
use App\Infrastructure\TokenService;
use App\Presentation\Auth;
use App\Presentation\AuthController;
use App\Presentation\Json;
use App\Presentation\Router;
use App\Presentation\TaskController;

require dirname(__DIR__) . '/autoload.php';

// Composition root: o único lugar que new em todo mundo.
$pdo = Connection::make();
$users = new PdoUserRepository($pdo);
$tasks = new PdoTaskRepository($pdo);
$hasher = new NativePasswordHasher();
$tokens = new TokenService($pdo);
$auth = new Auth($tokens);

$authController = new AuthController(
    new RegisterUser($users, $hasher),
    new LoginUser($users, $hasher, $tokens),
);

$taskController = new TaskController(
    $auth,
    new ListTasks($tasks),
    new CreateTask($tasks),
    new GetTask($tasks),
    new UpdateTask($tasks),
    new DeleteTask($tasks),
);

$router = new Router();
$router->add('POST', '/register', fn () => $authController->register());
$router->add('POST', '/login', fn () => $authController->login());
$router->add('GET', '/tasks', fn () => $taskController->index());
$router->add('POST', '/tasks', fn () => $taskController->store());
$router->add('GET', '/tasks/{id}', fn (array $p) => $taskController->show($p['id']));
$router->add('PATCH', '/tasks/{id}', fn (array $p) => $taskController->patch($p['id']));
$router->add('DELETE', '/tasks/{id}', fn (array $p) => $taskController->destroy($p['id']));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    $router->dispatch($method, $path);
} catch (AppException $e) {
    Json::error($e->status, $e->getMessage());
} catch (Throwable $e) {
    // Não vaza stack para o client.
    Json::error(500, 'Erro interno.');
}
