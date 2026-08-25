# 4.7 Controllers

## Resumo

> **Controllers** — classes que tratam request HTTP. Agrupam a lógica por ação.
>
> **Tipos:** Resource (CRUD), API Resource (sem create/edit), Single Action (__invoke), Nested Resource.
>
> **Importante:** DI no construtor ou no método. Lógica de negócio no Service. Validação no Form Request. Autorização com authorize().

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
Controllers agrupam a lógica de tratar request HTTP. Recebem Request, processam e devolvem Response.

**O essencial:**
- Ficam em `app/Http/Controllers/`
- Métodos batem com as ações (index, show, store, update, destroy)
- Resource controllers para CRUD

---

## Como funciona

**Controller básico:**

```php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();

        return view('users.index', compact('users'));
    }

    public function show(User $user)
    {
        // Route Model Binding
        return view('users.show', compact('user'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
        ]);

        $user = User::create($validated);

        return redirect()->route('users.show', $user);
    }
}
```

**Resource Controller:**

```php
// Criar resource controller
php artisan make:controller UserController --resource

// Gera os métodos:
class UserController extends Controller
{
    public function index()     // GET /users
    public function create()    // GET /users/create
    public function store()     // POST /users
    public function show()      // GET /users/{user}
    public function edit()      // GET /users/{user}/edit
    public function update()    // PUT/PATCH /users/{user}
    public function destroy()   // DELETE /users/{user}
}
```

**API Resource Controller:**

```php
// Sem create/edit (para API)
php artisan make:controller Api/UserController --api

class UserController extends Controller
{
    public function index()     // GET /api/users
    public function store()     // POST /api/users
    public function show()      // GET /api/users/{user}
    public function update()    // PUT/PATCH /api/users/{user}
    public function destroy()   // DELETE /api/users/{user}
}
```

**Dependency Injection no controller:**

```php
class OrderController extends Controller
{
    // Injeção pelo construtor
    public function __construct(
        private OrderService $orderService,
        private PaymentService $paymentService
    ) {}

    // Injeção no método
    public function store(
        CreateOrderRequest $request,
        NotificationService $notificationService
    ) {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated()
        );

        $notificationService->send($request->user(), 'Pedido criado');

        return new OrderResource($order);
    }
}
```

**Middleware no controller:**

```php
class UserController extends Controller
{
    public function __construct()
    {
        // Em todos os métodos
        $this->middleware('auth');

        // Só em alguns
        $this->middleware('role:admin')->only(['destroy', 'update']);

        // Exceto alguns
        $this->middleware('guest')->except(['logout']);
    }
}
```

---

## Quando usar

**Controller serve para:**
- Tratar request HTTP
- Validação (via Form Request)
- Chamar services
- Devolver Response/View/JSON

**NÃO serve para:**
- ❌ Lógica de negócio (vai para o Service)
- ❌ Mexer no banco direto (passa por Repository/Service)
- ❌ Cálculo complexo (fica no Service)

---

## Exemplo prático

**Controller RESTful com service:**

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{CreateOrderRequest, UpdateOrderRequest};
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['product', 'user'])
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->create(
            $request->user(),
            $request->validated()
        );

        return new OrderResource($order);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['product', 'user']));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $order = $this->orderService->update($order, $request->validated());

        return new OrderResource($order);
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $this->orderService->cancel($order);

        return response()->noContent();
    }
}
```

**Single Action Controller (uma ação):**

```php
// app/Http/Controllers/SendNewsletterController.php
class SendNewsletterController extends Controller
{
    public function __invoke(Request $request)
    {
        // Lógica de envio
        Newsletter::send($request->all());

        return response()->json(['message' => 'Newsletter enviada']);
    }
}

// Na rota (sem apontar o método)
Route::post('/newsletter', SendNewsletterController::class);
```

**Invokable Controller para ação complexa:**

```php
// app/Http/Controllers/ExportUsersController.php
class ExportUsersController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate([
            'role' => 'nullable|string',
            'from_date' => 'nullable|date',
        ]);

        $export = new UsersExport($filters);

        return Excel::download($export, 'users.xlsx');
    }
}

