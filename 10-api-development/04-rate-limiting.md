# 9.4 Rate Limiting

## Resumo

> **Rate Limiting** — limite de requests por usuário/IP num intervalo de tempo. Protege a API contra abuso.
>
> **Laravel:** middleware `throttle` (throttle:60,1 = 60 requests por minuto), `RateLimiter::for()` para limites customizados.
>
> **Importante:** Limites diferentes para leitura/escrita e usuário premium. Redis em sistema distribuído.

---

## Conteúdo

- [O que é](#o-que-é)
- [Uso básico](#uso-básico)
- [Limites customizados](#limites-customizados)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Rate Limiting — limite de requests por usuário/IP num intervalo de tempo. Protege a API contra abuso.

**Para quê:**
- Proteção contra DDoS
- Controle de carga
- Uso justo dos recursos
- Monetização (limites diferentes por plano)

---

## Uso básico

**Rate Limiting simples:**

```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    Route::apiResource('posts', PostController::class);
});
// 60 requests em 1 minuto
```

**Por usuário:**

```php
Route::middleware('throttle:100,1,user')->group(function () {
    // 100 requests por minuto POR USUÁRIO
});
```

**Headers na response:**

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
Retry-After: 45
```

---

## Limites customizados

**RouteServiceProvider:**

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('uploads', function (Request $request) {
        return $request->user()->isPremium()
            ? Limit::none()
            : Limit::perMinute(10);
    });

    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });
}
```

**Uso:**

```php
Route::middleware('throttle:api')->group(function () {
    // Limite customizado 'api'
});

Route::middleware('throttle:login')->post('/login', ...);
Route::middleware('throttle:uploads')->post('/upload', ...);
```

**Vários limites:**

```php
RateLimiter::for('strict', function (Request $request) {
    return [
        Limit::perMinute(100),
        Limit::perDay(1000),
    ];
});
```

---

## Quando usar

| Endpoint | Limite sugerido | Motivo |
|----------|-----------------|--------|
| Login/Register | 5-10/minuto | Proteção contra brute-force |
| Read | 100-1000/minuto | Request frequente é ok |
| Write | 30-60/minuto | Protege o banco |
| File Upload | 10-20/hora | Operação cara |
| Email/SMS | 5-10/hora | Serviço externo |

---

## Exemplo prático

### Limites diferentes por operação

```php
RateLimiter::for('api-read', function (Request $request) {
    return $request->user()?->isPremium()
        ? Limit::perMinute(1000)
        : Limit::perMinute(100);
});

RateLimiter::for('api-write', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});

// routes/api.php
Route::middleware('throttle:api-read')->get('/posts', [PostController::class, 'index']);
Route::middleware('throttle:api-write')->post('/posts', [PostController::class, 'store']);
```

### Response com info dos limites

```php
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $limiter = app(RateLimiter::class);
        $key = $request->user()?->id ?: $request->ip();

        $response->headers->set('X-RateLimit-Limit', 60);
        $response->headers->set('X-RateLimit-Remaining',
            $limiter->remaining($key, 60)
        );

        return $response;
    }
}
```

### Redis em sistemas distribuídos

```php
// .env
CACHE_DRIVER=redis

// Rate limiting usa Redis automaticamente
// Funciona certo em vários servidores
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Rate Limiting limita quantas requests cabem num intervalo de tempo
- Protege contra abuso e DDoS

**No Laravel:**
- `throttle:60,1` middleware (60 requests por minuto)
- `RateLimiter::for()` para limites customizados
- `Limit::perMinute()`, `Limit::perDay()`
- `by()` para agrupar (user_id ou IP)

**Headers:**
- `X-RateLimit-Limit` — máximo de requests
- `X-RateLimit-Remaining` — quanto ainda resta
- `Retry-After` — em quantos segundos pode tentar de novo

**Sistemas distribuídos:**
- Redis para estado compartilhado entre servidores
- Importante em apps com load balancer

**Boas práticas:**
- Limites diferentes para leitura/escrita
- Login/register com limite mais rígido
- Usuário premium = limite maior

---

## Exercícios práticos

### Exercício 1: Rate Limiting com planos

**Enunciado:** Crie um sistema com 3 planos: Free (100 req/min), Pro (500 req/min), Enterprise (ilimitado).

<details>
<summary>Solução</summary>

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    RateLimiter::for('api', function (Request $request) {
        $user = $request->user();

        if (!$user) {
            return Limit::perMinute(10)->by($request->ip());
        }

        return match($user->plan) {
            'enterprise' => Limit::none(),
            'pro' => Limit::perMinute(500)->by($user->id),
            'free' => Limit::perMinute(100)->by($user->id),
            default => Limit::perMinute(100)->by($user->id),
        };
    });
}

// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('posts', PostController::class);
});

// Model User
class User extends Authenticatable
{
    public function plan(): string
    {
        return $this->subscription?->plan ?? 'free';
    }

    public function isPremium(): bool
    {
        return in_array($this->plan(), ['pro', 'enterprise']);
    }
}
```
</details>

### Exercício 2: Response customizado quando passa do limite

**Enunciado:** Crie um middleware que devolve JSON com a info de quando pode repetir o request.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/CustomThrottleResponse.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class CustomThrottleResponse
{
    public function handle(Request $request, Closure $next, string $limiter = 'api')
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts = 60)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => 'Too Many Requests',
                'message' => "Limite de requests excedido. Tente de novo em {$seconds} segundos.",
                'retry_after' => $seconds,
                'limit' => $maxAttempts,
            ], 429)->header('Retry-After', $seconds);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining',
            RateLimiter::remaining($key, $maxAttempts)
        );

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return sha1(
            $request->user()?->id ?? $request->ip()
        );
    }
}

