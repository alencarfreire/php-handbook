# 6.7 Otimização de queries

## Resumo

> **Otimização de queries** — deixar o SQL mais rápido com índice, reescrita da query e cache.
>
> **Técnicas:** Índice em WHERE/ORDER BY, select só os campos que precisa, eager loading, chunk/lazy para volume grande, exists() no lugar de count().
>
> **Importante:** EXPLAIN mostra o plano da query. Índice composto para WHERE + ORDER BY. Batch insert no lugar de N queries.

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
Otimização de queries — deixar o SQL mais rápido com índice, reescrita da query e cache.

**Técnicas principais:**
- Índices
- SELECT só os campos que precisa
- Eager loading (N+1)
- Chunk para volume grande
- Cache

---

## Como funciona

**SELECT só os campos que precisa:**

```php
// ❌ Carrega todos os campos (lento)
$users = User::all();

// ✅ Carrega só o que precisa
$users = User::select('id', 'name', 'email')->get();

// Eloquent relationship
$posts = Post::with(['user' => function ($query) {
    $query->select('id', 'name');  // Só id e name
}])->get();
```

**Índices:**

```php
// ❌ Sem índice (Full Table Scan)
SELECT * FROM users WHERE email = 'joao@email.com';

// ✅ Com índice (Index Seek)
Schema::table('users', function (Blueprint $table) {
    $table->index('email');
});
```

**Chunk para volume grande:**

```php
// ❌ Carrega tudo na memória (Memory Limit)
$users = User::all();

foreach ($users as $user) {
    processUser($user);
}

// ✅ Processa em pedaços
User::chunk(100, function ($users) {
    foreach ($users as $user) {
        processUser($user);
    }
});

// Ou lazy (Generator)
User::lazy()->each(function ($user) {
    processUser($user);
});
```

**EXISTS no lugar de COUNT:**

```php
// ❌ Lento (conta todos os registros)
if (Post::where('user_id', $userId)->count() > 0) {
    // ...
}

// ✅ Rápido (para no primeiro encontrado)
if (Post::where('user_id', $userId)->exists()) {
    // ...
}
```

**Limit em subquery:**

```php
// ❌ Carrega todos os posts e filtra depois
$users = User::with('posts')->get();

// ✅ Limita a quantidade de posts
$users = User::with(['posts' => function ($query) {
    $query->limit(5);
}])->get();
```

---

## Quando usar

**Use otimização quando:**
- Query lenta (> 100ms)
- Tabela grande (> 10k registros)
- Query frequente
- Tráfego alto

**Não gaste tempo quando:**
- Tabela pequena
- Query rara
- Micro-otimização

---

## Exemplo prático

**Análise com EXPLAIN:**

```php
// Liga o Query Log
DB::enableQueryLog();

User::where('email', 'joao@email.com')->get();

$queries = DB::getQueryLog();

// Analisa com EXPLAIN
DB::statement('EXPLAIN ' . $queries[0]['query']);
```

**Otimizar JOIN:**

```php
// ❌ Muitos JOIN (lento)
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->join('categories', 'posts.category_id', '=', 'categories.id')
    ->join('tags', 'posts.tag_id', '=', 'tags.id')
    ->get();

// ✅ Eager loading (mais rápido)
$posts = Post::with(['user', 'category', 'tags'])->get();
```

**Raw SQL para query complexa:**

```php
// ❌ Eloquent (lento para lógica complexa)
$users = User::with('orders')
    ->get()
    ->filter(function ($user) {
        return $user->orders->sum('total') > 1000;
    });

// ✅ Raw SQL com subquery
$users = DB::table('users')
    ->join(DB::raw('(SELECT user_id, SUM(total) as total_spent
                     FROM orders
                     GROUP BY user_id) as order_totals'),
        'users.id', '=', 'order_totals.user_id')
    ->where('order_totals.total_spent', '>', 1000)
    ->get();

// Ou Query Builder
$users = User::whereHas('orders', function ($query) {
    $query->havingRaw('SUM(total) > ?', [1000]);
})->get();
```

**Evite OR (use UNION):**

```php
// ❌ OR pode não usar índice
User::where('status', 'active')
    ->orWhere('is_vip', true)
    ->get();

// ✅ UNION
User::where('status', 'active')
    ->union(User::where('is_vip', true))
    ->get();
```

**Desnormalização para dado lido com frequência:**

```php
// ❌ JOIN em toda query
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name as author_name')
    ->get();

// ✅ Guarda author_name em posts
Schema::table('posts', function (Blueprint $table) {
    $table->string('author_name')->nullable();
});

Post::creating(function (Post $post) {
    $post->author_name = $post->user->name;
});

// Agora sem JOIN
$posts = Post::select('id', 'title', 'author_name')->get();
```

**Índices para ORDER BY:**

```php
// ❌ Sem índice em created_at (Filesort)
$posts = Post::orderBy('created_at', 'desc')->paginate(20);

// ✅ Com índice
Schema::table('posts', function (Blueprint $table) {
    $table->index('created_at');
});

// Índice composto para WHERE + ORDER BY
Schema::table('posts', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
});

$posts = Post::where('status', 'published')
    ->orderBy('created_at', 'desc')
    ->paginate(20);
```

