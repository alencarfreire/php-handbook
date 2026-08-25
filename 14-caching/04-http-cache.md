# 14.4 HTTP Cache

## Resumo

> **HTTP Cache** — cache no nível de browser/CDN/proxy via HTTP headers.
>
> **Cache-Control:** `public` (em todo lugar), `private` (só no browser), `max-age` (TTL), `no-cache` (revalidate), `no-store` (não cacheia). **ETag** — fingerprint do conteúdo, 304 Not Modified se não mudou. **Last-Modified** — alternativa ao ETag.
>
> Static assets: `max-age=31536000, immutable` com hash na URL. Dynamic HTML: `private, no-cache`. CDN: `s-maxage` para a CDN, `max-age` para o browser. Vary: caches diferentes para headers diferentes (language, user-agent).

---

## Conteúdo

- [O que é](#o-que-é)
- [Cache-Control Header](#cache-control-header)
- [Exemplos de Cache-Control](#exemplos-de-cache-control)
- [ETag](#etag-entity-tag)
- [Last-Modified](#last-modified)
- [CDN Cache](#cdn-cache)
- [Browser Cache Busting](#browser-cache-busting)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**HTTP Cache:**
Cache no nível HTTP (browser, CDN, proxy).

**Para quê:**
- Diminuir latency (não ir no servidor)
- Reduzir banda
- Reduzir carga no servidor

**Níveis:**
1. Browser Cache
2. CDN Cache
3. Reverse Proxy Cache (Varnish, Nginx)

---

## Cache-Control Header

**Diretivas básicas:**

```php
// Laravel Response
return response($content)
    ->header('Cache-Control', 'public, max-age=3600');
```

**Diretivas:**

```
public         — pode cachear em todo lugar (browser, CDN, proxy)
private        — só no browser (não CDN/proxy)
no-cache       — checa com o servidor (revalidation)
no-store       — não cacheia de jeito nenhum
max-age=3600   — cache por 3600 segundos
s-maxage=7200  — para shared caches (CDN, proxy)
must-revalidate — depois de expirar, revalidate é obrigatório
immutable      — não muda (perfeito para assets com hash)
```

---

## Exemplos de Cache-Control

### 1. Static Assets (CSS, JS, Images)

```php
// Cacheia para sempre (com hash na URL)
return response()->file($path)
    ->header('Cache-Control', 'public, max-age=31536000, immutable');

// URL: /css/app.abc123.css (o hash muda quando o arquivo muda)
```

**Vite/Laravel Mix:**

```php
// resources/views/layouts/app.blade.php
@vite(['resources/css/app.css', 'resources/js/app.js'])

// Gera: /build/assets/app-abc123.css
```

---

### 2. Dynamic HTML

```php
// Não cacheia (dado do usuário)
return view('dashboard')
    ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
```

---

### 3. Páginas públicas

```php
// Cacheia por 1 hora
return view('blog.post', ['post' => $post])
    ->header('Cache-Control', 'public, max-age=3600');
```

---

### 4. API Responses

```php
// Cacheia por 5 minutos
return response()->json($data)
    ->header('Cache-Control', 'public, max-age=300');
```

---

## ETag (Entity Tag)

**O que é:**
Fingerprint do conteúdo. Se o conteúdo não mudou, devolve 304 Not Modified.

**Algoritmo:**

```
1. Client → Server: GET /page
2. Server → Client: 200 OK, ETag: "abc123"
3. Client guarda o ETag

4. Client → Server: GET /page, If-None-Match: "abc123"
5. Server checa o ETag
   - Se for o mesmo → 304 Not Modified (sem body)
   - Se mudou → 200 OK, ETag: "def456"
```

**Laravel:**

```php
$content = view('blog.post', ['post' => $post])->render();
$etag = md5($content);

if (request()->header('If-None-Match') === $etag) {
    return response('', 304);
}

return response($content)
    ->header('ETag', $etag)
    ->header('Cache-Control', 'public, max-age=3600');
```

---

## Last-Modified

**Alternativa ao ETag:**

```php
$post = Post::find($id);
$lastModified = $post->updated_at->toRfc7231String();

if (request()->header('If-Modified-Since') === $lastModified) {
    return response('', 304);
}

return view('blog.post', ['post' => $post])
    ->header('Last-Modified', $lastModified)
    ->header('Cache-Control', 'public, max-age=3600');
```

---

## Laravel Response Cache Package

**Composer:**

```bash
composer require spatie/laravel-responsecache
```

**Middleware:**

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \Spatie\ResponseCache\Middlewares\CacheResponse::class,
    ],
];
```

**Config:**

```php
// config/responsecache.php
return [
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),
    'cache_lifetime_in_seconds' => 60 * 60 * 24 * 7,  // 1 semana
    'cache_profile' => CacheAllSuccessfulGetRequests::class,
];
```

**Uso:**

```php
// Todo GET request entra no cache sozinho
Route::get('/blog/{post}', [PostController::class, 'show']);

// Invalidate na mão
ResponseCache::forget('/blog/post-1');

// Ou flush de tudo
ResponseCache::flush();
```

---

## CDN Cache

**CloudFlare, AWS CloudFront, Fastly:**

```php
// Cacheia na CDN
return response($content)
    ->header('Cache-Control', 'public, s-maxage=86400, max-age=3600');

// s-maxage — para a CDN (24 horas)
// max-age — para o browser (1 hora)
```

**Purge do cache da CDN:**

```php
// CloudFlare API
Http::post('https://api.cloudflare.com/client/v4/zones/{zone_id}/purge_cache', [
    'files' => ['https://example.com/blog/post-1'],
]);
```

---

## Vary Header

**O cache depende do header:**

```php
// Caches diferentes por Accept-Language
return response($content)
    ->header('Cache-Control', 'public, max-age=3600')
    ->header('Vary', 'Accept-Language');

// Caches diferentes para desktop/mobile
return response($content)
    ->header('Vary', 'User-Agent');
```

---

## Browser Cache Busting

**Problema:**
Você atualizou o CSS/JS, mas o browser usa o cache antigo.

**Solução: hash na URL**

```php
// Laravel Mix/Vite
mix('css/app.css')  // /css/app.css?id=abc123

// Se o arquivo muda → hash novo → URL nova → o browser baixa de novo
```

---

## Reverse Proxy Cache (Varnish/Nginx)

**Nginx:**

```nginx
proxy_cache_path /var/cache/nginx levels=1:2 keys_zone=my_cache:10m max_size=1g;

server {
    location / {
        proxy_cache my_cache;
        proxy_cache_valid 200 1h;
        proxy_cache_key "$scheme$request_method$host$request_uri";
        proxy_pass http://backend;

        # Adiciona header com o status do cache
        add_header X-Cache-Status $upstream_cache_status;
    }
}
```

**Laravel Application:**

```php
// Só setar o Cache-Control
return response($content)
    ->header('Cache-Control', 'public, max-age=3600');

// O Nginx cacheia sozinho
```

---

## Cache Warming

**Aquecer o cache depois do deploy:**

```php
class WarmHttpCacheCommand extends Command
{
    public function handle()
    {
        $urls = [
            'https://example.com/',
            'https://example.com/blog',
            'https://example.com/about',
        ];

        foreach ($urls as $url) {
            Http::get($url);  // Aquecer o cache
            $this->info("Aquecido: {$url}");
        }
    }
}

// Deploy script
php artisan cache:warm-http
```

---

## Boas práticas

```
✓ Static assets: public, max-age=31536000, immutable
✓ Dynamic HTML: private, no-cache
✓ Páginas públicas: public, max-age=3600
✓ API: public, max-age=300
✓ ETag ou Last-Modified para revalidation
✓ Hash na URL para cache busting (Laravel Mix/Vite)
✓ CDN para static assets
✓ Vary header para versões diferentes (language, user-agent)
✓ Monitoring: cache hit rate
✓ Purge da CDN depois do deploy
```

---

## Segurança

**Não cacheie:**
- Dado pessoal (private)
- Páginas sensíveis (no-store)
- CSRF tokens (no-cache)

```php
// Dado pessoal
return view('profile')
    ->header('Cache-Control', 'private, no-store');

// Páginas com formulário
return view('checkout')
    ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
```

---

## Na entrevista

> "HTTP Cache é cache no nível de browser/CDN/proxy. Cache-Control: public (em todo lugar), private (só no browser), max-age (TTL), no-cache (revalidate), no-store (não cacheia). ETag: fingerprint do conteúdo, 304 Not Modified se não mudou. Last-Modified é a alternativa. Static assets: max-age=31536000, immutable com hash na URL. Dynamic HTML: private, no-cache. CDN: s-maxage para a CDN, max-age para o browser. Vary: caches diferentes para headers diferentes. No Laravel: package Response Cache, Vite/Mix para cache busting. Boas práticas: estratégia diferente por tipo de conteúdo, não cacheia dado pessoal."

---

## Exercícios práticos

### Exercício 1: Middleware para ETag e Conditional Requests

**Enunciado:** Crie um middleware que gera ETag para as responses e trata os headers If-None-Match (304 Not Modified).

<details>
<summary>Solução</summary>

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ETagMiddleware
{
    /**
     * Processa o request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Só GET/HEAD com sucesso
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return $response;
        }

        if ($response->status() !== 200) {
            return $response;
        }

        // Gera o ETag a partir do conteúdo
        $content = $response->getContent();
        $etag = md5($content);

        // Seta o ETag header
        $response->setEtag($etag);

        // Checa If-None-Match
        $requestEtag = $request->header('If-None-Match');

        if ($requestEtag === $etag) {
            // Conteúdo não mudou — devolve 304
            $response->setNotModified();
        }

        return $response;
    }
}

// Registro no Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\ETagMiddleware::class,
    ],
];

// Versão avançada com weak ETags e exclusões
class AdvancedETagMiddleware
{
    /**
     * Routes que não devem usar ETag
     */
    protected array $except = [
        'admin/*',
        'api/auth/*',
    ];

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Checa exclusões
        if ($this->shouldExclude($request)) {
            return $response;
        }

        // Só GET/HEAD
        if (!$request->isMethodCacheable()) {
            return $response;
        }

        // Só responses de sucesso
        if (!$response->isSuccessful()) {
            return $response;
        }

        $content = $response->getContent();

        if (empty($content)) {
            return $response;
        }

        // Strong ETag (match exato do conteúdo)
        $etag = '"' . md5($content) . '"';

        // Ou Weak ETag (match semântico)
        // $etag = 'W/"' . md5($content) . '"';

        $response->headers->set('ETag', $etag);

        // Cache-Control para ETag
        if (!$response->headers->has('Cache-Control')) {
            $response->headers->set('Cache-Control', 'private, must-revalidate');
        }

        // Checa If-None-Match
        $requestEtag = $request->header('If-None-Match');

        if ($requestEtag === $etag) {
            $response->setNotModified();
        }

        return $response;
    }

    protected function shouldExclude(Request $request): bool
    {
        foreach ($this->except as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}

// Controller com ETag explícito
class BlogController extends Controller
{
    public function show(Post $post)
    {
        $content = view('blog.post', compact('post'))->render();
        $etag = md5($post->updated_at . $content);

        // Checa If-None-Match
        if (request()->header('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag);
        }

        return response($content)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=3600')
            ->header('Last-Modified', $post->updated_at->toRfc7231String());
    }
}
```
</details>

### Exercício 2: Response Cache Service com CDN Purge

**Enunciado:** Implemente um service que cacheia responses e consegue fazer purge do cache da CDN (CloudFlare).

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ResponseCacheService
{
    private const CACHE_PREFIX = 'response_cache:';
    private const DEFAULT_TTL = 3600; // 1 hora

    /**
     * Pega o response do cache ou cria um novo
     */
    public function remember(string $url, callable $callback, int $ttl = null): string
    {
        $cacheKey = $this->getCacheKey($url);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Guarda o response no cache
     */
    public function put(string $url, string $content, int $ttl = null): void
    {
        $cacheKey = $this->getCacheKey($url);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        Cache::put($cacheKey, $content, $ttl);
    }

    /**
     * Invalida o cache da URL
     */
    public function forget(string $url): void
    {
        $cacheKey = $this->getCacheKey($url);
        Cache::forget($cacheKey);
    }

    /**
     * Flush de todo o response cache
     */
    public function flush(): void
    {
        // Para Redis com tags
        Cache::tags(['response_cache'])->flush();
    }

    /**
     * Purge do cache da CDN CloudFlare
     */
    public function purgeCdn(array $urls): array
    {
        $zoneId = config('services.cloudflare.zone_id');
        $apiToken = config('services.cloudflare.api_token');

        if (!$zoneId || !$apiToken) {
            return ['success' => false, 'error' => 'CloudFlare não configurada'];
        }

        // Converte URLs relativas em absolutas
        $absoluteUrls = array_map(function ($url) {
            if (!str_starts_with($url, 'http')) {
                $url = config('app.url') . $url;
            }
            return $url;
        }, $urls);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiToken}",
                'Content-Type' => 'application/json',
            ])->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                'files' => $absoluteUrls,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'purged' => count($absoluteUrls)];
            }

            return [
                'success' => false,
                'error' => $response->json('errors.0.message', 'Erro desconhecido'),
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Purge de tudo na CloudFlare
     */
    public function purgeAllCdn(): array
    {
        $zoneId = config('services.cloudflare.zone_id');
        $apiToken = config('services.cloudflare.api_token');

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiToken}",
                'Content-Type' => 'application/json',
            ])->post("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache", [
                'purge_everything' => true,
            ]);

            return ['success' => $response->successful()];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getCacheKey(string $url): string
    {
        return self::CACHE_PREFIX . md5($url);
    }
}

