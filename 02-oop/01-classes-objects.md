# 2.1 Classes e objetos

## Resumo

> Classe é o molde: dados (propriedades) e comportamento (métodos). Objeto é a instância. Conceitos-chave: __construct, public/protected/private, static, const. PHP 8.0+ Constructor Property Promotion. Objeto passa por referência. $this (objeto), self (estático), parent (pai).

---

## Conteúdo

- [Declaração de classe](#declaração-de-classe)
- [Construtor (__construct)](#construtor-__construct)
- [Modificadores de acesso](#modificadores-de-acesso-public-private-protected)
- [$this vs self vs parent](#this-vs-self-vs-parent)
- [Propriedades e métodos estáticos](#propriedades-e-métodos-estáticos)
- [Constantes de classe](#constantes-de-classe)
- [Passagem de objetos](#passagem-de-objetos-por-referência)
- [Recapitulando](#recapitulando-classes-e-objetos)
- [Exercícios práticos](#exercícios-práticos)

---

## Declaração de classe

**O que é:**
Classe é o molde para criar objetos com dados (propriedades) e comportamento (métodos).

**Como funciona:**
```php
class User
{
    // Propriedades (properties)
    public string $name;
    public string $email;
    private int $age;

    // Método
    public function greet(): string
    {
        return "Olá, {$this->name}!";
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        if ($age < 0) {
            throw new \InvalidArgumentException('Idade não pode ser negativa');
        }
        $this->age = $age;
    }
}

// Criar o objeto
$user = new User();
$user->name = 'João';
$user->email = 'joao@email.com';
$user->setAge(25);

echo $user->greet();  // "Olá, João!"
```

**Quando usar:**
Para modelar entidade (User, Post, Order), service (PaymentService), value object (Money, Email).

**Exemplo prático:**
```php
// Model Eloquent
class Post extends Model
{
    protected $fillable = ['title', 'content', 'author_id'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}

// Uso
$post = Post::find(1);
echo $post->title;
echo $post->author->name;

if ($post->isPublished()) {
    // ...
}
```

**Na entrevista:**
> "Classe é o molde do objeto. Tem propriedade (dado) e método (comportamento). $this aponta para o objeto atual. No Laravel, model (User, Post) estende Model."

---

## Construtor (__construct)

**O que é:**
Método que roda sozinho na hora que você cria o objeto.

**Como funciona:**
```php
class User
{
    private string $name;
    private string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

$user = new User('João', 'joao@email.com');
echo $user->getName();  // "João"

// Constructor Property Promotion (PHP 8.0+)
class User
{
    public function __construct(
        private string $name,
        private string $email,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}

// Mais curto e mais legível!
```

**Quando usar:**
Para inicializar propriedade obrigatória e injetar dependência.

**Exemplo prático:**
```php
// Service com Dependency Injection
class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private PaymentGateway $gateway,
        private NotificationService $notifications,
    ) {}

    public function create(array $data): Order
    {
        $order = $this->repository->create($data);
        $this->gateway->charge($order->amount);
        $this->notifications->send($order->user, 'Pedido criado');

        return $order;
    }
}

// O Service Container do Laravel injeta as dependências sozinho
$orderService = app(OrderService::class);

// Value Object
class Money
{
    public function __construct(
        private int $amount,
        private string $currency = 'BRL',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Valor não pode ser negativo');
        }
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \Exception('Moedas não coincidem');
        }

        return new Money($this->amount + $other->amount, $this->currency);
    }
}

$price = new Money(1000, 'BRL');
$discount = new Money(100, 'BRL');
$total = $price->add($discount);
```

**Na entrevista:**
> "__construct roda na hora do new. PHP 8.0 trouxe Constructor Property Promotion — sintaxe curta (private string $name nos parâmetros). Uso para DI e para inicializar o que é obrigatório."

---

## Modificadores de acesso (public, private, protected)

**O que é:**
Palavras-chave que definem a visibilidade de propriedade e método.

**Como funciona:**
```php
class User
{
    public string $name;          // Acessível em qualquer lugar
    protected string $email;      // Acessível na classe e nas filhas
    private int $age;             // Acessível SÓ nesta classe

    public function getEmail(): string  // método public
    {
        return $this->email;
    }

    protected function validateAge(int $age): bool  // método protected
    {
        return $age >= 0 && $age <= 150;
    }

    private function log(string $message): void  // método private
    {
        // Lógica interna
    }
}

$user = new User();
$user->name = 'João';              // ✅ OK (public)
$user->email = 'joao@email.com';    // ❌ Error (protected)
$user->age = 25;                   // ❌ Error (private)

echo $user->getEmail();            // ✅ OK (método public)
```

**Quando usar:**
- `public` — API da classe (métodos públicos)
- `protected` — método que a classe filha precisa
- `private` — implementação interna (encapsulamento)

**Exemplo prático:**
```php
class Model
{
    protected array $attributes = [];  // Acessível nas filhas

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    protected function performInsert(): bool  // Usado nas filhas
    {
        // Lógica de insert no banco
    }

    private function cleanAttributes(): void  // Só para Model
    {
        // Limpeza interna
    }
}

class User extends Model
{
    public function save(): bool
    {
        return $this->performInsert();  // ✅ OK (protected)
    }
}

// Payment Service
class PaymentService
{
    private const API_KEY = 'secret';  // constante private

    public function charge(int $amount): bool
    {
        return $this->sendRequest($amount);
    }

    private function sendRequest(int $amount): bool  // Implementação interna
    {
        // Usa self::API_KEY
        // Quem consome a classe não precisa conhecer este método
    }
}
```

**Na entrevista:**
> "public — acessível em qualquer lugar, protected — na classe e nas filhas, private — só na classe atual. Encapsulamento: escondo a implementação (private), abro a API (public), libero para herança (protected)."

---

## $this vs self vs parent

**O que é:**
Palavras-chave para falar com o contexto certo.

**Como funciona:**
```php
class User
{
    private string $name;
    private static int $count = 0;

    public function __construct(string $name)
    {
        $this->name = $name;       // $this — objeto atual
        self::$count++;            // self — classe atual (para estático)
    }

    public function getName(): string
    {
        return $this->name;        // $this para propriedade do objeto
    }

    public static function getCount(): int
    {
        return self::$count;       // self para propriedade estática
    }
}

// parent — classe pai
class Admin extends User
{
    public function __construct(string $name)
    {
        parent::__construct($name);  // Chama o construtor do pai
        // Lógica extra
    }

    public function greet(): string
    {
        return "Admin: " . $this->getName();  // $this para método do objeto
    }
}

$user = new User('João');
echo $user->getName();      // "João" ($this)
echo User::getCount();      // 1 (self)

$admin = new Admin('Pedro');
echo Admin::getCount();     // 2 (self)
```

**Quando usar:**
- `$this` — para acessar propriedade e método do **objeto**
- `self` — para acessar propriedade e método estático da **classe atual**
- `parent` — para chamar método da **classe pai**

**Exemplo prático:**
```php
// Eloquent Model
class Post extends Model
{
    protected static function boot()
    {
        parent::boot();  // Chama o método do pai

        static::creating(function ($post) {
            $post->slug = Str::slug($post->title);
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);  // $this para o objeto
    }

    public static function published(): Builder
    {
        return self::where('status', 'published');  // self para estático
    }
}

// Service
class CacheService
{
    private const DEFAULT_TTL = 3600;

    public function remember(string $key, callable $callback): mixed
    {
        if ($value = $this->get($key)) {
            return $value;
        }

        $value = $callback();
        $this->put($key, $value, self::DEFAULT_TTL);  // self para constante

        return $value;
    }

    private function get(string $key): mixed
    {
        return cache()->get($key);
    }

    private function put(string $key, mixed $value, int $ttl): void
    {
        cache()->put($key, $value, $ttl);
    }
}
```

**Na entrevista:**
> "$this acessa o objeto (propriedade, método). self acessa estático da classe atual. parent chama método do pai. Na herança, parent::__construct() chama o construtor do pai."

---

## Propriedades e métodos estáticos

**O que é:**
Elementos da classe que pertencem à classe, não ao objeto.

**Como funciona:**
```php
class Database
{
    private static ?Database $instance = null;  // Propriedade estática

    private function __construct() {}  // construtor private

    public static function getInstance(): Database  // Método estático
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function query(string $sql): array
    {
        // Executa a query
        return [];
    }
}

// Uso sem criar objeto
$db = Database::getInstance();
$users = $db->query('SELECT * FROM users');

// Contador de objetos
class User
{
    private static int $count = 0;

    public function __construct()
    {
        self::$count++;
    }

    public static function getCount(): int
    {
        return self::$count;
    }
}

$user1 = new User();
$user2 = new User();
echo User::getCount();  // 2
```

**Quando usar:**
Para helper, factory, Singleton, contador, constante de classe.

**Exemplo prático:**
```php
// Classe helper
class Str
{
    public static function slug(string $title): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
    }

    public static function random(int $length = 16): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}

$slug = Str::slug('Hello World');  // "hello-world"
$token = Str::random(32);

// O Laravel usa isso o tempo todo
use Illuminate\Support\Str;
$slug = Str::slug('My Post Title');

// Eloquent scope (chamada estática)
class Post extends Model
{
    public static function published(): Builder
    {
        return self::where('status', 'published');
    }
}

$posts = Post::published()->get();

// Config
class Config
{
    private static array $settings = [];

    public static function set(string $key, mixed $value): void
    {
        self::$settings[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$settings[$key] ?? $default;
    }
}

Config::set('app.name', 'Minha App');
echo Config::get('app.name');
```

**Na entrevista:**
> "Elemento estático pertence à classe, não ao objeto. Chama com ::. Uso em helper (Str::slug), factory, Singleton. No Laravel tem bastante: Str, Arr, DB."

---

## Constantes de classe

**O que é:**
Valores imutáveis que pertencem à classe.

**Como funciona:**
```php
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';

    private string $status;

    public function __construct()
    {
        $this->status = self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsPaid(): void
    {
        $this->status = self::STATUS_PAID;
    }
}

// Uso
$order = new Order();
if ($order->isPaid()) {
    // ...
}

// Acesso de fora
if ($order->status === Order::STATUS_PAID) {
    // ...
}

// Modificadores de acesso (PHP 7.1+)
class Config
{
    public const PUBLIC_KEY = 'public';      // Acessível em qualquer lugar
    protected const PROTECTED_KEY = 'prot';  // Acessível nas filhas
    private const PRIVATE_KEY = 'private';   // Só nesta classe
}
```

**Quando usar:**
Para status, tipo, modo, valor de configuração.

**Exemplo prático:**
```php
// Status do usuário
class User extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function block(): void
    {
        $this->update(['status' => self::STATUS_BLOCKED]);
    }
}

// Papéis
class Role
{
    public const ADMIN = 'admin';
    public const EDITOR = 'editor';
    public const VIEWER = 'viewer';
}

if ($user->role === Role::ADMIN) {
    // Admin
}

// Códigos HTTP
class Response
{
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_SERVER_ERROR = 500;
}

return response()->json($data, Response::HTTP_OK);

// No Laravel, prefira Enum (PHP 8.1+)
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
}

$order->status = OrderStatus::Paid;
```

**Na entrevista:**
> "Constante de classe é valor imutável com const. Dentro da classe: self::. Fora: NomeDaClasse::. Uso para status, tipo, código HTTP. PHP 8.1 trouxe Enum — melhor para status."

---

## Passagem de objetos (por referência)

**O que é:**
Em PHP, objeto passa por referência ao valor (não é copiado).

**Como funciona:**
```php
class User
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }
}

function changeName(User $user): void
{
    $user->name = 'Novo nome';  // Muda o original!
}

$user = new User('João');
changeName($user);
echo $user->name;  // "Novo nome" (mudou)

// Copiar o objeto (clone)
$user1 = new User('João');
$user2 = $user1;  // Não é cópia! As duas variáveis apontam para o mesmo objeto

$user2->name = 'Pedro';
echo $user1->name;  // "Pedro" (mudou!)

// Cópia de verdade com clone
$user1 = new User('João');
$user2 = clone $user1;  // Cópia

$user2->name = 'Pedro';
echo $user1->name;  // "João" (não mudou)
echo $user2->name;  // "Pedro"
```

**Quando usar:**
O ponto: mudar propriedade do objeto dentro da função mexe no original. Para copiar, use `clone`.

**Exemplo prático:**
```php
// Método do service altera o objeto
class UserService
{
    public function activate(User $user): void
    {
        $user->is_active = true;
        $user->activated_at = now();
        $user->save();  // Eloquent persiste a mudança
    }
}

$user = User::find(1);
$service->activate($user);
// $user agora está ativo (o objeto mudou)

// Objetos imutáveis (Value Objects)
class Money
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {}

    public function add(Money $other): Money
    {
        // Devolve um objeto NOVO, não altera o atual
        return new Money(
            $this->amount + $other->amount,
            $this->currency
        );
    }
}

$price = new Money(1000, 'BRL');
$total = $price->add(new Money(500, 'BRL'));
// $price não mudou (1000)
// $total — objeto novo (1500)

// Clone para guardar o histórico
$order = Order::find(1);
$oldOrder = clone $order;  // Guardar o snapshot

$order->status = 'paid';
$order->save();

// Dá para comparar com $oldOrder
```

**Na entrevista:**
> "Objeto passa por referência: se você muda a propriedade dentro da função, o original muda. Para copiar, uso clone. Value Object eu deixo imutável (o método devolve um objeto novo)."

---

## Recapitulando classes e objetos

**O essencial:**
- Classe é o molde, objeto é a instância
- `__construct` — construtor (PHP 8.0: Constructor Property Promotion)
- Modificadores: `public` (em qualquer lugar), `protected` (filhas), `private` (só a classe)
- `$this` (objeto), `self` (estático da classe atual), `parent` (pai)
- Elemento estático pertence à classe: `static $property`, `public static function()`
- Constante de classe: `public const STATUS = 'active'`
- Objeto passa por referência (a mudança mexe no original)

**Importante na entrevista:**
- PHP 8.0: Constructor Property Promotion (`private string $name` nos parâmetros)
- Objeto não copia na atribuição (precisa de `clone`)
- `self::` para estático, `$this->` para objeto
- Encapsulamento: escondo a implementação (private), abro a API (public)
- Value Object eu deixo imutável

---

## Exercícios práticos

### Exercício 1: Crie o Value Object Money

**Enunciado:** Crie a classe `Money` com amount e currency. Adicione o método `add()`, que soma dois valores (só a mesma moeda). Deixe a classe imutável.

<details>
<summary>Solução</summary>

```php
final class Money
{
    public function __construct(
        private int $amount,        // Em centavos
        private string $currency = 'BRL',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    // Método imutável (devolve um objeto novo)
    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }

        return new Money($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }

        return new Money($this->amount - $other->amount, $this->currency);
    }

    public function format(): string
    {
        $formatted = number_format($this->amount / 100, 2, ',', '.');
        return "R$ {$formatted}";
    }

    public function __toString(): string
    {
        return $this->format();
    }
}

// Uso
$price = new Money(199900, 'BRL');        // R$ 1.999,00
$discount = new Money(10000, 'BRL');      // R$ 100,00
$total = $price->subtract($discount);     // R$ 1.899,00

echo "Preço: {$price}";                    // Preço: R$ 1.999,00
echo "Desconto: {$discount}";              // Desconto: R$ 100,00
echo "Total: {$total}";                    // Total: R$ 1.899,00

// $price não mudou (imutabilidade)
echo $price->getAmount();                 // 199900
```
</details>

### Exercício 2: Singleton Pattern

**Enunciado:** Implemente Singleton na classe `Database` — só pode existir uma instância.

<details>
<summary>Solução</summary>

```php
class Database
{
    private static ?Database $instance = null;
    private \PDO $connection;

    // Construtor private — não dá para criar com new
    private function __construct(
        private string $host = 'localhost',
        private string $dbname = 'test',
        private string $username = 'root',
        private string $password = '',
    ) {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4";
        $this->connection = new \PDO($dsn, $this->username, $this->password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    // Bloqueia clone
    private function __clone() {}

    // Bloqueia unserialize
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    // Único jeito de pegar a instância
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
}

// Uso
$db = Database::getInstance();
$users = $db->query('SELECT * FROM users WHERE id = ?', [1]);

// $db2 é a mesma instância
$db2 = Database::getInstance();
var_dump($db === $db2);  // true

// new Database();  // ❌ Error: Constructor is private
```
</details>

### Exercício 3: Contador de objetos

**Enunciado:** Crie a classe `User` que conta quantos objetos foram criados. Adicione o método estático `getCount()`.

<details>
<summary>Solução</summary>

```php
class User
{
    private static int $count = 0;
    private static array $instances = [];

    public function __construct(
        private string $name,
        private string $email,
    ) {
        self::$count++;
        self::$instances[] = $this;
    }

    public function __destruct()
    {
        self::$count--;
    }

    public static function getCount(): int
    {
        return self::$count;
    }

    public static function getTotalCreated(): int
    {
        return count(self::$instances);
    }

    public static function getAllInstances(): array
    {
        return self::$instances;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

// Uso
echo "Contagem: " . User::getCount();  // 0

$user1 = new User('João', 'joao@email.com');
$user2 = new User('Pedro', 'pedro@email.com');
$user3 = new User('Ana', 'ana@email.com');

echo "Ativos: " . User::getCount();          // 3
echo "Total criado: " . User::getTotalCreated();  // 3

unset($user2);
echo "Ativos depois do unset: " . User::getCount();  // 2
echo "Total criado: " . User::getTotalCreated();  // 3 (não mudou)

// Pegar todas as instâncias
foreach (User::getAllInstances() as $user) {
    echo $user->getName() . "\n";
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
