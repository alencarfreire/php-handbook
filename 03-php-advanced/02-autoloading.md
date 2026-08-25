# 3.2 Autoload (Composer, PSR-4)

## Resumo

> **Autoload** — carrega classes sozinho, sem require/include, via Composer.
>
> **PSR-4:** Namespace = estrutura de pastas. `App\\Models\\User` → `app/Models/User.php`.
>
> **Importante:** `composer dump-autoload` depois de mudar, `--optimize` em produção.

---

## Conteúdo

- [O que é autoload](#o-que-é-autoload)
- [Padrão PSR-4](#padrão-psr-4)
- [composer dump-autoload](#composer-dump-autoload)
- [Autoload classmap](#autoload-classmap)
- [Autoload files](#autoload-files)
- [Autoload de dev (autoload-dev)](#autoload-de-dev-autoload-dev)
- [spl_autoload_register](#spl_autoload_register-autoloader-próprio)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é autoload

**O que é:**
Carrega classes sozinho, sem require/include.

**Como funciona:**
```php
// SEM autoload (jeito antigo)
require_once 'app/Models/User.php';
require_once 'app/Services/UserService.php';
require_once 'app/Repositories/UserRepository.php';

$user = new App\Models\User();
$service = new App\Services\UserService();

// COM autoload
// require_once 'vendor/autoload.php';  // Só uma vez

$user = new App\Models\User();  // Carrega app/Models/User.php sozinho
$service = new App\Services\UserService();  // Carrega app/Services/UserService.php sozinho
```

**Quando usar:**
**Sempre** via Composer (PSR-4).

**Exemplo prático:**
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

// index.php (Laravel public/index.php)
require __DIR__ . '/../vendor/autoload.php';

// Agora as classes carregam sozinhas
use App\Models\User;
use App\Services\UserService;

$user = User::find(1);  // Carrega app/Models/User.php sozinho
$service = new UserService();  // Carrega app/Services/UserService.php sozinho
```

**Na entrevista:**
> "Autoload carrega a classe sozinho, sem require. O Composer gera o autoloader no PSR-4. Você dá require em vendor/autoload.php uma vez. O Composer liga o namespace à pasta."

---

## Padrão PSR-4

**O que é:**
Padrão de autoload: namespace = estrutura de pastas.

**Como funciona:**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Tests\\": "tests/"
        }
    }
}

// Estrutura do projeto:
// app/
//   Models/
//     User.php        → namespace App\Models; class User
//     Post.php        → namespace App\Models; class Post
//   Services/
//     UserService.php → namespace App\Services; class UserService
//   Http/
//     Controllers/
//       UserController.php → namespace App\Http\Controllers; class UserController

// Regras do PSR-4:
// 1. Namespace = caminho a partir do diretório base
// 2. Nome do arquivo = nome da classe + .php
// 3. Uma classe = um arquivo

// Exemplo:
// App\Models\User → app/Models/User.php
// App\Services\Order\OrderService → app/Services/Order/OrderService.php

// Uso:
use App\Models\User;
use App\Services\UserService;

$user = new User();  // Carrega app/Models/User.php
$service = new UserService();  // Carrega app/Services/UserService.php
```

**Quando usar:**
**Sempre** siga o PSR-4 na estrutura do projeto.

**Exemplo prático:**
```php
// Estrutura Laravel (PSR-4)
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    }
}

// Arquivo: app/Http/Controllers/Api/PostController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        return Post::all();
    }
}

// Arquivo: app/Services/Order/OrderService.php
namespace App\Services\Order;

use App\Models\Order;
use App\Repositories\OrderRepository;

class OrderService
{
    public function __construct(
        private OrderRepository $repository,
    ) {}

    public function create(array $data): Order
    {
        return $this->repository->create($data);
    }
}

// O Composer já sabe:
// App\Http\Controllers\Api\PostController → app/Http/Controllers/Api/PostController.php
// App\Services\Order\OrderService → app/Services/Order/OrderService.php
```

**Na entrevista:**
> "PSR-4 é o padrão de autoload. Namespace = estrutura de pastas. App\\ → app/, App\\Models\\User → app/Models/User.php. Uma classe = um arquivo. Nome do arquivo = nome da classe + .php."

---

## composer dump-autoload

**O que é:**
Comando que regenera os arquivos de autoload.

**Como funciona:**
```bash
# Depois de mudar o composer.json
composer dump-autoload

# Autoload otimizado (produção)
composer dump-autoload --optimize
# ou
composer dump-autoload -o

# Autoload authoritative (ainda mais rápido)
composer dump-autoload --classmap-authoritative
# ou
composer dump-autoload -a
```

**Quando usar:**
- Depois de mudar `autoload` no composer.json
- Depois de adicionar um namespace novo
- Antes do deploy (com `--optimize`)

**Exemplo prático:**
```php
// 1. Novo namespace no composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "MyPackage\\": "packages/my-package/src/"  // Novo namespace
        }
    }
}

// 2. Regenerar o autoload
composer dump-autoload

// 3. Agora dá para usar
use MyPackage\Services\MyService;
$service = new MyService();

// Produção (mais rápido)
composer dump-autoload --optimize

// Laravel Artisan (wrapper)
php artisan optimize  # Inclui composer dump-autoload -o

// CI/CD pipeline
composer install --no-dev --optimize-autoloader
```

**Na entrevista:**
> "composer dump-autoload regenera os arquivos de autoload. Eu rodo depois de mudar o autoload no composer.json. --optimize em produção, fica mais rápido. No Laravel, php artisan optimize já chama o dump-autoload."

---

## Autoload classmap

**O que é:**
Outro jeito de autoload: lista de classes e caminhos.

**Como funciona:**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "classmap": [
            "database/seeders",
            "database/factories"
        ]
    }
}

// classmap NÃO exige namespace = pasta
// database/seeders/UserSeeder.php
namespace Database\Seeders;

class UserSeeder extends Seeder
{
    // O arquivo pode estar em qualquer lugar, o Composer acha a classe
}

// Depois das mudanças
composer dump-autoload

// Agora dá para usar
use Database\Seeders\UserSeeder;
$seeder = new UserSeeder();
```

**Quando usar:**
Código legado, teste, seeder — quando o PSR-4 não vale.

**Exemplo prático:**
```php
// Laravel usa classmap em database
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        },
        "classmap": [
            "database/seeders",
            "database/factories"
        ]
    }
}

// Código legado (não PSR-4)
// lib/
//   some_old_file.php  → class SomeOldClass {}
//   another.php        → class AnotherClass {}

// composer.json
{
    "autoload": {
        "classmap": [
            "lib/"
        ]
    }
}

composer dump-autoload

// Agora dá para usar
$obj = new SomeOldClass();  // Acha em lib/some_old_file.php
```

**Na entrevista:**
> "classmap é uma lista de pastas com classes. O Composer varre os arquivos e monta o mapa. Não exige namespace = pasta. Uso em código legado e seeder. PSR-4 é o preferido."

---

## Autoload files

**O que é:**
Carrega arquivo sozinho (função, constante).

**Como funciona:**
```php
// composer.json
{
    "autoload": {
        "files": [
            "app/helpers.php",
            "app/constants.php"
        ]
    }
}

// app/helpers.php
<?php

if (!function_exists('format_price')) {
    function format_price(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }
}

if (!function_exists('str_limit')) {
    function str_limit(string $text, int $length): string
    {
        return mb_substr($text, 0, $length) . '...';
    }
}

// app/constants.php
<?php

define('MAX_UPLOAD_SIZE', 10485760);  // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'png', 'pdf']);

// Depois das mudanças
composer dump-autoload

// Os arquivos entram no require 'vendor/autoload.php'
$price = format_price(199900);  // "R$ 1.999,00"
$limit = str_limit('Texto longo...', 100);

$maxSize = MAX_UPLOAD_SIZE;
```

**Quando usar:**
Função global, constante, arquivo de bootstrap.

**Exemplo prático:**
```php
// Estrutura Laravel
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "files": [
            "app/helpers.php"  // Helpers globais
        ]
    }
}

