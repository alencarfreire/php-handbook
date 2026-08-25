# 16.4 Refatoração de código

## Exemplos de refatoração na entrevista

### Exemplo 1: God Controller

**❌ Antes (ruim):**

```php
class UserController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validação
        if (!$request->has('name') || strlen($request->name) < 3) {
            return back()->withErrors(['name' => 'Nome muito curto']);
        }
        if (!filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['email' => 'Email inválido']);
        }
        if (User::where('email', $request->email)->exists()) {
            return back()->withErrors(['email' => 'Email já cadastrado']);
        }

        // 2. Criar o usuário
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->save();

        // 3. Criar o perfil
        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->avatar = 'default.png';
        $profile->bio = '';
        $profile->save();

        // 4. Enviar email
        $to = $user->email;
        $subject = 'Bem-vindo!';
        $message = "Olá, {$user->name}, bem-vindo ao nosso site!";
        mail($to, $subject, $message);

        // 5. Log
        Log::info("Usuário registrado: {$user->id}");

        // 6. Incrementar o contador
        Cache::increment('users.total');

        return redirect('/dashboard');
    }
}
```

**Problemas:**

```
- Responsabilidades demais
- Sem testes (difícil de testar)
- Lógica duplicada
- Sem reúso
- Quebra o SOLID
```

**✅ Depois (bom):**

```php
// 1. Validação do request
class StoreUserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => 'required|min:3|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ];
    }
}

// 2. Service
class UserService
{
    public function __construct(
        private UserRepository $users,
        private MailService $mail
    ) {}

    public function register(array $data): User
    {
        DB::beginTransaction();

        try {
            // Criar o user
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // Criar o profile
            $user->profile()->create([
                'avatar' => 'default.png',
                'bio' => '',
            ]);

            // Email pela queue
            $this->mail->sendWelcome($user);

            // Event
            event(new UserRegistered($user));

            DB::commit();

            return $user;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// 3. Observer para side effects
class UserObserver
{
    public function created(User $user)
    {
        Log::info("Usuário registrado: {$user->id}");
        Cache::increment('users.total');
    }
}

// 4. Controller (fino)
class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->register($request->validated());

        return redirect('/dashboard');
    }
}
```

**Vantagens:**

```
✓ Responsabilidade única
✓ Código reutilizável
✓ Fácil de testar
✓ Segue SOLID
✓ DRY
```

---

### Exemplo 2: Inferno de if-else aninhado

**❌ Antes:**

```php
public function calculateDiscount(User $user, Order $order)
{
    if ($user->isActive()) {
        if ($user->isVip()) {
            if ($order->total > 100) {
                if ($order->items->count() > 5) {
                    return $order->total * 0.25;
                } else {
                    return $order->total * 0.20;
                }
            } else {
                return $order->total * 0.10;
            }
        } else {
            if ($order->total > 50) {
                return $order->total * 0.05;
            } else {
                return 0;
            }
        }
    } else {
        return 0;
    }
}
```

**✅ Depois (Early Return):**

```php
public function calculateDiscount(User $user, Order $order): float
{
    // Guard clauses
    if (!$user->isActive()) {
        return 0;
    }

    if (!$user->isVip()) {
        return $order->total > 50 ? $order->total * 0.05 : 0;
    }

    // Lógica VIP
    if ($order->total <= 100) {
        return $order->total * 0.10;
    }

    return $order->items->count() > 5
        ? $order->total * 0.25
        : $order->total * 0.20;
}
```

**Ainda melhor (Strategy Pattern):**

```php
interface DiscountStrategy
{
    public function calculate(Order $order): float;
}

class VipLargeOrderDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total * 0.25;
    }
}

class VipRegularOrderDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total * 0.20;
    }
}

class RegularUserDiscount implements DiscountStrategy
{
    public function calculate(Order $order): float
    {
        return $order->total > 50 ? $order->total * 0.05 : 0;
    }
}

class DiscountCalculator
{
    public function calculate(User $user, Order $order): float
    {
        $strategy = $this->resolveStrategy($user, $order);
        return $strategy->calculate($order);
    }

    private function resolveStrategy(User $user, Order $order): DiscountStrategy
    {
        if (!$user->isActive()) {
            return new NoDiscount();
        }

        if ($user->isVip() && $order->total > 100 && $order->items->count() > 5) {
            return new VipLargeOrderDiscount();
        }

        if ($user->isVip() && $order->total > 100) {
            return new VipRegularOrderDiscount();
        }

        return new RegularUserDiscount();
    }
}
```

---

### Exemplo 3: Duplicate Code

**❌ Antes:**

```php
class OrderController extends Controller
{
    public function adminIndex()
    {
        $orders = Order::where('status', 'pending')
            ->with('user', 'items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.orders', compact('orders'));
    }

    public function userOrders()
    {
        $orders = Order::where('status', 'pending')
            ->where('user_id', auth()->id())
            ->with('user', 'items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('user.orders', compact('orders'));
    }

    public function apiOrders()
    {
        $orders = Order::where('status', 'pending')
            ->with('user', 'items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($orders);
    }
}
```

**✅ Depois:**

```php
// Repository
class OrderRepository
{
    public function getPending(?int $userId = null)
    {
        return Order::where('status', 'pending')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->with('user', 'items')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
    }
}

// Controller
class OrderController extends Controller
{
    public function __construct(
        private OrderRepository $orders
    ) {}

    public function adminIndex()
    {
        $orders = $this->orders->getPending();
        return view('admin.orders', compact('orders'));
    }

    public function userOrders()
    {
        $orders = $this->orders->getPending(auth()->id());
        return view('user.orders', compact('orders'));
    }

    public function apiOrders()
    {
        return response()->json($this->orders->getPending());
    }
}
```

