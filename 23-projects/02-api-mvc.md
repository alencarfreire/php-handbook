# 23.2 API MVC + SOLID (PHP puro)

> **TL;DR**
> PHP puro. Front controller, quatro camadas, PDO SQLite, token no header. Sem Laravel. Abaixo está o fonte **inteiro**, arquivo por arquivo — não é só pasta e comando.

**Gerado por IA. Não existe no handbook original da CodeMate.**

## Conteúdo

- [O recorte](#o-recorte)
- [Como rodar](#como-rodar)
- [Autoload e schema](#autoload-e-schema)
- [Domínio](#domínio)
- [Casos de uso](#casos-de-uso)
- [Infraestrutura (PDO, hash, token)](#infraestrutura-pdo-hash-token)
- [HTTP](#http)
- [Composition root](#composition-root)
- [SOLID neste tamanho](#solid-neste-tamanho)
- [Na entrevista](#na-entrevista)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O recorte

API JSON de **tasks** do usuário logado.

| Método | Rota | Auth |
|---|---|---|
| POST | `/register` | não |
| POST | `/login` | não |
| GET | `/tasks` | Bearer |
| POST | `/tasks` | Bearer |
| GET | `/tasks/{id}` | Bearer |
| PATCH | `/tasks/{id}` | Bearer |
| DELETE | `/tasks/{id}` | Bearer |

Fluxo de um POST autenticado:

```
POST /tasks
  → Router
  → TaskController::store
  → CreateTask::handle
  → TaskRepository::save   (interface)
  → PdoTaskRepository      (SQL)
```

Não é Clean Architecture de livro. Entidade anêmica, sem bus, sem DTO de borda. É o tamanho que um júnior reescreve numa tarde.

## Como rodar

```bash
cd projects/02-api-mvc
php -S localhost:8001 -t public
```

```bash
curl -s -X POST http://localhost:8001/register \
  -H 'Content-Type: application/json' \
  -d '{"name":"João","email":"joao@email.com","password":"secret123"}'

TOKEN=$(curl -s -X POST http://localhost:8001/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"joao@email.com","password":"secret123"}' \
  | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')

curl -s -X POST http://localhost:8001/tasks \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"title":"Comprar ração"}'

curl -s http://localhost:8001/tasks -H "Authorization: Bearer $TOKEN"
```

SQLite nasce em `storage/app.sqlite` no primeiro request.

---

## Autoload e schema

Sem Composer obrigatório. `spl_autoload_register` mapeia `App\` → `src/`.

### `autoload.php`

```php
<?php

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/src/' . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});
```


Schema no boot (`Connection::make` executa o SQL se a tabela não existe):

### `database/schema.sql`

```sql
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS tokens (
    token TEXT PRIMARY KEY,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    done INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```


---

## Domínio

Sem PDO. Sem `$_SERVER`. Sem JSON. Só dados + contrato de persistência.

### `src/Domain/User.php`

```php
<?php

namespace App\Domain;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $passwordHash,
    ) {
    }

    public function toPublicArray(): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email,
        ];
    }
}
```


### `src/Domain/UserRepository.php`

```php
<?php

namespace App\Domain;

interface UserRepository
{
    public function save(User $user): User;

    public function findByEmail(string $email): ?User;

    public function findById(int $id): ?User;
}
```


### `src/Domain/Task.php`

```php
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
```


### `src/Domain/TaskRepository.php`

```php
<?php

namespace App\Domain;

interface TaskRepository
{
    public function save(Task $task): Task;

    public function findByIdForUser(int $id, int $userId): ?Task;

    /** @return list<Task> */
    public function allForUser(int $userId): array;

    public function deleteForUser(int $id, int $userId): bool;
}
```


`findByIdForUser` / `deleteForUser` já levam `user_id`. IDOR morre no contrato, não no controller.

---

## Casos de uso

Um arquivo = um `handle()`. Sem SQL. Sem `echo`. Erro de regra vira `AppException` com status. O front controller traduz em JSON.

### `src/Application/AppException.php`

```php
<?php

namespace App\Application;

use RuntimeException;

final class AppException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message, $status);
    }
}
```


### `src/Application/RegisterUser.php`

```php
<?php

namespace App\Application;

use App\Domain\User;
use App\Domain\UserRepository;
use App\Infrastructure\NativePasswordHasher;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly NativePasswordHasher $hasher,
    ) {
    }

    public function handle(string $name, string $email, string $password): User
    {
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            throw new AppException(422, 'Nome, e-mail válido e senha com 8+ caracteres.');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new AppException(409, 'E-mail já cadastrado.');
        }

        return $this->users->save(new User(
            null,
            $name,
            $email,
            $this->hasher->hash($password),
        ));
    }
}
```


### `src/Application/LoginUser.php`

```php
<?php

namespace App\Application;

use App\Domain\UserRepository;
use App\Infrastructure\NativePasswordHasher;
use App\Infrastructure\TokenService;

final class LoginUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly NativePasswordHasher $hasher,
        private readonly TokenService $tokens,
    ) {
    }

    public function handle(string $email, string $password): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$this->hasher->verify($password, $user->passwordHash)) {
            throw new AppException(401, 'Credenciais inválidas.');
        }

        return [
            'token' => $this->tokens->issue((int) $user->id),
            'user'  => $user->toPublicArray(),
        ];
    }
}
```


### `src/Application/CreateTask.php`

```php
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

        return $this->tasks->save(new Task(null, $userId, $title, false));
    }
}
```


### `src/Application/ListTasks.php`

```php
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
```


### `src/Application/GetTask.php`

```php
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
        if ($task === null) {
            throw new AppException(404, 'Task não encontrada.');
        }

        return $task;
    }
}
```


### `src/Application/UpdateTask.php`

```php
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
```


### `src/Application/DeleteTask.php`

```php
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
```


Hasher e token ainda são classes concretas. Na entrevista: “dava para extrair interface; neste bolso não valia o arquivo extra.”

---

## Infraestrutura (PDO, hash, token)

Aqui mora SQL, `password_hash` e a tabela `tokens`. Trocar SQLite por MySQL mexe em `Connection`. Os casos de uso não sabem.

### `src/Infrastructure/Connection.php`

```php
<?php

