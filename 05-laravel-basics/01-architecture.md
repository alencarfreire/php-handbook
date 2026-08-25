# 4.1 Arquitetura do Laravel

## Resumo

> **Laravel** — framework MVC com arquitetura em Service Container (container de serviços) e Service Providers.
>
> **Request Lifecycle:** index.php → Bootstrap → Kernel → Service Providers → Router → Middleware → Controller → Model → View → Response.
>
> **Importante:** Service Container para DI, Facades para acesso estático aos serviços.

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
Laravel é um framework MVC. A arquitetura gira em torno do Service Container e dos Service Providers. Componentes principais: Router → Middleware → Controller → Model → View.

**Princípios principais:**
- **MVC** (Model-View-Controller)
- **Service Container** (container IoC para DI)
- **Facades** (interface estática para os serviços)
- **Request Lifecycle** (ciclo de vida do request)

---

## Como funciona

**Request Lifecycle:**

```
1. public/index.php (ponto de entrada)
   ↓
2. Bootstrap (carrega o framework)
   ↓
3. Kernel (HTTP Kernel)
   ↓
4. Service Providers (registra os serviços)
   ↓
5. Router (roteamento)
   ↓
6. Middleware (processa o request)
   ↓
7. Controller (lógica de negócio)
   ↓
8. Model (trabalha com os dados)
   ↓
9. View (renderiza)
   ↓
10. Response (resposta para o cliente)
```

**Estrutura de pastas:**

```
app/
├── Console/          # Comandos Artisan
├── Exceptions/       # Tratamento de exceções
├── Http/
│   ├── Controllers/  # Controllers
│   ├── Middleware/   # Middleware
│   └── Requests/     # Form Requests
├── Models/           # Models Eloquent
├── Providers/        # Service Providers
└── Services/         # Lógica de negócio

bootstrap/           # Bootstrap do framework
config/             # Configuração
database/
├── migrations/     # Migrations
├── seeders/        # Seeders
└── factories/      # Factories

public/             # Arquivos públicos
├── index.php       # Ponto de entrada

resources/
├── views/          # Templates Blade
└── js/             # Assets de frontend

routes/
├── web.php         # Rotas web
├── api.php         # Rotas de API
└── console.php     # Comandos Artisan

storage/            # Logs, cache, sessões
tests/              # Testes
vendor/             # Dependências do Composer
```

**MVC no Laravel:**

```php
// Model (app/Models/User.php)
class User extends Model
{
    protected $fillable = ['name', 'email'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Controller (app/Http/Controllers/UserController.php)
class UserController extends Controller
{
    public function show(User $user)
    {
        return view('users.show', [
            'user' => $user,
            'posts' => $user->posts
        ]);
    }
}

// View (resources/views/users/show.blade.php)
<h1>{{ $user->name }}</h1>
<p>{{ $user->email }}</p>

@foreach ($posts as $post)
    <article>{{ $post->title }}</article>
@endforeach

// Route (routes/web.php)
Route::get('/users/{user}', [UserController::class, 'show']);
```

**Service Container (IoC):**

```php
// Registro no AppServiceProvider
public function register(): void
{
    $this->app->singleton(PaymentService::class, function ($app) {
        return new PaymentService(
            $app->make(PaymentGateway::class)
        );
    });
}

// Injeção automática no controller
class OrderController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function store(Request $request)
    {
        // $paymentService injetado automaticamente
        $this->paymentService->charge($request->amount);
    }
}
```

---

## Quando usar

**Use o Laravel quando:**
- Precisa de um framework completo (não microframework)
- Projeto de tamanho médio/grande
- Velocidade de desenvolvimento importa
- Precisa de ORM (Eloquent), rotas e middleware prontos
- O time conhece Laravel

**NÃO use quando:**
- Microsserviço com dependências mínimas (Lumen, Slim)
- Projeto de alta carga que exige performance máxima (componentes Symfony, RoadRunner)
- Projeto legado em outro framework

---

## Exemplo prático

**Arquitetura típica do app:**

