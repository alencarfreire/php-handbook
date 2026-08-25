# 4.4 Facades

## Resumo

> **Facades** — interface estática para serviços do Service Container (container de serviços).
>
> **Exemplo:** `Cache::get()` no lugar de `app('cache')->get()`. Usa `__callStatic()` para chamar os métodos.
>
> **Importante:** Dá para mockar nos testes. Real-time Facades via `Facades\App\Services\ServiceName`.

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
Facades — interface estática para classes no Service Container. Parecem métodos estáticos, mas passam pelo container.

**O essencial:**
- `Cache::get()` no lugar de `app('cache')->get()`
- Sintaxe estática, binding dinâmico
- Testable (dá para mockar)

---

## Como funciona

**Por dentro:**

```php
// Classe Facade
use Illuminate\Support\Facades\Facade;

class Cache extends Facade
{
    // Chave no container
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}

// Uso
Cache::get('key');  // Equivale a app('cache')->get('key')
```

**Método mágico __callStatic:**

```php
// Dentro da classe Facade
public static function __callStatic($method, $args)
{
    $instance = static::getFacadeRoot();  // Pega do container

    return $instance->$method(...$args);  // Chama o método
}
```

**Facades mais usadas:**

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

// Database
DB::table('users')->where('active', 1)->get();

// Cache
Cache::remember('users', 3600, fn() => User::all());

// Logs
Log::info('Usuário registrado', ['user_id' => $user->id]);

// Storage
Storage::disk('s3')->put('file.txt', 'conteúdo');

// Mail
Mail::to($user)->send(new Welcome($user));
```

**Real-time Facades (automáticas):**

```php
// Classe comum (SEM Facade)
namespace App\Services;

class PaymentService
{
    public function charge(int $amount): bool
    {
        // Lógica
    }
}

// Uso via Real-time Facade
use Facades\App\Services\PaymentService;

PaymentService::charge(1000);  // O Laravel cria a facade sozinho
```

---

## Quando usar

**Prós:**
- ✅ Sintaxe curta
- ✅ Testable (dá para mockar)
- ✅ Autocomplete da IDE (com laravel-ide-helper)

**Contras:**
- ❌ Esconde as dependências (não aparecem no construtor)
- ❌ Mais difícil de testar (precisa de métodos especiais)
- ❌ Chamada estática parece estado global

**Quando usar:**
- Rotas, migrations, seeders (código curto)
- Controllers (se não exagerar)

**Quando NÃO usar:**
- Services (melhor DI pelo construtor)
- Testes (mockar facade é mais chato)

---

## Exemplo prático

**Uso no controller:**

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        // Cache facade
        $products = Cache::remember('products.all', 3600, function () {
            Log::info('Carregando produtos do banco');
            return Product::all();
        });

        return view('products.index', compact('products'));
    }

    public function store(Request $request)
    {
        $product = Product::create($request->validated());

        // Limpa o cache
        Cache::forget('products.all');

        // Log
        Log::info('Produto criado', ['id' => $product->id]);

        return redirect()->route('products.show', $product);
    }
}
```

**Facade customizada:**

```php
// 1. Service (app/Services/PaymentService.php)
namespace App\Services;

class PaymentService
{
    public function charge(User $user, int $amount): bool
    {
        // Lógica de pagamento
        return true;
    }

    public function refund(Order $order): bool
    {
        // Lógica de reembolso
        return true;
    }
}

// 2. Registro no Service Provider
public function register(): void
{
    $this->app->singleton('payment', function ($app) {
        return new PaymentService();
    });
}

// 3. Classe Facade (app/Facades/Payment.php)
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'payment';  // Chave no container
    }
}

// 4. Uso
use App\Facades\Payment;

Payment::charge($user, 1000);
Payment::refund($order);
```

**Real-time Facades:**

```php
// Service (app/Services/NotificationService.php)
namespace App\Services;

class NotificationService
{
    public function send(User $user, string $message): void
    {
        // Envia notificação
    }
}

// Uso SEM criar classe Facade
use Facades\App\Services\NotificationService;

// O Laravel cria a facade sozinho
NotificationService::send($user, 'Olá');
```

