# 16.5 Casos reais

## Casos reais de entrevista

### Caso 1: Flash sale de e-commerce

**Enunciado:**

```
A loja lança um flash sale: 100 produtos com desconto
por 1 hora. 10.000 usuários tentam comprar ao mesmo tempo.

Problemas:
- Overselling (vendeu mais do que tinha)
- Race conditions
- Site lento
- Servidor cai com a carga

Como você resolveria isso?
```

**Solução:**

**1. Impedir overselling:**

```php
// Pessimistic locking
DB::transaction(function () use ($productId) {
    $product = Product::where('id', $productId)
        ->lockForUpdate()  // SELECT ... FOR UPDATE
        ->first();

    if ($product->stock <= 0) {
        throw new OutOfStockException();
    }

    Order::create([...]);
    $product->decrement('stock');
});

// Ou operações atômicas no Redis
Redis::watch("product:$productId:stock");

$stock = Redis::get("product:$productId:stock");
if ($stock > 0) {
    Redis::multi();
    Redis::decr("product:$productId:stock");
    Redis::exec();
} else {
    throw new OutOfStockException();
}
```

**2. Queue para o checkout:**

```php
// Não processar o checkout de forma síncrona
Route::post('/checkout', function (Request $request) {
    // Mandar pra queue rápido
    ProcessCheckout::dispatch($request->all());

    return response()->json([
        'message' => 'Seu pedido está sendo processado',
        'queue_position' => Queue::size('checkouts') + 1
    ]);
});

// O job processa de forma assíncrona
class ProcessCheckout implements ShouldQueue
{
    public function handle()
    {
        // Checar stock
        // Criar o pedido
        // Pagamento
        // Email
    }
}
```

**3. Cache:**

```php
// Página do produto
Route::get('/product/{id}', function ($id) {
    return Cache::remember("product.$id", 300, function () use ($id) {
        return Product::with('category')->find($id);
    });
});

// Stock via Redis
$stock = Redis::get("product:$id:stock");
```

**4. Rate limiting:**

```php
// Limitar requests por usuário
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store']);
});

// 10 requests por minuto
```

**5. CDN para estáticos:**

```
CloudFlare / CloudFront faz cache de:
- Imagens dos produtos
- CSS/JS
- Product pages (stale-while-revalidate)
```

**6. Horizontal scaling:**

```
Load Balancer
├─ App Server 1
├─ App Server 2
├─ App Server 3
└─ ...

Shared:
- Redis (sessions, cache, queue)
- Database (read replicas)
```

---

### Caso 2: Performance do feed de rede social

**Enunciado:**

```
App estilo Instagram. O feed demora 5+ segundos
para usuários com muitos follows.

O usuário segue 1000 pessoas
Precisa mostrar os últimos 20 posts

Como otimizar?
```

**Solução:**

**1. O problema (Pull model):**

```php
// ❌ Lento
function getFeed(User $user)
{
    $followingIds = $user->following()->pluck('id'); // 1000 IDs

    return Post::whereIn('user_id', $followingIds)
        ->with('user', 'likes', 'comments')
        ->orderBy('created_at', 'desc')
        ->limit(20)
        ->get();
    // A query varre milhões de posts
}
```

**2. Push model (pre-compute do feed):**

```php
// Quando o usuário cria um post
class PostCreated
{
    public function handle(Post $post)
    {
        $followerIds = $post->user->followers()->pluck('id');

        // Push nos feeds pré-computados
        foreach ($followerIds->chunk(1000) as $chunk) {
            PushPostToFeeds::dispatch($post->id, $chunk);
        }
    }
}

class PushPostToFeeds implements ShouldQueue
{
    public function handle()
    {
        foreach ($this->followerIds as $followerId) {
            Redis::zadd(
                "feed:$followerId",
                $this->post->created_at->timestamp,
                $this->post->id
            );

            // Manter só os últimos 1000
            Redis::zremrangebyrank("feed:$followerId", 0, -1001);
        }
    }
}

// Leitura do feed
function getFeed(User $user)
{
    $postIds = Redis::zrevrange("feed:{$user->id}", 0, 19);

    return Post::whereIn('id', $postIds)
        ->with('user')
        ->get()
        ->sortByDesc('created_at');
}
// Muito rápido!
```

