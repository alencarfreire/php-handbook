# 9.7 Materialized Views

> **TL;DR:** Materialized View é o resultado de uma query, gravado fisicamente no disco. REFRESH MATERIALIZED VIEW atualiza, CONCURRENTLY não bloqueia SELECT. Casos de uso: estatística de dashboard, agregação pesada, relatório. No Laravel: criar com DB::statement, refresh pelo Scheduler. MySQL não tem — workaround com tabela + scheduled job.

## Conteúdo

- [O que é](#o-que-é)
- [Criar Materialized View](#criar-materialized-view)
- [Refresh da Materialized View](#refresh-da-materialized-view)
- [Refresh automático](#refresh-automático)
- [Casos de uso](#casos-de-uso)
- [Update incremental](#update-incremental-postgresql-13)
- [Triggers para refresh](#triggers-para-refresh)
- [MySQL](#mysql)
- [Boas práticas](#boas-práticas)
- [Alternativas](#alternativas)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Materialized View (visão materializada):**
Resultado de uma query, guardado como tabela. Os dados ficam fisicamente no disco.

**View vs Materialized View:**
- **View** (comum): virtual, a query roda toda vez
- **Materialized View**: física, os dados ficam em cache

**Para quê:**
- Acelerar query pesada (aggregations, joins)
- Pré-calcular operação cara
- Relatório e analytics

**Trade-off:**
- ✅ SELECT rápido
- ❌ Dado pode ficar velho (precisa de REFRESH)
- ❌ Ocupa espaço em disco

---

## Criar Materialized View

**PostgreSQL:**

```sql
CREATE MATERIALIZED VIEW product_stats AS
SELECT
    category_id,
    COUNT(*) as products_count,
    AVG(price) as avg_price,
    SUM(stock) as total_stock
FROM products
GROUP BY category_id;

-- Criar índice
CREATE INDEX ON product_stats(category_id);
```

**Laravel Migration:**

```php
Schema::create('product_stats', function (Blueprint $table) {
    DB::statement("
        CREATE MATERIALIZED VIEW product_stats AS
        SELECT
            category_id,
            COUNT(*) as products_count,
            AVG(price) as avg_price,
            SUM(stock) as total_stock
        FROM products
        GROUP BY category_id
    ");

    DB::statement("CREATE INDEX ON product_stats(category_id)");
});
```

---

## Refresh da Materialized View

**Refresh manual:**

```sql
REFRESH MATERIALIZED VIEW product_stats;
```

**Concurrent Refresh (não bloqueia SELECT):**

```sql
REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats;
-- Precisa de índice UNIQUE
```

**Laravel:**

```php
// Na command ou no job
DB::statement('REFRESH MATERIALIZED VIEW product_stats');

// Concurrent
DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats');
```

---

## Refresh automático

**Laravel Scheduler:**

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Atualizar a cada hora
    $schedule->call(function () {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats');
    })->hourly();

    // Ou toda noite
    $schedule->command('app:refresh-materialized-views')->daily();
}
```

**Command Artisan:**

```php
// app/Console/Commands/RefreshMaterializedViews.php
class RefreshMaterializedViews extends Command
{
    protected $signature = 'app:refresh-materialized-views';

    public function handle()
    {
        $views = [
            'product_stats',
            'user_analytics',
            'sales_summary',
        ];

        foreach ($views as $view) {
            $this->info("Atualizando {$view}...");

            DB::statement("REFRESH MATERIALIZED VIEW CONCURRENTLY {$view}");

            $this->info("✓ {$view} atualizada");
        }
    }
}
```

---

## Casos de uso

### 1. Estatísticas de dashboard

**Problema: query lenta**

```sql
-- Calcula toda vez (lento)
SELECT
    COUNT(*) as total_users,
    COUNT(CASE WHEN created_at > NOW() - INTERVAL '30 days' THEN 1 END) as new_users,
    COUNT(CASE WHEN last_login > NOW() - INTERVAL '7 days' THEN 1 END) as active_users,
    AVG(orders_count) as avg_orders
FROM users;
```

**Solução: Materialized View**

```sql
CREATE MATERIALIZED VIEW dashboard_stats AS
SELECT
    COUNT(*) as total_users,
    COUNT(CASE WHEN created_at > NOW() - INTERVAL '30 days' THEN 1 END) as new_users,
    COUNT(CASE WHEN last_login > NOW() - INTERVAL '7 days' THEN 1 END) as active_users,
    AVG(orders_count) as avg_orders
FROM users;

-- Refresh a cada hora
```

**Laravel:**

```php
class DashboardController extends Controller
{
    public function index()
    {
        // Rápido (dado já calculado)
        $stats = DB::table('dashboard_stats')->first();

        return view('dashboard', compact('stats'));
    }
}
```

---

### 2. Busca de produto com agregações

**Problema:**

```sql
-- Lento: JOIN + GROUP BY + COUNT
SELECT
    products.*,
    categories.name as category_name,
    AVG(reviews.rating) as avg_rating,
    COUNT(reviews.id) as reviews_count
FROM products
LEFT JOIN categories ON products.category_id = categories.id
LEFT JOIN reviews ON products.id = reviews.product_id
GROUP BY products.id, categories.name;
```

**Solução:**

```sql
CREATE MATERIALIZED VIEW products_with_stats AS
SELECT
    products.id,
    products.name,
    products.price,
    products.stock,
    categories.name as category_name,
    COALESCE(AVG(reviews.rating), 0) as avg_rating,
    COUNT(reviews.id) as reviews_count
FROM products
LEFT JOIN categories ON products.category_id = categories.id
LEFT JOIN reviews ON products.id = reviews.product_id
GROUP BY products.id, categories.name;

CREATE INDEX ON products_with_stats(category_name);
CREATE INDEX ON products_with_stats(avg_rating DESC);
```

**Laravel Model:**

```php
// app/Models/ProductWithStats.php
class ProductWithStats extends Model
{
    protected $table = 'products_with_stats';
    public $timestamps = false;

    // Somente leitura
    public function save(array $options = [])
    {
        throw new Exception('Materialized view é somente leitura');
    }
}

// Uso
$products = ProductWithStats::where('category_name', 'Eletrônicos')
    ->where('avg_rating', '>=', 4.0)
    ->orderBy('avg_rating', 'desc')
    ->paginate(20);
```

---

### 3. Relatório / Analytics

**Relatório mensal de vendas:**

```sql
CREATE MATERIALIZED VIEW monthly_sales AS
SELECT
    DATE_TRUNC('month', created_at) as month,
    user_id,
    users.name as user_name,
    COUNT(*) as orders_count,
    SUM(total) as total_revenue,
    AVG(total) as avg_order_value
FROM orders
JOIN users ON orders.user_id = users.id
WHERE created_at >= NOW() - INTERVAL '12 months'
GROUP BY DATE_TRUNC('month', created_at), user_id, users.name;

CREATE INDEX ON monthly_sales(month);
CREATE INDEX ON monthly_sales(user_id);
```

**Laravel:**

```php
class ReportsController extends Controller
{
    public function monthlySales()
    {
        $sales = DB::table('monthly_sales')
            ->whereBetween('month', [now()->subYear(), now()])
            ->orderBy('month', 'desc')
            ->get();

        return view('reports.monthly-sales', compact('sales'));
    }
}
```

---

### 4. Leaderboard (ranking)

```sql
CREATE MATERIALIZED VIEW user_leaderboard AS
SELECT
    users.id,
    users.name,
    COUNT(orders.id) as orders_count,
    SUM(orders.total) as total_spent,
    ROW_NUMBER() OVER (ORDER BY SUM(orders.total) DESC) as rank
FROM users
LEFT JOIN orders ON users.id = orders.user_id
GROUP BY users.id, users.name
ORDER BY total_spent DESC
LIMIT 100;

-- Refresh diário
```

---

## Update incremental (PostgreSQL 13+)

**Problema:**
REFRESH MATERIALIZED VIEW recalcula TODOS os dados.

**Solução: Incremental Materialized View**

```sql
-- Precisa de: primary key ou unique index
CREATE MATERIALIZED VIEW product_stats AS
SELECT
    category_id,
    COUNT(*) as products_count,
    AVG(price) as avg_price
FROM products
GROUP BY category_id;

CREATE UNIQUE INDEX ON product_stats(category_id);

-- Agora dá para fazer incremental refresh (só o que mudou)
REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats;
```

---

## Triggers para refresh

**Auto-refresh quando o dado muda:**

```sql
-- Function para o refresh
CREATE OR REPLACE FUNCTION refresh_product_stats()
RETURNS TRIGGER AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY product_stats;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Trigger em INSERT/UPDATE/DELETE
CREATE TRIGGER trigger_refresh_product_stats
AFTER INSERT OR UPDATE OR DELETE ON products
FOR EACH STATEMENT
EXECUTE FUNCTION refresh_product_stats();
```

**Problema:**
- ❌ REFRESH depois de CADA mudança (lento)
- ❌ Pode bloquear

**Melhor:**
- ✅ Scheduled refresh (hourly/daily)
- ✅ Refresh manual via job depois de operação em lote

---

## MySQL

**MySQL NÃO tem Materialized Views!**

**Workaround: tabela comum + update agendado**

```php
// Migration: tabela comum
Schema::create('product_stats', function (Blueprint $table) {
    $table->unsignedBigInteger('category_id')->primary();
    $table->integer('products_count');
    $table->decimal('avg_price');
    $table->timestamp('updated_at');
});

// Command para o refresh
class RefreshProductStats extends Command
{
    public function handle()
    {
        DB::table('product_stats')->truncate();

        DB::table('product_stats')->insert(
            DB::table('products')
                ->select([
                    'category_id',
                    DB::raw('COUNT(*) as products_count'),
                    DB::raw('AVG(price) as avg_price'),
                    DB::raw('NOW() as updated_at'),
                ])
                ->groupBy('category_id')
                ->get()
                ->toArray()
        );
    }
}

// Scheduler
$schedule->command('app:refresh-product-stats')->hourly();
```

---

## Boas práticas

```
✓ REFRESH CONCURRENTLY com índice UNIQUE (não bloqueia SELECT)
✓ Scheduler para refresh automático (hourly/daily)
✓ Índices na Materialized View para query rápida
✓ Use em query de agregação read-heavy
✓ NÃO use se o dado precisa ser real-time
✓ Monitore o tamanho (pode ocupar bastante espaço)
✓ Use para relatório, dashboard, leaderboard
✓ No MySQL: workaround com tabela comum + scheduled job
```

---

## Alternativas

**Quando NÃO usar Materialized View:**

### 1. Dado real-time → Cache

```php
// Em vez de Materialized View
Cache::remember('dashboard_stats', 3600, function () {
    return DB::table('users')->select([...])->first();
});
```

### 2. Mudança frequente → Query Optimization

```sql
-- Em vez de Materialized View: adicionar índices
CREATE INDEX ON products(category_id, price);
```

### 3. Query simples → View comum

```sql
-- Se a query é rápida, View comum basta
CREATE VIEW active_users AS
SELECT * FROM users WHERE is_active = true;
```

---

## Exercícios práticos

### Exercício 1: Materialized View de estatísticas do dashboard

**Enunciado:** Criar materialized view para o dashboard com atualização automática.

<details>
<summary>Solução</summary>

```php
// database/migrations/xxxx_create_dashboard_stats_view.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE MATERIALIZED VIEW dashboard_stats AS
            SELECT
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE created_at > NOW() - INTERVAL '30 days') as new_users_30d,
                (SELECT COUNT(*) FROM users WHERE last_login > NOW() - INTERVAL '7 days') as active_users_7d,
                (SELECT COUNT(*) FROM orders) as total_orders,
                (SELECT SUM(total) FROM orders WHERE status = 'completed') as total_revenue,
                (SELECT AVG(total) FROM orders WHERE status = 'completed') as avg_order_value,
                NOW() as updated_at
        ");

        // Índice UNIQUE (para CONCURRENTLY)
        DB::statement("CREATE UNIQUE INDEX ON dashboard_stats (updated_at)");
    }

    public function down()
    {
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS dashboard_stats");
    }
};

// app/Console/Commands/RefreshDashboardStats.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshDashboardStats extends Command
{
    protected $signature = 'stats:refresh-dashboard';
    protected $description = 'Atualiza a materialized view do dashboard';

    public function handle()
    {
        $this->info('Atualizando estatísticas do dashboard...');

        $start = microtime(true);

        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY dashboard_stats');

        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->info("✓ Estatísticas do dashboard atualizadas em {$duration}ms");
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Atualizar a cada 15 minutos
    $schedule->command('stats:refresh-dashboard')->everyFifteenMinutes();
}

// app/Http/Controllers/DashboardController.php
class DashboardController extends Controller
{
    public function index()
    {
        // Rápido! Dado já calculado
        $stats = DB::table('dashboard_stats')->first();

        return response()->json([
            'stats' => $stats,
            'updated_at' => $stats->updated_at,
        ]);
    }
}
```

</details>

### Exercício 2: Ranking de produtos com Materialized View

**Enunciado:** Criar o top de produtos por vendas com atualização periódica.

<details>
<summary>Solução</summary>

```php
// Migration
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE MATERIALIZED VIEW product_rankings AS
            SELECT
                p.id,
                p.name,
                p.category_id,
                c.name as category_name,
                COUNT(oi.id) as orders_count,
                SUM(oi.quantity) as units_sold,
                SUM(oi.price * oi.quantity) as total_revenue,
                AVG(r.rating) as avg_rating,
                COUNT(r.id) as reviews_count,
                ROW_NUMBER() OVER (ORDER BY SUM(oi.price * oi.quantity) DESC) as overall_rank,
                ROW_NUMBER() OVER (
                    PARTITION BY p.category_id
                    ORDER BY SUM(oi.price * oi.quantity) DESC
                ) as category_rank
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN reviews r ON p.id = r.product_id
            GROUP BY p.id, p.name, p.category_id, c.name
        ");

        // Índices para query rápida
        DB::statement("CREATE UNIQUE INDEX ON product_rankings (id)");
        DB::statement("CREATE INDEX ON product_rankings (category_id)");
        DB::statement("CREATE INDEX ON product_rankings (overall_rank)");
        DB::statement("CREATE INDEX ON product_rankings (category_rank)");
    }

    public function down()
    {
        DB::statement("DROP MATERIALIZED VIEW IF EXISTS product_rankings");
    }
};

// app/Models/ProductRanking.php (model somente leitura)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRanking extends Model
{
    protected $table = 'product_rankings';
    public $timestamps = false;

    // Somente leitura
    public function save(array $options = [])
    {
        throw new \Exception('ProductRanking é uma materialized view somente leitura');
    }

    public function delete()
    {
        throw new \Exception('ProductRanking é uma materialized view somente leitura');
    }
}

// app/Http/Controllers/ProductRankingController.php
class ProductRankingController extends Controller
{
    public function topProducts(Request $request)
    {
        $limit = $request->input('limit', 10);

        $products = ProductRanking::orderBy('overall_rank')
            ->limit($limit)
            ->get();

        return response()->json($products);
    }

    public function topByCategory(Request $request, $categoryId)
    {
        $limit = $request->input('limit', 10);

        $products = ProductRanking::where('category_id', $categoryId)
            ->orderBy('category_rank')
            ->limit($limit)
            ->get();

        return response()->json($products);
    }
}

// app/Console/Commands/RefreshProductRankings.php
class RefreshProductRankings extends Command
{
    protected $signature = 'stats:refresh-products';

    public function handle()
    {
        $this->info('Atualizando ranking de produtos...');

        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_rankings');

        $this->info('✓ Ranking de produtos atualizado');
    }
}

// Scheduler: atualizar a cada hora
$schedule->command('stats:refresh-products')->hourly();
```

</details>

### Exercício 3: Alternativa no MySQL (sem Materialized Views)

**Enunciado:** Implementar o equivalente de materialized view no MySQL.

<details>
<summary>Solução</summary>

```php
// Migration (tabela comum no lugar da materialized view)
Schema::create('dashboard_stats_cache', function (Blueprint $table) {
    $table->id();
    $table->integer('total_users');
    $table->integer('new_users_30d');
    $table->integer('active_users_7d');
    $table->integer('total_orders');
    $table->decimal('total_revenue', 15, 2);
    $table->decimal('avg_order_value', 10, 2);
    $table->timestamp('updated_at');
});

// app/Services/DashboardStatsService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function refresh(): void
    {
        $stats = $this->calculateStats();

        // Truncate e insert (para MySQL)
        DB::table('dashboard_stats_cache')->truncate();
        DB::table('dashboard_stats_cache')->insert($stats);
    }

    private function calculateStats(): array
    {
        return [
            'total_users' => DB::table('users')->count(),
            'new_users_30d' => DB::table('users')
                ->where('created_at', '>', now()->subDays(30))
                ->count(),
            'active_users_7d' => DB::table('users')
                ->where('last_login', '>', now()->subDays(7))
                ->count(),
            'total_orders' => DB::table('orders')->count(),
            'total_revenue' => DB::table('orders')
                ->where('status', 'completed')
                ->sum('total') ?? 0,
            'avg_order_value' => DB::table('orders')
                ->where('status', 'completed')
                ->avg('total') ?? 0,
            'updated_at' => now(),
        ];
    }

    public function get(): ?object
    {
        return DB::table('dashboard_stats_cache')->first();
    }
}

// app/Console/Commands/RefreshDashboardStatsMySQL.php
class RefreshDashboardStatsMySQL extends Command
{
    protected $signature = 'stats:refresh-dashboard';

    public function __construct(
        private DashboardStatsService $statsService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Atualizando estatísticas do dashboard...');

        $start = microtime(true);

        $this->statsService->refresh();

        $duration = round((microtime(true) - $start) * 1000, 2);

        $this->info("✓ Estatísticas do dashboard atualizadas em {$duration}ms");
    }
}

// app/Http/Controllers/DashboardController.php
class DashboardController extends Controller
{
    public function __construct(
        private DashboardStatsService $statsService
    ) {}

    public function index()
    {
        $stats = $this->statsService->get();

        if (!$stats) {
            // Primeira vez — calcular e guardar no cache
            $this->statsService->refresh();
            $stats = $this->statsService->get();
        }

        return response()->json([
            'stats' => $stats,
            'updated_at' => $stats->updated_at,
        ]);
    }
}

// Scheduler
$schedule->command('stats:refresh-dashboard')->everyFifteenMinutes();
```

</details>

---

## Na entrevista

> "Materialized View é o resultado de uma query, gravado fisicamente no disco. Diferença da View comum: o dado fica em cache, não recalcula toda vez. REFRESH MATERIALIZED VIEW atualiza, CONCURRENTLY não bloqueia SELECT (precisa de índice UNIQUE). Casos de uso: estatística de dashboard, agregação pesada, relatório, leaderboard. No Laravel: criar com DB::statement na migration, refresh pelo Scheduler (hourly/daily). MySQL não tem — workaround com tabela + scheduled job. Boas práticas: CONCURRENTLY, índices, scheduled refresh, para query read-heavy. Alternativas: Cache para real-time, índices para query simples."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
