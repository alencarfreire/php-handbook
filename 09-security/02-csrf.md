# 8.2 CSRF (Cross-Site Request Forgery)

## Resumo

> **CSRF (Cross-Site Request Forgery)** — ataque em que o atacante faz um usuário autenticado executar uma ação indesejada em outro site.
>
> **Proteção:** o Laravel valida o token CSRF sozinho pelo middleware `VerifyCsrfToken`. A diretiva Blade `@csrf` coloca um campo hidden com o token. No AJAX, use o header `X-CSRF-TOKEN`.
>
> **Importante:** cookies SameSite dão uma camada extra. API com tokens (Sanctum) não precisa de CSRF.

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
CSRF — falsificação de request entre sites. O atacante faz o usuário executar uma ação indesejada no site em que ele está autenticado.

**Como funciona o ataque:**
1. O usuário está logado em site.com
2. Abre evil.com
3. evil.com manda um POST para site.com
4. A request roda no nome do usuário

---

## Como funciona

**Exemplo de ataque CSRF:**

```html
<!-- evil.com -->
<form action="https://bank.com/transfer" method="POST">
    <input type="hidden" name="to" value="attacker">
    <input type="hidden" name="amount" value="10000">
</form>
<script>
    document.forms[0].submit();  // Envia sozinho
</script>

<!-- Se o usuário estiver logado no bank.com,
     a transferência roda sem ele perceber -->
```

**Proteção com token CSRF:**

```php
// ❌ Código VULNERÁVEL (sem CSRF)
Route::post('/transfer', function (Request $request) {
    $user = auth()->user();
    $user->balance -= $request->input('amount');
    // A transferência roda
});

// ✅ PROTEÇÃO: token CSRF (Laravel por padrão)
// No form
<form method="POST" action="/transfer">
    @csrf  <!-- Gera o campo hidden com o token -->
    <input type="number" name="amount">
    <button type="submit">Transferir</button>
</form>

// O Laravel valida o token sozinho pelo middleware
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class,  // Proteção CSRF
    ],
];
```

**Token CSRF no JavaScript:**

```javascript
// O Laravel coloca o token no meta tag sozinho
<meta name="csrf-token" content="{{ csrf_token() }}">

// O Axios coloca no header sozinho
// resources/js/bootstrap.js
window.axios.defaults.headers.common['X-CSRF-TOKEN'] = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute('content');

// Fetch na mão
fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});
```

---

## Quando usar

**CSRF precisa para:**
- ✅ Requests POST, PUT, DELETE
- ✅ Mudança de dados (transferência, compra, exclusão)
- ✅ Formulários web

**CSRF NÃO precisa para:**
- ❌ Requests GET (só leitura)
- ❌ API com tokens (Sanctum, Passport)
- ❌ API stateless

---

## Exemplo prático

**Exceção na checagem CSRF:**

```php
// app/Http/Middleware/VerifyCsrfToken.php
class VerifyCsrfToken extends Middleware
{
    // Fora da checagem CSRF
    protected $except = [
        'webhook/*',  // Webhooks de serviços externos
        'api/*',      // API endpoints (usam tokens)
    ];
}
```

**CSRF no AJAX:**

```javascript
// Componente Vue.js
export default {
    methods: {
        async submitForm() {
            try {
                const response = await axios.post('/api/posts', {
                    title: this.title,
                    body: this.body,
                });
                // Token CSRF entra sozinho pelo Axios
            } catch (error) {
                if (error.response.status === 419) {
                    alert('Token CSRF não bate. Recarregue a página.');
                }
            }
        }
    }
}
```

**Cookies SameSite (proteção extra):**

```php
// config/session.php
'same_site' => 'lax',  // Ou 'strict'

// Atributos SameSite:
// - 'strict' — cookie não vai em request de outro site (proteção forte)
// - 'lax' — cookie só vai em GET (equilíbrio)
// - 'none' — cookie vai sempre (precisa para iframe)
```

**Double Submit Cookie (alternativa):**

```php
// Método alternativo de proteção CSRF
class DoubleSubmitCsrfMiddleware
{
    public function handle($request, Closure $next)
    {
        if ($request->isMethod('POST')) {
            $cookieToken = $request->cookie('csrf_token');
            $headerToken = $request->header('X-CSRF-TOKEN');

            if ($cookieToken !== $headerToken) {
                abort(419, 'Token CSRF não bate');
            }
        }

        return $next($request);
    }
}
```

**Sanctum na API (sem CSRF):**

```php
// API usa tokens no lugar de sessões
Route::middleware('auth:sanctum')->post('/posts', function (Request $request) {
    // CSRF não precisa (stateless)
    return Post::create($request->all());
});

// O cliente manda Bearer token
fetch('/api/posts', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,  // No lugar do CSRF
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});
```

**Checagem de referer (extra):**

```php
class CheckRefererMiddleware
{
    public function handle($request, Closure $next)
    {
        $referer = $request->headers->get('referer');

        if ($referer && !str_starts_with($referer, config('app.url'))) {
            abort(403, 'Referer inválido');
        }

        return $next($request);
    }
}
```