**Cache de queries:**

```php
// Cacheia o resultado
$users = Cache::remember('users.all', 3600, function () {
    return User::all();
});

// Cache com tags
$posts = Cache::tags(['posts'])->remember('posts.published', 3600, function () {
    return Post::where('published', true)->get();
});

// Limpa no update
Post::created(function () {
    Cache::tags(['posts'])->flush();
});
```

**Query caching (MySQL):**

```php
// MySQL Query Cache (obsoleto no MySQL 8.0)
// Use Redis/Memcached no lugar
```

**Paginação em vez de get():**

```php
// ❌ Carrega tudo (lento em volume grande)
$posts = Post::where('published', true)->get();

// ✅ Paginação
$posts = Post::where('published', true)->paginate(20);

// Paginação simples (sem total count)
$posts = Post::where('published', true)->simplePaginate(20);

// Cursor pagination (volume grande)
$posts = Post::where('published', true)->cursorPaginate(20);
```

**Batch Insert:**

```php
// ❌ N INSERT
foreach ($data as $item) {
    User::create($item);
}

// ✅ Um único INSERT
User::insert($data);

// Ou com timestamps
$now = now();
$data = array_map(function ($item) use ($now) {
    return array_merge($item, [
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}, $data);

User::insert($data);
```

**Update com condição (sem carregar o model):**

```php
// ❌ Carrega o model
$user = User::find($id);
$user->increment('views');

// ✅ Atualiza direto
User::where('id', $id)->increment('views');

// Bulk update
User::where('status', 'pending')
    ->where('created_at', '<', now()->subDays(30))
    ->update(['status' => 'expired']);
```

**select distinct em vez de pluck + unique:**

```php
// ❌ Carrega tudo e depois unique
$emails = User::pluck('email')->unique();

// ✅ DISTINCT no SQL
$emails = User::select('email')->distinct()->pluck('email');
```

---

## Na entrevista

> "Otimização: índice em WHERE/ORDER BY/JOIN, select só os campos que precisa, eager loading para N+1, chunk/lazy para volume grande, exists() no lugar de count(), cache do resultado. EXPLAIN mostra o plano da query. Índice composto para WHERE + ORDER BY. Desnormalização para dado lido com frequência. Batch insert no lugar de N queries. Update sem carregar o model. Cursor pagination para volume grande. simplePaginate() sem contar o total."

---

## Exercícios práticos

### Exercício 1: Otimize a query lenta

**Enunciado:** A query leva 5 segundos. Como otimizar?

```php
$users = User::all();

foreach ($users as $user) {
    $totalSpent = Order::where('user_id', $user->id)
        ->where('status', 'completed')
        ->sum('total');

    if ($totalSpent > 1000) {
        $user->update(['is_vip' => true]);
    }
}
```

<details>
<summary>Solução</summary>

```php
// ❌ Problemas:
// 1. User::all() carrega todos os usuários na memória
// 2. N queries para o sum (uma por usuário)
// 3. N queries para o update

// ✅ Solução 1: Uma query com subquery
DB::table('users')
    ->update([
        'is_vip' => DB::raw('(
            SELECT CASE
                WHEN COALESCE(SUM(total), 0) > 1000 THEN 1
                ELSE 0
            END
            FROM orders
            WHERE orders.user_id = users.id
              AND orders.status = "completed"
        )')
    ]);

// ✅ Solução 2: JOIN + GROUP BY + UPDATE
DB::statement('
    UPDATE users
    INNER JOIN (
        SELECT user_id, SUM(total) as total_spent
        FROM orders
        WHERE status = "completed"
        GROUP BY user_id
        HAVING total_spent > 1000
    ) as order_totals ON users.id = order_totals.user_id
    SET users.is_vip = 1
');

// ✅ Solução 3: Chunk para processar em pedaços (se a lógica precisa ficar no PHP)
User::chunk(100, function ($users) {
    $userIds = $users->pluck('id');

    // Pega os totais de todos os usuários do chunk numa query só
    $totals = Order::select('user_id', DB::raw('SUM(total) as total_spent'))
        ->whereIn('user_id', $userIds)
        ->where('status', 'completed')
        ->groupBy('user_id')
        ->pluck('total_spent', 'user_id');

    // Marca os VIP
    $vipIds = $totals->filter(fn($total) => $total > 1000)->keys();

    // Batch update
    if ($vipIds->isNotEmpty()) {
        User::whereIn('id', $vipIds)->update(['is_vip' => true]);
    }
});

// ✅ Solução 4: Desnormalização (melhor para query frequente)
// Adiciona total_spent em users
Schema::table('users', function (Blueprint $table) {
    $table->decimal('total_spent', 10, 2)->default(0);
    $table->boolean('is_vip')->default(false);
    $table->index('total_spent');
});

// Atualiza quando cria o pedido
class Order extends Model
{
    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if ($order->status === 'completed') {
                $order->user->increment('total_spent', $order->total);
                $order->user->updateVipStatus();
            }
        });

        static::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->status === 'completed') {
                $order->user->increment('total_spent', $order->total);
                $order->user->updateVipStatus();
            }
        });
    }
}

class User extends Model
{
    public function updateVipStatus(): void
    {
        $this->update(['is_vip' => $this->total_spent > 1000]);
    }
}

// Agora fica rápido:
$vipUsers = User::where('is_vip', true)->get();
```
</details>

