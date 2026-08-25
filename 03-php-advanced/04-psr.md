# 3.4 Padrões PSR

## Resumo

> **PSR** — PHP Standard Recommendations, padrões para compatibilidade entre bibliotecas.
>
> **Principais:** PSR-4 (autoload), PSR-3 (log), PSR-11 (container), PSR-12 (estilo de código).
>
> **Laravel** segue PSR-4, PSR-3, PSR-11, PSR-16.

---

## Conteúdo

- [O que é PSR](#o-que-é-psr)
- [PSR-1: Basic Coding Standard](#psr-1-basic-coding-standard)
- [PSR-12: Extended Coding Style Guide](#psr-12-extended-coding-style-guide)
- [PSR-3: Logger Interface](#psr-3-logger-interface)
- [PSR-4: Autoloading Standard](#psr-4-autoloading-standard)
- [PSR-11: Container Interface](#psr-11-container-interface)
- [PSR-16: Simple Cache](#psr-16-simple-cache)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é PSR

**O que é:**
PHP Standard Recommendations — padrões de código e interfaces para bibliotecas falarem a mesma língua.

**Principais PSR:**
- **PSR-1** — Basic Coding Standard (estilo básico)
- **PSR-2** — Coding Style Guide (legado, substituído pelo PSR-12)
- **PSR-3** — Logger Interface
- **PSR-4** — Autoloading Standard
- **PSR-6** — Caching Interface
- **PSR-7** — HTTP Message Interface
- **PSR-11** — Container Interface
- **PSR-12** — Extended Coding Style Guide
- **PSR-15** — HTTP Server Request Handlers
- **PSR-16** — Simple Cache

**Quando usar:**
**Sempre.** Siga os padrões PSR. Compatibilidade e leitura.

**Exemplo prático:**
```php
// Laravel segue PSR-4, PSR-3, PSR-11, PSR-16

// Autoload PSR-4
use App\Models\User;
use App\Services\UserService;

// Logger PSR-3
use Psr\Log\LoggerInterface;

class OrderService
{
    public function __construct(
        private LoggerInterface $logger,  // PSR-3
    ) {}

    public function create(array $data): Order
    {
        $this->logger->info('Criando pedido', $data);
        // ...
    }
}

// Container PSR-11
$service = app(OrderService::class);  // Laravel Container (PSR-11)

// Simple Cache PSR-16
Cache::put('key', 'value', 3600);  // Laravel Cache (PSR-16)
```

**Na entrevista:**
> "PSR são os padrões do PHP. PSR-4 é autoload, PSR-3 é log, PSR-11 é container. Laravel segue PSR. Isso deixa biblioteca compatível e o código legível."

---

## PSR-1: Basic Coding Standard

**O que é:**
Regras básicas de estilo.

**Regras:**
```php
// 1. <?php ou <?= para abrir
<?php

// 2. Só UTF-8 sem BOM
// 3. Arquivo declara símbolos (classes) OU produz efeito colateral, nunca os dois

// RUIM (declaração + efeito colateral)
<?php
class User {}
echo "Olá";  // Efeito colateral

// BOM (só declaração)
<?php
class User {}

// BOM (só efeitos colaterais)
<?php
require 'vendor/autoload.php';
$app->run();

// 4. namespace e use depois de <?php
<?php

declare(strict_types=1);  // Opcional

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model {}

// 5. Nome de classe em StudlyCaps (PascalCase)
class UserService {}
class OrderController {}

// 6. Constante de classe em UPPER_CASE
class Config
{
    public const DB_HOST = 'localhost';
    public const DB_PORT = 5432;
}

// 7. Método em camelCase
class UserService
{
    public function createUser() {}
    public function getUserById() {}
}
```

**Quando usar:**
**Sempre.** Siga o PSR-1.

**Exemplo prático:**
```php
// Arquivo Laravel (segue PSR-1)
<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Psr\Log\LoggerInterface;

class OrderService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    public function __construct(
        private OrderRepository $repository,
        private LoggerInterface $logger,
    ) {}

    public function createOrder(array $data): Order
    {
        $this->logger->info('Criando pedido');
        return $this->repository->create($data);
    }

    public function getOrderById(int $id): ?Order
    {
        return $this->repository->find($id);
    }
}
```

**Na entrevista:**
> "PSR-1: arquivo em UTF-8, classe em PascalCase, método em camelCase, constante em UPPER_CASE. Um arquivo — ou declaração, ou efeito colateral. declare(strict_types=1) no topo."

---

## PSR-12: Extended Coding Style Guide

**O que é:**
Regras de estilo estendidas. Substituiu o PSR-2.

**Regras:**
```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{User, Post, Comment};  // Agrupamento
use Illuminate\Support\Collection;

// 1. Indentação: 4 espaços (não tab)
// 2. Linha: de preferência <= 120 caracteres

class UserService
{
    // 3. Visibilidade obrigatória em toda propriedade e método
    private string $name;
    protected int $age;
    public bool $isActive;

    // 4. Construtor
    public function __construct(
        private UserRepository $repository,  // Cada parâmetro em uma linha
        private LoggerInterface $logger,
    ) {}  // Parêntese de fechamento em linha nova

    // 5. Métodos: { na mesma linha (PSR-12; na linha nova é legado)
    public function create(array $data): User
    {
        // Corpo do método
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Nome é obrigatório');
        }

        return $this->repository->create($data);
    }

    // 6. if, for, foreach: { na mesma linha
    public function process(Collection $items): void
    {
        foreach ($items as $item) {
            if ($item->isValid()) {
                $this->processItem($item);
            } else {
                $this->skipItem($item);
            }
        }
    }

    // 7. Tipo de retorno na mesma linha
    public function getUserById(int $id): ?User
    {
        return $this->repository->find($id);
    }
}

// 8. Operadores: espaço em volta
$sum = $a + $b;  // ✅
$sum=$a+$b;      // ❌

// 9. Vírgula no array: espaço depois
$array = [1, 2, 3];  // ✅
$array = [1,2,3];    // ❌

// 10. use no topo do arquivo, ordem alfabética
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
```

**Quando usar:**
**Sempre.** Use PHP CS Fixer para formatar sozinho.

**Exemplo prático:**
```bash
# PHP CS Fixer
composer require friendsofphp/php-cs-fixer --dev

# .php-cs-fixer.php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/app')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);

# Rodar
php-cs-fixer fix

# Laravel Pint (já vem no Laravel 9+)
./vendor/bin/pint

# composer.json
{
    "scripts": {
        "format": "pint"
    }
}

composer format
```

**Na entrevista:**
> "PSR-12: indentação de 4 espaços, visibilidade obrigatória, { na mesma linha no if/foreach. PHP CS Fixer ou Laravel Pint formata sozinho. PSR-12 substituiu o PSR-2."

---

## PSR-3: Logger Interface

**O que é:**
Interface padrão de log.

**Como funciona:**
```php
// Interface PSR-3
namespace Psr\Log;

interface LoggerInterface
{
    public function emergency(string|\Stringable $message, array $context = []): void;
    public function alert(string|\Stringable $message, array $context = []): void;
    public function critical(string|\Stringable $message, array $context = []): void;
    public function error(string|\Stringable $message, array $context = []): void;
    public function warning(string|\Stringable $message, array $context = []): void;
    public function notice(string|\Stringable $message, array $context = []): void;
    public function info(string|\Stringable $message, array $context = []): void;
    public function debug(string|\Stringable $message, array $context = []): void;
    public function log($level, string|\Stringable $message, array $context = []): void;
}

// Uso
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function create(array $data): User
    {
        $this->logger->info('Criando usuário', ['email' => $data['email']]);

        try {
            $user = User::create($data);
            $this->logger->info('Usuário criado', ['id' => $user->id]);

            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Falha ao criar usuário', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }
}
```

**Quando usar:**
Para ficar compatível com qualquer logger (Monolog, Laravel Log).

**Exemplo prático:**
```php
// Laravel implementa PSR-3
use Illuminate\Support\Facades\Log;

// Métodos PSR-3
Log::emergency('Sistema fora do ar');
Log::alert('Problema crítico');
Log::critical('App caiu');
Log::error('Usuário não encontrado', ['id' => 123]);
Log::warning('Query lenta', ['time' => 5.2]);
Log::notice('Usuário fez login', ['user_id' => 1]);
Log::info('Processando pedido', ['order_id' => 456]);
Log::debug('Info de debug', ['var' => $value]);

// DI via PSR-3
class OrderService
{
    public function __construct(
        private LoggerInterface $logger,  // Qualquer implementação PSR-3
    ) {}

    public function process(Order $order): void
    {
        $this->logger->info('Processando pedido', [
            'order_id' => $order->id,
            'amount' => $order->amount,
        ]);

        // Lógica
    }
}

// O Service Container (container de serviços) do Laravel injeta sozinho
$service = app(OrderService::class);

// Dá para trocar a implementação
app()->bind(LoggerInterface::class, MyCustomLogger::class);
```

**Na entrevista:**
> "PSR-3 é a interface padrão de log. Métodos: emergency, alert, critical, error, warning, notice, info, debug. Laravel Log implementa PSR-3. DI via LoggerInterface para trocar o logger."

---

## PSR-4: Autoloading Standard

**O que é:**
Padrão de autoload de classes (veja o tópico 3.2).

**Regras:**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\": "database/"
        }
    }
}

// Regras PSR-4:
// 1. Nome completo da classe = namespace + class name
// 2. Namespace bate com a estrutura de pastas
// 3. Nome do arquivo = nome da classe + .php

// Exemplo:
// Classe: App\Services\Order\OrderService
// Arquivo: app/Services/Order/OrderService.php

// namespace App\Services\Order; — bate com app/Services/Order/
// class OrderService             — bate com OrderService.php
```

**Na entrevista:**
> "PSR-4 é o padrão de autoload. Namespace bate com a pasta. App\\ → app/, nome do arquivo = nome da classe. O Composer carrega sozinho."

---

## PSR-11: Container Interface

**O que é:**
Interface padrão de Service Container para Dependency Injection.

**Como funciona:**
```php
// Interface PSR-11
namespace Psr\Container;

interface ContainerInterface
{
    public function get(string $id): mixed;  // Pegar o service
    public function has(string $id): bool;   // Checar se existe
}

// Laravel Container implementa PSR-11
use Psr\Container\ContainerInterface;

$container = app();  // Laravel Container (PSR-11)

// Métodos PSR-11
if ($container->has(UserService::class)) {
    $service = $container->get(UserService::class);
}

// Uso
class OrderController
{
    public function __construct(
        private ContainerInterface $container,  // PSR-11
    ) {}

    public function index()
    {
        $service = $this->container->get(OrderService::class);
        $orders = $service->all();

        return view('orders.index', compact('orders'));
    }
}
```

**Quando usar:**
Para ficar compatível com qualquer container.

**Exemplo prático:**
```php
// Laravel Service Container (PSR-11)
app()->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

// Busca via PSR-11
$repository = app()->get(UserRepositoryInterface::class);

// Checagem
if (app()->has(UserRepositoryInterface::class)) {
    // ...
}

// DI no construtor (Laravel usa o container sozinho)
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {}
}

$service = app(UserService::class);  // Laravel resolve as dependências
```

**Na entrevista:**
> "PSR-11 é a interface padrão do container. Métodos: get(), has(). Laravel Container implementa PSR-11. Serve para DI, resolver dependência."

---

## PSR-16: Simple Cache

**O que é:**
Interface simples e padrão de cache.

**Como funciona:**
```php
// Interface PSR-16
namespace Psr\SimpleCache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function getMultiple(iterable $keys, mixed $default = null): iterable;
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool;
    public function deleteMultiple(iterable $keys): bool;
    public function has(string $key): bool;
}

// Laravel Cache implementa PSR-16
use Psr\SimpleCache\CacheInterface;

class UserService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function getUser(int $id): ?User
    {
        $key = "user:{$id}";

        // Métodos PSR-16
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $user = User::find($id);

        $this->cache->set($key, $user, 3600);

        return $user;
    }
}
```

**Quando usar:**
Para ficar compatível com qualquer driver de cache.

**Exemplo prático:**
```php
// Laravel Cache Facade (PSR-16)
use Illuminate\Support\Facades\Cache;

// Métodos PSR-16
Cache::set('key', 'value', 3600);
$value = Cache::get('key');
$exists = Cache::has('key');
Cache::delete('key');

// Operações em lote
Cache::setMultiple([
    'user:1' => $user1,
    'user:2' => $user2,
], 3600);

$users = Cache::getMultiple(['user:1', 'user:2']);

Cache::deleteMultiple(['user:1', 'user:2']);

// DI via PSR-16
$cache = app(CacheInterface::class);
$cache->set('key', 'value', 3600);

// Dá para trocar o driver (Redis, Memcached, File)
// config/cache.php
'default' => env('CACHE_DRIVER', 'redis'),
```

**Na entrevista:**
> "PSR-16 é a interface simples de cache. Métodos: get(), set(), delete(), has(). Laravel Cache implementa PSR-16. Compatível com Redis, Memcached."

---

## Recapitulando

**Principais PSR:**
- **PSR-1** — estilo básico (UTF-8, PascalCase nas classes)
- **PSR-12** — estilo estendido (substituiu o PSR-2, 4 espaços, visibilidade)
- **PSR-3** — Logger Interface (emergency, alert, error, warning, info)
- **PSR-4** — autoload (namespace = estrutura de pastas)
- **PSR-11** — Container Interface (get, has)
- **PSR-16** — Simple Cache (get, set, delete, has)
- **PSR-7** — HTTP Message Interface (Request, Response)
- **PSR-15** — HTTP Server Request Handlers (Middleware)

**Para que servem:**
- Compatibilidade entre bibliotecas
- Estilo único de código
- Troca de implementação sem dor

**Importante na entrevista:**
- Laravel segue PSR-4, PSR-3, PSR-11, PSR-16
- PSR-12 para formatar (PHP CS Fixer, Laravel Pint)
- PSR-3 para log (LoggerInterface)
- PSR-11 para DI (ContainerInterface)
- PSR-4 para autoload (obrigatório)

---

## Exercícios práticos

### Exercício 1: Configure o Laravel Pint para PSR-12

**Enunciado:** Configure o Laravel Pint para formatar o código automaticamente no PSR-12.

<details>
<summary>Solução</summary>

```json
// pint.json (na raiz do projeto)
{
    "preset": "psr12",
    "rules": {
        "array_syntax": {
            "syntax": "short"
        },
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "no_unused_imports": true,
        "blank_line_after_namespace": true,
        "blank_line_after_opening_tag": true,
        "concat_space": {
            "spacing": "one"
        },
        "trailing_comma_in_multiline": {
            "elements": ["arrays"]
        }
    }
}
```

```json
// composer.json (adicionar scripts)
{
    "scripts": {
        "format": "pint",
        "format:test": "pint --test",
        "format:dirty": "pint --dirty"
    }
}
```

Uso:
```bash
# Formatar todos os arquivos
./vendor/bin/pint

# Ou via composer
composer format

# Checar sem alterar
composer format:test

# Só arquivos alterados (git)
composer format:dirty
```

**.gitignore:**
```
.php-cs-fixer.cache
```

**CI/CD (GitHub Actions):**
```yaml
# .github/workflows/pint.yml
name: Laravel Pint

on: [push, pull_request]

jobs:
  pint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2

      - name: Install Dependencies
        run: composer install

      - name: Run Pint
        run: ./vendor/bin/pint --test
```
</details>

### Exercício 2: Implemente um Logger PSR-3

**Enunciado:** Crie um Logger customizado que implementa a interface PSR-3 e grava em arquivo.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class FileLogger implements LoggerInterface
{
    public function __construct(
        private string $logFile,
    ) {}

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        $message = $this->interpolate($message, $context);

        $contextJson = !empty($context) ? ' ' . json_encode($context) : '';

        $logMessage = "[{$timestamp}] {$levelUpper}: {$message}{$contextJson}\n";

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    private function interpolate(string|\Stringable $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr((string) $message, $replace);
    }
}

// Registro no Service Provider do Laravel
namespace App\Providers;

use App\Logging\FileLogger;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoggerInterface::class, function () {
            return new FileLogger(storage_path('logs/app.log'));
        });
    }
}

// Uso
use Psr\Log\LoggerInterface;

class UserService
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function create(array $data): User
    {
        $this->logger->info('Criando usuário', ['email' => $data['email']]);

        try {
            $user = User::create($data);
            $this->logger->info('Usuário criado', ['id' => $user->id]);

            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Falha ao criar usuário', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }
}
```
</details>

### Exercício 3: Crie um container compatível com PSR-11

**Enunciado:** Implemente um container de DI simples, compatível com PSR-11.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $id, callable|string $concrete): void
    {
        $this->bindings[$id] = $concrete;
    }

    public function singleton(string $id, callable|string $concrete): void
    {
        $this->bind($id, $concrete);
        // Marca como singleton
        $this->bindings[$id . '.singleton'] = true;
    }

    public function get(string $id): mixed
    {
        // Se já tem singleton pronto
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Se tem binding
        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];

            $object = is_callable($concrete)
                ? $concrete($this)
                : $this->resolve($concrete);

            // Guardar singleton
            if (isset($this->bindings[$id . '.singleton'])) {
                $this->instances[$id] = $object;
            }

            return $object;
        }

        // Tenta resolver sozinho
        if (class_exists($id)) {
            return $this->resolve($id);
        }

        throw new class("Entrada '{$id}' não encontrada no container") extends \Exception implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->instances[$id])
            || class_exists($id);
    }

    private function resolve(string $class): object
    {
        $reflection = new ReflectionClass($class);

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                if ($parameter->isDefaultValueAvailable()) {
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new \Exception("Não foi possível resolver {$parameter->getName()} em {$class}");
                }
            } else {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}

// Uso
$container = new Container();

// Bind da interface na implementação
$container->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

// Singleton
$container->singleton(DatabaseConnection::class, function ($container) {
    return new DatabaseConnection('localhost', 'mydb');
});

// Busca
$repository = $container->get(UserRepositoryInterface::class);

// Resolução automática de dependências
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private LoggerInterface $logger,
    ) {}
}

$service = $container->get(UserService::class);
// Resolve UserRepositoryInterface e LoggerInterface sozinho
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