**Testando CSRF:**

```php
// tests/Feature/CsrfTest.php
class CsrfTest extends TestCase
{
    public function test_post_without_csrf_token_fails(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Test',
            'body' => 'Content',
        ]);

        $response->assertStatus(419);  // Token CSRF não bate
    }

    public function test_post_with_csrf_token_succeeds(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Test',
            'body' => 'Content',
            '_token' => csrf_token(),
        ]);

        $response->assertStatus(302);
    }

    public function test_csrf_token_regenerates_on_login(): void
    {
        $oldToken = csrf_token();

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $newToken = csrf_token();

        $this->assertNotEquals($oldToken, $newToken);
    }
}
```

**CSRF no SPA (Single Page Application):**

```php
// routes/web.php
Route::get('/sanctum/csrf-cookie', function () {
    // Inicializa o cookie CSRF para o SPA
    return response()->noContent();
});

// JavaScript (primeira request)
await axios.get('/sanctum/csrf-cookie');

// Agora toda request vai com CSRF
await axios.post('/api/posts', data);
```

---

## Na entrevista

> "CSRF é falsificação de request de outro site. O Laravel protege com token CSRF (@csrf no form). O middleware VerifyCsrfToken checa o token em POST/PUT/DELETE. Header X-CSRF-TOKEN no AJAX (Axios coloca sozinho). Cookie SameSite é proteção extra. API com token (Sanctum) não precisa de CSRF (stateless). Exceção pelo $except no VerifyCsrfToken. Erro 419 se o token não bater. O token regenera no login."

---

## Exercícios práticos

### Exercício 1: Corrija o erro CSRF no AJAX

**Enunciado:** Você tem um componente Vue que manda POST e recebe 419. Corrija.

```javascript
// PostForm.vue
export default {
    data() {
        return {
            title: '',
            body: '',
        }
    },
    methods: {
        async submit() {
            const response = await fetch('/api/posts', {
                method: 'POST',
                body: JSON.stringify({
                    title: this.title,
                    body: this.body,
                }),
            });
        }
    }
}
```

<details>
<summary>Solução</summary>

```javascript
// Solução 1: Colocar o token CSRF no header
export default {
    data() {
        return {
            title: '',
            body: '',
        }
    },
    methods: {
        async submit() {
            const token = document.querySelector('meta[name="csrf-token"]').content;

            const response = await fetch('/api/posts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    title: this.title,
                    body: this.body,
                }),
            });
        }
    }
}

// No layout.blade.php (adicionar o meta tag)
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>

// Solução 2: Usar Axios (coloca o token sozinho)
// resources/js/bootstrap.js
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-CSRF-TOKEN'] =
    document.querySelector('meta[name="csrf-token"]').content;

// No componente
export default {
    methods: {
        async submit() {
            const response = await axios.post('/api/posts', {
                title: this.title,
                body: this.body,
            });
        }
    }
}
```
</details>

### Exercício 2: Configure exceções CSRF para webhook

**Enunciado:** Você tem um endpoint de webhook do Stripe que não passa na checagem CSRF. Configure a exceção.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/VerifyCsrfToken.php
<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * URIs fora da checagem CSRF
     */
    protected $except = [
        'webhooks/stripe',         // Endpoint específico
        'webhooks/*',              // Todos os webhooks
        'api/*',                   // Todas as rotas de API (se usam tokens)
    ];
}

// routes/web.php
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');

// StripeWebhookController
class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Valida a assinatura do Stripe no lugar do CSRF
        $signature = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $webhookSecret
            );

            // Processa o webhook
            match ($event->type) {
                'payment_intent.succeeded' => $this->handlePaymentSucceeded($event),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
                default => null,
            };

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
```
</details>

### Exercício 3: Implemente CSRF no SPA

**Enunciado:** Configure proteção CSRF para Single Page Application com Laravel Sanctum.

<details>
<summary>Solução</summary>

```php
// 1. Config do Sanctum
// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
    ))),

    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];

// 2. Rota para inicializar o cookie CSRF
// routes/web.php
Route::get('/sanctum/csrf-cookie', function () {
    return response()->noContent();
});

// 3. Setup do Axios no SPA
// src/api/client.js
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:8000',
    withCredentials: true, // Importante! Mandar cookies
});

// Inicializa CSRF antes da primeira request
let csrfInitialized = false;

api.interceptors.request.use(async (config) => {
    if (!csrfInitialized && config.method !== 'get') {
        await axios.get('http://localhost:8000/sanctum/csrf-cookie', {
            withCredentials: true,
        });
        csrfInitialized = true;
    }
    return config;
});

export default api;

// 4. Uso no componente
// src/components/LoginForm.vue
import api from '@/api/client';

export default {
    methods: {
        async login() {
            try {
                // Cookie CSRF entra sozinho
                const response = await api.post('/api/login', {
                    email: this.email,
                    password: this.password,
                });

                console.log('Logado:', response.data);
            } catch (error) {
                console.error('Login falhou:', error);
            }
        }
    }
}

// 5. config/cors.php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true, // Importante!
];
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