namespace App\Infrastructure;

use PDO;

final class Connection
{
    public static function make(): PDO
    {
        $dir = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $dir . '/app.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $schema = file_get_contents(dirname(__DIR__, 2) . '/database/schema.sql');
        $pdo->exec($schema);

        return $pdo;
    }
}
```


### `src/Infrastructure/NativePasswordHasher.php`

```php
<?php

namespace App\Infrastructure;

final class NativePasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
```


### `src/Infrastructure/TokenService.php`

```php
<?php

namespace App\Infrastructure;

use PDO;

final class TokenService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare(
            'INSERT INTO tokens (token, user_id, created_at) VALUES (:token, :user_id, :created_at)'
        );
        $stmt->execute([
            'token'      => $token,
            'user_id'    => $userId,
            'created_at' => gmdate('c'),
        ]);

        return $token;
    }

    public function userIdFor(string $token): ?int
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM tokens WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        return $row ? (int) $row['user_id'] : null;
    }
}
```


### `src/Infrastructure/PdoUserRepository.php`

```php
<?php

namespace App\Infrastructure;

use App\Domain\User;
use App\Domain\UserRepository;
use PDO;

final class PdoUserRepository implements UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(User $user): User
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :hash)'
        );
        $stmt->execute([
            'name'  => $user->name,
            'email' => $user->email,
            'hash'  => $user->passwordHash,
        ]);

        return new User(
            (int) $this->pdo->lastInsertId(),
            $user->name,
            $user->email,
            $user->passwordHash,
        );
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->map($row) : null;
    }

    private function map(array $row): User
    {
        return new User(
            (int) $row['id'],
            $row['name'],
            $row['email'],
            $row['password_hash'],
        );
    }
}
```


### `src/Infrastructure/PdoTaskRepository.php`

```php
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
```


Prepared statement. Nunca concatene input no SQL. Senha: `password_hash` / `password_verify`. Nunca `md5`.

Token não é JWT de biblioteca: `bin2hex(random_bytes(32))` na tabela. Request seguinte manda `Authorization: Bearer …`.

---

## HTTP

Router na mão. JSON in/out. Auth lê o header. Controller não escreve SQL.

### `src/Presentation/Json.php`

```php
<?php

namespace App\Presentation;

final class Json
{
    public static function send(int $status, mixed $payload): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $message): never
    {
        self::send($status, ['error' => $message]);
    }

    public static function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::error(400, 'JSON inválido.');
        }

        return $data;
    }
}
```


### `src/Presentation/Router.php`

```php
<?php

