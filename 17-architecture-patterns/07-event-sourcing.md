# 10.7 Event Sourcing

## Resumo

> **Event Sourcing** — guardar todas as mudanças como uma sequência de eventos.
>
> **Princípio:** Você não guarda o estado atual. Guarda os eventos. Estado = aplicar todos os eventos.
>
> **Importante:** EventStore guarda os eventos. Aggregate se reconstrói a partir deles. Snapshot para otimizar.

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
Event Sourcing — guardar todas as mudanças como uma sequência de eventos (events). O estado se reconstrói a partir dos eventos.

**Princípio:**
- Você não guarda o estado atual
- Guarda todos os eventos de mudança
- Estado = aplicar todos os eventos

---

## Como funciona

**Event:**

```php
// app/Domain/Order/Events/OrderCreated.php
class OrderCreated
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly array $items,
        public readonly DateTimeImmutable $occurredAt
    ) {}

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'items' => $this->items,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}

class OrderItemAdded
{
    public function __construct(
        public readonly string $orderId,
        public readonly int $productId,
        public readonly int $quantity,
        public readonly DateTimeImmutable $occurredAt
    ) {}
}

class OrderPaid
{
    public function __construct(
        public readonly string $orderId,
        public readonly float $amount,
        public readonly DateTimeImmutable $occurredAt
    ) {}
}
```

**Event Store:**

```php
// app/EventSourcing/EventStore.php
class EventStore
{
    public function append(string $aggregateId, object $event): void
    {
        DB::table('events')->insert([
            'aggregate_id' => $aggregateId,
            'event_type' => get_class($event),
            'event_data' => json_encode($event->toArray()),
            'occurred_at' => $event->occurredAt,
        ]);
    }

    public function getEventsForAggregate(string $aggregateId): array
    {
        $rows = DB::table('events')
            ->where('aggregate_id', $aggregateId)
            ->orderBy('occurred_at')
            ->get();

        return $rows->map(function ($row) {
            $eventClass = $row->event_type;
            $data = json_decode($row->event_data, true);

            return $eventClass::fromArray($data);
        })->toArray();
    }
}
```

**Aggregate:**

```php
// app/Domain/Order/Order.php
class Order
{
    private string $id;
    private string $userId;
    private array $items = [];
    private string $status = 'pending';
    private float $total = 0;

    // Eventos ainda não persistidos
    private array $uncommittedEvents = [];

    public static function create(string $id, string $userId, array $items): self
    {
        $order = new self();
        $order->recordThat(new OrderCreated(
            orderId: $id,
            userId: $userId,
            items: $items,
            occurredAt: new DateTimeImmutable()
        ));

        return $order;
    }

    public function addItem(int $productId, int $quantity): void
    {
        $this->recordThat(new OrderItemAdded(
            orderId: $this->id,
            productId: $productId,
            quantity: $quantity,
            occurredAt: new DateTimeImmutable()
        ));
    }

    public function markAsPaid(float $amount): void
    {
        $this->recordThat(new OrderPaid(
            orderId: $this->id,
            amount: $amount,
            occurredAt: new DateTimeImmutable()
        ));
    }

    // Reconstruir a partir dos eventos
    public static function reconstituteFrom(array $events): self
    {
        $order = new self();

        foreach ($events as $event) {
            $order->applyThat($event);
        }

        return $order;
    }

    private function recordThat(object $event): void
    {
        $this->applyThat($event);
        $this->uncommittedEvents[] = $event;
    }

    private function applyThat(object $event): void
    {
        match (get_class($event)) {
            OrderCreated::class => $this->applyOrderCreated($event),
            OrderItemAdded::class => $this->applyOrderItemAdded($event),
            OrderPaid::class => $this->applyOrderPaid($event),
        };
    }

    private function applyOrderCreated(OrderCreated $event): void
    {
        $this->id = $event->orderId;
        $this->userId = $event->userId;
        $this->items = $event->items;
    }

    private function applyOrderItemAdded(OrderItemAdded $event): void
    {
        $this->items[] = [
            'product_id' => $event->productId,
            'quantity' => $event->quantity,
        ];
    }

    private function applyOrderPaid(OrderPaid $event): void
    {
        $this->status = 'paid';
        $this->total = $event->amount;
    }

    public function getUncommittedEvents(): array
    {
        return $this->uncommittedEvents;
    }

    public function clearUncommittedEvents(): void
    {
        $this->uncommittedEvents = [];
    }
}
```

