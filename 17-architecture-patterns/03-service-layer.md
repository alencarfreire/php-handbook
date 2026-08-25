# 10.3 Service Layer

## Resumo

> **Service Layer** — camada de lógica de negócio entre Controller e Model/Repository.
>
> **Para quê:** Controllers finos, reuso da lógica, testabilidade.
>
> **Importante:** O Service chama Repository, outros Services, dispara eventos. Controllers ficam finos.

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
Service Layer — camada de lógica de negócio entre Controller e Model/Repository. Encapsula lógica complexa.

**Para quê:**
- Controllers finos
- Reuso da lógica
- Testabilidade

---

## Como funciona

**Estrutura:**

```
Controller → Service → Repository → Model
```

**Service:**

```php
// app/Services/OrderService.php
class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentService $paymentService,
        private NotificationService $notificationService
    ) {}

    public function create(User $user, array $items): Order
    {
        DB::beginTransaction();

        try {
            // 1. Criar o pedido
            $order = $this->orderRepository->create([
                'user_id' => $user->id,
                'total' => $this->calculateTotal($items),
            ]);

            // 2. Adicionar items
            foreach ($items as $item) {
                $order->items()->create($item);
            }

            // 3. Cobrar o pagamento
            $this->paymentService->charge($user, $order->total);

            // 4. Enviar notificação
            $this->notificationService->sendOrderConfirmation($user, $order);

            // 5. Event
            event(new OrderCreated($order));

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function calculateTotal(array $items): float
    {
        return array_sum(array_column($items, 'price'));
    }
}
```

**Uso no Controller:**

```php
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated('items')
        );

        return redirect()->route('orders.show', $order);
    }
}
```

---

## Quando usar

**Service para:**
- Lógica de negócio
- Operações com várias models
- APIs externas
- Cálculos complexos

**NÃO para:**
- CRUD simples (Controller + Model basta)

---

## Exemplo prático

**Service com várias dependências:**

```php
class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private MailService $mailService,
        private CacheService $cacheService
    ) {}

    public function register(array $data): User
    {
        // 1. Criar o usuário
        $user = $this->userRepository->create([
            'password' => Hash::make($data['password']),
            ...$data,
        ]);

        // 2. Enviar e-mail de boas-vindas
        $this->mailService->sendWelcome($user);

        // 3. Limpar o cache
        $this->cacheService->forget('users.count');

        // 4. Event
        event(new UserRegistered($user));

        return $user;
    }

    public function updateProfile(User $user, array $data): User
    {
        $user = $this->userRepository->update($user, $data);

        // Invalidar o cache
        $this->cacheService->forget("user.{$user->id}");

        return $user;
    }
}
```

**Action Classes (alternativa):**

```php
// app/Actions/CreateOrderAction.php
class CreateOrderAction
{
    public function execute(User $user, array $items): Order
    {
        // Lógica de criar o pedido
        return $order;
    }
}

// Controller
public function store(CreateOrderRequest $request, CreateOrderAction $action)
{
    $order = $action->execute($request->user(), $request->validated('items'));

    return redirect()->route('orders.show', $order);
}
```

---

## Na entrevista

> "Service Layer guarda a lógica de negócio. Controllers ficam finos. O Service chama Repository, outros Services, dispara eventos. Uso para lógica complexa, operação com várias models, API externa. DI pelo construtor. Alternativa: Action Classes para uma operação só. Testo com mock das dependências."

---

## Exercícios práticos

### Exercício 1: Crie UserRegistrationService

**Enunciado:** Implemente o serviço de registro de usuário que:
1. Cria o usuário
2. Envia e-mail de boas-vindas
3. Cria as configurações iniciais
4. Dispara o evento UserRegistered

<details>
<summary>Solução</summary>

