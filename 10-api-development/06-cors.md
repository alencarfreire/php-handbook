# 9.6 CORS (Cross-Origin Resource Sharing)

## Resumo

> **CORS** — mecanismo que deixa o navegador fazer request para uma API em outro domínio.
>
> **Laravel:** config/cors.php com allowed_origins, allowed_methods, supports_credentials.
>
> **Importante:** Request preflight (OPTIONS), wildcard (*) só em API pública.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Configuração](#configuração)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
CORS (Cross-Origin Resource Sharing) — mecanismo que libera requests de outros domínios. Por padrão o navegador bloqueia request cross-origin por segurança.

**Problema sem CORS:**
```javascript
// Frontend em example.com
fetch('https://api.another-domain.com/posts')
// ❌ Erro de CORS: Access-Control-Allow-Origin missing
```

**Por que precisa:**
- Same-Origin Policy (política de mesma origem) do navegador bloqueia as requests
- Proteção contra CSRF e outros ataques
- Acesso controlado à API

---

## Como funciona

### Preflight Request

Para requests "complexas", o navegador manda OPTIONS primeiro:

```
OPTIONS /api/posts HTTP/1.1
Origin: https://example.com
Access-Control-Request-Method: POST
Access-Control-Request-Headers: Content-Type, Authorization

HTTP/1.1 204 No Content
Access-Control-Allow-Origin: https://example.com
Access-Control-Allow-Methods: POST, GET, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Max-Age: 86400
```

### Simple vs Preflight Requests

**Simple Request (sem preflight):**
- GET, HEAD, POST
- Content-Type: text/plain, multipart/form-data, application/x-www-form-urlencoded
- Só headers simples

**Preflight Request (precisa de OPTIONS):**
- PUT, DELETE, PATCH
- Content-Type: application/json
- Custom headers (Authorization, X-Custom-Header)

---

## Configuração

### Laravel (nativo)

```php
// config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],  // ou ['GET', 'POST', 'PUT', 'DELETE']

    'allowed_origins' => [
        'https://example.com',
        'https://app.example.com',
    ],

    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.example\.com$/',  // Todos os subdomínios
    ],

    'allowed_headers' => ['*'],  // ou ['Content-Type', 'Authorization']

    'exposed_headers' => ['X-Total-Count'],  // Headers visíveis para o cliente

    'max_age' => 86400,  // Cache do preflight em segundos

    'supports_credentials' => true,  // Cookies/Authorization
];

// Middleware aplicado automaticamente (HandleCors no middleware global)
```

### Wildcard (liberar para todos)

```php
'allowed_origins' => ['*'],  // ⚠️ Só para API pública!

// Com '*' você não pode:
'supports_credentials' => false,  // Credentials não funcionam com wildcard
```

---

## Quando usar

| Cenário | Precisa de CORS? |
|----------|------------|
| SPA em outro domínio | ✅ Sim |
| App mobile (WebView) | ✅ Sim |
| API pública | ✅ Sim |
| Same-origin (API e frontend no mesmo domínio) | ❌ Não |
| Requests server-to-server | ❌ Não |
| Postman/curl | ❌ Não (CORS só no navegador) |

---

## Exemplo prático

### Configuração de production

```php
// config/cors.php
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'https://example.com'),
        env('ADMIN_URL', 'https://admin.example.com'),
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
    ],

    'exposed_headers' => [
        'X-Total-Count',
        'X-Page-Count',
    ],

    'supports_credentials' => true,
    'max_age' => 86400,
];

// .env
FRONTEND_URL=https://app.example.com
ADMIN_URL=https://admin.example.com
```

### Multiple Origins via Environment

```php
'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', ''))),

// .env
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com,https://staging.example.com
```

### Request do frontend com credentials

```javascript
// Fetch correto com credentials
fetch('https://api.example.com/posts', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token,
    },
    credentials: 'include',  // Envia cookies
    body: JSON.stringify(data),
})
.then(response => response.json())
.catch(error => {
    if (error.message.includes('CORS')) {
        console.error('Erro de CORS — confira allowed_origins');
    }
});
```

### Debug de problemas de CORS

```php
// Middleware temporário de debug
class DebugCors
{
    public function handle(Request $request, Closure $next)
    {
        \Log::info('Debug CORS', [
            'origin' => $request->header('Origin'),
            'method' => $request->method(),
            'path' => $request->path(),
            'headers' => $request->headers->all(),
        ]);

        $response = $next($request);

        \Log::info('Headers da response CORS', [
            'allow_origin' => $response->headers->get('Access-Control-Allow-Origin'),
            'allow_methods' => $response->headers->get('Access-Control-Allow-Methods'),
        ]);

        return $response;
    }
}
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- CORS deixa o navegador fazer request cross-origin
- Sem CORS o navegador bloqueia request para outro domínio
- Proteção da Same-Origin Policy

**Configuração no Laravel:**
- `config/cors.php` — config do CORS
- `allowed_origins` — domínios liberados
- `allowed_methods` — métodos HTTP (GET, POST, PUT, DELETE)
- `supports_credentials` — para cookies/auth headers

**Preflight:**
- Request OPTIONS antes da request "complexa"
- O navegador checa as permissões
- Fica em cache por `max_age` segundos

**Headers:**
- `Access-Control-Allow-Origin` — origin liberado
- `Access-Control-Allow-Methods` — métodos liberados
- `Access-Control-Allow-Credentials` — para cookies

**Boas práticas:**
- Wildcard (*) só em API pública
- Origins explícitos em production
- `supports_credentials: true` para auth
- HandleCors middleware já vem automático no Laravel

---

## Exercícios práticos

### Exercício 1: Configure CORS para SPA multi-tenant

Você tem SPA em subdomínios: app.client1.com, app.client2.com. A API fica em api.example.com. Configure o CORS.

<details>
<summary>Solução</summary>

```php
// config/cors.php
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Pattern para todos os subdomínios app.*.com
    'allowed_origins_patterns' => [
        '/^https:\/\/app\.[a-z0-9-]+\.com$/',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Tenant-Id',  // Header customizado para o tenant
    ],

    'exposed_headers' => [
        'X-Total-Count',
    ],

    'supports_credentials' => true,
    'max_age' => 86400,
];

