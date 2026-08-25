# 4.5 Rotas (Routing)

## Resumo

> **Routing** — sistema de rotas do Laravel. Liga URL a controller ou closure.
>
> **Arquivos:** routes/web.php (sessões, CSRF) e routes/api.php (stateless, throttle).
>
> **Importante:** Route Model Binding, Resource routes para CRUD, grupos com prefix/middleware/name, rate limiting.

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
Sistema de rotas do Laravel. Liga URL a controller ou closure. Suporta REST, parâmetros, middleware, grupos.

**Arquivos principais:**
- `routes/web.php` — rotas web (sessões, CSRF)
- `routes/api.php` — rotas de API (sem sessão, com throttle)

---

## Como funciona

**Sintaxe básica:**

```php
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Request GET
Route::get('/users', [UserController::class, 'index']);

// Request POST
Route::post('/users', [UserController::class, 'store']);

// Request PUT/PATCH
Route::put('/users/{id}', [UserController::class, 'update']);
Route::patch('/users/{id}', [UserController::class, 'update']);

// Request DELETE
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// Vários métodos
Route::match(['get', 'post'], '/form', [FormController::class, 'handle']);

// Qualquer método
Route::any('/debug', [DebugController::class, 'index']);

// Closure (sem controller)
Route::get('/hello', function () {
    return 'Olá, mundo';
});
```

**Parâmetros da rota:**

```php
// Parâmetro obrigatório
Route::get('/users/{id}', [UserController::class, 'show']);

// Parâmetro opcional
Route::get('/users/{id?}', [UserController::class, 'show']);

// Regex constraint
Route::get('/users/{id}', [UserController::class, 'show'])
    ->where('id', '[0-9]+');

Route::get('/posts/{slug}', [PostController::class, 'show'])
    ->where('slug', '[a-z0-9-]+');

// Vários constraints
Route::get('/users/{id}/posts/{postId}', [PostController::class, 'show'])
    ->where(['id' => '[0-9]+', 'postId' => '[0-9]+']);

// Constraints globais (no RouteServiceProvider)
Route::pattern('id', '[0-9]+');
```

**Rotas nomeadas:**

```php
// Definição
Route::get('/users/{id}', [UserController::class, 'show'])
    ->name('users.show');

// Uso
$url = route('users.show', ['id' => 1]);  // /users/1
return redirect()->route('users.show', ['id' => 1]);

// No Blade
<a href="{{ route('users.show', $user) }}">Ver usuário</a>
```

**Route Model Binding:**

```php
// Binding automático (por id)
Route::get('/users/{user}', function (User $user) {
    return $user->email;  // Laravel acha User::find($id) sozinho
});

// Por outro campo (slug)
Route::get('/posts/{post:slug}', function (Post $post) {
    return $post->title;  // Post::where('slug', $slug)->firstOrFail()
});

// Lógica customizada (no RouteServiceProvider)
Route::bind('post', function (string $value) {
    return Post::where('slug', $value)
        ->orWhere('id', $value)
        ->firstOrFail();
});
```

**Grupos de rotas:**

```php
// Prefix
Route::prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/posts', [AdminPostController::class, 'index']);
});
// /admin/users, /admin/posts

// Middleware
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// Name prefix
Route::name('admin.')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index'])
        ->name('users.index');  // admin.users.index
});

// Combinação
Route::prefix('api')
    ->middleware('auth:sanctum')
    ->name('api.')
    ->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->name('users.index');  // api.users.index → /api/users
    });
```

**Resource Routes:**

```php
// Rotas RESTful
Route::resource('posts', PostController::class);

// Gera:
// GET    /posts              index
// GET    /posts/create       create
// POST   /posts              store
// GET    /posts/{post}       show
// GET    /posts/{post}/edit  edit
// PUT    /posts/{post}       update
// DELETE /posts/{post}       destroy

// Só algumas actions
Route::resource('posts', PostController::class)
    ->only(['index', 'show']);

Route::resource('posts', PostController::class)
    ->except(['create', 'edit']);

// API resource (sem create/edit)
Route::apiResource('posts', PostController::class);
// Gera só: index, store, show, update, destroy
```

---

## Quando usar

**web.php vs api.php:**

| web.php | api.php |
|---------|---------|
| Sessões, CSRF | Sem sessões |
| Cookies | Stateless |
| Para apps web | Para API |
| Middleware: web | Middleware: api |

**Resource routes:**
- ✅ Use para CRUD padrão
- ❌ Não use para actions fora do padrão (crie rotas à parte)

---

## Exemplo prático

**Rotas de API RESTful:**

```php
// routes/api.php
use App\Http\Controllers\Api\{
    AuthController,
    ProductController,
    OrderController,
};

// Rotas públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Products API
    Route::apiResource('products', ProductController::class);

    // Orders API
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::post('/', [OrderController::class, 'store'])->name('store');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
    });
});
```

**Painel admin com prefix:**

```php
// routes/web.php
Route::prefix('admin')
    ->middleware(['auth', 'admin'])
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('users', AdminUserController::class);
        Route::resource('posts', AdminPostController::class);

        // Actions customizadas
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban'])
            ->name('users.ban');

        Route::post('/posts/{post}/publish', [AdminPostController::class, 'publish'])
            ->name('posts.publish');
    });

// URLs:
// /admin/dashboard → admin.dashboard
// /admin/users → admin.users.index
// /admin/users/5/ban → admin.users.ban
```

