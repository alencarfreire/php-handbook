# 4.6 Middleware

## Resumo

> **Middleware** — filtros de request HTTP. Rodam antes/depois do controller.
>
> **Tipos:** Before (antes do controller), After (depois), Terminable (depois de enviar a response).
>
> **Importante:** Registro no Kernel.php (global, grupos, aliases), parâmetros com middleware('role:admin'), cadeia do middleware pipeline.

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
Middleware — filtros de request HTTP. Rodam antes/depois do controller. Servem para autenticação, log, CORS, etc.

**O essencial:**
- Processam a request antes do controller
- Podem alterar Request/Response
- Cadeia de middleware (middleware pipeline)

---

## Como funciona

**Estrutura do middleware:**

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAge
{
    // Roda ANTES do controller
    public function handle(Request $request, Closure $next)
    {
        // Checagem antes do controller
        if ($request->age < 18) {
            return redirect('home');
        }

        // Passa adiante
        return $next($request);
    }
}
```

**After Middleware (depois do controller):**

```php
class LogResponse
{
    public function handle(Request $request, Closure $next)
{
        // Primeiro roda o controller
        $response = $next($request);

        // Processa depois do controller
        Log::info('Response', [
            'status' => $response->status(),
            'content' => $response->getContent(),
        ]);

        return $response;
    }
}
```

**Parâmetros do middleware:**

```php
class CheckRole
{
    public function handle(Request $request, Closure $next, string $role)
    {
        if (!$request->user()->hasRole($role)) {
            abort(403);
        }

        return $next($request);
    }
}

// Uso
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');
```

**Registro do middleware:**

```php
// app/Http/Kernel.php
class Kernel extends HttpKernel
{
    // Middleware global (roda em TODAS as requests)
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

    // Grupos de middleware
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ],

        'api' => [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    // Aliases de middleware (para usar nas rotas)
    protected $middlewareAliases = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'role' => \App\Http\Middleware\CheckRole::class,
    ];
}
```

**Aplicando middleware:**

```php
// Na rota
Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('auth');

Route::get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth', 'role:admin']);

// No grupo
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// No controller (construtor)
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->only(['destroy']);
        $this->middleware('guest')->except(['logout']);
    }
}
```

---

## Quando usar

**Use middleware para:**
- Autenticação (auth, guest)
- Autorização (roles, permissões)
- Rate limiting
- CORS
- Log
- Cache de response
- Alterar request/response

**Não use para:**
- Regra de negócio (isso fica no service)
- Acesso direto ao banco (só checagens)

---

## Exemplo prático

**Checagem de role:**

```php
// app/Http/Middleware/CheckRole.php
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!$request->user()) {
            abort(401);
        }

        if (!$request->user()->hasAnyRole($roles)) {
            abort(403, 'Acesso negado');
        }

        return $next($request);
    }
}

// Registro no Kernel.php
protected $middlewareAliases = [
    'role' => \App\Http\Middleware\CheckRole::class,
];

// Uso
Route::middleware('role:admin,moderator')->group(function () {
    Route::get('/admin/users', [UserController::class, 'index']);
});
```

**API Token Authentication:**

```php
// app/Http/Middleware/ApiToken.php
class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-API-Token');

        if (!$token || !$this->isValidToken($token)) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        // Coloca o usuário no request
        $request->merge(['api_user' => $this->getUserByToken($token)]);

        return $next($request);
    }

    private function isValidToken(string $token): bool
    {
        return ApiToken::where('token', $token)
            ->where('expires_at', '>', now())
            ->exists();
    }
}
```

**CORS Middleware (customizado):**

```php
// app/Http/Middleware/Cors.php
class Cors
{
    public function handle(Request $request, Closure $next)
    {
        // Request preflight (OPTIONS)
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }

        // Request normal
        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

**Log de requests:**

```php
// app/Http/Middleware/LogRequests.php
class LogRequests
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Executa a request
        $response = $next($request);

        // Tempo de execução
        $duration = microtime(true) - $startTime;

        // Loga
        Log::info('Request processada', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $request->user()?->id,
            'status' => $response->status(),
            'duration' => round($duration * 1000, 2) . 'ms',
        ]);

        return $response;
    }
}
```

**Force JSON Response (para API):**

```php
// app/Http/Middleware/ForceJsonResponse.php
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        // Seta Accept: application/json
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}

// Aplica em todas as rotas de API
// app/Http/Kernel.php
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\ForceJsonResponse::class,
        'throttle:60,1',
    ],
];
```

**Tenant Middleware (multi-tenancy):**

```php
// app/Http/Middleware/IdentifyTenant.php
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        // Descobre o tenant pelo subdomínio
        $subdomain = explode('.', $request->getHost())[0];

        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();

        // Seta a conexão com o banco do tenant
        config([
            'database.connections.tenant.database' => "tenant_{$tenant->id}",
        ]);

        DB::purge('tenant');
        DB::reconnect('tenant');

        // Guarda no request
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
```

**Cache Response Middleware:**

```php
// app/Http/Middleware/CacheResponse.php
class CacheResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 3600)
    {
        // Só para GET
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $key = 'response:' . md5($request->fullUrl());

        // Checa o cache
        if ($cached = Cache::get($key)) {
            return response($cached['content'], $cached['status'])
                ->withHeaders($cached['headers']);
        }

        // Executa a request
        $response = $next($request);

        // Cacheia só respostas de sucesso
        if ($response->status() === 200) {
            Cache::put($key, [
                'content' => $response->getContent(),
                'status' => $response->status(),
                'headers' => $response->headers->all(),
            ], $ttl);
        }

        return $response;
    }
}

// Uso
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('cache:600');  // 10 minutos
```

**Comando para criar middleware:**

```bash
# Criar middleware
php artisan make:middleware CheckAge

# O middleware vai para app/Http/Middleware/CheckAge.php
```

**Terminable Middleware (roda depois de enviar a response):**

```php
class TerminableMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    // Roda DEPOIS de enviar a response para o cliente
    public function terminate(Request $request, $response)
    {
        // Operações pesadas (não bloqueiam a response)
        Log::info('Response enviada ao cliente');

        // Analytics
        Analytics::track($request, $response);
    }
}
```

---

## Na entrevista

> "Middleware processa a request antes e depois do controller. handle($request, $next) é o método principal. Global fica em $middleware, grupos em $middlewareGroups, aliases em $middlewareAliases. Você aplica com ->middleware() na rota ou no construtor do controller. Parâmetro entra assim: middleware('role:admin'). Terminable middleware com terminate() roda depois de enviar a response. Uso para auth, role, rate limiting, log, CORS."

---

## Exercícios práticos

### Exercício 1: Crie um middleware de checagem de assinatura

**Enunciado:** O usuário precisa ter assinatura ativa para acessar recursos premium. Se a assinatura expirou, devolve 403.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/CheckSubscription.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscription
{
    public function handle(Request $request, Closure $next, string $plan = null)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Não autorizado'], 401);
        }

        // Checa se a assinatura está ativa
        if (!$user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'Assinatura necessária',
                'message' => 'Faça upgrade para acessar este recurso'
            ], 403);
        }

        // Checa o plano específico (se passou)
        if ($plan && !$user->hasSubscription($plan)) {
            return response()->json([
                'error' => 'Upgrade de plano necessário',
                'message' => "Este recurso exige o plano {$plan}"
            ], 403);
        }

        return $next($request);
    }
}

// Registro em app/Http/Kernel.php
protected $middlewareAliases = [
    'subscription' => \App\Http\Middleware\CheckSubscription::class,
];

// Uso
Route::middleware(['auth:sanctum', 'subscription'])->group(function () {
    Route::get('/premium/features', [PremiumController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'subscription:pro'])->group(function () {
    Route::get('/pro/analytics', [AnalyticsController::class, 'index']);
});
```
</details>

### Exercício 2: Implemente Terminable Middleware para analytics

**Enunciado:** Crie um middleware que manda as requests para analytics DEPOIS de enviar a response ao cliente (para não atrasar a response).

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/TrackAnalytics.php
namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;

class TrackAnalytics
{
    public function __construct(
        private AnalyticsService $analytics
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Before: guarda o horário de início
        $request->attributes->set('start_time', microtime(true));

        return $next($request);
    }

    // Roda DEPOIS de enviar a response para o cliente
    public function terminate(Request $request, $response)
    {
        // Tempo de execução
        $duration = microtime(true) - $request->attributes->get('start_time');

        // Envia para analytics (não bloqueia a response)
        $this->analytics->track([
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $response->status(),
            'duration' => round($duration * 1000, 2), // ms
            'timestamp' => now(),
        ]);
    }
}

// Registro no Kernel.php (global)
protected $middleware = [
    \App\Http\Middleware\TrackAnalytics::class,
];

// app/Services/AnalyticsService.php
class AnalyticsService
{
    public function track(array $data): void
    {
        // Manda para a queue (processamento assíncrono)
        dispatch(new TrackAnalyticsJob($data));
    }
}
```
</details>

### Exercício 3: Configure CORS middleware com whitelist de origens

**Enunciado:** Crie um CORS middleware que só aceita requests de origens confiáveis do config.

<details>
<summary>Solução</summary>

```php
// config/cors.php
return [
    'allowed_origins' => [
        'https://myapp.com',
        'https://admin.myapp.com',
        'http://localhost:3000', // Dev
    ],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],

    'exposed_headers' => ['X-Total-Count'],

    'max_age' => 86400, // 24 horas
];

// app/Http/Middleware/Cors.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');
        $allowedOrigins = config('cors.allowed_origins');

        // Checa o origin
        if (!in_array($origin, $allowedOrigins)) {
            if ($request->isMethod('OPTIONS')) {
                return response('', 403);
            }
        }

        // Request preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods')))
                ->header('Access-Control-Allow-Headers', implode(', ', config('cors.allowed_headers')))
                ->header('Access-Control-Max-Age', config('cors.max_age'));
        }

        // Request normal
        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $origin)
            ->header('Access-Control-Allow-Methods', implode(', ', config('cors.allowed_methods')))
            ->header('Access-Control-Allow-Headers', implode(', ', config('cors.allowed_headers')))
            ->header('Access-Control-Expose-Headers', implode(', ', config('cors.exposed_headers')));
    }
}

// Registro para API
protected $middlewareGroups = [
    'api' => [
        \App\Http\Middleware\Cors::class,
        'throttle:api',
    ],
];
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