// Rota
Route::get('/users/export', ExportUsersController::class)
    ->middleware('role:admin');
```

**Nested Resource Controller:**

```php
// Comentários de posts
class PostCommentController extends Controller
{
    // GET /posts/{post}/comments
    public function index(Post $post)
    {
        return CommentResource::collection(
            $post->comments()->paginate(20)
        );
    }

    // POST /posts/{post}/comments
    public function store(Request $request, Post $post)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return new CommentResource($comment);
    }

    // DELETE /posts/{post}/comments/{comment}
    public function destroy(Post $post, Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}

// Rota
Route::resource('posts.comments', PostCommentController::class)
    ->only(['index', 'store', 'destroy']);
```

**Controller com métodos customizados:**

```php
class PostController extends Controller
{
    // Métodos RESTful
    public function index() { /* ... */ }
    public function show(Post $post) { /* ... */ }

    // Métodos customizados
    public function publish(Post $post)
    {
        $this->authorize('publish', $post);

        $post->update(['published_at' => now()]);

        return redirect()->route('posts.show', $post);
    }

    public function unpublish(Post $post)
    {
        $this->authorize('publish', $post);

        $post->update(['published_at' => null]);

        return redirect()->route('posts.show', $post);
    }
}

// Rotas
Route::resource('posts', PostController::class);
Route::post('/posts/{post}/publish', [PostController::class, 'publish'])
    ->name('posts.publish');
Route::post('/posts/{post}/unpublish', [PostController::class, 'unpublish'])
    ->name('posts.unpublish');
```

**Devolver tipos diferentes de Response:**

```php
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::paginate(20);

        // JSON para API
        if ($request->wantsJson()) {
            return ProductResource::collection($products);
        }

        // View para a web
        return view('products.index', compact('products'));
    }

    public function download(Product $product)
    {
        // Baixar arquivo
        return Storage::download($product->file_path);
    }

    public function export()
    {
        // Stream para arquivo grande
        return response()->streamDownload(function () {
            $products = Product::cursor();

            foreach ($products as $product) {
                echo $product->toJson() . "\n";
            }
        }, 'products.json');
    }
}
```

**Organização dos controllers:**

```
app/Http/Controllers/
├── Api/                      # Controllers de API
│   ├── V1/
│   │   ├── UserController.php
│   │   └── OrderController.php
│   └── V2/
│       └── UserController.php
├── Admin/                    # Admin
│   ├── DashboardController.php
│   ├── UserController.php
│   └── PostController.php
├── Auth/                     # Autenticação
│   ├── LoginController.php
│   ├── RegisterController.php
│   └── ForgotPasswordController.php
├── HomeController.php
├── PostController.php
└── UserController.php
```

**Form Request no controller:**

```php
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        // Validação já passou
        $validated = $request->validated();

        // Criar o pedido
        $order = Order::create([
            'user_id' => $request->user()->id,
            ...$validated,
        ]);

        return new OrderResource($order);
    }
}

