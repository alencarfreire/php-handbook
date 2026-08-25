# 9.1 REST API

## Resumo

> **REST API** — estilo de arquitetura para criar web services via HTTP.
>
> **O essencial:** GET (leitura), POST (criação), PUT/PATCH (atualização), DELETE (exclusão). Stateless, recursos via URI.
>
> **Laravel:** `Route::apiResource()`, API Resources para transformação, HTTP status codes (200, 201, 204, 404, 422).

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
REST (Representational State Transfer) — estilo de arquitetura para criar API. Usa métodos HTTP para operar sobre recursos.

**Princípios REST:**
- Stateless (sem estado)
- Interface uniforme
- Recursos via URI
- Métodos HTTP (GET, POST, PUT, DELETE)

---

## Como funciona

**Métodos HTTP:**

```
GET     /api/posts           # Lista de posts
GET     /api/posts/1         # Um post
POST    /api/posts           # Criar post
PUT     /api/posts/1         # Atualizar post (completo)
PATCH   /api/posts/1         # Atualizar post (parcial)
DELETE  /api/posts/1         # Apagar post
```

**Rotas de API no Laravel:**

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // RESTful resource
    Route::apiResource('posts', PostController::class);

    // Equivalente a:
    // Route::get('/posts', [PostController::class, 'index']);
    // Route::post('/posts', [PostController::class, 'store']);
    // Route::get('/posts/{post}', [PostController::class, 'show']);
    // Route::put('/posts/{post}', [PostController::class, 'update']);
    // Route::delete('/posts/{post}', [PostController::class, 'destroy']);
});
```

**API Controller:**

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{StorePostRequest, UpdatePostRequest};
use App\Http\Resources\PostResource;
use App\Models\Post;

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->paginate(20);

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request)
    {
        $post = Post::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return new PostResource($post);
    }

    public function show(Post $post)
    {
        return new PostResource($post->load('user', 'comments'));
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        return new PostResource($post);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}
```

**HTTP Status Codes:**

```php
200 OK                  // GET, PUT, PATCH com sucesso
201 Created             // POST com sucesso
204 No Content          // DELETE com sucesso
400 Bad Request         // Request inválido
401 Unauthorized        // Não autenticado
403 Forbidden           // Sem permissão
404 Not Found           // Não encontrado
422 Unprocessable       // Erro de validação
500 Internal Error      // Erro do servidor

// Exemplos
return response()->json($data, 200);
return response()->json($post, 201);
return response()->noContent();  // 204
return response()->json(['error' => 'Não encontrado'], 404);
```

---

## Quando usar

**REST para:**
- Operações CRUD
- APIs públicas
- Web services padrão

**Não REST (GraphQL) para:**
- Queries complexas de dados
- Muitos recursos aninhados
- Flexibilidade na escolha dos campos

---

## Exemplo prático

**REST API completa:**

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Endpoints públicos
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);

    // Autenticação
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Endpoints protegidos
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);

        // CRUD de posts
        Route::apiResource('posts', PostController::class)
            ->except(['index', 'show']);

        // Nested resources
        Route::apiResource('posts.comments', CommentController::class)
            ->shallow();
    });
});
```

**API Resource (transformação da response):**

```php
// app/Http/Resources/PostResource.php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->when($request->user(), $this->body),
            'published_at' => $this->published_at?->toISOString(),

            // Relationships
            'author' => new UserResource($this->whenLoaded('user')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            // Calculado
            'comments_count' => $this->when(
                $this->comments_count !== null,
                $this->comments_count
            ),

            // Links
            'links' => [
                'self' => route('posts.show', $this->id),
            ],
        ];
    }
}
```

**Filtro e ordenação:**

```php
class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::query();

        // Filtro
        if ($request->filled('category')) {
            $query->where('category_id', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        if ($request->filled('published')) {
            $query->where('published', $request->boolean('published'));
        }

        // Ordenação
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSort = ['id', 'title', 'created_at', 'views'];
        if (in_array($sortBy, $allowedSort)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginação
        $perPage = $request->input('per_page', 20);
        $posts = $query->paginate($perPage);

        return PostResource::collection($posts);
    }
}

// Exemplos de request:
// GET /api/posts?category=1&search=laravel&sort_by=title&sort_order=asc&per_page=50
```

**Recursos aninhados (nested):**

```php
// routes/api.php
Route::apiResource('posts.comments', CommentController::class);

// Gera:
// GET    /posts/{post}/comments
// POST   /posts/{post}/comments
// GET    /posts/{post}/comments/{comment}
// PUT    /posts/{post}/comments/{comment}
// DELETE /posts/{post}/comments/{comment}

// Controller
class CommentController extends Controller
{
    public function index(Post $post)
    {
        $comments = $post->comments()->with('user')->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(Request $request, Post $post)
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return new CommentResource($comment);
    }
}
```

**Tratamento de erros:**

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($request->is('api/*')) {
        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Recurso não encontrado'
            ], 404);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Falha na validação',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'message' => 'Acesso negado'
            ], 403);
        }

        return response()->json([
            'message' => 'Erro interno do servidor',
            'error' => app()->environment('local') ? $exception->getMessage() : null,
        ], 500);
    }

    return parent::render($request, $exception);
}
```

**HATEOAS (Hypermedia):**

```php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,

            // HATEOAS links
            '_links' => [
                'self' => [
                    'href' => route('posts.show', $this->id),
                ],
                'author' => [
                    'href' => route('users.show', $this->user_id),
                ],
                'comments' => [
                    'href' => route('posts.comments.index', $this->id),
                ],
                'edit' => $this->when(
                    $request->user()?->can('update', $this->resource),
                    ['href' => route('posts.update', $this->id)]
                ),
            ],
        ];
    }
}
```

**Versionamento:**

```php
// routes/api.php
Route::prefix('v1')->namespace('Api\V1')->group(function () {
    Route::apiResource('posts', PostController::class);
});

Route::prefix('v2')->namespace('Api\V2')->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Controllers em namespaces diferentes
// App\Http\Controllers\Api\V1\PostController
// App\Http\Controllers\Api\V2\PostController
```

---

## Na entrevista

> "REST usa métodos HTTP: GET (leitura), POST (criação), PUT/PATCH (atualização), DELETE (exclusão). Stateless — cada request é independente. Status codes: 200 OK, 201 Created, 204 No Content, 404 Not Found, 422 Validation. Laravel: apiResource para CRUD, Route Model Binding, API Resources para transformar a response. Filtro por query params. Nested resources para relações (posts/{post}/comments). HATEOAS — links na response. Versionamento por /v1, /v2."

---

## Exercícios práticos

### Exercício 1: Crie uma REST API para blog

**Enunciado:** Crie os endpoints da API para posts: listar, criar, atualizar, apagar. Adicione paginação e filtro por categoria.

<details>
<summary>Solução</summary>

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('posts', PostController::class);
    });

    // Endpoints públicos
    Route::get('/posts', [PostController::class, 'index']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
});

