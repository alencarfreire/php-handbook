# 2.6 Métodos mágicos

## Resumo

> **Métodos mágicos** são métodos especiais com dois underscores (__). O PHP chama sozinho em situações específicas.
>
> **Os principais:** __construct (criação), __get/__set (acesso a propriedades), __call/__callStatic (chamada de métodos), __toString (conversão para string), __invoke (chamar como função), __clone (clonagem).
>
> **Importante:** Eloquent usa __get/__set para $attributes, __call para scopes. Não abuse — complica o debug.

---

## Conteúdo

- [__construct e __destruct](#__construct-e-__destruct)
- [__get, __set, __isset, __unset](#__get-__set-__isset-__unset)
- [__call e __callStatic](#__call-e-__callstatic)
- [__toString](#__tostring)
- [__invoke](#__invoke)
- [__clone](#__clone)
- [__sleep e __wakeup](#__sleep-e-__wakeup)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## __construct e __destruct

**O que é:**
`__construct` roda na criação do objeto, `__destruct` na destruição.

**Como funciona:**
```php
class User
{
    public function __construct(
        private string $name,
    ) {
        echo "User criado: {$this->name}\n";
    }

    public function __destruct()
    {
        echo "User destruído: {$this->name}\n";
    }
}

$user = new User('João');  // "User criado: João"
unset($user);              // "User destruído: João"

// __destruct roda sozinho no fim do script
$user = new User('Pedro');
// Script termina → "User destruído: Pedro"
```

**Quando usar:**
- `__construct` — inicialização, DI
- `__destruct` — limpar recurso (fechar conexão, arquivo)

**Exemplo prático:**
```php
// Database connection
class Database
{
    private ?\PDO $connection = null;

    public function __construct(
        private string $host,
        private string $dbname,
    ) {
        $this->connection = new \PDO("mysql:host={$host};dbname={$dbname}");
    }

    public function __destruct()
    {
        $this->connection = null;  // Fecha a conexão
    }
}

// File handler
class FileLogger
{
    private $handle;

    public function __construct(string $filename)
    {
        $this->handle = fopen($filename, 'a');
    }

    public function log(string $message): void
    {
        fwrite($this->handle, $message . PHP_EOL);
    }

    public function __destruct()
    {
        if ($this->handle) {
            fclose($this->handle);  // Fecha o arquivo
        }
    }
}
```

**Na entrevista:**
> "__construct roda quando o objeto nasce. __destruct quando morre (unset ou fim do script). Uso __destruct pra fechar conexão e arquivo."

---

## __get, __set, __isset, __unset

**O que é:**
Intercepta acesso a propriedade inexistente ou inacessível.

**Como funciona:**
```php
class User
{
    private array $data = [];

    // Intercepta LEITURA de propriedade inexistente
    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    // Intercepta ESCRITA em propriedade inexistente
    public function __set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    // Intercepta isset()
    public function __isset(string $name): bool
    {
        return isset($this->data[$name]);
    }

    // Intercepta unset()
    public function __unset(string $name): void
    {
        unset($this->data[$name]);
    }
}

$user = new User();
$user->name = 'João';         // __set('name', 'João')
echo $user->name;             // __get('name') → "João"
var_dump(isset($user->name)); // __isset('name') → true
unset($user->name);           // __unset('name')
```

**Quando usar:**
Propriedade dinâmica, objeto proxy, model de ORM.

**Exemplo prático:**
```php
// Eloquent Model (simplificado)
class Model
{
    protected array $attributes = [];

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }
}

$user = new User();
$user->name = 'João';
$user->email = 'joao@email.com';
echo $user->name;  // Lê de $attributes

// Lazy load de relações
class Post extends Model
{
    protected array $relations = [];

    public function __get(string $key): mixed
    {
        // Se for propriedade — devolve
        if (isset($this->attributes[$key])) {
            return $this->attributes[$key];
        }

        // Se for relação — carrega
        if (method_exists($this, $key)) {
            return $this->relations[$key] ??= $this->$key()->get();
        }

        return null;
    }
}

$post = Post::find(1);
echo $post->title;   // Propriedade (de $attributes)
echo $post->author->name;  // Relação (lazy load via __get)
```

**Na entrevista:**
> "__get intercepta leitura de propriedade inexistente, __set a escrita, __isset o isset(), __unset o unset(). Eloquent usa isso em $attributes e no lazy load das relações."

---

## __call e __callStatic

**O que é:**
Intercepta chamada de método inexistente (de instância e estático).

**Como funciona:**
```php
class Magic
{
    // Intercepta chamada de método inexistente
    public function __call(string $method, array $args): mixed
    {
        echo "Método chamado: {$method}\n";
        echo "Argumentos: " . implode(', ', $args) . "\n";
        return null;
    }

    // Intercepta chamada de método estático inexistente
    public static function __callStatic(string $method, array $args): mixed
    {
        echo "Método estático chamado: {$method}\n";
        echo "Argumentos: " . implode(', ', $args) . "\n";
        return null;
    }
}

$obj = new Magic();
$obj->nonExistent('arg1', 'arg2');
// Método chamado: nonExistent
// Argumentos: arg1, arg2

Magic::staticNonExistent('arg1', 'arg2');
// Método estático chamado: staticNonExistent
// Argumentos: arg1, arg2
```

**Quando usar:**
Método dinâmico, mapper, fluent API.

**Exemplo prático:**
```php
// Query Builder (simplificado)
class QueryBuilder
{
    protected array $wheres = [];

    // where('status', 'active') → whereStatus('active')
    public function __call(string $method, array $args): self
    {
        if (str_starts_with($method, 'where')) {
            $column = Str::snake(substr($method, 5));  // whereStatus → status
            $this->wheres[$column] = $args[0];
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    public function get(): array
    {
        // SELECT * FROM table WHERE status = 'active'
        return [];
    }
}

$query = new QueryBuilder();
$users = $query->whereStatus('active')
               ->whereDepartmentId(5)
               ->get();

// Eloquent scopes
class User extends Model
{
    public function __call(string $method, array $parameters)
    {
        // scope + método
        if (method_exists($this, 'scope' . ucfirst($method))) {
            return $this->{'scope' . ucfirst($method)}(...$parameters);
        }

        return parent::__call($method, $parameters);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

User::active()->get();  // Chama scopeActive via __callStatic

// Laravel Facade
class Cache
{
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = app('cache');  // Pega do Service Container
        return $instance->$method(...$args);
    }
}

Cache::put('key', 'value', 3600);  // Via __callStatic
```

**Na entrevista:**
> "__call intercepta método inexistente, __callStatic o estático. Eloquent usa pra scopes (whereActive), Query Builder pra where*, Facade pra proxy das chamadas."

---

## __toString

**O que é:**
Converte o objeto em string.

**Como funciona:**
```php
class User
{
    public function __construct(
        private string $name,
        private string $email,
    ) {}

    public function __toString(): string
    {
        return "{$this->name} ({$this->email})";
    }
}

$user = new User('João', 'joao@email.com');
echo $user;  // "João (joao@email.com)" (chama __toString)

// Sem __toString
$obj = new stdClass();
echo $obj;  // ❌ Error: Object of class stdClass could not be converted to string
```

**Quando usar:**
Pra imprimir objeto como string, log e debug.

**Exemplo prático:**
```php
// Value Object
class Money
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {}

    public function __toString(): string
    {
        $formatted = number_format($this->amount / 100, 2, ',', '.');
        return "R$ {$formatted}";
    }
}

$price = new Money(199900, 'BRL');
echo "Preço: {$price}";  // "Preço: R$ 1.999,00"

// Eloquent Model
class User extends Model
{
    public function __toString(): string
    {
        return $this->name;
    }
}

$user = User::find(1);
echo "Usuário: {$user}";  // "Usuário: João"

// Exception
class OrderException extends Exception
{
    public function __construct(
        string $message,
        private Order $order,
    ) {
        parent::__construct($message);
    }

    public function __toString(): string
    {
        return "OrderException: {$this->message} (Order ID: {$this->order->id})";
    }
}

try {
    throw new OrderException('Payment failed', $order);
} catch (OrderException $e) {
    echo $e;  // "OrderException: Payment failed (Order ID: 123)"
}
```

**Na entrevista:**
> "__toString converte o objeto em string. Roda no echo, no cast pra string e na concatenação. Uso em Value Object (Money), model, exception. Fica fácil de logar e imprimir."

---

## __invoke

**O que é:**
Deixa chamar o objeto como função.

**Como funciona:**
```php
class Multiplier
{
    public function __construct(
        private int $factor,
    ) {}

    public function __invoke(int $value): int
    {
        return $value * $this->factor;
    }
}

$double = new Multiplier(2);
echo $double(5);  // 10 (chama o objeto como função)

$triple = new Multiplier(3);
echo $triple(5);  // 15

// Dá pra usar como callback
$numbers = [1, 2, 3, 4, 5];
$doubled = array_map($double, $numbers);  // [2, 4, 6, 8, 10]
```

**Quando usar:**
Objeto callable, middleware, Strategy, Command.

**Exemplo prático:**
```php
// Laravel Middleware
class Authenticate
{
    public function __invoke($request, Closure $next)
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        return $next($request);
    }
}

// Route
Route::get('/dashboard', DashboardController::class)
    ->middleware(Authenticate::class);

// Strategy pattern
interface DiscountStrategy
{
    public function __invoke(int $price): int;
}

class PercentageDiscount implements DiscountStrategy
{
    public function __construct(private int $percent) {}

    public function __invoke(int $price): int
    {
        return (int) ($price * (1 - $this->percent / 100));
    }
}

class FixedDiscount implements DiscountStrategy
{
    public function __construct(private int $amount) {}

    public function __invoke(int $price): int
    {
        return max(0, $price - $this->amount);
    }
}

class PriceCalculator
{
    public function calculate(int $price, DiscountStrategy $discount): int
    {
        return $discount($price);  // Chama o objeto como função
    }
}

$calculator = new PriceCalculator();
$price = 1000;

$finalPrice = $calculator->calculate($price, new PercentageDiscount(10));
// 900

$finalPrice = $calculator->calculate($price, new FixedDiscount(100));
// 900

// Command pattern
class CreateUserCommand
{
    public function __construct(
        private array $data,
    ) {}

    public function __invoke(UserRepository $repository): User
    {
        return $repository->create($this->data);
    }
}

$command = new CreateUserCommand(['name' => 'João', 'email' => 'joao@email.com']);
$user = $command(new UserRepository());  // Executa o command
```

**Na entrevista:**
> "__invoke deixa chamar o objeto como função. O objeto vira callable. No Laravel, middleware com __invoke entra no Pipeline. Uso em Strategy, Command e objeto callable."

---

## __clone

**O que é:**
Roda quando você clona o objeto com `clone`.

**Como funciona:**
```php
class User
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}

    public function __clone()
    {
        // Clonagem profunda (clona objetos aninhados)
        $this->address = clone $this->address;

        echo "Objeto clonado\n";
    }
}

class Address
{
    public function __construct(public string $city) {}
}

$user1 = new User('João', new Address('São Paulo'));
$user2 = clone $user1;  // Chama __clone

$user2->name = 'Pedro';
$user2->address->city = 'Rio de Janeiro';

echo $user1->name;  // "João"
echo $user1->address->city;  // "São Paulo" (não mudou, por causa do clone $this->address)

// Sem __clone, objetos aninhados NÃO são clonados
class UserBad
{
    public function __construct(
        public string $name,
        public Address $address,
    ) {}
}

$user1 = new UserBad('João', new Address('São Paulo'));
$user2 = clone $user1;

$user2->address->city = 'Rio de Janeiro';
echo $user1->address->city;  // "Rio de Janeiro" (mudou! Mesma referência)
```

**Quando usar:**
Clonagem profunda (copiar objetos aninhados), criar snapshot.

**Exemplo prático:**
```php
// Cópia de um model
class Post extends Model
{
    public function __clone()
    {
        $this->id = null;  // Zera o ID (pra criar um registro novo)
        $this->slug = null;
        $this->created_at = null;
        $this->updated_at = null;

        // Limpa as relações
        $this->relations = [];
    }
}

$original = Post::find(1);
$duplicate = clone $original;
$duplicate->title = 'Cópia: ' . $original->title;
$duplicate->save();  // Cria um registro novo (ID novo)

// Snapshot pra comparar mudanças
class Order extends Model
{
    private ?Order $snapshot = null;

    public function createSnapshot(): void
    {
        $this->snapshot = clone $this;
    }

    public function getChanges(): array
    {
        if ($this->snapshot === null) {
            return [];
        }

        $changes = [];
        foreach ($this->attributes as $key => $value) {
            if ($this->snapshot->attributes[$key] !== $value) {
                $changes[$key] = [
                    'old' => $this->snapshot->attributes[$key],
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    public function __clone()
    {
        // Clonagem profunda de attributes
        $this->attributes = $this->attributes;
    }
}

$order = Order::find(1);
$order->createSnapshot();

$order->status = 'paid';
$order->amount = 2000;

$changes = $order->getChanges();
// ['status' => ['old' => 'pending', 'new' => 'paid'], 'amount' => ['old' => 1000, 'new' => 2000]]
```

**Na entrevista:**
> "__clone roda no clone. Por padrão o clone é shallow copy — objeto aninhado continua referência. No __clone eu faço deep clone: clone $this->nested. Uso pra duplicar model e criar snapshot."

---

## __sleep e __wakeup

**O que é:**
`__sleep` roda antes da serialização, `__wakeup` depois da desserialização.

**Como funciona:**
```php
class User
{
    public string $name;
    public string $email;
    private $connection;  // Recurso (não serializa)

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
        $this->connection = fopen('connection.txt', 'w');
    }

    // Antes da serialização (diz o que serializar)
    public function __sleep(): array
    {
        fclose($this->connection);  // Fecha o recurso
        return ['name', 'email'];   // Serializa só esses campos
    }

    // Depois da desserialização (restaura o estado)
    public function __wakeup(): void
    {
        $this->connection = fopen('connection.txt', 'a');  // Reabre
    }
}

$user = new User('João', 'joao@email.com');
$serialized = serialize($user);  // Chama __sleep

$restored = unserialize($serialized);  // Chama __wakeup
```

**Quando usar:**
Limpar recurso antes de serializar, restaurar estado depois de desserializar.

**Exemplo prático:**
```php
// Cache de objeto
class UserService
{
    private LoggerInterface $logger;
    private array $data;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->data = [];
    }

    public function __sleep(): array
    {
        // Não serializa o logger (recurso)
        return ['data'];
    }

    public function __wakeup(): void
    {
        // Restaura o logger do Service Container
        $this->logger = app(LoggerInterface::class);
    }
}

// Cache
$service = new UserService($logger);
Cache::put('service', serialize($service), 3600);

// Restauração
$cached = unserialize(Cache::get('service'));

// Laravel Queue (serialização do job)
class ProcessOrder implements ShouldQueue
{
    public function __construct(
        private Order $order,  // Serializa
        private LoggerInterface $logger,  // NÃO serializa
    ) {}

    public function __sleep(): array
    {
        return ['order'];  // Só o order vai pra queue
    }

    public function __wakeup(): void
    {
        $this->logger = app(LoggerInterface::class);  // Restaura
    }
}
```

**Na entrevista:**
> "__sleep roda antes do serialize() — eu digo quais propriedades serializar. __wakeup depois do unserialize() — restauro recurso (logger, connection). No Laravel Queue isso serializa o job."

---

## Recapitulando

**Os principais:**
- `__construct()` — construtor (na criação)
- `__destruct()` — destrutor (na destruição)
- `__get($name)` — leitura de propriedade inexistente
- `__set($name, $value)` — escrita em propriedade inexistente
- `__isset($name)` — isset() em propriedade inexistente
- `__unset($name)` — unset() em propriedade inexistente
- `__call($method, $args)` — chamada de método inexistente
- `__callStatic($method, $args)` — chamada de método estático inexistente
- `__toString()` — conversão para string
- `__invoke()` — chama o objeto como função (callable)
- `__clone()` — na clonagem (cópia profunda)
- `__sleep()` — antes da serialização (o que serializar)
- `__wakeup()` — depois da desserialização (restaurar)

**Importante na entrevista:**
- Eloquent usa __get/__set para $attributes, __call para scopes
- __invoke deixa o objeto callable (Middleware, Strategy, Command)
- __clone pra deep clone de objetos aninhados
- __sleep/__wakeup pra serialização (Laravel Queue)
- __toString pra imprimir fácil (Money, Exception)
- Não abuse — complica o debug

---

## Exercícios práticos

### Exercício 1: Model dinâmico com __get/__set/__isset/__unset

**Enunciado:** Crie a classe `DynamicModel`, que guarda atributos num array e dá acesso pelos métodos mágicos.

<details>
<summary>Solução</summary>

```php
class DynamicModel
{
    protected array $attributes = [];
    protected array $casts = [];

    public function __get(string $key): mixed
    {
        if (!isset($this->attributes[$key])) {
            return null;
        }

        return $this->castAttribute($key, $this->attributes[$key]);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    protected function castAttribute(string $key, mixed $value): mixed
    {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        return match($this->casts[$key]) {
            'int' => (int) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => is_string($value) ? json_decode($value, true) : $value,
            'datetime' => new \DateTime($value),
            default => $value,
        };
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}

class User extends DynamicModel
{
    protected array $casts = [
        'id' => 'int',
        'is_active' => 'bool',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}

// Uso
$user = new User();
$user->name = 'João';
$user->email = 'joao@email.com';
$user->is_active = '1';  // Vai ser convertido para bool
$user->metadata = '{"key":"value"}';  // Vai ser convertido para array

echo $user->name;  // "João"
var_dump($user->is_active);  // bool(true)
print_r($user->metadata);  // ['key' => 'value']

var_dump(isset($user->name));  // true
unset($user->name);
var_dump(isset($user->name));  // false
```
</details>

### Exercício 2: Fluent Builder com __call

**Enunciado:** Crie um `QueryBuilder` com métodos where* via __call (whereStatus, whereName etc.).

<details>
<summary>Solução</summary>

```php
class QueryBuilder
{
    protected array $wheres = [];
    protected array $orders = [];
    protected ?int $limit = null;

    public function __call(string $method, array $args): self
    {
        // whereStatus('active') -> where('status', 'active')
        if (str_starts_with($method, 'where')) {
            $column = $this->snakeCase(substr($method, 5));
            $this->wheres[$column] = $args[0];
            return $this;
        }

        // orderByName('asc') -> orderBy('name', 'asc')
        if (str_starts_with($method, 'orderBy')) {
            $column = $this->snakeCase(substr($method, 7));
            $this->orders[$column] = $args[0] ?? 'asc';
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    public function where(string $column, mixed $value): self
    {
        $this->wheres[$column] = $value;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function toSql(): string
    {
        $sql = 'SELECT * FROM table';

        if (!empty($this->wheres)) {
            $conditions = [];
            foreach ($this->wheres as $column => $value) {
                $conditions[] = "{$column} = '{$value}'";
            }
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($this->orders)) {
            $orders = [];
            foreach ($this->orders as $column => $direction) {
                $orders[] = "{$column} {$direction}";
            }
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        return $sql;
    }

    private function snakeCase(string $value): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }
}

// Uso
$query = new QueryBuilder();
$sql = $query->whereStatus('active')
             ->whereDepartmentId(5)
             ->whereIsActive(true)
             ->orderByCreatedAt('desc')
             ->limit(10)
             ->toSql();

echo $sql;
// SELECT * FROM table WHERE status = 'active' AND department_id = '5' AND is_active = '1' ORDER BY created_at desc LIMIT 10
```
</details>

### Exercício 3: Classe callable com __invoke para Middleware

**Enunciado:** Crie um `AuthMiddleware` com __invoke que checa autenticação.

<details>
<summary>Solução</summary>

```php
interface MiddlewareInterface
{
    public function __invoke(array $request, callable $next): array;
}

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ?int $userId = null,
    ) {}

    public function __invoke(array $request, callable $next): array
    {
        // Checagem de autenticação
        if ($this->userId === null) {
            return [
                'status' => 401,
                'body' => ['error' => 'Unauthorized'],
            ];
        }

        // Adiciona user_id no request
        $request['user_id'] = $this->userId;

        // Passa adiante
        return $next($request);
    }
}

class LogMiddleware implements MiddlewareInterface
{
    public function __invoke(array $request, callable $next): array
    {
        echo "[LOG] Request: " . ($request['path'] ?? '/') . "\n";

        $response = $next($request);

        echo "[LOG] Response: {$response['status']}\n";

        return $response;
    }
}

class RateLimitMiddleware implements MiddlewareInterface
{
    private static array $requests = [];
    private int $maxRequests = 5;

    public function __invoke(array $request, callable $next): array
    {
        $ip = $request['ip'] ?? '127.0.0.1';

        if (!isset(self::$requests[$ip])) {
            self::$requests[$ip] = 0;
        }

        self::$requests[$ip]++;

        if (self::$requests[$ip] > $this->maxRequests) {
            return [
                'status' => 429,
                'body' => ['error' => 'Too many requests'],
            ];
        }

        return $next($request);
    }
}

// Pipeline de middleware
class Pipeline
{
    private array $middleware = [];

    public function pipe(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function handle(array $request, callable $destination): array
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            function ($next, $middleware) {
                return fn($request) => $middleware($request, $next);
            },
            $destination
        );

        return $pipeline($request);
    }
}

// Uso
$pipeline = new Pipeline();
$pipeline->pipe(new LogMiddleware())
         ->pipe(new RateLimitMiddleware())
         ->pipe(new AuthMiddleware(userId: 123));

$request = ['path' => '/api/users', 'ip' => '192.168.1.1'];

$response = $pipeline->handle($request, function ($request) {
    // Handler final (controller)
    return [
        'status' => 200,
        'body' => ['message' => 'Success', 'user_id' => $request['user_id']],
    ];
});

print_r($response);
// [LOG] Request: /api/users
// [LOG] Response: 200
// ['status' => 200, 'body' => ['message' => 'Success', 'user_id' => 123]]
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
