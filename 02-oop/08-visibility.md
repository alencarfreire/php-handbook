# 2.8 Visibilidade (Visibility)

## Resumo

> **Visibilidade** — modificadores de acesso que definem a visibilidade de propriedades e métodos: public (em qualquer lugar), protected (classe + filhas), private (só a classe).
>
> **Conceitos-chave:** encapsulamento (esconder a implementação), propriedades readonly (PHP 8.1+), classe readonly (PHP 8.2+), constantes de classe com modificadores (PHP 7.1+).
>
> **Importante:** Encapsulamento é o princípio central de OOP. Dá para ampliar a visibilidade (protected → public), mas não dá para reduzir.

---

## Conteúdo

- [public, protected, private](#public-protected-private)
- [Visibilidade na herança](#visibilidade-na-herança)
- [Mudança de visibilidade na sobrescrita](#mudança-de-visibilidade-na-sobrescrita)
- [Propriedades readonly (PHP 8.1+)](#propriedades-readonly-php-81)
- [Classe readonly (PHP 8.2+)](#classe-readonly-php-82)
- [Constantes de classe e visibilidade](#constantes-de-classe-e-visibilidade)
- [Encapsulamento — princípio de OOP](#encapsulamento--princípio-de-oop)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## public, protected, private

**O que é:**
Modificadores de acesso que definem a visibilidade de propriedades e métodos.

**Como funciona:**
```php
class User
{
    public string $name;          // Acessível em qualquer lugar
    protected string $email;      // Acessível na classe e nas filhas
    private string $password;     // Acessível SÓ nesta classe

    public function __construct(string $name, string $email, string $password)
    {
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
    }

    public function getEmail(): string  // método public
    {
        return $this->email;
    }

    protected function hashPassword(string $password): string  // método protected
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    private function validatePassword(string $password): bool  // método private
    {
        return strlen($password) >= 8;
    }
}

$user = new User('João', 'joao@email.com', 'secret');

echo $user->name;                // ✅ OK (public)
echo $user->email;               // ❌ Error (protected)
echo $user->password;            // ❌ Error (private)

echo $user->getEmail();          // ✅ OK (método public)
$user->hashPassword('pass');     // ❌ Error (protected)
$user->validatePassword('pass'); // ❌ Error (private)
```

**Quando usar:**
- `public` — API externa da classe
- `protected` — métodos que as filhas podem usar
- `private` — implementação interna

**Exemplo prático:**
```php
class Model
{
    protected array $attributes = [];  // protected — para as filhas
    private array $original = [];      // private — só para Model

    public function getAttribute(string $key): mixed  // public API
    {
        return $this->attributes[$key] ?? null;
    }

    protected function setAttribute(string $key, mixed $value): void  // Para as filhas
    {
        $this->attributes[$key] = $value;
    }

    private function syncOriginal(): void  // Lógica interna
    {
        $this->original = $this->attributes;
    }

    public function save(): bool
    {
        // Usa o método private
        $this->syncOriginal();
        return true;
    }
}

class User extends Model
{
    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);  // ✅ OK (protected)
        // $this->syncOriginal();  // ❌ Error (private, não acessível na filha)
    }
}
```

**Na entrevista:**
> "public vale em qualquer lugar, protected na classe e nas filhas, private só na classe atual. Encapsulamento: escondo a implementação (private), abro a API (public), libero acesso para as filhas (protected)."

---

## Visibilidade na herança

**O que é:**
Como os modificadores se comportam na herança.

**Como funciona:**
```php
class Animal
{
    public string $name;
    protected int $age;
    private string $secret;

    public function getAge(): int
    {
        return $this->age;
    }

    protected function calculateYears(): int
    {
        return $this->age * 7;  // Para cachorros
    }

    private function getSecret(): string
    {
        return $this->secret;
    }
}

class Dog extends Animal
{
    public function info(): string
    {
        $info = "Nome: {$this->name}\n";       // ✅ public
        $info .= "Idade: {$this->age}\n";      // ✅ protected (acessível)
        // $info .= $this->secret;             // ❌ private (NÃO acessível)

        $info .= $this->calculateYears();      // ✅ método protected
        // $info .= $this->getSecret();        // ❌ método private

        return $info;
    }

    // Dá para sobrescrever protected
    protected function calculateYears(): int
    {
        return $this->age * 10;  // Para cachorros é diferente
    }

    // Não dá para sobrescrever private (isso é um método novo)
    private function getSecret(): string
    {
        return "Segredo do cachorro";  // Isso é um método NOVO, não é sobrescrita
    }
}
```

**Quando usar:**
- `protected` para métodos que as filhas precisam acessar
- `private` para métodos que não devem ser sobrescritos

**Exemplo prático:**
```php
// Eloquent Model
abstract class Model
{
    protected array $attributes = [];  // As filhas têm acesso

    // public — API externa
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    // protected — para sobrescrever nas filhas
    protected function castAttribute(string $key, mixed $value): mixed
    {
        // Cast de tipos
        return $value;
    }

    // private — não pode mudar nas filhas
    private function syncOriginalAttributes(): void
    {
        // Lógica crítica, não pode mudar
    }
}

class User extends Model
{
    // Sobrescreve o método protected
    protected function castAttribute(string $key, mixed $value): mixed
    {
        if ($key === 'birth_date' && is_string($value)) {
            return new \DateTime($value);
        }

        return parent::castAttribute($key, $value);
    }

    // Não dá para sobrescrever o private syncOriginalAttributes()
}
```

**Na entrevista:**
> "A filha vê public e protected, mas não vê private. protected é para método que dá para sobrescrever. private é para método que não pode mudar nas filhas."

---

## Mudança de visibilidade na sobrescrita

**O que é:**
Dá para ampliar a visibilidade (protected → public), mas não dá para reduzir (public → protected).

**Como funciona:**
```php
class Animal
{
    protected function eat(): string
    {
        return "Comendo";
    }

    public function sleep(): string
    {
        return "Dormindo";
    }
}

class Dog extends Animal
{
    // ✅ OK: amplia (protected → public)
    public function eat(): string
    {
        return "Cachorro comendo";
    }

    // ❌ Fatal error: reduz (public → protected)
    protected function sleep(): string
    {
        return "Cachorro dormindo";
    }
}

// Regra: só dá para ampliar a visibilidade
// private → protected → public (só para a direita)
```

**Quando usar:**
Raro precisar mudar visibilidade. Em geral, desenhe a API certa de primeira.

**Exemplo prático:**
```php
// Controller base
class Controller
{
    protected function authorize(string $ability, mixed $model): void
    {
        if (!Gate::allows($ability, $model)) {
            abort(403);
        }
    }
}

// Controller concreto
class PostController extends Controller
{
    // Amplia a visibilidade para usar no middleware
    public function authorize(string $ability, mixed $model): void
    {
        parent::authorize($ability, $model);
        Log::info("Checagem de autorização", ['ability' => $ability]);
    }
}

// O middleware pode chamar
Route::post('/posts/{post}', function (Post $post) {
    app(PostController::class)->authorize('update', $post);
    // ...
});
```

**Na entrevista:**
> "Dá para ampliar a visibilidade (protected → public), mas não dá para reduzir (public → protected). Isso quebra o LSP (substituição de Liskov). Desenhe a API já com a visibilidade certa."

---

## Propriedades readonly (PHP 8.1+)

**O que é:**
Propriedades que só podem ser atribuídas uma vez (no construtor ou na declaração).

**Como funciona:**
```php
class User
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $id,
    ) {}
}

$user = new User('João', 'joao@email.com', 1);

echo $user->name;  // "João" ✅
$user->name = 'Pedro';  // ❌ Error: Cannot modify readonly property

// Ou
class Post
{
    public readonly string $slug;

    public function __construct(string $title)
    {
        $this->slug = Str::slug($title);  // ✅ OK (primeira atribuição)
    }

    public function updateSlug(string $slug): void
    {
        $this->slug = $slug;  // ❌ Error (não dá para alterar)
    }
}
```

**Quando usar:**
Propriedade imutável (ID, slug, timestamps na criação), Value Objects.

**Exemplo prático:**
```php
// Value Object
class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \Exception('Currency mismatch');
        }

        // Cria um objeto novo (imutabilidade)
        return new Money($this->amount + $other->amount, $this->currency);
    }
}

$price = new Money(1000, 'BRL');
// $price->amount = 2000;  // ❌ Error (readonly)

// DTO (Data Transfer Object)
readonly class CreateUserDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}

$dto = new CreateUserDTO('João', 'joao@email.com', 'secret');
// Todas as propriedades são readonly (não dá para alterar)

// Event
class OrderCreated
{
    public function __construct(
        public readonly Order $order,
        public readonly \DateTimeImmutable $createdAt,
    ) {}
}

$event = new OrderCreated($order, new \DateTimeImmutable());
// $event->order = $anotherOrder;  // ❌ Error
```

**Na entrevista:**
> "readonly (PHP 8.1+) — a propriedade só pode ser atribuída uma vez (no construtor). Uso em Value Objects (Money), DTO, Events. Garante imutabilidade dos dados."

---

## Classe readonly (PHP 8.2+)

**O que é:**
Todas as propriedades da classe viram readonly automaticamente.

**Como funciona:**
```php
// PHP 8.2+
readonly class User
{
    public function __construct(
        public string $name,
        public string $email,
        public int $id,
    ) {}
}

// Equivale a:
class UserManual
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly int $id,
    ) {}
}

$user = new User('João', 'joao@email.com', 1);
// $user->name = 'Pedro';  // ❌ Error (todas as propriedades são readonly)
```

**Quando usar:**
Classes imutáveis (DTO, Value Objects, Events).

**Exemplo prático:**
```php
// DTO
readonly class RegisterUserRequest
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'],
            $data['email'],
            $data['password'],
        );
    }
}

$request = RegisterUserRequest::fromArray($requestData);
// Garantia: os dados não mudam

// Value Object
readonly class Email
{
    public string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email');
        }

        $this->value = strtolower($email);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

$email = new Email('JOAO@EMAIL.COM');
// $email->value = 'changed';  // ❌ Error

// Event
readonly class UserRegistered
{
    public function __construct(
        public int $userId,
        public string $email,
        public \DateTimeImmutable $timestamp,
    ) {}
}
```

**Na entrevista:**
> "readonly class (PHP 8.2+) — todas as propriedades já nascem readonly. Mais curto do que escrever readonly em cada uma. Uso em DTO, Value Objects, Events — garantia de imutabilidade."

---

## Constantes de classe e visibilidade

**O que é:**
Constantes de classe podem ter modificadores (PHP 7.1+).

**Como funciona:**
```php
class Config
{
    public const PUBLIC_CONST = 'public';
    protected const PROTECTED_CONST = 'protected';
    private const PRIVATE_CONST = 'private';

    public function getPrivateConst(): string
    {
        return self::PRIVATE_CONST;  // ✅ OK (dentro da classe)
    }
}

echo Config::PUBLIC_CONST;        // ✅ "public"
echo Config::PROTECTED_CONST;     // ❌ Error (protected)
echo Config::PRIVATE_CONST;       // ❌ Error (private)

class ExtendedConfig extends Config
{
    public function getProtectedConst(): string
    {
        return self::PROTECTED_CONST;  // ✅ OK (classe filha)
        // return self::PRIVATE_CONST;  // ❌ Error (private não acessível)
    }
}
```

**Quando usar:**
- `public` — constante usada de fora
- `protected` — constante usada na hierarquia
- `private` — constante usada só na classe

**Exemplo prático:**
```php
class Order
{
    // public — o cliente pode usar
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';

    // protected — só para as filhas
    protected const INTERNAL_STATUS_PROCESSING = 'processing';

    // private — só para Order
    private const DB_TABLE = 'orders';

    private string $status;

    public function markAsPaid(): void
    {
        $this->status = self::STATUS_PAID;
        // Usa a constante private
        DB::table(self::DB_TABLE)->update([...]);
    }
}

// O cliente pode usar public
if ($order->status === Order::STATUS_PAID) {
    // ...
}

// Status HTTP
class Response
{
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NOT_FOUND = 404;

    protected const DEFAULT_STATUS = self::HTTP_OK;

    private const HEADERS_WHITELIST = ['Content-Type', 'Authorization'];

    public function send(int $status = self::DEFAULT_STATUS): void
    {
        // Usa a constante protected
    }
}
```

**Na entrevista:**
> "Constante de classe (PHP 7.1+) pode ser public, protected, private. public para uso externo, protected para a hierarquia, private para a classe. O default é public."

---

## Encapsulamento — princípio de OOP

**O que é:**
Esconder a implementação interna e expor uma API pública.

**Como funciona:**
```php
// RUIM: quebra o encapsulamento
class UserBad
{
    public string $name;
    public string $email;
    public string $password;  // Acesso aberto à senha! ❌
}

$user = new UserBad();
$user->password = 'plain_password';  // Guarda em texto puro ❌
echo $user->password;  // Dá para ler ❌

// BOM: encapsulamento
class User
{
    private string $name;
    private string $email;
    private string $passwordHash;  // Hash, não a senha

    public function __construct(string $name, string $email, string $password)
    {
        $this->name = $name;
        $this->email = $email;
        $this->setPassword($password);  // Usa o método
    }

    // Getters (acesso controlado)
    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    // A senha não sai para fora!
    public function setPassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Password too short');
        }

        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }
}

$user = new User('João', 'joao@email.com', 'secret123');
// $user->password = 'plain';  // ❌ Não dá (private)
echo $user->getName();  // ✅ OK (via getter)
$user->checkPassword('secret123');  // ✅ OK
```

**Quando usar:**
**Sempre**. Esconda a implementação, abra só a API.

**Exemplo prático:**
```php
// Eloquent Model (encapsula $attributes)
class User extends Model
{
    private array $attributes = [];  // Escondido

    // Acesso controlado
    public function __get(string $key): mixed
    {
        // Dá para colocar lógica (cast, mutators)
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        // Validação, transformação
        $this->attributes[$key] = $value;
    }

    protected function castAttribute(string $key): mixed
    {
        // Cast de tipos
    }
}

// Payment Service (encapsula as API keys)
class PaymentService
{
    private const API_KEY = 'secret_key';  // Escondido
    private string $apiUrl;

    public function __construct(string $apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    // API pública
    public function charge(int $amount): bool
    {
        return $this->sendRequest('/charge', ['amount' => $amount]);
    }

    // Implementação interna (escondida)
    private function sendRequest(string $endpoint, array $data): bool
    {
        // Usa self::API_KEY
        // O cliente não vê o detalhe da implementação
        return true;
    }
}

$service = new PaymentService('https://api.example.com');
$service->charge(1000);  // API simples
// $service->sendRequest();  // ❌ Não dá (private)
```

**Na entrevista:**
> "Encapsulamento é esconder a implementação e expor uma API pública. Propriedade private, acesso via getter/setter. Escondo detalhe (API keys, algoritmo), abro só o que o cliente precisa."

---

## Recapitulando

**O essencial:**
- `public` — acessível em qualquer lugar (API externa)
- `protected` — acessível na classe e nas filhas
- `private` — acessível só na classe atual
- A filha vê public e protected, mas não vê private
- Dá para ampliar a visibilidade (protected → public), mas não dá para reduzir
- `readonly` (PHP 8.1+) — a propriedade é atribuída uma vez
- `readonly class` (PHP 8.2+) — todas as propriedades são readonly
- Constantes de classe (PHP 7.1+) podem ser public/protected/private

**Encapsulamento:**
- Esconda a implementação (private)
- Abra a API (public)
- Para as filhas — protected

**Importante na entrevista:**
- Encapsulamento é o princípio central de OOP
- readonly para Value Objects, DTO, Events
- private para lógica interna, public para API
- Constantes de classe podem ter modificadores (PHP 7.1+)
- PHP 8.2: readonly class para classes imutáveis

---

## Exercícios práticos

### Exercício 1: Encapsulamento no model User

**Enunciado:** Crie a classe `User` com senha encapsulada (guarda o hash, acesso só via checkPassword).

<details>
<summary>Solução</summary>

```php
class User
{
    private string $passwordHash;
    private array $loginAttempts = [];

    public function __construct(
        private string $name,
        private string $email,
        string $password,
    ) {
        $this->setPassword($password);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setPassword(string $password): void
    {
        if (!$this->validatePassword($password)) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }

        $this->passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $this->logPasswordChange();
    }

    public function checkPassword(string $password): bool
    {
        $this->recordLoginAttempt();

        if ($this->isTooManyAttempts()) {
            throw new \Exception('Too many login attempts. Account locked.');
        }

        $isValid = password_verify($password, $this->passwordHash);

        if ($isValid) {
            $this->resetLoginAttempts();
        }

        return $isValid;
    }

    private function validatePassword(string $password): bool
    {
        return strlen($password) >= 8;
    }

    private function logPasswordChange(): void
    {
        echo "Senha alterada em " . date('Y-m-d H:i:s') . "\n";
    }

    private function recordLoginAttempt(): void
    {
        $this->loginAttempts[] = time();
    }

    private function isTooManyAttempts(): bool
    {
        // Últimas 5 tentativas nos últimos 15 minutos
        $recent = array_filter($this->loginAttempts, fn($time) => $time > time() - 900);
        return count($recent) >= 5;
    }

    private function resetLoginAttempts(): void
    {
        $this->loginAttempts = [];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            // password NÃO entra no toArray (encapsulamento)
        ];
    }
}

// Uso
$user = new User('João', 'joao@email.com', 'secret123');

// ✅ OK
echo $user->getName();  // "João"
echo $user->getEmail(); // "joao@email.com"

// ✅ OK
if ($user->checkPassword('secret123')) {
    echo "Login ok\n";
}

// ❌ Não dá para pegar a senha direto
// echo $user->password;  // Error
// echo $user->passwordHash;  // Error (private)

// ✅ Dá para alterar pelo método (com validação)
$user->setPassword('newpassword123');
```
</details>

### Exercício 2: DTO readonly e Value Object

**Enunciado:** Crie `OrderDTO` (readonly class) e `Money` (propriedades readonly) para dados imutáveis.

<details>
<summary>Solução</summary>

```php
// PHP 8.2+ readonly class
readonly class CreateOrderDTO
{
    public function __construct(
        public int $userId,
        public array $items,
        public ?string $couponCode = null,
        public string $shippingAddress = '',
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }

        if (empty($this->items)) {
            throw new \InvalidArgumentException('Order must have at least one item');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['user_id'],
            items: $data['items'],
            couponCode: $data['coupon_code'] ?? null,
            shippingAddress: $data['shipping_address'] ?? '',
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'items' => $this->items,
            'coupon_code' => $this->couponCode,
            'shipping_address' => $this->shippingAddress,
        ];
    }
}

// PHP 8.1+ readonly properties
class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency = 'BRL',
    ) {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }

        // Cria um objeto NOVO (imutabilidade)
        return new Money($this->amount + $other->amount, $this->currency);
    }

    public function multiply(int $factor): Money
    {
        return new Money($this->amount * $factor, $this->currency);
    }

    public function format(): string
    {
        return 'R$ ' . number_format($this->amount / 100, 2, ',', '.');
    }
}

// Uso
$dto = CreateOrderDTO::fromArray([
    'user_id' => 1,
    'items' => [
        ['product_id' => 10, 'quantity' => 2],
        ['product_id' => 20, 'quantity' => 1],
    ],
    'coupon_code' => 'SAVE10',
]);

echo $dto->userId;  // 1
// $dto->userId = 2;  // ❌ Error: Cannot modify readonly property

$price = new Money(100000, 'BRL');  // R$ 1.000,00
$tax = new Money(20000, 'BRL');     // R$ 200,00

$total = $price->add($tax);  // R$ 1.200,00
echo $total->format();

// $price não mudou (imutabilidade)
echo $price->format();  // R$ 1.000,00

// $price->amount = 50000;  // ❌ Error: Cannot modify readonly property
```
</details>

### Exercício 3: Constantes de classe com modificadores

**Enunciado:** Crie a classe `OrderStatus` com constantes public, protected e private.

<details>
<summary>Solução</summary>

```php
class OrderStatus
{
    // public — acessíveis em qualquer lugar
    public const PENDING = 'pending';
    public const PAID = 'paid';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    // protected — só na hierarquia de classes
    protected const INTERNAL_PROCESSING = 'processing';
    protected const INTERNAL_REFUNDING = 'refunding';

    // private — só nesta classe
    private const DB_TABLE = 'orders';
    private const CACHE_PREFIX = 'order:';

    private string $status;

    public function __construct()
    {
        $this->status = self::PENDING;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markAsPaid(): void
    {
        if ($this->status !== self::PENDING) {
            throw new \Exception('Can only mark pending orders as paid');
        }

        $this->status = self::PAID;
        $this->logStatusChange(self::PAID);
    }

    public function ship(): void
    {
        if ($this->status !== self::PAID) {
            throw new \Exception('Can only ship paid orders');
        }

        $this->status = self::SHIPPED;
        $this->logStatusChange(self::SHIPPED);
    }

    protected function startProcessing(): void
    {
        $this->status = self::INTERNAL_PROCESSING;
        $this->logStatusChange(self::INTERNAL_PROCESSING);
    }

    private function logStatusChange(string $newStatus): void
    {
        // Usa a constante private
        $cacheKey = self::CACHE_PREFIX . $this->getId();
        echo "Status alterado para {$newStatus} (cache em {$cacheKey})\n";

        // Usa a constante private
        echo "Atualizando tabela " . self::DB_TABLE . "\n";
    }

    private function getId(): int
    {
        return 123; // Simplificado
    }

    public static function getAllStatuses(): array
    {
        return [
            self::PENDING,
            self::PAID,
            self::SHIPPED,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }
}

class PriorityOrder extends OrderStatus
{
    public function processFast(): void
    {
        // ✅ OK: constante protected acessível
        $this->startProcessing();

        // ❌ Error: constante private inacessível
        // echo self::DB_TABLE;
    }

    protected function customProcessing(): void
    {
        // ✅ OK: constante protected
        $status = self::INTERNAL_PROCESSING;
    }
}

// Uso
$order = new OrderStatus();

// ✅ constantes public acessíveis
echo "Status disponíveis:\n";
foreach (OrderStatus::getAllStatuses() as $status) {
    echo "- {$status}\n";
}

$order->markAsPaid();
// Status alterado para paid (cache em order:123)
// Atualizando tabela orders

$order->ship();
// Status alterado para shipped (cache em order:123)

// ❌ constantes protected/private inacessíveis de fora
// echo OrderStatus::INTERNAL_PROCESSING;  // Error
// echo OrderStatus::DB_TABLE;  // Error

// ✅ constantes public acessíveis
if ($order->getStatus() === OrderStatus::SHIPPED) {
    echo "Pedido enviado!\n";
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