// app/helpers.php
<?php

// O Laravel já tem vários helpers
// Você pode adicionar os seus

if (!function_exists('active_class')) {
    function active_class(string $path, string $active = 'active'): string
    {
        return request()->is($path) ? $active : '';
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date): string
    {
        return $date ? (new DateTime($date))->format('d/m/Y') : '';
    }
}

// Uso no Blade
<li class="{{ active_class('users*') }}">
    <a href="/users">Usuários</a>
</li>

// Uso no PHP
$formatted = format_date($user->created_at);

// Pacotes também registram files
// vendor/laravel/framework/composer.json
{
    "autoload": {
        "files": [
            "src/Illuminate/Foundation/helpers.php",
            "src/Illuminate/Support/helpers.php"
        ]
    }
}
```

**Na entrevista:**
> "files carrega o arquivo no require de vendor/autoload.php. Uso para função global e constante. O Laravel carrega helpers.php assim. if (!function_exists()) evita conflito."

---

## Autoload de dev (autoload-dev)

**O que é:**
Autoload só de desenvolvimento (teste).

**Como funciona:**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}

// Estrutura:
// tests/
//   Unit/
//     ExampleTest.php → namespace Tests\Unit; class ExampleTest
//   Feature/
//     UserTest.php    → namespace Tests\Feature; class UserTest

// tests/Feature/UserTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class UserTest extends TestCase
{
    public function test_user_can_be_created(): void
    {
        $user = User::factory()->create();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}

// Em desenvolvimento (composer install)
composer dump-autoload  # Carrega autoload + autoload-dev

// Em produção (composer install --no-dev)
composer install --no-dev  # NÃO carrega autoload-dev (teste não entra)
```

