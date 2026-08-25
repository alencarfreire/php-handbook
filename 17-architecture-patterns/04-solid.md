# 10.4 Princípios SOLID

## Resumo

> **SOLID** — 5 princípios de design orientado a objetos.
>
> **Princípios:** S (uma responsabilidade), O (estender sem alterar), L (substituir por subclasses), I (interfaces pequenas), D (depender de abstrações).
>
> **Importante:** O Laravel aplica D sozinho via Service Container. Os padrões Repository/Service seguem SOLID.

---

## Conteúdo

- [O que é](#o-que-é)
- [Single Responsibility](#single-responsibility-uma-responsabilidade)
- [Open/Closed](#openclosed-aberto-para-extensão-fechado-para-modificação)
- [Liskov Substitution](#liskov-substitution-substituição-de-liskov)
- [Interface Segregation](#interface-segregation-segregação-de-interfaces)
- [Dependency Inversion](#dependency-inversion-inversão-de-dependências)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
SOLID — 5 princípios de design orientado a objetos. Código flexível e fácil de manter.

**Princípios:**
- **S** - Single Responsibility
- **O** - Open/Closed
- **L** - Liskov Substitution
- **I** - Interface Segregation
- **D** - Dependency Inversion

---

## Single Responsibility (uma responsabilidade)

A classe deve ter só um motivo para mudar.

```php
// ❌ RUIM: a classe faz tudo
class User
{
    public function save() { /* banco */ }
    public function sendEmail() { /* Email */ }
    public function generateReport() { /* PDF */ }
}

// ✅ BOM: responsabilidades separadas
class User { /* só dados */ }
class UserRepository { public function save(User $user) {} }
class MailService { public function sendWelcome(User $user) {} }
class ReportGenerator { public function generate(User $user) {} }
```

---

## Open/Closed (aberto para extensão, fechado para modificação)

Você estende o comportamento sem mudar o código que já existe.

```php
// ❌ RUIM: tipo novo = mudar a classe
class PaymentProcessor
{
    public function process($type, $amount)
    {
        if ($type === 'credit_card') {
            // ...
        } elseif ($type === 'paypal') {
            // ...
        }
        // Tipo novo = mudar a classe
    }
}

// ✅ BOM: tipo novo = classe nova
interface PaymentMethod
{
    public function charge(float $amount): bool;
}

class CreditCardPayment implements PaymentMethod
{
    public function charge(float $amount): bool { /* ... */ }
}

class PayPalPayment implements PaymentMethod
{
    public function charge(float $amount): bool { /* ... */ }
}

class PaymentProcessor
{
    public function process(PaymentMethod $method, float $amount)
    {
        return $method->charge($amount);
    }
}
```

---

## Liskov Substitution (substituição de Liskov)

Subclasses devem substituir a classe base sem mudar o comportamento.

```php
// ❌ RUIM: viola LSP
class Rectangle
{
    protected $width;
    protected $height;

    public function setWidth($width) { $this->width = $width; }
    public function setHeight($height) { $this->height = $height; }
    public function area() { return $this->width * $this->height; }
}

class Square extends Rectangle
{
    public function setWidth($width) {
        $this->width = $this->height = $width; // Muda o comportamento
    }
}

// ✅ BOM: classes separadas
interface Shape
{
    public function area(): float;
}

class Rectangle implements Shape
{
    public function __construct(
        private float $width,
        private float $height
    ) {}

    public function area(): float {
        return $this->width * $this->height;
    }
}

class Square implements Shape
{
    public function __construct(private float $side) {}

    public function area(): float {
        return $this->side * $this->side;
    }
}
```

---

## Interface Segregation (segregação de interfaces)

Várias interfaces específicas são melhores do que uma interface grande.

```php
// ❌ RUIM: interface grande
interface Worker
{
    public function work();
    public function eat();
    public function sleep();
}

class Robot implements Worker
{
    public function work() { /* OK */ }
    public function eat() { /* Robô não come! */ }
    public function sleep() { /* Robô não dorme! */ }
}

// ✅ BOM: interfaces pequenas
interface Workable
{
    public function work();
}

interface Eatable
{
    public function eat();
}

class Human implements Workable, Eatable
{
    public function work() { /* ... */ }
    public function eat() { /* ... */ }
}

class Robot implements Workable
{
    public function work() { /* ... */ }
}
```

---

## Dependency Inversion (inversão de dependências)

Dependa de abstrações, não de implementações concretas.

```php
// ❌ RUIM: depende da classe concreta
class OrderService
{
    private MySQLOrderRepository $repository;

    public function __construct()
    {
        $this->repository = new MySQLOrderRepository(); // Acoplamento rígido
    }
}

// ✅ BOM: depende da interface
interface OrderRepository
{
    public function save(Order $order): void;
}

class MySQLOrderRepository implements OrderRepository
{
    public function save(Order $order): void { /* ... */ }
}

class OrderService
{
    public function __construct(
        private OrderRepository $repository // Interface
    ) {}
}

// Service Container injeta a implementação
$this->app->bind(OrderRepository::class, MySQLOrderRepository::class);
```

---

## Na entrevista

> "SOLID: S — uma classe, uma responsabilidade. O — você estende por herança ou interface, sem mudar o código que já existe. L — a subclasse substitui a classe base sem quebrar o comportamento. I — interfaces pequenas e específicas. D — depende de abstração (interface), não de classe concreta. No Laravel o Service Container aplica D sozinho com DI. Repository e Service seguem SOLID."

---

## Exercícios práticos

### Exercício 1: Corrija a violação de SRP

Essa classe faz demais. Separe as responsabilidades pelo Single Responsibility.

```php
class User extends Model
{
    public function save(array $options = [])
    {
        // Salva no banco
        parent::save($options);

        // Envia email
        Mail::to($this->email)->send(new WelcomeEmail($this));

        // Log
        Log::info("User {$this->id} saved");

        // Limpa o cache
        Cache::forget("user.{$this->id}");
    }
}
```

<details>
<summary>Solução</summary>

```php
// Separamos as responsabilidades:

// 1. Model — só dados
class User extends Model
{
    protected $fillable = ['name', 'email', 'password'];
}

// 2. Repository — acesso ao banco
class UserRepository
{
    public function save(User $user): User
    {
        $user->save();
        return $user;
    }
}

// 3. MailService — envio de email
class MailService
{
    public function sendWelcome(User $user): void
    {
        Mail::to($user->email)->send(new WelcomeEmail($user));
    }
}

// 4. CacheService — cache
class CacheService
{
    public function forgetUser(int $userId): void
    {
        Cache::forget("user.{$userId}");
    }
}

// 5. Service — coordenação
class UserService
{
    public function __construct(
        private UserRepository $repository,
        private MailService $mailService,
        private CacheService $cache
    ) {}

    public function create(array $data): User
    {
        $user = new User($data);
        $this->repository->save($user);

        $this->mailService->sendWelcome($user);
        $this->cache->forgetUser($user->id);

        Log::info("User {$user->id} created");

        return $user;
    }
}
```
</details>

### Exercício 2: Aplique o princípio Open/Closed

Adicione um tipo novo de notificação sem mudar o código que já existe.

```php
class NotificationService
{
    public function send(string $type, User $user, string $message)
    {
        if ($type === 'email') {
            Mail::to($user->email)->send(new Notification($message));
        } elseif ($type === 'sms') {
            // Lógica de SMS
        }
        // Tipo novo = mudar a classe ❌
    }
}
```

<details>
<summary>Solução</summary>

```php
// 1. Criamos a interface
interface NotificationChannel
{
    public function send(User $user, string $message): void;
}

// 2. Implementação de cada canal
class EmailChannel implements NotificationChannel
{
    public function send(User $user, string $message): void
    {
        Mail::to($user->email)->send(new Notification($message));
    }
}

class SmsChannel implements NotificationChannel
{
    public function send(User $user, string $message): void
    {
        // Lógica de SMS
    }
}

// 3. Canal novo — só uma classe nova (sem mudar o que já existe!)
class PushChannel implements NotificationChannel
{
    public function send(User $user, string $message): void
    {
        // Lógica de push notification
    }
}

class SlackChannel implements NotificationChannel
{
    public function send(User $user, string $message): void
    {
        // Lógica do webhook do Slack
    }
}

// 4. Service trabalha com a interface
class NotificationService
{
    public function __construct(
        private NotificationChannel $channel
    ) {}

    public function send(User $user, string $message): void
    {
        $this->channel->send($user, $message);
    }
}

// 5. Registro no ServiceProvider
$this->app->bind(NotificationChannel::class, function ($app) {
    return match (config('notifications.default')) {
        'email' => new EmailChannel(),
        'sms' => new SmsChannel(),
        'push' => new PushChannel(),
        'slack' => new SlackChannel(),
    };
});
```
</details>

### Exercício 3: Corrija a violação de Interface Segregation

Simplifique a interface: quebre em interfaces menores.

```php
interface Animal
{
    public function walk();
    public function fly();
    public function swim();
}

class Dog implements Animal
{
    public function walk() { /* OK */ }
    public function fly() { /* Cachorro não voa! */ }
    public function swim() { /* OK */ }
}
```

<details>
<summary>Solução</summary>

```php
// Separamos em interfaces específicas:

interface Walkable
{
    public function walk(): void;
}

interface Flyable
{
    public function fly(): void;
}

interface Swimmable
{
    public function swim(): void;
}

// Cada classe implementa só as interfaces que precisa
class Dog implements Walkable, Swimmable
{
    public function walk(): void
    {
        echo "O cachorro está andando";
    }

    public function swim(): void
    {
        echo "O cachorro está nadando";
    }
}

class Bird implements Walkable, Flyable
{
    public function walk(): void
    {
        echo "O pássaro está andando";
    }

    public function fly(): void
    {
        echo "O pássaro está voando";
    }
}

class Fish implements Swimmable
{
    public function swim(): void
    {
        echo "O peixe está nadando";
    }
}

class Duck implements Walkable, Flyable, Swimmable
{
    public function walk(): void { }
    public function fly(): void { }
    public function swim(): void { }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
