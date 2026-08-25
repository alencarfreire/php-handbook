# 10.2 Repository Pattern

## Resumo

> **Repository Pattern** — camada de abstração entre a lógica de negócio e o acesso a dados.
>
> **Para quê:** Isolar do ORM, reusar queries, testar com mock.
>
> **Importante:** A Interface define o contrato, a Implementation tem as queries. Você registra no Service Provider.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Repository — camada de abstração entre a lógica de negócio e o acesso a dados. Encapsula as queries no banco.

**Para quê:**
- Isolar do ORM
- Reusar queries
- Testabilidade (mock do repository)

---

## Como funciona

**Interface:**

```php
// app/Contracts/PostRepositoryInterface.php
interface PostRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Post;
    public function create(array $data): Post;
    public function update(Post $post, array $data): Post;
    public function delete(Post $post): bool;
}
```

**Implementation:**

```php
// app/Repositories/PostRepository.php
class PostRepository implements PostRepositoryInterface
{
    public function all(): Collection
    {
        return Post::with('user')->latest()->get();
    }

    public function find(int $id): ?Post
    {
        return Post::with('user', 'comments')->find($id);
    }

    public function create(array $data): Post
    {
        return Post::create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);
        return $post;
    }

    public function delete(Post $post): bool
    {
        return $post->delete();
    }

    // Métodos customizados
    public function findPublished(): Collection
    {
        return Post::where('published', true)->get();
    }

    public function findByUser(User $user): Collection
    {
        return Post::where('user_id', $user->id)->get();
    }
}
```

**Service Provider (registro):**

```php
// app/Providers/RepositoryServiceProvider.php
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PostRepositoryInterface::class,
            PostRepository::class
        );
    }
}
```

**Uso no Service:**

```php
class PostService
{
    public function __construct(
        private PostRepositoryInterface $postRepository
    ) {}

    public function getAll(): Collection
    {
        return $this->postRepository->all();
    }

    public function create(User $user, array $data): Post
    {
        return $this->postRepository->create([
            'user_id' => $user->id,
            ...$data,
        ]);
    }
}
```

---

## Quando usar

**Prós:**
- ✅ Reuso de queries
- ✅ Testabilidade
- ✅ Isolamento do ORM

**Contras:**
- ❌ Mais código (boilerplate)
- ❌ Pode ser overkill para CRUD simples

**Use quando:**
- Queries complexas
- Precisa trocar o ORM
- Muitas queries reutilizadas

---

## Exemplo prático

**Repository com filtro:**

```php
class PostRepository implements PostRepositoryInterface
{
    public function filter(array $filters): Collection
    {
        $query = Post::query();

        if (isset($filters['category'])) {
            $query->where('category_id', $filters['category']);
        }

        if (isset($filters['search'])) {
            $query->where('title', 'like', "%{$filters['search']}%");
        }

        if (isset($filters['published'])) {
            $query->where('published', $filters['published']);
        }

        return $query->with('user')->paginate(20);
    }
}
```

**Mock nos testes:**

```php
// tests/Unit/PostServiceTest.php
class PostServiceTest extends TestCase
{
    public function test_creates_post(): void
    {
        $repository = Mockery::mock(PostRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn(new Post(['id' => 1, 'title' => 'Teste']));

        $service = new PostService($repository);
        $post = $service->create($user, ['title' => 'Teste']);

        $this->assertEquals('Teste', $post->title);
    }
}
```

---

## Na entrevista

> "Repository encapsula o acesso a dados. A Interface define o contrato, a Implementation tem as queries. Bind no Service Provider. Prós: reuso, testabilidade, isolamento do ORM. Você usa no Service Layer. Mock do repository nos testes. Pode ser overkill para CRUD simples. Serve para query complexa e para trocar a fonte de dados."

---

## Exercícios práticos

### Exercício 1: Crie um Repository com filtro

**Enunciado:** Implemente `UserRepository` com o método `filter()` que recebe um array de filtros: `role`, `status`, `search` (por nome/email).

<details>
<summary>Solução</summary>