### Exercício 2: Otimize a paginação

**Enunciado:** A tabela tem 10 milhões de registros. A paginação fica lenta nas últimas páginas. Por quê e como corrigir?

```php
// Lento na página 100000
$posts = Post::orderBy('created_at', 'desc')
    ->paginate(20);
```

<details>
<summary>Solução</summary>

```php
// ❌ Problema: o OFFSET cresce
// Na página 100000: OFFSET 2000000
// O MySQL lê e descarta 2 milhões de registros

// ✅ Solução 1: Cursor pagination (offset grande)
$posts = Post::orderBy('created_at', 'desc')
    ->orderBy('id', 'desc')  // Importante: inclui uma chave única
    ->cursorPaginate(20);

// O cursor usa WHERE no lugar de OFFSET:
// WHERE (created_at < '2024-01-01' OR (created_at = '2024-01-01' AND id < 12345))
// LIMIT 20

// No Blade
{{ $posts->links() }}  // Funciona sozinho

// ✅ Solução 2: Keyset pagination (na mão)
// Guarda o último ID
$lastId = request('last_id');

$posts = Post::where('id', '<', $lastId ?? PHP_INT_MAX)
    ->orderBy('id', 'desc')
    ->limit(20)
    ->get();

// Devolve last_id para a próxima página
return [
    'data' => $posts,
    'next_page' => $posts->isNotEmpty() ? $posts->last()->id : null,
];

// ✅ Solução 3: simplePaginate (sem total count)
$posts = Post::orderBy('created_at', 'desc')
    ->simplePaginate(20);

// Não calcula o total (mais rápido)
// Mostra só "Anterior" e "Próximo"

// ✅ Solução 4: Índice para ORDER BY + LIMIT
Schema::table('posts', function (Blueprint $table) {
    $table->index(['created_at', 'id']);  // Composite index
});

// Agora o MySQL usa o índice no ORDER BY
// e pula o OFFSET com eficiência

// ✅ Solução 5: Desnormalização + busca
// Busca por texto + paginação
// Usa Elasticsearch ou Meilisearch
$posts = Post::search(request('q'))
    ->paginate(20);
```
</details>

### Exercício 3: Operações em batch

**Enunciado:** Precisa criar 10000 usuários a partir de um CSV. Como fazer rápido?

<details>
<summary>Solução</summary>

```php
// ❌ Lento: 10000 INSERT
foreach ($csvData as $row) {
    User::create([
        'name' => $row['name'],
        'email' => $row['email'],
    ]);
}

// ✅ Solução 1: Batch Insert
$users = [];
foreach ($csvData as $row) {
    $users[] = [
        'name' => $row['name'],
        'email' => $row['email'],
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

// Um único INSERT
User::insert($users);

// ✅ Solução 2: Chunk para volume grande
collect($csvData)->chunk(1000)->each(function ($chunk) {
    $users = $chunk->map(fn($row) => [
        'name' => $row['name'],
        'email' => $row['email'],
        'created_at' => now(),
        'updated_at' => now(),
    ])->toArray();

    User::insert($users);
});

// ✅ Solução 3: Bulk insert com upsert (Laravel 8+)
User::upsert(
    $users,
    ['email'],  // Campo único
    ['name', 'updated_at']  // Atualiza se já existir
);

// ✅ Solução 4: LOAD DATA INFILE (o mais rápido)
// 1. Salva o CSV
$csvPath = storage_path('app/users.csv');

// 2. Carrega pelo MySQL
DB::statement("
    LOAD DATA LOCAL INFILE '{$csvPath}'
    INTO TABLE users
    FIELDS TERMINATED BY ','
    ENCLOSED BY '\"'
    LINES TERMINATED BY '\\n'
    IGNORE 1 ROWS
    (name, email)
    SET created_at = NOW(), updated_at = NOW()
");

// ✅ Solução 5: Queue para processar em background
ImportUsersFromCsv::dispatch($csvPath);

// Job
class ImportUsersFromCsv implements ShouldQueue
{
    public function handle(): void
    {
        $csv = Reader::createFromPath($this->csvPath);

        foreach ($csv->chunk(1000) as $chunk) {
            $users = $chunk->map(fn($row) => [
                'name' => $row['name'],
                'email' => $row['email'],
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();

            User::insert($users);
        }
    }
}

// Comparação de performance:
// create() x 10000: ~30 segundos
// insert() (batch): ~2 segundos
// LOAD DATA INFILE: ~0.5 segundo
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