// app/Http/Requests/CreateOrderRequest.php
class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ];
    }
}
```

---

## Na entrevista

> "Controllers tratam request HTTP. Resource controller para CRUD (index, show, store, update, destroy), API controller sem create/edit. DI pelo construtor ou pelo método. Lógica de negócio eu coloco no Service, validação no Form Request. authorize() para checar permissão. Single Action Controller com __invoke() para uma ação só. Middleware pelo construtor: $this->middleware()."

---

## Exercícios práticos

### Exercício 1: Nested Resource Controller

**Enunciado:** Implemente o controller de comentários de posts: `POST /posts/{post}/comments`, `GET /posts/{post}/comments`, `DELETE /posts/{post}/comments/{comment}`.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/PostCommentController.php
namespace App\Http\Controllers;

use App\Http\Requests\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\{Post, Comment};
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except('index');
    }

    // GET /posts/{post}/comments
    public function index(Post $post)
    {
        $comments = $post->comments()
            ->with('user')
            ->latest()
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    // POST /posts/{post}/comments
    public function store(CreateCommentRequest $request, Post $post)
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated()['body'],
        ]);

        // Notificar o autor do post
        $post->user->notify(new CommentCreated($comment));

        return new CommentResource($comment->load('user'));
    }

    // DELETE /posts/{post}/comments/{comment}
    public function destroy(Post $post, Comment $comment)
    {
        // Conferir se o comentário pertence ao post
        if ($comment->post_id !== $post->id) {
            abort(404);
        }

        $this->authorize('delete', $comment);

        $comment->delete();

        return response()->noContent();
    }
}

// routes/api.php
Route::resource('posts.comments', PostCommentController::class)
    ->only(['index', 'store', 'destroy']);

// app/Http/Requests/CreateCommentRequest.php
class CreateCommentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => 'required|string|min:3|max:1000',
        ];
    }
}
```
</details>

### Exercício 2: Single Action Controller de export

**Enunciado:** Crie um invokable controller para exportar usuários para Excel, com filtro.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/ExportUsersController.php
namespace App\Http\Controllers;

use App\Exports\UsersExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportUsersController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('export', User::class);

        $filters = $request->validate([
            'role' => 'nullable|string|in:admin,user,moderator',
            'status' => 'nullable|string|in:active,inactive',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'search' => 'nullable|string|max:255',
        ]);

        $export = new UsersExport($filters);

        $filename = 'users_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download($export, $filename);
    }
}

// routes/web.php
Route::get('/admin/users/export', ExportUsersController::class)
    ->middleware(['auth', 'role:admin'])
    ->name('admin.users.export');

// app/Exports/UsersExport.php
namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;

class UsersExport implements FromQuery, WithHeadings
{
    public function __construct(private array $filters) {}

    public function query()
    {
        $query = User::query();

        if (!empty($this->filters['role'])) {
            $query->where('role', $this->filters['role']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['from_date'])) {
            $query->whereDate('created_at', '>=', $this->filters['from_date']);
        }

        if (!empty($this->filters['search'])) {
            $query->where(function($q) {
                $q->where('name', 'like', "%{$this->filters['search']}%")
                  ->orWhere('email', 'like', "%{$this->filters['search']}%");
            });
        }

        return $query->select(['id', 'name', 'email', 'role', 'created_at']);
    }

    public function headings(): array
    {
        return ['ID', 'Nome', 'Email', 'Role', 'Criado em'];
    }
}
```
</details>

### Exercício 3: API Controller com Service Layer

**Enunciado:** Implemente o OrderController. Ele usa OrderService para a lógica de negócio e devolve API Resources.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/Api/OrderController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{CreateOrderRequest, UpdateOrderRequest};
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function store(CreateOrderRequest $request)
    {
        $order = $this->orderService->createOrder(
            $request->user(),
            $request->validated()
        );

        return new OrderResource($order->load('items.product'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load('items.product'));
    }

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $order = $this->orderService->updateOrder($order, $request->validated());

        return new OrderResource($order);
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $this->orderService->cancelOrder($order);

        return response()->noContent();
    }
}

// app/Services/OrderService.php
namespace App\Services;

use App\Models\{User, Order};
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            $order = $user->orders()->create([
                'status' => 'pending',
                'total' => 0,
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                $total += $product->price * $item['quantity'];
            }

            $order->update(['total' => $total]);

            // Enviar notificação
            $user->notify(new OrderCreated($order));

            return $order;
        });
    }

    public function cancelOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'cancelled']);

            // Devolver os produtos ao estoque
            foreach ($order->items as $item) {
                $item->product->increment('stock', $item->quantity);
            }
        });
    }
}

// app/Http/Requests/CreateOrderRequest.php
class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
