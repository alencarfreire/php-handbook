# 2.7 Ligação estática (Late Static Binding)

## Resumo

> **Late Static Binding** — mecanismo que aponta para a classe que chamou (`static`), não para a classe onde o método foi definido (`self`).
>
> **Conceitos-chave:** self (classe da definição), static (classe da chamada), new static (instância de quem chamou), static::class (nome da classe).
>
> **Importante:** Eloquent usa static para devolver o tipo certo em find() e all(). static::boot() + parent::boot() para estender a lógica.

---

## Conteúdo

- [self vs static](#self-vs-static)
- [static:: em métodos](#static-em-métodos)
- [new static vs new self](#new-static-vs-new-self)
- [parent:: com static](#parent-com-static)
- [get_called_class() (obsoleta no PHP 8.0)](#get_called_class-obsoleta-no-php-80)
- [Problemas com Late Static Binding](#problemas-com-late-static-binding)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## self vs static

**O que é:**
`self` aponta para a classe onde o método foi definido. `static` aponta para a classe que chamou (ligação tardia).

**Como funciona:**
```php
class Animal
{
    public static function getClass(): string
    {
        return self::class;  // Animal (classe onde foi definido)
    }

    public static function getCalledClass(): string
    {
        return static::class;  // Classe que chamou (Late Static Binding)
    }
}

class Dog extends Animal {}

echo Animal::getClass();  // "Animal"
echo Dog::getClass();     // "Animal" (self — sempre Animal)

echo Animal::getCalledClass();  // "Animal"
echo Dog::getCalledClass();     // "Dog" (static — classe da chamada)
```

**Quando usar:**
`static` em métodos que precisam da classe que chamou (Factory, Active Record).

**Exemplo prático:**
```php
// Eloquent Model (simplificado)
class Model
{
    public static function find(int $id): ?static
    {
        $class = static::class;  // Pega a classe que chamou
        // SELECT * FROM {table} WHERE id = {$id}
        return new $class;  // Devolve instância da classe que chamou
    }

    public static function all(): Collection
    {
        $class = static::class;
        // SELECT * FROM {table}
        return collect([new $class, new $class]);
    }
}

class User extends Model {}
class Post extends Model {}

$user = User::find(1);  // Devolve User (não Model!)
// static::class = User::class

$post = Post::find(1);  // Devolve Post (não Model!)
// static::class = Post::class

// Com self seria:
class ModelBad
{
    public static function find(int $id): ?self
    {
        return new self;  // Sempre Model (não é o que você quer!)
    }
}

$user = User::find(1);  // Devolve Model (não User!) ❌
```

**Na entrevista:**
> "self aponta para a classe onde o método foi definido. static aponta para a classe que chamou (Late Static Binding). Eloquent usa static para devolver o tipo certo em find() e all(). Com static, a subclasse consegue sobrescrever o comportamento."

---

## static:: em métodos

**O que é:**
Chamada de métodos estáticos da classe que chamou, via `static::`.

**Como funciona:**
```php
class Model
{
    protected static string $table;

    public static function getTable(): string
    {
        return static::$table;  // Pega $table da classe que chamou
    }

    public static function all(): array
    {
        $table = static::getTable();  // Chama o método da classe que chamou
        return DB::select("SELECT * FROM {$table}");
    }
}

class User extends Model
{
    protected static string $table = 'users';
}

class Post extends Model
{
    protected static string $table = 'posts';
}

echo User::getTable();  // "users" (static::$table de User)
echo Post::getTable();  // "posts" (static::$table de Post)

$users = User::all();  // SELECT * FROM users
$posts = Post::all();  // SELECT * FROM posts
```

**Quando usar:**
Para chamar métodos e propriedades da subclasse a partir da classe base.

**Exemplo prático:**
```php
// Active Record pattern
abstract class ActiveRecord
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    public static function find(int $id): ?static
    {
        $table = static::$table;
        $pk = static::$primaryKey;

        $data = DB::selectOne("SELECT * FROM {$table} WHERE {$pk} = ?", [$id]);

        if ($data === null) {
            return null;
        }

        return static::hydrate($data);  // Cria objeto da classe que chamou
    }

    protected static function hydrate(array $data): static
    {
        $instance = new static();  // Instância da classe que chamou

        foreach ($data as $key => $value) {
            $instance->$key = $value;
        }

        return $instance;
    }

    public function save(): bool
    {
        $table = static::$table;

        // INSERT ou UPDATE
        return true;
    }
}

class User extends ActiveRecord
{
    protected static string $table = 'users';

    public int $id;
    public string $name;
    public string $email;
}

$user = User::find(1);  // Devolve User (não ActiveRecord)
// static::hydrate cria new User()
```

**Na entrevista:**
> "static:: chama o método ou a propriedade da classe que chamou. Uso em Active Record, Factory e classe base. static::$property pega o valor da subclasse. static::method() chama o método da subclasse."

---

## new static vs new self

**O que é:**
`new self` cria instância da classe onde o método foi definido. `new static` cria instância da classe que chamou.

**Como funciona:**
```php
class Animal
{
    public static function createSelf(): self
    {
        return new self();  // Sempre Animal
    }

    public static function createStatic(): static
    {
        return new static();  // Classe da chamada
    }
}

class Dog extends Animal {}

$animal1 = Animal::createSelf();  // Animal
$animal2 = Animal::createStatic();  // Animal

$dog1 = Dog::createSelf();  // Animal (não Dog!) ❌
$dog2 = Dog::createStatic();  // Dog ✅
```

**Quando usar:**
`new static` em Factory, builder e método que cria objeto.

**Exemplo prático:**
```php
// Factory method
abstract class Model
{
    public static function make(array $attributes = []): static
    {
        $instance = new static();  // Instância da classe que chamou

        foreach ($attributes as $key => $value) {
            $instance->$key = $value;
        }

        return $instance;
    }

    public static function create(array $attributes): static
    {
        $instance = static::make($attributes);
        $instance->save();

        return $instance;
    }
}

class User extends Model
{
    public string $name;
    public string $email;

    public function save(): void
    {
        // Salva no banco
    }
}

$user = User::make(['name' => 'João', 'email' => 'joao@email.com']);
// $user é User (não Model)

$user = User::create(['name' => 'Pedro', 'email' => 'pedro@email.com']);
// Cria e salva um User

// Builder pattern
class QueryBuilder
{
    protected array $wheres = [];

    public function where(string $column, mixed $value): static
    {
        $this->wheres[$column] = $value;
        return $this;  // Fluent interface
    }

    public function clone(): static
    {
        return clone $this;  // Clona com o tipo certo
    }
}

class UserQueryBuilder extends QueryBuilder
{
    public function active(): static
    {
        return $this->where('is_active', true);
    }
}

$query = new UserQueryBuilder();
$activeUsers = $query->active()->where('department_id', 5);
// Todos os métodos devolvem UserQueryBuilder (não QueryBuilder)
```

**Na entrevista:**
> "new self cria instância da classe onde o método foi definido. new static cria da classe que chamou. Uso new static em Factory e builder. Eloquent::make() e Eloquent::create() usam new static."

---

## parent:: com static

**O que é:**
Combinar a chamada do método pai com Late Static Binding.

**Como funciona:**
```php
class Model
{
    public static function boot(): void
    {
        echo "Model inicializado\n";
    }

    public static function initialize(): void
    {
        static::boot();  // Chama boot() da classe que chamou
    }
}

class User extends Model
{
    public static function boot(): void
    {
        parent::boot();  // Chama o boot() do pai
        echo "User inicializado\n";
    }
}

User::initialize();
// Model inicializado
// User inicializado

// Sem parent::
class PostBad extends Model
{
    public static function boot(): void
    {
        // Não chamou parent::boot() — a lógica do pai ficou de fora!
        echo "Post inicializado\n";
    }
}

PostBad::initialize();
// Post inicializado (Model inicializado não rodou!)
```

**Quando usar:**
Para estender a lógica do pai na subclasse.

**Exemplo prático:**
```php
// Eloquent Model boot
abstract class Model
{
    protected static function boot(): void
    {
        static::bootTraits();  // Carrega os traits

        // Lógica base
    }

    protected static function bootTraits(): void
    {
        foreach (class_uses_recursive(static::class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists(static::class, $method)) {
                static::$method();
            }
        }
    }
}

trait SoftDeletes
{
    protected static function bootSoftDeletes(): void
    {
        static::addGlobalScope('soft_deletes', function ($query) {
            $query->whereNull('deleted_at');
        });
    }
}

class Post extends Model
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();  // IMPORTANTE: chama o boot do pai

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }
}

// Ao chamar Post::boot():
// 1. parent::boot() → Model::boot() → bootTraits() → bootSoftDeletes()
// 2. static::creating() — adiciona a lógica própria
```

**Na entrevista:**
> "parent::method() chama o método do pai. static:: chama o da classe que chamou. No Eloquent, no boot() eu sempre chamo parent::boot() para carregar os traits. parent + static deixam a subclasse estender a lógica do pai."

---

## get_called_class() (obsoleta no PHP 8.0)

**O que é:**
Função que devolve o nome da classe que chamou. No PHP 8.0+ o substituto é `static::class`.

**Como funciona:**
```php
class Animal
{
    public static function whoAmI(): string
    {
        // PHP < 8.0
        return get_called_class();

        // PHP 8.0+
        return static::class;  // Preferível
    }
}

class Dog extends Animal {}

echo Dog::whoAmI();  // "Dog"

// static::class (PHP 8.0+)
class Model
{
    public static function getModelName(): string
    {
        return static::class;  // Mais curto e mais legível
    }
}

echo User::getModelName();  // "User"
```

**Quando usar:**
No PHP 8.0+ use sempre `static::class` no lugar de `get_called_class()`.

**Exemplo prático:**
```php
// Logger
class BaseService
{
    protected function log(string $message): void
    {
        $class = static::class;  // Classe que chamou
        Log::info("[{$class}] {$message}");
    }
}

class OrderService extends BaseService
{
    public function process(): void
    {
        $this->log('Processando pedido');
        // [OrderService] Processando pedido
    }
}

class UserService extends BaseService
{
    public function process(): void
    {
        $this->log('Processando usuário');
        // [UserService] Processando usuário
    }
}

// Rotas
class Controller
{
    public function getRoute(): string
    {
        $class = static::class;
        $shortName = class_basename($class);  // UserController → UserController
        $route = Str::snake(str_replace('Controller', '', $shortName));

        return "/{$route}";
    }
}

class UserController extends Controller {}

echo (new UserController())->getRoute();  // "/user"
```

**Na entrevista:**
> "get_called_class() devolve o nome da classe que chamou (obsoleta no PHP 8.0). Uso static::class no lugar. static::class devolve o nome completo da classe, com namespace."

---

## Problemas com Late Static Binding

**O que é:**
Late Static Binding pode surpreender se você não souber como ele funciona.

**Problemas:**
```php
// Problema 1: propriedade estática não herda do jeito que você espera
class Model
{
    protected static array $instances = [];

    public static function register(): void
    {
        static::$instances[] = new static();
    }

    public static function getInstances(): array
    {
        return static::$instances;
    }
}

class User extends Model {}
class Post extends Model {}

User::register();
User::register();
Post::register();

// Esperado: User — 2, Post — 1
// Realidade: tudo no mesmo array Model::$instances
var_dump(User::getInstances());  // 3 elementos (User, User, Post) ❌

// Solução: cada classe declara a própria propriedade
class UserFixed extends Model
{
    protected static array $instances = [];
}

class PostFixed extends Model
{
    protected static array $instances = [];
}

// Problema 2: debug fica difícil
class Base
{
    public static function who(): string
    {
        return static::class;  // Depende do contexto da chamada
    }
}

class Child extends Base
{
    public static function test(): string
    {
        return parent::who();  // Qual classe devolve?
    }
}

echo Child::test();  // "Child" (Late Static Binding vale mesmo via parent)
```

**Quando usar:**
Use com cuidado e documente o comportamento. Prefira composição a herança.

**Exemplo prático:**
```php
// Uso certo: Eloquent Model
abstract class Model
{
    // Cada subclasse define a própria tabela
    abstract protected static function getTable(): string;

    public static function all(): Collection
    {
        $table = static::getTable();  // Chama o método da subclasse
        return DB::table($table)->get();
    }
}

class User extends Model
{
    protected static function getTable(): string
    {
        return 'users';
    }
}

$users = User::all();  // SELECT * FROM users

// Uso errado: hierarquia complicada
class A
{
    public static function test(): string
    {
        return static::getClass();
    }

    protected static function getClass(): string
    {
        return self::class;
    }
}

class B extends A
{
    protected static function getClass(): string
    {
        return parent::getClass() . ' -> ' . static::class;
    }
}

class C extends B {}

echo C::test();  // "A -> C" (difícil de rastrear) ❌
```

**Na entrevista:**
> "Late Static Binding pode complicar o debug. Propriedade estática precisa ser redeclarada em cada subclasse. Uso com cuidado e documento. Em caso complexo, prefiro composição a herança."

---

## Recapitulando

- `self` — classe onde o método foi definido
- `static` — classe que chamou (Late Static Binding)
- `new self` — cria instância da classe da definição
- `new static` — cria instância da classe que chamou
- `static::` — chama método ou propriedade da classe que chamou
- `parent::` + `static` — estende a lógica do pai
- `static::class` — nome da classe que chamou (PHP 8.0+)

**self vs static:**
| self | static |
|------|--------|
| Classe da definição | Classe da chamada |
| Ligação antecipada | Ligação tardia |
| Não é sobrescrito | É sobrescrito nas subclasses |

**Importante na entrevista:**
- Eloquent usa `static` para devolver o tipo certo em find() e all()
- `new static` em Factory e builder
- `static::boot()` + `parent::boot()` para estender a lógica
- Cuidado com propriedade estática (redeclare em cada subclasse)
- `static::class` no lugar de `get_called_class()` (PHP 8.0+)

---

## Exercícios práticos

### Exercício 1: Active Record com static

**Enunciado:** Crie um `Model` base com find(), all() e create() usando static para devolver o tipo certo.

<details>
<summary>Solução</summary>

```php
abstract class Model
{
    protected array $attributes = [];
    protected static array $instances = [];

    abstract protected static function getTable(): string;

    public static function find(int $id): ?static
    {
        $table = static::getTable();
        echo "SELECT * FROM {$table} WHERE id = {$id}\n";

        // Cria instância da classe que chamou (User/Post)
        $instance = new static();
        $instance->attributes = ['id' => $id, 'loaded' => true];

        return $instance;
    }

    public static function all(): array
    {
        $table = static::getTable();
        echo "SELECT * FROM {$table}\n";

        return [
            static::make(['id' => 1]),
            static::make(['id' => 2]),
        ];
    }

    public static function make(array $attributes = []): static
    {
        $instance = new static();
        $instance->attributes = $attributes;
        return $instance;
    }

    public static function create(array $attributes): static
    {
        $instance = static::make($attributes);
        $instance->save();
        return $instance;
    }

    public function save(): bool
    {
        $table = static::getTable();

        if (isset($this->attributes['id'])) {
            echo "UPDATE {$table} SET ... WHERE id = {$this->attributes['id']}\n";
        } else {
            $this->attributes['id'] = rand(1, 1000);
            echo "INSERT INTO {$table} (...) VALUES (...)\n";
        }

        return true;
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }
}

class User extends Model
{
    protected static function getTable(): string
    {
        return 'users';
    }
}

class Post extends Model
{
    protected static function getTable(): string
    {
        return 'posts';
    }
}

// Uso
$user = User::find(1);  // Devolve User (não Model!)
// SELECT * FROM users WHERE id = 1
var_dump($user instanceof User);  // true

$post = Post::find(1);  // Devolve Post (não Model!)
// SELECT * FROM posts WHERE id = 1
var_dump($post instanceof Post);  // true

$users = User::all();  // Array de objetos User
// SELECT * FROM users
var_dump($users[0] instanceof User);  // true

$newUser = User::create(['name' => 'João']);
// INSERT INTO users (...) VALUES (...)
```
</details>

### Exercício 2: Factory Pattern com new static

**Enunciado:** Crie uma classe `Factory` base que usa new static para criar instâncias.

<details>
<summary>Solução</summary>

```php
abstract class Factory
{
    protected array $attributes = [];

    public static function new(array $attributes = []): static
    {
        $instance = new static();
        $instance->attributes = $attributes;
        return $instance;
    }

    public function state(string $name): static
    {
        $method = "state" . ucfirst($name);

        if (!method_exists($this, $method)) {
            throw new \Exception("State {$name} não existe");
        }

        $this->$method();
        return $this;
    }

    public function make(): object
    {
        return $this->createInstance();
    }

    public function create(): object
    {
        $instance = $this->make();
        echo "Salvando no banco...\n";
        return $instance;
    }

    abstract protected function createInstance(): object;
}

class UserFactory extends Factory
{
    protected function createInstance(): object
    {
        $user = new \stdClass();
        $user->name = $this->attributes['name'] ?? 'John Doe';
        $user->email = $this->attributes['email'] ?? 'john@example.com';
        $user->is_admin = $this->attributes['is_admin'] ?? false;
        return $user;
    }

    protected function stateAdmin(): void
    {
        $this->attributes['is_admin'] = true;
        $this->attributes['email'] = $this->attributes['email'] ?? 'admin@example.com';
    }

    protected function stateActive(): void
    {
        $this->attributes['is_active'] = true;
    }
}

class PostFactory extends Factory
{
    protected function createInstance(): object
    {
        $post = new \stdClass();
        $post->title = $this->attributes['title'] ?? 'Título padrão';
        $post->content = $this->attributes['content'] ?? 'Conteúdo padrão';
        $post->published = $this->attributes['published'] ?? false;
        return $post;
    }

    protected function statePublished(): void
    {
        $this->attributes['published'] = true;
        $this->attributes['published_at'] = date('Y-m-d H:i:s');
    }

    protected function stateDraft(): void
    {
        $this->attributes['published'] = false;
        $this->attributes['published_at'] = null;
    }
}

// Uso
$user = UserFactory::new(['name' => 'João'])->make();
print_r($user);  // stdClass {name: "João", email: "john@example.com", ...}

$admin = UserFactory::new()->state('admin')->make();
print_r($admin);  // stdClass {is_admin: true, email: "admin@example.com"}

$activeAdmin = UserFactory::new()
    ->state('admin')
    ->state('active')
    ->create();  // Salvando no banco...

$publishedPost = PostFactory::new(['title' => 'Meu post'])
    ->state('published')
    ->make();
print_r($publishedPost);
// stdClass {title: "Meu post", published: true, published_at: "2024-..."}
```
</details>

### Exercício 3: Mecanismo de boot com parent::boot()

**Enunciado:** Crie um sistema de métodos boot em que as subclasses estendem a lógica do pai.

<details>
<summary>Solução</summary>

```php
abstract class Model
{
    protected static array $booted = [];
    protected static array $observers = [];

    public static function boot(): void
    {
        // Garante que a classe ainda não foi carregada
        if (isset(static::$booted[static::class])) {
            return;
        }

        echo "Inicializando " . static::class . "\n";

        // Carrega os traits
        static::bootTraits();

        // Marca como carregado
        static::$booted[static::class] = true;
    }

    protected static function bootTraits(): void
    {
        foreach (class_uses(static::class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists(static::class, $method)) {
                static::$method();
            }
        }
    }

    protected static function observe(string $event, callable $callback): void
    {
        if (!isset(static::$observers[static::class])) {
            static::$observers[static::class] = [];
        }

        static::$observers[static::class][$event][] = $callback;
    }

    protected static function fireEvent(string $event, $model): void
    {
        if (!isset(static::$observers[static::class][$event])) {
            return;
        }

        foreach (static::$observers[static::class][$event] as $callback) {
            $callback($model);
        }
    }

    public function save(): void
    {
        static::fireEvent('saving', $this);
        echo "Salvando " . static::class . "\n";
        static::fireEvent('saved', $this);
    }
}

trait SoftDeletes
{
    protected static function bootSoftDeletes(): void
    {
        echo "  - Inicializando trait SoftDeletes\n";

        static::observe('deleting', function ($model) {
            echo "  - SoftDeletes: definindo deleted_at\n";
        });
    }
}

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        echo "  - Inicializando trait HasUuid\n";

        static::observe('creating', function ($model) {
            echo "  - HasUuid: gerando UUID\n";
        });
    }
}

class Post extends Model
{
    use SoftDeletes, HasUuid;

    public static function boot(): void
    {
        parent::boot();  // IMPORTANTE: chama o boot do pai

        echo "  - Lógica de boot customizada do Post\n";

        static::observe('saving', function ($post) {
            echo "  - Post: gerando slug\n";
        });
    }
}

class User extends Model
{
    use SoftDeletes;

    public static function boot(): void
    {
        parent::boot();

        echo "  - Lógica de boot customizada do User\n";

        static::observe('creating', function ($user) {
            echo "  - User: gerando hash da senha\n";
        });
    }
}

// Uso
Post::boot();
// Inicializando Post
//   - Inicializando trait SoftDeletes
//   - Inicializando trait HasUuid
//   - Lógica de boot customizada do Post

echo "\n";

User::boot();
// Inicializando User
//   - Inicializando trait SoftDeletes
//   - Lógica de boot customizada do User

echo "\n";

$post = new Post();
$post->save();
// - Post: gerando slug
// Salvando Post
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
