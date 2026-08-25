# 4.2 Service Container

## Resumo

> **Service Container (container de serviços)** — container IoC do Laravel para gerenciar dependências.
>
> **Registro:** `bind()` (instância nova), `singleton()` (uma por request), `instance()` (objeto que já existe).
>
> **Importante:** injeção automática pelo construtor. Interface liga na implementação.

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
Service Container — o núcleo do Laravel para gerenciar dependências. Cria os objetos e injeta as dependências sozinho.

**O essencial:**
- Registro de serviços (`bind`, `singleton`)
- Resolução de dependências (injeção automática)
- Dependency Injection pelo construtor

---

## Como funciona

**Registro de serviços:**

```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    // bind — instância nova toda vez
    $this->app->bind(PaymentGateway::class, StripeGateway::class);

    // singleton — uma instância por request
    $this->app->singleton(CacheService::class, function ($app) {
        return new CacheService(
            $app->make('cache.store')
        );
    });

    // instance — usa um objeto que já existe
    $logger = new Logger('app');
    $this->app->instance(Logger::class, $logger);
}
```

**Resolução de dependências:**

```php
// 1. Pelo construtor (automático)
class OrderController extends Controller
{
    // O Laravel cria o OrderService sozinho
    public function __construct(
        private OrderService $orderService
    ) {}
}

// 2. Pelo helper app()
$service = app(OrderService::class);

// 3. Por resolve()
$service = resolve(OrderService::class);

// 4. Por make()
$service = app()->make(OrderService::class);

// 5. Pela facade
use Illuminate\Support\Facades\App;
$service = App::make(OrderService::class);
```

**Contextual Binding (binding contextual):**

```php
// Implementações diferentes para classes diferentes
public function register(): void
{
    // OrderService recebe StripeGateway
    $this->app->when(OrderService::class)
        ->needs(PaymentGateway::class)
        ->give(StripeGateway::class);

    // RefundService recebe PayPalGateway
    $this->app->when(RefundService::class)
        ->needs(PaymentGateway::class)
        ->give(PayPalGateway::class);
}
```

**Binding de interfaces:**

```php
// Interface
interface PaymentGateway
{
    public function charge(int $amount): bool;
}

// Implementação
class StripeGateway implements PaymentGateway
{
    public function charge(int $amount): bool
    {
        // Stripe API
    }
}

// Registro
public function register(): void
{
    $this->app->bind(
        PaymentGateway::class,
        StripeGateway::class
    );
}

// Uso
class OrderService
{
    // Recebe StripeGateway sozinho
    public function __construct(
        private PaymentGateway $gateway
    ) {}
}
```

---

## Quando usar

**Use quando:**
- Precisa trocar a implementação (interfaces)
- Teste (mock das dependências)
- Serviços singleton (logger, por exemplo)
- Inicialização pesada do objeto

**Não use quando:**
- Value object simples, sem dependência
- DTO (Data Transfer Objects)
- Models Eloquent (eles se criam sozinhos)

---

## Exemplo prático

**Exemplo típico com interfaces:**

```php
// 1. Interface (app/Contracts/NotificationService.php)
interface NotificationService
{
    public function send(User $user, string $message): void;
}

// 2. Implementações
class EmailNotificationService implements NotificationService
{
    public function __construct(
        private Mailer $mailer
    ) {}

    public function send(User $user, string $message): void
    {
        $this->mailer->to($user->email)->send(new Notification($message));
    }
}

class SmsNotificationService implements NotificationService
{
    public function __construct(
        private SmsGateway $gateway
    ) {}

    public function send(User $user, string $message): void
    {
        $this->gateway->send($user->phone, $message);
    }
}

// 3. Registro (app/Providers/AppServiceProvider.php)
public function register(): void
{
    // Escolhe a implementação pelo config
    $this->app->bind(
        NotificationService::class,
        config('notifications.driver') === 'sms'
            ? SmsNotificationService::class
            : EmailNotificationService::class
    );
}

// 4. Uso
class OrderService
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function create(User $user, array $data): Order
    {
        $order = Order::create($data);

        // Envia por email ou SMS, conforme o config
        $this->notificationService->send(
            $user,
            "Pedido #{$order->id} criado"
        );

        return $order;
    }
}
```

**Singleton para operações caras:**

```php
// Service Provider
public function register(): void
{
    // Uma instância por request
    $this->app->singleton(ElasticsearchClient::class, function ($app) {
        return new ElasticsearchClient(
            config('services.elasticsearch.host')
        );
    });
}

// Uso em controllers diferentes
class ProductController
{
    public function __construct(
        private ElasticsearchClient $elasticsearch
    ) {}

    public function search(Request $request)
    {
        // Mesma instância do UserController
        return $this->elasticsearch->search($request->query('q'));
    }
}
```

**Contextual Binding para implementações diferentes:**

