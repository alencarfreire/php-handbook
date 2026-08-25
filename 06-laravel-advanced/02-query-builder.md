# 5.2 Query Builder

## Resumo

> **Query Builder** é uma interface fluente para montar SQL sem escrever SQL puro.
>
> **Base:** `DB::table('users')` + métodos `where()`, `join()`, `groupBy()`, `orderBy()`.
>
> **Proteção:** SQL injection fica bloqueado — os valores vão como parâmetro.

---

## Conteúdo

- [O que é](#o-que-é)
- [Consultas básicas](#como-funciona)
- [INSERT, UPDATE, DELETE](#como-funciona)
- [JOIN](#como-funciona)
- [Aggregates](#como-funciona)
- [Transações](#exemplo-prático)
- [Quando usar](#quando-usar)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Query Builder é uma interface fluente para montar SQL. Protege contra SQL injection. Funciona em qualquer banco que o Laravel suporte.

**O essencial:**
- `DB::table()` — começa a query
- Métodos fluentes: where, join, groupBy, orderBy
- Proteção contra SQL injection

---

## Como funciona

**Consultas básicas:**

```php
use Illuminate\Support\Facades\DB;

// SELECT
$users = DB::table('users')->get();
$user = DB::table('users')->where('id', 1)->first();
$email = DB::table('users')->where('id', 1)->value('email');

// SELECT com condições
$users = DB::table('users')
    ->where('active', true)
    ->where('age', '>', 18)
    ->get();

// Condição OR
$users = DB::table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// WHERE IN
$users = DB::table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// WHERE BETWEEN
$users = DB::table('users')
    ->whereBetween('age', [18, 65])
    ->get();

// WHERE NULL
$users = DB::table('users')
    ->whereNull('deleted_at')
    ->get();

// WHERE LIKE
$users = DB::table('users')
    ->where('name', 'like', '%João%')
    ->get();
```

**INSERT:**

```php
// Inserir um registro
DB::table('users')->insert([
    'name' => 'João',
    'email' => 'joao@email.com',
]);

// Inserir vários
DB::table('users')->insert([
    ['name' => 'João', 'email' => 'joao@email.com'],
    ['name' => 'Maria', 'email' => 'maria@email.com'],
]);

// Inserir e pegar o ID
$id = DB::table('users')->insertGetId([
    'name' => 'João',
    'email' => 'joao@email.com',
]);

// Inserir ou atualizar (upsert)
DB::table('users')->upsert([
    ['email' => 'joao@email.com', 'name' => 'João'],
    ['email' => 'maria@email.com', 'name' => 'Maria'],
], ['email'], ['name']);  // Campos únicos, campos que atualizam
```

**UPDATE:**

```php
// Atualizar
DB::table('users')
    ->where('id', 1)
    ->update(['name' => 'João Silva']);

// increment / decrement
DB::table('users')->increment('views');
DB::table('users')->increment('views', 5);
DB::table('users')->decrement('likes');

// increment com update extra
DB::table('users')->increment('views', 1, ['updated_at' => now()]);

// Update or Insert
DB::table('users')->updateOrInsert(
    ['email' => 'joao@email.com'],  // Condição de busca
    ['name' => 'João', 'active' => true]  // Dados do update/insert
);
```

**DELETE:**

```php
// Deletar
DB::table('users')->where('id', 1)->delete();

// Deletar todos
DB::table('users')->delete();

// Truncate (mais rápido)
DB::table('users')->truncate();
```

**JOIN:**

```php
// INNER JOIN
$users = DB::table('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->select('users.*', 'profiles.bio')
    ->get();

// LEFT JOIN
$users = DB::table('users')
    ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();

// Vários JOIN
$users = DB::table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->join('products', 'orders.product_id', '=', 'products.id')
    ->select('users.name', 'products.name as product')
    ->get();

// JOIN com condição
$users = DB::table('users')
    ->join('posts', function ($join) {
        $join->on('users.id', '=', 'posts.user_id')
             ->where('posts.published', true);
    })
    ->get();
```

**Aggregates:**

```php
// COUNT
$count = DB::table('users')->count();
$activeCount = DB::table('users')->where('active', true)->count();

// SUM, AVG, MIN, MAX
$sum = DB::table('orders')->sum('amount');
$avg = DB::table('orders')->avg('amount');
$min = DB::table('products')->min('price');
$max = DB::table('products')->max('price');

// Existência
$exists = DB::table('users')->where('email', 'joao@email.com')->exists();
$notExists = DB::table('users')->where('email', 'joao@email.com')->doesntExist();
```

**GROUP BY, HAVING:**

```php
// Agrupamento
$users = DB::table('orders')
    ->select('user_id', DB::raw('SUM(amount) as total'))
    ->groupBy('user_id')
    ->get();

// HAVING
$users = DB::table('orders')
    ->select('user_id', DB::raw('SUM(amount) as total'))
    ->groupBy('user_id')
    ->having('total', '>', 1000)
    ->get();
```

**ORDER BY, LIMIT:**

```php
// Ordenação
$users = DB::table('users')
    ->orderBy('name', 'asc')
    ->orderBy('created_at', 'desc')
    ->get();

// Ordenação aleatória
$users = DB::table('users')->inRandomOrder()->get();

// LIMIT, OFFSET
$users = DB::table('users')->limit(10)->get();
$users = DB::table('users')->offset(10)->limit(10)->get();

// Paginação
$users = DB::table('users')->paginate(15);
```

---

## Quando usar

**Query Builder quando:**
- JOIN complexo
- Funções de agregação
- Raw SQL com parâmetros
- Operação em lote (bulk)

**Eloquent quando:**
- CRUD
- Relationships
- Events, observers
- Soft deletes

---

## Exemplo prático

**Query pesada com JOIN e agregação:**

```php
// Usuários com quantidade de pedidos e valor total
$users = DB::table('users')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
    ->select(
        'users.id',
        'users.name',
        'users.email',
        DB::raw('COUNT(orders.id) as orders_count'),
        DB::raw('COALESCE(SUM(orders.amount), 0) as total_spent')
    )
    ->groupBy('users.id', 'users.name', 'users.email')
    ->having('orders_count', '>', 0)
    ->orderBy('total_spent', 'desc')
    ->get();
```

**Subqueries:**

```php
// Usuários com o último pedido
$latestOrders = DB::table('orders')
    ->select('user_id', DB::raw('MAX(created_at) as last_order_date'))
    ->groupBy('user_id');

$users = DB::table('users')
    ->joinSub($latestOrders, 'latest_orders', function ($join) {
        $join->on('users.id', '=', 'latest_orders.user_id');
    })
    ->get();

// Ou com whereIn e subquery
$activeUsers = DB::table('users')
    ->whereIn('id', function ($query) {
        $query->select('user_id')
              ->from('orders')
              ->where('created_at', '>', now()->subDays(30));
    })
    ->get();
```

**Transactions:**

```php
DB::transaction(function () {
    DB::table('users')->where('id', 1)->update(['balance' => DB::raw('balance - 100')]);
    DB::table('users')->where('id', 2)->update(['balance' => DB::raw('balance + 100')]);

    DB::table('transactions')->insert([
        'from_user_id' => 1,
        'to_user_id' => 2,
        'amount' => 100,
    ]);
});

// Controle manual da transação
DB::beginTransaction();

try {
    // Queries
    DB::table('users')->where('id', 1)->update(['balance' => DB::raw('balance - 100')]);
    DB::table('users')->where('id', 2)->update(['balance' => DB::raw('balance + 100')]);

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

**Raw expressions:**

```php
// DB::raw() para expressões complexas
$users = DB::table('users')
    ->select(DB::raw('COUNT(*) as user_count, status'))
    ->where('status', '<>', 1)
    ->groupBy('status')
    ->get();

// WHERE RAW
$users = DB::table('users')
    ->whereRaw('age > ? and votes = 100', [25])
    ->get();

// ORDER BY RAW
$users = DB::table('users')
    ->orderByRaw('updated_at - created_at DESC')
    ->get();
```

**Chunking (volume grande):**

```php
// Processar em partes (economiza memória)
DB::table('users')->orderBy('id')->chunk(100, function ($users) {
    foreach ($users as $user) {
        // Processar o usuário
        processUser($user);
    }
});

// Lazy (Generator por baixo)
DB::table('users')->orderBy('id')->lazy()->each(function ($user) {
    processUser($user);
});

// Cursor (Generator)
foreach (DB::table('users')->cursor() as $user) {
    processUser($user);
}
```

**Conditional Clauses:**

```php
$role = request('role');
$status = request('status');

$users = DB::table('users')
    ->when($role, function ($query, $role) {
        return $query->where('role', $role);
    })
    ->when($status, function ($query, $status) {
        return $query->where('status', $status);
    })
    ->get();

// unless (o inverso de when)
$users = DB::table('users')
    ->unless(auth()->user()->isAdmin(), function ($query) {
        return $query->where('user_id', auth()->id());
    })
    ->get();
```

**Debug:**

```php
// Pegar o SQL
$query = DB::table('users')->where('active', true)->toSql();
// SELECT * FROM users WHERE active = ?

// Com os parâmetros
$query = DB::table('users')->where('active', true);
dd($query->toSql(), $query->getBindings());

// Mandar a query para o log
DB::enableQueryLog();

DB::table('users')->where('active', true)->get();

$queries = DB::getQueryLog();
dd($queries);

// Logar todas as queries (no AppServiceProvider)
DB::listen(function ($query) {
    Log::info('Query', [
        'sql' => $query->sql,
        'bindings' => $query->bindings,
        'time' => $query->time,
    ]);
});
```

---

## Na entrevista

> "Query Builder é interface fluente para SQL, com proteção contra injection. Começo com DB::table() e monto com where, join, groupBy, orderBy. Transação: DB::transaction() ou beginTransaction/commit/rollBack. Volume grande: chunk, lazy, cursor — economiza memória. DB::raw() para expressão complexa. when() para cláusula condicional. toSql() e getBindings() para debug. Query Builder no JOIN pesado e na agregação. Eloquent no CRUD e no relationship."

---

## Exercícios práticos

### Exercício 1: Monte um relatório complexo

**Enunciado:** Traga a lista de usuários com a quantidade de pedidos e o valor total das compras nos últimos 30 dias.

<details>
<summary>Solução</summary>

```php
$users = DB::table('users')
    ->leftJoin('orders', function ($join) {
        $join->on('users.id', '=', 'orders.user_id')
             ->where('orders.created_at', '>=', now()->subDays(30));
    })
    ->select(
        'users.id',
        'users.name',
        'users.email',
        DB::raw('COUNT(orders.id) as orders_count'),
        DB::raw('COALESCE(SUM(orders.total), 0) as total_spent')
    )
    ->groupBy('users.id', 'users.name', 'users.email')
    ->orderBy('total_spent', 'desc')
    ->get();
```
</details>

### Exercício 2: Implemente a transação de transferência

**Enunciado:** Implemente a transferência de dinheiro entre dois usuários, com checagem de saldo.

<details>
<summary>Solução</summary>

```php
public function transfer(int $fromUserId, int $toUserId, float $amount): void
{
    DB::transaction(function () use ($fromUserId, $toUserId, $amount) {
        // Pega o saldo do remetente com lock
        $fromUser = DB::table('users')
            ->where('id', $fromUserId)
            ->lockForUpdate()
            ->first();

        if (!$fromUser || $fromUser->balance < $amount) {
            throw new \Exception('Saldo insuficiente');
        }

        // Debita o remetente
        DB::table('users')
            ->where('id', $fromUserId)
            ->update(['balance' => DB::raw("balance - {$amount}")]);

        // Credita o destinatário
        DB::table('users')
            ->where('id', $toUserId)
            ->update(['balance' => DB::raw("balance + {$amount}")]);

        // Grava a transação
        DB::table('transactions')->insert([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'amount' => $amount,
            'created_at' => now(),
        ]);
    });
}
```
</details>

### Exercício 3: Processe uma seleção grande

**Enunciado:** Atualize o campo `status` de 100.000 usuários inativos em partes, para a memória não estourar.

<details>
<summary>Solução</summary>

```php
// Opção 1: chunk
DB::table('users')
    ->where('last_login_at', '<', now()->subYear())
    ->where('status', 'active')
    ->orderBy('id')
    ->chunk(1000, function ($users) {
        $ids = $users->pluck('id');

        DB::table('users')
            ->whereIn('id', $ids)
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]);

        Log::info("Atualizados {$users->count()} usuários");
    });

// Opção 2: lazy (mais eficiente)
DB::table('users')
    ->where('last_login_at', '<', now()->subYear())
    ->where('status', 'active')
    ->orderBy('id')
    ->lazy(1000)
    ->each(function ($user) {
        DB::table('users')
            ->where('id', $user->id)
            ->update(['status' => 'inactive']);
    });
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