**3. Hybrid para celebrities:**

```php
function getFeed(User $user)
{
    // Feed pré-computado para users comuns
    $preComputedIds = Redis::zrevrange("feed:{$user->id}", 0, 19);

    // Live query para celebrities
    $celebrityIds = $user->following()
        ->where('followers_count', '>', 1000000)
        ->pluck('id');

    $liveIds = Post::whereIn('user_id', $celebrityIds)
        ->where('created_at', '>', now()->subDays(3))
        ->pluck('id');

    // Merge e sort
    $allIds = collect($preComputedIds)->merge($liveIds)
        ->unique()
        ->take(20);

    return Post::whereIn('id', $allIds)->get();
}
```

**4. Cache:**

```php
// Feed do usuário por 5 minutos
Route::get('/api/feed', function () {
    $userId = auth()->id();

    return Cache::remember("feed:$userId", 300, function () use ($userId) {
        return getFeed(User::find($userId));
    });
});
```

---

### Caso 3: Timeout no payment gateway

**Enunciado:**

```
Integração com payment gateway (Stripe).
Às vezes o request dá timeout (30+ segundos).

O usuário espera e depois recebe 504 Gateway Timeout.
Mas o dinheiro pode ter sido cobrado!

Como tratar?
```

**Solução:**

**1. Processamento assíncrono:**

```php
// ❌ Síncrono (ruim)
public function checkout(Request $request)
{
    $charge = Stripe::charges()->create([...]); // Pode travar
    return redirect('/success');
}

// ✅ Assíncrono (bom)
public function checkout(Request $request)
{
    $order = Order::create([...]);

    ProcessPayment::dispatch($order);

    return response()->json([
        'message' => 'O pagamento está sendo processado',
        'order_id' => $order->id,
        'status_url' => "/orders/{$order->id}/status"
    ]);
}

class ProcessPayment implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 60;

    public function handle()
    {
        try {
            $charge = Stripe::charges()->create([...]);

            $this->order->update([
                'status' => 'paid',
                'stripe_charge_id' => $charge->id
            ]);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Retry automático
            throw $e;
        }
    }
}
```

**2. Webhook para confirmation:**

```php
// Webhook do Stripe
Route::post('/webhooks/stripe', function (Request $request) {
    $event = $request->all();

    if ($event['type'] === 'charge.succeeded') {
        $chargeId = $event['data']['object']['id'];

        Order::where('stripe_charge_id', $chargeId)
            ->update(['status' => 'paid']);
    }

    return response('OK');
});
```

**3. Idempotency key:**

```php
// Impedir charges duplicados
$idempotencyKey = "order-{$order->id}-" . now()->timestamp;

$charge = Stripe::charges()->create([
    'amount' => $order->total * 100,
    'currency' => 'brl',
    'source' => $token,
], [
    'idempotency_key' => $idempotencyKey
]);

// Request repetido com a mesma key não cria um charge novo
```

**4. Config de timeout:**

```php
// Timeout do client
$stripe = new \Stripe\StripeClient([
    'api_key' => config('stripe.secret'),
    'timeout' => 10, // 10 segundos
    'connect_timeout' => 5,
]);

// Timeout do server (nginx)
// location /checkout {
//     proxy_read_timeout 30s;
//     proxy_connect_timeout 10s;
// }
```

---

### Caso 4: Migration zero-downtime

**Enunciado:**

```
Precisa renomear uma coluna em production sem downtime:
users.name → users.full_name

Problema:
- A versão antiga do código lê `name`
- A versão nova lê `full_name`
- O deploy é gradual (rolling update)

Como fazer?
```

**Solução (Expand-Contract):**

**Fase 1: Expand (adicionar a coluna nova):**

```php
// Migration 1
Schema::table('users', function (Blueprint $table) {
    $table->string('full_name')->nullable()->after('name');
});

// Copiar os dados
DB::table('users')->update([
    'full_name' => DB::raw('name')
]);
```

