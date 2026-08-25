# 10.6 CQRS (Command Query Responsibility Segregation)

## Resumo

> **CQRS** — separação das operações de leitura (Query) e escrita (Command).
>
> **Princípio:** Command altera (não retorna), Query retorna (não altera).
>
> **Importante:** Modelos diferentes: Write Model (Domain Entity), Read Model (DTO). Read repository otimizado para leitura.

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
CQRS — separação das operações de leitura (Query) e escrita (Command). Modelos diferentes para leitura e escrita.

**Princípio:**
- **Command** — altera o estado, não retorna dado
- **Query** — retorna dado, não altera o estado

---

## Como funciona

**Command (escrita):**

```php
// app/Application/Commands/CreateOrderCommand.php
class CreateOrderCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly array $items
    ) {}
}

// Handler
class CreateOrderHandler
{
    public function __construct(
        private OrderRepository $orders
    ) {}

    public function handle(CreateOrderCommand $command): void
    {
        $order = new Order(
            OrderId::generate(),
            UserId::fromInt($command->userId)
        );

        foreach ($command->items as $item) {
            $order->addItem($item['product_id'], $item['quantity']);
        }

        $this->orders->save($order);

        // Não retorna dado (void)
    }
}
```

**Query (leitura):**

```php
// app/Application/Queries/GetOrderQuery.php
class GetOrderQuery
{
    public function __construct(
        public readonly int $orderId
    ) {}
}

// Handler
class GetOrderHandler
{
    public function __construct(
        private OrderReadRepository $orders
    ) {}

    public function handle(GetOrderQuery $query): OrderDTO
    {
        return $this->orders->findById($query->orderId);
        // Retorna dado, não altera
    }
}
```

**Controller:**

```php
class OrderController extends Controller
{
    public function store(
        Request $request,
        CreateOrderHandler $handler
    ) {
        $command = new CreateOrderCommand(
            userId: $request->user()->id,
            items: $request->input('items')
        );

        $handler->handle($command);

        return response()->noContent();  // 204
    }

    public function show(
        int $id,
        GetOrderHandler $handler
    ) {
        $query = new GetOrderQuery($id);
        $order = $handler->handle($query);

        return response()->json($order);
    }
}
```

---

## Quando usar

**CQRS para:**
- Lógica complexa de leitura/escrita
- Performance diferente para read/write
- Event Sourcing

**NÃO para:**
- CRUD simples
- Projeto pequeno

---

## Exemplo prático

**Modelos diferentes para leitura e escrita:**

```php
// Write Model (Domain)
class Order
{
    private OrderId $id;
    private UserId $userId;
    private Collection $items;

    public function addItem(Product $product, int $quantity): void
    {
        $this->items->push(new OrderItem($product, $quantity));
    }
}

// Read Model (DTO)
class OrderDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $userName,
        public readonly float $total,
        public readonly array $items
    ) {}
}

// Read Repository (otimizado para leitura)
class OrderReadRepository
{
    public function findById(int $id): OrderDTO
    {
        $data = DB::table('orders')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->leftJoin('order_items', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.id', $id)
            ->select([
                'orders.id',
                'users.name as user_name',
                'orders.total',
            ])
            ->first();

        $items = DB::table('order_items')
            ->where('order_id', $id)
            ->get();

        return new OrderDTO(
            id: $data->id,
            userName: $data->user_name,
            total: $data->total,
            items: $items->toArray()
        );
    }
}
```

**Bus para commands:**

```php
// app/Bus/CommandBus.php
class CommandBus
{
    public function dispatch(object $command): void
    {
        $handler = $this->resolveHandler($command);
        $handler->handle($command);
    }

    private function resolveHandler(object $command): object
    {
        $handlerClass = get_class($command) . 'Handler';
        return app($handlerClass);
    }
}

// Uso
$this->commandBus->dispatch(
    new CreateOrderCommand($userId, $items)
);
```

---

## Na entrevista

> "CQRS separa leitura e escrita. Command altera o estado, não retorna dado. Query retorna dado, não altera. Modelos diferentes: Write Model (Domain Entity), Read Model (DTO). Read repository otimizado para leitura (JOIN, desnormalização). CommandBus para dispatch das commands. Usa junto com Event Sourcing. Serve para lógica complexa, não para CRUD simples."

---

## Exercícios práticos

### Exercício 1: Implemente Command e Query para User

Crie `CreateUserCommand` e `GetUserQuery` com os handlers.

<details>
<summary>Solução</summary>

