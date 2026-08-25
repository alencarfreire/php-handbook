# 10.5 DDD (Domain-Driven Design)

## Resumo

> **DDD** — abordagem de desenvolvimento orientada ao domínio (domain).
>
> **Conceitos:** Entity (com ID), Value Object (sem ID), Aggregate (grupo de Entity), Repository, Domain Events.
>
> **Importante:** Estrutura: Domain (lógica), Infrastructure (banco/API), Application (use cases). Serve para regra de negócio complexa.

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
DDD — abordagem de desenvolvimento orientada ao domínio (domain). O código espelha a regra de negócio.

**Conceitos principais:**
- Entity — objeto com identidade
- Value Object — objeto sem identidade
- Aggregate — grupo de objetos relacionados
- Repository — acesso aos aggregates
- Domain Event — evento no domain

---

## Como funciona

**Entity (com identidade):**

```php
// app/Domain/Order/Order.php
class Order
{
    private OrderId $id;
    private UserId $userId;
    private OrderStatus $status;
    private Money $total;
    private Collection $items;

    public function __construct(OrderId $id, UserId $userId)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->status = OrderStatus::pending();
        $this->items = collect();
    }

    public function addItem(Product $product, int $quantity): void
    {
        $this->items->push(new OrderItem($product, $quantity));
        $this->recalculateTotal();
    }

    public function markAsPaid(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException();
        }

        $this->status = OrderStatus::paid();
        $this->recordEvent(new OrderPaid($this->id));
    }

    private function recalculateTotal(): void
    {
        $this->total = $this->items->sum(fn($item) => $item->subtotal());
    }
}
```

**Value Object (sem identidade):**

```php
// app/Domain/Order/Money.php
class Money
{
    private function __construct(
        private float $amount,
        private string $currency
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Valor não pode ser negativo');
        }
    }

    public static function fromAmount(float $amount, string $currency = 'BRL'): self
    {
        return new self($amount, $currency);
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }

        return new Money($this->amount + $other->amount, $this->currency);
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }
}
```

**Aggregate Root:**

```php
class Order // Aggregate Root
{
    private Collection $items; // Parte do aggregate

    public function addItem(Product $product, int $quantity): void
    {
        // Só pelo Order dá para adicionar item
        $this->items->push(new OrderItem($product, $quantity));
    }

    // Não dá para alterar OrderItem direto, fora do Order
}
```

---

## Quando usar

**DDD serve para:**
- Lógica de negócio complexa
- Projeto grande
- Requisito que muda

**NÃO serve para:**
- CRUD simples
- Projeto pequeno

---

## Exemplo prático

**Estrutura do projeto:**

```
app/Domain/
├── Order/
│   ├── Order.php (Entity)
│   ├── OrderId.php (Value Object)
│   ├── OrderStatus.php (Value Object)
│   ├── OrderItem.php (Entity)
│   ├── OrderRepository.php (Interface)
│   └── Events/
│       ├── OrderCreated.php
│       └── OrderPaid.php
├── Product/
│   ├── Product.php
│   └── ProductRepository.php
└── User/
    ├── User.php
    └── UserRepository.php

app/Infrastructure/
├── Persistence/
│   ├── EloquentOrderRepository.php
│   └── EloquentProductRepository.php
└── Services/
    └── StripePaymentService.php

app/Application/
├── Commands/
│   ├── CreateOrderCommand.php
│   └── CreateOrderHandler.php
└── Queries/
    ├── GetOrderQuery.php
    └── GetOrderHandler.php
```

**Domain Service:**

```php
// app/Domain/Order/OrderService.php
class OrderService
{
    public function __construct(
        private OrderRepository $orders,
        private ProductRepository $products
    ) {}

    public function placeOrder(UserId $userId, array $items): Order
    {
        $order = new Order(OrderId::generate(), $userId);

        foreach ($items as $item) {
            $product = $this->products->find($item['product_id']);
            $order->addItem($product, $item['quantity']);
        }

        $this->orders->save($order);

        return $order;
    }
}
```

---

## Na entrevista

> "DDD é orientado ao domínio. Entity tem identidade (Order, User). Value Object não tem (Money, Address). Aggregate é um grupo de Entity relacionadas, acesso só pelo Root. Repository para os aggregates. Domain Events para reagir a mudanças. Estrutura: Domain (lógica), Infrastructure (banco, API), Application (use cases). Serve para regra de negócio complexa, não para CRUD simples."

---

## Exercícios práticos

### Exercício 1: Crie um Value Object para Money

**Enunciado:** Implemente o Value Object `Money` com validação e operações aritméticas.

<details>
<summary>Solução</summary>

