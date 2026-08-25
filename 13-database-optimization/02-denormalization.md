# 9.2 Desnormalização

> **TL;DR**
> Desnormalização adiciona redundância para acelerar a leitura. Tipos: duplicar colunas (evitar JOIN), counter cache (agregações pré-computadas), summary tables, JSONB. Trade-off: SELECT mais rápido, UPDATE mais lento, risco de inconsistency. Solução: Observers para sincronizar sozinho, scheduled jobs para checar. Use em workload read-heavy. Evite em write-heavy.

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de desnormalização](#tipos-de-desnormalização)
  - [1. Duplicar colunas](#1-duplicar-colunas)
  - [2. Agregações pré-computadas](#2-agregações-pré-computadas)
  - [3. Tabelas resumo (summary tables)](#3-tabelas-resumo-summary-tables)
  - [4. Materialized Views](#4-materialized-views)
  - [5. JSONB para campos flexíveis](#5-jsonb-para-campos-flexíveis)
- [Exemplos práticos](#exemplos-práticos)
- [Riscos da desnormalização](#riscos-da-desnormalização)
- [Quando desnormalizar](#quando-desnormalizar)
- [Quando NÃO desnormalizar](#quando-não-desnormalizar)
- [Best Practices](#best-practices)
- [Verificar consistency](#verificar-consistency)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Desnormalização:**
Você adiciona redundância de propósito numa base já normalizada. Objetivo: leitura mais rápida.

**Para quê:**
- Menos JOIN (SELECT mais rápido)
- Agregações pré-computadas
- Menos carga no banco

**Trade-off:**
- ✅ SELECT mais rápido
- ❌ INSERT/UPDATE mais lento
- ❌ Risco de inconsistency (precisa sincronizar)

---

## Tipos de desnormalização

### 1. Duplicar colunas

**Problema: JOIN em todo request**

```php
// Normalized (3NF)
$orders = Order::with('customer')->get();

foreach ($orders as $order) {
    echo $order->customer->name;  // JOIN toda vez
}
```

**Solução: duplicar customer_name**

```php
// Migration
Schema::table('orders', function (Blueprint $table) {
    $table->string('customer_name')->after('customer_id');
});

// Na criação do pedido
Order::create([
    'customer_id' => $customer->id,
    'customer_name' => $customer->name,  // duplicação
    'total' => 100,
]);

// Query SEM JOIN
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer_name;  // sem JOIN!
}
```

**Sincronizar quando muda:**

```php
class Customer extends Model
{
    protected static function booted()
    {
        static::updated(function ($customer) {
            // Atualiza customer_name em todos os pedidos
            Order::where('customer_id', $customer->id)
                ->update(['customer_name' => $customer->name]);
        });
    }
}
```

---

### 2. Agregações pré-computadas

**Problema: COUNT/SUM em todo request**

```php
// Normalized: conta toda vez
class User extends Model
{
    public function getOrdersCountAttribute()
    {
        return $this->orders()->count();  // SELECT COUNT(*)
    }
}
```

**Solução: guardar o counter**

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->integer('orders_count')->default(0);
    $table->decimal('total_spent', 10, 2)->default(0);
});

// Observer para atualizar sozinho
class OrderObserver
{
    public function created(Order $order)
    {
        $order->user->increment('orders_count');
        $order->user->increment('total_spent', $order->total);
    }

    public function deleted(Order $order)
    {
        $order->user->decrement('orders_count');
        $order->user->decrement('total_spent', $order->total);
    }
}

// Registrar o observer
Order::observe(OrderObserver::class);

// Uso (SEM query)
$user = User::find(1);
echo $user->orders_count;  // sem SELECT COUNT(*)!
echo $user->total_spent;
```

---

### 3. Tabelas resumo (summary tables)

**Problema: agregações pesadas**

```sql
-- Calcular toda vez (lento)
SELECT
    DATE_TRUNC('day', created_at) as date,
    COUNT(*) as orders_count,
    SUM(total) as revenue
FROM orders
WHERE created_at >= NOW() - INTERVAL '30 days'
GROUP BY DATE_TRUNC('day', created_at);
```

**Solução: summary table**

```php
// Migration
Schema::create('daily_stats', function (Blueprint $table) {
    $table->date('date')->primary();
    $table->integer('orders_count')->default(0);
    $table->decimal('revenue', 12, 2)->default(0);
    $table->timestamps();
});

// Job para atualizar (hourly)
class UpdateDailyStats extends Command
{
    public function handle()
    {
        $today = now()->toDateString();

        $stats = Order::whereDate('created_at', $today)
            ->selectRaw('COUNT(*) as orders_count, SUM(total) as revenue')
            ->first();

        DailyStat::updateOrCreate(
            ['date' => $today],
            [
                'orders_count' => $stats->orders_count,
                'revenue' => $stats->revenue,
            ]
        );
    }
}

// Scheduler
$schedule->command('stats:update-daily')->hourly();

// Uso (rápido!)
$stats = DailyStat::where('date', '>=', now()->subDays(30))->get();
```

---

### 4. Materialized Views

**Ver o tópico 9.7 Materialized Views**

---

### 5. JSONB para campos flexíveis

**Problema: muitos campos opcionais**

```sql
-- Normalized (muitos NULL)
CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    color VARCHAR(50),      -- NULL se não for roupa
    size VARCHAR(10),       -- NULL se não for roupa
    cpu VARCHAR(50),        -- NULL se não for eletrônico
    ram VARCHAR(20),        -- NULL se não for eletrônico
    storage VARCHAR(50)     -- NULL se não for eletrônico
);
```

**Solução: JSONB**

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->jsonb('attributes');  // campos flexíveis
});

// Clothing
Product::create([
    'name' => 'T-Shirt',
    'attributes' => [
        'color' => 'blue',
        'size' => 'L',
        'material' => 'cotton',
    ],
]);

// Electronics
Product::create([
    'name' => 'Laptop',
    'attributes' => [
        'brand' => 'Dell',
        'cpu' => 'Intel i7',
        'ram' => '16GB',
    ],
]);
```

---

## Exemplos práticos

### 1. Posts com counter cache

**Problema:**

```php
// COUNT toda vez (lento)
class Post extends Model
{
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}

$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments()->count();  // N+1 queries!
}
```

**Solução:**

```php
// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->integer('comments_count')->default(0);
});

// Observer
class CommentObserver
{
    public function created(Comment $comment)
    {
        $comment->post->increment('comments_count');
    }

    public function deleted(Comment $comment)
    {
        $comment->post->decrement('comments_count');
    }
}

// Uso (SEM queries)
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments_count;  // 0 queries!
}
```

---

### 2. E-commerce: totais do pedido

**Problema:**

```php
// Calcular total toda vez
class Order extends Model
{
    public function getTotalAttribute()
    {
        return $this->items()->sum('price * quantity');  // SUM query
    }
}
```

**Solução:**

```php
// Migration
Schema::table('orders', function (Blueprint $table) {
    $table->decimal('total', 10, 2)->default(0);
});

// Observer
class OrderItemObserver
{
    public function created(OrderItem $item)
    {
        $this->recalculateTotal($item->order);
    }

    public function updated(OrderItem $item)
    {
        $this->recalculateTotal($item->order);
    }

    public function deleted(OrderItem $item)
    {
        $this->recalculateTotal($item->order);
    }

    private function recalculateTotal(Order $order)
    {
        $total = $order->items()
            ->selectRaw('SUM(price * quantity) as total')
            ->value('total');

        $order->update(['total' => $total ?? 0]);
    }
}

// Uso
$order = Order::find(1);
echo $order->total;  // sem SUM query!
```

---

### 3. Full-text search com desnormalização

**Problema:**

```sql
-- Busca em title + body + tags (JOIN + concat)
SELECT posts.*
FROM posts
LEFT JOIN tags ON posts.id = tags.post_id
WHERE
    posts.title ILIKE '%keyword%' OR
    posts.body ILIKE '%keyword%' OR
    tags.name ILIKE '%keyword%';
```

**Solução: search_vector**

```php
// Migration
Schema::table('posts', function (Blueprint $table) {
    $table->text('search_text');  // busca desnormalizada
    $table->index('search_text', null, 'gin');
});

// Observer
class PostObserver
{
    public function saved(Post $post)
    {
        // Junta todos os campos searchable
        $searchText = implode(' ', [
            $post->title,
            $post->body,
            $post->tags->pluck('name')->implode(' '),
        ]);

        $post->updateQuietly(['search_text' => $searchText]);
    }
}

// Uso (busca rápida)
Post::whereRaw("search_text ILIKE ?", ["%$keyword%"])->get();
```

---

## Riscos da desnormalização

### 1. Data Inconsistency

**Problema:**

```php
// Se esquecer de atualizar o campo desnormalizado
Order::where('customer_id', 1)->update([
    'total' => 200,
    // Esqueceu de atualizar customer.total_spent!
]);
```

**Solução: Observers**

```php
class OrderObserver
{
    public function updated(Order $order)
    {
        // Recalcula sozinho
        if ($order->isDirty('total')) {
            $this->recalculateCustomerTotalSpent($order->customer);
        }
    }
}
```

---

### 2. WRITE lento

**Problema:**

```php
// Cada INSERT atualiza o counter
Comment::create([...]);  // + UPDATE posts.comments_count
```

**Solução: Queue para bulk updates**

```php
// Em vez de update imediato
class Comment extends Model
{
    protected static function booted()
    {
        static::created(function ($comment) {
            // Adia o update do counter
            UpdatePostCommentsCount::dispatch($comment->post_id)->delay(60);
        });
    }
}
```

---

## Quando desnormalizar

```
✓ Workload read-heavy (SELECT >> INSERT/UPDATE)
✓ JOIN caro
✓ Agregações pesadas (COUNT, SUM)
✓ Dashboards, reports
✓ Full-text search
✓ Performance crítica
```

---

## Quando NÃO desnormalizar

```
❌ Workload write-heavy
❌ Strong consistency é crítica
❌ Dados mudam o tempo todo
❌ Banco pequeno (sem problema de performance)
```

---

## Best Practices

```
✓ Desnormalização = trade-off (velocidade vs consistency)
✓ Use Observers para sincronizar sozinho
✓ Só desnormalize DEPOIS de perfilar (não cedo demais)
✓ Documente os campos desnormalizados
✓ Scheduled jobs para checar consistency
✓ Logue divergência (monitoring)
✓ Materialized Views para agregações pesadas
```

---

## Verificar consistency

```php
// Comando Artisan para checar
class CheckCountersConsistency extends Command
{
    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            $actualCount = $user->orders()->count();
            $cachedCount = $user->orders_count;

            if ($actualCount !== $cachedCount) {
                $this->error("User {$user->id}: esperado {$actualCount}, veio {$cachedCount}");

                // Fix
                $user->update(['orders_count' => $actualCount]);
            }
        }
    }
}

// Scheduler: checa uma vez por dia
$schedule->command('check:counters-consistency')->daily();
```

---

## Exercícios práticos

### Exercício 1: Implementar counter cache

**Enunciado:** Você tem um blog com posts e comentários. Toda vez que lista os posts, roda N+1 para contar comentários. Otimize com desnormalização.

<details>
<summary>Solução</summary>

```php
// Migration: adicionar counter cache
Schema::table('posts', function (Blueprint $table) {
    $table->integer('comments_count')->default(0)->after('body');
    $table->index('comments_count');
});

// Observer para atualizar sozinho
class CommentObserver
{
    public function created(Comment $comment)
    {
        $comment->post->increment('comments_count');
    }

    public function deleted(Comment $comment)
    {
        $comment->post->decrement('comments_count');
    }

    public function restored(Comment $comment)
    {
        $comment->post->increment('comments_count');
    }
}

// No AppServiceProvider
public function boot()
{
    Comment::observe(CommentObserver::class);
}

// ANTES: N+1 queries
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments()->count(); // SELECT COUNT(*)
}

// DEPOIS: 1 query
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->comments_count; // 0 queries!
}

// Comando para recalcular (se dessincronizar)
class RecalculateCommentsCount extends Command
{
    protected $signature = 'posts:recalculate-comments';

    public function handle()
    {
        Post::query()->chunkById(100, function ($posts) {
            foreach ($posts as $post) {
                $count = $post->comments()->count();
                $post->update(['comments_count' => $count]);
            }
        });

        $this->info('Contagem de comentários recalculada!');
    }
}
```

**Pontos-chave:**
- Evitou o N+1
- Lista de posts rápida
- Dá para ordenar por popularidade
</details>

---

### Exercício 2: Desnormalizar para evitar JOIN

**Enunciado:** A tabela de pedidos sempre mostra o nome do cliente. Todo request faz JOIN. Otimize.

<details>
<summary>Solução</summary>

```php
// Migration: adicionar customer_name
Schema::table('orders', function (Blueprint $table) {
    $table->string('customer_name')->after('customer_id');
    $table->index(['customer_id', 'customer_name']);
});

// Observer para sincronizar
class CustomerObserver
{
    public function updated(Customer $customer)
    {
        // Se o nome mudou, atualiza todos os pedidos
        if ($customer->isDirty('name')) {
            Order::where('customer_id', $customer->id)
                ->update(['customer_name' => $customer->name]);
        }
    }
}

// Na criação do pedido
class CreateOrderAction
{
    public function execute(Customer $customer, array $items)
    {
        return Order::create([
            'customer_id' => $customer->id,
            'customer_name' => $customer->name, // duplicamos
            'total' => $this->calculateTotal($items),
        ]);
    }
}

// ANTES: JOIN em todo request
$orders = Order::with('customer')->get();
foreach ($orders as $order) {
    echo $order->customer->name; // JOIN
}

// DEPOIS: sem JOIN
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer_name; // sem JOIN!
}

// Endpoint de API (mais rápido)
Route::get('/orders', function () {
    return Order::select('id', 'customer_name', 'total', 'created_at')
        ->latest()
        ->paginate(20);
    // Sem JOIN com customers!
});
```

**Trade-offs:**
- ✅ SELECT mais rápido (sem JOIN)
- ✅ Menos carga no banco
- ❌ Ocupa mais espaço
- ❌ Precisa sincronizar no UPDATE de customer.name
</details>

---

### Exercício 3: Summary table para analytics

**Enunciado:** Você precisa de um dashboard com estatística diária de vendas do último ano. GROUP BY em milhões de pedidos toda vez é lento demais. Otimize.

<details>
<summary>Solução</summary>

```php
// Migration: summary table
Schema::create('daily_sales_stats', function (Blueprint $table) {
    $table->date('date')->primary();
    $table->integer('orders_count')->default(0);
    $table->decimal('revenue', 12, 2)->default(0);
    $table->decimal('avg_order_value', 10, 2)->default(0);
    $table->integer('new_customers')->default(0);
    $table->timestamps();
});

// Job para atualizar a estatística
class UpdateDailySalesStats implements ShouldQueue
{
    public function handle()
    {
        $yesterday = now()->subDay()->toDateString();

        // Junta a estatística de ontem
        $stats = Order::whereDate('created_at', $yesterday)
            ->selectRaw('
                COUNT(*) as orders_count,
                SUM(total) as revenue,
                AVG(total) as avg_order_value
            ')
            ->first();

        $newCustomers = Customer::whereDate('created_at', $yesterday)->count();

        DailySalesStat::updateOrCreate(
            ['date' => $yesterday],
            [
                'orders_count' => $stats->orders_count ?? 0,
                'revenue' => $stats->revenue ?? 0,
                'avg_order_value' => $stats->avg_order_value ?? 0,
                'new_customers' => $newCustomers,
            ]
        );
    }
}

// Scheduler: roda toda noite
protected function schedule(Schedule $schedule)
{
    $schedule->job(new UpdateDailySalesStats)->dailyAt('01:00');
}

// Controller: dashboard rápido
class DashboardController extends Controller
{
    public function index()
    {
        // ANTES: query lenta em milhões de pedidos
        // $stats = Order::where('created_at', '>=', now()->subYear())
        //     ->groupBy(DB::raw('DATE(created_at)'))
        //     ->select(...)
        //     ->get();

        // DEPOIS: query rápida na summary table
        $stats = DailySalesStat::where('date', '>=', now()->subYear())
            ->orderBy('date')
            ->get();

        return view('dashboard', compact('stats'));
    }
}

// Endpoint de API
Route::get('/api/stats/monthly', function () {
    return DailySalesStat::selectRaw('
            DATE_TRUNC(\'month\', date) as month,
            SUM(orders_count) as total_orders,
            SUM(revenue) as total_revenue
        ')
        ->where('date', '>=', now()->subYear())
        ->groupBy('month')
        ->get();
    // Super rápido!
});
```

**Pontos-chave:**
- Dashboard carrega na hora
- Sem carga na tabela principal orders
- Dá para acrescentar métricas
- Histórico fica guardado
</details>

---

## Na entrevista

> "Desnormalização é adicionar redundância para leitura mais rápida. Tipos: duplicar colunas (evitar JOIN), agregações pré-computadas (counter cache), summary tables, JSONB para campos flexíveis. Trade-off: SELECT mais rápido, INSERT/UPDATE mais lento, risco de inconsistency. Solução: Observers para sincronizar sozinho, scheduled jobs para checar consistency. Quando usar: workload read-heavy, JOIN caro, dashboards. Quando não: write-heavy, strong consistency crítica. Best practices: perfilar antes de desnormalizar, documentar, monitorar consistency."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
