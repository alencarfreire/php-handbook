# 6.9 Cache

## Resumo

> **Cache** — guardar dados usados com frequência para acesso rápido. Reduz a carga no banco e acelera o app.
>
> **Drivers:** file, database, redis (recomendado), memcached, array.
>
> **Importante:** Cache::remember() — se não está no cache, executa o callback e guarda. Tags para agrupar. Invalidação via observers.

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
Cache — guardar dados usados com frequência para acesso rápido. Reduz a carga no banco e acelera o app.

**Drivers de cache no Laravel:**
- file — arquivos (default)
- database — tabela no banco
- redis — Redis (recomendado)
- memcached — Memcached
- array — na memória (para testes)

---

## Como funciona

**Operações básicas:**

```php
use Illuminate\Support\Facades\Cache;

// Guardar para sempre
Cache::put('key', 'value');

// Guardar com TTL (segundos)
Cache::put('key', 'value', 3600);  // 1 hora

// Ou via now()
Cache::put('key', 'value', now()->addMinutes(60));

// Buscar
$value = Cache::get('key');

// Com default
$value = Cache::get('key', 'default');

// Com Closure
$value = Cache::get('key', function () {
    return 'valor padrão';
});

// Checar se existe
if (Cache::has('key')) {
    // A chave existe
}

// Remover
Cache::forget('key');

// Limpar o cache inteiro
Cache::flush();
```

**Remember (cache com callback):**

```php
// Se está no cache, devolve. Se não está, executa o callback e guarda
$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});

// Remember forever
$settings = Cache::rememberForever('settings', function () {
    return Setting::pluck('value', 'key');
});

// Pull (buscar e remover)
$value = Cache::pull('key');
```

**Increment / Decrement:**

```php
// Increment
Cache::increment('page:views');  // +1
Cache::increment('page:views', 5);  // +5

// Decrement
Cache::decrement('page:views');  // -1
Cache::decrement('page:views', 3);  // -3
```

**Tags (agrupar o cache):**

```php
// Guardar com tags (só Redis/Memcached)
Cache::tags(['people', 'artists'])->put('John', 'Artista', 600);
Cache::tags(['people', 'authors'])->put('Jane', 'Autor', 600);

// Buscar
$value = Cache::tags(['people', 'artists'])->get('John');

// Remover tudo com a tag
Cache::tags(['people'])->flush();  // Remove John e Jane
Cache::tags(['artists'])->flush();  // Remove só o John
```

---

## Quando usar

**Use cache quando:**
- Dados lidos com frequência, mudam pouco
- Cálculo caro (query pesada, API)
- Dados estáticos (settings, config)

**Não use cache quando:**
- Dados mudam o tempo todo
- Dados pessoais (ficam velhos)
- Precisa estar sempre atualizado

---

## Exemplo prático

**Cache de queries:**

```php
// Lista de posts
$posts = Cache::remember('posts.published', 3600, function () {
    return Post::where('published', true)
        ->with('user', 'category')
        ->latest()
        ->get();
});

// Invalidar quando muda
class Post extends Model
{
    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget('posts.published');
        });

        static::deleted(function () {
            Cache::forget('posts.published');
        });
    }
}
```

**Cache com tags:**

```php
// Cache com tags
$post = Cache::tags(['posts', 'post:' . $id])->remember("post:{$id}", 3600, function () use ($id) {
    return Post::with('user', 'comments')->find($id);
});

// Invalidar todos os posts
Cache::tags(['posts'])->flush();

// Invalidar um post específico
Cache::tags(['post:' . $id])->flush();

// Observer para invalidar automaticamente
class PostObserver
{
    public function saved(Post $post): void
    {
        Cache::tags(['posts', 'post:' . $post->id])->flush();
    }

    public function deleted(Post $post): void
    {
        Cache::tags(['posts', 'post:' . $post->id])->flush();
    }
}
```

**View caching:**

```php
// Cachear a view
public function show(Post $post)
{
    $html = Cache::remember("views.post.{$post->id}", 3600, function () use ($post) {
        return view('posts.show', compact('post'))->render();
    });

    return $html;
}

// Ou via middleware de response cache
Route::get('/posts/{post}', [PostController::class, 'show'])
    ->middleware('cache.response:3600');
```

**Model attribute caching:**

```php
class User extends Model
{
    // Cachear computed attribute
    public function getFullNameAttribute(): string
    {
        return Cache::remember("user:{$this->id}:full_name", 3600, function () {
            return "{$this->first_name} {$this->last_name}";
        });
    }

    // Cachear o count do relationship
    public function getPostsCountAttribute(): int
    {
        return Cache::remember("user:{$this->id}:posts_count", 3600, function () {
            return $this->posts()->count();
        });
    }
}
```

