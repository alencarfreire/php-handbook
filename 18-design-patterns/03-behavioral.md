# 11.3 Padrões comportamentais (Behavioral Patterns)

## Resumo

> **Behavioral Patterns** — padrões para comunicação entre objetos e distribuição de responsabilidade.
>
> **Principais:** Strategy (algoritmos intercambiáveis), Observer (notifica dependentes), Command (encapsula o request), Chain of Responsibility (cadeia de handlers), Template Method (esqueleto do algoritmo).
>
> **Exemplos no Laravel:** Validation rules (Strategy), Events/Listeners (Observer), Jobs/Queue (Command), Middleware (Chain of Responsibility).

---

## Conteúdo

- [O que é](#o-que-é)
- [Strategy](#1-strategy-estratégia)
- [Observer](#2-observer-observador)
- [Command](#3-command-comando)
- [Chain of Responsibility](#4-chain-of-responsibility-cadeia-de-responsabilidade)
- [Template Method](#5-template-method-método-template)
- [Iterator](#6-iterator-iterador)
- [Comparação](#comparação)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Behavioral Patterns:**
Padrões para comunicação entre objetos e distribuição de responsabilidade.

**Para quê:**
- Comunicação flexível entre objetos
- Encapsular comportamento
- Acoplamento fraco

**Padrões principais:**
1. Strategy
2. Observer
3. Command
4. Chain of Responsibility
5. Template Method
6. Iterator
7. State
8. Mediator

---

## 1. Strategy (Estratégia)

**O que é:**
Define uma família de algoritmos, encapsula cada um e deixa intercambiáveis.

**Quando usar:**
- Várias classes parecidas, só o comportamento muda
- Precisa de variantes do mesmo algoritmo
- Evitar if/switch

**Problema sem Strategy:**

```php
class OrderProcessor
{
    public function process(Order $order, string $shippingType)
    {
        // Ruim: switch para cada tipo
        switch ($shippingType) {
            case 'standard':
                $cost = 5;
                $days = 5;
                break;
            case 'express':
                $cost = 15;
                $days = 2;
                break;
            case 'overnight':
                $cost = 30;
                $days = 1;
                break;
        }

        $order->shipping_cost = $cost;
        $order->delivery_days = $days;
    }
}
```

**Solução: Strategy**

```php
interface ShippingStrategy
{
    public function calculate(Order $order): array;
}

class StandardShipping implements ShippingStrategy
{
    public function calculate(Order $order): array
    {
        return [
            'cost' => 5,
            'days' => 5,
        ];
    }
}

class ExpressShipping implements ShippingStrategy
{
    public function calculate(Order $order): array
    {
        return [
            'cost' => 15,
            'days' => 2,
        ];
    }
}

class OvernightShipping implements ShippingStrategy
{
    public function calculate(Order $order): array
    {
        return [
            'cost' => 30,
            'days' => 1,
        ];
    }
}

class OrderProcessor
{
    public function __construct(
        private ShippingStrategy $shippingStrategy
    ) {}

    public function process(Order $order): void
    {
        $shipping = $this->shippingStrategy->calculate($order);

        $order->shipping_cost = $shipping['cost'];
        $order->delivery_days = $shipping['days'];
        $order->save();
    }
}

// Uso
$strategy = new ExpressShipping();
$processor = new OrderProcessor($strategy);
$processor->process($order);
```

**Laravel Validation Rules = Strategy:**

```php
// Cada regra = uma strategy
$request->validate([
    'email' => ['required', 'email', 'unique:users'],
    'password' => ['required', 'min:8', 'confirmed'],
]);

// Custom strategy
class CustomRule implements Rule
{
    public function passes($attribute, $value)
    {
        return $value === 'valid';
    }

    public function message()
    {
        return 'O campo :attribute deve ser válido.';
    }
}
```

---

## 2. Observer (Observador)

**O que é:**
Define uma dependência um-para-muitos. Quando o estado de um objeto muda, todos os dependentes são notificados.

**Quando usar:**
- Um objeto precisa avisar outros sobre mudanças
- Objetos com acoplamento fraco
- Arquitetura event-driven

**Implementação:**

```php
interface Observer
{
    public function update(Subject $subject): void;
}

interface Subject
{
    public function attach(Observer $observer): void;
    public function detach(Observer $observer): void;
    public function notify(): void;
}

class Order implements Subject
{
    private array $observers = [];
    private string $status;

    public function attach(Observer $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(Observer $observer): void
    {
        $key = array_search($observer, $this->observers, true);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->notify();  // Notifica os observers
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}

class EmailNotificationObserver implements Observer
{
    public function update(Subject $subject): void
    {
        if ($subject instanceof Order) {
            echo "Email: status do pedido mudou para {$subject->getStatus()}\n";
        }
    }
}

class SmsNotificationObserver implements Observer
{
    public function update(Subject $subject): void
    {
        if ($subject instanceof Order) {
            echo "SMS: status do pedido mudou para {$subject->getStatus()}\n";
        }
    }
}

// Uso
$order = new Order();
$order->attach(new EmailNotificationObserver());
$order->attach(new SmsNotificationObserver());

$order->setStatus('shipped');  // Os dois observers são notificados
```

**Laravel Events = Observer Pattern:**

```php
// Event
class OrderShipped
{
    public function __construct(public Order $order) {}
}

// Observers (Listeners)
class SendShipmentNotification
{
    public function handle(OrderShipped $event)
    {
        Mail::to($event->order->user)->send(new OrderShippedEmail($event->order));
    }
}

class UpdateInventory
{
    public function handle(OrderShipped $event)
    {
        foreach ($event->order->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }
}

// Registro
Event::listen(OrderShipped::class, [
    SendShipmentNotification::class,
    UpdateInventory::class,
]);

// Trigger
event(new OrderShipped($order));  // Todos os listeners são notificados
```

---

## 3. Command (Comando)

**O que é:**
Encapsula um request como objeto. Dá para parametrizar o cliente, enfileirar ou logar.

**Quando usar:**
- Parametrizar objetos com operações
- Undo/Redo
- Fila de operações
- Log de operações

**Implementação:**

```php
interface Command
{
    public function execute(): void;
    public function undo(): void;
}

class Order
{
    public string $status = 'pending';
}

class PlaceOrderCommand implements Command
{
    private string $previousStatus;

    public function __construct(private Order $order) {}

    public function execute(): void
    {
        $this->previousStatus = $this->order->status;
        $this->order->status = 'placed';
        echo "Pedido criado\n";
    }

    public function undo(): void
    {
        $this->order->status = $this->previousStatus;
        echo "Criação do pedido desfeita\n";
    }
}

class ShipOrderCommand implements Command
{
    private string $previousStatus;

    public function __construct(private Order $order) {}

    public function execute(): void
    {
        $this->previousStatus = $this->order->status;
        $this->order->status = 'shipped';
        echo "Pedido enviado\n";
    }

    public function undo(): void
    {
        $this->order->status = $this->previousStatus;
        echo "Envio do pedido desfeito\n";
    }
}

class CommandInvoker
{
    private array $history = [];

    public function execute(Command $command): void
    {
        $command->execute();
        $this->history[] = $command;
    }

    public function undo(): void
    {
        $command = array_pop($this->history);
        if ($command) {
            $command->undo();
        }
    }
}

// Uso
$order = new Order();
$invoker = new CommandInvoker();

$invoker->execute(new PlaceOrderCommand($order));
$invoker->execute(new ShipOrderCommand($order));

$invoker->undo();  // Desfaz o shipment
$invoker->undo();  // Desfaz o placement
```

**Laravel Jobs = Command Pattern:**

```php
// Job = Command
class SendEmailJob implements ShouldQueue
{
    public function __construct(
        private User $user,
        private string $message
    ) {}

    public function handle(): void
    {
        Mail::to($this->user)->send(new GenericEmail($this->message));
    }
}

// Invoker = Queue
SendEmailJob::dispatch($user, 'Olá');

// Parametrização, fila, retry — tudo como no Command Pattern
```

---

## 4. Chain of Responsibility (Cadeia de responsabilidade)

**O que é:**
Evita acoplar o remetente ao destinatário. Vários objetos podem tratar o request.

**Quando usar:**
- Vários objetos podem tratar o request
- O handler não é conhecido de antemão
- O conjunto de handlers é dinâmico

**Implementação:**

```php
abstract class Handler
{
    private ?Handler $nextHandler = null;

    public function setNext(Handler $handler): Handler
    {
        $this->nextHandler = $handler;
        return $handler;
    }

    public function handle(Request $request): ?Response
    {
        $response = $this->process($request);

        if ($response === null && $this->nextHandler !== null) {
            return $this->nextHandler->handle($request);
        }

        return $response;
    }

    abstract protected function process(Request $request): ?Response;
}

class AuthenticationHandler extends Handler
{
    protected function process(Request $request): ?Response
    {
        if (!$request->hasToken()) {
            return new Response('Não autorizado', 401);
        }

        // Autenticado, passa adiante
        return null;
    }
}

class AuthorizationHandler extends Handler
{
    protected function process(Request $request): ?Response
    {
        if (!$request->hasPermission()) {
            return new Response('Proibido', 403);
        }

        return null;
    }
}

class ValidationHandler extends Handler
{
    protected function process(Request $request): ?Response
    {
        if (!$request->isValid()) {
            return new Response('Dados inválidos', 422);
        }

        return null;
    }
}

class ActionHandler extends Handler
{
    protected function process(Request $request): ?Response
    {
        // Lógica de negócio de fato
        return new Response('Sucesso', 200);
    }
}

// Uso: monta a cadeia
$chain = new AuthenticationHandler();
$chain->setNext(new AuthorizationHandler())
      ->setNext(new ValidationHandler())
      ->setNext(new ActionHandler());

$response = $chain->handle($request);
```

**Laravel Middleware = Chain of Responsibility:**

```php
// Cada middleware = um handler na cadeia
class Authenticate
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        return $next($request);  // Passa para o próximo
    }
}

class VerifyEmail
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return redirect('/verify-email');
        }

        return $next($request);
    }
}

// Route = a cadeia
Route::middleware(['auth', 'verified', 'throttle:60,1'])
    ->get('/dashboard', [DashboardController::class, 'index']);
```

---

## 5. Template Method (Método template)

**O que é:**
Define o esqueleto do algoritmo e deixa alguns passos para as subclasses.

**Quando usar:**
- Algoritmo comum com variação nos passos
- Evitar duplicação

**Implementação:**

```php
abstract class DataImporter
{
    // Template method (esqueleto)
    public function import(string $file): void
    {
        $data = $this->readFile($file);
        $validated = $this->validate($data);
        $transformed = $this->transform($validated);
        $this->save($transformed);
    }

    abstract protected function readFile(string $file): array;
    abstract protected function validate(array $data): array;
    abstract protected function transform(array $data): array;
    abstract protected function save(array $data): void;
}

class CsvImporter extends DataImporter
{
    protected function readFile(string $file): array
    {
        return array_map('str_getcsv', file($file));
    }

    protected function validate(array $data): array
    {
        return array_filter($data, fn($row) => count($row) === 3);
    }

    protected function transform(array $data): array
    {
        return array_map(fn($row) => [
            'name' => $row[0],
            'email' => $row[1],
            'phone' => $row[2],
        ], $data);
    }

    protected function save(array $data): void
    {
        DB::table('users')->insert($data);
    }
}

class JsonImporter extends DataImporter
{
    protected function readFile(string $file): array
    {
        return json_decode(file_get_contents($file), true);
    }

    protected function validate(array $data): array
    {
        return array_filter($data, fn($row) => isset($row['email']));
    }

    protected function transform(array $data): array
    {
        return $data;  // Já está no formato certo
    }

    protected function save(array $data): void
    {
        DB::table('users')->insert($data);
    }
}

// Uso
$importer = new CsvImporter();
$importer->import('users.csv');
```

---

## 6. Iterator (Iterador)

**O que é:**
Dá acesso sequencial aos elementos sem expor a estrutura interna.

**Laravel Collections = Iterator:**

```php
$users = User::all();  // Collection implements Iterator

foreach ($users as $user) {
    echo $user->name;
}

// Métodos do iterator
$users->each(fn($user) => $user->notify());
$users->map(fn($user) => $user->email);
$users->filter(fn($user) => $user->isActive());
```

---

## Comparação

| Pattern | Caso de uso | Exemplo no Laravel |
|---------|----------|-----------------|
| Strategy | Algoritmos intercambiáveis | Validation rules |
| Observer | Notificar objetos dependentes | Events & Listeners |
| Command | Encapsular o request | Jobs, Queue |
| Chain of Responsibility | Cadeia de handlers | Middleware |
| Template Method | Algoritmo comum, passos variam | Import classes |
| Iterator | Percorrer a coleção | Collections |

---

## Na entrevista

> "Behavioral Patterns são para comunicação entre objetos. Strategy: algoritmos intercambiáveis. Validation rules do Laravel é o exemplo. Observer: notificação um-para-muitos. Events e Listeners do Laravel. Command: encapsula o request como objeto. Jobs do Laravel na queue. Chain of Responsibility: cadeia de handlers. Middleware é o exemplo. Template Method: esqueleto do algoritmo, os passos variam. Iterator: percorre a coleção. Collections do Laravel. Strategy, Observer e Command são os mais pedidos. Middleware = Chain of Responsibility, Events = Observer, Jobs = Command."

---

## Exercícios práticos

### Exercício 1: Implemente o Strategy Pattern

Crie um sistema de desconto: `NoDiscount`, `PercentageDiscount`, `FixedDiscount`. Use o Strategy Pattern.

<details>
<summary>Solução</summary>

```php
interface DiscountStrategy
{
    public function calculate(float $amount): float;
}

class NoDiscount implements DiscountStrategy
{
    public function calculate(float $amount): float
    {
        return $amount;
    }
}

class PercentageDiscount implements DiscountStrategy
{
    public function __construct(private float $percentage) {}

    public function calculate(float $amount): float
    {
        return $amount * (1 - $this->percentage / 100);
    }
}

class FixedDiscount implements DiscountStrategy
{
    public function __construct(private float $discount) {}

    public function calculate(float $amount): float
    {
        return max(0, $amount - $this->discount);
    }
}

class Order
{
    public function __construct(
        private float $amount,
        private DiscountStrategy $discountStrategy
    ) {}

    public function getTotal(): float
    {
        return $this->discountStrategy->calculate($this->amount);
    }
}

// Uso
$order1 = new Order(1000, new PercentageDiscount(10));
echo $order1->getTotal();  // 900

$order2 = new Order(1000, new FixedDiscount(100));
echo $order2->getTotal();  // 900

$order3 = new Order(1000, new NoDiscount());
echo $order3->getTotal();  // 1000
```
</details>

### Exercício 2: Implemente o Observer Pattern

Crie um sistema de notificação em que `Order` avisa `EmailNotifier` e `SmsNotifier` quando o status muda.

<details>
<summary>Solução</summary>

```php
interface Observer
{
    public function update(string $event, mixed $data): void;
}

interface Subject
{
    public function attach(Observer $observer): void;
    public function detach(Observer $observer): void;
    public function notify(string $event, mixed $data): void;
}

class Order implements Subject
{
    private array $observers = [];
    private string $status;

    public function attach(Observer $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(Observer $observer): void
    {
        $key = array_search($observer, $this->observers, true);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    public function notify(string $event, mixed $data): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($event, $data);
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->notify('status_changed', ['status' => $status]);
    }
}

class EmailNotifier implements Observer
{
    public function update(string $event, mixed $data): void
    {
        if ($event === 'status_changed') {
            echo "Email: status do pedido mudou para {$data['status']}\n";
        }
    }
}

class SmsNotifier implements Observer
{
    public function update(string $event, mixed $data): void
    {
        if ($event === 'status_changed') {
            echo "SMS: status do pedido mudou para {$data['status']}\n";
        }
    }
}

// Uso
$order = new Order();
$order->attach(new EmailNotifier());
$order->attach(new SmsNotifier());

$order->setStatus('shipped');
// Email: status do pedido mudou para shipped
// SMS: status do pedido mudou para shipped
```
</details>

### Exercício 3: Qual a diferença entre Strategy e Template Method?

Explique a diferença e dê exemplos.

<details>
<summary>Solução</summary>

| Aspecto | Strategy | Template Method |
|--------|----------|-----------------|
| **Mecanismo** | Composição (has-a) | Herança (is-a) |
| **Mudança em runtime** | Sim, troca a strategy | Não, fica na subclasse |
| **Quantidade de objetos** | Várias strategies | Um objeto com o template |
| **Inversão de controle** | O cliente escolhe a strategy | A classe base controla o algoritmo |

**Strategy — composição, você escolhe o algoritmo:**
```php
class PaymentProcessor
{
    public function __construct(
        private PaymentStrategy $strategy  // Dá para trocar
    ) {}

    public function setStrategy(PaymentStrategy $strategy): void
    {
        $this->strategy = $strategy;
    }

    public function process(Order $order): void
    {
        $this->strategy->pay($order->total);
    }
}

$processor = new PaymentProcessor(new CreditCardStrategy());
$processor->process($order);  // Credit card

$processor->setStrategy(new PayPalStrategy());
$processor->process($order);  // PayPal
```

**Template Method — herança, esqueleto do algoritmo:**
```php
abstract class DataImporter
{
    // Template method
    public function import(string $file): void
    {
        $data = $this->readFile($file);  // Passo 1
        $validated = $this->validate($data);  // Passo 2
        $this->save($validated);  // Passo 3
    }

    abstract protected function readFile(string $file): array;
    abstract protected function validate(array $data): array;
    abstract protected function save(array $data): void;
}

class CsvImporter extends DataImporter
{
    protected function readFile(string $file): array
    {
        return array_map('str_getcsv', file($file));
    }
    // ...
}

// Algoritmo fixo: read → validate → save
$importer = new CsvImporter();
$importer->import('data.csv');
```

**Quando usar o quê:**
- **Strategy** — quando precisa de flexibilidade e trocar o algoritmo em runtime
- **Template Method** — quando o algoritmo é fixo e só os passos variam
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
