# 3.7 Novidades do PHP 8.x

## Resumo

> **PHP 8.x** — Named Arguments, Union Types, Match, Nullsafe ?->, Property Promotion, readonly, Enums.
>
> **PHP 8.0:** Constructor Property Promotion, Attributes, Match expression.
>
> **PHP 8.1:** Enums, readonly properties, never type, Fibers.

---

## Conteúdo

- [Named Arguments (PHP 8.0)](#named-arguments-php-80)
- [Union Types (PHP 8.0)](#union-types-php-80)
- [Match Expression (PHP 8.0)](#match-expression-php-80)
- [Nullsafe Operator ?-> (PHP 8.0)](#nullsafe-operator---php-80)
- [Constructor Property Promotion (PHP 8.0)](#constructor-property-promotion-php-80)
- [Readonly Properties (PHP 8.1)](#readonly-properties-php-81)
- [Enums (PHP 8.1)](#enums-php-81)
- [Outras novidades do PHP 8.x](#outras-novidades-do-php-8x)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Named Arguments (PHP 8.0)

**O que é:**
Passar argumento pelo nome (não pela ordem).

**Como funciona:**
```php
// Antes do PHP 8.0
function createUser(string $name, string $email, bool $isActive = true, ?string $role = null)
{
    // ...
}

createUser('João', 'joao@email.com', true, 'admin');

// Para pular $isActive, ainda precisa passar true
createUser('João', 'joao@email.com', true, null);

// PHP 8.0: named arguments
createUser(
    name: 'João',
    email: 'joao@email.com',
    role: 'admin'  // Pulamos $isActive
);

// A ordem não importa
createUser(
    email: 'joao@email.com',
    name: 'João',
    isActive: false
);
```

**Quando usar:**
Função com vários parâmetros opcionais. Melhora a leitura.

**Exemplo prático:**
```php
// Laravel Route
Route::get('/users', [UserController::class, 'index'])
    ->middleware('auth')
    ->name('users.index');

// Com named arguments (PHP 8.0+)
response()->json(
    data: $users,
    status: 200,
    headers: ['X-Custom' => 'value']
);

// Eloquent
User::create(
    attributes: [
        'name' => 'João',
        'email' => 'joao@email.com',
    ]
);

// Em testes (bem legível)
$this->assertDatabaseHas(
    table: 'users',
    data: ['email' => 'joao@email.com']
);
```

**Na entrevista:**
> "Named Arguments (PHP 8.0) — você passa pelo nome, a ordem não importa. Melhora a leitura e deixa pular parâmetro opcional. No Laravel eu uso em route, response e teste."

---

## Union Types (PHP 8.0)

**O que é:**
Vários tipos possíveis no parâmetro ou no retorno.

**Como funciona:**
```php
// Antes do PHP 8.0 (via PHPDoc)
/**
 * @param int|string $id
 * @return User|null
 */
function findUser($id)
{
    // ...
}

// PHP 8.0: Union Types
function findUser(int|string $id): User|null
{
    if (is_int($id)) {
        return User::find($id);
    }

    return User::where('email', $id)->first();
}

// Vários tipos
function process(int|float|string $value): int|float
{
    return is_string($value) ? (int) $value : $value;
}

// Com array
function save(array|object $data): void
{
    // ...
}
```

**Quando usar:**
Quando o parâmetro pode ter mais de um tipo.

**Exemplo prático:**
```php
// Laravel Response
function respond(array|Collection $data, int $status = 200): JsonResponse
{
    if ($data instanceof Collection) {
        $data = $data->toArray();
    }

    return response()->json($data, $status);
}

// Repository
class UserRepository
{
    public function find(int|string $id): User|null
    {
        if (is_int($id)) {
            return User::find($id);  // Busca por ID
        }

        return User::where('email', $id)->first();  // Busca por email
    }
}

// Cache
class CacheService
{
    public function remember(
        string $key,
        int|\DateInterval $ttl,
        callable $callback
    ): mixed {
        return Cache::remember($key, $ttl, $callback);
    }
}

// Nunca use com null (use nullable)
// RUIM
function bad(int|null $value): void {}  // ❌

// BOM
function good(?int $value): void {}  // ✅
```

**Na entrevista:**
> "Union Types (PHP 8.0) — vários tipos com |. int|string ou int|float. Não misturo com null (uso ?int). No Laravel, em método flexível: find por ID ou email."

---

## Match Expression (PHP 8.0)

**O que é:**
Switch melhorado: devolve valor e compara com ===.

**Como funciona:**
```php
// Antes do PHP 8.0 (switch)
$message = '';
switch ($status) {
    case 'pending':
        $message = 'Aguardando';
        break;
    case 'paid':
        $message = 'Pago';
        break;
    default:
        $message = 'Desconhecido';
}

// PHP 8.0: match
$message = match($status) {
    'pending' => 'Aguardando',
    'paid' => 'Pago',
    default => 'Desconhecido',
};

// Vários valores
$type = match($code) {
    200, 201, 204 => 'success',
    400, 404 => 'client_error',
    500, 502, 503 => 'server_error',
    default => 'unknown',
};

// Comparação estrita (===)
$result = match($value) {
    0 => 'zero',
    '0' => 'string zero',  // Não casa com 0
    default => 'other',
};
```

**Quando usar:**
No lugar de switch, quando você precisa devolver um valor.

**Exemplo prático:**
```php
// Status HTTP
$message = match($response->status()) {
    200 => 'OK',
    201 => 'Created',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Server Error',
    default => throw new HttpException($response->status()),
};

// Enum (PHP 8.1)
enum Status: string {
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
}

$badge = match($order->status) {
    Status::Pending => 'badge-warning',
    Status::Paid => 'badge-info',
    Status::Shipped => 'badge-success',
};

// Condições
$discount = match(true) {
    $amount > 10000 => 0.15,
    $amount > 5000 => 0.10,
    $amount > 1000 => 0.05,
    default => 0,
};
```

**Na entrevista:**
> "Match (PHP 8.0) — switch melhorado. Devolve valor, compara com ===, não precisa de break. Sem default e sem match, lança erro. Uso no lugar de switch quando preciso devolver valor."

---

## Nullsafe Operator ?-> (PHP 8.0)

**O que é:**
Acesso seguro a propriedade/método (se o objeto é null → devolve null).

**Como funciona:**
```php
// Antes do PHP 8.0
$country = null;
if ($user !== null && $user->address !== null) {
    $country = $user->address->country;
}

// PHP 8.0: nullsafe operator
$country = $user?->address?->country;
// Se $user ou $address = null → devolve null (sem erro)

// Com métodos
$city = $user?->getAddress()?->getCity();

// Em cadeia
$street = $user?->address?->street ?? 'Não informado';
```

**Quando usar:**
Acesso seguro a objeto aninhado.

**Exemplo prático:**
```php
// Relacionamentos Eloquent
$departmentName = $user?->department?->name ?? 'Sem departamento';

$managerEmail = $user?->department?->manager?->email;

// API Response
$data = json_decode($response->body());
$userId = $data?->user?->id;

// Template Blade
{{ $user?->profile?->avatar ?? '/default-avatar.png' }}

// Service
public function process(?User $user): void
{
    $this->logger->info('User email', [
        'email' => $user?->email ?? 'N/A',
    ]);
}
```

**Na entrevista:**
> "Nullsafe ?-> (PHP 8.0) — acesso seguro. Se o objeto é null, devolve null sem erro. $user?->address?->city. Uso em relacionamento Eloquent e response de API."

---

## Constructor Property Promotion (PHP 8.0)

**O que é:**
Sintaxe curta para declarar propriedade no construtor.

**Como funciona:**
```php
// Antes do PHP 8.0
class User
{
    private string $name;
    private string $email;
    private int $age;

    public function __construct(string $name, string $email, int $age)
    {
        $this->name = $name;
        $this->email = $email;
        $this->age = $age;
    }
}

// PHP 8.0: Property Promotion
class User
{
    public function __construct(
        private string $name,
        private string $email,
        private int $age,
    ) {}  // Mais curto!
}

// Estilo misto (pode misturar)
class User
{
    private string $createdAt;

    public function __construct(
        private string $name,
        private string $email,
    ) {
        $this->createdAt = date('Y-m-d H:i:s');
    }
}
```

**Quando usar:**
**Sempre** em construtor simples (DTO, Value Object, Service).

**Exemplo prático:**
```php
// DTO
readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}

// Value Object
class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}

    public function add(Money $other): Money
    {
        return new Money($this->amount + $other->amount, $this->currency);
    }
}

// Service com DI
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private PaymentGateway $gateway,
        private LoggerInterface $logger,
    ) {}

    public function create(array $data): Order
    {
        $this->logger->info('Criando pedido');
        return $this->repository->create($data);
    }
}
```

**Na entrevista:**
> "Constructor Property Promotion (PHP 8.0) — sintaxe curta. Você declara a propriedade no parâmetro do construtor: private string $name. Uso em DTO, Value Object e Service com DI."

---

## Readonly Properties (PHP 8.1)

**O que é:**
Propriedade que só recebe valor uma vez.

**Como funciona:**
```php
class User
{
    public function __construct(
        public readonly string $name,
        public readonly int $id,
    ) {}
}

$user = new User('João', 1);
echo $user->name;  // "João"
$user->name = 'Pedro';  // ❌ Error: Cannot modify readonly property

// readonly class (PHP 8.2)
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}
}
// Todas as propriedades ficam readonly automaticamente
```

**Quando usar:**
Dado imutável (DTO, Value Object, Event).

**Exemplo prático:**
```php
// Event
readonly class OrderCreated
{
    public function __construct(
        public Order $order,
        public \DateTimeImmutable $createdAt,
    ) {}
}

// DTO
readonly class RegisterUserRequest
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}

// Value Object
readonly class Email
{
    public string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }

        $this->value = strtolower($email);
    }
}
```

**Na entrevista:**
> "readonly (PHP 8.1) — a propriedade só recebe valor uma vez. readonly class (PHP 8.2) deixa todas readonly. Uso em DTO, Value Object e Event. Garante imutabilidade."

---

## Enums (PHP 8.1)

**O que é:**
Conjunto fechado de valores.

**Como funciona:**
```php
// PHP 8.1: Enum
enum Status
{
    case Pending;
    case Paid;
    case Shipped;
    case Delivered;
}

$status = Status::Pending;

// Com backing value (string/int)
enum Status: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}

$status = Status::Paid;
echo $status->value;  // "paid"
echo $status->name;   // "Paid"

// Comparação
if ($order->status === Status::Paid) {
    // Pago
}

// match com Enum
$badge = match($order->status) {
    Status::Pending => 'badge-warning',
    Status::Paid => 'badge-info',
    Status::Shipped => 'badge-primary',
    Status::Delivered => 'badge-success',
};

// Métodos no Enum
enum Status: string
{
    case Pending = 'pending';
    case Paid = 'paid';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Aguardando',
            self::Paid => 'Pago',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Paid => 'green',
        };
    }
}

echo Status::Paid->label();  // "Pago"
echo Status::Paid->color();  // "green"
```

**Quando usar:**
Status, tipo, modo — no lugar de constante de classe.

**Exemplo prático:**
```php
// Laravel Model
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Aguardando',
            self::Paid => 'Pago',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
        };
    }
}

class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,  // Cast automático
    ];
}

$order = Order::find(1);
if ($order->status === OrderStatus::Paid) {
    // Pago
}

echo $order->status->label();  // "Pago"

// Blade
<span class="badge-{{ $order->status->value }}">
    {{ $order->status->label() }}
</span>

// Migration
$table->enum('status', ['pending', 'paid', 'shipped', 'delivered', 'cancelled']);

// Validation
'status' => ['required', Rule::enum(OrderStatus::class)],
```

**Na entrevista:**
> "Enum (PHP 8.1) — conjunto fechado de valores. Backed Enum com string/int. Dá para ter método no Enum. No Laravel uso para status e tipo. Cast no model, Rule::enum na validação."

---

## Outras novidades do PHP 8.x

**PHP 8.0:**
- **throw em expressão**: `$value = $data['key'] ?? throw new Exception();`
- **str_contains()**, **str_starts_with()**, **str_ends_with()**
- **fdiv()** — divisão de ponto flutuante (não dá erro ao dividir por 0)
- **get_debug_type()** — melhor que gettype()

**PHP 8.1:**
- **Intersection Types**: `function save(Countable&Iterator $collection)`
- **never type**: `function redirect(): never { exit; }`
- **constantes final de classe**
- **Fibers** — threads leves (para async)

**PHP 8.2:**
- **readonly class** — todas as propriedades readonly
- **Disjunctive Normal Form (DNF)** types: `(A&B)|C`
- **true type** — só true (não bool)

**Exemplo prático:**
```php
// throw em expressão
$user = User::find($id) ?? throw new NotFoundException("Usuário {$id} não encontrado");

// Funções str_*
if (str_contains($email, '@gmail.com')) {
    // Gmail
}

if (str_starts_with($url, 'https://')) {
    // Conexão segura
}

// never type (PHP 8.1)
function redirect(string $url): never
{
    header("Location: {$url}");
    exit;
}

function fail(string $message): never
{
    throw new Exception($message);
}

// readonly class (PHP 8.2)
readonly class UserDTO
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

**Na entrevista:**
> "PHP 8.0: named arguments, union types, match, ?->, property promotion. PHP 8.1: readonly, enum, never. PHP 8.2: readonly class, DNF types. No Laravel eu uso tudo isso no dia a dia."

---

## Recapitulando

**PHP 8.0:**
- **Named Arguments** — passa pelo nome
- **Union Types** — int|string|null
- **Match** — switch melhorado
- **Nullsafe ?->** — acesso seguro
- **Property Promotion** — sintaxe curta do construtor
- **Attributes** — metadados (#[Attr])

**PHP 8.1:**
- **readonly** — propriedades imutáveis
- **Enum** — conjunto fechado de valores
- **never** — nunca devolve
- **Intersection Types** — A&B
- **Fibers** — async

**PHP 8.2:**
- **readonly class** — todas as propriedades readonly
- **DNF types** — (A&B)|C
- **true type**

**Importante na entrevista:**
- Property Promotion + readonly — padrão para DTO
- Enum no lugar de constante de classe
- Match no lugar de switch
- ?-> para acesso seguro
- Named Arguments para leitura
- Laravel usa tudo isso do PHP 8.x

---

## Exercícios práticos

### Exercício 1: Crie um DTO com recursos do PHP 8

**Enunciado:** Crie um DTO readonly para cadastro de usuário, com Property Promotion, Union Types e Named Arguments.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\DTO;

readonly class RegisterUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public string|null $phone = null,
        public int|null $age = null,
        public array $roles = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->input('name'),
            email: $request->input('email'),
            password: $request->input('password'),
            phone: $request->input('phone'),
            age: $request->integer('age'),
            roles: $request->input('roles', []),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            phone: $data['phone'] ?? null,
            age: $data['age'] ?? null,
            roles: $data['roles'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'phone' => $this->phone,
            'age' => $this->age,
            'roles' => $this->roles,
        ];
    }
}

// Uso
class UserService
{
    public function register(RegisterUserDTO $dto): User
    {
        $user = User::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => Hash::make($dto->password),
            'phone' => $dto->phone,
            'age' => $dto->age,
        ]);

        if (!empty($dto->roles)) {
            $user->roles()->attach($dto->roles);
        }

        return $user;
    }
}

// Controller
public function register(Request $request)
{
    $dto = RegisterUserDTO::fromRequest($request);

    $user = $this->userService->register($dto);

    return response()->json($user, 201);
}

// Não dá para alterar propriedade readonly
$dto = new RegisterUserDTO('João', 'joao@email.com', 'password');
$dto->name = 'Ana';  // ❌ Error: Cannot modify readonly property
```
</details>

### Exercício 2: Implemente uma State Machine com Enum

**Enunciado:** Crie o controle de status do pedido com Enum, métodos e match.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match($this) {
            self::Pending => 'Aguardando',
            self::Confirmed => 'Confirmado',
            self::Processing => 'Em processamento',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregue',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Pending => 'yellow',
            self::Confirmed, self::Processing => 'blue',
            self::Shipped => 'purple',
            self::Delivered => 'green',
            self::Cancelled, self::Refunded => 'red',
        };
    }

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match($this) {
            self::Pending => in_array($newStatus, [
                self::Confirmed,
                self::Cancelled,
            ]),
            self::Confirmed => in_array($newStatus, [
                self::Processing,
                self::Cancelled,
            ]),
            self::Processing => in_array($newStatus, [
                self::Shipped,
                self::Cancelled,
            ]),
            self::Shipped => in_array($newStatus, [
                self::Delivered,
            ]),
            self::Delivered => in_array($newStatus, [
                self::Refunded,
            ]),
            self::Cancelled, self::Refunded => false,
        };
    }

    public function allowedTransitions(): array
    {
        return match($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function isFinal(): bool
    {
        return match($this) {
            self::Delivered, self::Cancelled, self::Refunded => true,
            default => false,
        };
    }

    public function requiresPayment(): bool
    {
        return match($this) {
            self::Pending, self::Confirmed => true,
            default => false,
        };
    }
}

// Model
class Order extends Model
{
    protected $casts = [
        'status' => OrderStatus::class,
    ];

    public function transitionTo(OrderStatus $newStatus): void
    {
        if (!$this->status->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Não dá para ir de {$this->status->value} para {$newStatus->value}"
            );
        }

        $oldStatus = $this->status;

        $this->status = $newStatus;
        $this->save();

        event(new OrderStatusChanged($this, $oldStatus, $newStatus));
    }
}

// Service
class OrderService
{
    public function confirm(Order $order): void
    {
        $order->transitionTo(OrderStatus::Confirmed);

        // Enviar notificação
        Notification::send($order->user, new OrderConfirmed($order));
    }

    public function ship(Order $order, string $trackingNumber): void
    {
        $order->transitionTo(OrderStatus::Shipped);
        $order->update(['tracking_number' => $trackingNumber]);

        Notification::send($order->user, new OrderShipped($order));
    }

    public function cancel(Order $order, string $reason): void
    {
        $order->transitionTo(OrderStatus::Cancelled);
        $order->update(['cancellation_reason' => $reason]);

        // Reembolsa se já pagou
        if ($order->isPaid()) {
            $this->refundPayment($order);
        }
    }
}

// Controller
public function updateStatus(Request $request, Order $order)
{
    $newStatus = OrderStatus::from($request->input('status'));

    if (!$order->status->canTransitionTo($newStatus)) {
        return response()->json([
            'error' => 'Transição de status inválida',
            'current' => $order->status->value,
            'requested' => $newStatus->value,
            'allowed' => array_map(
                fn($s) => $s->value,
                $order->status->allowedTransitions()
            ),
        ], 422);
    }

    $order->transitionTo($newStatus);

    return response()->json([
        'message' => 'Status atualizado com sucesso',
        'order' => $order,
    ]);
}

// Blade
<span class="badge badge-{{ $order->status->color() }}">
    {{ $order->status->label() }}
</span>

@if(!$order->status->isFinal())
    <div class="actions">
        @foreach($order->status->allowedTransitions() as $transition)
            <button wire:click="transitionTo('{{ $transition->value }}')">
                {{ $transition->label() }}
            </button>
        @endforeach
    </div>
@endif
```
</details>

### Exercício 3: Crie um Query Builder flexível com Named Arguments e Union Types

**Enunciado:** Implemente o padrão Builder com Named Arguments e Union Types para parâmetros flexíveis.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\QueryBuilder;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FluentQueryBuilder
{
    public function __construct(
        private Builder $query,
    ) {}

    public function filter(
        string|array|null $search = null,
        string|array|null $status = null,
        int|string|null $userId = null,
        \DateTimeInterface|string|null $dateFrom = null,
        \DateTimeInterface|string|null $dateTo = null,
        array $tags = [],
        bool $onlyActive = false,
    ): self {
        // Search
        if ($search !== null) {
            $searchTerms = is_array($search) ? $search : [$search];

            $this->query->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $term) {
                    $q->orWhere('title', 'like', "%{$term}%")
                      ->orWhere('description', 'like', "%{$term}%");
                }
            });
        }

        // Status
        if ($status !== null) {
            $statuses = is_array($status) ? $status : [$status];
            $this->query->whereIn('status', $statuses);
        }

        // User
        if ($userId !== null) {
            $this->query->where('user_id', $userId);
        }

        // Date range
        if ($dateFrom !== null) {
            $date = $dateFrom instanceof \DateTimeInterface
                ? $dateFrom->format('Y-m-d')
                : $dateFrom;

            $this->query->where('created_at', '>=', $date);
        }

        if ($dateTo !== null) {
            $date = $dateTo instanceof \DateTimeInterface
                ? $dateTo->format('Y-m-d')
                : $dateTo;

            $this->query->where('created_at', '<=', $date);
        }

        // Tags
        if (!empty($tags)) {
            $this->query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('tags.id', $tags);
            });
        }

        // Only active
        if ($onlyActive) {
            $this->query->where('is_active', true);
        }

        return $this;
    }

    public function sort(
        string|array $orderBy = 'created_at',
        string $direction = 'desc',
    ): self {
        $columns = is_array($orderBy) ? $orderBy : [$orderBy];

        foreach ($columns as $column) {
            $this->query->orderBy($column, $direction);
        }

        return $this;
    }

    public function paginate(
        int $perPage = 15,
        int|null $page = null,
    ): \Illuminate\Pagination\LengthAwarePaginator {
        return $this->query->paginate(
            perPage: $perPage,
            page: $page,
        );
    }

    public function get(): Collection
    {
        return $this->query->get();
    }

    public function first(): mixed
    {
        return $this->query->first();
    }
}

// Service
class PostService
{
    public function search(array $params): \Illuminate\Pagination\LengthAwarePaginator
    {
        $builder = new FluentQueryBuilder(Post::query());

        return $builder
            ->filter(
                search: $params['search'] ?? null,
                status: $params['status'] ?? null,
                userId: $params['user_id'] ?? null,
                dateFrom: $params['date_from'] ?? null,
                dateTo: $params['date_to'] ?? null,
                tags: $params['tags'] ?? [],
                onlyActive: $params['only_active'] ?? false,
            )
            ->sort(
                orderBy: $params['order_by'] ?? 'created_at',
                direction: $params['direction'] ?? 'desc',
            )
            ->paginate(
                perPage: $params['per_page'] ?? 15,
                page: $params['page'] ?? null,
            );
    }
}

// Controller
public function index(Request $request)
{
    $posts = $this->postService->search([
        'search' => $request->input('q'),
        'status' => $request->input('status'),
        'user_id' => $request->integer('user_id'),
        'date_from' => $request->input('date_from'),
        'date_to' => $request->input('date_to'),
        'tags' => $request->input('tags', []),
        'only_active' => $request->boolean('only_active'),
        'order_by' => $request->input('order_by', 'created_at'),
        'direction' => $request->input('direction', 'desc'),
        'per_page' => $request->integer('per_page', 15),
    ]);

    return response()->json($posts);
}

// Ou com Named Arguments direto
$posts = (new FluentQueryBuilder(Post::query()))
    ->filter(
        search: 'Laravel',
        status: ['published', 'draft'],
        onlyActive: true,
    )
    ->sort(orderBy: 'created_at', direction: 'desc')
    ->paginate(perPage: 20);
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