**Repository:**

```php
class OrderRepository
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function save(Order $order): void
    {
        foreach ($order->getUncommittedEvents() as $event) {
            $this->eventStore->append($order->getId(), $event);
        }

        $order->clearUncommittedEvents();
    }

    public function find(string $orderId): ?Order
    {
        $events = $this->eventStore->getEventsForAggregate($orderId);

        if (empty($events)) {
            return null;
        }

        return Order::reconstituteFrom($events);
    }
}
```

---

## Quando usar

**Event Sourcing para:**
- Auditoria de todas as mudanças
- Reconstruir o estado em qualquer momento
- Regra de negócio complexa
- Temporal queries ("como estava ontem?")

**NÃO use para:**
- CRUD simples
- Protótipo rápido
- Projeto pequeno

---

## Exemplo prático

**Migration para events:**

```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->uuid('aggregate_id')->index();
    $table->string('event_type');
    $table->json('event_data');
    $table->timestamp('occurred_at');
    $table->timestamps();
});
```

**Projection (Read Model):**

```php
// app/Projections/OrderProjection.php
class OrderProjection
{
    public function __construct(
        private EventStore $eventStore
    ) {}

    public function projectOrder(string $orderId): array
    {
        $events = $this->eventStore->getEventsForAggregate($orderId);

        $projection = [
            'id' => $orderId,
            'status' => 'pending',
            'items' => [],
            'total' => 0,
        ];

        foreach ($events as $event) {
            match (get_class($event)) {
                OrderCreated::class => $projection['items'] = $event->items,
                OrderItemAdded::class => $projection['items'][] = [
                    'product_id' => $event->productId,
                    'quantity' => $event->quantity,
                ],
                OrderPaid::class => [
                    $projection['status'] = 'paid',
                    $projection['total'] = $event->amount,
                ],
                default => null,
            };
        }

        return $projection;
    }
}
```

**Snapshot (otimização):**

```php
// Guardar snapshot a cada N eventos
class SnapshotStore
{
    public function saveSnapshot(string $aggregateId, object $aggregate, int $version): void
    {
        DB::table('snapshots')->updateOrInsert(
            ['aggregate_id' => $aggregateId],
            [
                'aggregate_data' => serialize($aggregate),
                'version' => $version,
                'created_at' => now(),
            ]
        );
    }

    public function getSnapshot(string $aggregateId): ?array
    {
        return DB::table('snapshots')
            ->where('aggregate_id', $aggregateId)
            ->first();
    }
}

// Reconstruir a partir do snapshot + eventos depois dele
public function find(string $orderId): ?Order
{
    $snapshot = $this->snapshotStore->getSnapshot($orderId);

    if ($snapshot) {
        $order = unserialize($snapshot->aggregate_data);
        $events = $this->eventStore->getEventsAfterVersion(
            $orderId,
            $snapshot->version
        );
    } else {
        $order = new Order();
        $events = $this->eventStore->getEventsForAggregate($orderId);
    }

    return Order::reconstituteFrom($events, $order);
}
```

---

## Na entrevista

> "Event Sourcing guarda todas as mudanças como eventos. O Aggregate registra o evento (`recordThat`) e aplica (`applyThat`). O EventStore persiste. O estado se reconstrói a partir dos eventos. Projection monta o Read Model. Snapshot otimiza — você não relê tudo. Prós: auditoria completa, temporal queries, recuperação. Contras: complexidade, eventual consistency. Costuma ir junto com CQRS. Serve para regra de negócio complexa e auditoria."

---

## Exercícios práticos

### Exercício 1: Implemente um EventStore simples

**Enunciado:** Crie um EventStore com métodos para guardar e buscar eventos.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::create('domain_events', function (Blueprint $table) {
    $table->id();
    $table->uuid('aggregate_id')->index();
    $table->string('aggregate_type');
    $table->string('event_type');
    $table->json('event_data');
    $table->integer('version');
    $table->timestamp('occurred_at');
    $table->timestamps();

    $table->unique(['aggregate_id', 'version']);
});

// app/EventSourcing/EventStore.php
namespace App\EventSourcing;

use Illuminate\Support\Facades\DB;

