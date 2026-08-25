# 6.6 Problema N+1

## Resumo

> **Problema N+1** — 1 query para a lista + N queries para cada item. Problema clássico de performance.
>
> **Solução:** Eager Loading com `with()`. Aninhado: `with('posts.comments')`. Contadores: `withCount('posts')`.
>
> **Importante:** `preventLazyLoading()` em development detecta N+1. Laravel Debugbar/Telescope para monitorar.

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
N+1 é um problema clássico de performance: 1 query para a lista + N queries para cada item.

**Exemplo:**
- 1 query: buscar todos os usuários
- N queries: buscar os posts de cada usuário

---

## Como funciona

**❌ Problema N+1:**

```php
// 1 query: buscar os usuários
$users = User::all();  // SELECT * FROM users

// N queries: posts de cada usuário
foreach ($users as $user) {
    echo $user->posts->count();  // SELECT * FROM posts WHERE user_id = 1
                                 // SELECT * FROM posts WHERE user_id = 2
                                 // SELECT * FROM posts WHERE user_id = 3
                                 // ...
}

// Total: 1 + N queries (100 usuários = 101 queries!)
```

**✅ Solução: Eager Loading:**

```php
// 2 queries: usuários + todos os posts deles
$users = User::with('posts')->get();
// SELECT * FROM users
// SELECT * FROM posts WHERE user_id IN (1, 2, 3, ...)

foreach ($users as $user) {
    echo $user->posts->count();  // Sem query extra (já veio no eager load)
}

// Total: 2 queries (não importa quantos usuários)
```

---

## Quando usar

**Use Eager Loading quando:**
- Você itera a coleção e acessa relationships
- Você monta uma lista com dados aninhados

**Não use quando:**
- A relationship nem sempre é necessária
- Conditional loading (carrega só sob condição)

---

## Exemplo prático

**Nested Eager Loading:**

```php
// ❌ N+1 em três níveis
$users = User::all();

foreach ($users as $user) {
    foreach ($user->posts as $post) {  // N queries
        foreach ($post->comments as $comment) {  // N * M queries
            echo $comment->body;
        }
    }
}

// ✅ Eager Loading aninhado
$users = User::with(['posts.comments'])->get();
// SELECT * FROM users
// SELECT * FROM posts WHERE user_id IN (...)
// SELECT * FROM comments WHERE post_id IN (...)
```

**Conditional Eager Loading:**

```php
// Carregar só posts publicados
$users = User::with(['posts' => function ($query) {
    $query->where('published', true)
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();
```

**Lazy Eager Loading (carregar depois):**

```php
$users = User::all();

// Depois precisou dos posts
if ($needPosts) {
    $users->load('posts');  // Carrega agora
}

// Carrega só se ainda não estiver carregado
$users->loadMissing('posts');
```

**Counting Related Models:**

```php
// ❌ N+1 (COUNT por usuário)
$users = User::all();

foreach ($users as $user) {
    echo $user->posts()->count();  // SELECT COUNT(*) FROM posts WHERE user_id = 1
}

// ✅ withCount (1 query com LEFT JOIN e COUNT)
$users = User::withCount('posts')->get();

foreach ($users as $user) {
    echo $user->posts_count;  // Sem query extra
}
```

**Exists Queries:**

```php
// ❌ N+1
$users = User::all();

foreach ($users as $user) {
    if ($user->posts()->exists()) {  // SELECT EXISTS(...)
        echo "Tem posts";
    }
}

// ✅ whereHas (1 query)
$users = User::whereHas('posts')->get();

// Ou com condição
$users = User::whereHas('posts', function ($query) {
    $query->where('published', true);
})->get();
```

**Polymorphic Relations:**

```php
// ❌ N+1 com morphTo
$comments = Comment::all();

foreach ($comments as $comment) {
    echo $comment->commentable->title;  // N queries em tabelas diferentes
}

// ✅ with no polymorphic
$comments = Comment::with('commentable')->get();
```

**BelongsToMany with Pivot:**

```php
// ❌ N+1 nos dados do pivot
$users = User::all();

foreach ($users as $user) {
    foreach ($user->roles as $role) {
        echo $role->pivot->expires_at;  // Pivot já veio, OK
    }
}

// ✅ Eager load com pivot
$users = User::with(['roles' => function ($query) {
    $query->withPivot('expires_at', 'is_active');
}])->get();
```

