# 10.1 KISS, DRY, YAGNI

## Resumo

> **KISS** — simplicidade acima de complexidade. Evite overengineering.
>
> **DRY** — cada conhecimento em um lugar só. Sem duplicação (mas não por semelhança acidental).
>
> **YAGNI** — não adicione feature antes de precisar. Evite o "e se um dia precisar".

---

## Conteúdo

- [KISS (Keep It Simple, Stupid)](#kiss-keep-it-simple-stupid)
- [Exemplos de KISS](#exemplos-de-kiss)
- [DRY (Don't Repeat Yourself)](#dry-dont-repeat-yourself)
- [Exemplos de DRY](#exemplos-de-dry)
- [YAGNI (You Aren't Gonna Need It)](#yagni-you-arent-gonna-need-it)
- [Exemplos de YAGNI](#exemplos-de-yagni)
- [Quando quebrar o princípio](#quando-quebrar-o-princípio)
- [Combinando os princípios](#combinando-os-princípios)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## KISS (Keep It Simple, Stupid)

**Princípio:**
Simplicidade acima de complexidade. O código tem que ser fácil de ler.

**Por quê:**
- Mais fácil de ler
- Mais fácil de testar
- Mais fácil de manter
- Menos bugs

---

### Exemplos de KISS

**❌ Complicado:**

```php
class OrderProcessor
{
    public function process($order)
    {
        $strategy = $this->strategyFactory->create(
            $this->configResolver->resolve($order->type)
        );

        $pipeline = new Pipeline($this->container);
        $result = $pipeline
            ->send($order)
            ->through([
                ValidateOrderMiddleware::class,
                CalculateTotalsMiddleware::class,
                ApplyDiscountsMiddleware::class,
            ])
            ->then(function ($order) use ($strategy) {
                return $strategy->execute($order);
            });

        return $result;
    }
}

// Overengineering para uma tarefa simples!
```

**✅ Simples:**

```php
class OrderProcessor
{
    public function process(Order $order)
    {
        // 1. Validar
        if ($order->items->isEmpty()) {
            throw new InvalidOrderException('Pedido está vazio');
        }

        // 2. Calcular o total
        $order->total = $order->items->sum(fn($item) => $item->price * $item->quantity);

        // 3. Aplicar desconto
        if ($order->discount_code) {
            $order->total -= $this->calculateDiscount($order);
        }

        // 4. Salvar
        $order->save();

        return $order;
    }
}

// Dá para entender de primeira!
```

---

**❌ Complicado:**

```php
// Abstract Factory para lógica simples
interface ShapeFactory
{
    public function createShape(): Shape;
}

class CircleFactory implements ShapeFactory
{
    public function createShape(): Shape
    {
        return new Circle();
    }
}

class SquareFactory implements ShapeFactory
{
    public function createShape(): Shape
    {
        return new Square();
    }
}

$factory = $type === 'circle' ? new CircleFactory() : new SquareFactory();
$shape = $factory->createShape();
```

**✅ Simples:**

```php
// Direto
$shape = $type === 'circle' ? new Circle() : new Square();

// Ou, se precisar de flexibilidade
$shapes = [
    'circle' => Circle::class,
    'square' => Square::class,
];

$shape = new $shapes[$type]();
```

---

**Quando o KISS quebra:**
- Otimização prematura
- Overengineering (pattern por pattern)
- "E se precisar no futuro"

**Regra:**
> "Escreva o código como se quem for manter fosse um psicopata que sabe onde você mora"

---

## DRY (Don't Repeat Yourself)

**Princípio:**
Cada pedaço de conhecimento tem uma representação só no sistema. Uma, e sem ambiguidade.

**Por quê:**
- Mudança em um lugar só
- Menos duplicação
- Refatorar fica mais fácil

---

### Exemplos de DRY

**❌ WET (Write Everything Twice):**

```php
class UserController
{
    public function store(Request $request)
    {
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        Mail::to($user->email)->send(new WelcomeEmail($user));
        Log::info("Usuário {$user->id} registrado");

        return response()->json($user);
    }

    public function register(Request $request)
    {
        // Duplicação!
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        Mail::to($user->email)->send(new WelcomeEmail($user));
        Log::info("Usuário {$user->id} registrado");

        return redirect('/dashboard');
    }
}
```

**✅ DRY:**

```php
class UserController
{
    public function store(Request $request)
    {
        $user = $this->createUser($request);
        return response()->json($user);
    }

    public function register(Request $request)
    {
        $user = $this->createUser($request);
        return redirect('/dashboard');
    }

    private function createUser(Request $request): User
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Mail::to($user->email)->send(new WelcomeEmail($user));
        Log::info("Usuário {$user->id} registrado");

        return $user;
    }
}
```

---

**❌ Validação duplicada:**

```php
class OrderController
{
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // ...
    }
}

class ApiOrderController
{
    public function store(Request $request)
    {
        // Validação duplicada!
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // ...
    }
}
```

**✅ DRY (Form Request):**

```php
class StoreOrderRequest extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}

class OrderController
{
    public function store(StoreOrderRequest $request)
    {
        // Validação automática
    }
}

class ApiOrderController
{
    public function store(StoreOrderRequest $request)
    {
        // A mesma validação
    }
}
```

---

**Mas! Nem toda repetição quebra o DRY:**

```php
// ❌ DRY ruim (false abstraction)
class StringHelper
{
    public static function getUserFullName(User $user)
    {
        return $user->first_name . ' ' . $user->last_name;
    }

    public static function getProductFullName(Product $product)
    {
        return $product->brand . ' ' . $product->model;
    }
}

// Semelhança acidental, regras de negócio diferentes!
```

**✅ Melhor:**

```php
class User
{
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

class Product
{
    public function getFullNameAttribute()
    {
        return "{$this->brand} {$this->model}";
    }
}

// Contextos diferentes, métodos diferentes
```

---

## YAGNI (You Aren't Gonna Need It)

**Princípio:**
Não adicione feature antes de precisar.

**Por quê:**
- Menos código
- Desenvolvimento mais rápido
- Código mais simples

---

### Exemplos de YAGNI

**❌ Quebra do YAGNI:**

```php
class User extends Model
{
    // "E se um dia precisar"
    protected $guarded = [];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // 20 scopes "por precaução"...
}

// Só o scopeActive é usado!
```

**✅ YAGNI:**

```php
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Os outros scopes entram quando precisarem
}
```

---

**❌ Quebra do YAGNI:**

```php
// "Vamos deixar flexível para adicionar tipos novos fácil"
interface PaymentGateway
{
    public function charge($amount);
    public function refund($transactionId);
    public function subscribe($plan);
    public function cancelSubscription($subscriptionId);
    public function updateCard($token);
    public function getTransactionHistory();
    // ... 10 métodos
}

class StripeGateway implements PaymentGateway
{
    public function charge($amount) { /* ... */ }
    public function refund($transactionId) { throw new NotImplementedException(); }
    public function subscribe($plan) { throw new NotImplementedException(); }
    // Só o charge é usado!
}
```

**✅ YAGNI:**

```php
// Simples
class StripeGateway
{
    public function charge($amount)
    {
        // Implementação simples
    }
}

// Se precisar de outros métodos — a gente adiciona
// Se precisar de outros gateways — a gente adiciona a interface
```

---

**❌ Quebra do YAGNI:**

```php
// Configurabilidade "pro futuro"
class OrderProcessor
{
    public function process(
        Order $order,
        ?string $strategy = null,
        ?array $middlewares = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?EventDispatcher $dispatcher = null
    ) {
        // Complexidade que ninguém pediu
    }
}
```

**✅ YAGNI:**

```php
class OrderProcessor
{
    public function process(Order $order)
    {
        // Implementação simples
        // Se precisar de flexibilidade — a gente refatora
    }
}
```

---

## Quando quebrar o princípio

**KISS pode quebrar quando:**
- Performance é crítica (otimização deixa o código mais difícil)
- A lógica de domínio já é complexa por natureza

**DRY pode quebrar quando:**
- Semelhança acidental (contextos diferentes)
- Decoupling importa mais (microsserviços)

**YAGNI pode quebrar quando:**
- Refatorar custa caro (código legado)
- O contrato da API precisa existir já (bibliotecas)

---

## Combinando os princípios

**Código bom:**

```php
// KISS: simples e claro
// DRY: sem duplicação
// YAGNI: só a feature que precisa

class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create([
            'user_id' => $data['user_id'],
            'total' => $this->calculateTotal($data['items']),
        ]);

        foreach ($data['items'] as $item) {
            $order->items()->create($item);
        }

        event(new OrderCreated($order));

        return $order;
    }

    private function calculateTotal(array $items): float
    {
        return array_sum(array_map(
            fn($item) => $item['price'] * $item['quantity'],
            $items
        ));
    }
}
```

---

## Na entrevista

> "KISS — simplicidade acima de complexidade, evito overengineering. DRY — cada conhecimento em um lugar só, sem duplicação, mas não por semelhança acidental. YAGNI — não adiciono feature antes de precisar, evito o 'e se um dia precisar'. Dá para quebrar: KISS na otimização, DRY em contextos diferentes, YAGNI quando o refactor é caro. No Laravel: Form Request para DRY na validação, service simples em vez de pattern para KISS, scope só quando precisa para YAGNI."

---

## Exercícios práticos

### Exercício 1: Simplifique o código overengineered

**Enunciado:** Simplifique este código seguindo o KISS.

```php
// Overengineered
interface ShapeFactoryInterface
{
    public function createShape(): ShapeInterface;
}

class CircleFactory implements ShapeFactoryInterface
{
    public function createShape(): ShapeInterface
    {
        return new Circle();
    }
}

class ShapeManager
{
    private array $factories = [];

    public function registerFactory(string $type, ShapeFactoryInterface $factory): void
    {
        $this->factories[$type] = $factory;
    }

    public function createShape(string $type): ShapeInterface
    {
        if (!isset($this->factories[$type])) {
            throw new InvalidArgumentException("Tipo de forma desconhecido: $type");
        }

        return $this->factories[$type]->createShape();
    }
}

$manager = new ShapeManager();
$manager->registerFactory('circle', new CircleFactory());
$shape = $manager->createShape('circle');
```

<details>
<summary>Solução</summary>

```php
// ✅ KISS: simples e claro

// Opção 1: direto (se não precisa de flexibilidade)
$shape = match($type) {
    'circle' => new Circle(),
    'square' => new Square(),
    'triangle' => new Triangle(),
    default => throw new InvalidArgumentException("Forma desconhecida: $type"),
};

// Opção 2: factory simples (se precisa de um pouco de flexibilidade)
class ShapeFactory
{
    private const SHAPES = [
        'circle' => Circle::class,
        'square' => Square::class,
        'triangle' => Triangle::class,
    ];

    public static function create(string $type): Shape
    {
        $class = self::SHAPES[$type] ?? throw new InvalidArgumentException("Forma desconhecida: $type");

        return new $class();
    }
}

$shape = ShapeFactory::create('circle');

// Quando usar a versão overengineered:
// - Precisa registrar tipos em runtime
// - Sistema de plugins
// - Service Container
//
// Para criar objeto simples — KISS!
```
</details>

### Exercício 2: Corrija a duplicação (DRY)

**Enunciado:** Encontre e corrija a duplicação.

```php
class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        Mail::to($user->email)->send(new WelcomeEmail($user));
        Log::info("Usuário registrado: {$user->id}");

        return response()->json($user, 201);
    }

    public function adminStore(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_admin' => true,
        ]);

        Mail::to($user->email)->send(new WelcomeEmail($user));
        Log::info("Admin registrado: {$user->id}");

        return redirect()->route('admin.users.index');
    }
}
```

<details>
<summary>Solução</summary>

```php
// ✅ DRY: extrair a lógica comum

// 1. Form Request para validação
class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }
}

// 2. Service para a regra de negócio
class UserService
{
    public function create(array $data, bool $isAdmin = false): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_admin' => $isAdmin,
        ]);

        // Evento em vez de chamada direta
        event(new UserRegistered($user, $isAdmin));

        return $user;
    }
}

// 3. Listener para efeito colateral
class SendWelcomeEmail
{
    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeEmail($event->user));
    }
}

class LogUserRegistration
{
    public function handle(UserRegistered $event): void
    {
        $type = $event->isAdmin ? 'Admin' : 'User';
        Log::info("$type registrado: {$event->user->id}");
    }
}

// 4. Controllers (finos!)
class UserController extends Controller
{
    public function store(StoreUserRequest $request, UserService $userService)
    {
        $user = $userService->create($request->validated());

        return response()->json($user, 201);
    }

    public function adminStore(StoreUserRequest $request, UserService $userService)
    {
        $user = $userService->create($request->validated(), isAdmin: true);

        return redirect()->route('admin.users.index');
    }
}

// Vantagens:
// - Validação em um lugar só
// - Lógica de criação em um lugar só
// - Fácil de testar
// - Fácil adicionar tipo novo de usuário
```
</details>

### Exercício 3: Aplique o YAGNI

**Enunciado:** Simplifique o código tirando o que não é usado.

```php
class Product extends Model
{
    protected $fillable = [
        'name', 'description', 'price', 'stock', 'category_id',
        'brand', 'sku', 'weight', 'dimensions', 'color', 'size',
        'material', 'warranty_months', 'is_active', 'is_featured',
        'is_on_sale', 'sale_price', 'sale_starts_at', 'sale_ends_at',
        'meta_title', 'meta_description', 'meta_keywords',
    ];

    // 20+ scopes "por precaução"
    public function scopeActive($query) { return $query->where('is_active', true); }
    public function scopeInactive($query) { return $query->where('is_active', false); }
    public function scopeFeatured($query) { return $query->where('is_featured', true); }
    public function scopeOnSale($query) { return $query->where('is_on_sale', true); }
    public function scopeInStock($query) { return $query->where('stock', '>', 0); }
    public function scopeOutOfStock($query) { return $query->where('stock', 0); }
    public function scopeByBrand($query, $brand) { return $query->where('brand', $brand); }
    public function scopeByCategory($query, $categoryId) { return $query->where('category_id', $categoryId); }
    public function scopePriceRange($query, $min, $max) { return $query->whereBetween('price', [$min, $max]); }
    // ... mais 10 scopes que nunca são usados
}

// Só usam: active, inStock, byCategory
```

<details>
<summary>Solução</summary>

```php
// ✅ YAGNI: fica só o que é usado

class Product extends Model
{
    // fillable mínimo (o resto entra quando precisar)
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'category_id',
        'is_active',
    ];

    // Só os scopes usados
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    // Relations (só as que precisa)
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Outros scopes/fields entram quando realmente precisarem!
}

// Uso
Product::active()->inStock()->byCategory(1)->get();

// Se precisar de featured:
// public function scopeFeatured($query)
// {
//     return $query->where('is_featured', true);
// }

// Princípio: comece pequeno, cresça quando precisar
// Não é "e se precisar", é "adiciono quando precisar"
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
