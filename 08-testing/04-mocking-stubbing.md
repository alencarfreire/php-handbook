# 7.4 Mocking e Stubbing

## Resumo

> **Mock** — objeto falso para isolar o teste. Verifica as chamadas dos métodos. **Stub** — só devolve dados, sem verificar.
>
> **Mockery:** `shouldReceive()` espera a chamada, `with()` os argumentos, `andReturn()` o retorno, `once()`/`never()` a quantidade.
>
> **Importante:** Laravel Fakes para facades: `Mail::fake()`, `Queue::fake()`, `Storage::fake()`. Não mocke Eloquent — use Factory. Spy verifica as chamadas depois.

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
Mock — objeto falso para testar em isolamento. Stub — implementação simplificada da dependência.

**A diferença:**
- **Mock** — verifica as chamadas dos métodos (shouldReceive, once)
- **Stub** — só devolve dados (willReturn)

---

## Como funciona

**Mockery (padrão no Laravel):**

```php
use Mockery;

// Criar o mock
$mock = Mockery::mock(PaymentGateway::class);

// Esperar a chamada
$mock->shouldReceive('charge')
    ->once()  // Exatamente 1 vez
    ->with(100)  // Com o argumento 100
    ->andReturn(true);  // Devolver true

// Usar
$result = $mock->charge(100);  // true
```

**PHPUnit Mock:**

```php
// Criar o mock
$mock = $this->createMock(PaymentGateway::class);

// Configurar o retorno
$mock->method('charge')
    ->with(100)
    ->willReturn(true);

// Usar
$result = $mock->charge(100);  // true
```

---

## Quando usar

**Use Mock quando:**
- Testa em isolamento (unit tests)
- A dependência é cara (API, banco)
- Precisa verificar as chamadas

**Não use quando:**
- Feature tests (dependências reais)
- Objetos simples (DTO, Value Objects)

---

## Exemplo prático

**Mock de API externa:**

```php
// Service com dependência de API
class WeatherService
{
    public function __construct(
        private HttpClient $http
    ) {}

    public function getTemperature(string $city): float
    {
        $response = $this->http->get("https://api.weather.com/{$city}");

        return $response['temperature'];
    }
}

// Unit test com mock
class WeatherServiceTest extends TestCase
{
    public function test_returns_temperature(): void
    {
        // Mock do HTTP client
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('get')
            ->once()
            ->with('https://api.weather.com/SaoPaulo')
            ->andReturn(['temperature' => 15.5]);

        $service = new WeatherService($http);
        $result = $service->getTemperature('SaoPaulo');

        $this->assertEquals(15.5, $result);
    }
}
```

**Mock com retornos diferentes:**

```php
class NotificationService
{
    public function __construct(
        private MailService $mail,
        private SmsService $sms
    ) {}

    public function send(User $user, string $message): void
    {
        if ($user->prefers_email) {
            $this->mail->send($user->email, $message);
        } else {
            $this->sms->send($user->phone, $message);
        }
    }
}

// Teste de email
public function test_sends_email_if_user_prefers_email(): void
{
    $user = new User(['prefers_email' => true, 'email' => 'teste@email.com']);

    $mail = Mockery::mock(MailService::class);
    $mail->shouldReceive('send')
        ->once()
        ->with('teste@email.com', 'Olá');

    $sms = Mockery::mock(SmsService::class);
    $sms->shouldNotReceive('send');  // Não deve ser chamado

    $service = new NotificationService($mail, $sms);
    $service->send($user, 'Olá');
}

// Teste de SMS
public function test_sends_sms_if_user_prefers_sms(): void
{
    $user = new User(['prefers_email' => false, 'phone' => '+5511999998888']);

    $mail = Mockery::mock(MailService::class);
    $mail->shouldNotReceive('send');

    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')
        ->once()
        ->with('+5511999998888', 'Olá');

    $service = new NotificationService($mail, $sms);
    $service->send($user, 'Olá');
}
```

**Spy (verificar as chamadas depois):**

```php
// Spy não exige shouldReceive de antemão
$logger = Mockery::spy(Logger::class);

$service = new OrderService($logger);
$service->create($user, $items);

// Verificar depois da execução
$logger->shouldHaveReceived('log')
    ->with('Pedido criado', Mockery::type('array'));
```

**Partial Mock (mock só de alguns métodos):**

```php
// Mock só do charge(); o resto é real
$gateway = Mockery::mock(PaymentGateway::class)->makePartial();
$gateway->shouldReceive('charge')
    ->andReturn(true);

// Métodos reais funcionam
$gateway->validate($card);  // Método real
```

**Fake (Laravel Facades):**

```php
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Mail fake
Mail::fake();

// Código que envia o email
Mail::to($user)->send(new Welcome());

// Verificar
Mail::assertSent(Welcome::class);
Mail::assertSent(Welcome::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email);
});

// Queue fake
Queue::fake();
dispatch(new ProcessOrder($order));
Queue::assertPushed(ProcessOrder::class);

// Storage fake
Storage::fake('public');
$file = UploadedFile::fake()->image('photo.jpg');
$path = Storage::put('photos', $file);
Storage::assertExists($path);
```

