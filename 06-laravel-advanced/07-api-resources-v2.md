# 5.7 API Resources

## Resumo

> **API Resources** — camada que transforma models Eloquent em JSON para a API. Você controla a estrutura da response, esconde campo interno e adiciona dado computed.
>
> `Resource` para um model, `ResourceCollection` para coleção.
>
> Use `whenLoaded()` nas relationships, `when()` em campo condicional.

---

## Conteúdo

- [Fundamentos](#fundamentos)
- [Resource vs ResourceCollection](#resource-vs-resourcecollection)
- [Trabalhando com relationships](#trabalhando-com-relationships)
- [Campos condicionais](#campos-condicionais)
- [Paginação](#paginação)
- [Erros comuns](#erros-comuns)
- [Boas práticas](#boas-práticas)
- [Comparação de abordagens](#comparação-de-abordagens)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## Fundamentos

### O que é

**API Resource** — classe que define como o model Eloquent vira JSON na response da API.

### Para que servem?

| Problema | Solução com Resources |
|----------|-------------------|
| Devolve todos os campos do model (incluindo senha) | Você controla quais campos entram |
| Estrutura do banco = estrutura da API | API independente do banco |
| Lógica de transformação duplicada | Transformação num lugar só |
| Computed field é chato de adicionar | Qualquer campo entra fácil |

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

**Uso no controller:**

```php
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

## Resource vs ResourceCollection

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

## Trabalhando com relationships

### whenLoaded() — evita N+1

> **Importante:** Sempre use `whenLoaded()` nas relationships. Sem isso, você cai no N+1.

```php
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,

            // Carrega SÓ se veio no eager load
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

**No controller: eager loading**

```php
public function show(Post $post)
{
    return new PostResource(
        $post->load(['user', 'comments'])
    );
}
```

---

## Campos condicionais

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

            // Grupo de campos só para o dono ou admin
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

## Paginação

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

## Erros comuns

### Erro 1: problema N+1

**Errado:**

```php
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

**Certo:**

```php
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

### Erro 2: devolver o model em vez do Resource

**Errado:**

```php
public function show(User $user)
{
    return $user; // Mostra TODOS os campos, inclusive password_hash!
}
```

**Certo:**

```php
public function show(User $user)
{
    return new UserResource($user);
}
```

### Erro 3: não checar se a relationship carregou

**Errado:**

```php
'comments_count' => $this->comments_count
```

**Certo:**

```php
'comments_count' => $this->when(
    isset($this->comments_count),
    $this->comments_count
)
```

---

## Boas práticas

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

## Comparação de abordagens

| Abordagem | Prós | Contras | Quando usar |
|--------|-------|--------|-------------------|
| **Model direto** | Simples | Mostra TUDO, sem controle | API interna, protótipo |
| **Array na mão** | Flexível | Código duplicado | Caso pontual |
| **Resource** | Reuso, código limpo | Um pouco mais de código | Qualquer API pública |
| **DTO + Resource** | Tipagem máxima | Mais código | Projeto enterprise |

---

## Na entrevista

### Resposta estruturada

**O que é:**
- API Resources transformam models Eloquent em JSON para a API
- Resource para um model, ResourceCollection para coleção

**Métodos principais:**
- `toArray()` — define a estrutura do JSON
- `whenLoaded()` — carrega a relationship só se veio no eager load (evita N+1)
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

**Boas práticas:**
- Sempre `whenLoaded()` nas relationships
- Campo condicional para dado sensível
- Campo computed para regra de negócio
- Versionar Resources na API v1, v2

---

## Exercícios práticos

### Exercício 1: Resource com campos condicionais

**Enunciado:** Crie um `ArticleResource` que mostra `draft_content` só para o autor do artigo, e `views_count` só para admin.

<details>
<summary>Solução</summary>

```php
class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,

            // Só para o autor
            'draft_content' => $this->when(
                $request->user()?->id === $this->author_id,
                $this->draft_content
            ),

            // Só para admin
            'views_count' => $this->when(
                $request->user()?->isAdmin(),
                $this->views_count
            ),
        ];
    }
}
```
</details>

### Exercício 2: Corrija o N+1

**Enunciado:** O que está errado neste código? Corrija.

```php
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'author' => new AuthorResource($this->author),
            'reviews' => ReviewResource::collection($this->reviews),
        ];
    }
}
```

<details>
<summary>Solução</summary>

```php
class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'author' => new AuthorResource($this->whenLoaded('author')),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}

// No controller
$books = Book::with(['author', 'reviews'])->get();
return BookResource::collection($books);
```
</details>

### Exercício 3: Adicione um campo computed

**Enunciado:** Adicione em `ProductResource` o campo computed `discount_percentage`: `(original_price - price) / original_price * 100`.

<details>
<summary>Solução</summary>

```php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'discount_percentage' => $this->getDiscountPercentage(),
        ];
    }

    private function getDiscountPercentage(): ?float
    {
        if (!$this->original_price || $this->original_price <= $this->price) {
            return null;
        }

        return round(
            ($this->original_price - $this->price) / $this->original_price * 100,
            2
        );
    }
}
```
</details>

---

## Materiais extras

**Documentação oficial do Laravel:**
- [API Resources](https://laravel.com/docs/eloquent-resources)

**Temas relacionados:**
- [5.1 Eloquent Relationships](./01-eloquent-relationships.md)
- [5.2 Query Builder](./02-query-builder.md)

---

## Ajuda na preparação

Precisa de ajuda para a entrevista?

O **CodeMate** ajuda:
- Mock interview de Laravel
- Revisão de perguntas reais
- Code review dos seus projetos

[Agendar uma consultoria](https://codemate.team/consultation)

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
