# 18.1 Monólito vs Microsserviços

> **TL;DR**
> Monólito — uma base de código, um banco, desenvolvimento simples, mas o scaling dói. Microsserviços — serviços independentes, cada um com seu banco; mais difícil de desenvolver, mas escalam melhor. Monólito modular é o meio-termo para se preparar. Migração pelo Strangler Fig Pattern. Dados nos microsserviços: Saga Pattern para consistência.

## Conteúdo
- [O que é](#o-que-é)
- [Monólito](#monólito)
- [Microsserviços](#microsserviços)
- [Quando usar](#quando-usar)
- [Monólito modular (híbrido)](#monólito-modular-híbrido)
- [Migração monólito → microsserviços](#migração-monólito--microsserviços)
- [Gestão de dados](#gestão-de-dados)
- [Comunicação](#comunicação)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Monólito:**
O app inteiro numa base de código só. Um deploy, um banco, um processo.

**Microsserviços:**
O app vira serviços independentes. Cada serviço tem deploy próprio, banco próprio, base de código própria.

---

## Monólito

**Arquitetura:**

```
Single Application
├── Users module
├── Orders module
├── Payments module
├── Notifications module
└── Shared Database
```

**Prós:**

```
✅ Desenvolvimento simples (um projeto só)
✅ Deploy simples (um deploy)
✅ Teste simples (testes de integração)
✅ Transações funcionam (um banco)
✅ Sem chamada de rede (tudo no mesmo processo)
✅ Debug mais fácil
✅ Menos complexidade de DevOps
```

**Contras:**

```
❌ Scaling: você escala tudo (mesmo se o bottleneck for um módulo só)
❌ Tight coupling: mudança num módulo mexe no resto
❌ Deployment: um bug trava o deploy inteiro
❌ Technology lock-in: não dá para misturar tecnologias
❌ Team scaling: time grande sofre
❌ Startup time: o app grande demora para iniciar
```

**Exemplo (Laravel Monolith):**

```php
// app/Http/Controllers/OrderController.php
class OrderController extends Controller
{
    public function store(Request $request)
    {
        DB::transaction(function () use ($request) {
            // 1. Users module
            $user = User::find(auth()->id());

            // 2. Orders module
            $order = Order::create([...]);

            // 3. Payments module
            Payment::charge($user, $order->total);

            // 4. Notifications module
            Notification::send($user, new OrderCreated($order));
        });

        return redirect('/orders');
    }
}

// Tudo numa transação, um banco, um processo
```

---

## Microsserviços

**Arquitetura:**

```
API Gateway
    ↓
┌─────────────┬─────────────┬─────────────┬─────────────┐
│ User        │ Order       │ Payment     │ Notification│
│ Service     │ Service     │ Service     │ Service     │
│             │             │             │             │
│ Users DB    │ Orders DB   │ Payments DB │ Notif. DB   │
└─────────────┴─────────────┴─────────────┴─────────────┘
```

**Prós:**

```
✅ Independent scaling: escala só o serviço que precisa
✅ Independent deployment: deploy de um serviço não mexe nos outros
✅ Technology diversity: cada serviço pode ter a própria linguagem
✅ Team independence: times trabalham separados
✅ Fault isolation: bug num serviço não derruba o resto
✅ Easier understanding: base de código pequena é mais fácil de entender
```

**Contras:**

```
❌ Complexity: sistema distribuído é mais difícil
❌ Network calls: latência, falhas
❌ Data consistency: sem transação ACID entre serviços
❌ Testing: teste de integração fica mais difícil
❌ Debugging: o trace atravessa N serviços
❌ DevOps overhead: N serviços, N deploys, N bancos
❌ Eventual consistency no lugar de strong consistency
```

**Exemplo (Microsserviços):**

```php
// Order Service
class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 1. Chama o User Service via HTTP
        $user = Http::get("http://user-service/api/users/{$userId}")->json();

        // 2. Cria o pedido (banco local)
        $order = Order::create([...]);

        // 3. Chama o Payment Service
        $payment = Http::post("http://payment-service/api/charge", [
            'user_id' => $userId,
            'amount' => $order->total,
        ])->json();

        if (!$payment['success']) {
            // Rollback? Não dá! Bancos diferentes
            // Precisa de Saga ou compensation
            $this->compensateOrder($order);
            throw new PaymentFailedException();
        }

        // 4. Notificação assíncrona via message queue
        Queue::push('notification-service', new OrderCreated($order));

        return response()->json($order);
    }
}
```

---

## Quando usar

### Monólito para:

```
✓ Startups / MVP
✓ Time pequeno (< 10 pessoas)
✓ Apps simples
✓ Precisa de consistência forte
✓ Sem experiência em microsserviços
```

### Microsserviços para:

```
✓ Times grandes (> 20 pessoas)
✓ Partes diferentes precisam de scaling diferente
✓ Ciclos de deploy independentes
✓ Tecnologias diferentes para partes diferentes
✓ Tem expertise de DevOps
```

---

## Monólito modular (híbrido)

**O que é:**
Monólito com divisão clara em módulos. Preparação para microsserviços.

**Estrutura:**

```php
app/
├── Modules/
│   ├── Users/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── routes.php
│   ├── Orders/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── routes.php
│   └── Payments/
│       ├── Controllers/
│       ├── Models/
│       ├── Services/
│       └── routes.php
└── Shared/
    └── Database (compartilhado, mas dá para separar depois)
```

**Regras:**

```
✓ Módulos só se falam pela API pública (Services)
✓ Sem acesso direto aos Models de outro módulo
✓ Boundaries claros

// ❌ RUIM
$user = \App\Modules\Users\Models\User::find($id);

// ✅ BOM
$user = app(UserService::class)->find($id);
```

**Prós:**

```
✅ Simplicidade do monólito
✅ Preparação para microsserviços
✅ Depois é fácil extrair o módulo para um serviço
```

---

## Migração monólito → microsserviços

**Strangler Fig Pattern:**

```
1. O monólito continua rodando
2. Extrai um módulo para microsserviço
3. O API Gateway roteia parte das requests para o serviço novo
4. Migra o resto dos módulos aos poucos
5. No fim, desliga o monólito
```

**Passos:**

```
Step 1: Monólito
┌─────────────────────────┐
│   Monolith              │
│  - Users                │
│  - Orders               │
│  - Payments             │
└─────────────────────────┘

Step 2: Extrai o Payment Service
┌─────────────────────────┐        ┌─────────────┐
│   Monolith              │        │  Payment    │
│  - Users                │──────▶ │  Service    │
│  - Orders               │        └─────────────┘
│  - Payments (deprecated)│
└─────────────────────────┘

Step 3: Extrai o Order Service
┌─────────────────────────┐        ┌─────────────┐
│   Monolith              │        │  Order      │
│  - Users                │──────▶ │  Service    │
│  - Orders (deprecated)  │        └─────────────┘
└─────────────────────────┘        ┌─────────────┐
                                   │  Payment    │
                                   │  Service    │
                                   └─────────────┘

Step 4: Só o User Service sobrou
┌─────────────┐
│  User       │
│  Service    │
└─────────────┘
┌─────────────┐
│  Order      │
│  Service    │
└─────────────┘
┌─────────────┐
│  Payment    │
│  Service    │
└─────────────┘
```

---

## Gestão de dados

**Monólito:**

```sql
-- Um banco, transações ACID
BEGIN;
  INSERT INTO orders (...);
  UPDATE products SET stock = stock - 1;
  INSERT INTO payments (...);
COMMIT;
```

**Microsserviços:**

```
Order Service DB: orders
Payment Service DB: payments
Product Service DB: products

Não dá para fazer transação entre bancos!
```

**Soluções:**

### 1. Saga Pattern

```php
// Order Service
class CreateOrderSaga
{
    public function execute($data)
    {
        try {
            // 1. Cria o pedido
            $order = $this->orderService->create($data);

            // 2. Reserva o produto
            $reservation = $this->productService->reserve($data['product_id']);

            // 3. Pagamento
            $payment = $this->paymentService->charge($data['amount']);

            // 4. Confirma a reserva
            $this->productService->confirmReservation($reservation['id']);

            return $order;

        } catch (Exception $e) {
            // Compensation: desfaz as mudanças
            $this->productService->cancelReservation($reservation['id']);
            $this->orderService->cancel($order['id']);

            throw $e;
        }
    }
}
```

### 2. Event Sourcing

```php
// Cada mudança = um evento
event(new OrderCreated($order));
event(new PaymentProcessed($payment));
event(new InventoryReserved($product));

// Os outros serviços escutam os eventos e atualizam o próprio banco
```

### 3. Two-phase commit (2PC)

```
Coordinator:
1. Prepare phase: pergunta a todos os serviços "prontos?"
2. Commit phase: se todos disserem "sim" → commit, senão → rollback

❌ Quase ninguém usa (lento, blocking)
```

---

## Comunicação

**Síncrona (HTTP/REST):**

```php
// Order Service chama o User Service
$user = Http::get("http://user-service/api/users/{$id}")->json();

✅ Simples
❌ Tight coupling
❌ Se o user-service cair → o order-service para
```

**Assíncrona (Message Queue):**

```php
// Order Service publica o evento
event(new OrderCreated($order));

// User Service e Payment Service escutam e reagem
✅ Loose coupling
✅ Fault tolerant
❌ Eventual consistency
```

---

## Exercícios práticos

### Exercício 1: Monólito modular

**Enunciado:** Crie um monólito modular com dois módulos: Users e Orders. Os módulos só se falam por services, nunca direto pelos Models.

<details>
<summary>Solução</summary>

```php
// app/Modules/Users/Services/UserService.php
namespace App\Modules\Users\Services;

class UserService
{
    public function find(int $id): ?array
    {
        $user = \App\Modules\Users\Models\User::find($id);

        return $user ? [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ] : null;
    }
}

// app/Modules/Orders/Controllers/OrderController.php
namespace App\Modules\Orders\Controllers;

use App\Modules\Users\Services\UserService;

class OrderController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function store(Request $request)
    {
        // ✅ CERTO: pelo service
        $user = $this->userService->find($request->user_id);

        if (!$user) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }

        $order = Order::create([
            'user_id' => $user['id'],
            'total' => $request->total,
        ]);

        return response()->json($order);
    }
}
```
</details>

### Exercício 2: Saga Pattern para criar pedido

**Enunciado:** Implemente uma Saga simples para criar o pedido, com compensation se o pagamento falhar.

<details>
<summary>Solução</summary>

```php
class CreateOrderSaga
{
    private array $compensations = [];

    public function execute(array $data): Order
    {
        try {
            // Passo 1: Cria o pedido
            $order = Order::create(['status' => 'pending', ...$data]);
            $this->compensations[] = fn() => $order->delete();

            // Passo 2: Reserva o produto
            $product = Product::lockForUpdate()->find($data['product_id']);
            if ($product->stock < $data['quantity']) {
                throw new OutOfStockException();
            }
            $product->decrement('stock', $data['quantity']);
            $this->compensations[] = fn() => $product->increment('stock', $data['quantity']);

            // Passo 3: Pagamento (pode falhar)
            $payment = $this->processPayment($order);
            $this->compensations[] = fn() => $this->refundPayment($payment);

            // Sucesso
            $order->update(['status' => 'completed']);
            return $order;

        } catch (Exception $e) {
            // Compensation: desfaz na ordem inversa
            foreach (array_reverse($this->compensations) as $compensation) {
                $compensation();
            }
            throw $e;
        }
    }

    private function processPayment(Order $order): Payment
    {
        // Simula o pagamento
        if (rand(0, 1)) {
            throw new PaymentFailedException();
        }
        return Payment::create(['order_id' => $order->id, 'status' => 'paid']);
    }

    private function refundPayment(Payment $payment): void
    {
        $payment->update(['status' => 'refunded']);
    }
}
```
</details>

### Exercício 3: Strangler Fig Migration

**Enunciado:** Implemente o Strangler Fig para migrar o monólito para microsserviços aos poucos, via API Gateway.

<details>
<summary>Solução</summary>

```php
// API Gateway (routes/api.php)
Route::any('/api/payments/{path}', function (Request $request, $path) {
    // Microsserviço novo
    return Http::asForm()
        ->withToken($request->bearerToken())
        ->send(
            $request->method(),
            "http://payment-service/api/{$path}",
            ['json' => $request->all()]
        );
})->where('path', '.*');

Route::any('/api/{service}/{path}', function (Request $request, $service, $path) {
    // Monólito antigo (os outros serviços)
    return app()->call("App\\Http\\Controllers\\{$service}@{$path}", $request->all());
})->where('path', '.*');

// Vai extraindo os serviços:
// 1. Payment Service → microsserviço (pronto)
// 2. Order Service → o próximo
// 3. User Service → o último
// 4. Apaga o monólito
```
</details>

---

## Na entrevista

> "Monólito: uma base de código, um banco, um deploy. Prós: simplicidade, transações, debug. Contras: scaling do sistema inteiro, tight coupling, time grande sofre. Microsserviços: serviços independentes, cada um com seu banco. Prós: scaling e deploy independentes, tecnologias diferentes. Contras: sistema distribuído, eventual consistency, latência de rede. Monólito modular é o híbrido: você se prepara para microsserviços. Migração: Strangler Fig Pattern. Consistência dos dados: Saga Pattern, Event Sourcing. Comunicação: síncrona (HTTP) ou assíncrona (message queue)."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
