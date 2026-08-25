# 17.2 Apache Kafka

## Resumo

> **Apache Kafka** — distributed streaming platform para processar milhões de eventos por segundo, com persistência em disco.
>
> **Arquitetura:** Topics quebrados em Partitions. Consumer Groups leem em paralelo. Offset controla a posição da leitura.
>
> **Use cases:** Event streaming, event sourcing, CDC, analytics em real-time, agregação de logs.

---

## Conteúdo

- [O que é](#o-que-é)
- [Arquitetura](#arquitetura)
- [Topics e Partitions](#topics-e-partitions)
- [Producer](#producer)
- [Consumer](#consumer)
- [Laravel + Kafka](#laravel--kafka)
- [Offset Management](#offset-management)
- [Retention (armazenamento)](#retention-armazenamento)
- [Replication](#replication)
- [Use Cases](#use-cases)
- [Kafka vs RabbitMQ](#kafka-vs-rabbitmq)
- [Monitoring](#monitoring)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Kafka:**
Distributed streaming platform para processar streams de eventos em tempo real. Alta vazão (millions events/sec).

**Para quê:**
- Event streaming (pipelines de dados em real-time)
- Alta vazão
- Guardar eventos (event log)
- Comunicação entre microsserviços

**Diferença do RabbitMQ:**

```
RabbitMQ:
- Message broker (tarefas somem depois do processamento)
- Low latency
- Complex routing

Kafka:
- Event log (eventos ficam guardados)
- High throughput
- Simple pub/sub
```

---

## Arquitetura

**Componentes:**

```
Producer → Topic (partitions) → Consumer Group
                ↓
            (fica no disco)
```

**Topic:**
- Categoria de eventos (como uma tabela)
- Dividido em partitions para scaling

**Partition:**
- Sequência ordenada de mensagens
- Cada mensagem tem um offset (posição)

**Consumer Group:**
- Grupo de consumers para processar em paralelo
- Cada partition é lida por um consumer só no group

---

## Topics e Partitions

**Exemplo:**

```
Topic: user-events

Partition 0: [msg1, msg2, msg5, msg7]  ← Consumer 1
Partition 1: [msg3, msg6, msg8]        ← Consumer 2
Partition 2: [msg4, msg9]              ← Consumer 3
```

**Quantidade de partitions:**
- Mais partitions = mais paralelismo
- Recomendado: 3-6 partitions por topic
- Máximo de consumers no group = número de partitions

---

## Producer

**Exemplo em PHP:**

```bash
composer require enqueue/rdkafka
```

```php
use Enqueue\RdKafka\RdKafkaConnectionFactory;

$factory = new RdKafkaConnectionFactory([
    'global' => [
        'bootstrap.servers' => 'localhost:9092',
    ],
]);

$context = $factory->createContext();

// Criar o producer
$topic = $context->createTopic('user-events');
$producer = $context->createProducer();

// Enviar a mensagem
$message = $context->createMessage(json_encode([
    'event' => 'user_created',
    'user_id' => 123,
    'timestamp' => time(),
]));

$producer->send($topic, $message);
```

**Com chave (para partitioning):**

```php
$message = $context->createMessage($body);

// Mensagens com a mesma chave caem na mesma partition
$message->setKey((string)$userId);  // Partition por user_id

$producer->send($topic, $message);
```

---

## Consumer

**Single Consumer:**

```php
$context = $factory->createContext();
$consumer = $context->createConsumer(
    $context->createQueue('user-events')
);

while (true) {
    $message = $consumer->receive(1000);  // Timeout de 1 segundo

    if ($message) {
        $data = json_decode($message->getBody(), true);

        echo "Processando: {$data['event']}\n";

        // Processar...

        // Acknowledge
        $consumer->acknowledge($message);
    }
}
```

**Consumer Group:**

```php
$context = $factory->createContext([
    'global' => [
        'group.id' => 'email-service',  // Consumer group ID
        'enable.auto.commit' => 'false',
    ],
]);

$consumer = $context->createConsumer(
    $context->createQueue('user-events')
);

// O Kafka distribui as partitions entre os consumers do group
```

---

## Laravel + Kafka

**Package:**

```bash
composer require junges/laravel-kafka
```

**Configuração:**

```php
// config/kafka.php
return [
    'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
];
```

**Producer:**

```php
use Junges\Kafka\Facades\Kafka;

// Envio simples
Kafka::publishOn('user-events')
    ->withMessage([
        'event' => 'user_created',
        'user_id' => 123,
    ])
    ->send();

// Com chave e headers
Kafka::publishOn('user-events')
    ->withHeaders(['version' => '1.0'])
    ->withBodyKey('user_id', 123)
    ->withMessage([
        'event' => 'user_created',
        'email' => 'user@example.com',
    ])
    ->send();
```

**Consumer:**

```php
use Junges\Kafka\Facades\Kafka;

$consumer = Kafka::createConsumer(['user-events'])
    ->withConsumerGroupId('email-service')
    ->withHandler(function ($message) {
        $data = $message->getBody();

        Log::info('Kafka message', $data);

        // Processar...
    })
    ->build();

$consumer->consume();
```

**Console Command:**

```php
// app/Console/Commands/ConsumeKafka.php
class ConsumeKafka extends Command
{
    protected $signature = 'kafka:consume';

    public function handle()
    {
        $consumer = Kafka::createConsumer(['user-events'])
            ->withConsumerGroupId('laravel-consumer')
            ->withHandler(function ($message) {
                $this->info('Processando: ' . json_encode($message->getBody()));
            })
            ->build();

        $consumer->consume();
    }
}
```

```bash
php artisan kafka:consume
```

---

## Offset Management

**O que é offset:**
- Posição da mensagem na partition
- O consumer grava o offset para retomar (resume)

**Auto commit:**

```php
'enable.auto.commit' => 'true',  // Commit automático do offset
'auto.commit.interval.ms' => 5000,  // A cada 5 segundos
```

**Manual commit:**

```php
'enable.auto.commit' => 'false',

$consumer = Kafka::createConsumer(['user-events'])
    ->withHandler(function ($message) use ($consumer) {
        try {
            processMessage($message);

            // Sucesso: commit do offset
            $consumer->commit();
        } catch (Exception $e) {
            // Erro: sem commit (reprocessa na próxima)
            Log::error('Failed to process', ['error' => $e]);
        }
    })
    ->build();
```

**Reset offset:**

```bash
# Ler do começo
kafka-consumer-groups --bootstrap-server localhost:9092 \
    --group my-group \
    --reset-offsets --to-earliest \
    --topic user-events \
    --execute

# Ler a partir de um offset específico
kafka-consumer-groups --bootstrap-server localhost:9092 \
    --group my-group \
    --reset-offsets --to-offset 100 \
    --topic user-events:0 \
    --execute
```

---

## Retention (armazenamento)

**Por padrão:** 7 dias

```bash
# Configurar retention
kafka-configs --bootstrap-server localhost:9092 \
    --entity-type topics \
    --entity-name user-events \
    --alter \
    --add-config retention.ms=604800000  # 7 dias
```

**Infinite retention:**

```bash
# Guardar para sempre (event sourcing)
--add-config retention.ms=-1
```

**Compaction:**

```bash
# Guardar só o último valor de cada chave
--add-config cleanup.policy=compact
```

---

## Replication

**Para quê:** Tolerância a falhas

```
Broker 1 (leader for partition 0)    ← Producer writes here
Broker 2 (replica for partition 0)
Broker 3 (replica for partition 0)

Se o Broker 1 cair → Broker 2 vira leader
```

**Criar topic com replicação:**

```bash
kafka-topics --create \
    --bootstrap-server localhost:9092 \
    --topic user-events \
    --partitions 3 \
    --replication-factor 3  # 3 cópias
```

**min.insync.replicas:**

```bash
# No mínimo 2 réplicas precisam confirmar o write
--add-config min.insync.replicas=2
```

---

## Use Cases

### 1. Event Sourcing

```php
// Persistir todos os eventos do usuário
Kafka::publishOn('user-events')
    ->withBodyKey('user_id', $userId)
    ->withMessage([
        'event' => 'user_created',
        'data' => $userData,
        'timestamp' => time(),
    ])
    ->send();

Kafka::publishOn('user-events')
    ->withBodyKey('user_id', $userId)
    ->withMessage([
        'event' => 'email_verified',
        'timestamp' => time(),
    ])
    ->send();

// Consumer reconstrói o estado a partir dos eventos
```

---

### 2. CDC (Change Data Capture)

```php
// Toda mudança no banco → Kafka
class UserObserver
{
    public function created(User $user)
    {
        Kafka::publishOn('user-changes')
            ->withMessage([
                'operation' => 'INSERT',
                'table' => 'users',
                'data' => $user->toArray(),
            ])
            ->send();
    }

    public function updated(User $user)
    {
        Kafka::publishOn('user-changes')
            ->withMessage([
                'operation' => 'UPDATE',
                'table' => 'users',
                'before' => $user->getOriginal(),
                'after' => $user->toArray(),
            ])
            ->send();
    }
}

// Outros serviços sincronizam o banco deles
```

---

### 3. Metrics & Logging

```php
// Métricas em real-time
Kafka::publishOn('app-metrics')
    ->withMessage([
        'metric' => 'api.response_time',
        'value' => 150,  // ms
        'timestamp' => microtime(true),
    ])
    ->send();

// Consumer agrega e manda para InfluxDB/Prometheus
```

---

## Kafka vs RabbitMQ

**Escolha Kafka quando:**
```
✓ Alta vazão (millions/sec)
✓ Event log / Event sourcing
✓ Replay de eventos
✓ Guardar eventos por muito tempo
✓ Stream processing (Kafka Streams)
```

**Escolha RabbitMQ quando:**
```
✓ Low latency
✓ Complex routing (topic exchange, headers)
✓ Priority queues
✓ Tarefas somem depois do processamento
✓ Mais simples de configurar e manter
```

**Combinando:**
```
RabbitMQ: tarefas (emails, notifications)
Kafka: eventos (user_created, order_placed)
```

---

## Monitoring

**CLI:**

```bash
# Listar topics
kafka-topics --list --bootstrap-server localhost:9092

# Info do topic
kafka-topics --describe --topic user-events --bootstrap-server localhost:9092

# Consumer groups
kafka-consumer-groups --list --bootstrap-server localhost:9092

# Lag (atraso do consumer)
kafka-consumer-groups --describe --group my-group --bootstrap-server localhost:9092
```

**Kafka Manager / Kafka UI:**
- GUI para gerenciar
- http://localhost:9000

---

## Na entrevista

> "Kafka é uma distributed streaming platform para event log. Topics quebrados em partitions para paralelismo. Producer manda com chave (para partitioning). Consumer group: cada partition é lida por um consumer só. Offset é a posição na partition, commit manual ou auto. Retention: 7 dias por padrão, pode ser infinite (event sourcing). Replication para tolerância a falhas (min.insync.replicas). No Laravel: junges/laravel-kafka. Use cases: event sourcing, CDC, métricas em real-time. Kafka vs RabbitMQ: Kafka para high throughput e event log, RabbitMQ para tarefas e complex routing."

---

## Exercícios práticos

### Exercício 1: Event Sourcing com Kafka

Implemente event sourcing de pedidos com Kafka. Todo evento (created, paid, shipped) fica guardado e dá para reconstruir o estado.

<details>
<summary>Solução</summary>

```php
// app/Services/OrderEventSourcing.php
namespace App\Services;

use Junges\Kafka\Facades\Kafka;
use App\Models\Order;

class OrderEventSourcing
{
    private const TOPIC = 'order-events';

    public function publishEvent(string $eventType, Order $order, array $data = []): void
    {
        $event = [
            'event_type' => $eventType,
            'order_id' => $order->id,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        Kafka::publishOn(self::TOPIC)
            ->withBodyKey('order_id', $order->id)  // Todos os eventos do pedido na mesma partition
            ->withHeaders([
                'event_type' => $eventType,
                'version' => '1.0',
            ])
            ->withMessage($event)
            ->send();
    }

    public function createOrder(array $data): Order
    {
        $order = Order::create($data);

        $this->publishEvent('order.created', $order, [
            'items' => $data['items'],
            'total' => $data['total'],
        ]);

        return $order;
    }

    public function payOrder(Order $order, string $paymentMethod): void
    {
        $order->update(['status' => 'paid', 'paid_at' => now()]);

        $this->publishEvent('order.paid', $order, [
            'payment_method' => $paymentMethod,
        ]);
    }

    public function shipOrder(Order $order, string $trackingNumber): void
    {
        $order->update(['status' => 'shipped', 'shipped_at' => now()]);

        $this->publishEvent('order.shipped', $order, [
            'tracking_number' => $trackingNumber,
        ]);
    }

    public function rebuildOrderState(int $orderId): array
    {
        // Lê todos os eventos do pedido no Kafka
        // Na prática, use a Consumer API para ler do começo

        $state = [
            'order_id' => $orderId,
            'status' => 'unknown',
            'events' => [],
        ];

        // Pseudocódigo para reconstruir o estado
        // foreach ($events as $event) {
        //     switch ($event['event_type']) {
        //         case 'order.created':
        //             $state['status'] = 'created';
        //             $state['items'] = $event['data']['items'];
        //             break;
        //         case 'order.paid':
        //             $state['status'] = 'paid';
        //             $state['payment_method'] = $event['data']['payment_method'];
        //             break;
        //         case 'order.shipped':
        //             $state['status'] = 'shipped';
        //             $state['tracking_number'] = $event['data']['tracking_number'];
        //             break;
        //     }
        //     $state['events'][] = $event;
        // }

        return $state;
    }
}

// app/Console/Commands/ConsumeOrderEvents.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Log;

class ConsumeOrderEvents extends Command
{
    protected $signature = 'kafka:consume-orders';

    public function handle()
    {
        $consumer = Kafka::createConsumer(['order-events'])
            ->withConsumerGroupId('order-processor')
            ->withAutoCommit()
            ->withHandler(function ($message) {
                $data = $message->getBody();

                Log::info('Order event received', [
                    'event_type' => $data['event_type'],
                    'order_id' => $data['order_id'],
                ]);

                // Processar eventos (atualizar read models, analytics etc.)
                match ($data['event_type']) {
                    'order.created' => $this->handleOrderCreated($data),
                    'order.paid' => $this->handleOrderPaid($data),
                    'order.shipped' => $this->handleOrderShipped($data),
                    default => null,
                };
            })
            ->build();

        $consumer->consume();
    }

    private function handleOrderCreated(array $data): void
    {
        // Enviar welcome email
        // Atualizar analytics
        $this->info("Order created: {$data['order_id']}");
    }

    private function handleOrderPaid(array $data): void
    {
        // Começar a montar o pedido
        // Atualizar estatística de vendas
        $this->info("Order paid: {$data['order_id']}");
    }

    private function handleOrderShipped(array $data): void
    {
        // Enviar tracking info
        // Atualizar inventory
        $this->info("Order shipped: {$data['order_id']}");
    }
}

// Uso
$service = new OrderEventSourcing();

$order = $service->createOrder([
    'user_id' => 1,
    'items' => [['product_id' => 1, 'quantity' => 2]],
    'total' => 100.00,
]);

$service->payOrder($order, 'credit_card');
$service->shipOrder($order, 'TRACK123');
```
</details>

### Exercício 2: Consumer Group para processamento paralelo

Crie vários consumers no mesmo group para processar logs em paralelo.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/KafkaLogConsumer.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class KafkaLogConsumer extends Command
{
    protected $signature = 'kafka:consume-logs {consumer-id}';
    protected $description = 'Consome logs do Kafka';

    public function handle()
    {
        $consumerId = $this->argument('consumer-id');
        $groupId = 'log-processors';

        $this->info("Subindo consumer {$consumerId} no group {$groupId}");

        $consumer = Kafka::createConsumer(['application-logs'])
            ->withConsumerGroupId($groupId)
            ->withAutoCommit(false)  // Manual commit
            ->withHandler(function ($message) use ($consumerId) {
                $logData = $message->getBody();

                try {
                    // Processar o log
                    DB::table('application_logs')->insert([
                        'level' => $logData['level'],
                        'message' => $logData['message'],
                        'context' => json_encode($logData['context'] ?? []),
                        'processed_by' => $consumerId,
                        'created_at' => now(),
                    ]);

                    $this->info("[{$consumerId}] Log processado: {$logData['message']}");

                    // Manual commit depois do processamento ok
                    $message->getConsumer()->commit($message);

                } catch (\Exception $e) {
                    $this->error("[{$consumerId}] Falhou: {$e->getMessage()}");
                    // Sem commit — a mensagem volta a ser processada
                }
            })
            ->build();

        $consumer->consume();
    }
}

// Subir vários consumers
// Terminal 1: php artisan kafka:consume-logs consumer-1
// Terminal 2: php artisan kafka:consume-logs consumer-2
// Terminal 3: php artisan kafka:consume-logs consumer-3

// O Kafka distribui as partitions entre os consumers

// Producer de logs
namespace App\Services;

use Junges\Kafka\Facades\Kafka;

class KafkaLogger
{
    public function log(string $level, string $message, array $context = []): void
    {
        Kafka::publishOn('application-logs')
            ->withMessage([
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'timestamp' => now()->toIso8601String(),
                'hostname' => gethostname(),
            ])
            ->send();
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
}

// Uso
$logger = new KafkaLogger();
$logger->info('Usuário fez login', ['user_id' => 123]);
$logger->error('Falha na conexão com o banco', ['host' => 'db.example.com']);
```
</details>

### Exercício 3: CDC (Change Data Capture) com Kafka

Implemente CDC: toda mudança no banco vai para o Kafka e outros serviços sincronizam.

<details>
<summary>Solução</summary>

```php
// app/Observers/UserObserver.php
namespace App\Observers;

use App\Models\User;
use Junges\Kafka\Facades\Kafka;

class UserObserver
{
    private const TOPIC = 'user-changes';

    public function created(User $user): void
    {
        $this->publishChange('INSERT', $user, null);
    }

    public function updated(User $user): void
    {
        $this->publishChange('UPDATE', $user, $user->getOriginal());
    }

    public function deleted(User $user): void
    {
        $this->publishChange('DELETE', $user, null);
    }

    private function publishChange(string $operation, User $user, ?array $before): void
    {
        $event = [
            'operation' => $operation,
            'table' => 'users',
            'timestamp' => now()->toIso8601String(),
            'before' => $before,
            'after' => $user->toArray(),
        ];

        Kafka::publishOn(self::TOPIC)
            ->withBodyKey('user_id', $user->id)
            ->withHeaders([
                'operation' => $operation,
                'table' => 'users',
            ])
            ->withMessage($event)
            ->send();
    }
}

// Registrar o Observer no AppServiceProvider
use App\Models\User;
use App\Observers\UserObserver;

public function boot(): void
{
    User::observe(UserObserver::class);
}

// Consumer para sincronizar outro serviço
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Http;

class SyncUserChanges extends Command
{
    protected $signature = 'kafka:sync-users';

    public function handle()
    {
        $consumer = Kafka::createConsumer(['user-changes'])
            ->withConsumerGroupId('analytics-service')
            ->withAutoCommit(false)
            ->withHandler(function ($message) {
                $change = $message->getBody();

                try {
                    // Sincronizar com o serviço externo
                    match ($change['operation']) {
                        'INSERT' => $this->syncUserCreated($change['after']),
                        'UPDATE' => $this->syncUserUpdated($change['before'], $change['after']),
                        'DELETE' => $this->syncUserDeleted($change['after']),
                    };

                    $this->info("Sincronizado {$change['operation']} do user {$change['after']['id']}");

                    $message->getConsumer()->commit($message);

                } catch (\Exception $e) {
                    $this->error("Sync falhou: {$e->getMessage()}");
                }
            })
            ->build();

        $consumer->consume();
    }

    private function syncUserCreated(array $user): void
    {
        // Enviar para o serviço de analytics
        Http::post('https://analytics.example.com/users', [
            'id' => $user['id'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
        ]);
    }

    private function syncUserUpdated(array $before, array $after): void
    {
        // Atualizar no serviço de analytics
        Http::put("https://analytics.example.com/users/{$after['id']}", [
            'email' => $after['email'],
            'name' => $after['name'],
        ]);
    }

    private function syncUserDeleted(array $user): void
    {
        // Remover do serviço de analytics
        Http::delete("https://analytics.example.com/users/{$user['id']}");
    }
}

// Criar o topic com as configs certas para CDC
// bash
// kafka-topics --create \
//   --bootstrap-server localhost:9092 \
//   --topic user-changes \
//   --partitions 3 \
//   --replication-factor 3 \
//   --config retention.ms=-1 \
//   --config cleanup.policy=compact
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
