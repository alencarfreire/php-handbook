# 14.2 Redis Cache

## Resumo

> **Redis** — in-memory key-value store com estruturas ricas (strings, hashes, lists, sets, sorted sets).
>
> Laravel: `Cache::store('redis')->remember()`. Estruturas: **hashes** (objetos), **lists** (queues), **sets** (tags, usuários online), **sorted sets** (leaderboards). **Pipeline** para batch, **Transactions** (MULTI/EXEC), **Lua scripts** para operações atômicas.
>
> Casos de uso: cache, sessões, rate limiting, distributed locks, leaderboards, pub/sub, queues. Persistência: RDB (snapshots), AOF (logs).

---

## Conteúdo

- [O que é](#o-que-é)
- [Instalação](#instalação)
- [Uso básico](#uso-básico)
- [Estruturas de dados](#estruturas-de-dados)
- [Pipeline](#pipeline-operações-em-batch)
- [Transações](#transações)
- [Padrões de cache](#padrões-de-cache)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Redis:**
In-memory key-value store. Serve como cache, message broker, session store, leaderboard e muito mais.

**Para quê:**
- Muito rápido (in-memory)
- Estruturas ricas (strings, lists, sets, hashes, sorted sets)
- Persistência (opcional)
- Pub/Sub
- Transactions
- Lua scripting

---

## Instalação

**Docker:**

```bash
docker run -d --name redis -p 6379:6379 redis:alpine
```

**Laravel config:**

```php
// config/database.php
'redis' => [
    'client' => 'phpredis',  // ou 'predis'
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,
    ],
    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,  // Database separado para cache
    ],
],
```

---

## Uso básico

**Laravel Cache:**

```php
// Put (set com TTL)
Cache::store('redis')->put('key', 'value', 3600);

// Get
$value = Cache::store('redis')->get('key');

// Remember (get ou set)
$users = Cache::store('redis')->remember('users', 3600, function () {
    return User::all();
});

// Forget (apagar)
Cache::store('redis')->forget('key');

// Flush (apagar tudo)
Cache::store('redis')->flush();
```

---

## Estruturas de dados

### 1. Strings

**Operações básicas:**

```php
// Set
Redis::set('user:1:name', 'João');

// Get
$name = Redis::get('user:1:name');  // "João"

// Set com expiration
Redis::setex('session:abc', 3600, json_encode($data));

// Increment
Redis::incr('page_views');  // 1
Redis::incr('page_views');  // 2

// Decrement
Redis::decr('stock');
```

---

### 2. Hashes (para objetos)

```php
// Set hash fields
Redis::hset('user:1', 'name', 'João');
Redis::hset('user:1', 'email', 'joao@email.com');

// Ou em batch
Redis::hmset('user:1', [
    'name' => 'João',
    'email' => 'joao@email.com',
    'age' => 30,
]);

// Get field
$name = Redis::hget('user:1', 'name');  // "João"

// Get all
$user = Redis::hgetall('user:1');
// ['name' => 'João', 'email' => 'joao@email.com', 'age' => '30']

// Increment field
Redis::hincrby('user:1', 'age', 1);  // 31
```

---

### 3. Lists (para queues)

```php
// Push na list
Redis::lpush('queue', 'task1');
Redis::lpush('queue', 'task2');  // ['task2', 'task1']

// Pop da list
$task = Redis::lpop('queue');  // 'task2'

// Blocking pop (espera o item)
$task = Redis::blpop('queue', 5);  // Espera 5 segundos

// Pegar o range
$tasks = Redis::lrange('queue', 0, -1);  // Todos os itens

// Tamanho da list
$count = Redis::llen('queue');
```

**Caso de uso: Job Queue**

```php
// Producer
Redis::rpush('jobs:default', json_encode([
    'class' => SendEmailJob::class,
    'data' => ['user_id' => 1],
]));

// Consumer
while (true) {
    $job = Redis::blpop('jobs:default', 5);
    if ($job) {
        $this->process(json_decode($job[1], true));
    }
}
```

---

### 4. Sets (elementos únicos)

```php
// Adicionar no set
Redis::sadd('online_users', 1);
Redis::sadd('online_users', 2);
Redis::sadd('online_users', 1);  // Duplicata é ignorada

// Pegar os membros
$users = Redis::smembers('online_users');  // [1, 2]

// Checar se está no set
$isOnline = Redis::sismember('online_users', 1);  // true

// Remover
Redis::srem('online_users', 1);

// Contar
$count = Redis::scard('online_users');

// Operações de set
Redis::sadd('set1', 1, 2, 3);
Redis::sadd('set2', 2, 3, 4);

$intersection = Redis::sinter('set1', 'set2');  // [2, 3]
$union = Redis::sunion('set1', 'set2');  // [1, 2, 3, 4]
$diff = Redis::sdiff('set1', 'set2');  // [1]
```

**Caso de uso: Tags**

```php
// Adicionar tags no post
Redis::sadd('post:1:tags', 'php', 'laravel', 'redis');

// Pegar posts pela tag
Redis::sadd('tag:php:posts', 1, 2, 3);
Redis::sadd('tag:laravel:posts', 1, 4);

$posts = Redis::sinter('tag:php:posts', 'tag:laravel:posts');  // [1]
```

---

### 5. Sorted Sets (para leaderboards)

```php
// Adicionar com score
Redis::zadd('leaderboard', 100, 'player1');
Redis::zadd('leaderboard', 200, 'player2');
Redis::zadd('leaderboard', 150, 'player3');

// Pegar o top N (maiores scores)
$top = Redis::zrevrange('leaderboard', 0, 9);  // Top 10
// ['player2', 'player3', 'player1']

// Pegar o rank (0-based)
$rank = Redis::zrevrank('leaderboard', 'player2');  // 0 (primeiro)

// Pegar o score
$score = Redis::zscore('leaderboard', 'player1');  // 100

// Incrementar o score
Redis::zincrby('leaderboard', 10, 'player1');  // 110

// Contar
$count = Redis::zcard('leaderboard');

// Pegar o range por score
$players = Redis::zrangebyscore('leaderboard', 100, 200);
```

**Caso de uso: Leaderboard**

```php
class LeaderboardService
{
    public function addScore(int $userId, int $score): void
    {
        Redis::zincrby('leaderboard', $score, $userId);
    }

    public function getTop(int $limit = 10): array
    {
        return Redis::zrevrange('leaderboard', 0, $limit - 1, 'WITHSCORES');
    }

    public function getUserRank(int $userId): ?int
    {
        $rank = Redis::zrevrank('leaderboard', $userId);
        return $rank !== false ? $rank + 1 : null;  // 1-based
    }

    public function getUserScore(int $userId): int
    {
        return (int) Redis::zscore('leaderboard', $userId);
    }
}
```

---

## Expiração (TTL)

```php
// Set com TTL
Redis::setex('key', 60, 'value');  // 60 segundos

// Setar TTL numa chave existente
Redis::expire('key', 60);

// Pegar o TTL
$ttl = Redis::ttl('key');  // segundos restantes

// Remover o TTL (fica para sempre)
Redis::persist('key');
```

---

## Pipeline (operações em batch)

**Problema: N network round-trips**

```php
// Lento: 100 round-trips
for ($i = 0; $i < 100; $i++) {
    Redis::set("key:{$i}", $i);
}
```

**Solução: Pipeline**

```php
// Rápido: 1 round-trip
Redis::pipeline(function ($pipe) {
    for ($i = 0; $i < 100; $i++) {
        $pipe->set("key:{$i}", $i);
    }
});
```

---

## Transações

```php
Redis::multi();
Redis::set('key1', 'value1');
Redis::set('key2', 'value2');
Redis::incr('counter');
Redis::exec();

// Todos os comandos rodam de forma atômica
```

**Watch (lock otimista):**

```php
Redis::watch('balance');

$balance = Redis::get('balance');

if ($balance >= 100) {
    Redis::multi();
    Redis::decrby('balance', 100);
    Redis::incrby('points', 10);
    $result = Redis::exec();

    if ($result === null) {
        // Transaction falhou (balance mudou)
    }
} else {
    Redis::unwatch();
}
```

---

## Lua Scripts (operações atômicas)

```php
$script = <<<'LUA'
local current = redis.call('get', KEYS[1])
if tonumber(current) >= tonumber(ARGV[1]) then
    redis.call('decrby', KEYS[1], ARGV[1])
    return 1
else
    return 0
end
LUA;

$result = Redis::eval($script, 1, 'balance', 100);

if ($result === 1) {
    // Sucesso
} else {
    // Saldo insuficiente
}
```

---

## Padrões de cache

### 1. Cache de dados do usuário

```php
class UserRepository
{
    public function find(int $id): ?User
    {
        return Redis::remember("user:{$id}", 3600, function () use ($id) {
            return User::find($id);
        });
    }

    public function save(User $user): void
    {
        $user->save();

        // Invalidar o cache
        Redis::forget("user:{$user->id}");
    }
}
```

---

### 2. Rate Limiting

```php
class RateLimiter
{
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $attempts = Redis::get($key) ?? 0;

        if ($attempts >= $maxAttempts) {
            return false;  // Rate limited
        }

        if ($attempts === 0) {
            Redis::setex($key, $decaySeconds, 1);
        } else {
            Redis::incr($key);
        }

        return true;
    }
}

// Uso
if (!$rateLimiter->attempt("login:{$ip}", 5, 60)) {
    return response('Muitas tentativas', 429);
}
```

---

### 3. Session Storage

```php
// config/session.php
'driver' => 'redis',
'connection' => 'default',
```

---

### 4. Distributed Lock

```php
$lock = Cache::lock('process_orders', 10);  // 10 segundos

if ($lock->get()) {
    try {
        // Seção crítica
        $this->processOrders();
    } finally {
        $lock->release();
    }
} else {
    // Não conseguiu o lock
}
```

---

## Persistência

**RDB (snapshot):**

```ini
# redis.conf
save 900 1     # Depois de 900s se 1 chave mudou
save 300 10    # Depois de 300s se 10 chaves mudaram
save 60 10000  # Depois de 60s se 10000 chaves mudaram
```

**AOF (append-only file):**

```ini
# redis.conf
appendonly yes
appendfsync everysec  # ou always/no
```

---

## Monitoramento

**Redis CLI:**

```bash
# Info
redis-cli info

# Uso de memória
redis-cli info memory

# Quantidade de keys
redis-cli dbsize

# Monitorar comandos
redis-cli monitor

# Slow log
redis-cli slowlog get 10
```

**Laravel:**

```php
// Pegar info
$info = Redis::info();

// Pegar memória
$memory = Redis::info('memory');

// Contar as keys
$count = Redis::dbsize();
```

---

## Boas práticas

```
✓ Databases Redis separados para cada propósito (cache, sessions, queues)
✓ Namespace nas keys (user:1:name)
✓ TTL em todas as cache keys
✓ Pipeline para batch
✓ Lua scripts para operações atômicas
✓ Monitorar o uso de memória (eviction policy)
✓ Persistência para dados críticos
✓ Redis Sentinel/Cluster para HA
✓ NÃO guardar values enormes (< 1MB)
```

---

## Na entrevista

> "Redis é um in-memory key-value store, muito rápido. Estruturas: strings (counters), hashes (objetos), lists (queues), sets (tags, usuários online), sorted sets (leaderboards). No Laravel, Cache::remember para cache. Pipeline para batch (menos round-trips). Transactions para operações atômicas. Lua scripts para lógica atômica mais complexa. Casos de uso: cache, sessões, rate limiting, distributed locks, leaderboards, queues. Persistência: RDB snapshots, AOF logs. Boas práticas: namespace nas keys, TTL, monitorar memória, eviction policy."

---

## Exercícios práticos

### Exercício 1: Leaderboard com Sorted Sets

Implemente um serviço de leaderboard de jogo com Redis Sorted Sets. Métodos: adicionar pontos, pegar o top 10, pegar o rank do jogador.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class LeaderboardService
{
    private const LEADERBOARD_KEY = 'game:leaderboard';

    /**
     * Adicionar pontos ao jogador
     */
    public function addScore(int $userId, int $score): void
    {
        // Incrementar o score no sorted set
        Redis::zincrby(self::LEADERBOARD_KEY, $score, $userId);
    }

    /**
     * Definir o valor absoluto dos pontos
     */
    public function setScore(int $userId, int $score): void
    {
        Redis::zadd(self::LEADERBOARD_KEY, $score, $userId);
    }

    /**
     * Pegar o top N de jogadores
     */
    public function getTop(int $limit = 10): array
    {
        // ZREVRANGE — do maior para o menor, WITHSCORES — incluir os scores
        $data = Redis::zrevrange(self::LEADERBOARD_KEY, 0, $limit - 1, 'WITHSCORES');

        $result = [];
        $rank = 1;

        foreach ($data as $userId => $score) {
            $result[] = [
                'rank' => $rank++,
                'user_id' => (int) $userId,
                'score' => (int) $score,
            ];
        }

        return $result;
    }

    /**
     * Pegar o rank do jogador (1-based)
     */
    public function getUserRank(int $userId): ?int
    {
        // ZREVRANK — rank 0-based
        $rank = Redis::zrevrank(self::LEADERBOARD_KEY, $userId);

        return $rank !== false ? $rank + 1 : null; // 1-based
    }

    /**
     * Pegar os pontos do jogador
     */
    public function getUserScore(int $userId): int
    {
        return (int) Redis::zscore(self::LEADERBOARD_KEY, $userId) ?? 0;
    }

    /**
     * Pegar as informações do jogador (rank + pontos)
     */
    public function getUserInfo(int $userId): ?array
    {
        $score = $this->getUserScore($userId);

        if ($score === 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'rank' => $this->getUserRank($userId),
            'score' => $score,
        ];
    }

    /**
     * Pegar jogadores num intervalo de ranks
     */
    public function getRange(int $start, int $end): array
    {
        // 0-based
        $data = Redis::zrevrange(
            self::LEADERBOARD_KEY,
            $start - 1,
            $end - 1,
            'WITHSCORES'
        );

        $result = [];
        $rank = $start;

        foreach ($data as $userId => $score) {
            $result[] = [
                'rank' => $rank++,
                'user_id' => (int) $userId,
                'score' => (int) $score,
            ];
        }

        return $result;
    }

    /**
     * Remover o jogador do leaderboard
     */
    public function removeUser(int $userId): void
    {
        Redis::zrem(self::LEADERBOARD_KEY, $userId);
    }

    /**
     * Resetar o leaderboard inteiro
     */
    public function reset(): void
    {
        Redis::del(self::LEADERBOARD_KEY);
    }
}

// Uso no controller
class LeaderboardController extends Controller
{
    public function addScore(Request $request, LeaderboardService $leaderboard)
    {
        $validated = $request->validate([
            'score' => 'required|integer|min:0',
        ]);

        $leaderboard->addScore($request->user()->id, $validated['score']);

        return response()->json([
            'message' => 'Pontos adicionados',
            'user_info' => $leaderboard->getUserInfo($request->user()->id),
        ]);
    }

    public function top(LeaderboardService $leaderboard)
    {
        return response()->json([
            'leaderboard' => $leaderboard->getTop(10),
        ]);
    }

    public function myRank(Request $request, LeaderboardService $leaderboard)
    {
        return response()->json(
            $leaderboard->getUserInfo($request->user()->id)
        );
    }
}
```
</details>

### Exercício 2: Rate Limiting com Redis

Crie um middleware de rate limiting com Redis. Limite: 60 requests por minuto por IP.

<details>
<summary>Solução</summary>

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class RateLimitMiddleware
{
    private const MAX_ATTEMPTS = 60;
    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->tooManyAttempts($key)) {
            $retryAfter = $this->availableIn($key);

            return response()->json([
                'message' => 'Muitas requisições',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        $this->hit($key);

        $response = $next($request);

        return $this->addHeaders($response, $key);
    }

    /**
     * Montar a chave única do request
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $route = $request->route() ? $request->route()->getName() : $request->path();

        return sprintf(
            'rate_limit:%s:%s',
            $request->ip(),
            $route
        );
    }

    /**
     * Checar se o limite foi atingido
     */
    protected function tooManyAttempts(string $key): bool
    {
        return $this->attempts($key) >= self::MAX_ATTEMPTS;
    }

    /**
     * Pegar a quantidade de tentativas
     */
    protected function attempts(string $key): int
    {
        return (int) Redis::get($key) ?? 0;
    }

    /**
     * Incrementar o contador
     */
    protected function hit(string $key): void
    {
        $attempts = Redis::get($key);

        if ($attempts === null) {
            // Primeiro request — setar o TTL
            Redis::setex($key, self::DECAY_SECONDS, 1);
        } else {
            // Incrementar
            Redis::incr($key);
        }
    }

    /**
     * Em quantos segundos pode tentar de novo
     */
    protected function availableIn(string $key): int
    {
        return Redis::ttl($key);
    }

    /**
     * Adicionar headers com a info do limite
     */
    protected function addHeaders($response, string $key)
    {
        $attempts = $this->attempts($key);
        $remaining = max(0, self::MAX_ATTEMPTS - $attempts);

        return $response
            ->header('X-RateLimit-Limit', self::MAX_ATTEMPTS)
            ->header('X-RateLimit-Remaining', $remaining)
            ->header('X-RateLimit-Reset', now()->addSeconds($this->availableIn($key))->timestamp);
    }
}

// Registro no Kernel.php
protected $middlewareAliases = [
    'throttle.custom' => \App\Http\Middleware\RateLimitMiddleware::class,
];

// Uso nas routes
Route::middleware('throttle.custom')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});

// Versão avançada com limites diferentes
class FlexibleRateLimiter
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $key = $this->resolveRequestSignature($request);

        // Sliding window algorithm
        $now = time();
        $windowStart = $now - $decaySeconds;

        // Remover registros antigos
        Redis::zremrangebyscore($key, 0, $windowStart);

        // Contar os requests na janela
        $currentAttempts = Redis::zcard($key);

        if ($currentAttempts >= $maxAttempts) {
            return response()->json([
                'message' => 'Muitas requisições',
            ], 429);
        }

        // Adicionar o request atual
        Redis::zadd($key, $now, $now . ':' . uniqid());

        // Setar TTL na chave
        Redis::expire($key, $decaySeconds);

        return $next($request);
    }
}
```
</details>

### Exercício 3: Distributed Lock para seção crítica

Implemente um serviço de pagamento com distributed lock para evitar cobrança em dobro.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    /**
     * Processar o pagamento com distributed lock
     */
    public function processPayment(Order $order, array $paymentData): Payment
    {
        $lockKey = "payment:order:{$order->id}";

        // Pegar o lock por 10 segundos, esperar até 5 segundos
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            throw new \Exception('O pagamento já está em processamento');
        }

        try {
            // Seção crítica
            $payment = $this->processPaymentInternal($order, $paymentData);

            return $payment;
        } finally {
            // Sempre liberar o lock
            $lock->release();
        }
    }

    /**
     * Alternativa com block() — espera o lock automaticamente
     */
    public function processPaymentWithBlock(Order $order, array $paymentData): Payment
    {
        $lockKey = "payment:order:{$order->id}";

        return Cache::lock($lockKey, 10)->block(5, function () use ($order, $paymentData) {
            return $this->processPaymentInternal($order, $paymentData);
        });
    }

    private function processPaymentInternal(Order $order, array $paymentData): Payment
    {
        // Checar se o pedido ainda não foi pago
        if ($order->status === 'paid') {
            throw new \Exception('O pedido já está pago');
        }

        return DB::transaction(function () use ($order, $paymentData) {
            // Criar o pagamento
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => $order->total,
                'method' => $paymentData['method'],
                'status' => 'processing',
            ]);

            // Chamar o gateway de pagamento
            $result = $this->chargePaymentGateway($paymentData);

            if ($result['success']) {
                $payment->update([
                    'status' => 'completed',
                    'transaction_id' => $result['transaction_id'],
                ]);

                $order->update(['status' => 'paid']);
            } else {
                $payment->update(['status' => 'failed']);
                throw new \Exception('Pagamento falhou: ' . $result['error']);
            }

            return $payment;
        });
    }

    private function chargePaymentGateway(array $paymentData): array
    {
        // Simulação da chamada ao gateway
        sleep(1);

        return [
            'success' => true,
            'transaction_id' => 'txn_' . uniqid(),
        ];
    }
}

// Uso no controller
class PaymentController extends Controller
{
    public function process(Request $request, Order $order, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'method' => 'required|in:card,paypal',
            'card_number' => 'required_if:method,card',
            'cvv' => 'required_if:method,card',
        ]);

        try {
            $payment = $paymentService->processPayment($order, $validated);

            return response()->json([
                'message' => 'Pagamento processado com sucesso',
                'payment' => $payment,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

// Implementação custom de Lock
class RedisLock
{
    private string $key;
    private int $seconds;
    private ?string $owner = null;

    public function __construct(string $key, int $seconds)
    {
        $this->key = $key;
        $this->seconds = $seconds;
    }

    public function acquire(): bool
    {
        $this->owner = uniqid();

        // SET key owner NX EX seconds
        // NX — só se a chave não existir
        // EX — expiration
        $result = Redis::set(
            $this->key,
            $this->owner,
            'EX',
            $this->seconds,
            'NX'
        );

        return $result === true;
    }

    public function release(): bool
    {
        // Lua script para check-and-delete atômico
        $script = <<<'LUA'
if redis.call("get", KEYS[1]) == ARGV[1] then
    return redis.call("del", KEYS[1])
else
    return 0
end
LUA;

        return Redis::eval($script, 1, $this->key, $this->owner) === 1;
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