**Mock de Facades nos testes:**

```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

public function test_product_creation_clears_cache()
{
    // Mock da Cache facade
    Cache::shouldReceive('forget')
        ->once()
        ->with('products.all');

    $response = $this->postJson('/api/products', [
        'name' => 'Produto Teste',
    ]);

    $response->assertStatus(201);
}

public function test_order_confirmation_email_sent()
{
    // Fake Mail (não envia de verdade)
    Mail::fake();

    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    // Dispara o event
    event(new OrderCreated($order));

    // Confirma que o email foi enviado
    Mail::assertSent(OrderConfirmation::class, function ($mail) use ($user) {
        return $mail->hasTo($user->email);
    });
}
```

**Facade vs Dependency Injection:**

```php
// ❌ RUIM: Facade no service (esconde as dependências)
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create($data);

        // Dependência escondida
        Cache::forget('orders');
        Log::info('Pedido criado');

        return $order;
    }
}

// ✅ BOM: DI (dependências explícitas)
class OrderService
{
    public function __construct(
        private CacheRepository $cache,
        private LoggerInterface $logger
    ) {}

    public function create(array $data): Order
    {
        $order = Order::create($data);

        // Dependências explícitas (aparecem no construtor)
        $this->cache->forget('orders');
        $this->logger->info('Pedido criado');

        return $order;
    }
}

// ✅ OK: Facade no controller (operações curtas)
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        Cache::forget('orders');
        Log::info('Pedido criado');

        return new OrderResource($order);
    }
}
```

**Facade com alias:**

```php
// config/app.php
'aliases' => [
    'Cache' => Illuminate\Support\Facades\Cache::class,
    'DB' => Illuminate\Support\Facades\DB::class,
    'Payment' => App\Facades\Payment::class,  // Customizada
],

// Agora funciona sem use
Cache::get('key');
Payment::charge($user, 1000);
```

**Autocomplete na IDE:**

```bash
# Instalar laravel-ide-helper
composer require --dev barryvdh/laravel-ide-helper

# Gerar as anotações
php artisan ide-helper:generate

# Agora a IDE conhece os métodos das Facades
Cache::get('key');  // A IDE sugere os métodos
```

---

## Na entrevista

> "Facade é interface estática para serviço do container. Cache::get() no lugar de app('cache')->get(). Por dentro usa __callStatic() para chamar o método. Dá para mockar no teste (Cache::shouldReceive()). Real-time Facade o Laravel cria sozinho, namespace Facades\App\Services\ServiceName. Em service eu não abuso — prefiro DI no construtor, a dependência fica explícita."

---

## Exercícios práticos

### Exercício 1: Crie uma Facade customizada

**Enunciado:** Crie uma Facade para `SettingsService`, que carrega as configurações do banco.

<details>
<summary>Solução</summary>

```php
// 1. Service (app/Services/SettingsService.php)
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("settings.{$key}", 3600, function () use ($key, $default) {
            $setting = DB::table('settings')
                ->where('key', $key)
                ->first();

            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()]
        );

        Cache::forget("settings.{$key}");
    }

    public function all(): array
    {
        return Cache::remember('settings.all', 3600, function () {
            return DB::table('settings')
                ->pluck('value', 'key')
                ->toArray();
        });
    }
}

// 2. Service Provider (app/Providers/SettingsServiceProvider.php)
namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registro com a chave 'settings'
        $this->app->singleton('settings', function ($app) {
            return new SettingsService();
        });
    }
}

// 3. Classe Facade (app/Facades/Settings.php)
namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array all()
 *
 * @see \App\Services\SettingsService
 */
class Settings extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'settings';  // Chave no container
    }
}

// 4. Registro do provider (config/app.php)
'providers' => [
    // ...
    App\Providers\SettingsServiceProvider::class,
],

// 5. Registro do alias (config/app.php) — opcional
'aliases' => [
    // ...
    'Settings' => App\Facades\Settings::class,
],

// 6. Uso
use App\Facades\Settings;

// No controller
class HomeController extends Controller
{
    public function index()
    {
        $siteName = Settings::get('site_name', 'Meu Site');
        $maintenance = Settings::get('maintenance_mode', false);

        return view('home', compact('siteName', 'maintenance'));
    }
}

// No Blade
{{ Settings::get('site_name') }}

// Definir valor
Settings::set('site_name', 'Novo nome do site');

// Todas as configurações
$allSettings = Settings::all();
```