**Mock de Eloquent Model:**

```php
// ❌ NÃO mocke Eloquent direto
$user = Mockery::mock(User::class);  // Complicado, não precisa

// ✅ Use Factory
$user = User::factory()->make(['name' => 'João']);

// ✅ Ou crie o model de verdade
$user = new User(['name' => 'João']);
```

**Mock de Repository:**

```php
// Interface
interface UserRepository
{
    public function find(int $id): ?User;
}

// Implementação
class EloquentUserRepository implements UserRepository
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }
}

// Service
class UserService
{
    public function __construct(
        private UserRepository $users
    ) {}

    public function activate(int $id): void
    {
        $user = $this->users->find($id);
        $user->update(['active' => true]);
    }
}

// Unit test (mock do repository)
public function test_activates_user(): void
{
    $user = User::factory()->make(['id' => 1, 'active' => false]);

    $repository = Mockery::mock(UserRepository::class);
    $repository->shouldReceive('find')
        ->with(1)
        ->andReturn($user);

    $service = new UserService($repository);
    $service->activate(1);

    $this->assertTrue($user->active);
}

// Feature test (repository de verdade)
public function test_activates_user_in_database(): void
{
    $user = User::factory()->create(['active' => false]);

    $repository = new EloquentUserRepository();
    $service = new UserService($repository);
    $service->activate($user->id);

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'active' => true,
    ]);
}
```

**Argument Matchers:**

```php
// Qualquer valor
$mock->shouldReceive('method')
    ->with(Mockery::any());

// Tipo específico
$mock->shouldReceive('send')
    ->with(Mockery::type(User::class), Mockery::type('string'));

// Closure
$mock->shouldReceive('create')
    ->with(Mockery::on(function ($arg) {
        return $arg['email'] === 'teste@email.com';
    }));

// Subset (o array contém)
$mock->shouldReceive('log')
    ->with('Erro', Mockery::subset(['user_id' => 1]));
```

---

## Na entrevista

> "Mock é um objeto falso para isolar o teste. No Mockery: shouldReceive() espera a chamada, with() os argumentos, andReturn() o retorno, once()/never() a quantidade. Spy verifica as chamadas depois da execução. Partial mock mocka só alguns métodos. Laravel Fakes: Mail::fake(), Queue::fake(), Storage::fake(). Não mocko Eloquent — uso Factory. Mock para dependência cara (API, email). Argument matchers: Mockery::type(), Mockery::any(), Mockery::on()."

---

## Exercícios práticos

### Exercício 1: Mock Payment Gateway

Crie um `OrderService` que usa `PaymentGateway` para cobrar. Escreva o unit test com mock do gateway.

<details>
<summary>Solução</summary>

```php
// app/Services/OrderService.php
namespace App\Services;

use App\Models\{Order, User};
use App\Contracts\PaymentGatewayInterface;

class OrderService
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function createOrder(User $user, array $items, float $total): Order
    {
        // Cobrar
        $transactionId = $this->paymentGateway->charge($user, $total);

        // Criar o pedido
        $order = Order::create([
            'user_id' => $user->id,
            'items' => $items,
            'total' => $total,
            'transaction_id' => $transactionId,
            'status' => 'paid',
        ]);

        return $order;
    }

    public function refundOrder(Order $order): bool
    {
        if ($order->status !== 'paid') {
            throw new \RuntimeException('Só pedidos pagos podem ser reembolsados');
        }

        $success = $this->paymentGateway->refund(
            $order->transaction_id,
            $order->total
        );

        if ($success) {
            $order->update(['status' => 'refunded']);
        }

        return $success;
    }
}

// tests/Unit/OrderServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderService;
use App\Contracts\PaymentGatewayInterface;
use App\Models\{Order, User};
use Mockery;

class OrderServiceTest extends TestCase
{
    public function test_creates_order_and_charges_payment(): void
    {
        // Mock do payment gateway
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->with(Mockery::type(User::class), 150.00)
            ->andReturn('txn_123456');

        $user = User::factory()->make(['id' => 1]);
        $items = [
            ['product_id' => 1, 'quantity' => 2, 'price' => 50],
            ['product_id' => 2, 'quantity' => 1, 'price' => 50],
        ];

        $service = new OrderService($gateway);
        $order = $service->createOrder($user, $items, 150.00);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals('paid', $order->status);
        $this->assertEquals('txn_123456', $order->transaction_id);
    }

    public function test_refunds_paid_order(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldReceive('refund')
            ->once()
            ->with('txn_123456', 150.00)
            ->andReturn(true);

        $order = Order::factory()->make([
            'status' => 'paid',
            'transaction_id' => 'txn_123456',
            'total' => 150.00,
        ]);

        $service = new OrderService($gateway);
        $result = $service->refundOrder($order);

        $this->assertTrue($result);
        $this->assertEquals('refunded', $order->status);
    }

    public function test_throws_exception_when_refunding_unpaid_order(): void
    {
        $gateway = Mockery::mock(PaymentGatewayInterface::class);
        $gateway->shouldNotReceive('refund');

        $order = Order::factory()->make(['status' => 'pending']);

        $service = new OrderService($gateway);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Só pedidos pagos podem ser reembolsados');

        $service->refundOrder($order);
    }
}
```
</details>