// Middleware para cache automático da response
namespace App\Http\Middleware;

use App\Services\ResponseCacheService;
use Closure;
use Illuminate\Http\Request;

class CacheResponse
{
    public function __construct(
        private ResponseCacheService $cacheService
    ) {}

    public function handle(Request $request, Closure $next, int $ttl = 3600)
    {
        // Só GET requests
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        // Só para visitantes (não cacheia dado pessoal)
        if ($request->user()) {
            return $next($request);
        }

        $url = $request->fullUrl();

        $content = $this->cacheService->remember($url, function () use ($next, $request) {
            $response = $next($request);
            return $response->getContent();
        }, $ttl);

        return response($content)
            ->header('Cache-Control', "public, max-age={$ttl}")
            ->header('X-Cache', 'HIT');
    }
}

// Observer para purge automático quando o conteúdo muda
namespace App\Observers;

use App\Models\Post;
use App\Services\ResponseCacheService;

class PostObserver
{
    public function __construct(
        private ResponseCacheService $cacheService
    ) {}

    public function saved(Post $post): void
    {
        $urls = [
            route('blog.show', $post),
            route('blog.index'),
            route('home'),
        ];

        // Invalida o cache local
        foreach ($urls as $url) {
            $this->cacheService->forget($url);
        }

        // Purge da CDN
        $this->cacheService->purgeCdn($urls);
    }

