# 17.4 Comparação de Message Brokers

## Resumo

> **RabbitMQ** — message broker para task queues com roteamento flexível. **Kafka** — event streaming platform para high throughput. **Redis Pub/Sub** — fire-and-forget para real-time.
>
> **Escolha:** RabbitMQ para background jobs, Kafka para event sourcing e logs, Redis para WebSockets e notifications.
>
> **Delivery:** Redis at-most-once, RabbitMQ/Kafka at-least-once, Kafka exactly-once.

---

## Conteúdo

- [Comparação rápida](#comparação-rápida)
- [RabbitMQ](#rabbitmq)
- [Kafka](#kafka)
- [Redis Pub/Sub](#redis-pubsub)
- [Delivery Guarantees](#delivery-guarantees)
- [Performance](#performance)
- [Message Ordering](#message-ordering)
- [Scaling](#scaling)
- [Use Cases](#use-cases)
- [Combinando](#combinando)
- [Migration Path](#migration-path)
- [Decision Tree](#decision-tree)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## Comparação rápida

| Critério | RabbitMQ | Kafka | Redis Pub/Sub |
|----------|----------|-------|---------------|
| **Tipo** | Message Broker | Event Streaming | Pub/Sub |
| **Garantias de entrega** | At-least-once, Exactly-once | At-least-once | At-most-once (fire-and-forget) |
| **Armazenamento** | Na queue até o ACK | No disco (retention) | Não guarda |
| **Velocidade** | ~20k msg/s | ~1M msg/s | ~1M msg/s |
| **Consumers** | Competing | Consumer Groups | Todos os subscribers |
| **Complexidade** | Média | Alta | Baixa |
| **Use case** | Task queues, RPC | Event streaming, logs | Real-time, notifications |

---

## RabbitMQ

**Arquitetura: Message Broker**

```
Producer → Exchange → Queue → Consumer(s)
```

**Prós:**
- ✅ Flexible routing (direct, topic, fanout, headers)
- ✅ Dead Letter Exchange (DLX)
- ✅ Priority queues
- ✅ Retry logic
- ✅ Message TTL
- ✅ At-least-once, Exactly-once delivery

**Contras:**
- ❌ Não serve para high-throughput (mais lento que Kafka)
- ❌ Não serve para event streaming
- ❌ Mais difícil de escalar

**Quando usar:**
- Task queues (email, processamento de imagem)
- RPC (request-reply)
- Roteamento complexo
- Background jobs

**Laravel:**

```php
// config/queue.php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'queue' => 'default',
    'connection' => 'default',
],

// Job
SendEmailJob::dispatch($user)->onQueue('emails');
```

---

## Kafka

**Arquitetura: Event Streaming Platform**

```
Producer → Topic (Partitions) → Consumer Group → Consumer(s)
```

**Prós:**
- ✅ High throughput (milhões de msg/s)
- ✅ Guarda no disco (retention de 7 days+)
- ✅ Replay de events (controle de offset)
- ✅ Event sourcing
- ✅ Horizontal scaling (partitions)
- ✅ Exactly-once semantics

**Contras:**
- ❌ Setup complexo
- ❌ Overkill para tarefas simples
- ❌ Sem routing (só topics)
- ❌ Consome mais recurso (JVM)

**Quando usar:**
- Event streaming
- Agregação de logs
- CDC (Change Data Capture)
- Real-time analytics
- Event sourcing

**Laravel:**

```php
// Publicar o event
event(new OrderCreated($order));

// Consumir o event
class OrderCreatedListener
{
    public function handle(OrderCreated $event)
    {
        // Processar o event
    }
}
```

---

## Redis Pub/Sub

**Arquitetura: Fire-and-Forget Pub/Sub**

```
Publisher → Channel → All Subscribers
```

**Prós:**
- ✅ Muito rápido (in-memory)
- ✅ API simples
- ✅ Real-time (baixa latência)
- ✅ Já vem no Redis (você já usa para cache)

**Contras:**
- ❌ Fire-and-forget (sem garantia)
- ❌ Não guarda messages
- ❌ Sem retry
- ❌ Todo subscriber recebe todas as messages

**Quando usar:**
- Notifications em real-time
- Broadcasting via WebSockets
- Chat
- Live updates
- Invalidação de cache

**Laravel:**

```php
// config/broadcasting.php
'redis' => [
    'driver' => 'redis',
    'connection' => 'default',
],

// Broadcast do event
event(new MessageSent($message));

// Frontend (Laravel Echo)
Echo.channel('chat')
    .listen('MessageSent', (e) => {
        console.log(e.message);
    });
```

---

## Delivery Guarantees

### At-most-once (Redis Pub/Sub)

```
Publisher → Redis → Subscriber
                 ↓
            pode se perder
```

**Exemplo:**

```php
// Redis Pub/Sub
Redis::publish('notifications', json_encode($notification));

// Se o subscriber estiver offline → a message se perde
```

---

### At-least-once (RabbitMQ, Kafka)

```
Publisher → Broker → Consumer
                    ↓
              ACK depois do processamento
```

**RabbitMQ:**

```php
// Consumer
public function handle()
{
    try {
        $this->process($message);
        $this->ack();  // ACK depois do processamento com sucesso
    } catch (Exception $e) {
        $this->nack();  // Sem ACK → RabbitMQ reenvia
    }
}
```

**Kafka:**

```php
// Consumer
public function handle()
{
    $this->process($message);
    $this->commit();  // Commit do offset depois do processamento
}
```

---

### Exactly-once (Kafka, RabbitMQ with deduplication)

**Kafka:**

```java
// Producer
props.put("enable.idempotence", "true");

// Consumer
props.put("isolation.level", "read_committed");
```

**RabbitMQ (deduplication):**

```php
class ProcessOrderJob implements ShouldQueue
{
    public function handle()
    {
        $idempotencyKey = "order:{$this->orderId}";

        if (Cache::has($idempotencyKey)) {
            return;  // Já processado
        }

        $this->process($this->orderId);

        Cache::put($idempotencyKey, true, 3600);
    }
}
```

---

## Performance

**Throughput (messages/second):**

```
Redis Pub/Sub:   ~1,000,000 msg/s
Kafka:           ~1,000,000 msg/s (with batching)
RabbitMQ:        ~20,000 msg/s (single queue)
```

**Latency:**

```
Redis Pub/Sub:   < 1ms
RabbitMQ:        ~5-10ms
Kafka:           ~10-50ms
```

---

## Message Ordering

**RabbitMQ:**
- Garantia: dentro de uma queue
- Com consumers em paralelo: sem garantia

**Kafka:**
- Garantia: dentro da partition
- Key-based partitioning para ordering

**Redis Pub/Sub:**
- Sem garantia de ordering

---

## Scaling

**RabbitMQ:**
- Vertical scaling (servidor mais potente)
- Clustering (horizontal scaling limitado)
- Sharding na mão

**Kafka:**
- Horizontal scaling (add partitions)
- Consumer groups (consumo em paralelo)
- Distributed by design

**Redis Pub/Sub:**
- Vertical scaling
- Redis Cluster (mas Pub/Sub não é distributed)

---

## Use Cases

### 1. Envio de email (RabbitMQ)

```php
// RabbitMQ é o ideal para task queues
SendEmailJob::dispatch($user, $email)->onQueue('emails');

// Retry, DLX, Priority
```

**Por que não Kafka:**
- Overkill
- Email não precisa ficar no histórico

**Por que não Redis:**
- Email é crítico (precisa de garantia de entrega)

---

### 2. Event Sourcing (Kafka)

```php
// Kafka é o ideal para event streaming
event(new OrderCreated($order));
event(new PaymentProcessed($payment));
event(new OrderShipped($order));

// Dá para fazer replay dos events, audit log
```

**Por que não RabbitMQ:**
- Não guarda histórico
- Sem replay

**Por que não Redis:**
- Fire-and-forget (sem histórico)

---

### 3. Chat em real-time (Redis Pub/Sub)

```php
// Redis é o ideal para real-time
Redis::publish('chat.room.1', json_encode($message));

// Broadcasting via WebSocket
```

**Por que não RabbitMQ:**
- Mais lento
- Overkill

**Por que não Kafka:**
- Overkill
- Mais complexo

---

### 4. CDC (Change Data Capture) (Kafka)

```php
// Kafka Connect + Debezium
// Escutar mudanças no banco e replicar para outros serviços
```

**Por que Kafka:**
- Event streaming
- Retention (dá para fazer replay)

---

### 5. Background Jobs (RabbitMQ ou Laravel Queues)

```php
ProcessVideoJob::dispatch($video)->onQueue('video');
```

**RabbitMQ:**
- Se você precisa de Retry, DLX, Priority

**Laravel Database Queue:**
- Para casos simples

---

## Combinando

**Na prática você usa mais de um:**

```php
// RabbitMQ para background jobs
SendEmailJob::dispatch($user);

// Kafka para event streaming
event(new OrderCreated($order));

// Redis Pub/Sub para real-time
Redis::publish('notifications', $notification);
```

---

## Migration Path

**Startup (simples):**

```
Laravel Database Queue → Redis Queue
```

**Crescimento (carga média):**

```
Redis Queue → RabbitMQ
```

**Large scale (high throughput):**

```
RabbitMQ → Kafka (para event streaming)
RabbitMQ + Kafka (use cases diferentes)
```

---

## Decision Tree

```
Precisa de garantia de entrega?
├─ Sim
│  ├─ High throughput (milhões msg/s)?
│  │  ├─ Sim → Kafka
│  │  └─ Não → RabbitMQ
│  └─ Event streaming / histórico?
│     ├─ Sim → Kafka
│     └─ Não → RabbitMQ
└─ Não
   └─ Real-time / baixa latência?
      ├─ Sim → Redis Pub/Sub
      └─ Não → RabbitMQ (mais seguro)
```

---

## Na entrevista

> "RabbitMQ é message broker para task queues: routing flexível, retry, DLX, at-least-once. Kafka é event streaming platform para high throughput (milhões de msg/s), retention no disco, replay de events, event sourcing. Redis Pub/Sub é fire-and-forget para real-time (WebSockets, chat): in-memory, muito rápido. RabbitMQ para background jobs, Kafka para event streaming e CDC, Redis para notifications em real-time. Delivery: Redis at-most-once, RabbitMQ/Kafka at-least-once, Kafka exactly-once. Ordering: RabbitMQ na queue, Kafka na partition. No Laravel: RabbitMQ para queues, Redis para broadcasting."

---

## Exercícios práticos

### Exercício 1: Escolha o Message Broker certo

Para cada cenário escolha o message broker certo (RabbitMQ, Kafka ou Redis Pub/Sub) e explique o porquê.

**Cenários:**
1. Enviar emails de notificação depois do pedido
2. Sincronizar dados entre microsserviços (CDC)
3. Chat em real-time no app web
4. Processar milhões de logs por segundo
5. Notificar usuários online sobre mensagens novas

<details>
<summary>Solução</summary>

**1. Emails de notificação — RabbitMQ**

**Por que:**
- Precisa de garantia de entrega (at-least-once)
- Retry quando der erro
- Dead Letter Exchange para failed jobs
- High throughput não é crítico
- Background job processing

```php
// app/Jobs/SendOrderConfirmationEmail.php
class SendOrderConfirmationEmail implements ShouldQueue
{
    public $connection = 'rabbitmq';
    public $queue = 'emails';
    public $tries = 3;
    public $backoff = [60, 300, 900];

    public function __construct(public Order $order) {}

    public function handle()
    {
        Mail::to($this->order->user)->send(
            new OrderConfirmation($this->order)
        );
    }
}
```

**2. CDC (Change Data Capture) — Kafka**

**Por que:**
- Precisa do histórico de mudanças (retention)
- Replay para reconstruir o estado
- High throughput
- Event sourcing pattern
- Ordering garantido na partition

```php
// Kafka para CDC
class UserObserver
{
    public function created(User $user)
    {
        Kafka::publishOn('user-changes')
            ->withBodyKey('user_id', $user->id)
            ->withMessage([
                'operation' => 'INSERT',
                'data' => $user->toArray(),
            ])
            ->send();
    }
}
```

**3. Chat em real-time — Redis Pub/Sub**

**Por que:**
- Latência muito baixa (< 1ms)
- Fire-and-forget serve para chat
- Integração simples com Laravel Broadcasting
- Backend de WebSocket
- Perder message não é crítico (dá para recarregar do banco)

```php
// Laravel Broadcasting + Redis
class MessageSent implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->chatId);
    }
}
```

**4. Processar logs — Kafka**

**Por que:**
- High throughput (milhões de msg/s)
- Retention para análise
- Consumer groups para processar em paralelo
- Horizontal scaling via partitions
- Stream processing

```php
// Kafka para logs
Kafka::publishOn('application-logs')
    ->withMessage([
        'level' => 'error',
        'message' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ])
    ->send();
```

**5. Notifications online — Redis Pub/Sub**

**Por que:**
- Entrega em real-time
- Só quem está online recebe
- Baixa latência
- Simples
- Quem está offline pega do banco no login

```php
// Redis Pub/Sub para notifications
event(new NewNotification($notification));

// Frontend
Echo.private(`user.${userId}`)
    .notification((notification) => {
        showToast(notification.message);
    });
```

**Abordagem combinada (best practice):**

```php
// Envio de email: RabbitMQ
SendEmailJob::dispatch($user)->onQueue('emails');

// Logs e events: Kafka
event(new OrderCreated($order)); // → Kafka

// Notifications em real-time: Redis
broadcast(new NewMessage($message)); // → Redis Pub/Sub

// Notification offline: gravar no banco
$user->notifications()->create([...]);
```
</details>

### Exercício 2: Implemente Idempotency para brokers diferentes

Crie handlers idempotentes para RabbitMQ, Kafka e Redis para evitar duplicate processing.

<details>
<summary>Solução</summary>

```php
// 1. RabbitMQ com Idempotency Key
namespace App\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessPaymentJob implements ShouldQueue
{
    public $connection = 'rabbitmq';
    public $tries = 3;

    public function __construct(
        public int $orderId,
        public string $idempotencyKey
    ) {}

    public function handle()
    {
        $lockKey = "payment:{$this->orderId}:{$this->idempotencyKey}";

        // Lock atômico para evitar duplicate processing
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            // Já está processando ou já processou
            return;
        }

        try {
            // Checar se ainda não processou
            if (Cache::has("payment:processed:{$this->idempotencyKey}")) {
                return;
            }

            // Processar
            DB::transaction(function () {
                $order = Order::lockForUpdate()->find($this->orderId);

                if ($order->status !== 'pending') {
                    return; // Já processado
                }

                // Processar o pagamento
                $payment = PaymentGateway::charge($order->total);

                $order->update([
                    'status' => 'paid',
                    'payment_id' => $payment->id,
                ]);
            });

            // Marcar como processado
            Cache::put(
                "payment:processed:{$this->idempotencyKey}",
                true,
                3600
            );

        } finally {
            $lock->release();
        }
    }
}

// Uso
ProcessPaymentJob::dispatch(
    $order->id,
    Str::uuid() // Idempotency key único
);

// 2. Kafka com Offset Tracking
namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;

class ConsumeKafkaOrders extends Command
{
    public function handle()
    {
        $consumer = Kafka::createConsumer(['orders'])
            ->withConsumerGroupId('order-processor')
            ->withAutoCommit(false)
            ->withHandler(function ($message) {
                $offset = $message->getOffset();
                $partition = $message->getPartition();
                $data = $message->getBody();

                // Checar se o offset ainda não foi processado
                $processed = DB::table('kafka_offsets')
                    ->where('topic', 'orders')
                    ->where('partition', $partition)
                    ->where('offset', $offset)
                    ->exists();

                if ($processed) {
                    // Já processado
                    $message->getConsumer()->commit($message);
                    return;
                }

                DB::transaction(function () use ($data, $partition, $offset, $message) {
                    // Processar
                    Order::create($data);

                    // Guardar o offset
                    DB::table('kafka_offsets')->insert([
                        'topic' => 'orders',
                        'partition' => $partition,
                        'offset' => $offset,
                        'processed_at' => now(),
                    ]);

                    // Commit
                    $message->getConsumer()->commit($message);
                });
            })
            ->build();

        $consumer->consume();
    }
}

// 3. Redis Pub/Sub com Message Deduplication
namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

class IdempotentRedisPublisher
{
    public function publish(string $channel, array $data, ?string $messageId = null)
    {
        $messageId = $messageId ?? Str::uuid();

        // Checar se não publicou recentemente
        $cacheKey = "redis:published:{$channel}:{$messageId}";

        if (Cache::has($cacheKey)) {
            return false; // Duplicate
        }

        // Publish
        $payload = array_merge($data, [
            'message_id' => $messageId,
            'timestamp' => now()->toIso8601String(),
        ]);

        Redis::publish($channel, json_encode($payload));

        // Marcar como publicado (TTL de 5 minutos)
        Cache::put($cacheKey, true, 300);

        return true;
    }
}

class IdempotentRedisSubscriber
{
    private $processedMessages = [];

    public function subscribe(string $channel, callable $callback)
    {
        Redis::subscribe([$channel], function ($message) use ($callback) {
            $data = json_decode($message, true);
            $messageId = $data['message_id'] ?? null;

            if (!$messageId) {
                return; // Sem message_id
            }

            // Checar duplicata na memória (sessão atual)
            if (isset($this->processedMessages[$messageId])) {
                return;
            }

            // Checar no cache
            $cacheKey = "redis:processed:{$channel}:{$messageId}";

            if (Cache::has($cacheKey)) {
                return; // Já processado
            }

            // Processar
            $callback($data);

            // Marcar como processado
            $this->processedMessages[$messageId] = true;
            Cache::put($cacheKey, true, 300);

            // Limpar os antigos da memória
            if (count($this->processedMessages) > 1000) {
                $this->processedMessages = array_slice(
                    $this->processedMessages,
                    -500,
                    null,
                    true
                );
            }
        });
    }
}

// Uso
$publisher = new IdempotentRedisPublisher();

$messageId = Str::uuid();
$publisher->publish('notifications', [
    'user_id' => 123,
    'message' => 'Olá',
], $messageId);

// Se enviar de novo com o mesmo messageId, não publica
$publisher->publish('notifications', [
    'user_id' => 123,
    'message' => 'Olá',
], $messageId); // false

// Subscriber
$subscriber = new IdempotentRedisSubscriber();
$subscriber->subscribe('notifications', function ($data) {
    // Processar (uma vez, com garantia)
    Log::info('Notification', $data);
});
```

**Migration dos offsets do Kafka:**

```php
Schema::create('kafka_offsets', function (Blueprint $table) {
    $table->id();
    $table->string('topic');
    $table->integer('partition');
    $table->bigInteger('offset');
    $table->timestamp('processed_at');

    $table->unique(['topic', 'partition', 'offset']);
    $table->index(['topic', 'partition']);
});
```
</details>

### Exercício 3: Monte um sistema híbrido

Crie um sistema de processamento de pedidos que usa os três brokers, cada um para uma tarefa.

<details>
<summary>Solução</summary>

```php
// app/Services/OrderProcessingService.php
namespace App\Services;

use App\Models\Order;
use App\Jobs\SendOrderEmailJob;
use App\Events\OrderCreatedEvent;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Junges\Kafka\Facades\Kafka;

class OrderProcessingService
{
    public function createOrder(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            // 1. Criar o pedido
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $this->calculateTotal($data['items']),
                'status' => 'pending',
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            // 2. Event Sourcing via Kafka
            // Guardar o event para history e audit
            $this->publishToKafka('order.created', $order, $data);

            // 3. Background Jobs via RabbitMQ
            // Tarefas assíncronas com garantia de entrega
            $this->dispatchBackgroundJobs($order);

            // 4. Notification em real-time via Redis Pub/Sub
            // Notificar o usuário online
            $this->notifyUserRealtime($order);

            return $order;
        });
    }

    private function publishToKafka(string $eventType, Order $order, array $data): void
    {
        // Kafka: Event Sourcing + CDC
        Kafka::publishOn('order-events')
            ->withBodyKey('order_id', $order->id)
            ->withHeaders([
                'event_type' => $eventType,
                'version' => '1.0',
            ])
            ->withMessage([
                'event_type' => $eventType,
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'total' => $order->total,
                'items' => $data['items'],
                'timestamp' => now()->toIso8601String(),
            ])
            ->send();
    }

    private function dispatchBackgroundJobs(Order $order): void
    {
        // RabbitMQ: Background Jobs com retry
        SendOrderEmailJob::dispatch($order)
            ->onQueue('emails')
            ->onConnection('rabbitmq');

        UpdateInventoryJob::dispatch($order)
            ->onQueue('inventory')
            ->onConnection('rabbitmq');

        GenerateInvoiceJob::dispatch($order)
            ->onQueue('invoices')
            ->onConnection('rabbitmq')
            ->delay(now()->addMinutes(5));

        NotifyWarehouseJob::dispatch($order)
            ->onQueue('warehouse')
            ->onConnection('rabbitmq');
    }

    private function notifyUserRealtime(Order $order): void
    {
        // Redis Pub/Sub: notifications em real-time
        broadcast(new OrderCreatedEvent($order));

        // Também enviar para os admins
        Redis::publish('admin-notifications', json_encode([
            'type' => 'new_order',
            'order_id' => $order->id,
            'total' => $order->total,
            'user' => $order->user->name,
        ]));
    }

    public function payOrder(Order $order, array $paymentData): void
    {
        DB::transaction(function () use ($order, $paymentData) {
            // Processar o pagamento
            $payment = PaymentGateway::charge($order->total, $paymentData);

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_id' => $payment->id,
            ]);

            // Kafka: Event para o histórico
            Kafka::publishOn('order-events')
                ->withBodyKey('order_id', $order->id)
                ->withMessage([
                    'event_type' => 'order.paid',
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'timestamp' => now()->toIso8601String(),
                ])
                ->send();

            // RabbitMQ: Background jobs
            SendPaymentConfirmationJob::dispatch($order)
                ->onConnection('rabbitmq');

            StartFulfillmentJob::dispatch($order)
                ->onConnection('rabbitmq');

            // Redis: notification em real-time
            broadcast(new OrderPaidEvent($order));
        });
    }
}

// app/Console/Commands/ConsumeOrderEvents.php
// Kafka Consumer para analytics e sincronização
class ConsumeOrderEvents extends Command
{
    protected $signature = 'kafka:consume-orders';

    public function handle()
    {
        $consumer = Kafka::createConsumer(['order-events'])
            ->withConsumerGroupId('analytics-service')
            ->withAutoCommit(false)
            ->withHandler(function ($message) {
                $event = $message->getBody();

                // Processar para analytics
                match ($event['event_type']) {
                    'order.created' => $this->trackOrderCreated($event),
                    'order.paid' => $this->trackOrderPaid($event),
                    'order.shipped' => $this->trackOrderShipped($event),
                    default => null,
                };

                // Sincronizar com sistemas externos
                $this->syncToExternalService($event);

                $message->getConsumer()->commit($message);
            })
            ->build();

        $consumer->consume();
    }

    private function trackOrderCreated(array $event): void
    {
        // Enviar para analytics
        Analytics::track('order_created', [
            'order_id' => $event['order_id'],
            'total' => $event['total'],
        ]);
    }

    private function syncToExternalService(array $event): void
    {
        // Sincronizar com CRM, ERP, etc.
        Http::post('https://crm.example.com/orders/sync', $event);
    }
}

// config/queue.php
'connections' => [
    // RabbitMQ para background jobs
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'queue' => 'default',
        'connection' => [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
        ],
    ],
],

// config/broadcasting.php
'connections' => [
    // Redis para real-time
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
],

// config/kafka.php
return [
    // Kafka para event sourcing
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
];

// Arquitetura final:
// - RabbitMQ: email, geração de PDF, update de estoque (background jobs)
// - Kafka: event sourcing, CDC, analytics, audit log
// - Redis Pub/Sub: notifications em real-time, WebSockets, live updates
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