```php
// Service Provider
public function register(): void
{
    // ProductService usa ProductCache
    $this->app->when(ProductService::class)
        ->needs(CacheRepository::class)
        ->give(function ($app) {
            return new ProductCache(
                $app->make('cache.store'),
                ttl: 3600
            );
        });

    // UserService usa UserCache
    $this->app->when(UserService::class)
        ->needs(CacheRepository::class)
        ->give(function ($app) {
            return new UserCache(
                $app->make('cache.store'),
                ttl: 600
            );
        });
}
```

**Tagged Services (agrupamento):**

```php
// Registro com tags
public function register(): void
{
    $this->app->bind(StripePayment::class);
    $this->app->bind(PayPalPayment::class);
    $this->app->bind(YandexPayment::class);

    $this->app->tag([
        StripePayment::class,
        PayPalPayment::class,
        YandexPayment::class,
    ], 'payment.gateways');
}

// Uso de todos os serviços da tag
class PaymentRouter
{
    private array $gateways;

    public function __construct()
    {
        // Pega todos os serviços da tag
        $this->gateways = app()->tagged('payment.gateways');
    }

    public function route(string $method): PaymentGateway
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($method)) {
                return $gateway;
            }
        }

        throw new UnsupportedPaymentException();
    }
}
```

**Extending (estender serviços já registrados):**

```php
// Altera um registro que já existe
public function register(): void
{
    $this->app->extend(PaymentService::class, function ($service, $app) {
        // Adiciona log
        return new PaymentServiceWithLogging(
            $service,
            $app->make(Logger::class)
        );
    });
}
```

**Teste com mock:**

```php
// tests/Feature/OrderTest.php
public function test_order_creation_sends_notification()
{
    // Mock NotificationService
    $notificationMock = Mockery::mock(NotificationService::class);
    $notificationMock->shouldReceive('send')
        ->once()
        ->with(Mockery::type(User::class), Mockery::type('string'));

    // Substitui no container
    $this->app->instance(NotificationService::class, $notificationMock);

    // Teste
    $user = User::factory()->create();
    $response = $this->actingAs($user)->postJson('/api/orders', [
        'product_id' => 1,
        'quantity' => 2,
    ]);

    $response->assertStatus(201);
}
```

---

## Na entrevista

> "Service Container é o container IoC do Laravel para DI. bind() cria instância nova toda vez, singleton() é uma por request. A injeção pelo construtor é automática. Eu registro no Service Provider. Interface liga na implementação com bind(Interface::class, Implementation::class). Contextual binding quando a implementação muda conforme a classe. No teste eu substituo com app()->instance()."

---

## Exercícios práticos

### Exercício 1: Configure o Contextual Binding

**Enunciado:** Você tem dois serviços: `EmailService` e `SmsService`. Os dois implementam `NotificationInterface`. `OrderService` usa email. `UserService` usa SMS. Configure o container.

<details>
<summary>Solução</summary>

```php
// 1. Interface (app/Contracts/NotificationInterface.php)
interface NotificationInterface
{
    public function send(string $message): void;
}

// 2. Implementações
class EmailService implements NotificationInterface
{
    public function send(string $message): void
    {
        Mail::raw($message, function ($mail) {
            $mail->to('joao@email.com');
        });
    }
}

class SmsService implements NotificationInterface
{
    public function send(string $message): void
    {
        // SMS gateway API
    }
}

// 3. Service Provider (app/Providers/AppServiceProvider.php)
public function register(): void
{
    // OrderService recebe EmailService
    $this->app->when(OrderService::class)
        ->needs(NotificationInterface::class)
        ->give(EmailService::class);

    // UserService recebe SmsService
    $this->app->when(UserService::class)
        ->needs(NotificationInterface::class)
        ->give(SmsService::class);
}

// 4. Uso
class OrderService
{
    public function __construct(
        private NotificationInterface $notification
    ) {}

    public function create(array $data): Order
    {
        $order = Order::create($data);

        // Envia por email
        $this->notification->send("Pedido #{$order->id} criado");

        return $order;
    }
}

class UserService
{
    public function __construct(
        private NotificationInterface $notification
    ) {}

    public function register(array $data): User
    {
        $user = User::create($data);

        // Envia por SMS
        $this->notification->send("Bem-vindo, {$user->name}!");

        return $user;
    }
}
```
</details>

### Exercício 2: Singleton vs Bind

**Enunciado:** Quando usar `singleton()` e quando usar `bind()`? Dê exemplos.

<details>
<summary>Solução</summary>