**Cache warming (aquecer o cache):**

```php
// Command para aquecer o cache
class WarmCache extends Command
{
    public function handle(): void
    {
        // Aquecer dados populares
        Cache::remember('settings', 3600, fn() => Setting::all());
        Cache::remember('users.top', 3600, fn() => User::withCount('posts')->orderBy('posts_count', 'desc')->limit(100)->get());
        Cache::remember('posts.popular', 3600, fn() => Post::orderBy('views', 'desc')->limit(50)->get());

        $this->info('Cache aquecido com sucesso');
    }
}

// Rodar depois do deploy
php artisan cache:warm
```

**Cache aside pattern (read-through):**

```php
class UserRepository
{
    public function find(int $id): ?User
    {
        return Cache::remember("user:{$id}", 3600, function () use ($id) {
            return User::find($id);
        });
    }

    public function update(User $user): void
    {
        $user->save();

        // Invalidar o cache
        Cache::forget("user:{$user->id}");
    }
}
```

**Write-through cache:**

```php
class UserRepository
{
    public function update(User $user): void
    {
        // 1. Atualizar o banco
        $user->save();

        // 2. Atualizar o cache
        Cache::put("user:{$user->id}", $user, 3600);
    }
}
```

**Cache lock (evitar cache stampede):**

```php
$post = Cache::remember("post:{$id}", 3600, function () use ($id) {
    // Se o cache expirou e chegam muitos requests ao mesmo tempo,
    // todos batem no banco (cache stampede)

    return Post::find($id);
});

// Solução: Lock
$post = Cache::flexible("post:{$id}", [3600, 600], function () use ($id) {
    // 3600 — TTL, 600 — grace period
    // Quando expira, o primeiro request atualiza o cache,
    // os outros recebem o cache velho no grace period
    return Post::find($id);
});
```

**Monitorar o cache:**

```php
// O Laravel Telescope loga as operações de cache automaticamente

// Métricas
class CacheMetrics
{
    public static function trackHit(string $key): void
    {
        Redis::incr("cache:hits:{$key}");
    }

    public static function trackMiss(string $key): void
    {
        Redis::incr("cache:misses:{$key}");
    }

    public static function getHitRate(string $key): float
    {
        $hits = Redis::get("cache:hits:{$key}") ?: 0;
        $misses = Redis::get("cache:misses:{$key}") ?: 0;
        $total = $hits + $misses;

        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
}
```

**Estratégias de cache:**

```php
// 1. Cache aside (lazy loading)
$user = Cache::get("user:{$id}");
if (!$user) {
    $user = User::find($id);
    Cache::put("user:{$id}", $user, 3600);
}

// 2. Read-through (via helper)
$user = Cache::remember("user:{$id}", 3600, fn() => User::find($id));

// 3. Write-through (atualiza o cache na escrita)
$user->save();
Cache::put("user:{$user->id}", $user, 3600);

// 4. Write-behind (grava no banco depois)
Cache::put("user:{$id}", $user, 3600);
dispatch(new SyncUserToDatabase($user));
```

---

## Na entrevista

> "Cache acelera o app e reduz carga no banco. Drivers: file, database, redis (o melhor). Cache::remember() — se não está no cache, executa o callback e guarda. Tags para agrupar (Cache::tags(['posts'])->flush()). Invalidação via observers (saved, deleted). Cache aside — checa o cache → carrega do banco → guarda. Write-through — atualiza banco e cache. Cache stampede se resolve com flexible() ou lock. Hit rate para monitorar se está valendo. Aquecer o cache depois do deploy."

---

## Exercícios práticos

### Exercício 1: Implemente cache com invalidação automática

Coloque em cache a lista de posts do blog. Ao criar/atualizar/apagar um post, limpe o cache automaticamente.

<details>
<summary>Solução</summary>

