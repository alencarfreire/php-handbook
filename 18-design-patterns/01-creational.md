# 11.1 Padrões criacionais (Creational Patterns)

## Resumo

> **Creational Patterns** — padrões para criar objetos. Abstraem o processo de instanciação.
>
> **Principais:** Singleton (uma instância), Factory Method (cria pelo tipo), Abstract Factory (famílias de objetos), Builder (criação complexa), Prototype (clonagem).
>
> **Exemplos no Laravel:** Service Container singleton, Model Factories, Query Builder.

---

## Conteúdo

- [O que é](#o-que-é)
- [Singleton](#1-singleton)
- [Factory Method](#2-factory-method)
- [Abstract Factory](#3-abstract-factory)
- [Builder](#4-builder)
- [Prototype](#5-prototype)
- [Comparação](#comparação)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Creational Patterns:**
Padrões para criar objetos. Abstraem o processo de instanciação.

**Para quê:**
- Criar objetos com flexibilidade
- Esconder a complexidade da criação
- Reaproveitar código

**Padrões principais:**
1. Singleton
2. Factory Method
3. Abstract Factory
4. Builder
5. Prototype

---

## 1. Singleton

**O que é:**
Garante que a classe tenha uma instância só e oferece um ponto global de acesso.

**Quando usar:**
- Database connection
- Logger
- Configuration
- Cache manager

**Implementação:**

```php
class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    // Constructor private (não dá para criar de fora)
    private function __construct()
    {
        $this->connection = new PDO(
            'mysql:host=localhost;dbname=mydb',
            'user',
            'password'
        );
    }

    // Clone private (não dá para clonar)
    private function __clone() {}

    // unserialize bloqueado (não dá para desserializar)
    public function __wakeup()
    {
        throw new Exception("Não é possível desserializar um singleton");
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }

        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}

// Uso
$db1 = Database::getInstance();
$db2 = Database::getInstance();

var_dump($db1 === $db2);  // true (o mesmo objeto)
```

**Singleton no Laravel:**

```php
// Service Provider
$this->app->singleton(PaymentGateway::class, function ($app) {
    return new StripeGateway(config('stripe.key'));
});

// Em todo lugar a mesma instância
$gateway1 = app(PaymentGateway::class);
$gateway2 = app(PaymentGateway::class);

var_dump($gateway1 === $gateway2);  // true
```

**Contras do Singleton:**
- ❌ Global state (testar fica mais difícil)
- ❌ Tight coupling
- ❌ Multithreading issues

**Alternativa: Dependency Injection**

```php
// No lugar do Singleton
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway  // DI no lugar do Singleton
    ) {}
}
```

---

## 2. Factory Method

**O que é:**
Define a interface para criar objetos, mas deixa a subclasse decidir qual classe instanciar.

**Quando usar:**
- Você não sabe de antemão o tipo do objeto
- A criação fica a cargo das subclasses

**Problema sem Factory:**

```php
// Ruim: if/switch no código do cliente
$type = 'credit_card';

if ($type === 'credit_card') {
    $gateway = new CreditCardGateway();
} elseif ($type === 'paypal') {
    $gateway = new PayPalGateway();
} elseif ($type === 'crypto') {
    $gateway = new CryptoGateway();
}

$gateway->charge($amount);
```

**Solução: Factory Method**

```php
abstract class PaymentGatewayFactory
{
    abstract public function createGateway(): PaymentGateway;

    public function processPayment(int $amount): Payment
    {
        $gateway = $this->createGateway();
        return $gateway->charge($amount);
    }
}

class CreditCardGatewayFactory extends PaymentGatewayFactory
{
    public function createGateway(): PaymentGateway
    {
        return new CreditCardGateway();
    }
}

class PayPalGatewayFactory extends PaymentGatewayFactory
{
    public function createGateway(): PaymentGateway
    {
        return new PayPalGateway();
    }
}

// Uso
$factory = new CreditCardGatewayFactory();
$payment = $factory->processPayment(10000);  // R$ 100,00 em centavos
```

**Simple Factory (não é o padrão Gang of Four, mas é útil):**

```php
class PaymentGatewayFactory
{
    public static function create(string $type): PaymentGateway
    {
        return match ($type) {
            'credit_card' => new CreditCardGateway(),
            'paypal' => new PayPalGateway(),
            'crypto' => new CryptoGateway(),
            default => throw new InvalidArgumentException("Tipo desconhecido: {$type}"),
        };
    }
}

// Uso
$gateway = PaymentGatewayFactory::create('credit_card');
$gateway->charge($amount);
```

**Factory do Laravel para Models:**

```php
// database/factories/UserFactory.php
class UserFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
        ];
    }

    public function admin()
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}

// Uso
$user = User::factory()->create();
$admin = User::factory()->admin()->create();
```

---

## 3. Abstract Factory

**O que é:**
Oferece uma interface para criar famílias de objetos relacionados, sem apontar as classes concretas.

**Quando usar:**
- Você precisa criar famílias de objetos relacionados
- O sistema não pode depender de como os objetos são criados

**Exemplo: UI Components**

```php
// Abstract Factory
interface UIFactory
{
    public function createButton(): Button;
    public function createCheckbox(): Checkbox;
}

// Concrete Factories
class WindowsUIFactory implements UIFactory
{
    public function createButton(): Button
    {
        return new WindowsButton();
    }

    public function createCheckbox(): Checkbox
    {
        return new WindowsCheckbox();
    }
}

class MacUIFactory implements UIFactory
{
    public function createButton(): Button
    {
        return new MacButton();
    }

    public function createCheckbox(): Checkbox
    {
        return new MacCheckbox();
    }
}

// Products
interface Button
{
    public function render(): string;
}

class WindowsButton implements Button
{
    public function render(): string
    {
        return '<button class="windows">Clique</button>';
    }
}

class MacButton implements Button
{
    public function render(): string
    {
        return '<button class="mac">Clique</button>';
    }
}

// Client
class Application
{
    private Button $button;
    private Checkbox $checkbox;

    public function __construct(UIFactory $factory)
    {
        $this->button = $factory->createButton();
        $this->checkbox = $factory->createCheckbox();
    }

    public function render(): string
    {
        return $this->button->render() . $this->checkbox->render();
    }
}

// Uso
$os = 'windows';
$factory = $os === 'windows' ? new WindowsUIFactory() : new MacUIFactory();

$app = new Application($factory);
echo $app->render();
```

**Exemplo no Laravel: Notification Channels**

```php
interface NotificationFactory
{
    public function createEmailChannel(): EmailChannel;
    public function createSmsChannel(): SmsChannel;
}

class ProductionNotificationFactory implements NotificationFactory
{
    public function createEmailChannel(): EmailChannel
    {
        return new SmtpEmailChannel(config('mail.smtp'));
    }

    public function createSmsChannel(): SmsChannel
    {
        return new TwilioSmsChannel(config('services.twilio'));
    }
}

class TestingNotificationFactory implements NotificationFactory
{
    public function createEmailChannel(): EmailChannel
    {
        return new LogEmailChannel();  // Loga em vez de enviar
    }

    public function createSmsChannel(): SmsChannel
    {
        return new LogSmsChannel();
    }
}
```

---

## 4. Builder

**O que é:**
Separa a construção de um objeto complexo da representação dele. O mesmo processo monta representações diferentes.

**Quando usar:**
- O objeto tem muitos parâmetros opcionais
- A criação tem vários passos

**Problema sem Builder:**

```php
// Telescoping Constructor Anti-Pattern
class Pizza
{
    public function __construct(
        private string $size,
        private bool $cheese = false,
        private bool $pepperoni = false,
        private bool $bacon = false,
        private bool $mushrooms = false,
        private bool $olives = false
    ) {}
}

// Uso: difícil de ler
$pizza = new Pizza('large', true, false, true, false, true);
```

**Solução: Builder**

```php
class Pizza
{
    private string $size;
    private bool $cheese = false;
    private bool $pepperoni = false;
    private bool $bacon = false;

    private function __construct() {}

    public static function builder(): PizzaBuilder
    {
        return new PizzaBuilder();
    }

    // Getters...
}

class PizzaBuilder
{
    private Pizza $pizza;

    public function __construct()
    {
        $this->pizza = new Pizza();
    }

    public function size(string $size): self
    {
        $this->pizza->size = $size;
        return $this;
    }

    public function withCheese(): self
    {
        $this->pizza->cheese = true;
        return $this;
    }

    public function withPepperoni(): self
    {
        $this->pizza->pepperoni = true;
        return $this;
    }

    public function withBacon(): self
    {
        $this->pizza->bacon = true;
        return $this;
    }

    public function build(): Pizza
    {
        return $this->pizza;
    }
}

// Uso: fluent, fácil de ler
$pizza = Pizza::builder()
    ->size('large')
    ->withCheese()
    ->withBacon()
    ->build();
```

**Laravel Query Builder:**

```php
// Laravel Query Builder = Builder Pattern!
$users = DB::table('users')
    ->where('active', true)
    ->where('age', '>', 18)
    ->orderBy('name')
    ->limit(10)
    ->get();

// Eloquent Builder
$posts = Post::query()
    ->with('author')
    ->where('published', true)
    ->latest()
    ->paginate(20);
```

**HTTP Request Builder:**

```php
$response = Http::withHeaders([
        'X-Api-Key' => 'secret',
    ])
    ->timeout(30)
    ->retry(3, 100)
    ->post('https://api.example.com/users', [
        'name' => 'João',
    ]);
```

---

## 5. Prototype

**O que é:**
Cria objetos novos copiando (clonando) os que já existem.

**Quando usar:**
- Criar o objeto é caro (DB query, API call)
- Você precisa de vários objetos parecidos

**Implementação:**

```php
class Product
{
    public function __construct(
        public string $name,
        public int $price,  // centavos
        public array $attributes = []
    ) {}

    public function __clone()
    {
        // Deep clone para arrays/objetos
        $this->attributes = array_map(
            fn($attr) => is_object($attr) ? clone $attr : $attr,
            $this->attributes
        );
    }
}

// Criar o prototype
$prototype = new Product('Notebook', 499900, [  // R$ 4.999,00
    'brand' => 'Dell',
    'warranty' => '2 anos',
]);

// Clonar e modificar
$product1 = clone $prototype;
$product1->name = 'Notebook Gamer';
$product1->price = 749900;  // R$ 7.499,00

$product2 = clone $prototype;
$product2->name = 'Notebook Corporativo';
$product2->price = 599900;  // R$ 5.999,00
```

**Laravel Eloquent:**

```php
// Replicate = Prototype Pattern
$user = User::find(1);

$newUser = $user->replicate();
$newUser->email = 'joao@email.com';
$newUser->save();

// Replicate com relationships
$post = Post::with('tags')->find(1);
$newPost = $post->replicate();
$newPost->push();  // Salva com os relationships
```

---

## Comparação

| Pattern | Caso de uso | Exemplo no Laravel |
|---------|-------------|--------------------|
| Singleton | Uma instância | Service Container singleton |
| Factory Method | Criar pelo tipo | Model factories |
| Abstract Factory | Famílias de objetos | Notification channels |
| Builder | Criação complexa | Query Builder, HTTP Builder |
| Prototype | Clonagem | Model replicate() |

---

## Na entrevista

> "Creational Patterns são para criar objetos. Singleton: uma instância só, no Laravel é singleton() no Container. Factory Method: cria pelo tipo, Simple Factory com match. Abstract Factory: famílias de objetos relacionados. Builder: fluent interface para objeto complexo, Query Builder do Laravel é o exemplo. Prototype: clona o objeto, no Eloquent é replicate(). Factory e Builder são os mais comuns no Laravel. Singleton quase não precisa — DI é melhor. Builder quando a API tem parâmetro opcional e você quer leitura fácil."

---

## Exercícios práticos

### Exercício 1: Implemente uma Simple Factory

Crie um `PaymentFactory` com o método `create()` que devolve um payment gateway diferente conforme o tipo: `stripe`, `paypal`, `crypto`.

<details>
<summary>Solução</summary>

```php
interface PaymentGateway
{
    public function charge(int $amount): Payment;
}

class StripeGateway implements PaymentGateway
{
    public function charge(int $amount): Payment
    {
        // Lógica do Stripe
        return new Payment('stripe', $amount);
    }
}

class PayPalGateway implements PaymentGateway
{
    public function charge(int $amount): Payment
    {
        // Lógica do PayPal
        return new Payment('paypal', $amount);
    }
}

class PaymentFactory
{
    public static function create(string $type): PaymentGateway
    {
        return match ($type) {
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            'crypto' => new CryptoGateway(),
            default => throw new InvalidArgumentException("Tipo desconhecido: {$type}"),
        };
    }
}

// Uso
$gateway = PaymentFactory::create('stripe');
$payment = $gateway->charge(10000);  // R$ 100,00 em centavos
```
</details>

### Exercício 2: Implemente o Builder Pattern

Crie um `QueryBuilder` para montar SQL com os métodos `select()`, `where()`, `orderBy()`, `limit()`.

<details>
<summary>Solução</summary>

```php
class QueryBuilder
{
    private string $table;
    private array $selects = ['*'];
    private array $wheres = [];
    private ?string $orderBy = null;
    private ?int $limit = null;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function select(array $columns): self
    {
        $this->selects = $columns;
        return $this;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [$column, $operator, $value];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function toSql(): string
    {
        $sql = "SELECT " . implode(', ', $this->selects);
        $sql .= " FROM {$this->table}";

        if ($this->wheres) {
            $conditions = array_map(
                fn($w) => "{$w[0]} {$w[1]} '{$w[2]}'",
                $this->wheres
            );
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        return $sql;
    }
}

// Uso
$sql = (new QueryBuilder('users'))
    ->select(['name', 'email'])
    ->where('active', '=', 1)
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->toSql();
```
</details>

### Exercício 3: Qual é o problema do Singleton?

Por que o Singleton é considerado um antipattern? Qual é a alternativa?

<details>
<summary>Solução</summary>

**Problemas do Singleton:**

1. **Global State** — estado global mutável deixa o teste mais difícil
2. **Tight Coupling** — o código fica amarrado na classe concreta
3. **Hidden Dependencies** — a dependência não aparece no construtor
4. **Difícil de testar** — não dá para trocar por um mock
5. **Multithreading issues** — problema em ambiente com várias threads

**Alternativa: Dependency Injection**

```php
// Ruim: Singleton
class OrderService
{
    public function process(Order $order)
    {
        $gateway = PaymentGateway::getInstance();  // Dependência escondida
        $gateway->charge($order->total);
    }
}

// Bom: DI
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway  // Dependência explícita
    ) {}

    public function process(Order $order)
    {
        $this->gateway->charge($order->total);
    }
}

// No Service Container
$this->app->singleton(PaymentGateway::class, StripeGateway::class);
```

**Quando o Singleton é aceitável:**
- Log (operação simples, só escreve)
- Configuration (dado read-only)
- Connection pools (recurso compartilhado sob controle)
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