```php
// ✅ SINGLETON — uma instância por request
// Use para:
// - Operação cara (conexão de DB, cliente HTTP)
// - Serviço stateful (cache, logger)
// - Serviço sem estado mutável compartilhado de propósito

public function register(): void
{
    // 1. Database Connection
    $this->app->singleton(DatabaseConnection::class, function ($app) {
        return new DatabaseConnection(
            config('database.host'),
            config('database.port')
        );
    });

    // 2. Logger (stateful)
    $this->app->singleton(Logger::class, function ($app) {
        return new Logger(storage_path('logs/app.log'));
    });

    // 3. HTTP Client
    $this->app->singleton(HttpClient::class, function ($app) {
        return new HttpClient([
            'base_uri' => config('services.api.base_url'),
            'timeout' => 30,
        ]);
    });

    // 4. Cache Service
    $this->app->singleton(CacheService::class, function ($app) {
        return new CacheService($app->make('cache.store'));
    });
}

// ✅ BIND — instância nova toda vez
// Use para:
// - Serviço stateless
// - Value Objects
// - Serviço com estado mutável

public function register(): void
{
    // 1. Order Calculator (cada cálculo — objeto novo)
    $this->app->bind(OrderCalculator::class, function ($app) {
        return new OrderCalculator(
            taxRate: config('shop.tax_rate')
        );
    });

    // 2. PDF Generator (arquivo novo toda vez)
    $this->app->bind(PdfGenerator::class, function ($app) {
        return new PdfGenerator();
    });

    // 3. Payment Processor (transação nova)
    $this->app->bind(PaymentProcessor::class, function ($app) {
        return new PaymentProcessor(
            $app->make(PaymentGateway::class)
        );
    });
}

// Exemplo do problema: bind no lugar de singleton
class OrderService
{
    public function __construct(
        private DatabaseConnection $db  // ❌ Conexão nova toda vez!
    ) {}
}

// Se registrou com bind():
$service1 = app(OrderService::class);  // Cria DB connection #1
$service2 = app(OrderService::class);  // Cria DB connection #2 (ruim!)

// Se registrou com singleton():
$service1 = app(OrderService::class);  // Cria DB connection #1
$service2 = app(OrderService::class);  // Reusa DB connection #1 (bom!)
```

**Regra:**
- **singleton()** — se o serviço é **caro** ou **stateful**
- **bind()** — se o serviço é **barato** e **stateless**
</details>

### Exercício 3: Mock do serviço no teste

**Enunciado:** Você precisa testar o `OrderService` sem chamar o `PaymentGateway` de verdade. Como substituir?

<details>
<summary>Solução</summary>

```php
// Service (app/Services/OrderService.php)
class OrderService
{
    public function __construct(
        private PaymentGateway $paymentGateway
    ) {}

    public function create(User $user, array $data): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'total' => $data['total'],
        ]);

        // Cobra pelo gateway
        $this->paymentGateway->charge($order->total);

        return $order;
    }
}

// Test (tests/Feature/OrderServiceTest.php)
use Mockery;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    public function test_order_creation_charges_payment()
    {
        // 1. Cria o mock PaymentGateway
        $paymentMock = Mockery::mock(PaymentGateway::class);

        // 2. Configura as expectativas
        $paymentMock->shouldReceive('charge')
            ->once()
            ->with(1000)
            ->andReturn(true);

        // 3. Substitui no container
        $this->app->instance(PaymentGateway::class, $paymentMock);

        // 4. Teste
        $user = User::factory()->create();
        $service = app(OrderService::class);  // Recebe o nosso mock

        $order = $service->create($user, ['total' => 1000]);

        // 5. Checagens
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,
            'total' => 1000,
        ]);
    }

    public function test_payment_failure_throws_exception()
    {
        // Mock que lança exceção
        $paymentMock = Mockery::mock(PaymentGateway::class);
        $paymentMock->shouldReceive('charge')
            ->once()
            ->andThrow(new PaymentException('Cartão recusado'));

        $this->app->instance(PaymentGateway::class, $paymentMock);

        // Esperamos a exceção
        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Cartão recusado');

        $user = User::factory()->create();
        $service = app(OrderService::class);
        $service->create($user, ['total' => 1000]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

// Alternativa: Laravel Mock (sem Mockery)
class OrderServiceTest extends TestCase
{
    public function test_with_laravel_mock()
    {
        // Cria o mock
        $paymentMock = $this->createMock(PaymentGateway::class);

        // Configura as expectativas
        $paymentMock->expects($this->once())
            ->method('charge')
            ->with(1000)
            ->willReturn(true);

        // Substitui
        $this->app->instance(PaymentGateway::class, $paymentMock);

        // Teste
        $user = User::factory()->create();
        $service = app(OrderService::class);
        $order = $service->create($user, ['total' => 1000]);

        $this->assertEquals(1000, $order->total);
    }
}
```

**Formas de substituir:**
1. **app()->instance()** — substitui qualquer classe
2. **Mockery::mock()** — framework de mock mais poderoso
3. **createMock()** — mock nativo do PHPUnit
4. **bind()** no teste — registro temporário

```php
// Forma 4: registro temporário
public function test_with_fake_implementation()
{
    // Implementação fake
    $this->app->bind(PaymentGateway::class, function () {
        return new class implements PaymentGateway {
            public function charge(int $amount): bool
            {
                return true;  // Sempre dá certo
            }
        };
    });

    // Teste
    $service = app(OrderService::class);
    // ...
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
