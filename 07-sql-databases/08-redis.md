# 6.8 Redis

## Resumo

> **Redis** — banco in-memory key-value para cache, queues, sessões e dados real-time.
>
> **Estruturas:** strings, hashes, lists, sets, sorted sets. Guarda os dados na RAM (muito rápido).
>
> **Importante:** No Laravel: Cache::store('redis'), SESSION_DRIVER=redis, QUEUE_CONNECTION=redis. Leaderboards com sorted sets (zadd, zrevrange).

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
Redis — banco in-memory (key-value store). Serve para cache, queues, sessões e dados real-time.

**O essencial:**
- Guarda os dados na RAM (muito rápido)
- Estrutura key-value
- Estruturas: strings, lists, sets, hashes

---

## Como funciona

**Instalação e config:**

```bash
# Instalar Redis
brew install redis  # macOS
apt-get install redis  # Ubuntu

# Iniciar
redis-server

# Conexão no Laravel (.env)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Operações básicas:**

```php
use Illuminate\Support\Facades\Redis;

// SET (definir o valor)
Redis::set('user:1:name', 'João Silva');

// GET (ler o valor)
$name = Redis::get('user:1:name');  // 'João Silva'

// SETEX (com TTL em segundos)
Redis::setex('temp:data', 3600, 'value');  // Expira em 1 hora

// DEL (apagar)
Redis::del('user:1:name');

// EXISTS (checar se existe)
if (Redis::exists('user:1:name')) {
    // A chave existe
}

// INCR / DECR (incremento/decremento)
Redis::incr('page:views');  // +1
Redis::incrby('page:views', 10);  // +10
Redis::decr('page:views');  // -1
```

**Estruturas de dados:**

```php
// HASH (array associativo)
Redis::hset('user:1', 'name', 'João');
Redis::hset('user:1', 'email', 'joao@email.com');
Redis::hget('user:1', 'name');  // 'João'
Redis::hgetall('user:1');  // ['name' => 'João', 'email' => '...']

// LIST (lista)
Redis::rpush('queue', 'job1');  // Adicionar no fim
Redis::rpush('queue', 'job2');
Redis::lpop('queue');  // Tirar do começo ('job1')
Redis::lrange('queue', 0, -1);  // Todos os elementos

// SET (conjunto de elementos únicos)
Redis::sadd('tags', 'php');
Redis::sadd('tags', 'laravel');
Redis::sadd('tags', 'php');  // Duplicata não entra
Redis::smembers('tags');  // ['php', 'laravel']
Redis::sismember('tags', 'php');  // true

// SORTED SET (conjunto ordenado)
Redis::zadd('leaderboard', 100, 'user1');
Redis::zadd('leaderboard', 250, 'user2');
Redis::zadd('leaderboard', 150, 'user3');
Redis::zrevrange('leaderboard', 0, 9);  // Top 10 (decrescente)
// ['user2', 'user3', 'user1']
```

---

## Quando usar

**Use Redis para:**
- Cache (dados lidos com frequência)
- Sessões (acesso rápido)
- Queues (Jobs)
- Rate limiting
- Dados real-time (leaderboards, counters)
- Pub/Sub (Broadcasting)

**Não use para:**
- Persistência permanente (Redis é in-memory; sem persistence, os dados somem no restart)
- Volumes grandes (limitado pela RAM)
- Queries complexas (não tem SQL)

---

## Exemplo prático

**Cache de dados:**

```php
// Cachear o resultado da query
use Illuminate\Support\Facades\Cache;

$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});

// Com o driver Redis (.env: CACHE_DRIVER=redis)
$users = Cache::store('redis')->remember('users.all', 3600, function () {
    return User::all();
});

// Cache com tags
$posts = Cache::tags(['posts', 'published'])->remember('posts.published', 3600, function () {
    return Post::where('published', true)->get();
});

// Limpar a tag
Cache::tags(['posts'])->flush();
```

**Rate Limiting:**

```php
use Illuminate\Support\Facades\RateLimiter;

// Limitar tentativas de login
if (RateLimiter::tooManyAttempts('login:' . $email, 5)) {
    $seconds = RateLimiter::availableIn('login:' . $email);
    throw new TooManyRequestsException("Tente de novo em {$seconds} segundos");
}

