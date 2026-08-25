# 16.2 System Design

## Abordagem de System Design

**Processo:**

```
1. Esclarecer requisitos (5-10 min)
2. Arquitetura high-level (10-15 min)
3. Design detalhado (15-20 min)
4. Scaling (10 min)
5. Discussão de trade-offs (5 min)
```

---

## Exercício 1: URL Shortener (tipo bit.ly)

**1. Requisitos:**

```
Funcionais:
- Encurtar URL longa em URL curta
- Redirect da curta para a longa
- Custom aliases (opcional)
- Analytics (opcional)

Não-funcionais:
- 100M URLs por mês
- Low latency (< 100ms)
- High availability (99.9%)
- URL vive para sempre (sem expiration)
```

**2. Cálculos:**

```
Usuários:
- 100M URLs novas / mês
- 3M URLs / dia
- ~35 URLs / segundo

Leitura vs Escrita:
- ratio 100:1 (lê mais do que escreve)
- 3500 reads / segundo

Storage:
- URL média: 500 bytes
- 100M * 500 bytes = 50 GB / mês
- 50 GB * 12 = 600 GB / ano
- Em 5 anos: 3 TB
```

**3. API Design:**

```php
// Criar URL curta
POST /api/shorten
Body: {
    "long_url": "https://example.com/very/long/url",
    "custom_alias": "my-link" // opcional
}
Response: {
    "short_url": "https://short.ly/abc123",
    "long_url": "https://example.com/very/long/url"
}

// Redirect
GET /{short_code}
Response: 302 Redirect to long_url
```

**4. Database Schema:**

```sql
CREATE TABLE urls (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    long_url TEXT NOT NULL,
    user_id BIGINT,
    created_at TIMESTAMP,
    INDEX idx_short_code (short_code)
);

CREATE TABLE clicks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    url_id BIGINT,
    clicked_at TIMESTAMP,
    user_agent VARCHAR(255),
    ip_address VARCHAR(45),
    INDEX idx_url_id (url_id),
    INDEX idx_clicked_at (clicked_at)
);
```

**5. Geração do short code:**

```php
// Opção 1: Base62 encoding do ID
function encodeBase62(int $id): string
{
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $base = strlen($chars);
    $short = '';

    while ($id > 0) {
        $short = $chars[$id % $base] . $short;
        $id = floor($id / $base);
    }

    return $short ?: '0';
}

// ID 12345 → "3D7"

// Opção 2: Random + checagem de colisão
function generateShortCode(): string
{
    do {
        $code = substr(md5(uniqid()), 0, 6);
    } while (DB::table('urls')->where('short_code', $code)->exists());

    return $code;
}
```

**6. High-Level Architecture:**

```
Users
  ↓
Load Balancer (Nginx)
  ↓
App Servers (PHP-FPM)
  ↓
Cache (Redis) → Database (MySQL)
  ↓
Analytics (Queue → ClickHouse)
```

**7. Scaling:**

```
- Cache Redis: cachear URLs populares (regra 80/20)
- Database: sharding por range de short_code
- Read replicas para leitura
- CDN para estáticos
- Rate limiting para proteção
```

---

## Exercício 2: Instagram Feed

**1. Requisitos:**

```
Funcionais:
- Mostrar o feed (posts de usuários followed)
- Pagination
- Refresh do feed
- Like/comment

Não-funcionais:
- 500M daily active users
- Média de 20 follows por usuário
- Média de 2 posts por dia por usuário
```

**2. API:**

```php
// Buscar o feed
GET /api/feed?page=1&per_page=20
Response: {
    "posts": [
        {
            "id": 123,
            "user": {...},
            "image_url": "...",
            "caption": "...",
            "likes_count": 100,
            "created_at": "..."
        }
    ],
    "next_page": 2
}
```

**3. Database Schema:**

```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    username VARCHAR(50) UNIQUE
);

CREATE TABLE follows (
    follower_id BIGINT,
    following_id BIGINT,
    created_at TIMESTAMP,
    PRIMARY KEY (follower_id, following_id),
    INDEX idx_follower (follower_id)
);

CREATE TABLE posts (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    image_url VARCHAR(500),
    caption TEXT,
    created_at TIMESTAMP,
    INDEX idx_user_created (user_id, created_at)
);
```

**4. Feed Generation:**

**Approach 1: Pull (compute na leitura)**

```php
// ❌ Lento com muitos follows
function getFeed(User $user)
{
    $followingIds = $user->following()->pluck('id');

    return Post::whereIn('user_id', $followingIds)
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
}
```

**Approach 2: Push (pré-computa)**

```php
// Quando o usuário cria um post
class PostCreated
{
    public function handle(Post $post)
    {
        $followerIds = $post->user->followers()->pluck('id');

        foreach ($followerIds as $followerId) {
            // Adicionar no feed pré-computado
            Redis::zadd("feed:$followerId", $post->created_at->timestamp, $post->id);
        }
    }
}

// Leitura do feed
function getFeed(User $user)
{
    $postIds = Redis::zrevrange("feed:{$user->id}", 0, 19);
    return Post::whereIn('id', $postIds)->get();
}
```

