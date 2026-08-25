# 2.2 Herança

## Resumo

> **Herança** — mecanismo para criar uma classe a partir de outra (`extends`). A classe filha herda propriedades e métodos do pai.
>
> **Conceitos-chave:** override de métodos, parent::__construct(), final (trava herança/override), abstract (classe e método abstratos).
>
> **Importante:** PHP só tem herança simples. Para vários comportamentos, use trait ou interface.

---

## Conteúdo

- [O que é herança](#o-que-é-herança)
- [Sobrescrita de métodos (Override)](#sobrescrita-de-métodos-override)
- [parent::__construct()](#parent__construct)
- [final (proíbe herança/override)](#final-proíbe-herançaoverride)
- [abstract (classes abstratas)](#abstract-classes-abstratas)
- [Herança múltipla (NÃO existe em PHP)](#herança-múltipla-não-existe-em-php)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é herança

**O que é:**
Mecanismo para criar uma classe a partir de outra, herdando propriedades e métodos.

**Como funciona:**
```php
class Animal
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function eat(): string
    {
        return "{$this->name} come";
    }
}

class Dog extends Animal
{
    public function bark(): string
    {
        return "{$this->name} late: Au!";
    }
}

class Cat extends Animal
{
    public function meow(): string
    {
        return "{$this->name} mia: Miau!";
    }
}

$dog = new Dog('Rex');
echo $dog->eat();   // "Rex come" (herdado de Animal)
echo $dog->bark();  // "Rex late: Au!" (método próprio)

$cat = new Cat('Mimi');
echo $cat->eat();   // "Mimi come" (herdado)
echo $cat->meow();  // "Mimi mia: Miau!"
```

**Quando usar:**
Quando as classes compartilham comportamento (relação IS-A: Dog IS-A Animal).

**Exemplo prático:**
```php
// Controller base no Laravel
class Controller
{
    protected function respondWithSuccess(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $data], $status);
    }

    protected function respondWithError(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }
}

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return $this->respondWithSuccess($users);  // Usa o método do pai
    }

    public function store(Request $request)
    {
        $validated = $request->validate([...]);

        try {
            $user = User::create($validated);
            return $this->respondWithSuccess($user, 201);
        } catch (\Exception $e) {
            return $this->respondWithError($e->getMessage(), 500);
        }
    }
}

// Eloquent Model
class Post extends Model  // Herda de Model
{
    protected $fillable = ['title', 'content'];

    // Ganha todos os métodos do Model: find(), create(), update(), delete()
    // + você pode adicionar os seus
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
```

**Na entrevista:**
> "Herança é com extends. A classe filha ganha propriedades e métodos do pai. No Laravel, model herda de Model, controller herda de Controller. Uso para relação IS-A."

---

## Sobrescrita de métodos (Override)

**O que é:**
Mudar o comportamento do método do pai na classe filha.

**Como funciona:**
```php
class Animal
{
    public function makeSound(): string
    {
        return "Algum som";
    }
}

class Dog extends Animal
{
    // Sobrescreve o método
    public function makeSound(): string
    {
        return "Au!";
    }
}

class Cat extends Animal
{
    public function makeSound(): string
    {
        return "Miau!";
    }
}

$dog = new Dog();
echo $dog->makeSound();  // "Au!" (método sobrescrito)

$cat = new Cat();
echo $cat->makeSound();  // "Miau!"
```

**Quando usar:**
Quando você precisa mudar ou estender o comportamento do método do pai.

**Exemplo prático:**
```php
// Eloquent Model com override de save()
class Post extends Model
{
    public function save(array $options = [])
    {
        // Lógica extra antes de salvar
        if (empty($this->slug)) {
            $this->slug = Str::slug($this->title);
        }

        // Chama o método do pai
        return parent::save($options);
    }
}

// API Resource com override de toArray()
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toDateTimeString(),
            // Campos extras
            'posts_count' => $this->posts->count(),
        ];
    }
}

// FormRequest com override de rules()
class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'content' => 'required',
            'category_id' => 'required|exists:categories,id',
        ];
    }

    // Você pode sobrescrever messages()
    public function messages(): array
    {
        return [
            'title.required' => 'O título é obrigatório',
            'content.required' => 'O conteúdo é obrigatório',
        ];
    }
}
```

**Na entrevista:**
> "Override é mudar o método do pai na classe filha. parent::method() chama o do pai. No Laravel eu sobrescrevo save() no model, toArray() no Resource, rules() no FormRequest."

---

## parent::__construct()

**O que é:**
Chamar o construtor da classe pai a partir da filha.

**Como funciona:**
```php
class Animal
{
    protected string $name;
    protected int $age;

    public function __construct(string $name, int $age)
    {
        $this->name = $name;
        $this->age = $age;
    }
}

class Dog extends Animal
{
    private string $breed;

    public function __construct(string $name, int $age, string $breed)
    {
        parent::__construct($name, $age);  // Chama o construtor do pai
        $this->breed = $breed;
    }

    public function getInfo(): string
    {
        return "{$this->name}, {$this->age} anos, raça: {$this->breed}";
    }
}

$dog = new Dog('Rex', 3, 'Labrador');
echo $dog->getInfo();  // "Rex, 3 anos, raça: Labrador"
```

**Quando usar:**
**Sempre** chame `parent::__construct()` se o pai tem construtor.

**Exemplo prático:**
```php
// Service com dependências da base
class BaseService
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}
}

class OrderService extends BaseService
{
    public function __construct(
        LoggerInterface $logger,
        private OrderRepository $repository,
        private PaymentGateway $gateway,
    ) {
        parent::__construct($logger);  // Passa o logger para o pai
    }

    public function create(array $data): Order
    {
        $this->logger->info('Criando pedido', $data);
        $order = $this->repository->create($data);
        $this->gateway->charge($order->amount);

        return $order;
    }
}

// Eloquent Model
class Post extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        // Inicialização extra
        $this->perPage = 20;
    }
}

// Exception
class OrderException extends Exception
{
    public function __construct(
        string $message,
        private Order $order,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}

throw new OrderException('Pagamento falhou', $order);
```

**Na entrevista:**
> "parent::__construct() chama o construtor do pai. Sempre chamo se o pai tem construtor. Primeiro inicializo o que é do pai, depois o que é meu."

---

## final (proíbe herança/override)

**O que é:**
Palavra-chave que trava herança da classe ou override do método.

**Como funciona:**
```php
// final class — não dá para herdar
final class Money
{
    public function __construct(
        private int $amount,
        private string $currency,
    ) {}

    public function getAmount(): int
    {
        return $this->amount;
    }
}

class Euro extends Money {}  // ❌ Fatal error: Cannot extend final class Money

// final method — não dá para sobrescrever
class Animal
{
    final public function getId(): int
    {
        return $this->id;
    }

    public function makeSound(): string
    {
        return "Som";
    }
}

class Dog extends Animal
{
    public function getId(): int  // ❌ Fatal error: Cannot override final method
    {
        return 123;
    }

    public function makeSound(): string  // ✅ OK (método não é final)
    {
        return "Au!";
    }
}
```

**Quando usar:**
- `final class` — para Value Objects, onde herança não faz sentido
- `final method` — para método crítico, que ninguém pode mudar

**Exemplo prático:**
```php
// Value Object — não deve ser herdado
final class Email
{
    private string $value;

    public function __construct(string $email)
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email inválido');
        }

        $this->value = $email;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

// DTO — não deve ser herdado
final class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {}
}

// Método crítico: a filha não pode sobrescrever
class Model
{
    final public function save(): bool
    {
        // Lógica crítica de persistência
        // Filhos não podem mudar
        return $this->performSave();
    }

    protected function performSave(): bool
    {
        // Filhos podem sobrescrever
        return true;
    }
}

// Laravel Middleware
final class Authenticate
{
    public function handle($request, Closure $next)
    {
        // Lógica de autenticação
        return $next($request);
    }
}
```

**Na entrevista:**
> "final class trava herança, final method trava override. Uso em Value Object (Email, Money), DTO e método crítico. No Laravel algumas classes são final — middleware, por exemplo."

---

## abstract (classes abstratas)

**O que é:**
Classe que você não instancia (só herda). Pode ter método abstrato (sem implementação).

**Como funciona:**
```php
abstract class Shape
{
    protected string $color;

    public function __construct(string $color)
    {
        $this->color = $color;
    }

    // Método abstrato (sem implementação)
    abstract public function calculateArea(): float;

    // Método comum (com implementação)
    public function getColor(): string
    {
        return $this->color;
    }
}

class Circle extends Shape
{
    public function __construct(
        string $color,
        private float $radius,
    ) {
        parent::__construct($color);
    }

    // OBRIGATÓRIO implementar
    public function calculateArea(): float
    {
        return pi() * $this->radius ** 2;
    }
}

class Rectangle extends Shape
{
    public function __construct(
        string $color,
        private float $width,
        private float $height,
    ) {
        parent::__construct($color);
    }

    public function calculateArea(): float
    {
        return $this->width * $this->height;
    }
}

// $shape = new Shape('red');  // ❌ Cannot instantiate abstract class

$circle = new Circle('red', 5);
echo $circle->calculateArea();  // 78.54

$rectangle = new Rectangle('blue', 4, 6);
echo $rectangle->calculateArea();  // 24
```

**Quando usar:**
Quando tem lógica comum, mas parte dos métodos precisa ser implementada na filha.

**Exemplo prático:**
```php
// Repository base
abstract class BaseRepository
{
    public function __construct(
        protected Model $model,
    ) {}

    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    // Cada repository implementa o próprio critério de busca
    abstract public function findByCustomCriteria(array $criteria): Collection;
}

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new User());
    }

    public function findByCustomCriteria(array $criteria): Collection
    {
        $query = $this->model->query();

        if (isset($criteria['department_id'])) {
            $query->where('department_id', $criteria['department_id']);
        }

        if (isset($criteria['is_active'])) {
            $query->where('is_active', $criteria['is_active']);
        }

        return $query->get();
    }
}

// Payment Gateway
abstract class PaymentGateway
{
    abstract public function charge(int $amount, string $currency): bool;
    abstract public function refund(string $transactionId, int $amount): bool;

    protected function logTransaction(string $type, int $amount): void
    {
        // Lógica comum de log
        Log::info("Payment {$type}: {$amount}");
    }
}

class StripeGateway extends PaymentGateway
{
    public function charge(int $amount, string $currency): bool
    {
        $this->logTransaction('charge', $amount);
        // Stripe API logic
        return true;
    }

    public function refund(string $transactionId, int $amount): bool
    {
        $this->logTransaction('refund', $amount);
        // Stripe API logic
        return true;
    }
}
```

**Na entrevista:**
> "abstract class você não instancia, só herda. Pode ter método abstract (sem corpo) — a filha é obrigada a implementar. Uso em classe base com lógica comum, quando parte do comportamento fica na filha."

---

## Herança múltipla (NÃO existe em PHP)

**O que é:**
PHP NÃO tem herança múltipla (uma classe não herda de várias).

**Como funciona:**
```php
class A {}
class B {}

class C extends A, B {}  // ❌ Syntax error

// No lugar de herança múltipla, use:

// 1. Interfaces (pode implementar várias)
interface Flyable
{
    public function fly(): string;
}

interface Swimmable
{
    public function swim(): string;
}

class Duck implements Flyable, Swimmable
{
    public function fly(): string
    {
        return "O pato voa";
    }

    public function swim(): string
    {
        return "O pato nada";
    }
}

// 2. Traits (pode usar várias)
trait Flyable
{
    public function fly(): string
    {
        return "Voa";
    }
}

trait Swimmable
{
    public function swim(): string
    {
        return "Nada";
    }
}

class Duck
{
    use Flyable, Swimmable;
}

$duck = new Duck();
echo $duck->fly();   // "Voa"
echo $duck->swim();  // "Nada"
```

**Quando usar:**
Para compor comportamento, use **trait** (mais em 2.5).

**Exemplo prático:**
```php
// Model Laravel com traits
class Post extends Model
{
    use HasFactory;      // Factories para testes
    use SoftDeletes;     // Soft delete
    use Notifiable;      // Notificações
    use HasUuid;         // UUID no lugar de ID

    // Ganha os métodos de todos os traits
}

// Vários comportamentos via traits
trait Loggable
{
    public function log(string $message): void
    {
        Log::info($message);
    }
}

trait Cacheable
{
    public function cache(string $key, mixed $value): void
    {
        Cache::put($key, $value, 3600);
    }
}

class UserService
{
    use Loggable, Cacheable;

    public function process(User $user): void
    {
        $this->log("Processando usuário {$user->id}");
        $this->cache("user:{$user->id}", $user);
    }
}
```

**Na entrevista:**
> "PHP não tem herança múltipla. No lugar: interface (várias) ou trait (várias). Trait é reuso horizontal de código, sem herança."

---

## Recapitulando

**O essencial:**
- `extends` — herda de uma classe só
- A classe filha herda propriedades e métodos do pai
- Override — muda o comportamento do método do pai
- `parent::method()` — chama o método do pai
- `parent::__construct()` — sempre chame se o pai tem construtor
- `final class` — trava herança
- `final method` — trava override
- `abstract class` — não instancia, só herda
- `abstract method` — sem implementação; a filha é obrigada a implementar
- Herança múltipla NÃO existe → use trait ou interface

**Importante na entrevista:**
- PHP tem herança simples (um pai só)
- Método `abstract` a filha é obrigada a implementar
- `final` eu uso em Value Object e método crítico
- Para compor comportamento — trait
- No Laravel: Model, Controller, Middleware herdam das classes base

---

## Exercícios práticos

### Exercício 1: Controller base com métodos comuns

**Enunciado:** Crie um `Controller` base com os métodos `success()` e `error()` para respostas de API. Depois crie um `UserController` que herda de `Controller`.

<details>
<summary>Solução</summary>

```php
abstract class Controller
{
    // Métodos comuns a todos os controllers
    protected function success(mixed $data, string $message = 'Sucesso', int $status = 200): array
    {
        return [
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'code' => $status,
        ];
    }

    protected function error(string $message, int $status = 400, ?array $errors = null): array
    {
        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $status,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return $response;
    }

    protected function paginate(array $items, int $total, int $page, int $perPage): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    // Método abstrato — cada controller implementa o seu
    abstract protected function authorize(string $action): bool;
}

class UserController extends Controller
{
    public function index(): array
    {
        if (!$this->authorize('view')) {
            return $this->error('Não autorizado', 403);
        }

        $users = [
            ['id' => 1, 'name' => 'João'],
            ['id' => 2, 'name' => 'Pedro'],
        ];

        return $this->success($users, 'Usuários listados');
    }

    public function store(array $data): array
    {
        if (!$this->authorize('create')) {
            return $this->error('Não autorizado', 403);
        }

        // Validação
        if (empty($data['name'])) {
            return $this->error('Falha na validação', 422, [
                'name' => ['O nome é obrigatório']
            ]);
        }

        $user = ['id' => 3, 'name' => $data['name']];
        return $this->success($user, 'Usuário criado', 201);
    }

    protected function authorize(string $action): bool
    {
        // Lógica de autorização dos usuários
        return true;  // Simplificado
    }
}

// Uso
$controller = new UserController();
print_r($controller->index());
// [
//   'status' => 'success',
//   'message' => 'Usuários listados',
//   'data' => [['id' => 1, 'name' => 'João'], ...]
// ]
```
</details>

### Exercício 2: Hierarquia de models com lógica comum

**Enunciado:** Crie uma classe abstrata `Model` com os métodos `save()` e `delete()`. Depois crie os filhos `Post` e `User`.

<details>
<summary>Solução</summary>

```php
abstract class Model
{
    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    abstract protected static function getTable(): string;
    abstract protected static function getFillable(): array;

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function fill(array $data): static
    {
        $fillable = static::getFillable();

        foreach ($data as $key => $value) {
            if (in_array($key, $fillable)) {
                $this->attributes[$key] = $value;
            }
        }

        return $this;
    }

    public function save(): bool
    {
        $table = static::getTable();

        if ($this->exists) {
            echo "UPDATE {$table} SET ... WHERE id = {$this->id}\n";
        } else {
            echo "INSERT INTO {$table} (...) VALUES (...)\n";
            $this->exists = true;
        }

        $this->original = $this->attributes;
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        $table = static::getTable();
        echo "DELETE FROM {$table} WHERE id = {$this->id}\n";
        $this->exists = false;

        return true;
    }

    public static function find(int $id): ?static
    {
        $table = static::getTable();
        echo "SELECT * FROM {$table} WHERE id = {$id}\n";

        $instance = new static();
        $instance->attributes = ['id' => $id];
        $instance->exists = true;
        $instance->original = $instance->attributes;

        return $instance;
    }

    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!isset($this->original[$key]) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }
}

class Post extends Model
{
    protected static function getTable(): string
    {
        return 'posts';
    }

    protected static function getFillable(): array
    {
        return ['title', 'content', 'author_id'];
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}

class User extends Model
{
    protected static function getTable(): string
    {
        return 'users';
    }

    protected static function getFillable(): array
    {
        return ['name', 'email'];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}

// Uso
$post = new Post();
$post->fill(['title' => 'Meu post', 'content' => 'Conteúdo']);
$post->save();  // INSERT INTO posts

$user = User::find(1);  // SELECT * FROM users WHERE id = 1
$user->name = 'Novo nome';
print_r($user->getDirty());  // ['name' => 'Novo nome']
$user->save();  // UPDATE users
```
</details>

### Exercício 3: Classe final e override de métodos

**Enunciado:** Crie `Shape` com o método `calculateArea()` (pode sobrescrever) e `getColor()` (final, não pode sobrescrever).

<details>
<summary>Solução</summary>

```php
abstract class Shape
{
    public function __construct(
        protected string $color,
    ) {}

    // final — a filha não pode sobrescrever
    final public function getColor(): string
    {
        return $this->color;
    }

    final public function describe(): string
    {
        return sprintf(
            "%s %s (Area: %.2f, Perimeter: %.2f)",
            ucfirst($this->color),
            static::class,
            $this->calculateArea(),
            $this->calculatePerimeter()
        );
    }

    // Métodos abstratos — a filha É OBRIGADA a implementar
    abstract public function calculateArea(): float;
    abstract public function calculatePerimeter(): float;
}

class Circle extends Shape
{
    public function __construct(
        string $color,
        private float $radius,
    ) {
        parent::__construct($color);
    }

    public function calculateArea(): float
    {
        return pi() * $this->radius ** 2;
    }

    public function calculatePerimeter(): float
    {
        return 2 * pi() * $this->radius;
    }

    // ❌ Não dá para sobrescrever método final
    // public function getColor(): string
    // {
    //     return "Circle color: {$this->color}";
    // }
}

class Rectangle extends Shape
{
    public function __construct(
        string $color,
        private float $width,
        private float $height,
    ) {
        parent::__construct($color);
    }

    public function calculateArea(): float
    {
        return $this->width * $this->height;
    }

    public function calculatePerimeter(): float
    {
        return 2 * ($this->width + $this->height);
    }

    public function getDimensions(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}

// final class — não dá para herdar
final class Square extends Rectangle
{
    public function __construct(string $color, float $size)
    {
        parent::__construct($color, $size, $size);
    }
}

// ❌ Não dá para herdar de final class
// class SmallSquare extends Square {}

// Uso
$circle = new Circle('red', 5);
echo $circle->describe();
// Red Circle (Area: 78.54, Perimeter: 31.42)

$rectangle = new Rectangle('blue', 4, 6);
echo $rectangle->describe();
// Blue Rectangle (Area: 24.00, Perimeter: 20.00)

$square = new Square('green', 5);
echo $square->describe();
// Green Square (Area: 25.00, Perimeter: 20.00)
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