```php
// Repository com cache
class PostRepository
{
    protected int $cacheTtl = 3600; // 1 hora

    public function getPublished(): Collection
    {
        return Cache::tags(['posts'])->remember('posts.published', $this->cacheTtl, function () {
            return Post::where('published', true)
                ->with('user', 'category')
                ->latest()
                ->get();
        });
    }

    public function find(int $id): ?Post
    {
        return Cache::tags(['posts', "post:{$id}"])->remember("post:{$id}", $this->cacheTtl, function () use ($id) {
            return Post::with('user', 'category', 'tags')->find($id);
        });
    }

    public function invalidatePost(Post $post): void
    {
        Cache::tags(['posts', "post:{$post->id}"])->flush();
    }

    public function invalidateAll(): void
    {
        Cache::tags(['posts'])->flush();
    }
}

// Observer para invalidar automaticamente
class PostObserver
{
    public function __construct(protected PostRepository $repository)
    {
    }

    public function created(Post $post): void
    {
        $this->repository->invalidateAll();
    }

    public function updated(Post $post): void
    {
        $this->repository->invalidatePost($post);
    }

    public function deleted(Post $post): void
    {
        $this->repository->invalidatePost($post);
    }

    public function restored(Post $post): void
    {
        $this->repository->invalidateAll();
    }
}

// Registrar o Observer
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Post::observe(PostObserver::class);
    }
}

// Alternativa: Model Events
class Post extends Model
{
    protected static function booted(): void
    {
        static::saved(function (Post $post) {
            Cache::tags(['posts', "post:{$post->id}"])->flush();
        });

        static::deleted(function (Post $post) {
            Cache::tags(['posts', "post:{$post->id}"])->flush();
        });
    }
}

// Uso no controller
class PostController extends Controller
{
    public function __construct(protected PostRepository $repository)
    {
    }

    public function index()
    {
        $posts = $this->repository->getPublished();
        return view('posts.index', compact('posts'));
    }

    public function show(int $id)
    {
        $post = $this->repository->find($id);
        return view('posts.show', compact('post'));
    }
}
```
</details>

### Exercício 2: Evite o Cache Stampede

1000 requests batem no cache ao mesmo tempo, e ele acabou de expirar. Os 1000 vão no banco. Como evitar?

<details>
<summary>Solução</summary>

```php
// ❌ Problema: Cache Stampede (Dog-Piling)
// Cache expirou → todos os requests vão no banco ao mesmo tempo

$posts = Cache::remember('posts', 3600, function () {
    // Se 1000 requests chegam juntos depois que o cache expirou,
    // todos executam essa query pesada
    return Post::with('user', 'category')->get();
});

// ✅ Solução 1: Lock (só o primeiro atualiza o cache)
public function getPosts(): Collection
{
    $posts = Cache::get('posts');

    if ($posts !== null) {
        return $posts;
    }

    // Tentar pegar o lock
    $lock = Cache::lock('posts:refresh', 10);

    if ($lock->get()) {
        try {
            // Só o primeiro request consulta o banco
            $posts = Post::with('user', 'category')->get();
            Cache::put('posts', $posts, 3600);
            return $posts;
        } finally {
            $lock->release();
        }
    } else {
        // Os outros esperam e tentam pegar do cache
        sleep(1);
        return Cache::get('posts') ?? collect();
    }
}

// ✅ Solução 2: flexible() (grace period)
$posts = Cache::flexible('posts', [3600, 600], function () {
    // 3600 - TTL principal
    // 600 - grace period (10 minutos)

    // Depois que o TTL principal expira:
    // - O primeiro request atualiza o cache
    // - Os outros recebem o cache velho no grace period
    return Post::with('user', 'category')->get();
});

// ✅ Solução 3: Refresh probabilístico (probabilistic early expiration)
class CacheService
{
    public function remember(string $key, int $ttl, Closure $callback, float $beta = 1.0)
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            $expiresAt = Cache::get("{$key}:expires_at");

            if ($expiresAt) {
                $now = time();
                $timeToExpire = $expiresAt - $now;

                // A chance de refresh sobe conforme chega perto de expirar
                $probability = $beta * log(mt_rand() / mt_getrandmax());

                if ($timeToExpire < $probability) {
                    // Atualizar antes da hora
                    return $this->refreshCache($key, $ttl, $callback);
                }
            }

            return $cached;
        }

        return $this->refreshCache($key, $ttl, $callback);
    }

    protected function refreshCache(string $key, int $ttl, Closure $callback)
    {
        $lock = Cache::lock("{$key}:lock", 5);

        if ($lock->get()) {
            try {
                $value = $callback();
                Cache::put($key, $value, $ttl);
                Cache::put("{$key}:expires_at", time() + $ttl, $ttl);
                return $value;
            } finally {
                $lock->release();
            }
        }

        // Se não pegou o lock, devolve o cache velho
        return Cache::get($key);
    }
}

// ✅ Solução 4: Refresh em background (scheduled task)
class WarmCacheCommand extends Command
{
    public function handle(): void
    {
        // Atualizar o cache a cada hora, sem esperar expirar
        $posts = Post::with('user', 'category')->get();
        Cache::put('posts', $posts, 3600);

        $this->info('Cache aquecido com sucesso');
    }
}

// No Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Atualizar 5 minutos antes de expirar
    $schedule->command('cache:warm')->everyFiftyFiveMinutes();
}

// ✅ Solução 5: Stale-While-Revalidate
class StaleWhileRevalidate
{
    public function get(string $key, int $ttl, int $staleTime, Closure $callback)
    {
        $value = Cache::get($key);
        $expiresAt = Cache::get("{$key}:expires_at");

        $now = time();

        // Se o cache está fresco, devolve
        if ($value && $expiresAt && $expiresAt > $now) {
            return $value;
        }

        // Se o cache está velho, mas ainda no período stale
        $staleExpiresAt = Cache::get("{$key}:stale_expires_at");

        if ($value && $staleExpiresAt && $staleExpiresAt > $now) {
            // Devolver o cache velho
            // Atualizar em background, assíncrono
            dispatch(function () use ($key, $ttl, $staleTime, $callback) {
                $this->refresh($key, $ttl, $staleTime, $callback);
            })->afterResponse();

            return $value;
        }

        // Cache expirou de vez, atualiza síncrono
        return $this->refresh($key, $ttl, $staleTime, $callback);
    }

    protected function refresh(string $key, int $ttl, int $staleTime, Closure $callback)
    {
        $value = $callback();
        $now = time();

        Cache::put($key, $value, $ttl + $staleTime);
        Cache::put("{$key}:expires_at", $now + $ttl, $ttl + $staleTime);
        Cache::put("{$key}:stale_expires_at", $now + $ttl + $staleTime, $ttl + $staleTime);

        return $value;
    }
}
```
</details>