**Hybrid approach (é o que o Instagram usa de verdade):**

```
- Push para users com poucos followers (< 1M)
- Pull para celebrities com muitos followers
- Pre-compute só posts recentes (últimos 3 dias)
```

---

## Exercício 3: Rate Limiter

**1. Requisitos:**

```
- Limitar o usuário a N requests no período
- Limits diferentes por endpoint
- Devolver 429 Too Many Requests
```

**2. Algoritmos:**

**Fixed Window:**

```php
function checkRateLimit(string $userId, int $limit): bool
{
    $key = "rate_limit:$userId:" . date('Y-m-d-H');
    $count = Redis::incr($key);

    if ($count === 1) {
        Redis::expire($key, 3600); // 1 hora
    }

    return $count <= $limit;
}

// Problema: burst no início da janela
```

**Sliding Window Log:**

```php
function checkRateLimitSliding(string $userId, int $limit, int $window): bool
{
    $key = "rate_limit:$userId";
    $now = microtime(true);
    $cutoff = $now - $window;

    // Remover os antigos
    Redis::zremrangebyscore($key, 0, $cutoff);

    // Contar os atuais
    $count = Redis::zcard($key);

    if ($count < $limit) {
        Redis::zadd($key, $now, $now);
        Redis::expire($key, $window);
        return true;
    }

    return false;
}
```

**Token Bucket:**

```php
class TokenBucket
{
    private int $capacity;
    private float $refillRate; // tokens por segundo

    public function allowRequest(string $userId): bool
    {
        $key = "token_bucket:$userId";
        $now = microtime(true);

        $data = Redis::get($key);
        if ($data) {
            [$tokens, $lastRefill] = json_decode($data);
        } else {
            $tokens = $this->capacity;
            $lastRefill = $now;
        }

        // Refill tokens
        $elapsed = $now - $lastRefill;
        $tokens = min($this->capacity, $tokens + $elapsed * $this->refillRate);

        if ($tokens >= 1) {
            $tokens -= 1;
            Redis::setex($key, 3600, json_encode([$tokens, $now]));
            return true;
        }

        return false;
    }
}
```

---

## Exercício 4: Chat System

**1. Requisitos:**

```
- Chat 1-a-1
- Chat em grupo
- Entrega em real-time
- Histórico de mensagens
- Status online/offline
```

**2. Architecture:**

```
Users → WebSocket Server → Message Queue → Database
                         → Notification Service
```

**3. Database Schema:**

```sql
CREATE TABLE conversations (
    id BIGINT PRIMARY KEY,
    type ENUM('direct', 'group'),
    created_at TIMESTAMP
);

CREATE TABLE conversation_members (
    conversation_id BIGINT,
    user_id BIGINT,
    joined_at TIMESTAMP,
    last_read_message_id BIGINT,
    PRIMARY KEY (conversation_id, user_id)
);

CREATE TABLE messages (
    id BIGINT PRIMARY KEY,
    conversation_id BIGINT,
    user_id BIGINT,
    content TEXT,
    created_at TIMESTAMP,
    INDEX idx_conversation (conversation_id, created_at)
);
```

**4. WebSocket Implementation:**

```php
// Laravel Reverb / Pusher
class MessageSent implements ShouldBroadcast
{
    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel("conversation.{$this->message->conversation_id}");
    }
}

// Frontend
Echo.private(`conversation.${conversationId}`)
    .listen('MessageSent', (e) => {
        appendMessage(e.message);
    });
```

---

## Componentes comuns

**Load Balancer:**
```
Nginx/HAProxy
- Round-robin
- Least connections
- Health checks
```

**Cache:**
```
Redis/Memcached
- Session storage
- Query results
- Rate limiting
```

**Database:**
```
- Read replicas
- Sharding
- Indexes
```

**Queue:**
```
Redis/RabbitMQ/SQS
- Processamento async
- Envio de email
- Notifications
```

**CDN:**
```
CloudFlare/CloudFront
- Arquivos estáticos
- Imagens
- Vídeos
```

---

## Trade-offs

**SQL vs NoSQL:**

```
SQL (MySQL, PostgreSQL):
✓ ACID transactions
✓ Relationships (JOIN)
✓ Strong consistency
❌ Vertical scaling

NoSQL (MongoDB, DynamoDB):
✓ Horizontal scaling
✓ Flexible schema
✓ High throughput
❌ Eventual consistency
❌ No complex queries
```

**Caching:**

```
✓ Leituras rápidas
✓ Reduz carga no DB
❌ Dado stale
❌ Complexidade de cache invalidation
```

---

## Na entrevista

> "System Design processo: esclarecer requisitos, cálculos (QPS, storage), API design, database schema, arquitetura high-level, scaling. URL Shortener: Base62 encoding, Redis cache, sharding. Instagram Feed: push vs pull, hybrid approach. Rate Limiter: Fixed Window, Sliding Window, Token Bucket. Chat: WebSocket, queue, sharding por conversation_id. Componentes: Load Balancer, Cache (Redis), DB (replicas, sharding), Queue, CDN. Trade-offs: SQL vs NoSQL, cache vs consistency."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