---

### Exemplo 4: Long Method

**❌ Antes:**

```php
public function processOrder(Order $order)
{
    // 150 linhas de código...

    // Validação
    if ($order->total < 0) {
        throw new InvalidOrderException();
    }
    if ($order->items->isEmpty()) {
        throw new EmptyOrderException();
    }

    // Checar estoque
    foreach ($order->items as $item) {
        $product = Product::find($item->product_id);
        if ($product->stock < $item->quantity) {
            throw new InsufficientStockException();
        }
    }

    // Pagamento
    $stripe = new \Stripe\StripeClient(config('stripe.key'));
    try {
        $charge = $stripe->charges->create([
            'amount' => $order->total * 100,
            'currency' => 'brl',
            'source' => $order->payment_method_id,
        ]);
    } catch (\Stripe\Exception\CardException $e) {
        throw new PaymentFailedException($e->getMessage());
    }

    // Atualizar estoque
    foreach ($order->items as $item) {
        $product = Product::find($item->product_id);
        $product->stock -= $item->quantity;
        $product->save();
    }

    // Criar o envio
    $shipment = new Shipment();
    $shipment->order_id = $order->id;
    $shipment->address = $order->shipping_address;
    $shipment->save();

    // Enviar emails
    Mail::to($order->user)->send(new OrderConfirmation($order));
    Mail::to('admin@example.com')->send(new NewOrderNotification($order));

    // Atualizar o pedido
    $order->status = 'paid';
    $order->paid_at = now();
    $order->save();

    return $order;
}
```

**✅ Depois:**

```php
class OrderProcessor
{
    public function __construct(
        private OrderValidator $validator,
        private InventoryService $inventory,
        private PaymentService $payment,
        private ShipmentService $shipment,
        private NotificationService $notifications
    ) {}

    public function process(Order $order): Order
    {
        DB::transaction(function () use ($order) {
            $this->validator->validate($order);
            $this->inventory->reserve($order);
            $this->payment->charge($order);
            $this->inventory->deduct($order);
            $this->shipment->create($order);
            $this->updateOrderStatus($order);
            $this->notifications->sendOrderConfirmation($order);
        });

        return $order;
    }

    private function updateOrderStatus(Order $order): void
    {
        $order->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
```

---

### Exemplo 5: Magic Numbers

**❌ Antes:**

```php
if ($user->age >= 18) {
    // ...
}

if ($order->total > 100) {
    $discount = $order->total * 0.10;
}

if ($subscription->type === 1) {
    // Assinatura Pro
}

Cache::remember('products', 3600, fn() => Product::all());
```

**✅ Depois:**

```php
class User extends Model
{
    public const ADULT_AGE = 18;

    public function isAdult(): bool
    {
        return $this->age >= self::ADULT_AGE;
    }
}

class Order extends Model
{
    public const DISCOUNT_THRESHOLD = 100;
    public const DISCOUNT_PERCENTAGE = 0.10;

    public function calculateDiscount(): float
    {
        if ($this->total > self::DISCOUNT_THRESHOLD) {
            return $this->total * self::DISCOUNT_PERCENTAGE;
        }

        return 0;
    }
}

enum SubscriptionType: int
{
    case FREE = 0;
    case PRO = 1;
    case ENTERPRISE = 2;
}

if ($subscription->type === SubscriptionType::PRO) {
    // Assinatura Pro
}

// Config do TTL do cache
Cache::remember('products', config('cache.ttl.products'), fn() => Product::all());
```

---

### Exemplo 6: Primitive Obsession

**❌ Antes:**

```php
class User
{
    public string $email;
}

function sendEmail(string $email)
{
    // Sem validação, dá para passar "invalid-email"
    mail($email, ...);
}
```

**✅ Depois:**

```php
class Email
{
    private string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException();
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

class User
{
    public Email $email;
}

function sendEmail(Email $email)
{
    // Email garantido válido
    mail($email->getValue(), ...);
}
```

---

## Princípios de refatoração

**1. DRY (Don't Repeat Yourself):**

```
Código duplicado → Função/Método/Classe
```

**2. KISS (Keep It Simple, Stupid):**

```
Código complexo → Funções pequenas e simples
```

**3. YAGNI (You Aren't Gonna Need It):**

```
Apague código que não usa
```

**4. Extract Method:**

```
Método longo → Vários métodos curtos
```

**5. Replace Conditional with Polymorphism:**

```
if/else → Strategy Pattern / Inheritance
```

**6. Introduce Parameter Object:**

```
Muitos parâmetros → Objeto
```

---

## Code Smells

**1. Long Method** (> 20 linhas)
**2. Large Class** (> 200 linhas)
**3. Long Parameter List** (> 3-4 parâmetros)
**4. Duplicate Code**
**5. Dead Code** (não usado)
**6. Comments** (no lugar de código legível)
**7. Magic Numbers**
**8. Temporary Field**
**9. Feature Envy** (o método usa mais dados de outra classe)
**10. Data Clumps** (grupos de dados que sempre andam juntos)

---

## Na entrevista

> "Refatoração: God Controller vira Thin Controller + Service + Repository. Nested if-else vira Early Return ou Strategy Pattern. Código duplicado: Extract Method, DRY. Long Method: quebra em métodos pequenos. Magic Numbers: constantes ou enum. Primitive Obsession: Value Objects. Princípios: DRY, KISS, YAGNI, SOLID. Code smells: Long Method, Large Class, Duplicate Code, Dead Code. Refatoração tem que manter o comportamento (testes!)."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
