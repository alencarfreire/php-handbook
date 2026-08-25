# 8.1 XSS (Cross-Site Scripting)

## Resumo

> **XSS (Cross-Site Scripting)** — injeção de JavaScript malicioso na página para roubar cookies, tokens ou executar ações em nome do usuário.
>
> **Tipos:** Reflected (pela URL), Stored (no banco), DOM-based (pelo JavaScript).
>
> **Proteção:** Blade `{{ }}` escapa automaticamente, HTMLPurifier para rich text, Content Security Policy, HTTPOnly cookies.

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
XSS é injetar JavaScript malicioso na página. O atacante pode roubar cookies, tokens e executar ações em nome do usuário.

**Tipos de XSS:**
- **Reflected XSS** — pelos parâmetros da URL
- **Stored XSS** — gravado no banco
- **DOM-based XSS** — pelo JavaScript

---

## Como funciona

**Reflected XSS (pela URL):**

```php
// ❌ Código VULNERÁVEL
Route::get('/search', function (Request $request) {
    $query = $request->input('q');
    return "Resultados da busca: {$query}";
});

// Ataque:
// /search?q=<script>alert('XSS')</script>
// O JavaScript executa

// ✅ PROTEÇÃO: escape
Route::get('/search', function (Request $request) {
    $query = htmlspecialchars($request->input('q'), ENT_QUOTES, 'UTF-8');
    return "Resultados da busca: {$query}";
});

// Ou via Blade (escapa automaticamente)
return view('search', ['query' => $request->input('q')]);
// No Blade: Resultados: {{ $query }}
```

**Stored XSS (gravado no banco):**

```php
// ❌ Código VULNERÁVEL
class CommentController extends Controller
{
    public function store(Request $request)
    {
        Comment::create([
            'body' => $request->input('body'),  // Sem validação
        ]);
    }

    public function show(Comment $comment)
    {
        return view('comments.show', ['comment' => $comment]);
    }
}

// No Blade (sem escape)
{!! $comment->body !!}  // ❌ Executa o <script>

// O atacante envia:
// body: <script>fetch('/steal-token?token='+document.cookie)</script>

// ✅ PROTEÇÃO 1: Validação
public function store(Request $request)
{
    $validated = $request->validate([
        'body' => 'required|string|max:1000',
    ]);

    Comment::create($validated);
}

// ✅ PROTEÇÃO 2: Escape no Blade
{{ $comment->body }}  // htmlspecialchars() automático

// ✅ PROTEÇÃO 3: Limpar HTML (se precisar de rich text)
use Mews\Purifier\Facades\Purifier;

public function store(Request $request)
{
    Comment::create([
        'body' => Purifier::clean($request->input('body')),
    ]);
}
```

**DOM-based XSS:**

```javascript
// ❌ JavaScript VULNERÁVEL
const params = new URLSearchParams(window.location.search);
const message = params.get('msg');
document.getElementById('output').innerHTML = message;  // Perigoso!

// Ataque:
// ?msg=<img src=x onerror="alert('XSS')">

// ✅ PROTEÇÃO
document.getElementById('output').textContent = message;  // Seguro
```

---

## Quando usar

**Sempre se proteja de XSS:**
- ✅ Qualquer input do usuário
- ✅ Parâmetros da URL
- ✅ Formulários
- ✅ Dados da API

**O Laravel protege automaticamente:**
- Blade {{ }} escapa HTML
- Form Request valida
- Tokens CSRF

---

## Exemplo prático

**Saída segura no Blade:**

```blade
{{-- ✅ Escapa automaticamente --}}
<h1>{{ $post->title }}</h1>
<p>{{ $comment->body }}</p>

{{-- ❌ NÃO escapa (só para HTML confiável) --}}
{!! $post->body !!}

{{-- ✅ Rich text seguro --}}
{!! Purifier::clean($post->body) !!}

{{-- ✅ Escape em atributos --}}
<input type="text" value="{{ $user->name }}">
<a href="{{ $url }}">Link</a>

{{-- ❌ PERIGOSO: contexto JavaScript --}}
<script>
    var name = "{{ $user->name }}";  // Pode quebrar o JS
</script>

{{-- ✅ Seguro: JSON encode --}}
<script>
    var user = @json($user);  // Laravel helper
</script>
```

**HTMLPurifier para rich text:**

```bash
composer require mews/purifier
```

```php
// config/purifier.php (publicar)
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"

// Uso
use Mews\Purifier\Facades\Purifier;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        Post::create([
            'title' => $validated['title'],
            'body' => Purifier::clean($validated['body']),  // Limpeza
        ]);
    }
}

// No Blade
{!! $post->body !!}  // Seguro (já foi limpo)
```

**Content Security Policy (CSP):**

```php
// app/Http/Middleware/AddSecurityHeaders.php
class AddSecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Bloquear inline scripts
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'"
        );

        // Prevenir XSS
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Prevenir clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Bloquear MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}

// Registrar em Kernel.php
protected $middleware = [
    \App\Http\Middleware\AddSecurityHeaders::class,
];
```

**HTTPOnly cookies:**