**Route Model Binding com lógica customizada:**

```php
// app/Providers/RouteServiceProvider.php
public function boot(): void
{
    // Bind de User por uuid
    Route::bind('user', function (string $value) {
        return User::where('uuid', $value)->firstOrFail();
    });

    // Bind de Post por slug ou id
    Route::bind('post', function (string $value) {
        return Post::where('slug', $value)
            ->orWhere('id', $value)
            ->firstOrFail();
    });
}

// Uso
Route::get('/users/{user}', [UserController::class, 'show']);
// /users/550e8400-e29b-41d4-a716-446655440000

Route::get('/posts/{post}', [PostController::class, 'show']);
// /posts/my-first-post ou /posts/123
```

**Subdomain routing:**

```php
// Subdomínios
Route::domain('{account}.myapp.com')->group(function () {
    Route::get('/dashboard', function (string $account) {
        return "Dashboard de {$account}";
    });
});

// tenant1.myapp.com/dashboard → "Dashboard de tenant1"
```

**Rate Limiting:**

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // API rate limit
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    // Custom limiter
    RateLimiter::for('uploads', function (Request $request) {
        return $request->user()->isPremium()
            ? Limit::none()
            : Limit::perMinute(10);
    });
}

// Uso
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('products', ProductController::class);
});

Route::post('/upload', [UploadController::class, 'store'])
    ->middleware('throttle:uploads');
```

**Fallback route:**

```php
// Tratamento de 404
Route::fallback(function () {
    return response()->json(['error' => 'Não encontrado'], 404);
});
```

**Redirect routes:**

```php
// Redirect
Route::redirect('/old-url', '/new-url', 301);

// Permanent redirect
Route::permanentRedirect('/old-url', '/new-url');
```

**View routes (sem controller):**

```php
// Só devolver a view
Route::view('/about', 'pages.about');

// Com dados
Route::view('/welcome', 'welcome', ['name' => 'Laravel']);
```

---

## Na entrevista

> "Rotas em routes/web.php (com CSRF, sessões) e routes/api.php (stateless). Route Model Binding acha o model sozinho por id ou outro campo. Resource routes para CRUD (apiResource sem create/edit). Grupos com prefix, middleware, name. Rate limiting com RateLimiter::for(). Rotas nomeadas com o helper route()."

---

## Exercícios práticos

### Exercício 1: Rotas de API com versionamento

**Enunciado:** Crie rotas de API para `Product` com versionamento v1 e v2. V1 usa CRUD padrão. V2 adiciona a action customizada `publish`.

<details>
<summary>Solução</summary>

```php
// routes/api.php
use App\Http\Controllers\Api\V1\ProductController as ProductV1Controller;
use App\Http\Controllers\Api\V2\ProductController as ProductV2Controller;

// API V1
Route::prefix('v1')
    ->middleware('auth:sanctum')
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource('products', ProductV1Controller::class);
    });

// API V2
Route::prefix('v2')
    ->middleware('auth:sanctum')
    ->name('api.v2.')
    ->group(function () {
        Route::apiResource('products', ProductV2Controller::class);

        // Action customizada
        Route::post('products/{product}/publish', [ProductV2Controller::class, 'publish'])
            ->name('products.publish');
    });

// Gera:
// POST /api/v1/products → api.v1.products.store
// GET  /api/v1/products → api.v1.products.index
// POST /api/v2/products/{product}/publish → api.v2.products.publish
```
</details>

### Exercício 2: Subdomain routing para multi-tenancy

**Enunciado:** Configure rotas para um app multi-tenant. Cada cliente tem o próprio subdomínio (tenant1.app.com, tenant2.app.com).

<details>
<summary>Solução</summary>

```php
// routes/web.php
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\UserController;

// Tenant subdomain
Route::domain('{tenant}.myapp.com')
    ->middleware(['web', 'identify.tenant'])
    ->name('tenant.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('users', UserController::class);
    });

// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->route('tenant');

        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        // Definir o tenant atual
        app()->instance('current.tenant', $tenant);

        return $next($request);
    }
}

// Uso:
// tenant1.myapp.com/dashboard → tenant.dashboard
// tenant1.myapp.com/users → tenant.users.index
```
</details>

### Exercício 3: Rate limiting com limites diferentes

**Enunciado:** Crie rate limiting para a API: usuários comuns — 60 requests/min, premium — sem limite, visitantes — 10 requests/min.

<details>
<summary>Solução</summary>

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

public function boot(): void
{
    // API rate limiter
    RateLimiter::for('api', function (Request $request) {
        if (!$request->user()) {
            // Visitantes: 10/min por IP
            return Limit::perMinute(10)->by($request->ip());
        }

        if ($request->user()->isPremium()) {
            // Premium: sem limite
            return Limit::none();
        }

        // Usuários comuns: 60/min
        return Limit::perMinute(60)->by($request->user()->id);
    });

    // Upload limiter
    RateLimiter::for('uploads', function (Request $request) {
        return $request->user()?->isPremium()
            ? Limit::perHour(1000)
            : Limit::perHour(10);
    });
}

// routes/api.php
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('products', ProductController::class);
});

Route::post('/upload', [UploadController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:uploads']);
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
