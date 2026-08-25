# 13.1 Caching

## Resumo

> **Caching** — guardar resultados de cálculos para reusar. Acelera o app várias vezes.
>
> **Tipos:** Application cache (dados), Route/Config/View cache, OPcache (PHP bytecode), Redis (sessões, queue).
>
> **Comandos:** `Cache::remember`, `Cache::tags`, `php artisan optimize`, `Cache::forget` para invalidar.

---

## Conteúdo

- [O que é](#o-que-é)
- [Application Cache](#application-cache)
- [Cache de queries](#cache-de-queries)
- [Invalidação do cache](#invalidação-do-cache)
- [Laravel Cache Commands](#laravel-cache-commands)
- [Redis](#redis)
- [HTTP Cache](#http-cache)
- [Exemplos práticos](#exemplos-práticos)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Cache — guardar resultados de cálculos para reusar. Acelera o app.

**Tipos de cache:**
- Application cache (dados)
- Route cache
- Config cache
- View cache
- OPcache (PHP bytecode)

---

## Application Cache

**Uso básico:**

```php
use Illuminate\Support\Facades\Cache;

// Buscar no cache
$value = Cache::get('key');

// Com valor default
$value = Cache::get('key', 'default');

// Guardar por 60 segundos
Cache::put('key', 'value', 60);

// Guardar para sempre
Cache::forever('key', 'value');

// Remover
Cache::forget('key');

// Checar se existe
if (Cache::has('key')) {
    // ...
}
```

**Cache::remember:**

```php
// Buscar ou calcular e cachear
$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});

// Forever
$settings = Cache::rememberForever('settings', function () {
    return Setting::all()->pluck('value', 'key');
});
```

**Tagging (Redis/Memcached):**

```php
// Guardar com tags
Cache::tags(['users', 'posts'])->put('joao', $user, 600);

// Buscar
$user = Cache::tags(['users', 'posts'])->get('joao');

// Limpar tudo com a tag
Cache::tags(['users'])->flush();
```

---

## Cache de queries

**Eloquent:**

```php
// ❌ RUIM: query a cada chamada
public function getUsers()
{
    return User::all();
}

// ✅ BOM: cachear
public function getUsers()
{
    return Cache::remember('users.all', 3600, function () {
        return User::all();
    });
}
```

**Query Builder:**

```php
$posts = Cache::remember('posts.published', 3600, function () {
    return DB::table('posts')
        ->where('published', true)
        ->orderBy('created_at', 'desc')
        ->limit(10)
        ->get();
});
```

**Cache por usuário:**

```php
public function getUserOrders(User $user)
{
    return Cache::remember("user.{$user->id}.orders", 3600, function () use ($user) {
        return $user->orders()->with('items')->get();
    });
}
```

---

## Invalidação do cache

**Model Observer:**

```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function created(User $user)
    {
        Cache::forget('users.all');
        Cache::forget('users.count');
    }

    public function updated(User $user)
    {
        Cache::forget("user.{$user->id}");
        Cache::forget('users.all');
    }

    public function deleted(User $user)
    {
        Cache::forget("user.{$user->id}");
        Cache::forget('users.all');
        Cache::forget('users.count');
    }
}
```

**Events:**

```php
// app/Listeners/ClearUserCache.php
class ClearUserCache
{
    public function handle(UserUpdated $event)
    {
        Cache::tags(['users'])->flush();
    }
}
```

---

## Laravel Cache Commands

**Route cache:**

```bash
# Cachear as routes (só sem Closure)
php artisan route:cache

# Limpar
php artisan route:clear
```

**Config cache:**

```bash
# Cachear o config (não use env() no código!)
php artisan config:cache

# Limpar
php artisan config:clear
```

**View cache:**

```bash
# Pré-compilar as views Blade
php artisan view:cache

# Limpar
php artisan view:clear
```

**Event cache:**

```bash
# Cachear os event listeners
php artisan event:cache

# Limpar
php artisan event:clear
```

**Otimização para production:**

```bash
# Tudo em um comando
php artisan optimize

# Inclui:
# - config:cache
# - route:cache
# - view:cache

# Limpar tudo
php artisan optimize:clear
```

---

## Redis

**Config (.env):**

```
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Uso:**

```php
use Illuminate\Support\Facades\Redis;

// Operações básicas
Redis::set('name', 'João');
$name = Redis::get('name');

// Expire
Redis::setex('key', 60, 'value');

// Lists
Redis::lpush('queue', 'task1');
Redis::rpush('queue', 'task2');
$task = Redis::lpop('queue');

// Sets
Redis::sadd('users:online', $userId);
Redis::srem('users:online', $userId);
$online = Redis::smembers('users:online');

// Sorted Sets (para leaderboards)
Redis::zadd('scores', $score, $userId);
$top = Redis::zrevrange('scores', 0, 9);  // Top 10
```

---

## HTTP Cache

**Cache de response:**

```php
// Cachear a response por 60 segundos
Route::get('/posts', function () {
    return Cache::remember('posts.all', 60, function () {
        return Post::all();
    });
});

// ETags
public function show(Post $post)
{
    $etag = md5($post->updated_at);

    if ($request->header('If-None-Match') === $etag) {
        return response()->noContent(304);
    }

    return response()->json($post)
        ->header('ETag', $etag)
        ->header('Cache-Control', 'max-age=3600');
}
```

**Middleware:**

```php
// app/Http/Middleware/CacheResponse.php
public function handle($request, Closure $next)
{
    $key = 'response.' . md5($request->url());

    if (Cache::has($key)) {
        return response(Cache::get($key))
            ->header('X-Cache', 'HIT');
    }

    $response = $next($request);

    Cache::put($key, $response->getContent(), 3600);

    return $response->header('X-Cache', 'MISS');
}
```

---

## Exemplos práticos

**Cache do dashboard:**

```php
public function dashboard()
{
    $data = Cache::remember('dashboard.stats', 600, function () {
        return [
            'users_count' => User::count(),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'revenue_today' => Order::whereDate('created_at', today())->sum('total'),
            'popular_products' => Product::withCount('orders')
                ->orderBy('orders_count', 'desc')
                ->limit(5)
                ->get(),
        ];
    });

    return view('dashboard', $data);
}
```

**Cache com invalidação automática:**

```php
// app/Services/CachedUserService.php
class CachedUserService
{
    public function getUser(int $id): ?User
    {
        return Cache::remember("user.$id", 3600, function () use ($id) {
            return User::with('profile', 'roles')->find($id);
        });
    }

    public function updateUser(int $id, array $data): User
    {
        $user = User::findOrFail($id);
        $user->update($data);

        // Invalidar o cache
        Cache::forget("user.$id");

        return $user;
    }
}
```

**Leaderboard com Redis:**

```php
class LeaderboardService
{
    public function addScore(int $userId, int $score): void
    {
        Redis::zadd('leaderboard', $score, $userId);
    }

    public function getTop(int $limit = 10): array
    {
        return Cache::remember("leaderboard.top.$limit", 60, function () use ($limit) {
            $userIds = Redis::zrevrange('leaderboard', 0, $limit - 1, 'WITHSCORES');

            $users = User::whereIn('id', array_keys($userIds))->get()->keyBy('id');

            return collect($userIds)->map(function ($score, $userId) use ($users) {
                return [
                    'user' => $users[$userId],
                    'score' => $score,
                ];
            })->values();
        });
    }
}
```

---

## Na entrevista

> "Cache acelera o app. Cache::remember para dados, invalidação via Model Observer ou events. Comandos do Laravel: route:cache, config:cache, view:cache, optimize. Redis para sessões, queue, cache. Tagging para apagar em grupo. HTTP cache com ETags. Cache::tags para agrupar. Cachear queries pesadas (JOIN, COUNT, agregados). Invalidar quando os dados mudam."

---

## Exercícios práticos

### Exercício 1: Cache com invalidação automática

Implemente o cache da lista de produtos populares (com mais de 10 pedidos). Invalide automaticamente quando um pedido novo for criado.

<details>
<summary>Solução</summary>

```php
// app/Services/ProductService.php
class ProductService
{
    public function getPopularProducts()
    {
        return Cache::remember('products.popular', 3600, function () {
            return Product::withCount('orders')
                ->having('orders_count', '>', 10)
                ->orderBy('orders_count', 'desc')
                ->limit(10)
                ->get();
        });
    }

    public function invalidatePopularCache(): void
    {
        Cache::forget('products.popular');
    }
}

// app/Observers/OrderObserver.php
class OrderObserver
{
    public function __construct(private ProductService $productService) {}

    public function created(Order $order)
    {
        // Invalidar o cache de produtos populares
        $this->productService->invalidatePopularCache();
    }
}

// app/Providers/AppServiceProvider.php
public function boot()
{
    Order::observe(OrderObserver::class);
}
```
</details>

### Exercício 2: Cache Tags para exclusão em grupo

Implemente o cache dos posts do usuário com tags. Ao atualizar o usuário ou criar um post novo, limpe todos os caches ligados.

<details>
<summary>Solução</summary>

```php
// app/Services/PostService.php
class PostService
{
    public function getUserPosts(int $userId)
    {
        return Cache::tags(['users', "user:$userId", 'posts'])
            ->remember("user.$userId.posts", 3600, function () use ($userId) {
                return Post::where('user_id', $userId)
                    ->with('category')
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
    }

    public function getPost(int $postId)
    {
        $post = Post::find($postId);

        return Cache::tags(['posts', "user:{$post->user_id}", "post:$postId"])
            ->remember("post.$postId", 3600, function () use ($post) {
                return $post->load('user', 'comments');
            });
    }
}

// app/Observers/UserObserver.php
class UserObserver
{
    public function updated(User $user)
    {
        // Limpar todos os caches ligados ao usuário
        Cache::tags(["user:{$user->id}"])->flush();
    }
}

// app/Observers/PostObserver.php
class PostObserver
{
    public function created(Post $post)
    {
        // Limpar o cache de posts do usuário
        Cache::tags(["user:{$post->user_id}"])->flush();
    }

    public function updated(Post $post)
    {
        // Limpar o cache do post
        Cache::tags(["post:{$post->id}"])->flush();
    }
}
```
</details>

### Exercício 3: Redis para Leaderboard

Implemente um ranking de jogadores com Redis Sorted Sets. Métodos para somar pontos e buscar o top 10.

<details>
<summary>Solução</summary>

```php
// app/Services/LeaderboardService.php
use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    private const LEADERBOARD_KEY = 'game:leaderboard';
    private const CACHE_TTL = 60; // 1 minuto

    public function addScore(int $userId, int $score): void
    {
        // Adicionar ou atualizar o score
        Redis::zadd(self::LEADERBOARD_KEY, $score, $userId);

        // Invalidar o cache do top
        Cache::forget('leaderboard.top.10');
        Cache::forget('leaderboard.top.100');
    }

    public function incrementScore(int $userId, int $points): int
    {
        Redis::zincrby(self::LEADERBOARD_KEY, $points, $userId);

        Cache::forget('leaderboard.top.10');

        return $this->getScore($userId);
    }

    public function getScore(int $userId): int
    {
        return (int) Redis::zscore(self::LEADERBOARD_KEY, $userId);
    }

    public function getTop(int $limit = 10): array
    {
        return Cache::remember("leaderboard.top.$limit", self::CACHE_TTL, function () use ($limit) {
            // Buscar o top de jogadores com scores
            $userIds = Redis::zrevrange(
                self::LEADERBOARD_KEY,
                0,
                $limit - 1,
                'WITHSCORES'
            );

            // Converter em array [user_id => score]
            $scores = [];
            for ($i = 0; $i < count($userIds); $i += 2) {
                $scores[$userIds[$i]] = (int) $userIds[$i + 1];
            }

            // Carregar os usuários
            $users = User::whereIn('id', array_keys($scores))->get()->keyBy('id');

            // Montar o resultado
            return collect($scores)->map(function ($score, $userId) use ($users) {
                return [
                    'user' => $users[$userId] ?? null,
                    'score' => $score,
                    'rank' => $this->getRank($userId),
                ];
            })->values()->toArray();
        });
    }

    public function getRank(int $userId): int
    {
        // Posição no ranking (0-based → 1-based)
        $rank = Redis::zrevrank(self::LEADERBOARD_KEY, $userId);

        return $rank !== null ? $rank + 1 : 0;
    }

    public function getUserRankWithNeighbors(int $userId, int $range = 2): array
    {
        $rank = $this->getRank($userId);

        if ($rank === 0) {
            return [];
        }

        $start = max(0, $rank - $range - 1);
        $end = $rank + $range - 1;

        $userIds = Redis::zrevrange(
            self::LEADERBOARD_KEY,
            $start,
            $end,
            'WITHSCORES'
        );

        // Igual ao getTop
        // ...
    }
}

// Uso
$leaderboard = new LeaderboardService();

// Adicionar pontos
$leaderboard->addScore(1, 100);
$leaderboard->incrementScore(1, 50); // Agora 150

// Buscar o top
$top10 = $leaderboard->getTop(10);

// Buscar a posição do jogador
$rank = $leaderboard->getRank(1); // 5
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