RateLimiter::hit('login:' . $email, 60);  // +1 tentativa, TTL 60 segundos

// Depois do login com sucesso
RateLimiter::clear('login:' . $email);

// API rate limiting
Route::middleware('throttle:60,1')->group(function () {
    // 60 requests por minuto
});
```

**Session Storage:**

```php
// .env
SESSION_DRIVER=redis

// Sessões vão automaticamente para o Redis
session(['key' => 'value']);
$value = session('key');
```

**Queues (filas):**

```php
// .env
QUEUE_CONNECTION=redis

// Job vai automaticamente para a queue no Redis
SendEmail::dispatch($user);

// Iniciar o worker
php artisan queue:work redis
```

**Counters (contadores):**

```php
// Contador de views
class PostController extends Controller
{
    public function show(Post $post)
    {
        // Incremento no Redis
        Redis::incr("post:{$post->id}:views");

        // Sincronizar com o banco de vez em quando (em background)
        dispatch(new SyncPostViews($post));

        return view('posts.show', compact('post'));
    }

    public function getViews(Post $post): int
    {
        // Leitura rápida no Redis
        return (int) Redis::get("post:{$post->id}:views") ?: 0;
    }
}

// Job de sincronização
class SyncPostViews implements ShouldQueue
{
    public function handle(): void
    {
        $postIds = Post::pluck('id');

        foreach ($postIds as $postId) {
            $views = Redis::get("post:{$postId}:views");

            if ($views) {
                Post::where('id', $postId)->update(['views' => $views]);
            }
        }
    }
}
```

**Leaderboard (top de jogadores):**

```php
class LeaderboardService
{
    public function addScore(User $user, int $score): void
    {
        // Adicionar no sorted set
        Redis::zadd('leaderboard', $score, $user->id);
    }

    public function getTop(int $limit = 10): Collection
    {
        // Top N em ordem decrescente
        $userIds = Redis::zrevrange('leaderboard', 0, $limit - 1);

        return User::whereIn('id', $userIds)
            ->get()
            ->sortBy(function ($user) use ($userIds) {
                return array_search($user->id, $userIds);
            });
    }

    public function getUserRank(User $user): int
    {
        // Posição do usuário (do maior para o menor)
        $rank = Redis::zrevrank('leaderboard', $user->id);

        return $rank !== null ? $rank + 1 : 0;
    }
}
```

**Lock (bloqueio):**

```php
use Illuminate\Support\Facades\Cache;

// Pegar o lock
$lock = Cache::lock('process-orders', 10);  // 10 segundos

if ($lock->get()) {
    try {
        // Seção crítica (só um processo)
        processOrders();
    } finally {
        $lock->release();
    }
} else {
    // Não conseguiu o lock
    Log::info('Outro processo já está rodando');
}

// Ou com release automático
Cache::lock('process-orders', 10)->block(5, function () {
    // Espera até 5 segundos, depois executa
    processOrders();
});
```

**Pub/Sub (Broadcasting):**

```php
// Publisher
Redis::publish('notifications', json_encode([
    'message' => 'Novo pedido criado',
    'order_id' => $order->id,
]));

// Subscriber (em processo separado)
Redis::subscribe(['notifications'], function (string $message) {
    $data = json_decode($message, true);
    Log::info('Notificação recebida', $data);
});
```

**Cache aside pattern:**

```php
class UserRepository
{
    public function find(int $id): ?User
    {
        // 1. Checar o cache
        $cached = Redis::get("user:{$id}");

        if ($cached) {
            return unserialize($cached);
        }

        // 2. Carregar do banco
        $user = User::find($id);

        if ($user) {
            // 3. Guardar no cache
            Redis::setex("user:{$id}", 3600, serialize($user));
        }

        return $user;
    }

