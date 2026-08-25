# 13.3 Otimização de queries

## Resumo

> **Otimização de queries** — deixar o SQL mais rápido. EXPLAIN mostra o plano de execução.
>
> **Ferramentas:** EXPLAIN (análise), Laravel Debugbar (debug de N+1), Query Log (monitorar query lenta).
>
> **Métodos:** Índice em WHERE/ORDER BY, covering index, sem função no WHERE, cursor pagination quando o offset é grande.

---

## Conteúdo

- [O que é](#o-que-é)
- [EXPLAIN](#explain)
- [Laravel Debugbar](#laravel-debugbar)
- [Otimização do WHERE](#otimização-do-where)
- [Otimização do JOIN](#otimização-do-join)
- [Otimização da ordenação](#otimização-da-ordenação)
- [Otimização do COUNT](#otimização-do-count)
- [Otimização de UPDATE/DELETE](#otimização-de-updatedelete)
- [Exemplos práticos](#exemplos-práticos)
- [Profiling do banco](#profiling-do-banco)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Deixar o SQL mais rápido. EXPLAIN, profiling, fugir de operação lenta.

**Ferramentas:**
- EXPLAIN — plano de execução da query
- Laravel Debugbar — debug das queries
- Query Log — log das queries

---

## EXPLAIN

**Análise da query:**

```sql
EXPLAIN SELECT * FROM posts
WHERE user_id = 1
AND published = 1
ORDER BY created_at DESC;

-- Resultado:
-- +----+-------------+-------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
-- | id | select_type | table | partitions | type | possible_keys | key         | key_len | ref   | rows | filtered | Extra       |
-- +----+-------------+-------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
-- |  1 | SIMPLE      | posts | NULL       | ref  | user_id,idx   | user_id     | 4       | const |  100 |   10.00  | Using where |
-- +----+-------------+-------+------------+------+---------------+-------------+---------+-------+------+----------+-------------+
```

**Colunas importantes:**

```
type: tipo de acesso
  - ALL: varredura completa (lento)
  - index: varredura do índice
  - range: intervalo
  - ref: pela chave
  - const: constante (rápido)

possible_keys: índices possíveis
key: índice usado
rows: quantidade de linhas varridas
Extra:
  - Using index: usa covering index (rápido)
  - Using where: filtra depois de ler
  - Using filesort: ordenação (lento)
  - Using temporary: tabela temporária (lento)
```

**Laravel EXPLAIN:**

```php
$query = Post::where('user_id', 1)
    ->where('published', true)
    ->orderBy('created_at', 'desc');

// Pegar o SQL
dd($query->toSql(), $query->getBindings());

// Ou na mão via DB
DB::select('EXPLAIN ' . $query->toSql(), $query->getBindings());
```

---

## Laravel Debugbar

**Instalação:**

```bash
composer require barryvdh/laravel-debugbar --dev
```

**Uso:**

```php
// Mostra automaticamente:
// - Todas as queries
// - Tempo de execução
// - Queries duplicadas
// - Problemas de N+1

// http://localhost:8000 → painel embaixo
```

**Query Log:**

```php
// Ligar o log
DB::enableQueryLog();

// Seu código
$users = User::with('posts')->get();

// Ver as queries
dd(DB::getQueryLog());

// Desligar
DB::disableQueryLog();
```

---

## Otimização do WHERE

**Usar índices:**

```php
// ❌ LENTO: não usa o índice
Post::whereRaw('YEAR(created_at) = 2024')->get();
// SELECT * FROM posts WHERE YEAR(created_at) = 2024

// ✅ RÁPIDO: usa o índice em created_at
Post::whereBetween('created_at', ['2024-01-01', '2024-12-31'])->get();
// SELECT * FROM posts WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'

// ❌ LENTO: função no WHERE
User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first();

// ✅ RÁPIDO
User::where('email', strtolower($email))->first();
```

**Composite index:**

```php
// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->index(['user_id', 'published', 'created_at']);
});

// Uso (a ordem importa!)
// ✅ Usa o índice
Post::where('user_id', 1)
    ->where('published', true)
    ->orderBy('created_at', 'desc')
    ->get();

// ⚠️ Não usa o índice inteiro (pulou user_id)
Post::where('published', true)
    ->orderBy('created_at', 'desc')
    ->get();
```

---

## Otimização do JOIN

**INNER JOIN vs LEFT JOIN:**

```php
// INNER JOIN (mais rápido se só precisa dos relacionados)
$posts = Post::join('users', 'posts.user_id', '=', 'users.id')
    ->select('posts.*', 'users.name')
    ->get();

// LEFT JOIN (quando pode ter NULL)
$posts = Post::leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->select('posts.*', DB::raw('COUNT(comments.id) as comments_count'))
    ->groupBy('posts.id')
    ->get();
```

**Evitar subquery no SELECT:**

```php
// ❌ LENTO: subquery em cada linha
$posts = DB::table('posts')
    ->select([
        'posts.*',
        DB::raw('(SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count')
    ])
    ->get();

// ✅ RÁPIDO: LEFT JOIN
$posts = DB::table('posts')
    ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
    ->select('posts.*', DB::raw('COUNT(comments.id) as comments_count'))
    ->groupBy('posts.id')
    ->get();

// ✅ OU: withCount
$posts = Post::withCount('comments')->get();
```

---

## Otimização da ordenação

**Índice no ORDER BY:**

```php
// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->index('created_at');
});

// ✅ Usa o índice
Post::orderBy('created_at', 'desc')->get();

// ❌ Não usa o índice (função)
Post::orderByRaw('RAND()')->get();

// ✅ Alternativa: inRandomOrder (melhor em amostra pequena)
Post::inRandomOrder()->limit(10)->get();
```

**Covering index:**

```php
// Migration: o índice inclui todas as colunas necessárias
Schema::table('posts', function (Blueprint $table) {
    $table->index(['user_id', 'created_at', 'title']);
});

// ✅ A query usa só o índice (não lê a tabela)
Post::where('user_id', 1)
    ->select(['user_id', 'created_at', 'title'])
    ->orderBy('created_at', 'desc')
    ->get();

// EXPLAIN mostra "Using index"
```

---

## Otimização do COUNT

**Evitar COUNT(*):**

```php
// ❌ LENTO: varredura completa
$count = Post::count();

// ✅ Colocar em cache
$count = Cache::remember('posts.count', 3600, function () {
    return Post::count();
});

// ✅ Ou guardar em tabela separada (counter cache)
// posts_count na tabela users
```

**Paginação sem COUNT:**

```php
// ❌ LENTO: faz COUNT(*) do total
$posts = Post::paginate(20);

// ✅ RÁPIDO: sem COUNT
$posts = Post::simplePaginate(20);

// ✅ Ainda mais rápido: cursor pagination
$posts = Post::orderBy('id')->cursorPaginate(20);
```

---

## Otimização de UPDATE/DELETE

**Operações em batch:**

```php
// ❌ LENTO: N queries
foreach ($userIds as $id) {
    User::where('id', $id)->update(['active' => false]);
}

// ✅ RÁPIDO: 1 query
User::whereIn('id', $userIds)->update(['active' => false]);
```

**Evitar UPDATE sem WHERE:**

```php
// ⚠️ PERIGOSO e lento
User::update(['last_seen_at' => now()]);

// ✅ Com condição
User::where('active', true)->update(['last_seen_at' => now()]);
```

---

## Exemplos práticos

**Otimização da busca:**

```php
// ❌ LENTO
public function search($query)
{
    return Product::where('name', 'like', "%$query%")
        ->orWhere('description', 'like', "%$query%")
        ->get();
}

// ✅ Fulltext index
Schema::table('products', function (Blueprint $table) {
    $table->fullText(['name', 'description']);
});

public function search($query)
{
    return Product::whereFullText(['name', 'description'], $query)
        ->limit(100)
        ->get();
}
```

**Otimização da paginação:**

```php
// ❌ LENTO com offset grande
Product::orderBy('id')->offset(10000)->limit(20)->get();
// SELECT * FROM products ORDER BY id LIMIT 20 OFFSET 10000

// ✅ Cursor pagination (keyset)
Product::where('id', '>', $lastId)->orderBy('id')->limit(20)->get();
// SELECT * FROM products WHERE id > 10000 ORDER BY id LIMIT 20
```

**Desnormalização para performance:**

```php
// ❌ LENTO: COUNT em cada request
public function getPosts()
{
    return Post::withCount('comments')->get();
}

// ✅ Guardar comments_count na tabela posts
Schema::table('posts', function (Blueprint $table) {
    $table->integer('comments_count')->default(0);
});

// Observer para atualizar o contador
class CommentObserver
{
    public function created(Comment $comment)
    {
        $comment->post()->increment('comments_count');
    }

    public function deleted(Comment $comment)
    {
        $comment->post()->decrement('comments_count');
    }
}
```

---

## Profiling do banco

**MySQL slow query log:**

```sql
-- my.cnf
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1  -- Logar queries > 1 segundo

-- Análise
mysqldumpslow -s t -t 10 /var/log/mysql/slow.log
```

**Monitoramento de query no Laravel:**

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    if (app()->environment('local')) {
        DB::listen(function ($query) {
            if ($query->time > 1000) {  // > 1 segundo
                Log::warning('Slow query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                ]);
            }
        });
    }
}
```

---

## Na entrevista

> "Otimização de queries: EXPLAIN para ver o plano. Laravel Debugbar mostra N+1. Índice em WHERE, ORDER BY, JOIN. Covering index cobre todos os campos do SELECT. Composite index: a ordem das colunas importa. Sem função no WHERE. simplePaginate sem COUNT. Cursor pagination quando o offset é grande. Fulltext index para busca. Batch no lugar de N queries. Desnormalização para COUNT. Slow query log para monitorar."

---

## Exercícios práticos

### Exercício 1: Otimizar a query com EXPLAIN

Analise e otimize a query com EXPLAIN.

```php
// Query lenta
$users = DB::table('users')
    ->whereRaw('YEAR(created_at) = 2024')
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->get();
```

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: YEAR(created_at) não usa o índice
// EXPLAIN mostra: type = ALL (varredura completa)

// ✅ BOM: tira a função do WHERE
$users = DB::table('users')
    ->whereBetween('created_at', ['2024-01-01', '2024-12-31 23:59:59'])
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->get();

// Migration: composite index
Schema::table('users', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
});

// EXPLAIN agora mostra:
// type = range (usa o índice)
// key = status_created_at_index
// rows = ~100 (em vez de 10000)

// Checagem no Laravel
DB::enableQueryLog();
// ... query ...
dd(DB::getQueryLog());

// SQL do EXPLAIN
EXPLAIN SELECT * FROM users
WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'
AND status = 'active'
ORDER BY created_at DESC;
```
</details>

### Exercício 2: Cursor Pagination

Implemente paginação eficiente numa tabela grande (1 milhão de linhas) sem OFFSET.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: OFFSET fica lento com valor grande
public function index(Request $request)
{
    $page = $request->get('page', 1);
    $perPage = 20;

    // SELECT * FROM posts ORDER BY id LIMIT 20 OFFSET 100000
    // Varre 100020 linhas!
    return Post::orderBy('id')->paginate($perPage);
}

// ✅ BOM: Cursor pagination (keyset)
public function index(Request $request)
{
    $lastId = $request->get('last_id', 0);
    $perPage = 20;

    // SELECT * FROM posts WHERE id > 100000 ORDER BY id LIMIT 20
    // Varre só 20 linhas!
    $posts = Post::where('id', '>', $lastId)
        ->orderBy('id')
        ->limit($perPage)
        ->get();

    return response()->json([
        'data' => $posts,
        'meta' => [
            'last_id' => $posts->last()?->id,
            'has_more' => $posts->count() === $perPage,
        ],
    ]);
}

// ✅ Cursor pagination nativo do Laravel
public function index()
{
    return Post::orderBy('id')->cursorPaginate(20);
}

// Response:
// {
//   "data": [...],
//   "next_cursor": "eyJpZCI6MTAwMDJ9",
//   "prev_cursor": null
// }

// Performance:
// OFFSET 100000: ~500ms
// Cursor (WHERE id > 100000): ~5ms
```
</details>

### Exercício 3: Desnormalização para performance

Otimize a contagem de comentários dos posts com desnormalização.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: COUNT em cada request
public function index()
{
    return Post::withCount('comments')->get();
    // SELECT *, (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comments_count
}

// ✅ BOM: Desnormalização — guarda o contador na tabela
// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->integer('comments_count')->default(0)->after('content');
    $table->index('comments_count'); // Para ordenar por popularidade
});

// Preencher os existentes
DB::statement('
    UPDATE posts
    SET comments_count = (
        SELECT COUNT(*)
        FROM comments
        WHERE comments.post_id = posts.id
    )
');

// Observer para atualizar sozinho
class CommentObserver
{
    public function created(Comment $comment)
    {
        $comment->post()->increment('comments_count');
        Cache::forget("post.{$comment->post_id}");
    }

    public function deleted(Comment $comment)
    {
        $comment->post()->decrement('comments_count');
        Cache::forget("post.{$comment->post_id}");
    }
}

// Agora a query é simples
public function index()
{
    return Post::select(['id', 'title', 'comments_count'])
        ->orderBy('comments_count', 'desc')
        ->get();
    // SELECT id, title, comments_count FROM posts ORDER BY comments_count DESC
}

// Performance:
// withCount(): ~200ms para 10k posts
// denormalized: ~10ms
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
