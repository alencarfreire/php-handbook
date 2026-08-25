# 18.5 Saga Pattern

> **TL;DR**
> Saga Pattern — transação distribuída em microsserviços sem ACID. Sequência de transações locais com compensation se der erro. Tipos: Choreography (events, descentralizado) e Orchestration (coordinator no centro, HTTP calls). Idempotency contra duplicata. State Machine nas sagas complexas. Monitoring: loga cada passo, dashboard das sagas que não fecharam.

## Conteúdo
- [O que é](#o-que-é)
- [Tipos de Saga](#tipos-de-saga)
- [Choreography Saga (Laravel)](#choreography-saga-laravel)
- [Orchestration Saga (Laravel)](#orchestration-saga-laravel)
- [Choreography vs Orchestration](#choreography-vs-orchestration)
- [Saga State Machine](#saga-state-machine)
- [Idempotency](#idempotency)
- [Monitoramento e debug](#monitoramento-e-debug)
- [Boas práticas](#boas-práticas)
- [Quando usar](#quando-usar)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Saga Pattern:**
Padrão para gerenciar transações distribuídas em microsserviços.

**Problema:**
Em microsserviços você não usa transação ACID entre bancos de serviços diferentes.

```
Monolito:
BEGIN TRANSACTION;
  INSERT INTO orders (...);
  UPDATE inventory SET stock = stock - 1;
  INSERT INTO payments (...);
COMMIT;  -- Tudo ou nada!

Microsserviços:
Order Service → Order DB
Inventory Service → Inventory DB
Payment Service → Payment DB
❌ Sem transação entre bancos!
```

**Solução: Saga**
Sequência de transações locais com lógica de compensation.

---

## Tipos de Saga

### 1. Choreography (coreografia)

**Os serviços conversam por events (sem coordinator).**

```
1. Order Service: cria o pedido → emite OrderCreated
2. Inventory Service: escuta OrderCreated → reserva o estoque → emite InventoryReserved
3. Payment Service: escuta InventoryReserved → cobra o pagamento → emite PaymentProcessed
4. Order Service: escuta PaymentProcessed → confirma o pedido
```

**Se der erro:**

```
3. Payment Service: erro → emite PaymentFailed
4. Inventory Service: escuta PaymentFailed → cancela a reserva
5. Order Service: escuta PaymentFailed → cancela o pedido
```

---

### 2. Orchestration (orquestração)

**Um coordinator no centro manda na saga.**

```
Saga Orchestrator:
1. Chama Order Service → cria o pedido
2. Chama Inventory Service → reserva o estoque
3. Chama Payment Service → cobra o pagamento
4. Chama Order Service → confirma o pedido

Se der erro:
3. Payment Service: erro
4. Orchestrator: compensation
   - Chama Inventory Service → cancela a reserva
   - Chama Order Service → cancela o pedido
```

---

## Choreography Saga (Laravel)

**Cenário: criar pedido**

**1. Order Service**

```php
class CreateOrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create([
            'user_id' => $request->user_id,
            'status' => 'pending',
            'total' => $request->total,
        ]);

        // Emite o event
        event(new OrderCreated($order));

        return response()->json($order);
    }
}

// Event
class OrderCreated implements ShouldQueue
{
    public function __construct(public Order $order) {}
}

// Listener
class HandlePaymentFailed implements ShouldQueue
{
    public function handle(PaymentFailed $event)
    {
        // Compensation: cancela o pedido
        $order = Order::find($event->orderId);
        $order->update(['status' => 'cancelled']);

        Log::info("Pedido {$order->id} cancelado por falha no pagamento");
    }
}
```

---

**2. Inventory Service**

```php
// Listener
class ReserveInventory implements ShouldQueue
{
    public function handle(OrderCreated $event)
    {
        try {
            $order = $event->order;

            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product->stock < $item->quantity) {
                    throw new OutOfStockException();
                }

                $product->decrement('stock', $item->quantity);

                // Cria a reserva
                Reservation::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item->quantity,
                ]);
            }

            // Success: emite o event
            event(new InventoryReserved($order->id));

        } catch (OutOfStockException $e) {
            // Failure: emite o event
            event(new InventoryReservationFailed($order->id, $e->getMessage()));
        }
    }
}

class CancelReservation implements ShouldQueue
{
    public function handle(PaymentFailed $event)
    {
        // Compensation: cancela a reserva
        $reservations = Reservation::where('order_id', $event->orderId)->get();

        foreach ($reservations as $reservation) {
            $product = Product::find($reservation->product_id);
            $product->increment('stock', $reservation->quantity);

            $reservation->delete();
        }

        Log::info("Reservas do pedido {$event->orderId} canceladas");
    }
}
```

---

**3. Payment Service**

```php
// Listener
class ProcessPayment implements ShouldQueue
{
    public function handle(InventoryReserved $event)
    {
        try {
            $order = Order::find($event->orderId);

            // Cobra o pagamento
            $payment = $this->chargeCustomer($order);

            // Success: emite o event
            event(new PaymentProcessed($order->id, $payment->id));

        } catch (PaymentException $e) {
            // Failure: emite o event (dispara a compensation)
            event(new PaymentFailed($order->id, $e->getMessage()));
        }
    }

    private function chargeCustomer(Order $order): Payment
    {
        // Stripe API, PayPal, etc.
        return Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total,
            'status' => 'completed',
        ]);
    }
}
```

---

**4. Encerramento (Order Service)**

```php
// Listener
class CompleteOrder implements ShouldQueue
{
    public function handle(PaymentProcessed $event)
    {
        $order = Order::find($event->orderId);
        $order->update(['status' => 'completed']);

        // Notifica o cliente
        Mail::to($order->user)->send(new OrderCompletedEmail($order));

        Log::info("Pedido {$order->id} concluído");
    }
}
```

---

## Orchestration Saga (Laravel)

**Saga Orchestrator:**

```php
class CreateOrderSaga
{
    private Order $order;
    private array $compensations = [];

    public function execute(array $data): Order
    {
        try {
            // Step 1: Create Order
            $this->order = $this->createOrder($data);
            $this->compensations[] = fn() => $this->cancelOrder($this->order);

            // Step 2: Reserve Inventory
            $reservations = $this->reserveInventory($this->order);
            $this->compensations[] = fn() => $this->cancelReservations($reservations);

            // Step 3: Process Payment
            $payment = $this->processPayment($this->order);
            $this->compensations[] = fn() => $this->refundPayment($payment);

            // Step 4: Complete Order
            $this->completeOrder($this->order);

            return $this->order;

        } catch (Exception $e) {
            // Rollback: roda as compensations na ordem inversa
            $this->compensate();

            throw $e;
        }
    }

    private function createOrder(array $data): Order
    {
        return Order::create([
            'user_id' => $data['user_id'],
            'status' => 'pending',
            'total' => $data['total'],
        ]);
    }

    private function reserveInventory(Order $order): array
    {
        // Chamada HTTP no Inventory Service
        $response = Http::post('http://inventory-service/api/reserve', [
            'order_id' => $order->id,
            'items' => $order->items->toArray(),
        ]);

        if ($response->failed()) {
            throw new InventoryException('Falha ao reservar estoque');
        }

        return $response->json('reservations');
    }

    private function processPayment(Order $order): Payment
    {
        // Chamada HTTP no Payment Service
        $response = Http::post('http://payment-service/api/charge', [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);

        if ($response->failed()) {
            throw new PaymentException('Pagamento falhou');
        }

        return new Payment($response->json());
    }

    private function completeOrder(Order $order): void
    {
        $order->update(['status' => 'completed']);
    }

    private function compensate(): void
    {
        // Roda as compensations na ordem inversa
        foreach (array_reverse($this->compensations) as $compensation) {
            try {
                $compensation();
            } catch (Exception $e) {
                Log::error('Compensation falhou', ['error' => $e->getMessage()]);
            }
        }
    }

    private function cancelOrder(Order $order): void
    {
        $order->update(['status' => 'cancelled']);
    }

    private function cancelReservations(array $reservations): void
    {
        Http::post('http://inventory-service/api/cancel-reservations', [
            'reservations' => $reservations,
        ]);
    }

    private function refundPayment(Payment $payment): void
    {
        Http::post('http://payment-service/api/refund', [
            'payment_id' => $payment->id,
        ]);
    }
}

// Controller
class OrderController extends Controller
{
    public function store(Request $request, CreateOrderSaga $saga)
    {
        try {
            $order = $saga->execute($request->validated());

            return response()->json($order);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Falha ao criar o pedido',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
```

---

## Choreography vs Orchestration

| Choreography | Orchestration |
|--------------|---------------|
| Descentralizado | Centralizado (Orchestrator) |
| Events | HTTP calls |
| Coupling fraco | Coupling forte |
| Debug mais difícil | Debug mais fácil |
| Saga simples | Saga complexa |

---

## Saga State Machine

**Saga complexa → state machine:**

```php
class OrderSagaStateMachine
{
    private string $state = 'created';

    public function transition(string $event): void
    {
        $this->state = match ([$this->state, $event]) {
            ['created', 'inventory_reserved'] => 'inventory_reserved',
            ['inventory_reserved', 'payment_processed'] => 'payment_processed',
            ['payment_processed', 'order_completed'] => 'completed',

            // Compensations
            ['inventory_reserved', 'payment_failed'] => 'compensating_inventory',
            ['compensating_inventory', 'inventory_cancelled'] => 'cancelled',

            default => throw new InvalidTransitionException(),
        };

        $this->save();
    }

    private function save(): void
    {
        // Persiste o state no banco
        SagaState::updateOrCreate(
            ['saga_id' => $this->sagaId],
            ['state' => $this->state]
        );
    }
}
```

---

## Idempotency

**Problema: events podem duplicar (network retry).**

**Solução: idempotency key**

```php
class ReserveInventory implements ShouldQueue
{
    public function handle(OrderCreated $event)
    {
        $idempotencyKey = "reserve_inventory:{$event->order->id}";

        // Já rodou?
        if (Cache::has($idempotencyKey)) {
            Log::info("Estoque já reservado para o pedido {$event->order->id}");
            return;
        }

        // Reserva o estoque
        $this->reserve($event->order);

        // Marca como feito (TTL 24h)
        Cache::put($idempotencyKey, true, 86400);
    }
}
```

---

## Monitoramento e debug

**Saga Log:**

```php
class SagaLog extends Model
{
    protected $fillable = ['saga_id', 'step', 'status', 'data', 'error'];
}

// Em cada passo
SagaLog::create([
    'saga_id' => $this->sagaId,
    'step' => 'reserve_inventory',
    'status' => 'completed',
    'data' => json_encode($reservations),
]);

// Se der erro
SagaLog::create([
    'saga_id' => $this->sagaId,
    'step' => 'process_payment',
    'status' => 'failed',
    'error' => $e->getMessage(),
]);
```

**Dashboard da Saga:**

```php
// Ver todos os passos da saga
$logs = SagaLog::where('saga_id', $sagaId)->orderBy('created_at')->get();

foreach ($logs as $log) {
    echo "{$log->step}: {$log->status}\n";
}
```

---

## Boas práticas

```
✓ Idempotency em toda operação
✓ Compensation em cada passo
✓ Logging de todos os passos da saga
✓ Retry com exponential backoff
✓ Timeout em cada passo
✓ Monitoring: sagas que não fecharam
✓ Dead Letter Queue para events que falharam
✓ State machine nas sagas complexas
✓ Orchestration no complexo, Choreography no simples
```

---

## Quando usar

**Saga entra quando:**
- ✅ Microsserviços com bancos diferentes
- ✅ A operação toca vários serviços
- ✅ Precisa de consistência

**Saga NÃO entra quando:**
- ❌ Monolito (usa transação ACID)
- ❌ Eventual consistency serve (events simples)
- ❌ A operação fica num serviço só

---

## Exercícios práticos

<details>
<summary>Exercício 1: Orchestration Saga para pedido</summary>

**Enunciado:**
Crie uma Orchestration Saga para criar pedido, com os passos: criar pedido, reservar estoque, pagamento, confirmar. Se der erro, roda a compensation.

**Solução:**

```php
class CreateOrderSaga
{
    private Order $order;
    private array $compensations = [];

    public function execute(array $data): Order
    {
        DB::beginTransaction();

        try {
            // Passo 1: criar o pedido
            $this->order = Order::create([
                'user_id' => $data['user_id'],
                'status' => 'pending',
                'total' => $data['total'],
            ]);
            $this->compensations[] = fn() => $this->order->delete();
            $this->logStep('order_created', 'completed');

            // Passo 2: reservar estoque
            $reservation = $this->reserveInventory($data['items']);
            $this->compensations[] = fn() => $this->cancelReservation($reservation);
            $this->logStep('inventory_reserved', 'completed');

            // Passo 3: pagamento
            $payment = $this->processPayment($this->order);
            $this->compensations[] = fn() => $this->refundPayment($payment);
            $this->logStep('payment_processed', 'completed');

            // Passo 4: confirmar o pedido
            $this->order->update(['status' => 'completed']);
            $this->logStep('order_completed', 'completed');

            DB::commit();
            return $this->order;

        } catch (Exception $e) {
            DB::rollBack();
            $this->logStep('saga_failed', 'failed', $e->getMessage());
            $this->compensate();
            throw $e;
        }
    }

    private function reserveInventory(array $items): array
    {
        $response = Http::timeout(5)->post('http://inventory-service/api/reserve', [
            'order_id' => $this->order->id,
            'items' => $items,
        ]);

        if ($response->failed()) {
            throw new InventoryException('Falha ao reservar estoque');
        }

        return $response->json('reservation_id');
    }

    private function processPayment(Order $order)
    {
        $response = Http::timeout(5)->post('http://payment-service/api/charge', [
            'order_id' => $order->id,
            'amount' => $order->total,
        ]);

        if ($response->failed()) {
            throw new PaymentException('Pagamento falhou');
        }

        return $response->json('payment_id');
    }

    private function compensate(): void
    {
        logger()->warning("Iniciando compensation do pedido {$this->order->id}");

        foreach (array_reverse($this->compensations) as $index => $compensation) {
            try {
                $compensation();
                logger()->info("Passo de compensation {$index} concluído");
            } catch (Exception $e) {
                logger()->error("Passo de compensation {$index} falhou: {$e->getMessage()}");
            }
        }
    }

    private function cancelReservation($reservationId): void
    {
        Http::post('http://inventory-service/api/cancel', ['reservation_id' => $reservationId]);
    }

    private function refundPayment($paymentId): void
    {
        Http::post('http://payment-service/api/refund', ['payment_id' => $paymentId]);
    }

    private function logStep(string $step, string $status, ?string $error = null): void
    {
        SagaLog::create([
            'saga_id' => $this->order->id,
            'step' => $step,
            'status' => $status,
            'error' => $error,
            'created_at' => now(),
        ]);
    }
}
```
</details>

<details>
<summary>Exercício 2: Choreography Saga com events</summary>

**Enunciado:**
Implemente uma Choreography Saga usando Laravel Events para criar pedido.

**Solução:**

```php
// 1. Order Service — cria o pedido
class CreateOrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create([
            'user_id' => $request->user_id,
            'status' => 'pending',
            'total' => $request->total,
        ]);

        // Emite o event
        event(new OrderCreated($order));

        return response()->json($order);
    }
}

// 2. Inventory Service — escuta OrderCreated
class ReserveInventoryListener
{
    public function handle(OrderCreated $event)
    {
        try {
            foreach ($event->order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                if ($product->stock < $item->quantity) {
                    throw new OutOfStockException();
                }

                $product->decrement('stock', $item->quantity);
            }

            // Success
            event(new InventoryReserved($event->order->id));

        } catch (OutOfStockException $e) {
            // Failure
            event(new InventoryReservationFailed($event->order->id));
        }
    }
}

// 3. Payment Service — escuta InventoryReserved
class ProcessPaymentListener
{
    public function handle(InventoryReserved $event)
    {
        try {
            $payment = $this->chargeCustomer($event->orderId);
            event(new PaymentProcessed($event->orderId, $payment->id));
        } catch (PaymentException $e) {
            event(new PaymentFailed($event->orderId));
        }
    }
}

// 4. Compensation — Order Service escuta PaymentFailed
class CancelOrderListener
{
    public function handle(PaymentFailed $event)
    {
        $order = Order::find($event->orderId);
        $order->update(['status' => 'cancelled']);

        logger()->info("Pedido {$event->orderId} cancelado por falha no pagamento");
    }
}

// 5. Compensation — Inventory Service escuta PaymentFailed
class CancelReservationListener
{
    public function handle(PaymentFailed $event)
    {
        $order = Order::find($event->orderId);

        foreach ($order->items as $item) {
            $product = Product::find($item->product_id);
            $product->increment('stock', $item->quantity);
        }

        logger()->info("Reserva de estoque cancelada para o pedido {$event->orderId}");
    }
}

// EventServiceProvider.php
protected $listen = [
    OrderCreated::class => [ReserveInventoryListener::class],
    InventoryReserved::class => [ProcessPaymentListener::class],
    PaymentProcessed::class => [CompleteOrderListener::class],
    PaymentFailed::class => [CancelOrderListener::class, CancelReservationListener::class],
];
```
</details>

<details>
<summary>Exercício 3: Idempotency na Saga</summary>

**Enunciado:**
Coloque idempotency no listener da Saga para não duplicar quando o event der retry.

**Solução:**

```php
class ReserveInventoryListener implements ShouldQueue
{
    public function handle(OrderCreated $event)
    {
        $orderId = $event->order->id;
        $idempotencyKey = "saga:reserve_inventory:{$orderId}";

        // Já rodou?
        if (Cache::has($idempotencyKey)) {
            logger()->info("Estoque já reservado para o pedido {$orderId} (idempotency)");
            return;
        }

        try {
            DB::transaction(function () use ($event) {
                foreach ($event->order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);

                    if ($product->stock < $item->quantity) {
                        throw new OutOfStockException();
                    }

                    $product->decrement('stock', $item->quantity);

                    // Salva a reserva
                    Reservation::create([
                        'order_id' => $event->order->id,
                        'product_id' => $product->id,
                        'quantity' => $item->quantity,
                    ]);
                }
            });

            // Marca como feito (TTL 24 horas)
            Cache::put($idempotencyKey, true, 86400);

            // Success event
            event(new InventoryReserved($event->order->id));

        } catch (OutOfStockException $e) {
            // Failure event
            event(new InventoryReservationFailed($event->order->id, $e->getMessage()));
        }
    }
}

// No teste você confere a idempotency
public function test_reservation_is_idempotent()
{
    $order = Order::factory()->create();
    $event = new OrderCreated($order);

    // Primeira chamada
    (new ReserveInventoryListener())->handle($event);
    $firstStock = Product::first()->stock;

    // Segunda chamada (não pode mudar o stock)
    (new ReserveInventoryListener())->handle($event);
    $secondStock = Product::first()->stock;

    $this->assertEquals($firstStock, $secondStock);
}
```
</details>

---

## Na entrevista

> "Saga Pattern gerencia transação distribuída em microsserviços. O problema: não tem ACID entre bancos diferentes. A solução: sequência de transações locais + compensation. Tipos: Choreography (events, descentralizado, coupling fraco) e Orchestration (coordinator, HTTP calls, centralizado). Compensation: desfaz na ordem inversa se der erro. Idempotency: não duplica event. State Machine nas sagas complexas. Monitoring: loga cada passo, dashboard das sagas abertas. Boas práticas: idempotency, retry, timeout, DLQ. Orchestration no complexo, Choreography no simples."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