namespace App\Presentation;

final class Router
{
    /** @var list<array{method: string, regex: string, names: list<string>, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return '([^/]+)';
        }, $path);

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'names'   => $names,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $allowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $allowed = true;
            if ($route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['names'] as $i => $name) {
                $params[$name] = $matches[$i + 1];
            }

            ($route['handler'])($params);
            return;
        }

        if ($allowed) {
            Json::error(405, 'Método não permitido.');
        }

        Json::error(404, 'Rota não encontrada.');
    }
}
```


### `src/Presentation/Auth.php`

```php
<?php

namespace App\Presentation;

use App\Infrastructure\TokenService;

final class Auth
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function userId(): int
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)/', $header, $m)) {
            Json::error(401, 'Token ausente.');
        }

        $userId = $this->tokens->userIdFor($m[1]);
        if ($userId === null) {
            Json::error(401, 'Token inválido.');
        }

        return $userId;
    }
}
```


### `src/Presentation/AuthController.php`

```php
<?php

namespace App\Presentation;

use App\Application\LoginUser;
use App\Application\RegisterUser;

final class AuthController
{
    public function __construct(
        private readonly RegisterUser $register,
        private readonly LoginUser $login,
    ) {
    }

    public function register(): never
    {
        $body = Json::body();
        $user = $this->register->handle(
            trim((string) ($body['name'] ?? '')),
            trim((string) ($body['email'] ?? '')),
            (string) ($body['password'] ?? ''),
        );

        Json::send(201, $user->toPublicArray());
    }

    public function login(): never
    {
        $body = Json::body();
        Json::send(200, $this->login->handle(
            trim((string) ($body['email'] ?? '')),
            (string) ($body['password'] ?? ''),
        ));
    }
}
```


### `src/Presentation/TaskController.php`

```php
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
```


---

## Composition root

`public/index.php` **monta** as peças e despacha. É o único lugar que conhece todo mundo.

### `public/index.php`

```php
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
    Json::error(500, 'Erro interno.');
}
```


`-t public` no built-in server: a URL é `/register`, não `/public/register`.

---

## SOLID neste tamanho

- **S** — `CreateTask` só cria. `LoginUser` só autentica.
- **O** — novo jeito de persistir = nova classe na interface. Não edita o caso de uso.
- **L** — `PdoTaskRepository` honra o contrato. `allForUser` devolve `Task[]`.
- **I** — `UserRepository` não tem método de task.
- **D** — `CreateTask` depende de `TaskRepository`, não de `PDO`.

## Na entrevista

> "Eu separei domínio, caso de uso e PDO. O controller não escreve SQL. Auth é token na tabela, não sessão. Toda query de task leva `user_id`. Se a vaga usar Laravel, o Eloquent entra no lugar do repository concreto — a ideia das camadas continua."

Se puxarem “isso não é Clean Architecture de verdade”: você concorda. É o recorte de entrevista.

## Recapitulando

- Front controller + router na mão
- Interface no domínio, PDO na infraestrutura
- Token Bearer, não `$_SESSION`
- 422 / 401 / 404 / 409 / 405 saem do caso de uso via `AppException`
- Projeto de bolso: SQLite, sem Composer obrigatório

## Exercícios práticos

### Exercício 1

**Enunciado:**
Adicione `POST /logout` que apaga o token atual. 204. Sem token → 401.

<details>
<summary>Solução</summary>

Em `TokenService`, um `revoke(string $token): void` com `DELETE FROM tokens WHERE token = :token`. Rota autenticada: lê o Bearer, apaga, 204. Não precisa apagar o user.

</details>

### Exercício 2

**Enunciado:**
Um user não pode ver a task do outro. Prove com dois cadastros e um GET no id alheio.

<details>
<summary>Solução</summary>

Registra João e Maria. João cria a task 1. Login da Maria, `GET /tasks/1` → 404 (`findByIdForUser` filtra `user_id`). Não devolve 403 com o id — você nem admite que existe.

</details>

### Exercício 3

**Enunciado:**
Extraia `PasswordHasher` como interface. Por quê?

<details>
<summary>Solução</summary>

Hoje `RegisterUser` e `LoginUser` conhecem `NativePasswordHasher` (infra). Interface no domínio/application: o caso de uso não muda se você trocar o algo. DIP de verdade. Neste bolso era opcional; na entrevista você demonstra que **sabe** onde cortar.

</details>

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