```php
// 1. Route (routes/api.php)
Route::post('/orders', [OrderController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:60,1']);

// 2. Middleware (app/Http/Middleware/Authenticate.php)
class Authenticate extends Middleware
{
    protected function redirectTo($request)
    {
        if (!$request->expectsJson()) {
            return route('login');
        }
    }
}

// 3. Controller (app/Http/Controllers/OrderController.php)
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated()
        );

        return new OrderResource($order);
    }
}

// 4. Form Request (app/Http/Requests/CreateOrderRequest.php)
class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ];
    }
}

// 5. Service (app/Services/OrderService.php)
class OrderService
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService
    ) {}

    public function create(User $user, array $data): Order
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->id,
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'total' => $this->calculateTotal($data),
            ]);

            $this->paymentService->charge($order);
            $this->notificationService->sendOrderConfirmation($order);

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// 6. Model (app/Models/Order.php)
class Order extends Model
{
    protected $fillable = ['user_id', 'product_id', 'quantity', 'total'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

// 7. Resource (app/Http/Resources/OrderResource.php)
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'quantity' => $this->quantity,
            'total' => $this->total,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

**Service Provider para inicialização:**

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Registro dos serviços
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                config('services.payment.key'),
                $app->make(HttpClient::class)
            );
        });
    }

    public function boot(): void
    {
        // Validators, macros, event listeners
        Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^\+55\d{10,11}$/', $value);
        });

        // Model observers
        Order::observe(OrderObserver::class);
    }
}
```

**Lifecycle em detalhe:**

```php
// public/index.php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// app/Http/Kernel.php
class Kernel extends HttpKernel
{
    // Middleware global (roda sempre)
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
    ];

    // Grupos de middleware (por nome)
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        ],

        'api' => [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];
}
```

---

## Na entrevista

> "O Laravel usa arquitetura MVC. Request lifecycle: index.php → Bootstrap → Kernel → Service Providers → Router → Middleware → Controller → Model → View → Response. Service Container (IoC) para DI. Estrutura: app/ (código), routes/ (rotas), resources/views/ (templates), database/ (migrations). Nos projetos eu uso camada de Service para a lógica de negócio, Form Requests para validação, Resources para respostas de API."

---

## Exercícios práticos

### Exercício 1: Explique o Request Lifecycle

**Enunciado:** Você tem a rota `POST /api/orders`. Descreva o caminho completo do request, de `index.php` até a resposta para o cliente.

<details>
<summary>Solução</summary>

```
1. public/index.php
   - Ponto de entrada, carrega o autoloader do Composer
   - Cria a instância de Application

2. bootstrap/app.php
   - Cria o Service Container
   - Registra o kernel (HTTP Kernel)

3. app/Http/Kernel.php
   - Carrega a stack de middleware
   - Middleware global (TrustProxies, HandleCors)

4. Service Providers (config/app.php)
   - AppServiceProvider::register()
   - RouteServiceProvider::register()
   - EventServiceProvider::register()
   - ...boot() de todos os providers

5. Router (routes/api.php)
   - Encontra a rota POST /api/orders
   - Aplica o grupo de route middleware 'api'

6. Middleware do grupo 'api'
   - throttle:60,1 (rate limiting)
   - SubstituteBindings (route model binding)
   - Authenticate (se tiver auth:sanctum)

7. Controller (OrderController::store)
   - Dependency Injection (OrderService)
   - Validação via Form Request (CreateOrderRequest)

8. Service Layer (OrderService::create)
   - Lógica de negócio
   - Transações de DB
   - Event dispatching

9. Model (Order::create)
   - Eloquent ORM
   - Query no banco

10. Response
    - API Resource (OrderResource)
    - JSON serialization
    - HTTP Response

11. Middleware (na ordem inversa)
    - Processamento final do Response

12. Cliente
    - Resposta JSON
```
</details>

### Exercício 2: Organize a estrutura para uma feature nova

**Enunciado:** Precisa da feature "Exportar pedidos em PDF". Quais arquivos/classes você cria e onde?

<details>
<summary>Solução</summary>