**API Resource com Relationships:**

```php
// ❌ N+1 no Resource
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => new UserResource($this->user),  // N queries
            'comments_count' => $this->comments()->count(),  // N queries
        ];
    }
}

// Controller
public function index()
{
    $posts = Post::paginate(20);
    return PostResource::collection($posts);  // N+1!
}

// ✅ Eager load no controller
public function index()
{
    $posts = Post::with('user')
        ->withCount('comments')
        ->paginate(20);

    return PostResource::collection($posts);
}

// Resource
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => new UserResource($this->whenLoaded('user')),
            'comments_count' => $this->when(
                $this->comments_count !== null,
                $this->comments_count
            ),
        ];
    }
}
```

**Debugging N+1:**

```php
// 1. Laravel Debugbar
// Mostra todas as queries e as duplicatas

// 2. Telescope
// A aba Queries mostra todas as queries

// 3. QueryLog
DB::enableQueryLog();

User::all()->each(function ($user) {
    echo $user->posts->count();
});

dd(DB::getQueryLog());

// 4. Package: beyondcode/laravel-query-detector
// Detecta N+1 sozinho em development

// 5. Prevent Lazy Loading (Laravel 8.43+)
// No AppServiceProvider
use Illuminate\Database\Eloquent\Model;

public function boot(): void
{
    Model::preventLazyLoading(! app()->isProduction());
}

// Agora lazy loading lança exceção
```

**Global Scopes para Eager Loading:**

```php
// Carregar a relationship automaticamente
class Post extends Model
{
    protected $with = ['user', 'category'];

    // Agora Post::all() já traz user e category
}

// Desligar em uma query específica
$posts = Post::without('user')->get();
```

**Subquery Select (alternativa ao withCount):**

```php
// Colocar subquery no SELECT
$users = User::select([
    'users.*',
    'posts_count' => Post::selectRaw('COUNT(*)')
        ->whereColumn('posts.user_id', 'users.id')
])->get();

// Equivale ao withCount, mas com mais controle
$users = User::addSelect([
    'latest_post_created_at' => Post::select('created_at')
        ->whereColumn('posts.user_id', 'users.id')
        ->latest()
        ->limit(1)
])->get();
```

---

## Na entrevista

> "N+1 é 1 query para a lista + N queries para cada item. Solução: Eager Loading com with(). with('posts.comments') para aninhado. withCount('posts') para contador sem carregar os dados. whereHas() para filtrar pela relationship. load() para carregar depois. preventLazyLoading() em development detecta N+1. Laravel Debugbar/Telescope para monitorar. whenLoaded() no API Resource para carga condicional."

---

## Exercícios práticos

### Exercício 1: Encontre e corrija o N+1

O que está errado neste código? Quantas queries vão rodar?

```php
public function index()
{
    $posts = Post::where('published', true)->get();

    return view('posts.index', compact('posts'));
}

// Blade view
@foreach ($posts as $post)
    <h2>{{ $post->title }}</h2>
    <p>Autor: {{ $post->user->name }}</p>
    <p>Categoria: {{ $post->category->name }}</p>
    <p>Comentários: {{ $post->comments->count() }}</p>
@endforeach
```

<details>
<summary>Solução</summary>

```php
// ❌ Problema: 1 + 3N queries
// 1 query: SELECT * FROM posts WHERE published = 1
// N queries: SELECT * FROM users WHERE id = ? (para cada post)
// N queries: SELECT * FROM categories WHERE id = ? (para cada post)
// N queries: SELECT * FROM comments WHERE post_id = ? (para cada post)

// 100 posts = 1 + 300 = 301 queries!

// ✅ Solução: Eager Loading
public function index()
{
    $posts = Post::where('published', true)
        ->with(['user', 'category'])  // Carrega user e category
        ->withCount('comments')  // Conta os comentários
        ->get();

    return view('posts.index', compact('posts'));
}

// Agora são 4 queries:
// 1. SELECT * FROM posts WHERE published = 1
// 2. SELECT * FROM users WHERE id IN (...)
// 3. SELECT * FROM categories WHERE id IN (...)
// 4. SELECT post_id, COUNT(*) FROM comments WHERE post_id IN (...) GROUP BY post_id

// No Blade
@foreach ($posts as $post)
    <h2>{{ $post->title }}</h2>
    <p>Autor: {{ $post->user->name }}</p>
    <p>Categoria: {{ $post->category->name }}</p>
    <p>Comentários: {{ $post->comments_count }}</p>  {{-- Vem do withCount --}}
@endforeach
```
</details>