// app/Http/Kernel.php
protected $middlewareAliases = [
    'throttle.custom' => \App\Http\Middleware\CustomThrottleResponse::class,
];

// routes/api.php
Route::middleware('throttle.custom')->group(function () {
    Route::apiResource('posts', PostController::class);
});
```
</details>

### Exercício 3: Rate Limiting dinâmico por endpoint

**Enunciado:** Limites diferentes por operação: leitura (100/min), criação (20/min), exclusão (10/min).

<details>
<summary>Solução</summary>

```php
// app/Providers/RouteServiceProvider.php
public function boot(): void
{
    // Operações de leitura
    RateLimiter::for('api-read', function (Request $request) {
        return Limit::perMinute(100)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Operações de escrita
    RateLimiter::for('api-create', function (Request $request) {
        return Limit::perMinute(20)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Operações de exclusão
    RateLimiter::for('api-delete', function (Request $request) {
        return Limit::perMinute(10)->by(
            $request->user()?->id ?: $request->ip()
        );
    });

    // Proteção do login
    RateLimiter::for('login', function (Request $request) {
        return Limit::perMinute(5)->by($request->ip());
    });
}

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // Read
    Route::middleware('throttle:api-read')->group(function () {
        Route::get('/posts', [PostController::class, 'index']);
        Route::get('/posts/{post}', [PostController::class, 'show']);
    });

    // Create
    Route::middleware('throttle:api-create')->group(function () {
        Route::post('/posts', [PostController::class, 'store']);
    });

    // Delete
    Route::middleware('throttle:api-delete')->group(function () {
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    });
});

// Login
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

// Monitorar no Controller
class PostController extends Controller
{
    public function store(Request $request)
    {
        $limiter = app(RateLimiter::class);
        $key = 'api-create:' . ($request->user()?->id ?: $request->ip());

        Log::info('Rate limit check', [
            'key' => $key,
            'remaining' => $limiter->remaining($key, 20),
            'user_id' => $request->user()?->id,
        ]);

        // ... criar o post
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