```php
// config/session.php
'http_only' => true,  // JavaScript não consegue ler o cookie

// Setar cookie na mão
return response('Conteúdo')->cookie(
    'token',
    $value,
    $minutes = 60,
    $path = '/',
    $domain = null,
    $secure = true,  // Só HTTPS
    $httpOnly = true  // Proteção contra XSS
);
```

**Sanitizar o input do usuário:**

```php
class CommentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        // Remover tags HTML (se não precisar de rich text)
        $body = strip_tags($validated['body']);

        // Ou deixar só tags específicas
        $body = strip_tags($validated['body'], '<b><i><u><a>');

        Comment::create(['body' => $body]);
    }
}
```

**Proteção no Vue.js / React:**

```javascript
// Vue escapa automaticamente
<div>{{ message }}</div>  // Seguro

// v-html é perigoso
<div v-html="message"></div>  // ❌ vulnerabilidade XSS

// React escapa automaticamente
<div>{message}</div>  // Seguro

// dangerouslySetInnerHTML é perigoso
<div dangerouslySetInnerHTML={{__html: message}} />  // ❌ XSS
```

**Teste de XSS:**

```php
// tests/Feature/XssTest.php
class XssTest extends TestCase
{
    public function test_xss_payload_is_escaped(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->post('/comments', [
            'body' => $xssPayload,
        ]);

        $comment = Comment::latest()->first();

        // Checar se gravou como está
        $this->assertEquals($xssPayload, $comment->body);

        // Checar se escapou na saída
        $response = $this->get("/comments/{$comment->id}");
        $response->assertDontSee('<script>', false);  // false = não escapar na busca
        $response->assertSee('&lt;script&gt;', false);  // Versão escapada
    }
}
```

---

## Na entrevista

> "XSS é injetar JavaScript na página. Tipos: Reflected (pela URL), Stored (no banco), DOM-based (pelo JS). Proteção: Blade {{ }} escapa sozinho, {!! !!} não escapa. HTMLPurifier para rich text (Purifier::clean()). Content Security Policy bloqueia inline scripts. HTTPOnly cookies impedem roubo via JS. strip_tags() remove HTML. Sempre validar e escapar o input do usuário. @json() para passar dado pro JavaScript com segurança."

---

## Exercícios práticos

### Exercício 1: Corrija a vulnerabilidade XSS

O que está errado neste código? Corrija.

```php
Route::get('/search', function (Request $request) {
    $query = $request->input('q');
    return view('search', compact('query'));
});

// search.blade.php
<h1>Resultados da busca: {!! $query !!}</h1>
```

<details>
<summary>Solução</summary>

```php
// Problema: {!! !!} não escapa HTML. Dá XSS via ?q=<script>alert('XSS')</script>

// Solução 1: Usar {{ }} (escapa automaticamente)
Route::get('/search', function (Request $request) {
    $query = $request->input('q');
    return view('search', compact('query'));
});

// search.blade.php
<h1>Resultados da busca: {{ $query }}</h1>

// Solução 2: Validação e limpeza
Route::get('/search', function (Request $request) {
    $validated = $request->validate([
        'q' => 'required|string|max:255',
    ]);

    $query = strip_tags($validated['q']);
    return view('search', compact('query'));
});

// search.blade.php
<h1>Resultados da busca: {{ $query }}</h1>
```
</details>

### Exercício 2: Implemente um editor rich text seguro

Crie um controller para salvar HTML de um editor WYSIWYG com proteção contra XSS.

<details>
<summary>Solução</summary>

```php
// 1. Instalar HTMLPurifier
composer require mews/purifier

// 2. Publicar o config
php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"

// 3. PostController
use Mews\Purifier\Facades\Purifier;

class PostController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        // Limpar HTML de tags perigosas
        $cleanBody = Purifier::clean($validated['body'], [
            'HTML.Allowed' => 'p,b,i,u,a[href],ul,ol,li,strong,em',
        ]);

        $post = Post::create([
            'title' => $validated['title'],
            'body' => $cleanBody,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('posts.show', $post);
    }
}

// 4. No Blade (seguro, já foi limpo)
<div class="post-body">
    {!! $post->body !!}
</div>

// 5. config/purifier.php (configuração)
return [
    'default' => [
        'HTML.Allowed' => 'p,b,i,u,a[href|title],ul,ol,li,strong,em,h2,h3',
        'AutoFormat.RemoveEmpty' => true,
    ],
];
```
</details>

### Exercício 3: Adicione Content Security Policy

Implemente um middleware para adicionar headers CSP.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/ContentSecurityPolicy.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Header CSP
        $csp = [
            "default-src 'self'",
            "script-src 'self' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // Headers de segurança extras
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        return $response;
    }
}

// Registro em app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\ContentSecurityPolicy::class,
];

// Alternativa: usar o pacote
composer require spatie/laravel-csp

// config/csp.php
return [
    'enabled' => env('CSP_ENABLED', true),

    'policy' => [
        'default-src' => ['self'],
        'script-src' => ['self', 'https://cdn.jsdelivr.net'],
        'style-src' => ['self', 'unsafe-inline'],
    ],
];
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
