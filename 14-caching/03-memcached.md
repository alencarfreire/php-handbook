# 14.3 Memcached

## Resumo

> **Memcached** — cache in-memory distribuído para key-value simples. Mais rápido que Redis, mas sem data structures, persistence, transactions.
>
> Setup multi-server com **consistent hashing** para horizontal scaling. Eviction: **LRU** (Least Recently Used) automático. Laravel: `Cache::store('memcached')->remember()`.
>
> **Redis vs Memcached:** Redis para data structures/persistence/pub-sub, Memcached para velocidade máxima no cache simples. No Laravel o usual é Redis (mais funções).

---

## Conteúdo

- [O que é](#o-que-é)
- [Quando usar Memcached](#quando-usar-memcached)
- [Instalação](#instalação)
- [Uso básico](#uso-básico)
- [Setup multi-server](#setup-multi-server)
- [Stats e monitoramento](#stats-e-monitoramento)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Memcached:**
Cache in-memory distribuído. Mais simples e mais rápido que Redis, mas só key-value (sem data structures).

**Redis vs Memcached:**

| Critério | Redis | Memcached |
|----------|-------|-----------|
| Data structures | ✅ Sim (lists, sets, hashes) | ❌ Só strings |
| Persistence | ✅ RDB, AOF | ❌ Não |
| Replication | ✅ Sim | ❌ Não (só client-side) |
| Transactions | ✅ Sim | ❌ Não |
| Pub/Sub | ✅ Sim | ❌ Não |
| Velocidade | Muito rápido | Um pouco mais rápido |
| Memory efficiency | Boa | Excelente |
| Use case | Cache + mais | Só cache |

---

## Quando usar Memcached

**Memcached para:**
- ✅ Cache key-value simples
- ✅ Velocidade máxima
- ✅ Horizontal scaling (multi-server)
- ✅ Menos memory overhead

**Redis para:**
- ✅ Data structures (lists, sets, sorted sets)
- ✅ Persistence
- ✅ Pub/Sub
- ✅ Transactions
- ✅ Lógica mais complexa

---

## Instalação

**Docker:**

```bash
docker run -d --name memcached -p 11211:11211 memcached:alpine
```

**Laravel config:**

```php
// .env
CACHE_DRIVER=memcached
MEMCACHED_HOST=127.0.0.1

// config/cache.php
'memcached' => [
    'driver' => 'memcached',
    'servers' => [
        [
            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
            'port' => env('MEMCACHED_PORT', 11211),
            'weight' => 100,
        ],
    ],
],
```

---

## Uso básico

```php
// put
Cache::store('memcached')->put('key', 'value', 3600);

// get
$value = Cache::store('memcached')->get('key');

// remember
$users = Cache::store('memcached')->remember('users', 3600, function () {
    return User::all();
});

// forget
Cache::store('memcached')->forget('key');

// flush
Cache::store('memcached')->flush();

// increment/decrement
Cache::store('memcached')->increment('counter');
Cache::store('memcached')->decrement('counter');
```

---

## Setup multi-server

**Consistent hashing para distribuir:**

```php
// config/cache.php
'memcached' => [
    'driver' => 'memcached',
    'persistent_id' => 'memcached_pool_id',
    'sasl' => [
        env('MEMCACHED_USERNAME'),
        env('MEMCACHED_PASSWORD'),
    ],
    'options' => [
        // Libmemcached options
    ],
    'servers' => [
        [
            'host' => '10.0.0.1',
            'port' => 11211,
            'weight' => 100,
        ],
        [
            'host' => '10.0.0.2',
            'port' => 11211,
            'weight' => 100,
        ],
        [
            'host' => '10.0.0.3',
            'port' => 11211,
            'weight' => 100,
        ],
    ],
],
```

**Consistent Hashing:**
- As chaves se espalham pelos servidores
- Adicionar/remover servidor mexe pouco na distribuição

---

## Política de eviction

**Memcached usa LRU (Least Recently Used):**

```
Memória cheia → remove as keys least recently used
```

**Laravel: você não controla a eviction (é automática).**

---

## Proteção contra cache stampede

**Problema: cache miss → todos os requests vão no banco**

**Solução: Lock**

```php
$users = Cache::lock('users_list')->get(function () {
    return Cache::remember('users', 3600, function () {
        return User::all();
    });
});
```

---

## Pattern de cache distribuído

**Cenário: 3 web servers + 3 servidores Memcached**

```
Web Server 1 ──┐
Web Server 2 ──┼──→ Memcached Cluster ──┬──→ Server 1
Web Server 3 ──┘                        ├──→ Server 2
                                        └──→ Server 3
```

**Consistent hashing distribui sozinho:**

```php
// No Web Server 1
Cache::put('user:1', $user, 3600);
// → Memcached Server 2 (consistent hash)

// No Web Server 2
Cache::get('user:1');
// → Memcached Server 2 (o mesmo servidor!)
```

---

## Stats e monitoramento

**Stats do Memcached:**

```bash
# Conectar
telnet localhost 11211

# Stats
stats

# Items
stats items

# Slabs
stats slabs
```

**Laravel:**

```php
// Laravel não tem stats built-in para Memcached
// Use o client direto

$memcached = Cache::store('memcached')->getMemcached();
$stats = $memcached->getStats();

foreach ($stats as $server => $stat) {
    echo "Servidor: {$server}\n";
    echo "Memória: {$stat['bytes']} / {$stat['limit_maxbytes']}\n";
    echo "Items: {$stat['curr_items']}\n";
    echo "Hits: {$stat['get_hits']}\n";
    echo "Misses: {$stat['get_misses']}\n";
}
```

---

## Namespacing das chaves

**Problema: apps diferentes → conflitos**

```php
// App 1
Cache::put('user:1', $user1);

// App 2
Cache::put('user:1', $user2);  // Conflito!
```

**Solução: Prefix**

```php
// config/cache.php
'memcached' => [
    'driver' => 'memcached',
    'prefix' => env('CACHE_PREFIX', 'myapp'),  // myapp:user:1
],
```

---

## Serialização

**Memcached serializa sozinho:**

```php
// Laravel usa serialize/unserialize
Cache::put('user', $user, 3600);  // serialize($user)
$user = Cache::get('user');       // unserialize(...)

// Dá para trocar o serializer
$memcached->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_JSON);
```

---

## Storage de sessão

```php
// config/session.php
'driver' => 'memcached',
'connection' => 'default',
```

**Vantagens:**
- ✅ Sessões disponíveis em todos os web servers
- ✅ Rápido (in-memory)
- ✅ Auto-expiration

**Desvantagens:**
- ❌ A session pode ser evicted (se faltar memória)

---

## Boas práticas

```
✓ Multi-server para high availability
✓ Consistent hashing para distribuir
✓ Prefix para namespacing
✓ Monitoring: hit rate, memory usage
✓ TTL em todas as keys
✓ Não guardar dado crítico (pode ser evicted)
✓ Cache só de dado read-heavy
✓ Para persistência, use Redis
```

---

## Memcached vs Redis no Laravel

**Memcached se:**
- Você só precisa de cache key-value simples
- Velocidade máxima é crítica
- Setup multi-server

**Redis se:**
- Precisa de data structures (lists, sets, hashes, sorted sets)
- Precisa de Pub/Sub (Laravel Broadcasting)
- Precisa de Persistence
- Precisa de Queues
- Precisa de Transactions

**No Laravel o usual é Redis** (mais funções, velocidade quase igual).

---

## Migração: Memcached → Redis

```php
// Antes (Memcached)
Cache::store('memcached')->put('key', 'value', 3600);

// Depois (Redis)
Cache::store('redis')->put('key', 'value', 3600);

// Ou mudar o driver
CACHE_DRIVER=redis  # .env
```

**Para cache simples, a diferença é mínima.**

---

## Na entrevista

> "Memcached é cache in-memory distribuído, mais simples e um pouco mais rápido que Redis. Só key-value — sem data structures, persistence, pub/sub. Multi-server com consistent hashing para horizontal scaling. Eviction: LRU automático. Use case: cache simples, velocidade máxima, setup distribuído. No Laravel, Redis costuma ganhar (data structures, pub/sub, queues, persistence). Memcached entra quando você só precisa de cache key-value com multi-server. Boas práticas: prefix para namespacing, monitorar hit rate, TTL, não guardar dado crítico."

---

## Exercícios práticos

### Exercício 1: Setup multi-server do Memcached

Configure o Laravel para trabalhar com 3 servidores Memcached. Implemente um service que monitora as stats de todos os servidores.

<details>
<summary>Solução</summary>

```php
// config/cache.php
'memcached' => [
    'driver' => 'memcached',
    'persistent_id' => 'memcached_pool',
    'options' => [
        // Usar consistent hashing
        Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
        Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
    ],
    'servers' => [
        [
            'host' => env('MEMCACHED_HOST_1', '10.0.0.1'),
            'port' => env('MEMCACHED_PORT_1', 11211),
            'weight' => 100,
        ],
        [
            'host' => env('MEMCACHED_HOST_2', '10.0.0.2'),
            'port' => env('MEMCACHED_PORT_2', 11211),
            'weight' => 100,
        ],
        [
            'host' => env('MEMCACHED_HOST_3', '10.0.0.3'),
            'port' => env('MEMCACHED_PORT_3', 11211),
            'weight' => 100,
        ],
    ],
],

// Service de monitoramento
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MemcachedMonitoringService
{
    public function getStats(): array
    {
        $memcached = Cache::store('memcached')->getMemcached();
        $stats = $memcached->getStats();

        $result = [];

        foreach ($stats as $server => $stat) {
            if ($stat === false) {
                $result[$server] = ['status' => 'offline'];
                continue;
            }

            $result[$server] = [
                'status' => 'online',
                'uptime' => $stat['uptime'],
                'memory' => [
                    'bytes' => $stat['bytes'],
                    'limit_maxbytes' => $stat['limit_maxbytes'],
                    'usage_percent' => round(($stat['bytes'] / $stat['limit_maxbytes']) * 100, 2),
                ],
                'items' => [
                    'current' => $stat['curr_items'],
                    'total' => $stat['total_items'],
                ],
                'operations' => [
                    'get_hits' => $stat['get_hits'],
                    'get_misses' => $stat['get_misses'],
                    'hit_rate' => $this->calculateHitRate($stat),
                ],
                'connections' => [
                    'current' => $stat['curr_connections'],
                    'total' => $stat['total_connections'],
                ],
                'evictions' => $stat['evictions'],
            ];
        }

        return $result;
    }

    private function calculateHitRate(array $stat): float
    {
        $total = $stat['get_hits'] + $stat['get_misses'];

        if ($total === 0) {
            return 0;
        }

        return round(($stat['get_hits'] / $total) * 100, 2);
    }

    public function getAggregatedStats(): array
    {
        $stats = $this->getStats();

        $totalMemory = 0;
        $totalUsedMemory = 0;
        $totalItems = 0;
        $totalHits = 0;
        $totalMisses = 0;
        $totalEvictions = 0;
        $serversOnline = 0;

        foreach ($stats as $server => $stat) {
            if ($stat['status'] === 'offline') {
                continue;
            }

            $serversOnline++;
            $totalMemory += $stat['memory']['limit_maxbytes'];
            $totalUsedMemory += $stat['memory']['bytes'];
            $totalItems += $stat['items']['current'];
            $totalHits += $stat['operations']['get_hits'];
            $totalMisses += $stat['operations']['get_misses'];
            $totalEvictions += $stat['evictions'];
        }

        return [
            'servers_total' => count($stats),
            'servers_online' => $serversOnline,
            'memory_total_mb' => round($totalMemory / 1024 / 1024, 2),
            'memory_used_mb' => round($totalUsedMemory / 1024 / 1024, 2),
            'memory_usage_percent' => $totalMemory > 0
                ? round(($totalUsedMemory / $totalMemory) * 100, 2)
                : 0,
            'items_total' => $totalItems,
            'hit_rate' => ($totalHits + $totalMisses) > 0
                ? round(($totalHits / ($totalHits + $totalMisses)) * 100, 2)
                : 0,
            'evictions_total' => $totalEvictions,
        ];
    }
}

// Controller
class MemcachedStatsController extends Controller
{
    public function index(MemcachedMonitoringService $service)
    {
        return response()->json([
            'servers' => $service->getStats(),
            'aggregated' => $service->getAggregatedStats(),
        ]);
    }
}

// Command de monitoramento
class MonitorMemcachedCommand extends Command
{
    protected $signature = 'memcached:monitor';
    protected $description = 'Monitorar servidores Memcached';

    public function handle(MemcachedMonitoringService $service)
    {
        $stats = $service->getStats();

        $this->info('Status dos servidores Memcached:');
        $this->newLine();

        foreach ($stats as $server => $stat) {
            $this->line("Servidor: {$server}");

            if ($stat['status'] === 'offline') {
                $this->error('  Status: OFFLINE');
                $this->newLine();
                continue;
            }

            $this->info('  Status: ONLINE');
            $this->line("  Memória: {$stat['memory']['usage_percent']}%");
            $this->line("  Items: {$stat['items']['current']}");
            $this->line("  Hit Rate: {$stat['operations']['hit_rate']}%");
            $this->line("  Evictions: {$stat['evictions']}");
            $this->newLine();
        }

        $aggregated = $service->getAggregatedStats();
        $this->info('Stats agregadas:');
        $this->line("  Servidores online: {$aggregated['servers_online']}/{$aggregated['servers_total']}");
        $this->line("  Uso de memória: {$aggregated['memory_usage_percent']}%");
        $this->line("  Total de items: {$aggregated['items_total']}");
        $this->line("  Hit Rate: {$aggregated['hit_rate']}%");
    }
}
```
</details>

### Exercício 2: Proteção contra cache stampede no Memcached

Implemente proteção contra cache stampede no Memcached com probabilistic early expiration.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MemcachedStampedeProtection
{
    /**
     * Cache com proteção contra stampede
     *
     * @param string $key Chave do cache
     * @param int $ttl TTL em segundos
     * @param callable $callback Função para buscar os dados
     * @param float $beta Coeficiente de early expiration (geralmente 1.0)
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback, float $beta = 1.0)
    {
        $cacheKey = "stampede:{$key}";
        $expiryKey = "stampede:{$key}:expiry";

        $value = Cache::store('memcached')->get($cacheKey);
        $expiry = Cache::store('memcached')->get($expiryKey);

        if ($value !== null && $expiry !== null) {
            $now = time();
            $timeLeft = $expiry - $now;

            // Probabilistic early expiration
            // δ (delta) — tempo de recálculo (usamos 1s)
            $delta = 1;
            $xfetch = $delta * $beta * log(mt_rand() / mt_getrandmax());

            // Se a probability diz que é hora de atualizar
            if ($timeLeft - $xfetch <= 0) {
                // Coloca um lock temporário
                $lockKey = "stampede:{$key}:lock";

                if ($this->acquireLock($lockKey, 10)) {
                    try {
                        // Recalcular o valor
                        $newValue = $callback();

                        Cache::store('memcached')->put($cacheKey, $newValue, $ttl);
                        Cache::store('memcached')->put($expiryKey, time() + $ttl, $ttl);

                        return $newValue;
                    } finally {
                        $this->releaseLock($lockKey);
                    }
                }
            }

            return $value;
        }

        // Cache miss — pegar o lock
        $lockKey = "stampede:{$key}:lock";

        if ($this->acquireLock($lockKey, 10)) {
            try {
                // Double-check
                $value = Cache::store('memcached')->get($cacheKey);
                if ($value !== null) {
                    return $value;
                }

                // Calcular o valor
                $value = $callback();

                Cache::store('memcached')->put($cacheKey, $value, $ttl);
                Cache::store('memcached')->put($expiryKey, time() + $ttl, $ttl);

                return $value;
            } finally {
                $this->releaseLock($lockKey);
            }
        }

        // Não pegou o lock — espera e tenta no cache
        usleep(100000); // 100ms

        $value = Cache::store('memcached')->get($cacheKey);

        return $value ?? $callback();
    }

    private function acquireLock(string $key, int $seconds): bool
    {
        return Cache::store('memcached')->add($key, 1, $seconds);
    }

    private function releaseLock(string $key): void
    {
        Cache::store('memcached')->forget($key);
    }
}

// Uso
$cache = new MemcachedStampedeProtection();

$users = $cache->remember('users_list', 3600, function () {
    // Query cara
    return User::with('roles', 'permissions')->get();
}, beta: 1.0);

// Alternativa: abordagem simples com Lock
class SimpleStampedeProtection
{
    public function remember(string $key, int $ttl, callable $callback)
    {
        $value = Cache::store('memcached')->get($key);

        if ($value !== null) {
            return $value;
        }

        // Tentar pegar o lock
        $lockKey = "{$key}:lock";

        if (Cache::store('memcached')->add($lockKey, 1, 10)) {
            try {
                // Double-check
                $value = Cache::store('memcached')->get($key);
                if ($value !== null) {
                    return $value;
                }

                // Calcular
                $value = $callback();
                Cache::store('memcached')->put($key, $value, $ttl);

                return $value;
            } finally {
                Cache::store('memcached')->forget($lockKey);
            }
        }

        // Esperar e tentar de novo
        $attempts = 0;
        while ($attempts < 50) { // max 5 segundos
            usleep(100000); // 100ms

            $value = Cache::store('memcached')->get($key);
            if ($value !== null) {
                return $value;
            }

            $attempts++;
        }

        // Fallback — calcular sem cache
        return $callback();
    }
}
```
</details>

### Exercício 3: Session storage com Memcached e monitoramento

Configure session storage no Memcached e crie um middleware para monitorar o hit rate das sessions.

<details>
<summary>Solução</summary>

```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'memcached'),
'connection' => env('SESSION_CONNECTION', 'default'),
'store' => env('SESSION_STORE', null),

// Middleware para monitorar sessions
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SessionMonitoring
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $sessionId = $request->session()->getId();

        // Incrementar o contador de requests
        $this->incrementSessionRequests($sessionId);

        $response = $next($request);

        $duration = microtime(true) - $startTime;

        // Logar as métricas
        $this->logSessionMetrics($sessionId, $duration);

        return $response;
    }

    private function incrementSessionRequests(string $sessionId): void
    {
        $key = "session:metrics:{$sessionId}:requests";
        Cache::increment($key);
        Cache::expire($key, 3600); // 1 hora
    }

    private function logSessionMetrics(string $sessionId, float $duration): void
    {
        $metricsKey = "session:metrics:{$sessionId}:response_times";

        $metrics = Cache::get($metricsKey, []);
        $metrics[] = $duration;

        // Guardar só os últimos 100 requests
        if (count($metrics) > 100) {
            array_shift($metrics);
        }

        Cache::put($metricsKey, $metrics, 3600);
    }
}

// Service para analisar métricas de session
namespace App\Services;

class SessionAnalyticsService
{
    public function getSessionMetrics(string $sessionId): array
    {
        $requestsKey = "session:metrics:{$sessionId}:requests";
        $responseTimesKey = "session:metrics:{$sessionId}:response_times";

        $requests = Cache::get($requestsKey, 0);
        $responseTimes = Cache::get($responseTimesKey, []);

        return [
            'session_id' => $sessionId,
            'total_requests' => $requests,
            'avg_response_time' => $this->calculateAverage($responseTimes),
            'min_response_time' => !empty($responseTimes) ? min($responseTimes) : 0,
            'max_response_time' => !empty($responseTimes) ? max($responseTimes) : 0,
        ];
    }

    public function getActiveSessionsCount(): int
    {
        // Isso pede uma implementação custom
        // Memcached não devolve todas as chaves
        // Precisa de tracking separado das sessões ativas
        return Cache::get('active_sessions_count', 0);
    }

    private function calculateAverage(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        return round(array_sum($values) / count($values), 4);
    }
}

// Tracker de sessões ativas
namespace App\Http\Middleware;

class TrackActiveSessions
{
    public function handle(Request $request, Closure $next)
    {
        $sessionId = $request->session()->getId();
        $activeSessionsKey = 'active_sessions';

        // Adicionar a sessão no set de ativas (usamos um Redis separado)
        Cache::store('redis')->put(
            "{$activeSessionsKey}:{$sessionId}",
            now()->timestamp,
            1800 // 30 minutos
        );

        // Atualizar o contador
        $this->updateActiveSessionsCount();

        return $next($request);
    }

    private function updateActiveSessionsCount(): void
    {
        // Contar as sessões ativas
        // Nota: em production é melhor usar Redis Sets
        $pattern = 'active_sessions:*';
        $keys = Cache::store('redis')->keys($pattern);

        Cache::put('active_sessions_count', count($keys), 60);
    }
}

// Dashboard controller
class SessionDashboardController extends Controller
{
    public function index(Request $request, SessionAnalyticsService $analytics)
    {
        $sessionMetrics = $analytics->getSessionMetrics(
            $request->session()->getId()
        );

        $activeSessionsCount = $analytics->getActiveSessionsCount();

        return view('dashboard.sessions', [
            'session_metrics' => $sessionMetrics,
            'active_sessions_count' => $activeSessionsCount,
        ]);
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