**PHPDoc para autocomplete na IDE:**
```php
/**
 * @method static mixed get(string $key, mixed $default = null)
 * @method static void set(string $key, mixed $value)
 * @method static array all()
 *
 * @see \App\Services\SettingsService
 */
```
</details>

### Exercício 2: Facade vs Dependency Injection

**Enunciado:** Quando usar Facade e quando usar DI? Corrija o código.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: Facade no Service (esconde as dependências)
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create($data);

        // Dependências escondidas (não aparecem no construtor)
        Cache::forget('orders.all');
        Log::info('Pedido criado', ['id' => $order->id]);
        Mail::to($order->user)->send(new OrderConfirmation($order));

        return $order;
    }

    // Problemas:
    // 1. Difícil de testar (precisa de Mockery)
    // 2. Dependências escondidas (não dá para ver o que usa)
    // 3. Não dá para trocar no teste sem métodos especiais
}

// ✅ BOM: DI no Service (dependências explícitas)
namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Mail\Mailer;
use Psr\Log\LoggerInterface;

class OrderService
{
    // Dependências explícitas (aparecem no construtor)
    public function __construct(
        private CacheRepository $cache,
        private LoggerInterface $logger,
        private Mailer $mailer
    ) {}

    public function create(array $data): Order
    {
        $order = Order::create($data);

        // As mesmas ações, mas via DI
        $this->cache->forget('orders.all');
        $this->logger->info('Pedido criado', ['id' => $order->id]);
        $this->mailer->to($order->user)->send(new OrderConfirmation($order));

        return $order;
    }

    // Prós:
    // 1. Fácil de testar (mock no construtor)
    // 2. Dependências explícitas (dá para ver o que usa)
    // 3. Troca no container ou no teste
}

// ✅ OK: Facade no Controller (operações curtas)
namespace App\Http\Controllers;

use App\Facades\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService  // Service via DI
    ) {}

    public function store(Request $request)
    {
        // Facade para operação simples no controller — OK
        Cache::forget('orders.all');
        Log::info('Criação do pedido iniciada');

        $order = $this->orderService->create($request->validated());

        return new OrderResource($order);
    }

    public function index()
    {
        $perPage = Settings::get('orders_per_page', 15);

        return OrderResource::collection(
            Order::paginate($perPage)
        );
    }
}

// ✅ ÓTIMO: Facade em rotas, migrations, seeders
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
});

// database/seeders/UserSeeder.php
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@email.com',
            'password' => Hash::make('password'),
        ]);
    }
}

// database/migrations/xxx_create_orders_table.php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

Schema::create('orders', function (Blueprint $table) {
    $table->id();
    // ...
});
```

**Regra:**
- **Services** → DI pelo construtor (dependências explícitas)
- **Controllers** → Facade OK para operação simples
- **Rotas, migrations, seeders** → Facade (código curto)
- **Tests** → DI ou Mockery para Facades

**Testes:**
```php
// Service com DI — fácil de testar
class OrderServiceTest extends TestCase
{
    public function test_order_creation()
    {
        // Mock pelo construtor
        $cacheMock = $this->createMock(CacheRepository::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $mailerMock = $this->createMock(Mailer::class);

        $cacheMock->expects($this->once())->method('forget');
        $loggerMock->expects($this->once())->method('info');
        $mailerMock->expects($this->once())->method('to');

        $service = new OrderService($cacheMock, $loggerMock, $mailerMock);
        $order = $service->create(['total' => 1000]);

        $this->assertEquals(1000, $order->total);
    }
}

// Facade — precisa de Mockery
class OrderServiceWithFacadeTest extends TestCase
{
    public function test_order_creation()
    {
        Cache::shouldReceive('forget')->once();
        Log::shouldReceive('info')->once();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once();

        $service = new OrderService();
        $order = $service->create(['total' => 1000]);

        $this->assertEquals(1000, $order->total);
    }
}
```
</details>

### Exercício 3: Real-time Facade

**Enunciado:** Use Real-time Facade para `PaymentService` sem criar a classe Facade.

<details>
<summary>Solução</summary>

```php
// 1. Service (app/Services/PaymentService.php)
namespace App\Services;

class PaymentService
{
    public function __construct(
        private string $apiKey
    ) {}

