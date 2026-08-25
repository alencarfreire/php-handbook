# 5.7 API Resources

## O que é

**O que é:**
API Resources transformam models em JSON. Você controla o que volta na API e em que formato.

**O essencial:**
- Resource — um model
- ResourceCollection — coleção
- Escondem a estrutura interna do banco

---

## Como funciona

**Criar o Resource:**

```bash
# Resource para um model
php artisan make:resource UserResource

# Resource para coleção
php artisan make:resource UserCollection
```

**Resource básico:**

```php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

**Uso no controller:**

```php
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function show(User $user)
    {
        return new UserResource($user);
    }

    public function index()
    {
        $users = User::paginate(20);

        return UserResource::collection($users);
    }
}
```

**Resource com relationships:**

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'body' => $this->body,
            'created_at' => $this->created_at->toISOString(),

            // Autor — só se veio no eager load
            'author' => new UserResource($this->whenLoaded('user')),

            // Comments — só se vieram no eager load
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            // Campo condicional
            'is_editable' => $this->when(
                $request->user()?->can('update', $this->resource),
                true
            ),
        ];
    }
}

// No controller, com eager loading
public function show(Post $post)
{
    return new PostResource($post->load(['user', 'comments']));
}
```

**ResourceCollection:**

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class PostCollection extends ResourceCollection
{
    // Envolver em data
    public $collects = PostResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
            ],
        ];
    }

    // Dados extras
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Posts listados com sucesso',
        ];
    }
}

// Uso
public function index()
{
    $posts = Post::with('user')->paginate(20);

    return new PostCollection($posts);
}
```

---

## Quando usar

**Use API Resources quando:**
- API endpoints
- Precisa esconder campo do model
- Precisa transformar dado
- Campo condicional

**Não use quando:**
- Request interno (entre serviços)
- CRUD simples, sem transformação

---

## Exemplo prático

**Resource completo com lógica condicional:**

```php
namespace App\Http\Resources;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'total' => $this->total,

            // Formatar data
            'created_at' => $this->created_at->toISOString(),
            'created_at_human' => $this->created_at->diffForHumans(),

            // Resources aninhados
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            // Campos condicionais (só dono ou admin)
            $this->mergeWhen($this->isViewableBy($request->user()), [
                'payment_method' => $this->payment_method,
                'billing_address' => $this->billing_address,
                'shipping_address' => $this->shipping_address,
            ]),

            // Campo condicional
            'can_cancel' => $this->when(
                $request->user()?->can('cancel', $this->resource),
                true
            ),

            // Campo computed
            'is_shipped' => $this->status === 'shipped',

            // Dados do pivot
            'pivot' => $this->whenPivotLoaded('order_product', function () {
                return [
                    'quantity' => $this->pivot->quantity,
                    'price' => $this->pivot->price,
                ];
            }),
        ];
    }

    // Metadados extras
    public function with(Request $request): array
    {
        return [
            'links' => [
                'self' => route('orders.show', $this->id),
                'user' => route('users.show', $this->user_id),
            ],
        ];
    }

    private function isViewableBy(?User $user): bool
    {
        return $user && ($user->id === $this->user_id || $user->isAdmin());
    }
}
```

**Nested Resources:**

```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,

            // Coleções aninhadas
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),

            // Contadores
            'posts_count' => $this->when(
                $this->posts_count !== null,
                $this->posts_count
            ),

            // Último post
            'latest_post' => new PostResource($this->whenLoaded('latestPost')),
        ];
    }
}

// Controller
public function show(User $user)
{
    return new UserResource(
        $user->load(['posts', 'latestPost'])
            ->loadCount('posts')
    );
}
```

**Conditional Attributes:**

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,

            // body só para autenticados
            'body' => $this->when($request->user(), $this->body),

            // Só para admin
            $this->mergeWhen($request->user()?->isAdmin(), [
                'views_count' => $this->views,
                'ip_address' => $this->ip_address,
            ]),

            // draft só para o autor
            'draft' => $this->when(
                $request->user()?->id === $this->user_id,
                $this->draft
            ),
        ];
    }
}
```

**Resource com parâmetros:**

```php
class PostResource extends JsonResource
{
    // Passar parâmetros pelo construtor
    public function __construct($resource, private bool $detailed = false)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
        ];

        // Versão detalhada
        if ($this->detailed) {
            $data['body'] = $this->body;
            $data['meta_description'] = $this->meta_description;
            $data['comments'] = CommentResource::collection($this->whenLoaded('comments'));
        }

        return $data;
    }
}

// Uso
return new PostResource($post, detailed: true);
```

**Additional Data in Collections:**

```php
class PostCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl(),
            ],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
            'filters' => [
                'category' => $request->input('category'),
                'search' => $request->input('search'),
            ],
        ];
    }
}
```

**Wrapping and Unwrapping:**

```php
// Trocar o wrap (padrão: 'data')
class PostResource extends JsonResource
{
    public static $wrap = 'post';  // Envolver em 'post'
}

// Ou desligar o wrap
class PostResource extends JsonResource
{
    public static $wrap = null;
}

// Ou global no AppServiceProvider
use Illuminate\Http\Resources\Json\JsonResource;

public function boot(): void
{
    JsonResource::withoutWrapping();  // Desligar para todos
}
```

**Pagination Meta:**

```php
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->paginate(20);

        return PostResource::collection($posts);
    }
}

// Retorna:
{
    "data": [...],
    "links": {
        "first": "http://example.com/api/posts?page=1",
        "last": "http://example.com/api/posts?page=10",
        "prev": null,
        "next": "http://example.com/api/posts?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "last_page": 10,
        "per_page": 20,
        "to": 20,
        "total": 200
    }
}
```

**Response Headers:**

```php
class UserResource extends JsonResource
{
    public function withResponse(Request $request, $response): void
    {
        $response->header('X-User-ID', $this->id);
        $response->header('X-Resource-Type', 'user');
    }
}
```

---

## Na entrevista

> "API Resources transformam models em JSON. Resource para um model, ResourceCollection para coleção. toArray() define a estrutura. whenLoaded() nos relationships — evita N+1. when() para campo condicional. mergeWhen() para um grupo de campos. ResourceCollection::collection() para paginação. with() para metadados extras. withoutWrapping() tira o wrap data. Uso em API endpoints, para esconder campo e transformar dado."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