### Exercício 2: API Resource com N+1

Corrija o N+1 no endpoint da API.

```php
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::paginate(20);
        return PostResource::collection($posts);
    }
}

class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'category' => $this->category->name,
            'comments_count' => $this->comments()->count(),
            'likes_count' => $this->likes()->count(),
            'tags' => $this->tags->pluck('name'),
        ];
    }
}
```

<details>
<summary>Solução</summary>

```php
// ✅ Controller corrigido
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with(['user', 'category', 'tags'])
            ->withCount(['comments', 'likes'])
            ->paginate(20);

        return PostResource::collection($posts);
    }
}

// ✅ Resource corrigido com whenLoaded
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,

            // whenLoaded evita N+1 se a relationship não veio no eager load
            'author' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),

            'category' => $this->whenLoaded('category', fn() => $this->category->name),

            // Usa withCount (não chama count() no Resource)
            'comments_count' => $this->when(
                isset($this->comments_count),
                $this->comments_count
            ),

            'likes_count' => $this->when(
                isset($this->likes_count),
                $this->likes_count
            ),

            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->pluck('name');
            }),
        ];
    }
}

// Alternativa: Resource separado para User
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => new UserResource($this->whenLoaded('user')),
            'category' => $this->whenLoaded('category', fn() => $this->category->name),
            'comments_count' => $this->comments_count ?? 0,
            'likes_count' => $this->likes_count ?? 0,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
```
</details>

### Exercício 3: Relationships aninhadas

Otimize o carregamento: usuários → posts → comentários → autor do comentário.

```php
$users = User::all();

foreach ($users as $user) {
    foreach ($user->posts as $post) {
        foreach ($post->comments as $comment) {
            echo $comment->user->name;
        }
    }
}
```

<details>
<summary>Solução</summary>

```php
// ❌ Problema: N+1 aninhado
// 1 query: users
// N queries: posts de cada user
// N*M queries: comments de cada post
// N*M*K queries: user de cada comment
// 10 users, 100 posts, 1000 comments = milhares de queries!

// ✅ Solução 1: Nested Eager Loading
$users = User::with(['posts.comments.user'])->get();

// São 4 queries:
// 1. SELECT * FROM users
// 2. SELECT * FROM posts WHERE user_id IN (...)
// 3. SELECT * FROM comments WHERE post_id IN (...)
// 4. SELECT * FROM users WHERE id IN (...) -- autores dos comentários

foreach ($users as $user) {
    foreach ($user->posts as $post) {
        foreach ($post->comments as $comment) {
            echo $comment->user->name;  // Sem query extra
        }
    }
}

// ✅ Solução 2: Com condições
$users = User::with([
    'posts' => function ($query) {
        $query->where('published', true)
              ->latest()
              ->limit(5);
    },
    'posts.comments' => function ($query) {
        $query->latest()->limit(10);
    },
    'posts.comments.user:id,name'  // Carrega só id e name
])->get();

// ✅ Solução 3: Lazy Eager Loading (se você esqueceu de carregar)
$users = User::all();

// Depois precisou dos dados aninhados
$users->load(['posts.comments.user']);

// ✅ Solução 4: Só contadores (sem carregar os dados)
$users = User::withCount([
    'posts',
    'posts as published_posts_count' => function ($query) {
        $query->where('published', true);
    }
])->get();

foreach ($users as $user) {
    echo "{$user->name}: {$user->posts_count} posts";
}

// ✅ Solução 5: preventLazyLoading para detectar N+1
// No AppServiceProvider::boot()
use Illuminate\Database\Eloquent\Model;

Model::preventLazyLoading(! app()->isProduction());

// Agora lazy loading lança exceção em development
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
