# 9.3 Window Functions (funções de janela)

> **TL;DR:** Window Functions calculam sobre uma janela de linhas, sem GROUP BY. ROW_NUMBER dá número único, RANK pula posição, PARTITION BY separa em grupos. Running total com SUM() OVER, moving average com ROWS BETWEEN. LAG/LEAD pega a linha anterior/próxima. Casos de uso: tops, soma acumulada, deduplicate.

## Conteúdo

- [O que é](#o-que-é)
- [Sintaxe](#sintaxe)
- [ROW_NUMBER, RANK, DENSE_RANK](#row_number-rank-dense_rank)
  - [ROW_NUMBER](#row_number)
  - [RANK](#rank)
  - [DENSE_RANK](#dense_rank)
- [PARTITION BY](#partition-by)
- [Running Totals](#running-totals-soma-acumulada)
- [Moving Average](#moving-average-média-móvel)
- [LAG e LEAD](#lag-e-lead)
- [FIRST_VALUE e LAST_VALUE](#first_value-e-last_value)
- [NTILE](#ntile-dividir-em-n-grupos)
- [Exemplos práticos](#exemplos-práticos)
- [Frame Clause](#frame-clause)
- [Performance](#performance)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Window Functions:**
Funções SQL que calculam sobre um conjunto de linhas (janela) ligado à linha atual, sem agrupar o resultado.

**Diferença do GROUP BY:**
- **GROUP BY** junta as linhas em uma
- **Window Functions** mantêm todas as linhas e acrescentam colunas calculadas

**Para quê:**
- Ranking (tops, rankings)
- Running totals (somas acumuladas)
- Moving averages
- Row numbering
- Lag/Lead (comparar com a linha anterior/próxima)

---

## Sintaxe

```sql
function_name([args]) OVER (
    [PARTITION BY column]
    [ORDER BY column]
    [ROWS/RANGE frame_clause]
)
```

**Componentes:**
- **PARTITION BY** — separa em grupos (como GROUP BY, mas não junta as linhas)
- **ORDER BY** — ordem dentro da janela
- **ROWS/RANGE** — frame da janela (padrão: do início até a linha atual)

---

## ROW_NUMBER, RANK, DENSE_RANK

### ROW_NUMBER()

**Número único para cada linha:**

```sql
SELECT
    name,
    salary,
    ROW_NUMBER() OVER (ORDER BY salary DESC) as row_num
FROM employees;

-- name       salary  row_num
-- Alice      5000    1
-- Bob        4000    2
-- Charlie    4000    3  ← número único, mesmo com salary igual
-- David      3000    4
```

---

### RANK()

**Rank com pulos quando o valor é igual:**

```sql
SELECT
    name,
    salary,
    RANK() OVER (ORDER BY salary DESC) as rank
FROM employees;

-- name       salary  rank
-- Alice      5000    1
-- Bob        4000    2
-- Charlie    4000    2  ← mesmo rank
-- David      3000    4  ← pula o 3
```

---

### DENSE_RANK()

**Rank sem pulos:**

```sql
SELECT
    name,
    salary,
    DENSE_RANK() OVER (ORDER BY salary DESC) as dense_rank
FROM employees;

-- name       salary  dense_rank
-- Alice      5000    1
-- Bob        4000    2
-- Charlie    4000    2  ← mesmo rank
-- David      3000    3  ← sem pulo
```

---

## PARTITION BY

**Uma janela por grupo:**

```sql
-- Top 3 salários em cada departamento
SELECT
    department,
    name,
    salary,
    ROW_NUMBER() OVER (
        PARTITION BY department
        ORDER BY salary DESC
    ) as rank_in_dept
FROM employees;

-- department  name     salary  rank_in_dept
-- Sales       Alice    5000    1
-- Sales       Bob      4000    2
-- Sales       Charlie  3000    3
-- IT          David    6000    1
-- IT          Eve      5500    2
-- IT          Frank    5000    3
```

**Laravel Eloquent:**

```php
// Top 3 produtos em cada categoria
DB::table('products')
    ->select([
        'category_id',
        'name',
        'price',
        DB::raw('ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY price DESC) as rank')
    ])
    ->get()
    ->filter(fn($p) => $p->rank <= 3);
```

---

## Running Totals (soma acumulada)

```sql
SELECT
    date,
    revenue,
    SUM(revenue) OVER (
        ORDER BY date
        ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
    ) as running_total
FROM daily_sales
ORDER BY date;

-- date        revenue  running_total
-- 2024-01-01  100      100
-- 2024-01-02  150      250  ← 100 + 150
-- 2024-01-03  200      450  ← 100 + 150 + 200
-- 2024-01-04  120      570  ← 100 + 150 + 200 + 120
```

**Forma curta:**

```sql
SUM(revenue) OVER (ORDER BY date)
-- Por padrão: do início até a linha atual
```

**Laravel:**

```php
DB::table('daily_sales')
    ->select([
        'date',
        'revenue',
        DB::raw('SUM(revenue) OVER (ORDER BY date) as running_total')
    ])
    ->orderBy('date')
    ->get();
```

---

## Moving Average (média móvel)

**Média dos últimos 3 dias:**

```sql
SELECT
    date,
    price,
    AVG(price) OVER (
        ORDER BY date
        ROWS BETWEEN 2 PRECEDING AND CURRENT ROW
    ) as moving_avg_3days
FROM stock_prices
ORDER BY date;

-- date        price  moving_avg_3days
-- 2024-01-01  100    100           ← avg(100)
-- 2024-01-02  110    105           ← avg(100, 110)
-- 2024-01-03  120    110           ← avg(100, 110, 120)
-- 2024-01-04  130    120           ← avg(110, 120, 130)
-- 2024-01-05  140    130           ← avg(120, 130, 140)
```

---

## LAG e LEAD

### LAG (linha anterior)

```sql
SELECT
    date,
    revenue,
    LAG(revenue, 1) OVER (ORDER BY date) as prev_day_revenue,
    revenue - LAG(revenue, 1) OVER (ORDER BY date) as change
FROM daily_sales
ORDER BY date;

-- date        revenue  prev_day  change
-- 2024-01-01  100      NULL      NULL
-- 2024-01-02  150      100       50   ← 150 - 100
-- 2024-01-03  120      150       -30  ← 120 - 150
```

---

### LEAD (próxima linha)

```sql
SELECT
    date,
    revenue,
    LEAD(revenue, 1) OVER (ORDER BY date) as next_day_revenue
FROM daily_sales
ORDER BY date;

-- date        revenue  next_day
-- 2024-01-01  100      150
-- 2024-01-02  150      120
-- 2024-01-03  120      NULL
```

---

## FIRST_VALUE e LAST_VALUE

```sql
SELECT
    department,
    name,
    salary,
    FIRST_VALUE(name) OVER (
        PARTITION BY department
        ORDER BY salary DESC
    ) as highest_paid,
    LAST_VALUE(name) OVER (
        PARTITION BY department
        ORDER BY salary DESC
        ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
    ) as lowest_paid
FROM employees;

-- department  name    salary  highest_paid  lowest_paid
-- Sales       Alice   5000    Alice         Charlie
-- Sales       Bob     4000    Alice         Charlie
-- Sales       Charlie 3000    Alice         Charlie
```

---

## NTILE (dividir em N grupos)

```sql
-- Dividir usuários em 4 grupos (quartis) por atividade
SELECT
    user_id,
    activity_score,
    NTILE(4) OVER (ORDER BY activity_score DESC) as quartile
FROM users;

-- user_id  activity_score  quartile
-- 101      1000            1  ← top 25%
-- 102      900             1
-- 103      800             2
-- 104      700             2
-- 105      600             3
-- 106      500             3
-- 107      400             4  ← bottom 25%
-- 108      300             4
```

**Use case:** A/B testing, segmentação de usuários.

---

## Exemplos práticos

### 1. Top 5 produtos em cada categoria

```sql
WITH ranked_products AS (
    SELECT
        category_id,
        product_id,
        name,
        sales,
        ROW_NUMBER() OVER (
            PARTITION BY category_id
            ORDER BY sales DESC
        ) as rank
    FROM products
)
SELECT *
FROM ranked_products
WHERE rank <= 5;
```

**Laravel:**

```php
DB::table(DB::raw('(
    SELECT
        category_id,
        product_id,
        name,
        sales,
        ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY sales DESC) as rank
    FROM products
) as ranked'))
->where('rank', '<=', 5)
->get();
```

---

### 2. Percentual do total

```sql
SELECT
    product_id,
    sales,
    ROUND(sales * 100.0 / SUM(sales) OVER (), 2) as percent_of_total
FROM products;

-- product_id  sales  percent_of_total
-- 1           100    10.00%
-- 2           200    20.00%
-- 3           300    30.00%
-- 4           400    40.00%
-- Total: 1000
```

---

### 3. Encontrar gaps (buracos)

```sql
-- Encontrar datas faltando
WITH dates AS (
    SELECT
        date,
        LEAD(date) OVER (ORDER BY date) as next_date
    FROM sales
)
SELECT
    date as missing_after,
    next_date as next_available
FROM dates
WHERE next_date - date > INTERVAL '1 day';
```

---

### 4. Deduplicate (remover duplicatas)

```sql
-- Ficar só com o último registro de cada user
WITH ranked AS (
    SELECT
        *,
        ROW_NUMBER() OVER (
            PARTITION BY user_id
            ORDER BY created_at DESC
        ) as rn
    FROM user_actions
)
SELECT *
FROM ranked
WHERE rn = 1;
```

**Laravel:**

```php
DB::table(DB::raw('(
    SELECT
        *,
        ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
    FROM user_actions
) as ranked'))
->where('rn', 1)
->get();
```

---

### 5. Comparar com o período anterior

```sql
SELECT
    month,
    revenue,
    LAG(revenue, 1) OVER (ORDER BY month) as prev_month,
    ROUND(
        (revenue - LAG(revenue, 1) OVER (ORDER BY month)) * 100.0 /
        LAG(revenue, 1) OVER (ORDER BY month),
        2
    ) as growth_percent
FROM monthly_revenue
ORDER BY month;

-- month     revenue  prev_month  growth_percent
-- 2024-01   1000     NULL        NULL
-- 2024-02   1200     1000        20.00%
-- 2024-03   1100     1200        -8.33%
```

---

## Frame Clause

**ROWS vs RANGE:**

```sql
-- ROWS: linhas físicas
ROWS BETWEEN 2 PRECEDING AND CURRENT ROW

-- RANGE: intervalo lógico (pelo valor)
RANGE BETWEEN INTERVAL '7 days' PRECEDING AND CURRENT ROW
```

**Exemplos de frame:**

```sql
-- Do início até a linha atual (padrão)
ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW

-- Últimas 3 linhas
ROWS BETWEEN 2 PRECEDING AND CURRENT ROW

-- A atual e as 2 seguintes
ROWS BETWEEN CURRENT ROW AND 2 FOLLOWING

-- Todas as linhas do partition
ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
```

---

## Performance

**Índices:**

```sql
-- Window function usa ORDER BY
-- Precisa de índice na coluna do ORDER BY
CREATE INDEX idx_sales_date ON sales(date);

-- Com PARTITION BY precisa de índice composto
CREATE INDEX idx_products_category_sales ON products(category_id, sales DESC);
```

**Materialized View para window queries pesadas:**

```sql
CREATE MATERIALIZED VIEW product_rankings AS
SELECT
    category_id,
    product_id,
    sales,
    ROW_NUMBER() OVER (PARTITION BY category_id ORDER BY sales DESC) as rank
FROM products;

CREATE INDEX ON product_rankings(category_id, rank);

-- Atualizar de tempos em tempos
REFRESH MATERIALIZED VIEW product_rankings;
```

---

## Exercícios práticos

### Exercício 1: Top de produtos em cada categoria

**Enunciado:** Traga os 3 produtos mais caros de cada categoria.

<details>
<summary>Solução</summary>

```php
// Solução SQL
$topProducts = DB::select("
    WITH ranked_products AS (
        SELECT
            p.id,
            p.name,
            p.price,
            c.name as category_name,
            ROW_NUMBER() OVER (
                PARTITION BY p.category_id
                ORDER BY p.price DESC
            ) as rank
        FROM products p
        JOIN categories c ON p.category_id = c.id
    )
    SELECT *
    FROM ranked_products
    WHERE rank <= 3
    ORDER BY category_name, rank
");

// Laravel Eloquent (via subquery)
$products = DB::table(DB::raw('(
    SELECT
        products.*,
        categories.name as category_name,
        ROW_NUMBER() OVER (
            PARTITION BY products.category_id
            ORDER BY products.price DESC
        ) as rank
    FROM products
    JOIN categories ON products.category_id = categories.id
) as ranked'))
->where('rank', '<=', 3)
->orderBy('category_name')
->orderBy('rank')
->get();

// Agrupar por categoria
$grouped = collect($products)->groupBy('category_name');

foreach ($grouped as $category => $items) {
    echo "Categoria: {$category}\n";
    foreach ($items as $product) {
        echo "  {$product->rank}. {$product->name} - R$ {$product->price}\n";
    }
}
```

</details>

### Exercício 2: Soma acumulada de vendas

**Enunciado:** Calcule a soma acumulada de vendas por dia e o percentual do total.

<details>
<summary>Solução</summary>

```php
// SQL com running total e percentual
$salesReport = DB::select("
    SELECT
        date,
        daily_revenue,
        SUM(daily_revenue) OVER (
            ORDER BY date
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) as running_total,
        ROUND(
            daily_revenue * 100.0 / SUM(daily_revenue) OVER (),
            2
        ) as percent_of_total
    FROM (
        SELECT
            DATE(created_at) as date,
            SUM(total) as daily_revenue
        FROM orders
        WHERE created_at >= NOW() - INTERVAL '30 days'
        GROUP BY DATE(created_at)
    ) daily_sales
    ORDER BY date
");

// Laravel Query Builder
$report = DB::table(DB::raw('(
    SELECT
        DATE(created_at) as date,
        SUM(total) as daily_revenue
    FROM orders
    WHERE created_at >= NOW() - INTERVAL \'30 days\'
    GROUP BY DATE(created_at)
) as daily_sales'))
->selectRaw("
    date,
    daily_revenue,
    SUM(daily_revenue) OVER (ORDER BY date) as running_total,
    ROUND(daily_revenue * 100.0 / SUM(daily_revenue) OVER (), 2) as percent_of_total
")
->orderBy('date')
->get();

// Formatar para o relatório
foreach ($report as $row) {
    echo sprintf(
        "%s: R$ %s (Acumulado: R$ %s, %s%%)\n",
        $row->date,
        number_format($row->daily_revenue, 2),
        number_format($row->running_total, 2),
        $row->percent_of_total
    );
}
```

</details>

### Exercício 3: Comparar com o período anterior (MoM Growth)

**Enunciado:** Calcule o crescimento mensal da receita (Month-over-Month growth).

<details>
<summary>Solução</summary>

```php
// SQL com LAG para comparar com o mês anterior
$monthlyGrowth = DB::select("
    SELECT
        month,
        revenue,
        LAG(revenue, 1) OVER (ORDER BY month) as prev_month_revenue,
        revenue - LAG(revenue, 1) OVER (ORDER BY month) as revenue_change,
        CASE
            WHEN LAG(revenue, 1) OVER (ORDER BY month) IS NULL THEN NULL
            ELSE ROUND(
                (revenue - LAG(revenue, 1) OVER (ORDER BY month)) * 100.0 /
                LAG(revenue, 1) OVER (ORDER BY month),
                2
            )
        END as growth_percent
    FROM (
        SELECT
            DATE_TRUNC('month', created_at) as month,
            SUM(total) as revenue
        FROM orders
        WHERE created_at >= NOW() - INTERVAL '12 months'
        GROUP BY DATE_TRUNC('month', created_at)
    ) monthly_revenue
    ORDER BY month
");

// Artisan Command para o relatório
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonthlyGrowthReport extends Command
{
    protected $signature = 'report:monthly-growth';

    public function handle()
    {
        $data = DB::select("
            SELECT
                TO_CHAR(month, 'YYYY-MM') as month,
                revenue,
                LAG(revenue, 1) OVER (ORDER BY month) as prev_month,
                ROUND(
                    (revenue - LAG(revenue, 1) OVER (ORDER BY month)) * 100.0 /
                    NULLIF(LAG(revenue, 1) OVER (ORDER BY month), 0),
                    2
                ) as growth_percent
            FROM (
                SELECT
                    DATE_TRUNC('month', created_at) as month,
                    SUM(total) as revenue
                FROM orders
                WHERE created_at >= NOW() - INTERVAL '12 months'
                GROUP BY DATE_TRUNC('month', created_at)
            ) monthly_revenue
            ORDER BY month
        ");

        $this->table(
            ['Mês', 'Receita', 'Mês anterior', 'Crescimento %'],
            array_map(function ($row) {
                return [
                    $row->month,
                    'R$ ' . number_format($row->revenue, 2),
                    $row->prev_month ? 'R$ ' . number_format($row->prev_month, 2) : '-',
                    $row->growth_percent ? $row->growth_percent . '%' : '-',
                ];
            }, $data)
        );
    }
}
```

</details>

---

## Na entrevista

> "Window Functions calculam sobre uma janela de linhas, sem GROUP BY. Sintaxe: function OVER (PARTITION BY, ORDER BY, ROWS). ROW_NUMBER para número único, RANK com pulos, DENSE_RANK sem pulos. PARTITION BY separa em grupos. Running total com SUM() OVER (ORDER BY). Moving average com ROWS BETWEEN N PRECEDING AND CURRENT ROW. LAG/LEAD para a linha anterior/próxima. Casos de uso: top por categoria, soma acumulada, percentual do total, deduplicate. Índice nas colunas do ORDER BY para performance."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