// Alternativa: allowed_origins dinâmico
// app/Http/Middleware/DynamicCors.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DynamicCors
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Checa o origin
        if ($this->isAllowedOrigin($origin)) {
            return $next($request)
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Tenant-Id')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        return $next($request);
    }

    private function isAllowedOrigin(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        // Checa o pattern
        return preg_match('/^https:\/\/app\.[a-z0-9-]+\.com$/', $origin) === 1;
    }
}
```
</details>

### Exercício 2: Trate o preflight CORS para custom headers

A API exige o custom header `X-Api-Key`. Configure o CORS para o preflight.

<details>
<summary>Solução</summary>

```php
// config/cors.php
return [
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        env('FRONTEND_URL'),
    ],

    // IMPORTANTE: incluir X-Api-Key em allowed_headers
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Api-Key',  // Custom header
    ],

    'supports_credentials' => true,
    'max_age' => 86400,
];

// Frontend
fetch('https://api.example.com/posts', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-Api-Key': 'your-api-key',  // Dispara o preflight
    },
    body: JSON.stringify(data),
});

// Middleware da API para validar a chave
namespace App\Http\Middleware;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        // Deixa o preflight passar
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $apiKey = $request->header('X-Api-Key');

        if (!$apiKey || !$this->isValidKey($apiKey)) {
            return response()->json([
                'error' => 'API key inválida ou ausente'
            ], 401);
        }

        return $next($request);
    }

    private function isValidKey(string $key): bool
    {
        return hash_equals(
            config('app.api_key'),
            $key
        );
    }
}
```
</details>

### Exercício 3: Debug de erro de CORS

O cliente recebe um erro de CORS. Crie um endpoint de debug e um middleware para diagnosticar.

<details>
<summary>Solução</summary>

```php
// routes/api.php (endpoint de debug)
Route::get('/debug/cors', function (Request $request) {
    $origin = $request->header('Origin');
    $allowedOrigins = config('cors.allowed_origins');

    return response()->json([
        'request' => [
            'origin' => $origin,
            'method' => $request->method(),
            'headers' => $request->headers->all(),
        ],
        'config' => [
            'allowed_origins' => $allowedOrigins,
            'allowed_methods' => config('cors.allowed_methods'),
            'allowed_headers' => config('cors.allowed_headers'),
            'supports_credentials' => config('cors.supports_credentials'),
        ],
        'diagnosis' => [
            'origin_allowed' => in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins),
            'recommendations' => $this->getRecommendations($origin, $allowedOrigins),
        ],
    ]);
})->middleware('cors');

// app/Http/Middleware/CorsDebugger.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CorsDebugger
{
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');

        // Log das requests preflight
        if ($request->isMethod('OPTIONS')) {
            Log::debug('Request preflight CORS', [
                'origin' => $origin,
                'method' => $request->header('Access-Control-Request-Method'),
                'headers' => $request->header('Access-Control-Request-Headers'),
                'path' => $request->path(),
            ]);
        }

        $response = $next($request);

        // Log dos headers da response
        Log::debug('Response CORS', [
            'origin' => $origin,
            'allow_origin' => $response->headers->get('Access-Control-Allow-Origin'),
            'allow_methods' => $response->headers->get('Access-Control-Allow-Methods'),
            'allow_headers' => $response->headers->get('Access-Control-Allow-Headers'),
            'allow_credentials' => $response->headers->get('Access-Control-Allow-Credentials'),
        ]);

        // Inclui info de debug no ambiente de dev
        if (config('app.debug')) {
            $response->headers->set('X-CORS-Debug', json_encode([
                'origin_received' => $origin,
                'origin_allowed' => $response->headers->get('Access-Control-Allow-Origin'),
                'config_origins' => config('cors.allowed_origins'),
            ]));
        }

        return $response;
    }
}

// Registrar no Kernel para debug
protected $middlewareGroups = [
    'api' => [
        // ...
        \App\Http\Middleware\CorsDebugger::class, // Só em dev
    ],
];

// Helper de debug no frontend
// Colar no console do DevTools
function debugCors(url) {
    fetch(url, { method: 'OPTIONS' })
        .then(response => {
            console.log('Response do preflight:');
            console.log('Allow-Origin:', response.headers.get('Access-Control-Allow-Origin'));
            console.log('Allow-Methods:', response.headers.get('Access-Control-Allow-Methods'));
            console.log('Allow-Headers:', response.headers.get('Access-Control-Allow-Headers'));
            console.log('Allow-Credentials:', response.headers.get('Access-Control-Allow-Credentials'));
        });
}

// debugCors('https://api.example.com/posts');
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