**Quando usar:**
Teste, utilitário de dev, fixture.

**Exemplo prático:**
```php
// composer.json (exemplo completo)
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        },
        "files": [
            "app/helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}

// tests/Unit/Services/UserServiceTest.php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\UserService;
use App\Repositories\UserRepository;
use Mockery;

class UserServiceTest extends TestCase
{
    public function test_create_user(): void
    {
        $repository = Mockery::mock(UserRepository::class);
        $service = new UserService($repository);

        $repository->shouldReceive('create')
            ->once()
            ->andReturn(new User());

        $user = $service->create(['name' => 'Teste']);

        $this->assertInstanceOf(User::class, $user);
    }
}

// CI/CD
# Instala dependências de teste
composer install

# Roda os testes
php artisan test

# Deploy (sem dependências de dev)
composer install --no-dev --optimize-autoloader
```

**Na entrevista:**
> "autoload-dev é só de desenvolvimento: teste, utilitário de dev. composer install carrega. composer install --no-dev não carrega. Em produção teste não entra. No Laravel: Tests\\ → tests/."

---

## spl_autoload_register (autoloader próprio)

**O que é:**
Registra o seu autoloader (sem Composer).

**Como funciona:**
```php
// Autoloader próprio (PSR-4)
spl_autoload_register(function ($class) {
    // App\Models\User → app/Models/User.php

    // Namespace base
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    // Checa o prefixo
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;  // Não é o nosso namespace
    }

    // Nome relativo da classe
    $relativeClass = substr($class, strlen($prefix));

    // Troca \ por /
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Carrega o arquivo
    if (file_exists($file)) {
        require $file;
    }
});

// Agora dá para usar
$user = new App\Models\User();  // Carrega app/Models/User.php sozinho

// Vários autoloaders
spl_autoload_register(function ($class) {
    // Primeiro autoloader
});

spl_autoload_register(function ($class) {
    // Segundo autoloader
});
// Chamam em sequência até a classe carregar
```

**Quando usar:**
Raro (Composer é melhor). Projeto pequeno sem Composer.

**Exemplo prático:**
```php
// index.php (sem Composer)
<?php

// Autoloader PSR-4
spl_autoload_register(function ($class) {
    $namespaces = [
        'App\\' => __DIR__ . '/app/',
        'Lib\\' => __DIR__ . '/lib/',
    ];

    foreach ($namespaces as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
                return;
            }
        }
    }
});

// Agora dá para usar
use App\Controllers\HomeController;
use Lib\Database\Connection;

$controller = new HomeController();
$db = new Connection();

// O Composer faz o mesmo (e melhor)
require 'vendor/autoload.php';
```

**Na entrevista:**
> "spl_autoload_register registra o seu autoloader. A função recebe o nome da classe, vira caminho de arquivo e carrega. O Composer usa isso por baixo. Em projeto de verdade eu fico com o Composer."

