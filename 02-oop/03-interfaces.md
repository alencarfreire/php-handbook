# 2.3 Interfaces

## Resumo

> **Interface** — contrato que define os métodos que a classe tem que implementar. Só a declaração, sem implementação.
>
> **Conceitos-chave:** implements (implementação), várias interfaces, herança de interfaces, constantes na interface.
>
> **Importante:** A classe pode implementar várias interfaces (herança aceita só um pai). Interfaces PSR para as libs falarem a mesma língua.

---

## Conteúdo

- [O que é interface](#o-que-é-interface)
- [Implementar várias interfaces](#implementar-várias-interfaces)
- [Herança de interfaces](#herança-de-interfaces)
- [Constantes em interfaces](#constantes-em-interfaces)
- [Interface vs classe abstrata](#interface-vs-classe-abstrata)
- [Interfaces PSR (padrões PHP)](#interfaces-psr-padrões-php)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é interface

**O que é:**
Contrato que define os métodos que a classe tem que implementar. Interface é só declaração, sem corpo.

**Como funciona:**
```php
interface PaymentGatewayInterface
{
    public function charge(int $amount, string $currency): bool;
    public function refund(string $transactionId, int $amount): bool;
    public function getBalance(): int;
}

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(int $amount, string $currency): bool
    {
        // Implementação do Stripe
        return true;
    }

    public function refund(string $transactionId, int $amount): bool
    {
        // Implementação do Stripe
        return true;
    }

    public function getBalance(): int
    {
        // Implementação do Stripe
        return 10000;
    }
}

class PayPalGateway implements PaymentGatewayInterface
{
    public function charge(int $amount, string $currency): bool
    {
        // Implementação do PayPal
        return true;
    }

    public function refund(string $transactionId, int $amount): bool
    {
        // Implementação do PayPal
        return true;
    }

    public function getBalance(): int
    {
        // Implementação do PayPal
        return 5000;
    }
}

// Qualquer implementação serve
function processPayment(PaymentGatewayInterface $gateway, int $amount): bool
{
    return $gateway->charge($amount, 'BRL');
}

$stripe = new StripeGateway();
$paypal = new PayPalGateway();

processPayment($stripe, 1000);  // Funciona
processPayment($paypal, 1000);  // Funciona
```

**Quando usar:**
Contrato, polimorfismo, Dependency Injection, teste (mock).

**Exemplo prático:**
```php
// Interface do Repository
interface UserRepositoryInterface
{
    public function find(int $id): ?User;
    public function all(): Collection;
    public function create(array $data): User;
    public function update(int $id, array $data): User;
    public function delete(int $id): bool;
}

// Implementação Eloquent
class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?User
    {
        return User::find($id);
    }

    public function all(): Collection
    {
        return User::all();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }
}

// Service com DI
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {}

    public function register(array $data): User
    {
        return $this->repository->create($data);
    }
}

// Laravel Service Container
app()->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

// Agora dá para trocar a implementação fácil (por exemplo, nos testes)
app()->bind(UserRepositoryInterface::class, FakeUserRepository::class);
```

**Na entrevista:**
> "Interface é contrato: só a declaração dos métodos. A classe implements a interface e é obrigada a implementar tudo. Uso para polimorfismo, DI, teste. No Laravel eu crio interface para Repository e service."

---

## Implementar várias interfaces

**O que é:**
A classe pode implementar várias interfaces (herança aceita só um pai).

**Como funciona:**
```php
interface Loggable
{
    public function log(string $message): void;
}

interface Cacheable
{
    public function cache(string $key, mixed $value): void;
    public function getCached(string $key): mixed;
}

interface Notifiable
{
    public function notify(string $message): void;
}

class UserService implements Loggable, Cacheable, Notifiable
{
    public function log(string $message): void
    {
        Log::info($message);
    }

    public function cache(string $key, mixed $value): void
    {
        Cache::put($key, $value, 3600);
    }

    public function getCached(string $key): mixed
    {
        return Cache::get($key);
    }

    public function notify(string $message): void
    {
        // Envia a notificação
    }
}

// Funciona com qualquer uma das interfaces
function logMessage(Loggable $service, string $msg): void
{
    $service->log($msg);
}

function cacheData(Cacheable $service, string $key, mixed $data): void
{
    $service->cache($key, $data);
}

$service = new UserService();
logMessage($service, 'Teste');
cacheData($service, 'user:1', ['name' => 'João']);
```

**Quando usar:**
Quando o objeto precisa de várias "habilidades" (log + cache + notificação).

**Exemplo prático:**
```php
// Laravel Contracts
interface Arrayable
{
    public function toArray(): array;
}

interface Jsonable
{
    public function toJson($options = 0): string;
}

class User extends Model implements Arrayable, Jsonable
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
}

// Dá para usar como Arrayable ou Jsonable
function convertToArray(Arrayable $object): array
{
    return $object->toArray();
}

function convertToJson(Jsonable $object): string
{
    return $object->toJson();
}

$user = User::find(1);
$array = convertToArray($user);
$json = convertToJson($user);
```

**Na entrevista:**
> "A classe implementa várias interfaces separadas por vírgula: implements A, B, C. Isso resolve herança múltipla via contrato. No Laravel o model implementa Arrayable, Jsonable."

---

## Herança de interfaces

**O que é:**
Interface pode herdar outra interface (extends).

**Como funciona:**
```php
interface Readable
{
    public function read(): string;
}

interface Writable
{
    public function write(string $data): bool;
}

// Interface herda outras interfaces
interface ReadWritable extends Readable, Writable
{
    public function readWrite(string $data): string;
}

class File implements ReadWritable
{
    public function read(): string
    {
        return file_get_contents('file.txt');
    }

    public function write(string $data): bool
    {
        return file_put_contents('file.txt', $data) !== false;
    }

    public function readWrite(string $data): string
    {
        $this->write($data);
        return $this->read();
    }
}
```

**Quando usar:**
Para hierarquia de interfaces (base → estendida).

**Exemplo prático:**
```php
// Interface base do Repository
interface RepositoryInterface
{
    public function find(int $id): ?Model;
    public function all(): Collection;
}

// Interface estendida com métodos extras
interface AdvancedRepositoryInterface extends RepositoryInterface
{
    public function findByCustomCriteria(array $criteria): Collection;
    public function paginate(int $perPage): LengthAwarePaginator;
    public function search(string $query): Collection;
}

class UserRepository implements AdvancedRepositoryInterface
{
    // Obrigado a implementar TODOS os métodos (das duas interfaces)
    public function find(int $id): ?Model { /* ... */ }
    public function all(): Collection { /* ... */ }
    public function findByCustomCriteria(array $criteria): Collection { /* ... */ }
    public function paginate(int $perPage): LengthAwarePaginator { /* ... */ }
    public function search(string $query): Collection { /* ... */ }
}

// Laravel Contracts
interface Authenticatable extends \JsonSerializable
{
    public function getAuthIdentifierName(): string;
    public function getAuthIdentifier(): mixed;
    public function getAuthPassword(): string;
    // ...
}

class User extends Model implements Authenticatable
{
    // Implementa os métodos de Authenticatable + JsonSerializable
}
```

**Na entrevista:**
> "Interface pode herdar outras via extends. A classe que implementa a interface estendida é obrigada a implementar TODOS os métodos da hierarquia. No Laravel, Authenticatable herda JsonSerializable."

---

## Constantes em interfaces

**O que é:**
Interface pode ter constante (sempre public).

**Como funciona:**
```php
interface OrderStatus
{
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
}

class Order implements OrderStatus
{
    private string $status;

    public function __construct()
    {
        $this->status = self::PENDING;
    }

    public function markAsPaid(): void
    {
        $this->status = self::PAID;
    }
}

// Acesso às constantes
echo OrderStatus::PENDING;  // "pending"

if ($order->status === OrderStatus::PAID) {
    // ...
}
```

**Quando usar:**
Constante ligada ao contrato.

**Exemplo prático:**
```php
// Códigos HTTP
interface HttpStatus
{
    public const OK = 200;
    public const CREATED = 201;
    public const NO_CONTENT = 204;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const SERVER_ERROR = 500;
}

class ApiController implements HttpStatus
{
    public function index()
    {
        return response()->json($data, self::OK);
    }

    public function store(Request $request)
    {
        $item = Item::create($request->all());
        return response()->json($item, self::CREATED);
    }

    public function destroy(int $id)
    {
        Item::destroy($id);
        return response()->json(null, self::NO_CONTENT);
    }
}

// Cache TTL
interface CacheTTL
{
    public const MINUTE = 60;
    public const HOUR = 3600;
    public const DAY = 86400;
    public const WEEK = 604800;
}

class CacheService implements CacheTTL
{
    public function rememberUser(int $userId): User
    {
        return Cache::remember("user:{$userId}", self::HOUR, function() use ($userId) {
            return User::find($userId);
        });
    }
}
```

**Na entrevista:**
> "Interface pode ter constante (sempre public). Uso para status HTTP, TTL, status de pedido. PHP 8.1 trouxe Enum — melhor para esses casos."

---

## Interface vs classe abstrata

**O que é:**
Comparação dos dois jeitos de definir contrato.

**Interface:**
```php
interface PaymentGatewayInterface
{
    public function charge(int $amount): bool;
    public function refund(string $id): bool;
}

class StripeGateway implements PaymentGatewayInterface
{
    public function charge(int $amount): bool { /* ... */ }
    public function refund(string $id): bool { /* ... */ }
}
```

**Classe abstrata:**
```php
abstract class PaymentGateway
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    // Método com implementação
    protected function log(string $message): void
    {
        Log::info($message);
    }

    // Métodos abstratos (sem implementação)
    abstract public function charge(int $amount): bool;
    abstract public function refund(string $id): bool;
}

class StripeGateway extends PaymentGateway
{
    public function charge(int $amount): bool
    {
        $this->log("Cobrando {$amount}");  // Usa o método do pai
        // Stripe API
        return true;
    }

    public function refund(string $id): bool
    {
        $this->log("Reembolsando {$id}");
        // Stripe API
        return true;
    }
}
```

**Diferenças:**

| Interface | Classe abstrata |
|-----------|-------------------|
| Só declaração de método | Pode ter implementação |
| Sem propriedade (só constante) | Pode ter propriedade |
| Dá para implementar várias | Herda só uma |
| Sem construtor | Pode ter construtor |
| Todo método é public | Métodos: public, protected, private |
| Contrato "O QUE fazer" | Classe base "COMO fazer" |

**Quando usar:**
- **Interface** — contrato (o que a classe tem que saber fazer), polimorfismo, DI
- **Classe abstrata** — lógica compartilhada (como fazer), comportamento base

**Exemplo prático:**
```php
// Interface — o contrato
interface LoggerInterface
{
    public function log(string $level, string $message): void;
}

// Classe abstrata — lógica compartilhada
abstract class BaseLogger implements LoggerInterface
{
    protected function format(string $level, string $message): string
    {
        return "[{$level}] " . now() . " - {$message}";
    }

    abstract protected function write(string $formatted): void;

    public function log(string $level, string $message): void
    {
        $formatted = $this->format($level, $message);
        $this->write($formatted);
    }
}

// Implementações concretas
class FileLogger extends BaseLogger
{
    protected function write(string $formatted): void
    {
        file_put_contents('log.txt', $formatted . PHP_EOL, FILE_APPEND);
    }
}

class DatabaseLogger extends BaseLogger
{
    protected function write(string $formatted): void
    {
        DB::table('logs')->insert(['message' => $formatted]);
    }
}

// Dá para usar pela interface
function logMessage(LoggerInterface $logger, string $message): void
{
    $logger->log('info', $message);
}

$fileLogger = new FileLogger();
$dbLogger = new DatabaseLogger();

logMessage($fileLogger, 'Teste');  // Funciona
logMessage($dbLogger, 'Teste');    // Funciona
```

**Na entrevista:**
> "Interface é contrato (O QUE fazer), só declaração. Classe abstrata é implementação base (COMO fazer), pode ter lógica. Várias interfaces, uma classe abstrata. Interface para DI, classe abstrata para reaproveitar código."

---

## Interfaces PSR (padrões PHP)

**O que é:**
Interfaces padrão PSR para as libs serem compatíveis.

**Como funciona:**
```php
// PSR-3: Logger Interface
use Psr\Log\LoggerInterface;

class MyService
{
    public function __construct(
        private LoggerInterface $logger,  // Qualquer logger PSR-3
    ) {}

    public function process(): void
    {
        $this->logger->info('Processando...');
        $this->logger->error('Erro!', ['context' => 'data']);
    }
}

// Qualquer logger PSR-3 serve
$monolog = new Monolog\Logger('app');
$service = new MyService($monolog);

// PSR-7: HTTP Message Interface
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function handleRequest(RequestInterface $request): ResponseInterface
{
    $method = $request->getMethod();
    $uri = $request->getUri();

    return new Response(200, [], 'OK');
}

// PSR-11: Container Interface
use Psr\Container\ContainerInterface;

class ServiceFactory
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function create(): MyService
    {
        return new MyService(
            $this->container->get(LoggerInterface::class)
        );
    }
}
```

**Principais interfaces PSR:**
- **PSR-3** — Logger Interface (LoggerInterface)
- **PSR-6** — Caching Interface (CacheItemPoolInterface)
- **PSR-7** — HTTP Message Interface (RequestInterface, ResponseInterface)
- **PSR-11** — Container Interface (ContainerInterface)
- **PSR-15** — HTTP Server Request Handlers (RequestHandlerInterface, MiddlewareInterface)
- **PSR-16** — Simple Cache (CacheInterface)

**Quando usar:**
Use sempre interface PSR para a lib ser compatível.

**Exemplo prático:**
```php
// Laravel usa interfaces PSR
use Illuminate\Contracts\Cache\Repository as CacheContract;  // PSR-16
use Psr\Log\LoggerInterface;  // PSR-3

class OrderService
{
    public function __construct(
        private LoggerInterface $logger,
        private CacheContract $cache,
    ) {}

    public function create(array $data): Order
    {
        $this->logger->info('Criando pedido', $data);

        $order = Order::create($data);

        $this->cache->put("order:{$order->id}", $order, 3600);

        return $order;
    }
}

// O Service Container injeta as implementações PSR sozinho
$service = app(OrderService::class);
```

**Na entrevista:**
> "Interfaces PSR são padrões PHP para as libs falarem a mesma língua. PSR-3 (Logger), PSR-7 (HTTP), PSR-11 (Container), PSR-16 (Cache). Laravel usa interface PSR no DI. Dá para trocar a implementação sem dor."

---

## Recapitulando

**O essencial:**
- Interface é contrato: só declaração de método (sem implementação)
- `implements` — a classe implementa a interface
- Dá para implementar várias: `implements A, B, C`
- Interface pode herdar outras: `extends A, B`
- Constante na interface (sempre public)
- Interface vs classe abstrata:
  - Interface — contrato (O QUE), várias
  - Abstrata — implementação (COMO), só uma
- Interfaces PSR — padrão de compatibilidade

**Importante na entrevista:**
- Interface é só declaração (classe abstrata pode ter corpo)
- Dá para implementar várias (resolve herança múltipla)
- Uso para DI, polimorfismo, teste
- Interfaces PSR (PSR-3, PSR-7, PSR-11) para compatibilidade
- No Laravel eu crio interface para Repository e service

---

## Exercícios práticos

### Exercício 1: Implemente o Repository Pattern

Crie `UserRepositoryInterface` com find, all, create, update, delete. Implemente `EloquentUserRepository` e `ArrayUserRepository`.

<details>
<summary>Solução</summary>

```php
interface UserRepositoryInterface
{
    public function find(int $id): ?array;
    public function all(): array;
    public function create(array $data): array;
    public function update(int $id, array $data): array;
    public function delete(int $id): bool;
    public function findByEmail(string $email): ?array;
}

// Implementação em array (para testes)
class ArrayUserRepository implements UserRepositoryInterface
{
    private array $users = [];
    private int $nextId = 1;

    public function find(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    public function all(): array
    {
        return array_values($this->users);
    }

    public function create(array $data): array
    {
        $user = [
            'id' => $this->nextId++,
            'name' => $data['name'],
            'email' => $data['email'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->users[$user['id']] = $user;
        return $user;
    }

    public function update(int $id, array $data): array
    {
        if (!isset($this->users[$id])) {
            throw new \Exception('User not found');
        }

        $this->users[$id] = array_merge($this->users[$id], $data);
        $this->users[$id]['updated_at'] = date('Y-m-d H:i:s');

        return $this->users[$id];
    }

    public function delete(int $id): bool
    {
        if (!isset($this->users[$id])) {
            return false;
        }

        unset($this->users[$id]);
        return true;
    }

    public function findByEmail(string $email): ?array
    {
        foreach ($this->users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }

        return null;
    }
}

// Implementação Eloquent (pseudocódigo)
class EloquentUserRepository implements UserRepositoryInterface
{
    public function find(int $id): ?array
    {
        $user = User::find($id);
        return $user ? $user->toArray() : null;
    }

    public function all(): array
    {
        return User::all()->toArray();
    }

    public function create(array $data): array
    {
        return User::create($data)->toArray();
    }

    public function update(int $id, array $data): array
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user->fresh()->toArray();
    }

    public function delete(int $id): bool
    {
        return User::destroy($id) > 0;
    }

    public function findByEmail(string $email): ?array
    {
        $user = User::where('email', $email)->first();
        return $user ? $user->toArray() : null;
    }
}

// Service com DI
class UserService
{
    public function __construct(
        private UserRepositoryInterface $repository,
    ) {}

    public function register(string $name, string $email, string $password): array
    {
        // Checa o email
        if ($this->repository->findByEmail($email)) {
            throw new \Exception('Email already exists');
        }

        return $this->repository->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
    }

    public function getUserById(int $id): array
    {
        $user = $this->repository->find($id);

        if (!$user) {
            throw new \Exception('User not found');
        }

        return $user;
    }
}

// Uso (troca a implementação fácil)
$repository = new ArrayUserRepository();
$service = new UserService($repository);

$user = $service->register('João', 'joao@email.com', 'secret');
print_r($user);

// Em produção
$repository = new EloquentUserRepository();
$service = new UserService($repository);
```
</details>

### Exercício 2: Várias interfaces e Cacheable

Crie as interfaces `Loggable`, `Cacheable`, `Notifiable`. A classe `OrderService` implementa as três.

<details>
<summary>Solução</summary>

```php
interface Loggable
{
    public function log(string $level, string $message, array $context = []): void;
}

interface Cacheable
{
    public function cache(string $key, mixed $value, int $ttl = 3600): void;
    public function getCached(string $key): mixed;
    public function clearCache(string $key): bool;
}

interface Notifiable
{
    public function notify(string $channel, string $message, array $data = []): void;
}

class OrderService implements Loggable, Cacheable, Notifiable
{
    private array $cache = [];
    private array $logs = [];
    private array $notifications = [];

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        echo "[{$level}] {$message}\n";
    }

    public function cache(string $key, mixed $value, int $ttl = 3600): void
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];
    }

    public function getCached(string $key): mixed
    {
        if (!isset($this->cache[$key])) {
            return null;
        }

        $cached = $this->cache[$key];

        if ($cached['expires_at'] < time()) {
            unset($this->cache[$key]);
            return null;
        }

        return $cached['value'];
    }

    public function clearCache(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    public function notify(string $channel, string $message, array $data = []): void
    {
        $this->notifications[] = [
            'channel' => $channel,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        echo "[{$channel}] {$message}\n";
    }

    // Lógica de negócio
    public function createOrder(array $orderData): array
    {
        $this->log('info', 'Criando pedido', ['data' => $orderData]);

        // Checa o cache
        $cacheKey = "order:draft:{$orderData['user_id']}";
        $draft = $this->getCached($cacheKey);

        if ($draft) {
            $this->log('info', 'Usando rascunho do cache');
            $orderData = array_merge($draft, $orderData);
        }

        $order = [
            'id' => rand(1000, 9999),
            'user_id' => $orderData['user_id'],
            'amount' => $orderData['amount'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Cacheia o pedido
        $this->cache("order:{$order['id']}", $order, 7200);

        // Notificação
        $this->notify('email', 'Pedido criado', ['order_id' => $order['id']]);
        $this->notify('sms', 'Seu pedido #' . $order['id'] . ' foi criado');

        $this->log('info', 'Pedido criado com sucesso', ['order_id' => $order['id']]);

        return $order;
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function getNotifications(): array
    {
        return $this->notifications;
    }
}

// Uso
$service = new OrderService();

$order = $service->createOrder([
    'user_id' => 1,
    'amount' => 1000,
]);

print_r($order);
print_r($service->getLogs());
print_r($service->getNotifications());
```
</details>

### Exercício 3: Payment Gateway com interface

Crie `PaymentGatewayInterface` e duas implementações: `StripeGateway` e `PayPalGateway`.

<details>
<summary>Solução</summary>

```php
interface PaymentGatewayInterface
{
    public function charge(int $amount, string $currency, array $metadata = []): array;
    public function refund(string $transactionId, int $amount): array;
    public function getBalance(): int;
    public function getName(): string;
}

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private string $apiKey,
    ) {}

    public function charge(int $amount, string $currency, array $metadata = []): array
    {
        // Stripe API logic
        return [
            'transaction_id' => 'stripe_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'gateway' => 'stripe',
            'fee' => (int) ($amount * 0.029 + 30), // 2.9% + 30 centavos
            'metadata' => $metadata,
        ];
    }

    public function refund(string $transactionId, int $amount): array
    {
        return [
            'refund_id' => 'refund_' . uniqid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'refunded',
            'gateway' => 'stripe',
        ];
    }

    public function getBalance(): int
    {
        // Stripe API balance
        return 1000000; // 10.000,00
    }

    public function getName(): string
    {
        return 'Stripe';
    }
}

class PayPalGateway implements PaymentGatewayInterface
{
    public function __construct(
        private string $clientId,
        private string $clientSecret,
    ) {}

    public function charge(int $amount, string $currency, array $metadata = []): array
    {
        // PayPal API logic
        return [
            'transaction_id' => 'paypal_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'completed',
            'gateway' => 'paypal',
            'fee' => (int) ($amount * 0.034 + 10), // 3.4% + 10 centavos
            'metadata' => $metadata,
        ];
    }

    public function refund(string $transactionId, int $amount): array
    {
        return [
            'refund_id' => 'paypal_refund_' . uniqid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'refunded',
            'gateway' => 'paypal',
        ];
    }

    public function getBalance(): int
    {
        // PayPal API balance
        return 500000; // 5.000,00
    }

    public function getName(): string
    {
        return 'PayPal';
    }
}

// Payment Service com DI
class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    public function processPayment(int $amount, string $currency = 'BRL'): array
    {
        echo "Processando pagamento via {$this->gateway->getName()}...\n";

        $result = $this->gateway->charge($amount, $currency, [
            'customer_id' => 123,
            'order_id' => 456,
        ]);

        echo "Pagamento {$result['status']}: {$result['transaction_id']}\n";
        echo "Taxa: " . number_format($result['fee'] / 100, 2, ',', '.') . " {$currency}\n";

        return $result;
    }

    public function processRefund(string $transactionId, int $amount): array
    {
        echo "Processando reembolso via {$this->gateway->getName()}...\n";

        $result = $this->gateway->refund($transactionId, $amount);

        echo "Reembolso {$result['status']}: {$result['refund_id']}\n";

        return $result;
    }
}

// Uso — troca o gateway fácil
$stripeGateway = new StripeGateway('sk_test_...');
$paymentService = new PaymentService($stripeGateway);

$payment = $paymentService->processPayment(100000, 'BRL'); // 1000,00 BRL
$refund = $paymentService->processRefund($payment['transaction_id'], 50000);

// Troca para PayPal
$paypalGateway = new PayPalGateway('client_id', 'client_secret');
$paymentService = new PaymentService($paypalGateway);

$payment = $paymentService->processPayment(100000, 'BRL');
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