    public function deleted(Post $post): void
    {
        $this->saved($post);
    }
}

// Command de purge
class PurgeCacheCommand extends Command
{
    protected $signature = 'cache:purge {--cdn : Purge CDN cache} {--all : Purge all}';
    protected $description = 'Limpa o response cache e, se quiser, a CDN';

    public function handle(ResponseCacheService $cacheService)
    {
        // Purge do cache local
        $cacheService->flush();
        $this->info('Cache local limpo');

        // Purge da CDN
        if ($this->option('cdn')) {
            if ($this->option('all')) {
                $result = $cacheService->purgeAllCdn();
            } else {
                $urls = [
                    config('app.url'),
                    config('app.url') . '/blog',
                ];
                $result = $cacheService->purgeCdn($urls);
            }

            if ($result['success']) {
                $this->info('Cache da CDN limpo');
            } else {
                $this->error('Falha no purge da CDN: ' . ($result['error'] ?? 'Desconhecido'));
            }
        }
    }
}
```
</details>

### Exercício 3: Smart Cache Headers Manager

**Enunciado:** Crie um service que coloca os Cache-Control headers certos conforme o tipo de conteúdo.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

class CacheHeadersManager
{
    /**
     * Config dos cache headers por tipo de conteúdo
     */
    private array $profiles = [
        'static_assets' => [
            'cache_control' => 'public, max-age=31536000, immutable',
            'patterns' => ['/build/*', '/storage/*', '*.css', '*.js', '*.png', '*.jpg', '*.woff'],
        ],
        'public_pages' => [
            'cache_control' => 'public, max-age=3600',
            'patterns' => ['/blog/*', '/about', '/contact'],
        ],
        'api_responses' => [
            'cache_control' => 'public, max-age=300',
            'patterns' => ['/api/public/*'],
        ],
        'private_pages' => [
            'cache_control' => 'private, no-cache, no-store, must-revalidate',
            'patterns' => ['/dashboard/*', '/profile/*', '/admin/*'],
        ],
        'no_cache' => [
            'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'patterns' => ['/checkout/*', '/payment/*'],
        ],
    ];

    /**
     * Aplica os cache headers na response
     */
    public function apply($response, string $url): void
    {
        $profile = $this->detectProfile($url);

        if ($profile) {
            $response->header('Cache-Control', $profile['cache_control']);

            // Vary header para versões diferentes
            if ($this->shouldVary($url)) {
                $response->header('Vary', 'Accept-Language, User-Agent');
            }
        }
    }

    /**
     * Detecta o perfil da URL
     */
    private function detectProfile(string $url): ?array
    {
        foreach ($this->profiles as $name => $profile) {
            foreach ($profile['patterns'] as $pattern) {
                if ($this->matchesPattern($url, $pattern)) {
                    return $profile;
                }
            }
        }

        return null;
    }

    private function matchesPattern(string $url, string $pattern): bool
    {
        // Checagem simples de wildcards
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return preg_match($regex, $url) === 1;
    }

    private function shouldVary(string $url): bool
    {
        // Vary para páginas multilíngues
        return str_starts_with($url, '/blog/') || str_starts_with($url, '/docs/');
    }
}

// Middleware
namespace App\Http\Middleware;

use App\Services\CacheHeadersManager;
use Closure;
use Illuminate\Http\Request;

class SetCacheHeaders
{
    public function __construct(
        private CacheHeadersManager $cacheManager
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Aplica os cache headers
        $this->cacheManager->apply($response, $request->path());

        return $response;
    }
}

// Response Macro para facilitar
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Response;

class ResponseMacroServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Macro para static assets
        Response::macro('cacheForever', function () {
            return $this->header('Cache-Control', 'public, max-age=31536000, immutable');
        });

        // Macro para páginas públicas
        Response::macro('cachePublic', function (int $seconds = 3600) {
            return $this->header('Cache-Control', "public, max-age={$seconds}");
        });

        // Macro para páginas privadas
        Response::macro('cachePrivate', function (int $seconds = 3600) {
            return $this->header('Cache-Control', "private, max-age={$seconds}");
        });

        // Macro para no-cache
        Response::macro('noCache', function () {
            return $this->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        });

        // Macro para CDN
        Response::macro('cacheCdn', function (int $cdnSeconds = 86400, int $browserSeconds = 3600) {
            return $this->header(
                'Cache-Control',
                "public, s-maxage={$cdnSeconds}, max-age={$browserSeconds}"
            );
        });
    }
}

// Uso no controller
class AssetController extends Controller
{
    public function css(string $filename)
    {
        $content = file_get_contents(public_path("css/{$filename}"));

        return response($content, 200, [
            'Content-Type' => 'text/css',
        ])->cacheForever();
    }
}

class BlogController extends Controller
{
    public function show(Post $post)
    {
        return view('blog.post', compact('post'))
            ->cachePublic(3600)
            ->header('Vary', 'Accept-Language');
    }
}

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index')->noCache();
    }
}

class ApiController extends Controller
{
    public function posts()
    {
        $posts = Post::latest()->limit(10)->get();

        return response()->json($posts)
            ->cacheCdn(cdnSeconds: 86400, browserSeconds: 3600);
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