### Exercício 3: Cache em camadas

Implemente cache em dois níveis: L1 (array na memória do processo) + L2 (Redis).

<details>
<summary>Solução</summary>

```php
// Cache em dois níveis para um request
class TwoLevelCache
{
    protected array $localCache = [];

    public function remember(string $key, int $ttl, Closure $callback)
    {
        // L1: Checar o local cache (array na memória)
        if (isset($this->localCache[$key])) {
            return $this->localCache[$key];
        }

        // L2: Checar o Redis
        $value = Cache::remember($key, $ttl, $callback);

        // Guardar no L1
        $this->localCache[$key] = $value;

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->localCache[$key]);
        Cache::forget($key);
    }

    public function flush(): void
    {
        $this->localCache = [];
        Cache::flush();
    }
}

// Uso
class PostRepository
{
    public function __construct(protected TwoLevelCache $cache)
    {
    }

    public function find(int $id): ?Post
    {
        return $this->cache->remember("post:{$id}", 3600, function () use ($id) {
            return Post::find($id);
        });
    }
}

// Registro no Service Container
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TwoLevelCache::class);
    }
}

// Versão mais avançada com TTL no L1
class AdvancedTwoLevelCache
{
    protected array $localCache = [];
    protected array $expiresAt = [];
    protected int $localTtl = 60; // L1 cache 60 segundos

    public function remember(string $key, int $ttl, Closure $callback)
    {
        // L1: Checar o local cache
        if ($this->hasValidLocalCache($key)) {
            return $this->localCache[$key];
        }

        // L2: Redis cache
        $value = Cache::remember($key, $ttl, $callback);

        // Guardar no L1
        $this->storeInLocalCache($key, $value);

        return $value;
    }

    protected function hasValidLocalCache(string $key): bool
    {
        return isset($this->localCache[$key])
            && isset($this->expiresAt[$key])
            && $this->expiresAt[$key] > time();
    }

    protected function storeInLocalCache(string $key, $value): void
    {
        $this->localCache[$key] = $value;
        $this->expiresAt[$key] = time() + $this->localTtl;
    }

    public function forget(string $key): void
    {
        unset($this->localCache[$key], $this->expiresAt[$key]);
        Cache::forget($key);
    }
}

// Middleware para limpar o L1 entre requests (se você usa Octane)
class ClearLocalCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Limpar o L1 depois de cada request
        app(TwoLevelCache::class)->flush();

        return $response;
    }
}

// Exemplo num app de verdade
class UserService
{
    public function __construct(protected TwoLevelCache $cache)
    {
    }

    public function getUser(int $id): ?User
    {
        // Em 1000 chamadas no mesmo request:
        // - Primeira: request no Redis
        // - As outras 999: do array (na hora)
        return $this->cache->remember("user:{$id}", 3600, function () use ($id) {
            return User::find($id);
        });
    }

    public function getManyUsers(array $ids): Collection
    {
        return collect($ids)->map(function ($id) {
            return $this->getUser($id); // O L1 evita duplicata
        })->filter();
    }
}

// Teste de performance
// Sem L1: 1000 chamadas = 1000 requests no Redis (~100ms)
// Com L1: 1000 chamadas = 1 request no Redis + 999 lookups no array (~5ms)
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