```php
// app/Application/Commands/CreateUserCommand.php
namespace App\Application\Commands;

class CreateUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {}
}

// app/Application/Commands/CreateUserHandler.php
namespace App\Application\Commands;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use Illuminate\Support\Facades\Hash;

class CreateUserHandler
{
    public function __construct(
        private UserRepository $users
    ) {}

    public function handle(CreateUserCommand $command): void
    {
        $user = new User(
            name: $command->name,
            email: $command->email,
            password: Hash::make($command->password)
        );

        $this->users->save($user);

        // Não retorna dado (void)
    }
}

// app/Application/Queries/GetUserQuery.php
namespace App\Application\Queries;

class GetUserQuery
{
    public function __construct(
        public readonly int $userId
    ) {}
}

// app/Application/Queries/GetUserHandler.php
namespace App\Application\Queries;

use App\Application\DTOs\UserDTO;
use Illuminate\Support\Facades\DB;

class GetUserHandler
{
    public function handle(GetUserQuery $query): UserDTO
    {
        $data = DB::table('users')
            ->select('id', 'name', 'email', 'created_at')
            ->where('id', $query->userId)
            ->first();

        if (!$data) {
            throw new \Exception('Usuário não encontrado');
        }

        return new UserDTO(
            id: $data->id,
            name: $data->name,
            email: $data->email,
            createdAt: $data->created_at
        );
    }
}

// app/Application/DTOs/UserDTO.php
namespace App\Application\DTOs;

class UserDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $createdAt
    ) {}
}

// Controller
class UserController extends Controller
{
    public function store(
        Request $request,
        CreateUserHandler $commandHandler
    ) {
        $command = new CreateUserCommand(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password')
        );

        $commandHandler->handle($command);

        return response()->noContent(); // 204
    }

    public function show(int $id, GetUserHandler $queryHandler)
    {
        $query = new GetUserQuery($id);
        $user = $queryHandler->handle($query);

        return response()->json($user);
    }
}
```
</details>

### Exercício 2: Crie um CommandBus

Implemente um CommandBus simples para rotear commands aos handlers automaticamente.

<details>
<summary>Solução</summary>

```php
// app/Bus/CommandBus.php
namespace App\Bus;

use Illuminate\Contracts\Container\Container;

class CommandBus
{
    public function __construct(
        private Container $container
    ) {}

    public function dispatch(object $command): void
    {
        $handler = $this->resolveHandler($command);
        $handler->handle($command);
    }

    private function resolveHandler(object $command): object
    {
        $handlerClass = $this->getHandlerClass($command);

        if (!class_exists($handlerClass)) {
            throw new \RuntimeException(
                "Handler não encontrado para " . get_class($command)
            );
        }

        return $this->container->make($handlerClass);
    }

    private function getHandlerClass(object $command): string
    {
        // CreateUserCommand -> CreateUserHandler
        return str_replace('Command', 'Handler', get_class($command));
    }
}

// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(CommandBus::class);
}

// Uso
class UserController extends Controller
{
    public function __construct(
        private CommandBus $commandBus
    ) {}

    public function store(Request $request)
    {
        $this->commandBus->dispatch(
            new CreateUserCommand(
                name: $request->input('name'),
                email: $request->input('email'),
                password: $request->input('password')
            )
        );

        return response()->noContent();
    }

    public function update(Request $request, int $id)
    {
        $this->commandBus->dispatch(
            new UpdateUserCommand(
                userId: $id,
                name: $request->input('name'),
                email: $request->input('email')
            )
        );

        return response()->noContent();
    }
}
```
</details>

### Exercício 3: Read Model com desnormalização

Crie um Read Model otimizado para exibir pedidos com todas as informações.

<details>
<summary>Solução</summary>

```php
// Tabela desnormalizada para leitura
// Migration: create_order_read_models_table
Schema::create('order_read_models', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->unique();
    $table->string('order_number');
    $table->foreignId('user_id');
    $table->string('user_name');
    $table->string('user_email');
    $table->decimal('total', 10, 2);
    $table->string('status');
    $table->integer('items_count');
    $table->json('items'); // Desnormalização
    $table->timestamp('created_at');
    $table->index(['user_id', 'status']);
});

// app/Application/Queries/GetOrdersQuery.php
class GetOrdersQuery
{
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $status = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20
    ) {}
}

// app/Application/Queries/GetOrdersHandler.php
use Illuminate\Support\Facades\DB;

class GetOrdersHandler
{
    public function handle(GetOrdersQuery $query): array
    {
        $queryBuilder = DB::table('order_read_models');

        if ($query->userId) {
            $queryBuilder->where('user_id', $query->userId);
        }

        if ($query->status) {
            $queryBuilder->where('status', $query->status);
        }

        $orders = $queryBuilder
            ->orderBy('created_at', 'desc')
            ->paginate($query->perPage, ['*'], 'page', $query->page);

        return [
            'data' => $orders->items(),
            'total' => $orders->total(),
            'page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
        ];
    }
}

// Atualiza o Read Model quando o Order é criado
// app/Listeners/UpdateOrderReadModel.php
class UpdateOrderReadModel
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        DB::table('order_read_models')->insert([
            'order_id' => $order->id,
            'order_number' => $order->number,
            'user_id' => $order->user_id,
            'user_name' => $order->user->name,
            'user_email' => $order->user->email,
            'total' => $order->total,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'items' => json_encode($order->items->map(fn($item) => [
                'product_name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ])),
            'created_at' => $order->created_at,
        ]);
    }
}

// Controller
class OrderController extends Controller
{
    public function index(Request $request, GetOrdersHandler $handler)
    {
        $query = new GetOrdersQuery(
            userId: $request->query('user_id'),
            status: $request->query('status'),
            page: $request->query('page', 1)
        );

        return response()->json($handler->handle($query));
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