    public function update(User $user): void
    {
        // 1. Atualizar o banco
        $user->save();

        // 2. Invalidar o cache
        Redis::del("user:{$user->id}");

        // Ou atualizar o cache
        Redis::setex("user:{$user->id}", 3600, serialize($user));
    }
}
```

---

## Na entrevista

> "Redis é um banco in-memory key-value para cache, queues e sessões. Estruturas: strings, hashes, lists, sets, sorted sets. No Laravel: Cache::store('redis'), SESSION_DRIVER=redis, QUEUE_CONNECTION=redis. Rate limiting com RateLimiter. Leaderboards com sorted sets (zadd, zrevrange). Lock com Cache::lock() para seção crítica. Pub/Sub para real-time. Cache aside: checa o cache → carrega do banco → guarda no cache."

---

## Exercícios práticos

### Exercício 1: Rate Limiting na API

Limite o endpoint da API: no máximo 10 requests por minuto por IP. Se passar, devolva 429.

<details>
<summary>Solução</summary>

```php
// Middleware de rate limiting
class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 10, int $decayMinutes = 1)
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Muitas requisições. Tente de novo mais tarde.',
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', $seconds);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            RateLimiter::remaining($key, $maxAttempts)
        );
    }

    protected function resolveRequestSignature(Request $request): string
    {
        return 'api:' . $request->ip();
    }

    protected function addHeaders($response, int $maxAttempts, int $remainingAttempts)
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);
    }
}

// Uso nas routes
Route::middleware(['rate.limit:10,1'])->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});

// Ou o throttle middleware nativo
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});

// Para usuários autenticados (por user_id)
Route::middleware('auth:sanctum', 'throttle:60,1')->group(function () {
    Route::post('/api/posts', [PostController::class, 'store']);
});

// No RouteServiceProvider para limite customizado
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Limites diferentes por tipo de usuário
RateLimiter::for('api', function (Request $request) {
    return $request->user()?->is_premium
        ? Limit::perMinute(1000)->by($request->user()->id)
        : Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});
```
</details>

### Exercício 2: Leaderboard (top de jogadores)

Crie um ranking de jogadores. Precisa devolver o top 100 e a posição de um jogador específico.

<details>
<summary>Solução</summary>

```php
class LeaderboardService
{
    protected string $key = 'game:leaderboard';

    // Adicionar/atualizar o score do jogador
    public function updateScore(User $user, int $score): void
    {
        // zadd adiciona ou atualiza o score
        Redis::zadd($this->key, $score, $user->id);
    }

    // Incrementar o score
    public function incrementScore(User $user, int $points): int
    {
        return Redis::zincrby($this->key, $points, $user->id);
    }

    // Pegar o top N
    public function getTop(int $limit = 100): Collection
    {
        // zrevrange devolve do maior para o menor
        $userIds = Redis::zrevrange($this->key, 0, $limit - 1, 'WITHSCORES');

        // $userIds = ['user1' => 1000, 'user2' => 950, ...]

        $users = User::whereIn('id', array_keys($userIds))->get()->keyBy('id');

        return collect($userIds)->map(function ($score, $userId) use ($users) {
            return [
                'user' => $users[$userId] ?? null,
                'score' => (int) $score,
                'rank' => Redis::zrevrank($this->key, $userId) + 1,
            ];
        })->values();
    }

    // Pegar a posição do jogador
    public function getUserRank(User $user): ?int
    {
        $rank = Redis::zrevrank($this->key, $user->id);

        return $rank !== null ? $rank + 1 : null;
    }

    // Pegar o score do jogador
    public function getUserScore(User $user): int
    {
        return (int) Redis::zscore($this->key, $user->id) ?: 0;
    }

    // Pegar os jogadores em volta do usuário
    public function getSurroundingPlayers(User $user, int $range = 5): Collection
    {
        $userRank = $this->getUserRank($user);

        if (!$userRank) {
            return collect();
        }

        $start = max(0, $userRank - $range - 1);
        $end = $userRank + $range - 1;

        $userIds = Redis::zrevrange($this->key, $start, $end, 'WITHSCORES');

        $users = User::whereIn('id', array_keys($userIds))->get()->keyBy('id');

        return collect($userIds)->map(function ($score, $userId) use ($users) {
            return [
                'user' => $users[$userId] ?? null,
                'score' => (int) $score,
                'rank' => Redis::zrevrank($this->key, $userId) + 1,
            ];
        })->values();
    }

