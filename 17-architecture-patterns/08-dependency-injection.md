# 10.8 Dependency Injection (DI)

## Resumo

> **Dependency Injection** — você passa as dependências de fora, não cria dentro da classe.
>
> **Tipos:** Constructor (obrigatórias), Setter (opcionais), Method (no método).
>
> **Importante:** o Service Container (container de serviços) do Laravel injeta sozinho pelo type-hint. Prós: testabilidade, baixo acoplamento.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Tipos de injeção](#tipos-de-injeção)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Dependency Injection — você passa as dependências de fora, não cria dentro da classe.

**Tipos:**
- Constructor Injection (pelo construtor)
- Setter Injection (pelo setter)
- Method Injection (pelo método)

---

## Como funciona

**❌ Sem DI (ruim):**

```php
class OrderService
{
    private OrderRepository $repository;
    private MailService $mailService;

    public function __construct()
    {
        // Cria as dependências dentro — acoplamento forte
        $this->repository = new MySQLOrderRepository();
        $this->mailService = new MailService();
    }

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->mailService->sendConfirmation($order);

        return $order;
    }
}

// Problema: não dá para testar, não dá para trocar a implementação
```

**✅ Com DI (bom):**

```php
// Constructor Injection
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private MailService $mailService
    ) {}

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->mailService->sendConfirmation($order);

        return $order;
    }
}

// Uso
$repository = new MySQLOrderRepository();
$mailService = new MailService();
$service = new OrderService($repository, $mailService);
```

**Service Container (Laravel):**

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Liga a interface na implementação
        $this->app->bind(
            OrderRepository::class,
            MySQLOrderRepository::class
        );

        // Singleton
        $this->app->singleton(MailService::class, function ($app) {
            return new MailService(config('mail.driver'));
        });

        // Contextual binding
        $this->app->when(OrderService::class)
            ->needs(OrderRepository::class)
            ->give(MySQLOrderRepository::class);
    }
}

// Controller — injeção automática
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function store(Request $request)
    {
        $order = $this->orderService->create($request->all());

        return response()->json($order);
    }
}
```

---

## Tipos de injeção

**Constructor Injection (o melhor):**

```php
class UserService
{
    // Dependências obrigatórias
    public function __construct(
        private UserRepository $repository,
        private CacheService $cache
    ) {}
}
```

**Setter Injection:**

```php
class UserService
{
    private ?LoggerInterface $logger = null;

    // Dependência opcional
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function create(array $data): User
    {
        $user = $this->repository->create($data);

        $this->logger?->info("Usuário criado: {$user->id}");

        return $user;
    }
}
```

**Method Injection:**

```php
class OrderController extends Controller
{
    // Injeção no método específico
    public function store(
        Request $request,
        OrderService $service  // Method injection
    ) {
        return $service->create($request->all());
    }
}
```

---

## Quando usar

**DI para:**
- Testabilidade (mock das dependências)
- Baixo acoplamento
- Trocar implementação

**Constructor vs Setter:**
- Constructor — dependência obrigatória
- Setter — dependência opcional

---

## Exemplo prático

**Interfaces para DI:**

```php
// app/Contracts/PaymentGateway.php
interface PaymentGateway
{
    public function charge(float $amount): bool;
}

class StripePayment implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // Stripe API
        return true;
    }
}

class PayPalPayment implements PaymentGateway
{
    public function charge(float $amount): bool
    {
        // PayPal API
        return true;
    }
}

// Service Provider
$this->app->bind(PaymentGateway::class, function ($app) {
    return match (config('payment.default')) {
        'stripe' => new StripePayment(),
        'paypal' => new PayPalPayment(),
    };
});

// Service
class OrderService
{
    public function __construct(
        private PaymentGateway $payment  // Interface, não a classe
    ) {}

