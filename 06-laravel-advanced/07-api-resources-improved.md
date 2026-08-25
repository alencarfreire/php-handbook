# 5.7 API Resources

> **TL;DR:** API Resources — camada que transforma models Eloquent em JSON. Controlam a estrutura da response, escondem campo interno, adicionam dados computed. `Resource` para um model, `ResourceCollection` para coleção. `whenLoaded()` nos relationships, `when()` para campo condicional.

---

## 📚 Conteúdo

- [Fundamentos](#fundamentos)
- [Resource vs ResourceCollection](#resource-vs-resourcecollection)
- [Relacionamentos](#relacionamentos)
- [Campos condicionais](#campos-condicionais)
- [Paginação](#paginação)
- [Erros comuns](#erros-comuns)
- [Best Practices](#best-practices)

---

## 🎯 Fundamentos

### O que é

**API Resource** — classe que define como o model Eloquent vira JSON na response da API.

### Pra que serve?

| Problema | Com Resources |
|----------|-------------------|
| Devolve todos os campos do model (incluindo senha) | Você escolhe o que aparece |
| Estrutura do banco = estrutura da API | API independente do banco |
| Lógica de transformação duplicada | Transformação num lugar só |
| Computed field é trabalhoso | Qualquer campo, sem drama |

### Criar

```bash
# Resource para um model
php artisan make:resource UserResource

# ResourceCollection
php artisan make:resource UserCollection --collection
```

### Exemplo básico

```php
// app/Http/Resources/UserResource.php
namespace App\Http\Resources;

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

```php
// No controller
use App\Http\Resources\UserResource;

class UserController extends Controller
{
    public function show(User $user)
    {
        return new UserResource($user);
    }
}
```

**Response da API:**
```json
{
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "joao@email.com",
    "created_at": "2024-01-15T10:30:00.000000Z"
  }
}
```

---

## 📦 Resource vs ResourceCollection

### Quando usar cada um?

| Tipo | Uso | Método |
|-----|--------------|-------|
| **Resource** | Um model | `new UserResource($user)` |
| **Resource::collection()** | Coleção (simples) | `UserResource::collection($users)` |
| **ResourceCollection** | Coleção (customizada) | `new UserCollection($users)` |

### Resource para coleção

```php
class UserController extends Controller
{
    public function index()
    {
        $users = User::paginate(20);

        // Coleção automática
        return UserResource::collection($users);
    }
}
```

### ResourceCollection customizada

```php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
            ],
            'summary' => [
                'active_users' => $this->collection->where('active', true)->count(),
            ],
        ];
    }
}
```

---

## 🔗 Relacionamentos

### whenLoaded() — evita N+1

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,

            // Só entra se veio no eager load
            'author' => new UserResource($this->whenLoaded('user')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),

            // Contador
            'comments_count' => $this->when(
                isset($this->comments_count),
                $this->comments_count
            ),
        ];
    }
}
```

```php
// No controller: eager loading
public function show(Post $post)
{
    return new PostResource(
        $post->load(['user', 'comments'])
    );
}
```

---

## 🎭 Campos condicionais

### when() — um campo

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,

            // Só para autenticados
            'body' => $this->when(
                $request->user(),
                $this->body
            ),

            // Só para o dono
            'draft' => $this->when(
                $request->user()?->id === $this->user_id,
                $this->draft
            ),
        ];
    }
}
```

### mergeWhen() — grupo de campos

```php
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,

            // Grupo de campos só para dono ou admin
            $this->mergeWhen($this->canView($request->user()), [
                'payment_method' => $this->payment_method,
                'billing_address' => $this->billing_address,
                'shipping_address' => $this->shipping_address,
            ]),
        ];
    }

    private function canView(?User $user): bool
    {
        return $user && (
            $user->id === $this->user_id ||
            $user->isAdmin()
        );
    }
}
```

---

## 📄 Paginação

### Paginação automática

```php
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->paginate(20);

        return PostResource::collection($posts);
    }
}
```

**Response:**
```json
{
  "data": [...],
  "links": {
    "first": "http://api.com/posts?page=1",
    "last": "http://api.com/posts?page=10",
    "prev": null,
    "next": "http://api.com/posts?page=2"
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

### Metadados extras

```php
class PostResource extends JsonResource
{
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'links' => [
                'self' => route('posts.show', $this->id),
                'author' => route('users.show', $this->user_id),
            ],
        ];
    }
}
```

---

## ⚠️ Erros comuns

### ❌ Erro 1: N+1

```php
// RUIM: N+1 query
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'author' => new UserResource($this->user), // N+1!
        ];
    }
}
```

```php
// BOM: whenLoaded
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'author' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

// No controller: eager loading
$posts = Post::with('user')->get();
```

### ❌ Erro 2: devolver o model em vez do Resource

```php
// RUIM: devolve o model direto
public function show(User $user)
{
    return $user; // Expõe TODOS os campos, inclusive password_hash!
}

// BOM: via Resource
public function show(User $user)
{
    return new UserResource($user);
}
```

### ❌ Erro 3: não checar se o relationship veio no eager load

```php
// RUIM
'comments_count' => $this->comments_count

// BOM
'comments_count' => $this->when(
    isset($this->comments_count),
    $this->comments_count
)
```

---

## ✅ Best Practices

### 1. Tirar o wrap "data" (opcional)

```php
// AppServiceProvider
use Illuminate\Http\Resources\Json\JsonResource;

public function boot(): void
{
    JsonResource::withoutWrapping();
}
```

**Antes:**
```json
{"data": {"id": 1, "name": "João"}}
```

**Depois:**
```json
{"id": 1, "name": "João"}
```

### 2. Wrap customizado

```php
class PostResource extends JsonResource
{
    public static $wrap = 'post'; // Envolve em 'post'
}
```

### 3. Campos computed

```php
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'total' => $this->total,

            // Campos computed
            'status_label' => $this->getStatusLabel(),
            'is_shipped' => $this->status === 'shipped',
            'can_cancel' => $this->canBeCancelled(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Em processamento',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            default => 'Desconhecido',
        };
    }
}
```

### 4. Versionamento da API

```php
// v1/UserResource.php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

// v2/UserResource.php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->name, // Renomeamos
            'avatar_url' => $this->avatar, // Campo novo
        ];
    }
}
```

---

## 📊 Comparando as abordagens

| Abordagem | Prós | Contras | Quando usar |
|--------|-------|--------|-------------------|
| **Model direto** | Simples | Expõe TUDO, sem controle | API interna, protótipo |
| **Array na mão** | Flexível | Duplica código | Caso pontual |
| **Resource** | Reuso, código limpo | Um pouco mais de código | API pública |
| **DTO + Resource** | Tipagem máxima | Mais código | Projeto enterprise |

---

## 🎓 Na entrevista

**Resposta estruturada:**

**O que é:**
- API Resources transformam models Eloquent em JSON
- Resource para um model, ResourceCollection para coleção

**Métodos principais:**
- `toArray()` — define a estrutura do JSON
- `whenLoaded()` — relationship só se veio no eager load (evita N+1)
- `when()` — campo condicional
- `mergeWhen()` — grupo de campos condicionais
- `with()` — metadados extras

**Uso:**
```php
// Um model
return new UserResource($user);

// Coleção
return UserResource::collection($users);

// Paginação (automática)
return UserResource::collection(User::paginate(20));
```

**Best practices:**
- Sempre `whenLoaded()` nos relationships
- Campo condicional para dado sensível
- Campo computed para regra de negócio
- Versionar Resources na API v1, v2

---

## 🚀 Próximo passo

Estudou API Resources? Agora pratica:

✅ Resource com relationships aninhados
✅ Campos condicionais por role
✅ Paginação com metadados customizados

**Quer ajuda pra entrevista?**

**[CodeMate](https://codemate.team)** ajuda com:
- 🎯 Mock interview de Laravel
- 💬 Perguntas reais, destrinchadas
- 📝 Code review dos seus projetos

**[Agendar consultoria →](https://codemate.team/consultation)**

---

<sub>📚 Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)</sub>