```php
// app/Domain/Shared/ValueObjects/Money.php
namespace App\Domain\Shared\ValueObjects;

class Money
{
    private function __construct(
        private readonly float $amount,
        private readonly string $currency
    ) {
        $this->validate();
    }

    public static function fromAmount(float $amount, string $currency = 'BRL'): self
    {
        return new self($amount, $currency);
    }

    public static function zero(string $currency = 'BRL'): self
    {
        return new self(0, $currency);
    }

    private function validate(): void
    {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Valor não pode ser negativo');
        }

        $allowedCurrencies = ['BRL', 'USD', 'EUR'];
        if (!in_array($this->currency, $allowedCurrencies)) {
            throw new \InvalidArgumentException("Moeda inválida: {$this->currency}");
        }
    }

    public function add(Money $other): Money
    {
        $this->ensureSameCurrency($other);

        return new Money(
            $this->amount + $other->amount,
            $this->currency
        );
    }

    public function subtract(Money $other): Money
    {
        $this->ensureSameCurrency($other);

        return new Money(
            $this->amount - $other->amount,
            $this->currency
        );
    }

    public function multiply(float $multiplier): Money
    {
        return new Money(
            $this->amount * $multiplier,
            $this->currency
        );
    }

    public function equals(Money $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    public function greaterThan(Money $other): bool
    {
        $this->ensureSameCurrency($other);

        return $this->amount > $other->amount;
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Moedas diferentes: {$this->currency} vs {$other->currency}"
            );
        }
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function format(): string
    {
        return match ($this->currency) {
            'BRL' => 'R$ ' . number_format($this->amount, 2, ',', '.'),
            'USD' => '$' . number_format($this->amount, 2),
            'EUR' => '€' . number_format($this->amount, 2),
        };
    }

    public function __toString(): string
    {
        return $this->format();
    }
}

// Uso
$price = Money::fromAmount(100, 'BRL');
$tax = Money::fromAmount(20, 'BRL');
$total = $price->add($tax); // R$ 120 BRL

echo $total->format(); // R$ 120,00
```
</details>

### Exercício 2: Implemente Aggregate Root para Order

**Enunciado:** Crie `Order` como Aggregate Root com `OrderItem` dentro. Só o Order altera os items.

<details>
<summary>Solução</summary>

```php
// app/Domain/Order/Order.php
namespace App\Domain\Order;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Collection;

class Order // Aggregate Root
{
    private OrderId $id;
    private UserId $userId;
    private OrderStatus $status;
    private Collection $items;
    private Money $total;

    public function __construct(OrderId $id, UserId $userId)
    {
        $this->id = $id;
        $this->userId = $userId;
        $this->status = OrderStatus::pending();
        $this->items = collect();
        $this->total = Money::zero();
    }

    // Só pelo Order dá para adicionar item
    public function addItem(ProductId $productId, int $quantity, Money $price): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }

        $item = new OrderItem($productId, $quantity, $price);
        $this->items->push($item);
        $this->recalculateTotal();
    }

    public function removeItem(ProductId $productId): void
    {
        $this->items = $this->items->reject(
            fn(OrderItem $item) => $item->getProductId()->equals($productId)
        );
        $this->recalculateTotal();
    }

    public function updateItemQuantity(ProductId $productId, int $quantity): void
    {
        $item = $this->findItem($productId);

        if (!$item) {
            throw new \DomainException('Item não encontrado no pedido');
        }

        $item->updateQuantity($quantity);
        $this->recalculateTotal();
    }

    public function markAsPaid(): void
    {
        if (!$this->status->isPending()) {
            throw new \DomainException('Só pedido pendente pode ser marcado como pago');
        }

        $this->status = OrderStatus::paid();
    }

    public function cancel(): void
    {
        if ($this->status->isShipped()) {
            throw new \DomainException('Não dá para cancelar pedido já enviado');
        }

        $this->status = OrderStatus::cancelled();
    }

    private function recalculateTotal(): void
    {
        $this->total = $this->items->reduce(
            fn(Money $total, OrderItem $item) => $total->add($item->getSubtotal()),
            Money::zero()
        );
    }

    private function findItem(ProductId $productId): ?OrderItem
    {
        return $this->items->first(
            fn(OrderItem $item) => $item->getProductId()->equals($productId)
        );
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }
}

// app/Domain/Order/OrderItem.php
class OrderItem // Parte do aggregate
{
    private Money $subtotal;

    public function __construct(
        private readonly ProductId $productId,
        private int $quantity,
        private readonly Money $price
    ) {
        $this->calculateSubtotal();
    }

    public function updateQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser positiva');
        }

        $this->quantity = $quantity;
        $this->calculateSubtotal();
    }

    private function calculateSubtotal(): void
    {
        $this->subtotal = $this->price->multiply($this->quantity);
    }

    public function getProductId(): ProductId
    {
        return $this->productId;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }
}
```
</details>

### Exercício 3: Crie um Domain Event

**Enunciado:** Implemente o Domain Event `OrderPlaced` e o listener.

<details>
<summary>Solução</summary>

```php
// app/Domain/Order/Events/OrderPlaced.php
namespace App\Domain\Order\Events;

use App\Domain\Order\Order;
use DateTimeImmutable;

class OrderPlaced
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly float $total,
        public readonly DateTimeImmutable $occurredAt
    ) {}

    public static function fromOrder(Order $order): self
    {
        return new self(
            orderId: (string) $order->getId(),
            userId: (string) $order->getUserId(),
            total: $order->getTotal()->getAmount(),
            occurredAt: new DateTimeImmutable()
        );
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'user_id' => $this->userId,
            'total' => $this->total,
            'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
        ];
    }
}

// app/Domain/Order/Order.php (versão atualizada)
class Order
{
    private array $domainEvents = [];

    public function place(): void
    {
        if ($this->items->isEmpty()) {
            throw new \DomainException('Não dá para finalizar pedido vazio');
        }

        $this->status = OrderStatus::placed();
        $this->recordEvent(OrderPlaced::fromOrder($this));
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}

// app/Infrastructure/Persistence/EloquentOrderRepository.php
class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function save(Order $order): void
    {
        // Persistência no banco
        // ...

        // Dispara os domain events
        foreach ($order->releaseEvents() as $event) {
            event($event);
        }
    }
}

// app/Listeners/SendOrderConfirmationEmail.php
class SendOrderConfirmationEmail
{
    public function handle(OrderPlaced $event): void
    {
        $user = User::find($event->userId);
        Mail::to($user->email)->send(new OrderConfirmationMail($event));
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
