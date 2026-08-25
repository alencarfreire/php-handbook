# 10.1 MVC (Model-View-Controller)

## Resumo

> **MVC** — padrão de arquitetura que separa o app em três componentes.
>
> **Componentes:** Model (dados/Eloquent), View (exibição/Blade), Controller (lógica de tratamento).
>
> **Importante:** Controllers ficam finos — a lógica de negócio vai para o Service Layer.

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
MVC é um padrão de arquitetura que separa o app em três componentes: Model (dados), View (exibição), Controller (lógica de tratamento).

**Componentes:**
- **Model** — trabalha com os dados (Eloquent)
- **View** — exibição (Blade)
- **Controller** — trata os requests

---

## Como funciona

**Fluxo MVC:**

```
Request → Router → Controller → Model → Database
                      ↓
                    View → Response
```

**Model (Eloquent):**

```php
// app/Models/Post.php
class Post extends Model
{
    protected $fillable = ['title', 'body', 'user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
```

**Controller:**

```php
// app/Http/Controllers/PostController.php
class PostController extends Controller
{
    public function index()
    {
        $posts = Post::with('user')->paginate(20);

        return view('posts.index', compact('posts'));
    }

    public function show(Post $post)
    {
        $post->load('user', 'comments');

        return view('posts.show', compact('post'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|max:255',
            'body' => 'required',
        ]);

        $post = Post::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return redirect()->route('posts.show', $post);
    }
}
```

**View (Blade):**

```blade
{{-- resources/views/posts/index.blade.php --}}
@extends('layouts.app')

@section('content')
    <h1>Posts</h1>

    @foreach($posts as $post)
        <article>
            <h2>{{ $post->title }}</h2>
            <p>Por {{ $post->user->name }}</p>
            <p>{{ $post->excerpt }}</p>
            <a href="{{ route('posts.show', $post) }}">Ler mais</a>
        </article>
    @endforeach

    {{ $posts->links() }}
@endsection
```

---

## Quando usar

**MVC serve para:**
- Apps web
- Operações CRUD
- Apps tradicionais server-side

**Problemas do MVC:**
- Controllers incham (Fat Controllers)
- Lógica de negócio no controller
- Solução: Service Layer, Repository

---

## Exemplo prático

**Controller fino (certo):**

```php
class PostController extends Controller
{
    public function __construct(
        private PostService $postService
    ) {}

    public function store(CreatePostRequest $request)
    {
        // Delega a lógica para o service
        $post = $this->postService->create(
            $request->user(),
            $request->validated()
        );

        return redirect()->route('posts.show', $post)
            ->with('success', 'Post criado!');
    }
}

// app/Services/PostService.php
class PostService
{
    public function create(User $user, array $data): Post
    {
        DB::beginTransaction();

        try {
            $post = Post::create([
                'user_id' => $user->id,
                ...$data,
            ]);

            // Lógica extra
            event(new PostCreated($post));
            Cache::forget('posts.latest');

            DB::commit();

            return $post;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**View Composers (para dados compartilhados):**

```php
// app/Providers/ViewServiceProvider.php
class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Para todas as views
        View::composer('*', function ($view) {
            $view->with('appName', config('app.name'));
        });

        // Para uma view específica
        View::composer('posts.*', function ($view) {
            $view->with('categories', Category::all());
        });
    }
}
```

---

## Na entrevista

> "MVC separa em Model (dados), View (exibição), Controller (lógica). No Laravel: Eloquent no Model, Blade na View, Controller trata o request. O controller recebe o request, fala com o Model, devolve a View. Fat Controller se resolve com Service Layer. View Composer para dado compartilhado. Route → Controller → Model → View → Response."

---

## Exercícios práticos

### Exercício 1: Refatorar Fat Controller

**Enunciado:** Você tem um controller com lógica de negócio. Extraia para o Service Layer.

```php
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create([
            'user_id' => auth()->id(),
            'total' => $request->total,
        ]);

        foreach ($request->items as $item) {
            $order->items()->create($item);
        }

        Mail::to($order->user)->send(new OrderConfirmation($order));
        Cache::forget('orders.latest');
        event(new OrderCreated($order));

        return redirect()->route('orders.show', $order);
    }
}
```

<details>
<summary>Solução</summary>

```php
// app/Services/OrderService.php
class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository
    ) {}

    public function create(User $user, array $data): Order
    {
        DB::beginTransaction();

        try {
            $order = $this->orderRepository->create([
                'user_id' => $user->id,
                'total' => $data['total'],
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            Mail::to($user)->send(new OrderConfirmation($order));
            Cache::forget('orders.latest');
            event(new OrderCreated($order));

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

// app/Http/Controllers/OrderController.php
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

        return redirect()->route('orders.show', $order)
            ->with('success', 'Pedido criado com sucesso!');
    }
}
```
</details>

### Exercício 2: Configurar View Composer

**Enunciado:** Crie um View Composer que injeta a lista de categorias em todas as views que começam com `posts.*`.

<details>
<summary>Solução</summary>

```php
// app/Providers/ViewServiceProvider.php
namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Models\Category;

class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Para um padrão específico de views
        View::composer('posts.*', function ($view) {
            $view->with('categories', Category::all());
        });

        // Para várias views
        View::composer(['posts.*', 'admin.posts.*'], function ($view) {
            $view->with('categories', Category::orderBy('name')->get());
        });

        // Ou via classe
        View::composer('posts.*', PostViewComposer::class);
    }
}

// app/View/Composers/PostViewComposer.php
namespace App\View\Composers;

use App\Models\Category;
use Illuminate\View\View;

class PostViewComposer
{
    public function compose(View $view): void
    {
        $view->with('categories', Category::cached()->get());
    }
}

// config/app.php — adicionar em providers
'providers' => [
    // ...
    App\Providers\ViewServiceProvider::class,
],

// resources/views/posts/create.blade.php
<select name="category_id">
    @foreach($categories as $category)
        <option value="{{ $category->id }}">{{ $category->name }}</option>
    @endforeach
</select>
```
</details>

### Exercício 3: Implementar Resource Controller

**Enunciado:** Crie um resource controller CRUD completo para o model `Article`, com validação.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/ArticleController.php
class ArticleController extends Controller
{
    public function index()
    {
        $articles = Article::with('user')->latest()->paginate(20);

        return view('articles.index', compact('articles'));
    }

    public function create()
    {
        return view('articles.create');
    }

    public function store(StoreArticleRequest $request)
    {
        $article = Article::create([
            'user_id' => auth()->id(),
            ...$request->validated(),
        ]);

        return redirect()->route('articles.show', $article)
            ->with('success', 'Artigo criado com sucesso!');
    }

    public function show(Article $article)
    {
        $article->load('user', 'comments');

        return view('articles.show', compact('article'));
    }

    public function edit(Article $article)
    {
        $this->authorize('update', $article);

        return view('articles.edit', compact('article'));
    }

    public function update(UpdateArticleRequest $request, Article $article)
    {
        $this->authorize('update', $article);

        $article->update($request->validated());

        return redirect()->route('articles.show', $article)
            ->with('success', 'Artigo atualizado com sucesso!');
    }

    public function destroy(Article $article)
    {
        $this->authorize('delete', $article);

        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', 'Artigo excluído com sucesso!');
    }
}

// app/Http/Requests/StoreArticleRequest.php
class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|max:255',
            'slug' => 'required|unique:articles',
            'body' => 'required',
            'published' => 'boolean',
        ];
    }
}

// routes/web.php
Route::resource('articles', ArticleController::class);
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