    // Limpar o leaderboard
    public function clear(): void
    {
        Redis::del($this->key);
    }
}

// Uso
$leaderboard = new LeaderboardService();

// Atualizar o score
$leaderboard->updateScore($user, 1500);
$leaderboard->incrementScore($user, 100);

// Top 10
$top10 = $leaderboard->getTop(10);

// Posição do jogador
$rank = $leaderboard->getUserRank($user);

// Jogadores em volta
$surrounding = $leaderboard->getSurroundingPlayers($user, 3);

// API endpoint
public function leaderboard()
{
    $leaderboard = new LeaderboardService();

    return response()->json([
        'top_100' => $leaderboard->getTop(100),
        'current_user' => [
            'rank' => $leaderboard->getUserRank(auth()->user()),
            'score' => $leaderboard->getUserScore(auth()->user()),
        ],
    ]);
}
```
</details>

### Exercício 3: Distributed Lock

Crie um sistema de locks para impedir que vários processos executem a mesma tarefa ao mesmo tempo.

<details>
<summary>Solução</summary>

```php
// Uso básico do Cache::lock()
class OrderProcessor
{
    public function processOrder(Order $order): void
    {
        $lock = Cache::lock("order:{$order->id}:processing", 10);

        if ($lock->get()) {
            try {
                // Só um processo executa este código
                $this->doProcessing($order);
            } finally {
                $lock->release();
            }
        } else {
            Log::info("Pedido {$order->id} já está em processamento");
        }
    }
}

// Com espera automática
class EmailSender
{
    public function sendBulkEmails(Collection $users): void
    {
        Cache::lock('send-bulk-emails', 60)->block(5, function () use ($users) {
            // Espera até 5 segundos pelo lock
            // Se pegou — executa; senão throw LockTimeoutException
            foreach ($users as $user) {
                Mail::to($user)->send(new NewsletterEmail());
            }
        });
    }
}

// Renovar o lock (operações longas)
class DataImporter
{
    public function import(string $file): void
    {
        $lock = Cache::lock('data-import', 120);  // 2 minutos

        if ($lock->get()) {
            try {
                $lines = file($file);

                foreach ($lines as $line) {
                    $this->processLine($line);

                    // Renovar o lock a cada 100 linhas
                    if ($line % 100 === 0) {
                        $lock->get();  // Atualizar o TTL
                    }
                }
            } finally {
                $lock->release();
            }
        }
    }
}

// Implementação custom com Redis
class CustomLock
{
    protected string $key;
    protected int $timeout;
    protected ?string $owner = null;

    public function __construct(string $key, int $timeout = 10)
    {
        $this->key = "lock:{$key}";
        $this->timeout = $timeout;
    }

    public function acquire(): bool
    {
        $this->owner = Str::random(20);

        // SET NX EX — seta se não existir, com TTL
        $acquired = Redis::set(
            $this->key,
            $this->owner,
            'EX',
            $this->timeout,
            'NX'
        );

        return $acquired === true;
    }

    public function release(): bool
    {
        // Apagar só se formos o dono (Lua script para atomicidade)
        $script = <<<'LUA'
            if redis.call("get", KEYS[1]) == ARGV[1] then
                return redis.call("del", KEYS[1])
            else
                return 0
            end
        LUA;

        return Redis::eval($script, 1, $this->key, $this->owner) === 1;
    }

    public function forceRelease(): void
    {
        Redis::del($this->key);
    }
}

// Uso do lock custom
$lock = new CustomLock('critical-section', 30);

if ($lock->acquire()) {
    try {
        // Seção crítica
        processCriticalTask();
    } finally {
        $lock->release();
    }
} else {
    Log::info('Não conseguiu o lock');
}

// Distributed lock para scheduled tasks
class ScheduledTaskLock
{
    public function handle(): void
    {
        $lock = Cache::lock('scheduled:daily-report', 3600);

        if ($lock->get()) {
            // Só um servidor executa esta tarefa
            try {
                $this->generateDailyReport();
            } finally {
                $lock->release();
            }
        }
    }
}

// No Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->call(function () {
        Cache::lock('scheduled:cleanup', 3600)->get(function () {
            // Lógica de cleanup
        });
    })->daily();
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