    public function charge(int $amount): bool
    {
        // Chamada da API de pagamento
        return true;
    }

    public function refund(string $transactionId): bool
    {
        // Chamada da API de reembolso
        return true;
    }

    public function getBalance(): int
    {
        // Busca o saldo
        return 10000;
    }
}

// 2. Registro no container (app/Providers/AppServiceProvider.php)
public function register(): void
{
    $this->app->singleton(PaymentService::class, function ($app) {
        return new PaymentService(
            apiKey: config('services.payment.key')
        );
    });
}

// 3. Uso da Real-time Facade (SEM criar classe Facade!)
namespace App\Http\Controllers;

// Prefixo Facades\ no namespace
use Facades\App\Services\PaymentService;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Usa como métodos estáticos
        $charged = PaymentService::charge($request->amount);

        if ($charged) {
            $order = Order::create($request->validated());
            return new OrderResource($order);
        }

        return response()->json(['error' => 'Pagamento falhou'], 400);
    }

    public function refund(Order $order)
    {
        $refunded = PaymentService::refund($order->transaction_id);

        if ($refunded) {
            $order->update(['status' => 'refunded']);
            return response()->json(['message' => 'Reembolsado']);
        }

        return response()->json(['error' => 'Reembolso falhou'], 400);
    }

    public function balance()
    {
        $balance = PaymentService::getBalance();

        return response()->json(['balance' => $balance]);
    }
}

// 4. No Blade
@php
    use Facades\App\Services\PaymentService;
@endphp

<div>Saldo: {{ PaymentService::getBalance() }}</div>

// 5. Teste da Real-time Facade
use Facades\App\Services\PaymentService;

class OrderControllerTest extends TestCase
{
    public function test_order_creation_charges_payment()
    {
        // Mock da Real-time Facade
        PaymentService::shouldReceive('charge')
            ->once()
            ->with(1000)
            ->andReturn(true);

        $response = $this->postJson('/api/orders', [
            'amount' => 1000,
            'product_id' => 1,
        ]);

        $response->assertStatus(201);
    }

    public function test_refund()
    {
        PaymentService::shouldReceive('refund')
            ->once()
            ->with('txn_123')
            ->andReturn(true);

        $order = Order::factory()->create(['transaction_id' => 'txn_123']);

        $response = $this->postJson("/api/orders/{$order->id}/refund");

        $response->assertStatus(200);
    }
}
```

**Como funciona a Real-time Facade:**
```php
// Chamada normal
use App\Services\PaymentService;
app(PaymentService::class)->charge(1000);

// Real-time Facade (automática)
use Facades\App\Services\PaymentService;
PaymentService::charge(1000);

// O Laravel cria a classe Facade sozinho:
namespace Facades\App\Services;

class PaymentService extends \Illuminate\Support\Facades\Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\PaymentService::class;
    }
}
```

**Prós das Real-time Facades:**
- Não precisa criar a classe Facade
- Sintaxe curta
- Funciona com qualquer classe

**Contras:**
- Nem todo mundo conhece essa feature
- A IDE pode não sugerir os métodos (precisa do laravel-ide-helper)
- Esconde as dependências (como as Facades comuns)

**Quando usar:**
- Em controllers para operações curtas
- Em templates Blade
- Quando você não quer criar a classe Facade
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