**Fase 2: Dual Write (escrever nas duas):**

```php
// Código v1.1 (lê name, escreve nas duas)
class User extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            $user->full_name = $user->name;
        });
    }
}

// Deploy v1.1
// Agora as duas colunas ficam sincronizadas
```

**Fase 3: Switch Read (ler da nova):**

```php
// Código v1.2 (lê full_name, escreve nas duas)
class User extends Model
{
    protected $appends = ['name'];

    // Accessor para backward compatibility
    public function getNameAttribute()
    {
        return $this->full_name;
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($user) {
            // Sync das duas colunas
            if (isset($user->attributes['full_name'])) {
                $user->name = $user->full_name;
            } else if (isset($user->attributes['name'])) {
                $user->full_name = $user->name;
            }
        });
    }
}

// Deploy v1.2
```

**Fase 4: Contract (remover a antiga):**

```php
// Código v1.3 (só full_name)
class User extends Model
{
    // Tirar o accessor, tirar o boot
}

// Migration 2
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('name');
});

// Deploy v1.3
```

**Timeline:**

```
Semana 1: Fase 1 (expand)
Semana 2: Fase 2 (dual write) + deploy
Semana 3: Monitorar, verificar os dados
Semana 4: Fase 3 (switch read) + deploy
Semana 5: Monitorar
Semana 6: Fase 4 (contract) + deploy
```

---

### Caso 5: Refatoração de código legado

**Enunciado:**

```
Você herdou o projeto:
- 1 arquivo com 5000 linhas (God Class)
- Sem testes
- Sem documentação
- Production funciona, mas precisa adicionar uma feature nova

Por onde começar?
```

**Solução:**

**1. Entender o que o código faz:**

```
- Rodar local
- Testar na mão os fluxos principais
- Desenhar o diagrama de flow
- Achar os entry points
```

**2. Adicionar testes (Characterization Tests):**

```php
// Testes do comportamento atual
public function test_user_can_login()
{
    $response = $this->post('/login', [
        'email' => 'joao@email.com',
        'password' => 'password'
    ]);

    $response->assertRedirect('/dashboard');
}

// Cobrir os caminhos críticos
// - Cadastro
// - Login
// - Checkout
// - etc
```

**3. Refatorar aos poucos:**

```php
// Era: 1 classe com 5000 linhas
class LegacyController
{
    public function checkout() { /* 500 linhas */ }
    public function processPayment() { /* 300 linhas */ }
    // ...
}

// Passo 1: Extract Method
class LegacyController
{
    public function checkout()
    {
        $this->validateCart();
        $this->calculateTotal();
        $this->processPayment();
        $this->sendEmail();
    }

    private function validateCart() { /* ... */ }
    private function calculateTotal() { /* ... */ }
}

// Passo 2: Extract Class
class CheckoutService
{
    public function process() { /* ... */ }
}

class LegacyController
{
    public function checkout()
    {
        (new CheckoutService())->process();
    }
}

// Passo 3: Dependency Injection
class LegacyController
{
    public function __construct(
        private CheckoutService $checkout
    ) {}

    public function checkout()
    {
        $this->checkout->process();
    }
}
```

**4. Strangler Fig Pattern:**

```
O código novo vive ao lado do antigo:

routes/legacy.php (rotas antigas)
routes/api.php (rotas novas)

Migrar os usuários aos poucos:
- 10% → versão nova
- 50% → versão nova
- 100% → versão nova

Quando todo mundo estiver na nova: apagar o código legacy
```

---

## Na entrevista

> "Casos reais: Flash sale — Redis atomic ops, queue no checkout, rate limiting, horizontal scaling. Feed — push model (pre-compute), hybrid para celebrities, cache. Timeout de pagamento — assíncrono via queue, webhooks, idempotency keys. Migration zero-downtime — Expand-Contract (adicionar coluna → dual write → switch read → remover a antiga). Refatoração de legado — characterization tests, refatoração gradual (Extract Method → Extract Class → DI), Strangler Fig."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