// app/Http/Controllers/Api/PostController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{StorePostRequest, UpdatePostRequest};
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::query()->with('user', 'category');

        // Filtro por categoria
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Busca
        if ($request->filled('search')) {
            $query->where('title', 'like', "%{$request->search}%");
        }

        // Ordenação
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $posts = $query->paginate($request->input('per_page', 15));

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request)
    {
        $post = Post::create([
            'user_id' => $request->user()->id,
            ...$request->validated(),
        ]);

        return new PostResource($post);
    }

    public function show(Post $post)
    {
        return new PostResource($post->load('user', 'category', 'comments'));
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        return new PostResource($post);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->noContent();
    }
}

// app/Http/Resources/PostResource.php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'published_at' => $this->published_at?->toISOString(),
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'comments_count' => $this->whenCounted('comments'),
        ];
    }
}
```

</details>

### Exercício 2: Adicione comentários aninhados

**Enunciado:** Crie endpoints para comentários de posts: `/api/posts/{post}/comments`. Implemente criar, listar e apagar comentários.

<details>
<summary>Solução</summary>

```php
// routes/api.php
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('posts.comments', CommentController::class)
        ->except(['update', 'show']);
});

// Endpoint público para listar
Route::get('/v1/posts/{post}/comments', [CommentController::class, 'index']);

// app/Http/Controllers/Api/CommentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\{Post, Comment};

class CommentController extends Controller
{
    public function index(Post $post)
    {
        $comments = $post->comments()
            ->with('user')
            ->latest()
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Post $post)
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return new CommentResource($comment);
    }

    public function destroy(Post $post, Comment $comment)
    {
        $this->authorize('delete', $comment);

        // Checa se o comentário pertence ao post
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comentário não encontrado'], 404);
        }

        $comment->delete();

        return response()->noContent();
    }
}

// app/Http/Resources/CommentResource.php
class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at->toISOString(),
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
        ];
    }
}
```

</details>

### Exercício 3: Implemente o tratamento de erros

**Enunciado:** Crie um tratamento único de erros para todos os endpoints da API. Devolva os status codes certos e erros estruturados.

<details>
<summary>Solução</summary>

```php
// app/Exceptions/Handler.php
namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    protected function handleApiException($request, Throwable $exception)
    {
        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => 'Falha na validação',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof ModelNotFoundException) {
            return response()->json([
                'message' => 'Recurso não encontrado',
            ], 404);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'message' => 'Endpoint não encontrado',
            ], 404);
        }

        if ($exception instanceof AuthenticationException) {
            return response()->json([
                'message' => 'Não autenticado',
            ], 401);
        }

        if ($exception instanceof AuthorizationException) {
            return response()->json([
                'message' => 'Acesso negado',
            ], 403);
        }

        // Erro genérico do servidor
        return response()->json([
            'message' => 'Erro interno do servidor',
            'error' => app()->environment('local') ? $exception->getMessage() : null,
            'trace' => app()->environment('local') ? $exception->getTraceAsString() : null,
        ], 500);
    }
}

// app/Http/Middleware/ForceJsonResponse.php
namespace App\Http\Middleware;

use Closure;

class ForceJsonResponse
{
    public function handle($request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}

// Registrar o middleware em app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\ForceJsonResponse::class,
        // ...
    ],
];
```

</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