    public function pay(Order $order): bool
    {
        return $this->payment->charge($order->total);
    }
}
```

**Mock nos testes:**

```php
// tests/Feature/OrderServiceTest.php
class OrderServiceTest extends TestCase
{
    public function test_creates_order(): void
    {
        // Mock das dependências
        $repository = Mockery::mock(OrderRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->andReturn(new Order(['id' => 1]));

        $mailService = Mockery::mock(MailService::class);
        $mailService->shouldReceive('sendConfirmation')
            ->once();

        // Injeta os mocks
        $service = new OrderService($repository, $mailService);

        $order = $service->create(['total' => 100]);

        $this->assertEquals(1, $order->id);
    }
}
```

**Facade vs DI:**

```php
// ❌ Facade — difícil de testar
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create($data);
        Mail::send(new OrderConfirmation($order));  // Facade

        return $order;
    }
}

// ✅ DI — fácil de testar
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private MailService $mailService
    ) {}

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->mailService->send(new OrderConfirmation($order));

        return $order;
    }
}
```

---

## Na entrevista

> "Dependency Injection passa as dependências de fora, não cria dentro. Constructor Injection para as obrigatórias, Setter para as opcionais. O Service Container do Laravel injeta sozinho pelo type-hint. Eu faço bind da interface na implementação no Service Provider. Prós: testabilidade (mock), baixo acoplamento, flexibilidade. Contextual binding quando a implementação muda conforme a classe. Facade vs DI: DI é melhor para teste."

---

## Exercícios práticos

### Exercício 1: Implemente Contextual Binding

**Enunciado:** Você tem dois services que precisam de implementações diferentes de `CacheInterface`. Configure o contextual binding.

<details>
<summary>Solução</summary>

```php
// app/Contracts/CacheInterface.php
interface CacheInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttl): void;
}

// app/Services/Cache/RedisCache.php
class RedisCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return Redis::get($key);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        Redis::setex($key, $ttl, serialize($value));
    }
}

// app/Services/Cache/MemcachedCache.php
class MemcachedCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return Memcached::get($key);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        Memcached::set($key, $value, $ttl);
    }
}

// app/Services/UserService.php (precisa de Redis)
class UserService
{
    public function __construct(
        private CacheInterface $cache
    ) {}
}

// app/Services/ProductService.php (precisa de Memcached)
class ProductService
{
    public function __construct(
        private CacheInterface $cache
    ) {}
}

// app/Providers/AppServiceProvider.php
public function register(): void
{
    // Contextual binding do UserService
    $this->app->when(UserService::class)
        ->needs(CacheInterface::class)
        ->give(RedisCache::class);

    // Contextual binding do ProductService
    $this->app->when(ProductService::class)
        ->needs(CacheInterface::class)
        ->give(MemcachedCache::class);

    // Ou via closure
    $this->app->when(UserService::class)
        ->needs(CacheInterface::class)
        ->give(function ($app) {
            return new RedisCache(
                $app->make('redis')->connection('users')
            );
        });
}
```
</details>

### Exercício 2: Troque Facade estática por DI

**Enunciado:** Refatore o código de Facade para Dependency Injection. Fica mais fácil de testar.

```php
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create($data);

        Mail::to($order->user->email)->send(new OrderConfirmation($order));
        Cache::forget('orders.latest');
        Log::info("Pedido {$order->id} criado");

        return $order;
    }
}
```

<details>
<summary>Solução</summary>

```php
// Cria interfaces/classes para as dependências

// app/Services/MailService.php
class MailService
{
    public function sendOrderConfirmation(Order $order): void
    {
        Mail::to($order->user->email)->send(
            new OrderConfirmation($order)
        );
    }
}

// app/Services/CacheService.php
class CacheService
{
    public function forgetOrders(): void
    {
        Cache::forget('orders.latest');
    }
}

// app/Services/LoggerService.php
class LoggerService
{
    public function logOrderCreated(int $orderId): void
    {
        Log::info("Pedido {$orderId} criado");
    }
}

// Refatora o OrderService
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private MailService $mailService,
        private CacheService $cacheService,
        private LoggerService $logger
    ) {}

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);

        $this->mailService->sendOrderConfirmation($order);
        $this->cacheService->forgetOrders();
        $this->logger->logOrderCreated($order->id);

        return $order;
    }
}

