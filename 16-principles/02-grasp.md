# 10.2 GRASP (General Responsibility Assignment Software Patterns)

## Resumo

> **GRASP** — 9 princípios para atribuir responsabilidade às classes no OOP.
>
> **Principais:** Information Expert (Order calcula o total), Creator (Order cria os items), Controller (thin), Low Coupling (DI).
>
> **Além disso:** High Cohesion (uma responsabilidade), Polymorphism (no lugar do if), Indirection (intermediários), Protected Variations (interfaces estáveis).

---

## Conteúdo

- [O que é](#o-que-é)
- [1. Information Expert](#1-information-expert)
- [2. Creator](#2-creator)
- [3. Controller](#3-controller)
- [4. Low Coupling](#4-low-coupling)
- [5. High Cohesion](#5-high-cohesion)
- [6. Polymorphism](#6-polymorphism)
- [7. Pure Fabrication](#7-pure-fabrication)
- [8. Indirection](#8-indirection)
- [9. Protected Variations](#9-protected-variations)
- [No Laravel](#no-laravel)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**GRASP:**
9 princípios para atribuir responsabilidade a classes e objetos no OOP.

**Para quê:**
- Distribuir responsabilidade do jeito certo
- Código fácil de manter
- Low coupling, high cohesion

**9 princípios:**
1. Information Expert
2. Creator
3. Controller
4. Low Coupling
5. High Cohesion
6. Polymorphism
7. Pure Fabrication
8. Indirection
9. Protected Variations

---

## 1. Information Expert

**Princípio:**
A responsabilidade vai para a classe que tem a informação para cumprir.

**❌ Ruim:**

```php
class OrderController
{
    public function show($id)
    {
        $order = Order::with('items')->find($id);

        // Controller calcula o total (não é responsabilidade dele!)
        $total = 0;
        foreach ($order->items as $item) {
            $total += $item->price * $item->quantity;
        }

        return view('orders.show', ['order' => $order, 'total' => $total]);
    }
}
```

**✅ Bom (Information Expert):**

```php
class Order extends Model
{
    // Order conhece os items → ele calcula o total
    public function getTotalAttribute(): float
    {
        return $this->items->sum(fn($item) => $item->price * $item->quantity);
    }
}

class OrderController
{
    public function show($id)
    {
        $order = Order::with('items')->find($id);

        return view('orders.show', ['order' => $order]);
    }
}

// Na view
{{ $order->total }}
```

---

## 2. Creator

**Princípio:**
A classe B cria A se:
- B contém A
- B agrega A
- B tem os dados para inicializar A

**❌ Ruim:**

```php
class OrderController
{
    public function store(Request $request)
    {
        $order = Order::create([...]);

        // Controller cria items (não é responsabilidade dele!)
        foreach ($request->items as $itemData) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'price' => Product::find($itemData['product_id'])->price,
            ]);
        }
    }
}
```

**✅ Bom (Creator):**

```php
class Order extends Model
{
    // Order contém items → Order cria os items
    public function addItem(int $productId, int $quantity): OrderItem
    {
        $product = Product::find($productId);

        return $this->items()->create([
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);
    }
}

class OrderController
{
    public function store(Request $request)
    {
        $order = Order::create([...]);

        foreach ($request->items as $itemData) {
            $order->addItem($itemData['product_id'], $itemData['quantity']);
        }
    }
}
```

---

## 3. Controller

**Princípio:**
O Controller trata eventos do sistema (HTTP requests) e delega a lógica de negócio para outros objetos.

**❌ Ruim (Fat Controller):**

```php
class OrderController extends Controller
{
    public function store(Request $request)
    {
        // Validação
        $validated = $request->validate([...]);

        // Lógica de negócio
        $order = Order::create([...]);

        foreach ($validated['items'] as $item) {
            $product = Product::find($item['product_id']);
            if ($product->stock < $item['quantity']) {
                throw new OutOfStockException();
            }
            $product->decrement('stock', $item['quantity']);

            $order->items()->create([...]);
        }

        // Email
        Mail::to($order->user)->send(new OrderCreated($order));

        // Log
        Log::info("Pedido criado", ['order_id' => $order->id]);

        return response()->json($order);
    }
}
```

**✅ Bom (Thin Controller):**

```php
class OrderController extends Controller
{
    public function store(
        StoreOrderRequest $request,
        OrderService $orderService
    ) {
        // Controller só coordena
        $order = $orderService->create($request->validated());

        return response()->json($order);
    }
}

class OrderService
{
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create([...]);

            foreach ($data['items'] as $itemData) {
                $this->addItemToOrder($order, $itemData);
            }

            event(new OrderCreated($order));

            return $order;
        });
    }

    private function addItemToOrder(Order $order, array $itemData): void
    {
        $product = Product::lockForUpdate()->find($itemData['product_id']);

        if ($product->stock < $itemData['quantity']) {
            throw new OutOfStockException();
        }

        $product->decrement('stock', $itemData['quantity']);

        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $itemData['quantity'],
            'price' => $product->price,
        ]);
    }
}
```

---

## 4. Low Coupling

**Princípio:**
Minimizar dependências entre classes.

**❌ High Coupling:**

```php
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Dependência direta de uma classe concreta
        $mailer = new SmtpMailer();
        $mailer->sendOrderConfirmation($order);

        $logger = new FileLogger();
        $logger->log("Pedido criado");

        return $order;
    }
}
```

**✅ Low Coupling (Dependency Injection):**

```php
class OrderService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger
    ) {}

    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Dependência da interface
        $this->mailer->sendOrderConfirmation($order);
        $this->logger->info("Pedido criado");

        return $order;
    }
}
```

---

## 5. High Cohesion

**Princípio:**
A classe tem uma responsabilidade clara. Os métodos da classe se relacionam.

**❌ Low Cohesion:**

```php
class UserService
{
    // Tudo numa classe só (responsabilidades diferentes!)
    public function register(array $data) { }
    public function login(string $email, string $password) { }
    public function sendPasswordReset(string $email) { }
    public function updateProfile(User $user, array $data) { }
    public function deleteAccount(User $user) { }
    public function exportToCSV(User $user) { }
    public function calculateStatistics() { }
}
```

**✅ High Cohesion:**

```php
// Separar em classes coesas
class AuthService
{
    public function register(array $data) { }
    public function login(string $email, string $password) { }
    public function sendPasswordReset(string $email) { }
}

class ProfileService
{
    public function update(User $user, array $data) { }
    public function delete(User $user) { }
}

class UserExportService
{
    public function toCSV(User $user) { }
}

class UserStatisticsService
{
    public function calculate() { }
}
```

---

## 6. Polymorphism

**Princípio:**
Use polimorfismo no lugar de if/switch.

**❌ Sem polimorfismo:**

```php
class OrderProcessor
{
    public function process(Order $order)
    {
        if ($order->payment_method === 'credit_card') {
            $this->processCreditCard($order);
        } elseif ($order->payment_method === 'paypal') {
            $this->processPayPal($order);
        } elseif ($order->payment_method === 'crypto') {
            $this->processCrypto($order);
        }
    }
}
```

**✅ Com polimorfismo:**

```php
interface PaymentGateway
{
    public function charge(Order $order): Payment;
}

class CreditCardGateway implements PaymentGateway
{
    public function charge(Order $order): Payment { }
}

class PayPalGateway implements PaymentGateway
{
    public function charge(Order $order): Payment { }
}

class CryptoGateway implements PaymentGateway
{
    public function charge(Order $order): Payment { }
}

class OrderProcessor
{
    public function process(Order $order, PaymentGateway $gateway)
    {
        $gateway->charge($order);
    }
}

// Uso
$gateway = match ($order->payment_method) {
    'credit_card' => new CreditCardGateway(),
    'paypal' => new PayPalGateway(),
    'crypto' => new CryptoGateway(),
};

$processor->process($order, $gateway);
```

---

## 7. Pure Fabrication

**Princípio:**
Crie uma classe artificial para uma responsabilidade que não cabe em nenhum objeto de domain.

**Exemplo:**
Log não é domain, mas você precisa.

```php
// Pure Fabrication: classe para responsabilidade técnica
class Logger
{
    public function log(string $message): void
    {
        // Responsabilidade técnica (não é domain)
    }
}

class OrderService
{
    public function __construct(private Logger $logger) {}

    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Usamos Pure Fabrication
        $this->logger->log("Pedido {$order->id} criado");

        return $order;
    }
}
```

**Outros exemplos de Pure Fabrication:**
- Repository (acesso ao banco)
- Cache
- EventDispatcher
- Validator

---

## 8. Indirection

**Princípio:**
Use um intermediário para baixar o coupling.

**❌ Dependência direta:**

```php
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Dependência direta do SMTP
        $mailer = new SmtpMailer();
        $mailer->send($order->user->email, 'Pedido criado', '...');

        return $order;
    }
}
```

**✅ Indirection (intermediário):**

```php
// Intermediário: Laravel Mail facade
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Indirection via Mail facade
        Mail::to($order->user)->send(new OrderCreated($order));

        return $order;
    }
}

// Mail facade — intermediário entre OrderService e SMTP
```

---

## 9. Protected Variations

**Princípio:**
Proteja o sistema de mudanças com interfaces estáveis.

**❌ Sem proteção:**

```php
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Dependência direta da Stripe API
        $stripe = new StripeClient(config('stripe.key'));
        $stripe->charges->create([
            'amount' => $order->total * 100,
            'currency' => 'brl',
        ]);

        return $order;
    }
}

// Se precisar trocar Stripe por PayPal → reescreve o OrderService
```

**✅ Protected Variations:**

```php
// Interface estável
interface PaymentGateway
{
    public function charge(int $amount, string $currency): Payment;
}

class StripeGateway implements PaymentGateway
{
    public function charge(int $amount, string $currency): Payment
    {
        $stripe = new StripeClient(config('stripe.key'));
        // ...
    }
}

class PayPalGateway implements PaymentGateway
{
    public function charge(int $amount, string $currency): Payment
    {
        // PayPal API
    }
}

class OrderService
{
    public function __construct(private PaymentGateway $gateway) {}

    public function create(array $data): Order
    {
        $order = Order::create([...]);

        // Protegidos de mudança no payment gateway
        $this->gateway->charge($order->total, 'brl');

        return $order;
    }
}

// Dá para trocar o gateway sem mexer no OrderService
```

---

## No Laravel

```php
// Information Expert
class Order extends Model
{
    public function getTotalAttribute() { /* Order conhece os items */ }
}

// Creator
class Order extends Model
{
    public function addItem($productId, $quantity) { /* Order cria os items */ }
}

// Controller (thin)
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $service)
    {
        return $service->create($request->validated());
    }
}

// Low Coupling (DI)
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway,
        private LoggerInterface $logger
    ) {}
}

// High Cohesion (Single Responsibility)
class AuthService { /* só auth */ }
class ProfileService { /* só profile */ }

// Polymorphism
interface PaymentGateway { }
class StripeGateway implements PaymentGateway { }

// Pure Fabrication
class OrderRepository { /* responsabilidade técnica */ }

// Indirection
Mail::to($user)->send(new OrderCreated($order));

// Protected Variations
interface PaymentGateway { /* interface estável */ }
```

---

## Na entrevista

> "GRASP — 9 princípios para atribuir responsabilidade. Information Expert: a responsabilidade vai para quem tem a informação (Order calcula o total). Creator: a classe cria o que ela contém (Order cria os items). Controller: thin, delega para o Service. Low Coupling: dependências via interface (DI). High Cohesion: uma responsabilidade clara (AuthService separado de ProfileService). Polymorphism: no lugar de if/switch. Pure Fabrication: classes artificiais para responsabilidade técnica (Logger, Repository). Indirection: intermediários (Mail facade). Protected Variations: interfaces estáveis para proteger de mudança (PaymentGateway)."

---

## Exercícios práticos

### Exercício 1: Information Expert + Creator

Reescreva o código aplicando Information Expert e Creator.

```php
// Ruim: Controller faz tudo
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create([
            'user_id' => $request->user_id,
            'status' => 'pending',
        ]);

        // Controller calcula o total (não é com ele!)
        $total = 0;
        foreach ($request->items as $itemData) {
            $product = Product::find($itemData['product_id']);
            $price = $product->price;
            $quantity = $itemData['quantity'];

            // Controller cria items (não é com ele!)
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $price,
            ]);

            $total += $price * $quantity;
        }

        $order->update(['total' => $total]);

        return response()->json($order);
    }
}
```

<details>
<summary>Solução</summary>

```php
// ✅ Information Expert + Creator

// Order conhece os items → Order calcula o total (Information Expert)
class Order extends Model
{
    public function getTotalAttribute(): float
    {
        return $this->items->sum(fn($item) => $item->subtotal);
    }

    // Order contém items → Order cria os items (Creator)
    public function addItem(int $productId, int $quantity): OrderItem
    {
        $product = Product::findOrFail($productId);

        return $this->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}

// OrderItem conhece o próprio subtotal (Information Expert)
class OrderItem extends Model
{
    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

// Service coordena o processo
class OrderService
{
    public function create(int $userId, array $items): Order
    {
        return DB::transaction(function () use ($userId, $items) {
            $order = Order::create([
                'user_id' => $userId,
                'status' => 'pending',
            ]);

            foreach ($items as $item) {
                $order->addItem($item['product_id'], $item['quantity']);
            }

            // total é calculado sozinho via accessor
            return $order->fresh(['items']);
        });
    }
}

// Controller só coordena (princípio Controller)
class OrderController extends Controller
{
    public function store(Request $request, OrderService $orderService)
    {
        $order = $orderService->create(
            $request->user_id,
            $request->items
        );

        return response()->json([
            'order' => $order,
            'total' => $order->total,  // Calculado pelo Order
        ]);
    }
}

// Vantagens:
// - Order responde pela própria lógica
// - Fácil de testar
// - Código reutilizável
// - Responsabilidade clara
```
</details>

### Exercício 2: Low Coupling + Protected Variations

Refatore o código para baixar o coupling e proteger de mudanças.

```php
// Ruim: High Coupling
class OrderService
{
    public function create(array $data): Order
    {
        $order = Order::create($data);

        // Dependência direta do Stripe
        $stripe = new \Stripe\StripeClient(config('services.stripe.key'));
        $charge = $stripe->charges->create([
            'amount' => $order->total * 100,
            'currency' => 'brl',
            'source' => $data['card_token'],
        ]);

        $order->update(['payment_id' => $charge->id]);

        // Dependência direta do SMTP
        $mailer = new \Swift_Mailer(
            new \Swift_SmtpTransport('smtp.gmail.com', 587)
        );
        $message = (new \Swift_Message('Confirmação de pedido'))
            ->setFrom('noreply@example.com')
            ->setTo($order->user->email)
            ->setBody('Seu pedido foi confirmado');
        $mailer->send($message);

        return $order;
    }
}

// Problemas:
// - Não dá para trocar Stripe por PayPal
// - Não dá para trocar SMTP por outro transport
// - Difícil de testar
```

<details>
<summary>Solução</summary>

```php
// ✅ Low Coupling + Protected Variations

// Interface estável (Protected Variations)
interface PaymentGateway
{
    public function charge(float $amount, string $token): Payment;
}

class StripeGateway implements PaymentGateway
{
    public function __construct(private string $apiKey) {}

    public function charge(float $amount, string $token): Payment
    {
        $stripe = new \Stripe\StripeClient($this->apiKey);

        $charge = $stripe->charges->create([
            'amount' => $amount * 100,
            'currency' => 'brl',
            'source' => $token,
        ]);

        return new Payment(
            id: $charge->id,
            amount: $amount,
            status: $charge->status
        );
    }
}

class PayPalGateway implements PaymentGateway
{
    public function charge(float $amount, string $token): Payment
    {
        // Implementação do PayPal
    }
}

// Notification interface (Protected Variations)
interface Notifier
{
    public function send(User $user, string $message): void;
}

class EmailNotifier implements Notifier
{
    public function send(User $user, string $message): void
    {
        Mail::to($user->email)->send(new GenericEmail($message));
    }
}

class SmsNotifier implements Notifier
{
    public function send(User $user, string $message): void
    {
        // Implementação de SMS
    }
}

// Service com Low Coupling (via DI)
class OrderService
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private Notifier $notifier
    ) {}

    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);

            // Não importa qual gateway (Low Coupling)
            $payment = $this->paymentGateway->charge(
                $order->total,
                $data['card_token']
            );

            $order->update(['payment_id' => $payment->id]);

            // Não importa qual notifier (Low Coupling)
            $this->notifier->send(
                $order->user,
                "Seu pedido #{$order->id} foi confirmado"
            );

            return $order;
        });
    }
}

// Service Provider para o binding
class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Dá para trocar fácil por PayPalGateway
        $this->app->bind(PaymentGateway::class, function () {
            return new StripeGateway(config('services.stripe.key'));
        });

        // Dá para trocar fácil por SmsNotifier
        $this->app->bind(Notifier::class, EmailNotifier::class);
    }
}

// Teste (fácil!)
class OrderServiceTest extends TestCase
{
    public function test_creates_order_with_payment()
    {
        // Mock das dependências
        $gateway = Mockery::mock(PaymentGateway::class);
        $gateway->shouldReceive('charge')
            ->once()
            ->andReturn(new Payment(id: 'test_123', amount: 100, status: 'succeeded'));

        $notifier = Mockery::mock(Notifier::class);
        $notifier->shouldReceive('send')->once();

        $service = new OrderService($gateway, $notifier);

        $order = $service->create([...]);

        $this->assertEquals('test_123', $order->payment_id);
    }
}

// Vantagens:
// - Troca gateway/notifier sem dor
// - Fácil de testar
// - Protegido de mudança na API
// - Low coupling
```
</details>

### Exercício 3: High Cohesion + Polymorphism

Refatore o Fat Service aplicando High Cohesion e Polymorphism.

```php
// Ruim: Low Cohesion (tudo numa classe só)
class UserService
{
    public function register(array $data) { /* ... */ }
    public function login(string $email, string $password) { /* ... */ }
    public function logout(User $user) { /* ... */ }
    public function updateProfile(User $user, array $data) { /* ... */ }
    public function uploadAvatar(User $user, $file) { /* ... */ }
    public function deleteAccount(User $user) { /* ... */ }
    public function sendNotification(User $user, string $message, string $type)
    {
        // Ruim: switch no lugar de polymorphism
        switch ($type) {
            case 'email':
                Mail::to($user->email)->send(new GenericEmail($message));
                break;
            case 'sms':
                $this->sendSms($user->phone, $message);
                break;
            case 'push':
                $this->sendPushNotification($user->id, $message);
                break;
        }
    }
}
```

<details>
<summary>Solução</summary>

```php
// ✅ High Cohesion: separar em classes coesas

// 1. Autenticação (coesa)
class AuthService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new UserRegistered($user));

        return $user;
    }

    public function login(string $email, string $password): ?User
    {
        if (Auth::attempt(['email' => $email, 'password' => $password])) {
            return Auth::user();
        }

        return null;
    }

    public function logout(User $user): void
    {
        Auth::logout();
    }
}

// 2. Perfil (coeso)
class ProfileService
{
    public function update(User $user, array $data): User
    {
        $user->update($data);

        event(new ProfileUpdated($user));

        return $user->fresh();
    }

    public function uploadAvatar(User $user, UploadedFile $file): User
    {
        $path = $file->store('avatars', 's3');

        $user->update(['avatar' => $path]);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();

        event(new AccountDeleted($user));
    }
}

// 3. Polymorphism para notificações

// Interface
interface NotificationChannel
{
    public function send(User $user, string $message): void;
}

// Implementações
class EmailChannel implements NotificationChannel
{
    public function send(User $user, string $message): void
    {
        Mail::to($user->email)->send(new GenericEmail($message));
    }
}

class SmsChannel implements NotificationChannel
{
    public function __construct(private SmsProvider $provider) {}

    public function send(User $user, string $message): void
    {
        $this->provider->send($user->phone, $message);
    }
}

class PushChannel implements NotificationChannel
{
    public function __construct(private PushProvider $provider) {}

    public function send(User $user, string $message): void
    {
        $this->provider->send($user->id, $message);
    }
}

// Notification Service
class NotificationService
{
    private array $channels = [];

    public function addChannel(string $name, NotificationChannel $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function send(User $user, string $message, string $channelName): void
    {
        $channel = $this->channels[$channelName]
            ?? throw new InvalidArgumentException("Canal desconhecido: $channelName");

        $channel->send($user, $message);
    }

    public function broadcast(User $user, string $message, array $channels): void
    {
        foreach ($channels as $channelName) {
            $this->send($user, $message, $channelName);
        }
    }
}

// Uso
$notificationService = new NotificationService();
$notificationService->addChannel('email', new EmailChannel());
$notificationService->addChannel('sms', new SmsChannel($smsProvider));
$notificationService->addChannel('push', new PushChannel($pushProvider));

// Enviar em um canal
$notificationService->send($user, 'Olá!', 'email');

// Broadcast em vários canais
$notificationService->broadcast($user, 'Mensagem importante', ['email', 'sms', 'push']);

// Vantagens:
// - Cada classe tem uma responsabilidade (High Cohesion)
// - Fácil adicionar canal novo (Open/Closed)
// - Sem switch/if (Polymorphism)
// - Fácil testar cada componente
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
