# 13.2 Otimização de banco

## Resumo

> **Otimização de banco** — acelerar o acesso ao banco com índices, evitar N+1 e otimizar JOIN.
>
> **Problemas:** N+1 queries (solução: `with()`), falta de índices, `SELECT *`, JOIN lento.
>
> **Métodos:** Eager Loading, paginação no lugar de `all()`, `chunk/lazy` para volume grande, `withCount` para agregados.

---

## Conteúdo

- [O que é](#o-que-é)
- [Problema N+1](#problema-n1)
- [Índices](#índices)
- [Otimização de queries](#otimização-de-queries)
- [Otimização de JOIN](#otimização-de-join)
- [Agregados](#agregados)
- [Cache de queries](#cache-de-queries)
- [Exemplos práticos](#exemplos-práticos)
- [Database Connection Pool](#database-connection-pool)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Otimizar queries no banco para o app ficar mais rápido. Índices, evitar N+1, otimizar JOIN.

**Problemas principais:**
- N+1 queries
- Falta de índices
- SELECT *
- JOIN lento

---

## Problema N+1

**Problema:**

```php
// ❌ RUIM: 1 + N queries
$posts = Post::all();  // 1 query

foreach ($posts as $post) {
    echo $post->user->name;  // N queries
}
// Total: 1 + 100 = 101 queries para 100 posts
```

**Solução: Eager Loading:**

```php
// ✅ BOM: 2 queries
$posts = Post::with('user')->get();  // 2 queries: posts + users

foreach ($posts as $post) {
    echo $post->user->name;  // Sem query extra
}
```

**Nested relationships:**

```php
// Carregar várias relationships
$posts = Post::with(['user', 'comments', 'tags'])->get();

// Relationships aninhadas
$posts = Post::with('comments.user')->get();

// Condições no eager loading
$posts = Post::with(['comments' => function ($query) {
    $query->where('approved', true)
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();
```

**Lazy Eager Loading:**

```php
$posts = Post::all();

// Carregar a relationship depois
if ($needUsers) {
    $posts->load('user');
}
```

---

## Índices

**Criar índices:**

```php
Schema::table('posts', function (Blueprint $table) {
    // Índice simples
    $table->index('user_id');

    // Índice composto
    $table->index(['user_id', 'published']);

    // Índice único
    $table->unique('email');

    // Índice full-text (MySQL)
    $table->fullText('title');
});
```

**Quando usar:**

```php
// ✅ Índice entra em:
// - WHERE clause
Post::where('user_id', 1)->get();  // Índice em user_id

// - ORDER BY
Post::orderBy('created_at', 'desc')->get();  // Índice em created_at

// - JOIN
Post::join('users', 'posts.user_id', '=', 'users.id');  // Índices nas duas colunas

// - FOREIGN KEY
$table->foreign('user_id')->references('id')->on('users');  // Índice automático
```

**Checar se o índice entra:**

```php
// EXPLAIN para analisar
DB::enableQueryLog();

Post::where('user_id', 1)
    ->where('published', true)
    ->orderBy('created_at', 'desc')
    ->get();

dd(DB::getQueryLog());

// Ou SQL direto:
// EXPLAIN SELECT * FROM posts WHERE user_id = 1 AND published = 1 ORDER BY created_at DESC;
```

---

## Otimização de queries

**Evitar SELECT *:**

```php
// ❌ RUIM: pega tudo
$users = User::all();

// ✅ BOM: só as colunas que precisa
$users = User::select(['id', 'name', 'email'])->get();

// Com relationships
$posts = Post::with('user:id,name')->get();
```

**Paginação:**

```php
// ❌ RUIM: carrega tudo na memória
$posts = Post::all();

// ✅ BOM: paginação
$posts = Post::paginate(20);

// Para API: paginação simples (sem total count)
$posts = Post::simplePaginate(20);

// Paginação por cursor (volume grande)
$posts = Post::orderBy('id')->cursorPaginate(20);
```

**Chunk para volume grande:**

```php
// ❌ RUIM: tabela inteira na memória
User::all()->each(function ($user) {
    $this->processUser($user);
});

// ✅ BOM: em pedaços
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        $this->processUser($user);
    }
});

// Lazy para iterar
User::lazy()->each(function ($user) {
    $this->processUser($user);
});
```

---

## Otimização de JOIN

**Eager Loading vs JOIN:**

```php
// Eager Loading (2 queries)
$posts = Post::with('user')->get();

// JOIN (1 query, mas duplica dados)
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as user_name')
    ->get();
```

**LEFT JOIN para contar:**

```php
// Quantidade de comments de cada post
$posts = Post::leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->select('posts.*', DB::raw('COUNT(comments.id) as comments_count'))
    ->groupBy('posts.id')
    ->get();

// Ou withCount (mais simples)
$posts = Post::withCount('comments')->get();
```

---

## Agregados

**COUNT, SUM, AVG:**

```php
// Total
$count = User::count();

// Com condição
$activeUsers = User::where('active', true)->count();

// SUM
$totalRevenue = Order::sum('total');
$todayRevenue = Order::whereDate('created_at', today())->sum('total');

// AVG
$avgOrderValue = Order::avg('total');

// MIN, MAX
$minPrice = Product::min('price');
$maxPrice = Product::max('price');
```

**Agrupamento:**

```php
// Pedidos por usuário
$orders = Order::select('user_id', DB::raw('COUNT(*) as total'))
    ->groupBy('user_id')
    ->get();

// Com HAVING
$bigSpenders = Order::select('user_id', DB::raw('SUM(total) as spent'))
    ->groupBy('user_id')
    ->having('spent', '>', 1000)
    ->get();
```

---

## Cache de queries

**Cache para agregados:**

```php
// ❌ RUIM: query em todo request
public function dashboard()
{
    return [
        'users_count' => User::count(),
        'orders_count' => Order::count(),
        'revenue' => Order::sum('total'),
    ];
}

// ✅ BOM: cache
public function dashboard()
{
    return Cache::remember('dashboard.stats', 600, function () {
        return [
            'users_count' => User::count(),
            'orders_count' => Order::count(),
            'revenue' => Order::sum('total'),
        ];
    });
}
```

---

## Exemplos práticos

**Otimizar query complexa:**

```php
// ❌ RUIM
public function getPopularPosts()
{
    $posts = Post::all();  // N+1

    return $posts->filter(function ($post) {
        return $post->comments()->count() > 10;  // N queries
    })->map(function ($post) {
        return [
            'title' => $post->title,
            'author' => $post->user->name,  // N+1
            'comments' => $post->comments->count(),
        ];
    });
}

// ✅ BOM
public function getPopularPosts()
{
    return Cache::remember('posts.popular', 3600, function () {
        return Post::withCount('comments')
            ->with('user:id,name')
            ->having('comments_count', '>', 10)
            ->select(['id', 'title', 'user_id'])
            ->get()
            ->map(function ($post) {
                return [
                    'title' => $post->title,
                    'author' => $post->user->name,
                    'comments' => $post->comments_count,
                ];
            });
    });
}
```

**Otimizar busca:**

```php
// ❌ RUIM
public function search($query)
{
    return Product::where('name', 'like', "%$query%")
        ->orWhere('description', 'like', "%$query%")
        ->get();
}

// ✅ BOM: fulltext index
Schema::table('products', function (Blueprint $table) {
    $table->fullText(['name', 'description']);
});

public function search($query)
{
    return Product::whereFullText(['name', 'description'], $query)
        ->limit(20)
        ->get();
}

// Ou Scout (Algolia, Meilisearch)
return Product::search($query)->get();
```

**Batch insert:**

```php
// ❌ RUIM: N queries
foreach ($data as $item) {
    Product::create($item);  // N insert queries
}

// ✅ BOM: 1 query
Product::insert($data);

// Ou com timestamps
$now = now();
$data = array_map(function ($item) use ($now) {
    return array_merge($item, [
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}, $data);

Product::insert($data);
```

---

## Database Connection Pool

**Persistent connections:**

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST'),
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'options' => [
        PDO::ATTR_PERSISTENT => true,  // Conexão persistente
    ],
],
```

**Read/Write connections:**

```php
'mysql' => [
    'read' => [
        'host' => ['192.168.1.1', '192.168.1.2'],  // Read replicas
    ],
    'write' => [
        'host' => ['192.168.1.3'],  // Master
    ],
    'sticky' => true,  // Ler do write depois de gravar
],
```

---

## Na entrevista

> "Otimização de banco: evito N+1 com eager loading (with). Índice em WHERE, ORDER BY, JOIN. SELECT só os campos que precisa. Paginação no lugar de all(). Chunk/lazy para volume grande. withCount para COUNT. Cache de agregados. Fulltext index para busca. Batch insert no lugar de N queries. EXPLAIN para analisar. Read/write replicas para escalar."

---

## Exercícios práticos

### Exercício 1: Corrija o N+1

**Enunciado:** Encontre e corrija o N+1 no código.

```php
// Controller
public function index()
{
    $posts = Post::where('published', true)->get();

    return view('posts.index', compact('posts'));
}

// View
@foreach($posts as $post)
    <h2>{{ $post->title }}</h2>
    <p>Autor: {{ $post->user->name }}</p>
    <p>Comentários: {{ $post->comments->count() }}</p>
    <p>Categoria: {{ $post->category->name }}</p>
@endforeach
```

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: 1 + N (users) + N (comments) + N (categories) queries

// ✅ BOM: 4 queries no total
public function index()
{
    $posts = Post::where('published', true)
        ->with(['user', 'category'])  // Eager load das relationships
        ->withCount('comments')        // COUNT numa query só
        ->get();

    return view('posts.index', compact('posts'));
}

// View (sem mudanças)
@foreach($posts as $post)
    <h2>{{ $post->title }}</h2>
    <p>Autor: {{ $post->user->name }}</p>
    <p>Comentários: {{ $post->comments_count }}</p>
    <p>Categoria: {{ $post->category->name }}</p>
@endforeach

// Queries:
// 1. SELECT * FROM posts WHERE published = 1
// 2. SELECT * FROM users WHERE id IN (1, 2, 3, ...)
// 3. SELECT * FROM categories WHERE id IN (1, 2, 3, ...)
// 4. SELECT post_id, COUNT(*) FROM comments WHERE post_id IN (...) GROUP BY post_id
```
</details>

### Exercício 2: Otimização com índices

**Enunciado:** Crie uma migration com os índices certos para a tabela `products`. Queries: filtro por category_id, busca por name, ordenação por price.

<details>
<summary>Solução</summary>

```php
// database/migrations/xxxx_create_products_table.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->integer('stock')->default(0);
    $table->foreignId('category_id')->constrained()->onDelete('cascade');
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    // Índices para otimizar as queries
    $table->index('category_id');           // WHERE category_id
    $table->index('name');                  // WHERE name LIKE / ORDER BY name
    $table->index('price');                 // ORDER BY price

    // Composite index para queries frequentes
    $table->index(['category_id', 'is_active', 'price']);  // WHERE category_id AND is_active ORDER BY price

    // Fulltext para busca
    $table->fullText(['name', 'description']);
});

// Uso
// ✅ Usa o composite index
Product::where('category_id', 1)
    ->where('is_active', true)
    ->orderBy('price', 'asc')
    ->get();

// ✅ Usa o fulltext index
Product::whereFullText(['name', 'description'], 'laptop')
    ->limit(20)
    ->get();
```
</details>

### Exercício 3: Otimização de batch

**Enunciado:** Otimize o import de 10000 produtos de um arquivo CSV.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: 10000 INSERT queries
public function import(string $csvPath)
{
    $rows = $this->parseCsv($csvPath);

    foreach ($rows as $row) {
        Product::create([
            'name' => $row['name'],
            'price' => $row['price'],
            'category_id' => $row['category_id'],
        ]);
    }
}

// ✅ BOM: Batch insert + chunk
public function import(string $csvPath)
{
    $rows = $this->parseCsv($csvPath);

    // Quebrar em chunks de 1000
    collect($rows)->chunk(1000)->each(function ($chunk) {
        $data = $chunk->map(function ($row) {
            return [
                'name' => $row['name'],
                'price' => $row['price'],
                'category_id' => $row['category_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->toArray();

        // 1 INSERT para 1000 linhas
        Product::insert($data);
    });
}

// ✅ AINDA MELHOR: DB transaction + desliga events
public function import(string $csvPath)
{
    Product::withoutEvents(function () use ($csvPath) {
        DB::transaction(function () use ($csvPath) {
            $rows = $this->parseCsv($csvPath);

            collect($rows)->chunk(1000)->each(function ($chunk) {
                $data = $chunk->map(function ($row) {
                    return [
                        'name' => $row['name'],
                        'price' => $row['price'],
                        'category_id' => $row['category_id'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->toArray();

                Product::insert($data);
            });
        });
    });
}

// Performance:
// ❌ create() no loop: ~30 s para 10k linhas
// ✅ batch insert: ~2 s
// ✅ + sem events + transaction: ~1 s
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
