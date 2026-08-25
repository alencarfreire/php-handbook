# 14.1 Estratégias de cache

## Resumo

> **Cache** — guardar dados num store rápido (in-memory) para baixar latência e carga no banco.
>
> Estratégias: **Cache-Aside** (lazy loading, `Cache::remember`), **Read/Write-Through** (o cache gerencia o banco), **Write-Behind** (write async no banco), **Refresh-Ahead** (atualiza antes do expiration).
>
> Invalidação: **TTL** (auto-expire), **Manual** (forget quando muda), **Cache Tags** (agrupar), **Event-based**. Problemas: **Thundering Herd** (Lock ou probabilistic expiration), stale data, cache pollution.

---

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de cache](#tipos-de-cache)
- [Estratégias de cache](#estratégias-de-cache)
- [Invalidação de cache](#invalidação-de-cache)
- [Thundering Herd Problem](#thundering-herd-problem)
- [Cache Warming](#cache-warming)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Cache:**
Guardar dados usados com frequência num store rápido. Baixa latência e carga no banco.

**Trade-off:**
- ✅ Mais rápido (in-memory vs disco)
- ✅ Menos carga no banco
- ❌ Stale data (os dados podem ficar velhos)
- ❌ Uso de memória
- ❌ Invalidação de cache é difícil

---

## Tipos de cache

### 1. Application-Level Cache

**Laravel Cache:**

```php
// Cachear por 1 hora
$users = Cache::remember('users', 3600, function () {
    return User::all();
});
```

---

### 2. Database Query Cache

**MySQL Query Cache (deprecated no MySQL 8.0):**

```sql
SELECT SQL_CACHE * FROM users;
```

**Laravel: cache do resultado:**

```php
$users = Cache::remember('users_list', 3600, function () {
    return DB::table('users')->get();
});
```

---

### 3. HTTP Cache (Browser, CDN, Proxy)

```php
// Laravel Response Cache
return response($content)
    ->header('Cache-Control', 'public, max-age=3600');
```

---

### 4. OPcache (PHP bytecode cache)

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=128
```

---

## Estratégias de cache

### 1. Cache-Aside (Lazy Loading)

**Algoritmo:**

```
1. Checar o cache
2. Se HIT → devolver
3. Se MISS → buscar no banco
4. Colocar no cache
5. Devolver os dados
```

**Laravel:**

```php
$user = Cache::remember("user:{$id}", 3600, function () use ($id) {
    return User::find($id);
});
```

**Prós:**
- ✅ Implementação simples
- ✅ O cache enche sob demanda

**Contras:**
- ❌ Primeiro request é lento (cache miss)
- ❌ Thundering herd problem

---

### 2. Read-Through Cache

**Algoritmo:**

```
1. O app pede ao cache
2. O cache busca no banco se der miss
3. O cache devolve os dados
```

**Implementação:**

```php
class UserRepository
{
    public function find(int $id): ?User
    {
        $cacheKey = "user:{$id}";

        // Read-through: o cache cuida do load
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $user = User::find($id);

        if ($user) {
            Cache::put($cacheKey, $user, 3600);
        }

        return $user;
    }
}
```

**Prós:**
- ✅ Abstração (o app não vê o cache miss)

**Contras:**
- ❌ Primeiro request é lento

---

### 3. Write-Through Cache

**Algoritmo:**

```
1. O app grava no cache
2. O cache grava no banco de forma síncrona
3. Devolve success
```

**Implementação:**

```php
class UserRepository
{
    public function save(User $user): void
    {
        // Write-through: grava no cache e no banco ao mesmo tempo
        $user->save();  // Banco

        Cache::put("user:{$user->id}", $user, 3600);  // Cache
    }
}
```

**Prós:**
- ✅ Cache sempre fresco
- ✅ Consistency

**Contras:**
- ❌ Mais lento (2 operações)
- ❌ Cache pollution (cacheia tudo, até o que quase ninguém usa)

---

### 4. Write-Behind (Write-Back) Cache

**Algoritmo:**

```
1. O app grava no cache
2. O cache devolve success (rápido)
3. O cache grava no banco depois (async)
```

**Implementação:**

```php
class UserRepository
{
    public function save(User $user): void
    {
        // Write-behind: grava no cache na hora
        Cache::put("user:{$user->id}", $user, 3600);

        // Banco async (job)
        SaveUserToDatabaseJob::dispatch($user);
    }
}
```

**Prós:**
- ✅ Muito rápido (write na memória)
- ✅ Menos carga no banco (batch writes)

**Contras:**
- ❌ Risco de data loss (se o cache cair antes de gravar no banco)
- ❌ Eventual consistency

---

### 5. Refresh-Ahead

**Algoritmo:**

```
1. O cache atualiza os dados ANTES do TTL acabar
2. Sem cache miss
```

**Implementação:**

```php
class RefreshAheadCache
{
    public function get(string $key, int $ttl, callable $callback)
    {
        $value = Cache::get($key);
        $expiresAt = Cache::get("{$key}:expires_at");

        // Atualizar antes (80% do TTL)
        if ($expiresAt && now()->timestamp > ($expiresAt - $ttl * 0.2)) {
            // Atualizar async
            RefreshCacheJob::dispatch($key, $callback);
        }

        // Se cache miss
        if ($value === null) {
            $value = $callback();
            Cache::put($key, $value, $ttl);
            Cache::put("{$key}:expires_at", now()->timestamp + $ttl, $ttl);
        }

        return $value;
    }
}
```

**Prós:**
- ✅ Sem cache miss (sempre fresh)
- ✅ Low latency

**Contras:**
- ❌ Pode atualizar dado que ninguém usa

---

## Invalidação de cache

> "There are only two hard things in Computer Science: cache invalidation and naming things" — Phil Karlton

### 1. TTL (Time To Live)

**Abordagem simples:**

```php
Cache::put('users', $users, 3600);  // 1 hora

// Some sozinho em 1 hora
```

**Prós:**
- ✅ Simples
- ✅ Não fica stale por muito tempo

**Contras:**
- ❌ Pode ficar stale até o TTL acabar

---

### 2. Manual Invalidation

**Quando os dados mudam:**

```php
class User extends Model
{
    protected static function booted()
    {
        static::updated(function ($user) {
            // Invalidar o cache
            Cache::forget("user:{$user->id}");
            Cache::forget('users_list');
        });

        static::deleted(function ($user) {
            Cache::forget("user:{$user->id}");
            Cache::forget('users_list');
        });
    }
}
```

**Prós:**
- ✅ Dados sempre frescos

**Contras:**
- ❌ Você precisa lembrar de invalidar em todo lugar

---

### 3. Cache Tags (Laravel)

**Agrupar cache keys:**

```php
// Cachear com tags
Cache::tags(['users', 'admins'])->put('admin_users', $users, 3600);

// Invalidar tudo com a tag 'users'
Cache::tags(['users'])->flush();
```

**Caso de uso:**

```php
class UserService
{
    public function getAllUsers()
    {
        return Cache::tags(['users'])->remember('users_list', 3600, function () {
            return User::all();
        });
    }

    public function getAdminUsers()
    {
        return Cache::tags(['users', 'admins'])->remember('admin_users', 3600, function () {
            return User::where('is_admin', true)->get();
        });
    }

    public function updateUser(User $user)
    {
        $user->save();

        // Invalidar todos os caches ligados a users
        Cache::tags(['users'])->flush();
    }
}
```

---

### 4. Event-Based Invalidation

```php
// Event
class UserUpdated
{
    public function __construct(public User $user) {}
}

// Listener
class InvalidateUserCache
{
    public function handle(UserUpdated $event)
    {
        Cache::forget("user:{$event->user->id}");
        Cache::tags(['users'])->flush();
    }
}

// Model
class User extends Model
{
    protected $dispatchesEvents = [
        'updated' => UserUpdated::class,
    ];
}
```

---

## Thundering Herd Problem

**Problema:**

```
Cache expira
    ↓
1000 requests ao mesmo tempo
    ↓
1000 queries no banco (sobrecarga!)
```

**Solução 1: Lock (Laravel)**

```php
$users = Cache::lock('users_list')->get(function () {
    // Só 1 processo executa
    return Cache::remember('users_list', 3600, function () {
        return User::all();
    });
});
```

**Solução 2: Probabilistic Early Expiration**

```php
function cacheWithProbabilisticExpiration($key, $ttl, $callback)
{
    $value = Cache::get($key);
    $expiresAt = Cache::get("{$key}:expires");

    if ($value && $expiresAt) {
        $now = time();
        $timeLeft = $expiresAt - $now;

        // A chance de atualizar sobe perto do expiration
        $probability = 1 - ($timeLeft / $ttl);

        if (rand(0, 100) / 100 < $probability) {
            // Atualizar antes
            $value = $callback();
            Cache::put($key, $value, $ttl);
            Cache::put("{$key}:expires", $now + $ttl, $ttl);
        }
    } else {
        $value = $callback();
        Cache::put($key, $value, $ttl);
        Cache::put("{$key}:expires", time() + $ttl, $ttl);
    }

    return $value;
}
```

---

## Cache Warming

**Preencher o cache antes (aquecer):**

```php
class WarmCacheCommand extends Command
{
    public function handle()
    {
        // Aquecer dados populares
        Cache::put('popular_products', Product::popular()->get(), 3600);
        Cache::put('categories', Category::all(), 3600);
        Cache::put('featured_posts', Post::featured()->get(), 3600);

        $this->info('Cache aquecido com sucesso');
    }
}

// Scheduler: aquece o cache de hora em hora
$schedule->command('cache:warm')->hourly();
```

---

## Cache Levels (cache em camadas)

```php
class MultiLevelCache
{
    public function get(string $key)
    {
        // L1: In-memory (APCu)
        if (apcu_exists($key)) {
            return apcu_fetch($key);
        }

        // L2: Redis
        if (Cache::has($key)) {
            $value = Cache::get($key);
            apcu_store($key, $value, 60);  // L1 cache
            return $value;
        }

        // L3: Database
        $value = DB::table('data')->find($key);
        Cache::put($key, $value, 3600);  // L2
        apcu_store($key, $value, 60);     // L1

        return $value;
    }
}
```

---

## Boas práticas

```
✓ Cachear o que é caro de calcular (DB queries, API calls)
✓ TTL razoável (nem longo demais, nem curto demais)
✓ Cache Tags para agrupar
✓ Event-based invalidation
✓ Lock para thundering herd
✓ Monitoring: cache hit rate, memory usage
✓ Cache Warming para dados populares
✓ Versionar cache keys quando a estrutura muda
✓ NÃO cachear dados pessoais (security)
```

---

## Na entrevista

> "Cache é guardar dados num store rápido. Estratégias: Cache-Aside (lazy loading, Laravel remember), Read/Write-Through (o cache gerencia o banco), Write-Behind (write async no banco), Refresh-Ahead (atualiza antes do expiration). Invalidação: TTL, manual (observers), Cache Tags (agrupar), event-based. Thundering Herd: Lock ou probabilistic early expiration. Cache Warming: preencher dados populares. Multi-level: L1 (APCu), L2 (Redis), L3 (DB). Boas práticas: cachear operação cara, Tags, monitorar hit rate, não cachear dado pessoal."

---

## Exercícios práticos

### Exercício 1: Cache-Aside com proteção contra Thundering Herd

**Enunciado:** Crie o método `getCachedUsers()` com estratégia Cache-Aside e proteção contra Thundering Herd via Lock.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserCacheService
{
    public function getCachedUsers(): Collection
    {
        $cacheKey = 'users_list';
        $lockKey = 'users_list_lock';

        // Tentar buscar no cache
        if ($users = Cache::get($cacheKey)) {
            return $users;
        }

        // Proteção contra Thundering Herd com Lock
        return Cache::lock($lockKey, 10)->block(5, function () use ($cacheKey) {
            // Double-check: outro processo pode ter colocado no cache
            if ($users = Cache::get($cacheKey)) {
                return $users;
            }

            // Carregar do banco
            $users = User::active()->get();

            // Guardar no cache por 1 hora
            Cache::put($cacheKey, $users, 3600);

            return $users;
        });
    }

    // Invalidação quando muda
    public function invalidateCache(): void
    {
        Cache::forget('users_list');
    }
}

// No model User
class User extends Model
{
    protected static function booted()
    {
        static::saved(function () {
            app(UserCacheService::class)->invalidateCache();
        });

        static::deleted(function () {
            app(UserCacheService::class)->invalidateCache();
        });
    }
}

// Uso
$users = app(UserCacheService::class)->getCachedUsers();
```
</details>

### Exercício 2: Write-Through Cache com invalidação event-based

**Enunciado:** Implemente o UserRepository com estratégia Write-Through e invalidação event-based via Cache Tags.

<details>
<summary>Solução</summary>

```php
namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserRepository
{
    private const CACHE_TTL = 3600; // 1 hora
    private const TAG = 'users';

    public function find(int $id): ?User
    {
        // Read-Through: o cache cuida do load
        return Cache::tags([self::TAG])->remember(
            "user:{$id}",
            self::CACHE_TTL,
            fn() => User::find($id)
        );
    }

    public function all(): Collection
    {
        return Cache::tags([self::TAG])->remember(
            'users:all',
            self::CACHE_TTL,
            fn() => User::all()
        );
    }

    public function getAdmins(): Collection
    {
        return Cache::tags([self::TAG, 'admins'])->remember(
            'users:admins',
            self::CACHE_TTL,
            fn() => User::where('is_admin', true)->get()
        );
    }

    // Write-Through: grava no banco e no cache ao mesmo tempo
    public function save(User $user): User
    {
        $user->save();

        // Atualizar o cache
        Cache::tags([self::TAG])->put(
            "user:{$user->id}",
            $user,
            self::CACHE_TTL
        );

        // Invalidar as listas
        Cache::tags([self::TAG])->forget('users:all');

        if ($user->is_admin) {
            Cache::tags(['admins'])->flush();
        }

        return $user;
    }

    public function delete(User $user): bool
    {
        $id = $user->id;
        $result = $user->delete();

        // Invalidar o cache
        Cache::tags([self::TAG])->forget("user:{$id}");
        Cache::tags([self::TAG])->flush(); // Limpar tudo relacionado

        return $result;
    }

    // Flush de tudo ligado a users
    public function flushCache(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}

// Uso no controller
public function index(UserRepository $repository)
{
    return $repository->all();
}

public function store(Request $request, UserRepository $repository)
{
    $user = new User($request->validated());
    return $repository->save($user);
}
```
</details>

### Exercício 3: Probabilistic Early Expiration para evitar cache miss

**Enunciado:** Implemente um método com probabilistic early expiration para atualizar o cache antes do TTL acabar.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ProbabilisticCache
{
    /**
     * Busca no cache com probabilistic early expiration
     *
     * @param string $key Chave do cache
     * @param int $ttl TTL em segundos
     * @param callable $callback Função que carrega os dados
     * @param float $beta Coeficiente (geralmente 1.0)
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback, float $beta = 1.0)
    {
        $value = Cache::get($key);
        $expiresAt = Cache::get("{$key}:expires");

        if ($value !== null && $expiresAt !== null) {
            $now = time();
            $timeLeft = $expiresAt - $now;

            // Probabilistic early expiration formula
            // probability = β * log(rand(0,1)) * δ
            // δ (delta) = time to recompute
            $delta = 1; // Assume 1 segundo para recompute

            $probability = -$beta * log(mt_rand() / mt_getrandmax()) * $delta;

            // Atualizar antes se probability > time left
            if ($probability >= $timeLeft) {
                // Atualizar o cache async
                dispatch(function () use ($key, $ttl, $callback) {
                    $newValue = $callback();
                    Cache::put($key, $newValue, $ttl);
                    Cache::put("{$key}:expires", time() + $ttl, $ttl);
                })->afterResponse();
            }

            return $value;
        }

        // Cache miss: carregar e guardar
        $value = $callback();
        Cache::put($key, $value, $ttl);
        Cache::put("{$key}:expires", time() + $ttl, $ttl);

        return $value;
    }
}

// Uso
$cache = new ProbabilisticCache();

$popularPosts = $cache->remember('popular_posts', 3600, function () {
    return Post::popular()->limit(10)->get();
}, beta: 1.0);

// Alternativa: Refresh-Ahead
class RefreshAheadCache
{
    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = Cache::get($key);
        $expiresAt = Cache::get("{$key}:expires_at");

        // Atualizar antes (aos 80% do TTL)
        if ($expiresAt && now()->timestamp > ($expiresAt - $ttl * 0.2)) {
            // Atualizar async
            dispatch(function () use ($key, $ttl, $callback) {
                $newValue = $callback();
                Cache::put($key, $newValue, $ttl);
                Cache::put("{$key}:expires_at", now()->timestamp + $ttl, $ttl);
            })->afterResponse();
        }

        // Cache miss
        if ($value === null) {
            $value = $callback();
            Cache::put($key, $value, $ttl);
            Cache::put("{$key}:expires_at", now()->timestamp + $ttl, $ttl);
        }

        return $value;
    }
}

// Uso do Refresh-Ahead
$cache = new RefreshAheadCache();
$stats = $cache->remember('dashboard_stats', 300, function () {
    return [
        'users' => User::count(),
        'orders' => Order::count(),
        'revenue' => Order::sum('total'),
    ];
});
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
