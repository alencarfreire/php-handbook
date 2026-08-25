# 9.5 API Versioning

## Resumo

> **API Versioning (versionamento de API)** — controle de versões da API para mudar sem quebrar clientes antigos.
>
> **Métodos:** URI (/api/v1), Header (Accept: application/vnd.api.v1+json), Query (?version=1).
>
> **Nova versão quando:** breaking changes, mudança de estrutura, remoção de campos.

---

## Conteúdo

- [O que é](#o-que-é)
- [Métodos de versionamento](#métodos-de-versionamento)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
API Versioning — controle de versões da API. Você muda a API sem quebrar clientes antigos.

**O essencial:**
- Compatibilidade com clientes antigos
- Migração gradual dos clientes
- Mudanças seguras na API

---

## Métodos de versionamento

### 1. URI Versioning (o mais usado)

```php
// routes/api.php
Route::prefix('v1')->namespace('Api\V1')->group(function () {
    Route::apiResource('posts', PostController::class);
});

Route::prefix('v2')->namespace('Api\V2')->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Estrutura
// app/Http/Controllers/Api/V1/PostController.php
// app/Http/Controllers/Api/V2/PostController.php
// app/Http/Resources/V1/PostResource.php
// app/Http/Resources/V2/PostResource.php
```

**Prós:**
- Simples e claro
- Aparece na URL
- Fácil de testar

**Contras:**
- Duplicação de código
- A URL fica suja

### 2. Header Versioning

```php
// Middleware
class ApiVersionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $version = $request->header('Api-Version', 'v1');
        $request->attributes->set('api_version', $version);

        return $next($request);
    }
}

// Controller
class PostController extends Controller
{
    public function index(Request $request)
    {
        $version = $request->attributes->get('api_version');

        return $version === 'v2'
            ? V2\PostResource::collection(Post::all())
            : V1\PostResource::collection(Post::all());
    }
}
```

**Prós:**
- URLs limpas
- RESTful

**Contras:**
- Mais difícil de testar
- Clientes precisam saber do header

### 3. Query Parameter

```php
Route::get('/posts', function (Request $request) {
    $version = $request->query('version', 'v1');

    return match($version) {
        'v2' => V2\PostResource::collection(Post::all()),
        default => V1\PostResource::collection(Post::all()),
    };
});
```

**Prós:**
- Fácil de testar

**Contras:**
- Não é RESTful
- Query params servem para outro fim

---

## Quando usar

### Nova versão quando:

| Mudança | Nova versão? | Exemplo |
|-----------|--------------|--------|
| Breaking changes | ✅ Sim | Renomear campo `content` → `body` |
| Mudança de estrutura | ✅ Sim | Objeto aninhado → estrutura plana |
| Remoção de campos | ✅ Sim | Tiraram o campo `deprecated_field` |
| Mudança de tipos | ✅ Sim | `string` → `integer` |
| Adição de campos | ❌ Não | Adicionaram `author` (backwards compatible) |
| Novos endpoints | ❌ Não | POST /posts/{id}/publish |
| Bug fixes | ❌ Não | Correção da lógica |

---

## Exemplo prático

### Resources diferentes por versão

```php
// V1: estrutura antiga
namespace App\Http\Resources\V1;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->body,  // Nome antigo do campo
            'created' => $this->created_at->toDateTimeString(),
        ];
    }
}

// V2: estrutura nova
namespace App\Http\Resources\V2;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,  // Nome novo
            'author' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Avisos de deprecation

```php
// V1 Controller (deprecated)
namespace App\Http\Controllers\Api\V1;

class PostController extends Controller
{
    public function index()
    {
        return V1\PostResource::collection(Post::all())
            ->response()
            ->header('X-API-Warn', 'V1 está deprecated. Migre para V2 até 2024-12-31.')
            ->header('X-API-Deprecation-Date', '2024-12-31')
            ->header('X-API-Sunset-Date', '2025-03-31');
    }
}
```

### Código compartilhado entre versões

```php
// app/Services/PostService.php
class PostService
{
    public function getAllPosts()
    {
        return Post::with('user')->get();
    }
}

// V1 Controller
class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index()
    {
        $posts = $this->service->getAllPosts();
        return V1\PostResource::collection($posts);
    }
}

// V2 Controller
class PostController extends Controller
{
    public function __construct(private PostService $service) {}

    public function index()
    {
        $posts = $this->service->getAllPosts();
        return V2\PostResource::collection($posts);
    }
}
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- API Versioning evita breaking changes para clientes antigos
- Permite evoluir a API sem quebrar ninguém

**Métodos:**
- URI versioning (/api/v1, /api/v2) — o mais usado
- Header versioning (Accept: application/vnd.api.v1+json)
- Query parameter (?version=1)

**Quando nova versão:**
- Breaking changes (renomear, remover campos)
- Mudança na estrutura da response
- Mudança de tipos

**Mudanças backwards compatible (sem nova versão):**
- Campos novos
- Endpoints novos
- Bug fixes

**Boas práticas:**
- Controllers/Resources diferentes por versão
- Avisos de deprecation via headers
- Semantic versioning (v1, v2, v3)
- Suporte às versões antigas por tempo limitado
- Shared Services para a lógica de negócio

---

## Exercícios práticos

### Exercício 1: Crie a v2 com breaking change

Você tem uma API V1 com o campo `user_name`. Na V2 precisa separar em `first_name` e `last_name`.

<details>
<summary>Solução</summary>

```php
// V1 Resource (estrutura antiga)
namespace App\Http\Resources\V1;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_name' => $this->name,  // Um campo só
            'email' => $this->email,
        ];
    }
}

// V2 Resource (estrutura nova)
namespace App\Http\Resources\V2;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Separa name em first_name e last_name
        [$firstName, $lastName] = $this->parseFullName($this->name);

        return [
            'id' => $this->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $this->email,
        ];
    }

    private function parseFullName(string $fullName): array
    {
        $parts = explode(' ', $fullName, 2);
        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }
}

// Migration no banco (se precisar persistir)
Schema::table('users', function (Blueprint $table) {
    $table->string('first_name')->nullable();
    $table->string('last_name')->nullable();
});

// Depois de migrar os dados
DB::table('users')->get()->each(function ($user) {
    [$firstName, $lastName] = explode(' ', $user->name, 2);
    DB::table('users')->where('id', $user->id)->update([
        'first_name' => $firstName,
        'last_name' => $lastName ?? '',
    ]);
});

// routes/api.php
Route::prefix('v1')->group(function () {
    Route::get('/users', [V1\UserController::class, 'index']);
});

Route::prefix('v2')->group(function () {
    Route::get('/users', [V2\UserController::class, 'index']);
});
```
</details>

### Exercício 2: Estratégia de deprecation

Crie um sistema de avisos de deprecation para a API V1.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/ApiDeprecationWarning.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ApiDeprecationWarning
{
    // Configuração das versões
    private array $deprecations = [
        'v1' => [
            'deprecated_at' => '2024-01-01',
            'sunset_at' => '2024-06-01',
            'message' => 'API v1 está deprecated. Migre para a v2.',
            'migration_url' => 'https://docs.example.com/api/v2-migration',
        ],
    ];

    public function handle(Request $request, Closure $next, string $version)
    {
        $response = $next($request);

        if (isset($this->deprecations[$version])) {
            $deprecation = $this->deprecations[$version];

            $response->headers->set('X-API-Deprecated', 'true');
            $response->headers->set('X-API-Deprecation-Date', $deprecation['deprecated_at']);
            $response->headers->set('X-API-Sunset-Date', $deprecation['sunset_at']);
            $response->headers->set('X-API-Deprecation-Info', $deprecation['migration_url']);

            $response->headers->set('Warning',
                sprintf('299 - "%s"', $deprecation['message'])
            );

            // Log de uso da API deprecated
            \Log::warning('Uso de API deprecated', [
                'version' => $version,
                'endpoint' => $request->path(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
        }

        return $response;
    }
}

// app/Http/Kernel.php
protected $middlewareAliases = [
    'api.deprecation' => \App\Http\Middleware\ApiDeprecationWarning::class,
];

// routes/api.php
Route::prefix('v1')
    ->middleware('api.deprecation:v1')
    ->group(function () {
        Route::apiResource('posts', V1\PostController::class);
    });

Route::prefix('v2')->group(function () {
    Route::apiResource('posts', V2\PostController::class);
});

// Command agendado para acompanhar
namespace App\Console\Commands;

class CheckDeprecatedApiUsage extends Command
{
    protected $signature = 'api:check-deprecated-usage';

    public function handle()
    {
        // Análise dos logs da última semana
        $usage = DB::table('api_logs')
            ->where('version', 'v1')
            ->where('created_at', '>=', now()->subWeek())
            ->groupBy('user_id')
            ->selectRaw('user_id, count(*) as requests_count')
            ->get();

        // Aviso aos usuários
        foreach ($usage as $record) {
            $user = User::find($record->user_id);
            if ($user) {
                Mail::to($user)->send(
                    new ApiDeprecationNotification('v1', '2024-06-01')
                );
            }
        }

        $this->info("Avisos de deprecation enviados para {$usage->count()} usuários");
    }
}
```
</details>

### Exercício 3: Versionamento via Header

Implemente versionamento via Header com fallback para v1.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/ApiVersionResolver.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiVersionResolver
{
    private const DEFAULT_VERSION = 'v1';
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];

    public function handle(Request $request, Closure $next)
    {
        $version = $this->resolveVersion($request);

        if (!in_array($version, self::SUPPORTED_VERSIONS)) {
            return response()->json([
                'error' => 'Versão de API não suportada',
                'supported_versions' => self::SUPPORTED_VERSIONS,
            ], 400);
        }

        $request->attributes->set('api_version', $version);
        $request->headers->set('X-API-Version', $version);

        return $next($request);
    }

    private function resolveVersion(Request $request): string
    {
        // 1. Checa o Accept header
        $accept = $request->header('Accept');
        if (preg_match('/application\/vnd\.api\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }

        // 2. Checa o header customizado
        if ($version = $request->header('Api-Version')) {
            return $version;
        }

        // 3. Fallback para o default
        return self::DEFAULT_VERSION;
    }
}

// Controller base
namespace App\Http\Controllers\Api;

abstract class ApiController extends Controller
{
    protected function getApiVersion(Request $request): string
    {
        return $request->attributes->get('api_version', 'v1');
    }

    protected function resourceForVersion(Request $request, $data, array $resources)
    {
        $version = $this->getApiVersion($request);
        $resourceClass = $resources[$version] ?? $resources['v1'];

        return is_array($data) || $data instanceof \Illuminate\Support\Collection
            ? $resourceClass::collection($data)
            : new $resourceClass($data);
    }
}

// Controller unificado
namespace App\Http\Controllers\Api;

use App\Models\Post;

class PostController extends ApiController
{
    public function index(Request $request)
    {
        $posts = Post::with('user')->paginate(20);

        return $this->resourceForVersion($request, $posts, [
            'v1' => \App\Http\Resources\V1\PostResource::class,
            'v2' => \App\Http\Resources\V2\PostResource::class,
        ]);
    }

    public function show(Request $request, Post $post)
    {
        return $this->resourceForVersion($request, $post, [
            'v1' => \App\Http\Resources\V1\PostResource::class,
            'v2' => \App\Http\Resources\V2\PostResource::class,
        ]);
    }
}

// routes/api.php
Route::middleware('api.version')->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Teste
// curl -H "Accept: application/vnd.api.v1+json" http://api.example.com/posts
// curl -H "Api-Version: v2" http://api.example.com/posts
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