---

## Recapitulando

**O essencial:**
- **PSR-4** — padrão de autoload (namespace = estrutura de pastas)
- `composer dump-autoload` — regenera o autoloader
- `--optimize` — autoload otimizado (produção)
- **classmap** — lista de pastas com classes (não exige PSR-4)
- **files** — carrega arquivo (função, constante)
- **autoload-dev** — só de desenvolvimento (teste)
- `spl_autoload_register` — autoloader próprio (raro)

**Estrutura do composer.json:**
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "classmap": ["database/seeders"],
        "files": ["app/helpers.php"]
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

**Importante na entrevista:**
- PSR-4: App\\Models\\User → app/Models/User.php
- Depois de mudar o composer.json: composer dump-autoload
- Produção: composer dump-autoload --optimize
- files para função global (helpers.php)
- autoload-dev não entra em produção (--no-dev)
- O Composer usa spl_autoload_register por baixo

---

## Exercícios práticos

### Exercício 1: Configure o autoload PSR-4 de um pacote
**Enunciado:** Crie a estrutura do pacote `MyPackage` com namespace `MyCompany\MyPackage`. Configure o autoload.

<details>
<summary>Solução</summary>

```json
// composer.json
{
    "name": "mycompany/mypackage",
    "autoload": {
        "psr-4": {
            "MyCompany\\MyPackage\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "MyCompany\\MyPackage\\Tests\\": "tests/"
        }
    }
}
```

Estrutura:
```
mypackage/
  src/
    Services/
      UserService.php    → namespace MyCompany\MyPackage\Services;
    Models/
      User.php          → namespace MyCompany\MyPackage\Models;
    helpers.php         → namespace MyCompany\MyPackage;
  tests/
    Unit/
      UserServiceTest.php → namespace MyCompany\MyPackage\Tests\Unit;
  composer.json
```

```php
// src/Services/UserService.php
<?php

namespace MyCompany\MyPackage\Services;

use MyCompany\MyPackage\Models\User;

class UserService
{
    public function create(string $name): User
    {
        return new User($name);
    }
}
```

Depois de criar a estrutura:
```bash
composer dump-autoload
```

Uso no projeto:
```php
use MyCompany\MyPackage\Services\UserService;

$service = new UserService();
$user = $service->create('João');
```
</details>

### Exercício 2: Inclua código legado via classmap
**Enunciado:** Tem uma pasta `legacy/` com classes sem namespace. Inclua elas no autoload.

<details>
<summary>Solução</summary>

Estrutura:
```
legacy/
  OldUser.php      → class OldUser {}
  OldProduct.php   → class OldProduct {}
  helpers.php      → function old_helper() {}
```

```json
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "classmap": [
            "legacy/"
        ]
    }
}
```

```bash
composer dump-autoload
```

Uso:
```php
// Classes do legacy ficam globais
$user = new OldUser();
$product = new OldProduct();

// Funções também ficam disponíveis
old_helper();
```

**Alternativa (só classes, sem funções):**
```json
{
    "autoload": {
        "classmap": [
            "legacy/OldUser.php",
            "legacy/OldProduct.php"
        ]
    }
}
```
</details>

### Exercício 3: Otimize o autoload de produção
**Enunciado:** Monte os comandos de deploy de um app Laravel com autoload otimizado.

<details>
<summary>Solução</summary>

```bash
# 1. Instala dependências sem pacotes de dev
composer install --no-dev --optimize-autoloader

# 2. Ou separado (se já estiver instalado)
composer dump-autoload --optimize --no-dev

# 3. Otimização Laravel (inclui composer dump-autoload -o)
php artisan optimize

# 4. Checa o modo de autoload
composer dump-autoload --optimize --classmap-authoritative

# Script completo de deploy
#!/bin/bash

# Instala dependências
composer install --no-dev --optimize-autoloader

# Cache do Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Permissões
chmod -R 755 storage bootstrap/cache

# Migrations
php artisan migrate --force
```

**Scripts do Composer (composer.json):**
```json
{
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "deploy": [
            "composer install --no-dev --optimize-autoloader",
            "@php artisan optimize",
            "@php artisan migrate --force"
        ]
    }
}
```

Uso:
```bash
composer deploy
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
