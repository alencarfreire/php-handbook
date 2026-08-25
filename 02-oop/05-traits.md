# 2.5 Traits

## Resumo

> **Trait** — mecanismo de reúso horizontal de código, sem herança. Trait é um conjunto de métodos que você "encaixa" na classe.
>
> **Conceitos-chave:** use (incluir), vários traits, resolver conflito (insteadof, as), mudar visibilidade, métodos abstratos no trait.
>
> **Importante:** Resolvem herança múltipla via composição. No Laravel: HasFactory, SoftDeletes, Notifiable.

---

## Conteúdo

- [O que é um trait](#o-que-é-um-trait)
- [Vários traits](#vários-traits)
- [Conflito de nomes de métodos](#conflito-de-nomes-de-métodos)
- [Mudar a visibilidade dos métodos](#mudar-a-visibilidade-dos-métodos)
- [Traits com métodos abstratos](#traits-com-métodos-abstratos)
- [Traits com propriedades](#traits-com-propriedades)
- [Traits dentro de traits](#traits-dentro-de-traits)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é um trait

**O que é:**
Reúso horizontal de código. Trait é um conjunto de métodos que você "encaixa" na classe.

**Como funciona:**
```php
trait Loggable
{
    public function log(string $message): void
    {
        echo "[LOG] {$message}\n";
    }
}

trait Timestampable
{
    public function createdAt(): string
    {
        return date('Y-m-d H:i:s');
    }
}

class User
{
    use Loggable, Timestampable;

    public function register(): void
    {
        $this->log('Usuário registrado');
        echo "Criado em: " . $this->createdAt();
    }
}

$user = new User();
$user->register();
// [LOG] Usuário registrado
// Criado em: 2024-01-15 10:30:00
```

**Quando usar:**
Quando você precisa reusar métodos em classes diferentes, sem herança.

**Exemplo prático:**
```php
// Traits de Model no Laravel
class Post extends Model
{
    use HasFactory;      // Factories para testes
    use SoftDeletes;     // Soft delete
    use Notifiable;      // Notificações

    // Você ganha os métodos de todos os traits:
    // - factory()
    // - trashed(), restore(), forceDelete()
    // - notify()
}

// Trait próprio
trait Sluggable
{
    public static function bootSluggable(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }
}

class Post extends Model
{
    use Sluggable;

    // Na criação, o slug sai do title automaticamente
}

$post = Post::create(['title' => 'Meu Post']);
echo $post->slug;  // "meu-post"
```

**Na entrevista:**
> "Trait é reúso horizontal de código. use Trait na classe adiciona os métodos do trait. Resolve herança múltipla. No Laravel, o model usa HasFactory, SoftDeletes, Notifiable."

---

## Vários traits

**O que é:**
A classe pode usar vários traits ao mesmo tempo.

**Como funciona:**
```php
trait Loggable
{
    public function log(string $message): void
    {
        Log::info($message);
    }
}

trait Cacheable
{
    public function cache(string $key, mixed $value, int $ttl = 3600): void
    {
        Cache::put($key, $value, $ttl);
    }

    public function getCached(string $key): mixed
    {
        return Cache::get($key);
    }
}

trait Notifiable
{
    public function notify(string $message): void
    {
        // Envia a notificação
    }
}

class UserService
{
    use Loggable, Cacheable, Notifiable;

    public function process(User $user): void
    {
        $this->log("Processando usuário {$user->id}");
        $this->cache("user:{$user->id}", $user);
        $this->notify("Usuário processado");
    }
}
```

**Quando usar:**
Para montar comportamento a partir de várias fontes.

**Exemplo prático:**
```php
// API Controller com vários traits
trait ApiResponse
{
    protected function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }
}

trait ValidatesRequests
{
    protected function validateOrFail(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }
}

trait AuthorizesRequests
{
    protected function authorizeOrFail(string $ability, mixed $model): void
    {
        if (!Gate::allows($ability, $model)) {
            abort(403);
        }
    }
}

class PostController extends Controller
{
    use ApiResponse, ValidatesRequests, AuthorizesRequests;

    public function update(Request $request, Post $post)
    {
        $this->authorizeOrFail('update', $post);

        $validated = $this->validateOrFail($request->all(), [
            'title' => 'required|max:255',
            'content' => 'required',
        ]);

        $post->update($validated);

        return $this->success($post);
    }
}
```

**Na entrevista:**
> "A classe pode usar vários traits separados por vírgula: use A, B, C. Cada trait adiciona os métodos dele. Uso para compor comportamento: ApiResponse + ValidatesRequests + AuthorizesRequests."

---

## Conflito de nomes de métodos

**O que é:**
Se dois traits têm método com o mesmo nome — conflito.

**Como funciona:**
```php
trait A
{
    public function greet(): string
    {
        return "Olá do A";
    }
}

trait B
{
    public function greet(): string
    {
        return "Olá do B";
    }
}

class MyClass
{
    use A, B;  // ❌ Fatal error: Trait method greet has not been applied

    // Solução 1: insteadof (fica um método)
    use A, B {
        A::greet insteadof B;  // Usa o método do A
    }

    // Solução 2: as (cria um alias)
    use A, B {
        A::greet insteadof B;
        B::greet as greetFromB;  // Alias do método do B
    }
}

$obj = new MyClass();
echo $obj->greet();         // "Olá do A"
echo $obj->greetFromB();    // "Olá do B"
```

**Quando usar:**
Para resolver conflito de nome quando a classe usa vários traits.

**Exemplo prático:**
```php
trait JsonResponseTrait
{
    protected function respond(mixed $data): JsonResponse
    {
        return response()->json($data);
    }
}

trait XmlResponseTrait
{
    protected function respond(mixed $data): Response
    {
        return response($data)->header('Content-Type', 'application/xml');
    }
}

class ApiController extends Controller
{
    use JsonResponseTrait, XmlResponseTrait {
        JsonResponseTrait::respond insteadof XmlResponseTrait;
        XmlResponseTrait::respond as respondXml;
    }

    public function index()
    {
        $data = ['users' => User::all()];

        if (request()->wantsJson()) {
            return $this->respond($data);  // JSON
        }

        return $this->respondXml($data);  // XML
    }
}
```

**Na entrevista:**
> "No conflito de nome eu uso insteadof (fica um método) ou as (cria um alias). insteadof escolhe um e descarta o do outro trait. as cria um alias com outro nome."

---

## Mudar a visibilidade dos métodos

**O que é:**
Você pode mudar a visibilidade (public/protected/private) do método do trait com `as`.

**Como funciona:**
```php
trait Loggable
{
    public function log(string $message): void
    {
        echo "[LOG] {$message}\n";
    }
}

class Service
{
    use Loggable {
        log as protected;  // Vira protected
    }

    public function process(): void
    {
        $this->log('Processando');  // ✅ OK dentro da classe
    }
}

$service = new Service();
$service->log('Teste');  // ❌ Error: protected method

// Ou cria um alias com outra visibilidade
class Service2
{
    use Loggable {
        log as protected internalLog;  // protected com alias
    }

    public function process(): void
    {
        $this->internalLog('Processando');
    }
}
```

**Quando usar:**
Para controlar o acesso aos métodos do trait de fora da classe.

**Exemplo prático:**
```php
trait HasApiToken
{
    public function generateToken(): string
    {
        return Str::random(64);
    }

    public function validateToken(string $token): bool
    {
        return $this->api_token === $token;
    }
}

class User extends Model
{
    use HasApiToken {
        generateToken as private;  // Só dentro da classe
    }

    public function refreshToken(): void
    {
        $this->api_token = $this->generateToken();  // ✅ OK
        $this->save();
    }
}

$user = User::find(1);
$user->refreshToken();  // ✅ OK (método public)
$user->generateToken();  // ❌ Error (private)
```

**Na entrevista:**
> "Com as você muda a visibilidade do método do trait: use Trait { method as private }. Uso para esconder método interno do trait e deixar só a API pública da classe."

---

## Traits com métodos abstratos

**O que é:**
O trait pode declarar um método abstrato — a classe é obrigada a implementar.

**Como funciona:**
```php
trait Cacheable
{
    // Método abstrato (a classe é obrigada a implementar)
    abstract protected function getCacheKey(): string;

    public function cache(mixed $value): void
    {
        $key = $this->getCacheKey();  // Usa o método da classe
        Cache::put($key, $value, 3600);
    }

    public function getCached(): mixed
    {
        $key = $this->getCacheKey();
        return Cache::get($key);
    }
}

class UserService
{
    use Cacheable;

    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    // OBRIGATÓRIO implementar
    protected function getCacheKey(): string
    {
        return "user:{$this->userId}";
    }

    public function getUser(): User
    {
        return $this->getCached() ?? User::find($this->userId);
    }
}
```

**Quando usar:**
Quando o trait precisa de dado da classe, mas a implementação depende da classe concreta.

**Exemplo prático:**
```php
trait Sluggable
{
    // A classe diz de qual campo sai o slug
    abstract protected function getSlugSource(): string;

    public static function bootSluggable(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $source = $model->getSlugSource();
                $model->slug = Str::slug($model->{$source});
            }
        });
    }
}

class Post extends Model
{
    use Sluggable;

    protected function getSlugSource(): string
    {
        return 'title';  // Slug a partir do title
    }
}

class Category extends Model
{
    use Sluggable;

    protected function getSlugSource(): string
    {
        return 'name';  // Slug a partir do name
    }
}

// Repository pattern
trait HasRepository
{
    abstract protected function getModel(): string;  // Classe do model

    public function find(int $id): ?Model
    {
        return $this->getModel()::find($id);
    }

    public function all(): Collection
    {
        return $this->getModel()::all();
    }
}

class UserRepository
{
    use HasRepository;

    protected function getModel(): string
    {
        return User::class;
    }
}
```

**Na entrevista:**
> "O trait pode declarar um método abstract — a classe é obrigada a implementar. Uso quando o trait precisa de dado da classe (getCacheKey, getSlugSource). O trait chama o método abstrato, a classe entrega a implementação."

---

## Traits com propriedades

**O que é:**
O trait pode ter propriedades (entram na classe).

**Como funciona:**
```php
trait Timestampable
{
    protected ?string $createdAt = null;
    protected ?string $updatedAt = null;

    public function setCreatedAt(): void
    {
        $this->createdAt = date('Y-m-d H:i:s');
    }

    public function setUpdatedAt(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }
}

class User
{
    use Timestampable;

    // As propriedades $createdAt e $updatedAt entram na classe
}

$user = new User();
$user->setCreatedAt();
echo $user->getCreatedAt();  // "2024-01-15 10:30:00"

// ⚠️ Não dá para redeclarar a propriedade com outro tipo/visibilidade
class Post
{
    use Timestampable;

    public string $createdAt;  // ❌ Fatal error: Cannot redeclare property
}
```

**Quando usar:**
Para adicionar estado (propriedades) junto com comportamento (métodos).

**Exemplo prático:**
```php
trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';  // Usa UUID no lugar do ID nas rotas
    }
}

class Post extends Model
{
    use HasUuid;

    // Na migration: $table->uuid('uuid')->unique();
}

// Route: /posts/{post}
// Em vez de /posts/1 fica /posts/550e8400-e29b-41d4-a716-446655440000

// Soft Deletes trait
trait SoftDeletes
{
    protected ?Carbon $deleted_at = null;

    public function delete(): void
    {
        $this->deleted_at = now();
        $this->save();
    }

    public function restore(): void
    {
        $this->deleted_at = null;
        $this->save();
    }

    public function trashed(): bool
    {
        return $this->deleted_at !== null;
    }
}
```

**Na entrevista:**
> "O trait pode ter propriedades — elas entram na classe. Não dá para redeclarar a propriedade do trait. No Laravel, SoftDeletes adiciona $deleted_at, HasUuid adiciona os métodos de UUID."

---

## Traits dentro de traits

**O que é:**
Um trait pode usar outros traits.

**Como funciona:**
```php
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

trait ServiceHelpers
{
    use Loggable, Cacheable;  // O trait usa outros traits

    public function process(string $key, mixed $data): void
    {
        $this->log("Processando {$key}");
        $this->cache($key, $data);
    }
}

class UserService
{
    use ServiceHelpers;  // Você ganha os métodos de todos os traits

    public function store(User $user): void
    {
        $this->process("user:{$user->id}", $user);
    }
}
```

**Quando usar:**
Para montar um trait composto a partir dos básicos.

**Exemplo prático:**
```php
// Traits de base
trait HasSlug
{
    public function generateSlug(string $source): string
    {
        return Str::slug($source);
    }
}

trait HasTimestamps
{
    public function touchTimestamps(): void
    {
        $this->updated_at = now();
    }
}

trait HasUuid
{
    public function generateUuid(): string
    {
        return (string) Str::uuid();
    }
}

// Trait composto
trait BlogModelHelpers
{
    use HasSlug, HasTimestamps, HasUuid;

    public function prepareForSave(string $title): void
    {
        $this->uuid = $this->generateUuid();
        $this->slug = $this->generateSlug($title);
        $this->touchTimestamps();
    }
}

class Post extends Model
{
    use BlogModelHelpers;  // Você ganha tudo do BlogModelHelpers

    public function save(array $options = [])
    {
        $this->prepareForSave($this->title);
        return parent::save($options);
    }
}
```

**Na entrevista:**
> "O trait pode usar outros traits com use. Uso para montar trait composto a partir dos básicos. A classe que usa o trait composto ganha os métodos de todos os traits aninhados."

---

## Recapitulando

**O essencial:**
- Trait — reúso horizontal de código
- `use Trait` na classe adiciona os métodos do trait
- Dá para usar vários: `use A, B, C`
- Conflito de nome: `insteadof` (escolhe) ou `as` (alias)
- Mudar visibilidade: `use Trait { method as private }`
- Trait pode ter método abstract (a classe é obrigada a implementar)
- Trait pode ter propriedades (entram na classe)
- Trait pode usar outros traits

**Importante na entrevista:**
- Resolvem herança múltipla via composição
- No Laravel: HasFactory, SoftDeletes, Notifiable, HasUuid
- O trait cola código na classe (copy-paste na compilação)
- Método abstract no trait — a classe é obrigada a implementar
- Mudar visibilidade serve para esconder método interno
- Trait != interface (trait é implementação, interface é contrato)

---

## Exercícios práticos

### Exercício 1: Crie um trait Sluggable para models

**Enunciado:** O trait deve gerar o slug automaticamente a partir do campo indicado, na criação do model.

<details>
<summary>Solução</summary>

```php
trait Sluggable
{
    // Método abstrato — a classe é obrigada a implementar
    abstract protected function getSlugSource(): string;

    // Método opcional para customizar
    protected function getSlugColumn(): string
    {
        return 'slug';
    }

    protected function generateSlug(string $value): string
    {
        // Transliteração + troca espaço por hífen
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $value), '-'));

        // Checagem de unicidade
        $original = $slug;
        $count = 1;

        while ($this->slugExists($slug)) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        // Checagem simplificada (na vida real — query no banco)
        static $used = [];

        if (in_array($slug, $used)) {
            return true;
        }

        $used[] = $slug;
        return false;
    }

    public function setSlugFromSource(): void
    {
        $source = $this->getSlugSource();
        $column = $this->getSlugColumn();

        if (empty($this->$column) && !empty($this->$source)) {
            $this->$column = $this->generateSlug($this->$source);
        }
    }

    // Método boot (roda na inicialização do model)
    public static function bootSluggable(): void
    {
        static::creating(function ($model) {
            $model->setSlugFromSource();
        });

        static::updating(function ($model) {
            // Opcional: atualiza o slug se o campo source mudar
            if ($model->isDirty($model->getSlugSource())) {
                $model->setSlugFromSource();
            }
        });
    }
}

// Model base (simplificado)
class Model
{
    protected array $attributes = [];

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public static function creating($callback)
    {
        // Simula o evento creating
        static::$callbacks['creating'][] = $callback;
    }

    public static function updating($callback)
    {
        static::$callbacks['updating'][] = $callback;
    }

    protected static $callbacks = ['creating' => [], 'updating' => []];

    public function save()
    {
        foreach (static::$callbacks['creating'] as $callback) {
            $callback($this);
        }
        echo "Saved: {$this->title} -> {$this->slug}\n";
    }

    public function isDirty($key)
    {
        return true; // Simplificado
    }
}

// Uso no model Post
class Post extends Model
{
    use Sluggable;

    protected function getSlugSource(): string
    {
        return 'title';  // Gera o slug a partir do title
    }
}

// Uso no model Category
class Category extends Model
{
    use Sluggable;

    protected function getSlugSource(): string
    {
        return 'name';  // Gera o slug a partir do name
    }

    protected function getSlugColumn(): string
    {
        return 'category_slug';  // Nome customizado da coluna
    }
}

// Uso
Post::bootSluggable();

$post = new Post();
$post->title = 'Meu Super Post';
$post->save();  // slug: meu-super-post

$post2 = new Post();
$post2->title = 'Meu Super Post';
$post2->save();  // slug: meu-super-post-1
```
</details>

### Exercício 2: Resolver conflitos de traits

**Enunciado:** Crie dois traits `JsonResponse` e `XmlResponse` com o método `respond()`. Use os dois no controller e resolva o conflito.

<details>
<summary>Solução</summary>

```php
trait JsonResponse
{
    protected function respond(array $data, int $status = 200): array
    {
        return [
            'format' => 'json',
            'data' => json_encode($data),
            'status' => $status,
            'content_type' => 'application/json',
        ];
    }

    protected function success($data): array
    {
        return $this->respond(['status' => 'success', 'data' => $data]);
    }

    protected function error(string $message, int $status = 400): array
    {
        return $this->respond(['status' => 'error', 'message' => $message], $status);
    }
}

trait XmlResponse
{
    protected function respond(array $data, int $status = 200): array
    {
        $xml = $this->arrayToXml($data);

        return [
            'format' => 'xml',
            'data' => $xml,
            'status' => $status,
            'content_type' => 'application/xml',
        ];
    }

    protected function arrayToXml(array $data, string $root = 'response'): string
    {
        $xml = "<?xml version='1.0'?><{$root}>";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= "<{$key}>" . $this->arrayToXml($value, '') . "</{$key}>";
            } else {
                $xml .= "<{$key}>{$value}</{$key}>";
            }
        }

        $xml .= "</{$root}>";
        return $xml;
    }
}

class ApiController
{
    use JsonResponse, XmlResponse {
        // Resolve o conflito do método respond()
        JsonResponse::respond insteadof XmlResponse;  // JSON por padrão
        XmlResponse::respond as respondXml;           // XML pelo alias
    }

    public function index(string $format = 'json'): array
    {
        $data = [
            'users' => [
                ['id' => 1, 'name' => 'João'],
                ['id' => 2, 'name' => 'Pedro'],
            ],
        ];

        if ($format === 'xml') {
            return $this->respondXml($data);
        }

        return $this->respond($data);
    }

    public function show(int $id, string $format = 'json'): array
    {
        $user = ['id' => $id, 'name' => 'João'];

        if ($format === 'xml') {
            return $this->respondXml(['user' => $user]);
        }

        return $this->success($user);  // Usa JsonResponse::success
    }
}

// Uso
$controller = new ApiController();

print_r($controller->index('json'));
// ['format' => 'json', 'data' => '{"users":[...]}', ...]

print_r($controller->index('xml'));
// ['format' => 'xml', 'data' => '<?xml version="1.0"?><response>...', ...]

print_r($controller->show(1, 'json'));
// ['format' => 'json', 'data' => '{"status":"success","data":{...}}', ...]
```
</details>

### Exercício 3: Trait com métodos abstratos e propriedades

**Enunciado:** Crie o trait `Cacheable`, que exige da classe o método `getCacheKey()` e adiciona cache.

<details>
<summary>Solução</summary>

```php
trait Cacheable
{
    protected array $cache = [];
    protected int $defaultTtl = 3600;

    // Método abstrato — a classe É OBRIGADA a implementar
    abstract protected function getCacheKey(): string;

    // Método opcional — dá para sobrescrever
    protected function getCacheTtl(): int
    {
        return $this->defaultTtl;
    }

    protected function getCachePrefix(): string
    {
        return strtolower(basename(str_replace('\\', '/', static::class)));
    }

    public function remember(callable $callback): mixed
    {
        $key = $this->buildCacheKey();

        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $this->getCacheTtl());

        return $value;
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];
    }

    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->cache[$key]['value'];
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if ($this->cache[$key]['expires_at'] < time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    public function forget(string $key): bool
    {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            return true;
        }

        return false;
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    protected function buildCacheKey(): string
    {
        $prefix = $this->getCachePrefix();
        $key = $this->getCacheKey();

        return "{$prefix}:{$key}";
    }
}

// Service de usuários
class UserService
{
    use Cacheable;

    private int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    // Implementação OBRIGATÓRIA do método abstrato
    protected function getCacheKey(): string
    {
        return "user:{$this->userId}";
    }

    // Sobrescreve o TTL
    protected function getCacheTtl(): int
    {
        return 7200;  // 2 horas
    }

    public function getUser(): array
    {
        return $this->remember(function () {
            // Operação cara (query no banco)
            echo "Buscando user {$this->userId} no banco...\n";

            return [
                'id' => $this->userId,
                'name' => 'Usuário ' . $this->userId,
                'email' => "user{$this->userId}@email.com",
            ];
        });
    }
}

// Repository de posts
class PostRepository
{
    use Cacheable;

    private string $slug;

    public function __construct(string $slug)
    {
        $this->slug = $slug;
    }

    protected function getCacheKey(): string
    {
        return "post:{$this->slug}";
    }

    protected function getCacheTtl(): int
    {
        return 3600;  // 1 hora
    }

    public function findBySlug(): array
    {
        return $this->remember(function () {
            echo "Buscando post {$this->slug} no banco...\n";

            return [
                'id' => rand(1, 100),
                'slug' => $this->slug,
                'title' => ucwords(str_replace('-', ' ', $this->slug)),
            ];
        });
    }
}

// Uso
$userService = new UserService(1);

$user = $userService->getUser();
// Buscando user 1 no banco...
print_r($user);

$user = $userService->getUser();  // Do cache (não imprime "Buscando...")
print_r($user);

$postRepo = new PostRepository('meu-super-post');

$post = $postRepo->findBySlug();
// Buscando post meu-super-post no banco...

$post = $postRepo->findBySlug();  // Do cache
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