```php
// 1. Route (routes/api.php)
Route::get('/orders/export', [OrderExportController::class, 'export'])
    ->middleware(['auth:sanctum', 'throttle:10,1']);

// 2. Controller (app/Http/Controllers/OrderExportController.php)
class OrderExportController extends Controller
{
    public function __construct(
        private OrderExportService $exportService
    ) {}

    public function export(Request $request)
    {
        $pdf = $this->exportService->exportToPdf(
            $request->user(),
            $request->validated()
        );

        return response()->download($pdf, 'orders.pdf');
    }
}

// 3. Form Request (app/Http/Requests/ExportOrdersRequest.php)
class ExportOrdersRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:pending,completed,cancelled',
        ];
    }
}

// 4. Service (app/Services/OrderExportService.php)
class OrderExportService
{
    public function __construct(
        private PdfGenerator $pdfGenerator
    ) {}

    public function exportToPdf(User $user, array $filters): string
    {
        $orders = Order::where('user_id', $user->id)
            ->whereBetween('created_at', [$filters['start_date'], $filters['end_date']])
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->with(['items.product'])
            ->get();

        return $this->pdfGenerator->generate('exports.orders', [
            'orders' => $orders,
            'user' => $user,
        ]);
    }
}

// 5. PDF Generator (app/Services/PdfGenerator.php)
class PdfGenerator
{
    public function generate(string $view, array $data): string
    {
        $pdf = PDF::loadView($view, $data);
        $filename = storage_path('app/exports/' . Str::uuid() . '.pdf');
        $pdf->save($filename);
        return $filename;
    }
}

// 6. View (resources/views/exports/orders.blade.php)
<!DOCTYPE html>
<html>
<body>
    <h1>Pedidos de {{ $user->name }}</h1>
    @foreach($orders as $order)
        <div>
            Pedido #{{ $order->id }} - {{ $order->total }}
        </div>
    @endforeach
</body>
</html>

// 7. Service Provider (app/Providers/AppServiceProvider.php)
public function register(): void
{
    $this->app->singleton(PdfGenerator::class);
}

// 8. Test (tests/Feature/OrderExportTest.php)
public function test_user_can_export_orders_to_pdf()
{
    $user = User::factory()->create();
    Order::factory()->count(5)->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/orders/export', [
        'start_date' => now()->subDays(30),
        'end_date' => now(),
    ]);

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
}
```

**Estrutura de pastas:**
```
app/
├── Http/
│   ├── Controllers/
│   │   └── OrderExportController.php
│   └── Requests/
│       └── ExportOrdersRequest.php
├── Services/
│   ├── OrderExportService.php
│   └── PdfGenerator.php
resources/
└── views/
    └── exports/
        └── orders.blade.php
tests/
└── Feature/
    └── OrderExportTest.php
```
</details>

### Exercício 3: Qual pattern é melhor?

**Enunciado:** Você tem um controller com 10 métodos e 500 linhas. Como refatorar?

<details>
<summary>Solução</summary>

**Problema:**
```php
// ❌ RUIM: Fat Controller
class OrderController extends Controller
{
    public function store(Request $request)
    {
        // 50 linhas de validação
        // 100 linhas de lógica de negócio
        // 50 linhas de envio de notificações
        // 30 linhas de log
    }
}
```

**Solução 1: Service Layer (recomendado)**
```php
// ✅ BOM: Controller fino + Service
class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated()
        );

        return new OrderResource($order);
    }
}

// Service para a lógica de negócio
class OrderService
{
    public function __construct(
        private PaymentService $paymentService,
        private NotificationService $notificationService
    ) {}

    public function create(User $user, array $data): Order
    {
        DB::beginTransaction();
        try {
            $order = Order::create([...]);
            $this->paymentService->charge($order);
            $this->notificationService->sendOrderConfirmation($order);
            DB::commit();
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**Solução 2: Action Pattern (para operações complexas)**
```php
// Single Action Controller
class CreateOrderAction extends Controller
{
    public function __invoke(
        CreateOrderRequest $request,
        OrderService $orderService
    ) {
        $order = $orderService->create(
            $request->user(),
            $request->validated()
        );

        return new OrderResource($order);
    }
}

// Route
Route::post('/orders', CreateOrderAction::class);
```

**Solução 3: Repository Pattern (para queries complexas)**
```php
interface OrderRepository
{
    public function create(array $data): Order;
    public function findByUser(User $user): Collection;
}

class EloquentOrderRepository implements OrderRepository
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findByUser(User $user): Collection
    {
        return Order::where('user_id', $user->id)
            ->with(['items.product'])
            ->get();
    }
}
```

**Estrutura final:**
```
app/
├── Http/
│   ├── Controllers/
│   │   └── OrderController.php (fino)
│   └── Requests/
│       └── CreateOrderRequest.php
├── Services/
│   ├── OrderService.php (lógica de negócio)
│   ├── PaymentService.php
│   └── NotificationService.php
├── Repositories/
│   ├── OrderRepository.php (interface)
│   └── EloquentOrderRepository.php (implementação)
└── Http/Resources/
    └── OrderResource.php
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