```php
// app/Services/UserRegistrationService.php
namespace App\Services;

use App\Contracts\UserRepositoryInterface;
use App\Events\UserRegistered;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserRegistrationService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function register(array $data): User
    {
        DB::beginTransaction();

        try {
            // 1. Criar o usuário
            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // 2. Criar as configurações iniciais
            UserSettings::create([
                'user_id' => $user->id,
                'theme' => 'light',
                'language' => 'pt_BR',
                'notifications_enabled' => true,
            ]);

            // 3. Enviar e-mail de boas-vindas
            Mail::to($user->email)->send(new WelcomeEmail($user));

            // 4. Disparar o evento
            event(new UserRegistered($user));

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// app/Http/Controllers/Auth/RegisterController.php
class RegisterController extends Controller
{
    public function __construct(
        private UserRegistrationService $registrationService
    ) {}

    public function store(RegisterRequest $request)
    {
        $user = $this->registrationService->register(
            $request->validated()
        );

        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Bem-vindo à plataforma!');
    }
}
```
</details>

### Exercício 2: Implemente PaymentService com vários gateways

**Enunciado:** Crie um `PaymentService` que trabalhe com gateways de pagamento diferentes (Stripe, PayPal).

<details>
<summary>Solução</summary>

```php
// app/Contracts/PaymentGatewayInterface.php
namespace App\Contracts;

interface PaymentGatewayInterface
{
    public function charge(float $amount, array $paymentDetails): bool;
    public function refund(string $transactionId, float $amount): bool;
}

// app/Services/Payment/StripeGateway.php
namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Stripe\Stripe;
use Stripe\Charge;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function charge(float $amount, array $paymentDetails): bool
    {
        try {
            Charge::create([
                'amount' => $amount * 100, // centavos
                'currency' => 'brl',
                'source' => $paymentDetails['token'],
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function refund(string $transactionId, float $amount): bool
    {
        // Lógica de reembolso do Stripe
        return true;
    }
}

// app/Services/Payment/PayPalGateway.php
namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;

class PayPalGateway implements PaymentGatewayInterface
{
    public function charge(float $amount, array $paymentDetails): bool
    {
        // Lógica de cobrança do PayPal
        return true;
    }

    public function refund(string $transactionId, float $amount): bool
    {
        // Lógica de reembolso do PayPal
        return true;
    }
}

// app/Services/PaymentService.php
namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\Payment;

class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway
    ) {}

    public function processOrderPayment(Order $order, array $paymentDetails): Payment
    {
        $success = $this->gateway->charge($order->total, $paymentDetails);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total,
            'status' => $success ? 'completed' : 'failed',
            'gateway' => get_class($this->gateway),
        ]);

        if ($success) {
            $order->update(['status' => 'paid']);
        }

        return $payment;
    }
}

// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(
        PaymentGatewayInterface::class,
        function ($app) {
            return match (config('payment.default_gateway')) {
                'stripe' => new StripeGateway(),
                'paypal' => new PayPalGateway(),
                default => new StripeGateway(),
            };
        }
    );
}
```
</details>

### Exercício 3: Action Class vs Service

**Enunciado:** Quando usar Action Class em vez de Service? Implemente `SendPasswordResetEmailAction`.

<details>
<summary>Solução</summary>

```php
// Action Class serve para UMA operação específica
// Service serve para um GRUPO de operações relacionadas

// app/Actions/SendPasswordResetEmailAction.php
namespace App\Actions;

use App\Mail\PasswordResetEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendPasswordResetEmailAction
{
    public function execute(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        $token = Password::createToken($user);

        Mail::to($user->email)->send(
            new PasswordResetEmail($user, $token)
        );

        return true;
    }
}

// app/Http/Controllers/Auth/ForgotPasswordController.php
class ForgotPasswordController extends Controller
{
    public function sendResetLink(
        Request $request,
        SendPasswordResetEmailAction $action
    ) {
        $request->validate(['email' => 'required|email']);

        $sent = $action->execute($request->email);

        return $sent
            ? back()->with('status', 'Link de redefinição enviado!')
            : back()->withErrors(['email' => 'Usuário não encontrado']);
    }
}

// Comparação:
// Action — uma operação, um método execute()
// Service — várias operações, vários métodos

// Exemplo de Service:
class UserService
{
    public function register(array $data): User { }
    public function updateProfile(User $user, array $data): User { }
    public function deleteAccount(User $user): bool { }
    public function suspendAccount(User $user): void { }
}

// Exemplos de Action:
class RegisterUserAction { public function execute(array $data): User { } }
class UpdateUserProfileAction { public function execute(User $user, array $data): User { } }
class DeleteUserAccountAction { public function execute(User $user): bool { } }
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