class EventStore
{
    public function append(
        string $aggregateId,
        string $aggregateType,
        object $event
    ): void {
        $version = $this->getNextVersion($aggregateId);

        DB::table('domain_events')->insert([
            'aggregate_id' => $aggregateId,
            'aggregate_type' => $aggregateType,
            'event_type' => get_class($event),
            'event_data' => json_encode($event->toArray()),
            'version' => $version,
            'occurred_at' => $event->occurredAt ?? now(),
            'created_at' => now(),
        ]);
    }

    public function getEventsForAggregate(string $aggregateId): array
    {
        $rows = DB::table('domain_events')
            ->where('aggregate_id', $aggregateId)
            ->orderBy('version')
            ->get();

        return $rows->map(function ($row) {
            $eventClass = $row->event_type;
            $data = json_decode($row->event_data, true);

            return $eventClass::fromArray($data);
        })->toArray();
    }

    public function getEventsAfterVersion(
        string $aggregateId,
        int $afterVersion
    ): array {
        $rows = DB::table('domain_events')
            ->where('aggregate_id', $aggregateId)
            ->where('version', '>', $afterVersion)
            ->orderBy('version')
            ->get();

        return $rows->map(function ($row) {
            $eventClass = $row->event_type;
            $data = json_decode($row->event_data, true);

            return $eventClass::fromArray($data);
        })->toArray();
    }

    private function getNextVersion(string $aggregateId): int
    {
        $latest = DB::table('domain_events')
            ->where('aggregate_id', $aggregateId)
            ->max('version');

        return ($latest ?? 0) + 1;
    }

    public function getAllEvents(int $limit = 100, int $offset = 0): array
    {
        $rows = DB::table('domain_events')
            ->orderBy('id')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'aggregate_id' => $row->aggregate_id,
                'event_type' => $row->event_type,
                'occurred_at' => $row->occurred_at,
            ];
        })->toArray();
    }
}
```
</details>

### Exercício 2: Crie uma Projection de estatísticas

**Enunciado:** Implemente uma Projection que calcula estatísticas gerais dos pedidos.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::create('order_statistics', function (Blueprint $table) {
    $table->id();
    $table->integer('total_orders')->default(0);
    $table->decimal('total_revenue', 12, 2)->default(0);
    $table->integer('pending_orders')->default(0);
    $table->integer('paid_orders')->default(0);
    $table->integer('cancelled_orders')->default(0);
    $table->timestamp('updated_at');
});

// app/Projections/OrderStatisticsProjection.php
namespace App\Projections;

use App\Domain\Order\Events\OrderCreated;
use App\Domain\Order\Events\OrderPaid;
use App\Domain\Order\Events\OrderCancelled;
use Illuminate\Support\Facades\DB;

class OrderStatisticsProjection
{
    public function projectOrderCreated(OrderCreated $event): void
    {
        DB::table('order_statistics')
            ->updateOrInsert(
                ['id' => 1],
                [
                    'total_orders' => DB::raw('total_orders + 1'),
                    'pending_orders' => DB::raw('pending_orders + 1'),
                    'updated_at' => now(),
                ]
            );
    }

    public function projectOrderPaid(OrderPaid $event): void
    {
        DB::table('order_statistics')
            ->where('id', 1)
            ->update([
                'total_revenue' => DB::raw("total_revenue + {$event->amount}"),
                'pending_orders' => DB::raw('pending_orders - 1'),
                'paid_orders' => DB::raw('paid_orders + 1'),
                'updated_at' => now(),
            ]);
    }

    public function projectOrderCancelled(OrderCancelled $event): void
    {
        DB::table('order_statistics')
            ->where('id', 1)
            ->update([
                'pending_orders' => DB::raw('pending_orders - 1'),
                'cancelled_orders' => DB::raw('cancelled_orders + 1'),
                'updated_at' => now(),
            ]);
    }

    public function rebuild(): void
    {
        // Limpar
        DB::table('order_statistics')->truncate();

        // Reconstruir a partir de todos os eventos
        $events = DB::table('domain_events')
            ->where('aggregate_type', 'Order')
            ->orderBy('id')
            ->get();

        foreach ($events as $eventRow) {
            $eventClass = $eventRow->event_type;
            $event = $eventClass::fromArray(
                json_decode($eventRow->event_data, true)
            );

            match (get_class($event)) {
                OrderCreated::class => $this->projectOrderCreated($event),
                OrderPaid::class => $this->projectOrderPaid($event),
                OrderCancelled::class => $this->projectOrderCancelled($event),
                default => null,
            };
        }
    }

    public function getStatistics(): array
    {
        $stats = DB::table('order_statistics')->first();

        return [
            'total_orders' => $stats->total_orders ?? 0,
            'total_revenue' => $stats->total_revenue ?? 0,
            'pending_orders' => $stats->pending_orders ?? 0,
            'paid_orders' => $stats->paid_orders ?? 0,
            'cancelled_orders' => $stats->cancelled_orders ?? 0,
        ];
    }
}

// Listener para atualizar automaticamente
class UpdateOrderStatistics
{
    public function __construct(
        private OrderStatisticsProjection $projection
    ) {}

    public function handle(object $event): void
    {
        match (get_class($event)) {
            OrderCreated::class => $this->projection->projectOrderCreated($event),
            OrderPaid::class => $this->projection->projectOrderPaid($event),
            OrderCancelled::class => $this->projection->projectOrderCancelled($event),
            default => null,
        };
    }
}
```
</details>

