# 3.1 Namespaces

## Resumo

> **Namespaces** — jeito de organizar o código em grupos lógicos, sem conflito de nomes.
>
> **O essencial:** `namespace App\Models;`, `use App\Models\User;`, `use ... as Alias;`, agrupamento `use App\Models\{User, Post}`.
>
> **PSR-4:** o namespace bate com a estrutura de pastas. `App\\Models\\User` → `app/Models/User.php`.

---

## Conteúdo

- [O que é namespace](#o-que-é-namespace)
- [Declaração de namespace](#declaração-de-namespace)
- [use, as, agrupamento](#use-as-agrupamento)
- [Namespace global](#namespace-global)
- [namespace e autoload (PSR-4)](#namespace-e-autoload-psr-4)
- [Constante __NAMESPACE__](#constante-__namespace__)
- [namespace_alias (use) para funções e constantes](#namespace_alias-use-para-funções-e-constantes)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é namespace

**O que é:**
Jeito de organizar o código em grupos lógicos, sem conflito de nomes.

**Como funciona:**
```php
// Arquivo: app/Models/User.php
namespace App\Models;

class User
{
    public string $name;
}

// Arquivo: app/Services/User.php
namespace App\Services;

class User  // Não conflita com App\Models\User
{
    public function process(): void {}
}

// Uso
use App\Models\User as ModelUser;
use App\Services\User as ServiceUser;

$model = new ModelUser();
$service = new ServiceUser();

// Ou o nome completo (Fully Qualified Name)
$model = new \App\Models\User();
$service = new \App\Services\User();
```

**Quando usar:**
**Sempre** no PHP moderno (PSR-4). Organizar código, evitar conflito de nomes.

**Exemplo prático:**
```php
// Estrutura Laravel
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $service,
    ) {}

    public function index(Request $request)
    {
        $users = User::all();
        return view('users.index', compact('users'));
    }
}

// Autoload do Composer (composer.json)
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Seeders\\": "database/seeders/"
        }
    }
}
```

**Na entrevista:**
> "Namespace organiza o código em grupos lógicos e evita conflito de nomes. No Laravel: App\Models, App\Http\Controllers. PSR-4 liga o namespace à pasta."

---

## Declaração de namespace

**O que é:**
Declarar o namespace no começo do arquivo.

**Como funciona:**
```php
<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            return User::create($data);
        });
    }
}

// Namespace aninhado
namespace App\Services\Payment;

class StripeService {}

namespace App\Services\Notification;

class EmailService {}

// Ou com chaves (raro)
namespace App\Services {
    class UserService {}
}

namespace App\Repositories {
    class UserRepository {}
}
```

**Quando usar:**
Um namespace por arquivo (primeira linha depois do `<?php`).

**Exemplo prático:**
```php
// app/Services/Order/OrderService.php
<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Services\Payment\PaymentGateway;

class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private PaymentGateway $gateway,
    ) {}

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->gateway->charge($order->amount);

        return $order;
    }
}

// Uso
use App\Services\Order\OrderService;

$service = app(OrderService::class);
```

**Na entrevista:**
> "O namespace vai na primeira linha depois do <?php. Um namespace por arquivo. Aninhado com \\ (App\\Services\\Order). Laravel segue PSR-4: namespace bate com a pasta."

---

## use, as, agrupamento

**O que é:**
Importar classes de outro namespace.

**Como funciona:**
```php
namespace App\Http\Controllers;

// Import da classe
use App\Models\User;
use App\Services\UserService;

// Alias (se houver conflito de nomes)
use App\Models\Post as PostModel;
use App\Services\Post as PostService;

// Agrupamento (PHP 7.0+)
use App\Models\{User, Post, Comment};
use App\Services\{
    UserService,
    PostService,
    CommentService
};

// Funções e constantes (PHP 5.6+)
use function App\Helpers\format_price;
use const App\Constants\MAX_ITEMS;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();  // Sem \App\Models\
        $service = new UserService();

        $price = format_price(1000);  // Função
        $limit = MAX_ITEMS;  // Constante

        return view('users.index', compact('users'));
    }
}

// Sem use (nome completo)
$user = new \App\Models\User();
```

**Quando usar:**
`use` para toda classe de outro namespace. Agrupamento para importar do mesmo namespace.

**Exemplo prático:**
```php
// Controller com vários imports
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{
    StorePostRequest,
    UpdatePostRequest
};
use App\Http\Resources\{
    PostResource,
    PostCollection
};
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Http\{JsonResponse, Request};

class PostController extends Controller
{
    public function __construct(
        private PostService $service,
    ) {}

    public function index(): PostCollection
    {
        $posts = Post::paginate(20);
        return new PostCollection($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->service->create($request->validated());
        return response()->json(new PostResource($post), 201);
    }
}

// Helper functions
namespace App\Helpers;

function format_price(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

// Uso
use function App\Helpers\format_price;

echo format_price(199900);  // "R$ 1.999,00"
```

**Na entrevista:**
> "use importa classes de outro namespace. as cria alias quando tem conflito. Agrupamento: use App\Models\{User, Post} para importar do mesmo namespace. Dá para importar função (use function) e constante (use const)."

---

## Namespace global

**O que é:**
Classes sem namespace (classes nativas do PHP, código legacy).

**Como funciona:**
```php
namespace App\Services;

// PDO — classe nativa no namespace global
$pdo = new \PDO('mysql:host=localhost', 'user', 'pass');

// Sem \ — procura no namespace atual
$pdo = new PDO();  // ❌ Class 'App\Services\PDO' not found

// Classes nativas do PHP
$date = new \DateTime();
$exception = new \Exception('Erro');
$reflection = new \ReflectionClass(User::class);

// Classe legacy sem namespace
class LegacyClass {}

// Uso
$legacy = new \LegacyClass();  // Do namespace global
```

**Quando usar:**
Sempre coloque `\` na frente das classes nativas do PHP (DateTime, Exception, PDO) dentro de um namespace.

**Exemplo prático:**
```php
namespace App\Services;

use App\Models\User;

class UserService
{
    public function create(array $data): User
    {
        try {
            // \DateTime — classe nativa
            $now = new \DateTime();

            $user = User::create([
                ...$data,
                'created_at' => $now,
            ]);

            return $user;
        } catch (\Exception $e) {  // \Exception — nativa
            throw new \RuntimeException('Falha ao criar o usuário', 0, $e);
        }
    }

    public function validate(string $email): bool
    {
        // filter_var — função nativa
        return filter_var($email, \FILTER_VALIDATE_EMAIL) !== false;
    }
}

// Ou importe
namespace App\Services;

use DateTime;
use Exception;
use RuntimeException;

class UserService
{
    public function create(array $data): User
    {
        try {
            $now = new DateTime();  // Sem \
            // ...
        } catch (Exception $e) {
            throw new RuntimeException('Falhou', 0, $e);
        }
    }
}
```

**Na entrevista:**
> "Namespace global é o das classes nativas do PHP (DateTime, Exception, PDO). Dentro de um namespace você precisa do \\ na frente ou de um use. Constantes do PHP (FILTER_VALIDATE_EMAIL) também ficam no global."

---

## namespace e autoload (PSR-4)

**O que é:**
Padrão que liga namespace à estrutura de pastas.

**Como funciona:**
```php
// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Seeders\\": "database/seeders/",
            "Tests\\": "tests/"
        }
    }
}

// Estrutura:
// app/
//   Models/
//     User.php        → namespace App\Models; class User
//   Services/
//     UserService.php → namespace App\Services; class UserService
//   Http/
//     Controllers/
//       UserController.php → namespace App\Http\Controllers; class UserController

// Autoload:
// new App\Models\User()         → app/Models/User.php
// new App\Services\UserService() → app/Services/UserService.php

// Depois de alterar o composer.json
composer dump-autoload
```

**Quando usar:**
**Sempre** siga PSR-4. Namespace = estrutura de pastas.

**Exemplo prático:**
```php
// Estrutura Laravel (PSR-4)
app/
  Http/
    Controllers/
      Api/
        PostController.php  → namespace App\Http\Controllers\Api;
    Middleware/
      Authenticate.php      → namespace App\Http\Middleware;
    Requests/
      StorePostRequest.php  → namespace App\Http\Requests;
  Models/
    User.php               → namespace App\Models;
    Post.php               → namespace App\Models;
  Services/
    Order/
      OrderService.php     → namespace App\Services\Order;

// O Composer carrega automaticamente pelo namespace
use App\Http\Controllers\Api\PostController;
use App\Services\Order\OrderService;

// Não precisa de require/include
$controller = new PostController();
$service = new OrderService();

// Namespace customizado
// composer.json
{
    "autoload": {
        "psr-4": {
            "MyApp\\": "src/",
            "MyApp\\Tests\\": "tests/"
        }
    }
}

// src/Services/Payment.php
namespace MyApp\Services;

class Payment {}

// Uso
use MyApp\Services\Payment;
$payment = new Payment();
```

**Na entrevista:**
> "PSR-4 liga namespace à pasta. O Composer carrega a classe sozinho. App\\ → app/, namespace App\\Models\\User → arquivo app/Models/User.php. Depois de mudar o composer.json eu rodo composer dump-autoload."

---

## Constante __NAMESPACE__

**O que é:**
Constante mágica que devolve o namespace atual.

**Como funciona:**
```php
namespace App\Services;

class UserService
{
    public function getCurrentNamespace(): string
    {
        return __NAMESPACE__;  // "App\Services"
    }

    public function getFullClassName(): string
    {
        return __NAMESPACE__ . '\\UserService';  // "App\Services\UserService"
    }
}

// Criação dinâmica de classe
namespace App\Services;

function createService(string $name): object
{
    $class = __NAMESPACE__ . '\\' . $name;  // "App\Services\UserService"
    return new $class();
}

$service = createService('UserService');
```

**Quando usar:**
Para criar classe dinâmica, metaprogramação.

**Exemplo prático:**
```php
// Factory de serviços
namespace App\Services;

class ServiceFactory
{
    public function make(string $serviceName): object
    {
        $class = __NAMESPACE__ . '\\' . $serviceName;

        if (!class_exists($class)) {
            throw new \RuntimeException("Service {$serviceName} não encontrado");
        }

        return app($class);  // Resolve pelo Service Container
    }
}

$factory = new ServiceFactory();
$userService = $factory->make('UserService');
$orderService = $factory->make('OrderService');

// Helper para criar DTO
namespace App\DTO;

function make(string $dtoName, array $data): object
{
    $class = __NAMESPACE__ . '\\' . $dtoName;
    return new $class(...$data);
}

$dto = make('CreateUserDTO', ['name' => 'João', 'email' => 'joao@email.com']);

// Log com namespace
namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;

class StripeService
{
    public function charge(int $amount): bool
    {
        Log::info(__NAMESPACE__ . ': Cobrando', ['amount' => $amount]);
        // [App\Services\Payment: Cobrando]

        return true;
    }
}
```

**Na entrevista:**
> "__NAMESPACE__ devolve o namespace atual. Uso para criar classe dinâmica, factory, log. __NAMESPACE__ . '\\\\' . $className monta o nome completo da classe."

---

## namespace_alias (use) para funções e constantes

**O que é:**
Importar funções e constantes de outro namespace.

**Como funciona:**
```php
// Arquivo com funções
namespace App\Helpers;

function format_price(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function truncate(string $text, int $length): string
{
    return mb_substr($text, 0, $length) . '...';
}

// Arquivo com constantes
namespace App\Constants;

const MAX_UPLOAD_SIZE = 10485760;  // 10MB
const ALLOWED_EXTENSIONS = ['jpg', 'png', 'pdf'];

// Uso
namespace App\Http\Controllers;

use function App\Helpers\{format_price, truncate};
use const App\Constants\{MAX_UPLOAD_SIZE, ALLOWED_EXTENSIONS};

class ProductController extends Controller
{
    public function index()
    {
        $price = format_price(199900);  // "R$ 1.999,00"
        $description = truncate('Texto longo...', 100);

        $maxSize = MAX_UPLOAD_SIZE;  // 10485760
        $extensions = ALLOWED_EXTENSIONS;  // ['jpg', 'png', 'pdf']

        return view('products.index', compact('price', 'description'));
    }
}

// Sem use (nome completo)
$price = \App\Helpers\format_price(199900);
$maxSize = \App\Constants\MAX_UPLOAD_SIZE;
```

**Quando usar:**
Para reusar funções e constantes entre namespaces.

**Exemplo prático:**
```php
// app/Helpers/helpers.php
namespace App\Helpers;

function array_get(array $array, string $key, mixed $default = null): mixed
{
    return $array[$key] ?? $default;
}

function str_limit(string $value, int $limit = 100): string
{
    return mb_substr($value, 0, $limit) . '...';
}

// composer.json (autoload de arquivos com funções)
{
    "autoload": {
        "files": [
            "app/Helpers/helpers.php"
        ]
    }
}

// Uso
namespace App\Services;

use function App\Helpers\{array_get, str_limit};

class DataService
{
    public function process(array $data): array
    {
        $name = array_get($data, 'name', 'Desconhecido');
        $description = str_limit($data['description'] ?? '', 200);

        return compact('name', 'description');
    }
}

// Helpers do Laravel (já estão no namespace global)
// Não precisa de use
$user = auth()->user();
$path = storage_path('app/files');
$config = config('app.name');
```

**Na entrevista:**
> "use function importa funções, use const importa constantes de outro namespace. Agrupamento: use function App\\Helpers\\{fn1, fn2}. Helpers do Laravel ficam no global (auth(), config())."

---

## Recapitulando

**O essencial:**
- `namespace App\Services;` — declaração de namespace
- `use App\Models\User;` — import da classe
- `use App\Models\User as ModelUser;` — alias
- `use App\Models\{User, Post};` — agrupamento
- `use function`, `use const` — import de funções e constantes
- `\DateTime` — namespace global (classes nativas)
- `__NAMESPACE__` — namespace atual

**PSR-4:**
- Namespace = estrutura de pastas
- `App\\Models\\User` → `app/Models/User.php`
- `composer dump-autoload` depois das mudanças

**Importante na entrevista:**
- Um namespace por arquivo (primeira linha)
- Sempre use para classes de outro namespace
- `\\` na frente das classes nativas (DateTime, Exception) dentro do namespace
- PSR-4: namespace bate com as pastas
- Laravel: App\\, Database\\, Tests\\
- Agrupamento use para importar do mesmo namespace

---

## Exercícios práticos

### Exercício 1: Crie uma estrutura de classes com namespace

**Enunciado:** Crie as classes `User`, `Post`, `Comment` no namespace `App\Models`. Crie os services `UserService`, `PostService` no namespace `App\Services`. Importe as dependências certo.

<details>
<summary>Solução</summary>

```php
// app/Models/User.php
<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}

// app/Models/Post.php
<?php

declare(strict_types=1);

namespace App\Models;

class Post
{
    public function __construct(
        public string $title,
        public User $author,
    ) {}
}

// app/Services/UserService.php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class UserService
{
    public function create(string $name, string $email): User
    {
        return new User($name, $email);
    }
}

// app/Services/PostService.php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\{Post, User};

class PostService
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function createPost(string $title, string $authorEmail): Post
    {
        $author = $this->userService->create('Autor', $authorEmail);
        return new Post($title, $author);
    }
}
```

**composer.json:**
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        }
    }
}
```

Depois das mudanças:
```bash
composer dump-autoload
```
</details>

### Exercício 2: Resolva o conflito de nomes

**Enunciado:** Há duas classes `User`: `App\Models\User` e `App\DTO\User`. Use as duas no mesmo arquivo, sem conflito.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Services;

use App\Models\User as UserModel;
use App\DTO\User as UserDTO;

class UserService
{
    public function create(UserDTO $dto): UserModel
    {
        $model = new UserModel();
        $model->name = $dto->name;
        $model->email = $dto->email;
        $model->save();

        return $model;
    }

    public function toDTO(UserModel $user): UserDTO
    {
        return new UserDTO(
            name: $user->name,
            email: $user->email,
        );
    }
}

// Uso
$dto = new UserDTO('João', 'joao@email.com');
$service = new UserService();
$user = $service->create($dto);  // UserModel
$backToDto = $service->toDTO($user);  // UserDTO
```
</details>

### Exercício 3: Crie helper functions com namespace

**Enunciado:** Crie o arquivo `app/Helpers/helpers.php` com as funções `format_price()` e `str_limit()` no namespace `App\Helpers`. Configure o autoload.

<details>
<summary>Solução</summary>

```php
// app/Helpers/helpers.php
<?php

namespace App\Helpers;

function format_price(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function str_limit(string $text, int $length): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return mb_substr($text, 0, $length) . '...';
}

// composer.json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "files": [
            "app/Helpers/helpers.php"
        ]
    }
}

// Uso
namespace App\Services;

use function App\Helpers\{format_price, str_limit};

class ProductService
{
    public function getFormattedPrice(int $price): string
    {
        return format_price($price);  // "R$ 19.999,00"
    }

    public function getShortDescription(string $description): string
    {
        return str_limit($description, 100);
    }
}

// Depois das mudanças
// composer dump-autoload
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