### Exercício 2: Spy para Logger

Crie um `UserRegistrationService` que registra as ações no log. Use Spy para verificar as chamadas do logger depois da execução.

<details>
<summary>Solução</summary>

```php
// app/Services/UserRegistrationService.php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Psr\Log\LoggerInterface;

class UserRegistrationService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function register(array $data): User
    {
        $this->logger->info('Cadastro de usuário iniciado', [
            'email' => $data['email'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $this->logger->info('Usuário cadastrado com sucesso', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    public function registerWithReferral(array $data, string $referralCode): User
    {
        $this->logger->info('Cadastro com indicação', [
            'email' => $data['email'],
            'referral_code' => $referralCode,
        ]);

        $user = $this->register($data);

        // Lógica do programa de indicação
        $this->logger->info('Bônus de indicação aplicado', [
            'user_id' => $user->id,
            'referral_code' => $referralCode,
        ]);

        return $user;
    }
}

// tests/Unit/UserRegistrationServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\UserRegistrationService;
use Psr\Log\LoggerInterface;
use Mockery;

class UserRegistrationServiceTest extends TestCase
{
    public function test_logs_registration_process(): void
    {
        // Spy não exige shouldReceive de antemão
        $logger = Mockery::spy(LoggerInterface::class);

        $service = new UserRegistrationService($logger);

        $user = $service->register([
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'password' => 'password123',
        ]);

        // Verificar as chamadas DEPOIS da execução
        $logger->shouldHaveReceived('info')
            ->with('Cadastro de usuário iniciado', Mockery::subset([
                'email' => 'joao@email.com',
            ]));

        $logger->shouldHaveReceived('info')
            ->with('Usuário cadastrado com sucesso', Mockery::subset([
                'email' => 'joao@email.com',
            ]));
    }

    public function test_logs_referral_registration(): void
    {
        $logger = Mockery::spy(LoggerInterface::class);

        $service = new UserRegistrationService($logger);

        $user = $service->registerWithReferral([
            'name' => 'Maria Silva',
            'email' => 'maria@email.com',
            'password' => 'password123',
        ], 'REF123');

        // Devem ser 4 chamadas de info()
        $logger->shouldHaveReceived('info')->times(4);

        // Verificar a chamada com o código de indicação
        $logger->shouldHaveReceived('info')
            ->with('Cadastro com indicação', Mockery::subset([
                'referral_code' => 'REF123',
            ]));
    }
}
```
</details>

### Exercício 3: Laravel Fakes para Mail e Queue

Escreva o teste de cadastro de usuário que envia welcome email e dispara um job de processamento. Use fakes.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/RegisterController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\WelcomeEmail;
use App\Jobs\ProcessNewUser;
use Illuminate\Support\Facades\{Hash, Mail};
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Enviar welcome email
        Mail::to($user)->send(new WelcomeEmail($user));

        // Disparar o job de processamento
        ProcessNewUser::dispatch($user);

        return response()->json([
            'message' => 'Cadastro realizado com sucesso',
            'user' => $user,
        ], 201);
    }
}

// tests/Feature/RegisterControllerTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Mail\WelcomeEmail;
use App\Jobs\ProcessNewUser;
use Illuminate\Support\Facades\{Mail, Queue};
use Illuminate\Foundation\Testing\RefreshDatabase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_user_and_sends_email(): void
    {
        Mail::fake();
        Queue::fake();

        $response = $this->postJson('/register', [
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Cadastro realizado com sucesso',
        ]);

        // Verificar o usuário no banco
        $this->assertDatabaseHas('users', [
            'email' => 'joao@email.com',
        ]);

        $user = User::where('email', 'joao@email.com')->first();

        // Verificar o envio do email
        Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Verificar o dispatch do job
        Queue::assertPushed(ProcessNewUser::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });
    }

    public function test_does_not_send_email_on_validation_failure(): void
    {
        Mail::fake();
        Queue::fake();

        $response = $this->postJson('/register', [
            'name' => 'João',
            'email' => 'invalid-email',  // Email inválido
            'password' => '123',  // Curto demais
        ]);

        $response->assertStatus(422);

        // Email e Job NÃO devem ser enviados
        Mail::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_rejects_duplicate_email(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'existente@email.com']);

        $response = $this->postJson('/register', [
            'name' => 'João Silva',
            'email' => 'existente@email.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