### Exercício 3: Implemente o mecanismo de Snapshot

**Enunciado:** Crie um sistema de Snapshot para otimizar a reconstrução de aggregates grandes.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::create('aggregate_snapshots', function (Blueprint $table) {
    $table->uuid('aggregate_id')->primary();
    $table->string('aggregate_type');
    $table->text('aggregate_data');
    $table->integer('version');
    $table->timestamps();
});

// app/EventSourcing/SnapshotStore.php
namespace App\EventSourcing;

use Illuminate\Support\Facades\DB;

class SnapshotStore
{
    private const SNAPSHOT_FREQUENCY = 10; // A cada 10 eventos

    public function saveSnapshot(
        string $aggregateId,
        string $aggregateType,
        object $aggregate,
        int $version
    ): void {
        DB::table('aggregate_snapshots')->updateOrInsert(
            ['aggregate_id' => $aggregateId],
            [
                'aggregate_type' => $aggregateType,
                'aggregate_data' => serialize($aggregate),
                'version' => $version,
                'updated_at' => now(),
            ]
        );
    }

    public function getSnapshot(string $aggregateId): ?object
    {
        $row = DB::table('aggregate_snapshots')
            ->where('aggregate_id', $aggregateId)
            ->first();

        if (!$row) {
            return null;
        }

        return (object) [
            'aggregate' => unserialize($row->aggregate_data),
            'version' => $row->version,
        ];
    }

    public function shouldCreateSnapshot(int $eventCount): bool
    {
        return $eventCount % self::SNAPSHOT_FREQUENCY === 0;
    }
}

// app/Repositories/EventSourcedOrderRepository.php
namespace App\Repositories;

use App\Domain\Order\Order;
use App\EventSourcing\EventStore;
use App\EventSourcing\SnapshotStore;

class EventSourcedOrderRepository
{
    public function __construct(
        private EventStore $eventStore,
        private SnapshotStore $snapshotStore
    ) {}

    public function save(Order $order): void
    {
        $events = $order->getUncommittedEvents();

        foreach ($events as $event) {
            $this->eventStore->append(
                $order->getId(),
                'Order',
                $event
            );
        }

        $order->clearUncommittedEvents();

        // Criar snapshot se precisar
        $eventCount = count(
            $this->eventStore->getEventsForAggregate($order->getId())
        );

        if ($this->snapshotStore->shouldCreateSnapshot($eventCount)) {
            $this->snapshotStore->saveSnapshot(
                $order->getId(),
                'Order',
                $order,
                $eventCount
            );
        }
    }

    public function find(string $orderId): ?Order
    {
        // Tentar carregar o snapshot
        $snapshot = $this->snapshotStore->getSnapshot($orderId);

        if ($snapshot) {
            // Reconstruir a partir do snapshot
            $order = clone $snapshot->aggregate;

            // Carregar só os eventos depois do snapshot
            $events = $this->eventStore->getEventsAfterVersion(
                $orderId,
                $snapshot->version
            );
        } else {
            // Criar um novo e carregar todos os eventos
            $order = new Order();
            $events = $this->eventStore->getEventsForAggregate($orderId);
        }

        if (empty($events) && !$snapshot) {
            return null;
        }

        // Aplicar os eventos
        foreach ($events as $event) {
            $order->applyThat($event);
        }

        return $order;
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