```php
// app/Contracts/UserRepositoryInterface.php
namespace App\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?User;
    public function filter(array $filters): Collection;
    public function create(array $data): User;
}

// app/Repositories/UserRepository.php
namespace App\Repositories;

use App\Contracts\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Collection;

class UserRepository implements UserRepositoryInterface
{
    public function all(): Collection
    {
        return User::all();
    }

    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function filter(array $filters): Collection
    {
        $query = User::query();

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->get();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }
}

// app/Providers/RepositoryServiceProvider.php
namespace App\Providers;

use App\Contracts\UserRepositoryInterface;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            UserRepositoryInterface::class,
            UserRepository::class
        );
    }
}

// Uso
class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function getFilteredUsers(array $filters): Collection
    {
        return $this->userRepository->filter($filters);
    }
}
```
</details>

### Exercício 2: Escreva um teste com Mock Repository

**Enunciado:** Teste `OrderService::create()` usando mock de `OrderRepository`.

<details>
<summary>Solução</summary>

```php
// tests/Unit/OrderServiceTest.php
namespace Tests\Unit;

use App\Contracts\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Mockery;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_creates_order_successfully(): void
    {
        // Arrange
        $user = User::factory()->make(['id' => 1]);
        $orderData = [
            'total' => 100.50,
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
            ],
        ];

        $expectedOrder = new Order([
            'id' => 1,
            'user_id' => $user->id,
            'total' => 100.50,
        ]);

        // Mock repository
        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->with([
                'user_id' => $user->id,
                'total' => 100.50,
            ])
            ->andReturn($expectedOrder);

        // Act
        $service = new OrderService($repository);
        $order = $service->create($user, $orderData);

        // Assert
        $this->assertEquals(1, $order->id);
        $this->assertEquals(100.50, $order->total);
        $this->assertEquals($user->id, $order->user_id);
    }

    public function test_handles_creation_failure(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $orderData = ['total' => 100];

        $repository = Mockery::mock(OrderRepositoryInterface::class);
        $repository->shouldReceive('create')
            ->once()
            ->andThrow(new \Exception('Erro no banco'));

        $service = new OrderService($repository);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Erro no banco');

        $service->create($user, $orderData);
    }
}
```
</details>

### Exercício 3: Troque a fonte de dados

**Enunciado:** Você tem um `ProductRepository` que fala com o banco. Implemente `ApiProductRepository` que busca os dados de uma API externa.

<details>
<summary>Solução</summary>

```php
// app/Contracts/ProductRepositoryInterface.php
interface ProductRepositoryInterface
{
    public function all(): Collection;
    public function find(int $id): ?Product;
}

// app/Repositories/EloquentProductRepository.php
class EloquentProductRepository implements ProductRepositoryInterface
{
    public function all(): Collection
    {
        return Product::all();
    }

    public function find(int $id): ?Product
    {
        return Product::find($id);
    }
}

// app/Repositories/ApiProductRepository.php
use Illuminate\Support\Facades\Http;

class ApiProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private string $apiUrl
    ) {}

    public function all(): Collection
    {
        $response = Http::get("{$this->apiUrl}/products");

        return collect($response->json('data'))->map(
            fn($item) => new Product($item)
        );
    }

    public function find(int $id): ?Product
    {
        $response = Http::get("{$this->apiUrl}/products/{$id}");

        if ($response->failed()) {
            return null;
        }

        return new Product($response->json('data'));
    }
}

// app/Providers/RepositoryServiceProvider.php
class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Escolhe a implementação pelo config
        $this->app->bind(
            ProductRepositoryInterface::class,
            function ($app) {
                return match (config('products.source')) {
                    'api' => new ApiProductRepository(
                        config('products.api_url')
                    ),
                    'database' => new EloquentProductRepository(),
                    default => new EloquentProductRepository(),
                };
            }
        );
    }
}

// config/products.php
return [
    'source' => env('PRODUCTS_SOURCE', 'database'), // 'database' ou 'api'
    'api_url' => env('PRODUCTS_API_URL', 'https://api.example.com'),
];

// Uso (o código não muda!)
class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {}

    public function getAllProducts(): Collection
    {
        return $this->productRepository->all();
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