// Teste com mocks
class OrderServiceTest extends TestCase
{
    public function test_creates_order_and_sends_notification(): void
    {
        $repository = Mockery::mock(OrderRepository::class);
        $repository->shouldReceive('create')
            ->once()
            ->andReturn(new Order(['id' => 1]));

        $mailService = Mockery::mock(MailService::class);
        $mailService->shouldReceive('sendOrderConfirmation')
            ->once();

        $cacheService = Mockery::mock(CacheService::class);
        $cacheService->shouldReceive('forgetOrders')
            ->once();

        $logger = Mockery::mock(LoggerService::class);
        $logger->shouldReceive('logOrderCreated')
            ->once()
            ->with(1);

        $service = new OrderService(
            $repository,
            $mailService,
            $cacheService,
            $logger
        );

        $order = $service->create(['total' => 100]);

        $this->assertEquals(1, $order->id);
    }
}
```
</details>

### Exercício 3: Implemente um Service Provider com binding

**Enunciado:** Crie um `PaymentServiceProvider` que registra Payment Gateways diferentes.

<details>
<summary>Solução</summary>

```php
// app/Contracts/PaymentGatewayInterface.php
interface PaymentGatewayInterface
{
    public function charge(float $amount, array $details): bool;
    public function refund(string $transactionId): bool;
}

// app/Services/Payment/StripePayment.php
class StripePayment implements PaymentGatewayInterface
{
    public function __construct(
        private string $apiKey
    ) {}

    public function charge(float $amount, array $details): bool
    {
        // Lógica de charge do Stripe
        return true;
    }

    public function refund(string $transactionId): bool
    {
        // Lógica de refund do Stripe
        return true;
    }
}

// app/Services/Payment/PayPalPayment.php
class PayPalPayment implements PaymentGatewayInterface
{
    public function __construct(
        private string $clientId,
        private string $secret
    ) {}

    public function charge(float $amount, array $details): bool
    {
        // Lógica de charge do PayPal
        return true;
    }

    public function refund(string $transactionId): bool
    {
        // Lógica de refund do PayPal
        return true;
    }
}

// app/Providers/PaymentServiceProvider.php
namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Payment\StripePayment;
use App\Services\Payment\PayPalPayment;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton do Stripe
        $this->app->singleton(StripePayment::class, function ($app) {
            return new StripePayment(
                apiKey: config('services.stripe.secret')
            );
        });

        // Singleton do PayPal
        $this->app->singleton(PayPalPayment::class, function ($app) {
            return new PayPalPayment(
                clientId: config('services.paypal.client_id'),
                secret: config('services.paypal.secret')
            );
        });

        // Binding padrão
        $this->app->bind(
            PaymentGatewayInterface::class,
            function ($app) {
                return match (config('payment.default_gateway')) {
                    'stripe' => $app->make(StripePayment::class),
                    'paypal' => $app->make(PayPalPayment::class),
                    default => $app->make(StripePayment::class),
                };
            }
        );

        // Binding nomeado
        $this->app->bind('payment.stripe', StripePayment::class);
        $this->app->bind('payment.paypal', PayPalPayment::class);
    }

    public function boot(): void
    {
        // Lógica de boot, se precisar
    }
}

// config/app.php — adicionar o provider
'providers' => [
    // ...
    App\Providers\PaymentServiceProvider::class,
],

// Uso
class OrderController extends Controller
{
    public function __construct(
        private PaymentGatewayInterface $payment
    ) {}

    public function pay(Request $request)
    {
        $success = $this->payment->charge(
            $request->amount,
            $request->payment_details
        );

        return $success
            ? response()->json(['message' => 'Pagamento realizado'])
            : response()->json(['message' => 'Falha no pagamento'], 400);
    }
}

// Uso de um gateway específico
$stripeGateway = app('payment.stripe');
$paypalGateway = app('payment.paypal');
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
