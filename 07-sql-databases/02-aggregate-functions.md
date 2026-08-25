# 6.2 Funções de agregação

## Resumo

> **Funções de agregação** — cálculos sobre grupos de linhas (COUNT, SUM, AVG, MIN, MAX). Devolvem um valor por grupo.
>
> **Funções:** COUNT (quantidade), SUM (soma), AVG (média), MIN/MAX (extremos).
>
> **Importante:** GROUP BY agrupa os dados. HAVING filtra depois do agrupamento. WHERE filtra antes.

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
Funções de agregação — cálculos sobre grupos de linhas (COUNT, SUM, AVG, MIN, MAX). Devolvem um valor por grupo.

**Funções principais:**
- COUNT() — quantidade
- SUM() — soma
- AVG() — média
- MIN() — mínimo
- MAX() — máximo

---

## Como funciona

**COUNT (contagem):**

```sql
-- Quantidade total de usuários
SELECT COUNT(*) FROM users;

-- Quantidade de valores não-NULL
SELECT COUNT(email) FROM users;

-- DISTINCT (valores únicos)
SELECT COUNT(DISTINCT status) FROM orders;

-- Com condição
SELECT COUNT(*) FROM users WHERE status = 'active';
```

**SUM (soma):**

```sql
-- Soma de todos os pedidos
SELECT SUM(total) FROM orders;

-- Soma com condição
SELECT SUM(total) FROM orders WHERE status = 'completed';

-- Com GROUP BY
SELECT
    user_id,
    SUM(total) AS total_spent
FROM orders
GROUP BY user_id;
```

**AVG (média):**

```sql
-- Valor médio do pedido
SELECT AVG(total) FROM orders;

-- Idade média dos usuários ativos
SELECT AVG(age) FROM users WHERE status = 'active';

-- Com GROUP BY
SELECT
    status,
    AVG(total) AS avg_total
FROM orders
GROUP BY status;
```

**MIN e MAX:**

```sql
-- Preço mínimo e máximo
SELECT
    MIN(price) AS min_price,
    MAX(price) AS max_price
FROM products;

-- Por categoria
SELECT
    category_id,
    MIN(price) AS min_price,
    MAX(price) AS max_price
FROM products
GROUP BY category_id;

-- Pedido mais antigo e mais novo
SELECT
    MIN(created_at) AS first_order,
    MAX(created_at) AS last_order
FROM orders;
```

**GROUP BY (agrupamento):**

```sql
-- Quantidade de pedidos por status
SELECT
    status,
    COUNT(*) AS count
FROM orders
GROUP BY status;

-- Soma de pedidos por usuário
SELECT
    user_id,
    COUNT(*) AS orders_count,
    SUM(total) AS total_spent,
    AVG(total) AS avg_order
FROM orders
GROUP BY user_id;

-- Agrupamento por vários campos
SELECT
    user_id,
    status,
    COUNT(*) AS count
FROM orders
GROUP BY user_id, status;
```

**HAVING (filtro depois do agrupamento):**

```sql
-- Usuários com mais de 10 pedidos
SELECT
    user_id,
    COUNT(*) AS orders_count
FROM orders
GROUP BY user_id
HAVING orders_count > 10;

-- Categorias com preço médio > 1000
SELECT
    category_id,
    AVG(price) AS avg_price
FROM products
GROUP BY category_id
HAVING avg_price > 1000;

-- Combinação de WHERE e HAVING
SELECT
    user_id,
    COUNT(*) AS orders_count,
    SUM(total) AS total_spent
FROM orders
WHERE status = 'completed'  -- Filtro ANTES do agrupamento
GROUP BY user_id
HAVING total_spent > 5000;  -- Filtro DEPOIS do agrupamento
```

---

## Quando usar

**COUNT:**
- Contar registros
- Quantidade de itens no grupo

**SUM:**
- Soma de valores (vendas, saldos)

**AVG:**
- Médias (ticket médio, nota)

**MIN/MAX:**
- Extremos (mais barato / mais caro)

---

## Exemplo prático

**Estatística de pedidos:**

```sql
-- Estatística geral
SELECT
    COUNT(*) AS total_orders,
    SUM(total) AS total_revenue,
    AVG(total) AS avg_order_value,
    MIN(total) AS min_order,
    MAX(total) AS max_order
FROM orders
WHERE status = 'completed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH);

-- Por usuário
SELECT
    user_id,
    users.name,
    COUNT(orders.id) AS orders_count,
    SUM(orders.total) AS total_spent,
    AVG(orders.total) AS avg_order,
    MAX(orders.total) AS max_order,
    MIN(orders.created_at) AS first_order,
    MAX(orders.created_at) AS last_order
FROM orders
INNER JOIN users ON orders.user_id = users.id
WHERE orders.status = 'completed'
GROUP BY user_id, users.name
HAVING orders_count >= 5
ORDER BY total_spent DESC
LIMIT 100;
```

**Análise de produtos:**

```sql
-- Top produtos por vendas
SELECT
    products.id,
    products.name,
    COUNT(order_items.id) AS times_sold,
    SUM(order_items.quantity) AS total_quantity,
    SUM(order_items.quantity * order_items.price) AS total_revenue
FROM products
INNER JOIN order_items ON products.id = order_items.product_id
INNER JOIN orders ON order_items.order_id = orders.id
WHERE orders.status = 'completed'
  AND orders.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY products.id, products.name
ORDER BY total_revenue DESC
LIMIT 20;
```

**Análise temporal:**

```sql
-- Vendas por dia
SELECT
    DATE(created_at) AS date,
    COUNT(*) AS orders_count,
    SUM(total) AS daily_revenue,
    AVG(total) AS avg_order
FROM orders
WHERE status = 'completed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Por mês
SELECT
    YEAR(created_at) AS year,
    MONTH(created_at) AS month,
    COUNT(*) AS orders_count,
    SUM(total) AS monthly_revenue
FROM orders
WHERE status = 'completed'
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY year DESC, month DESC;

-- Por hora (horários de pico)
SELECT
    HOUR(created_at) AS hour,
    COUNT(*) AS orders_count
FROM orders
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY HOUR(created_at)
ORDER BY orders_count DESC;
```

**Segmentação de usuários:**

```sql
-- Análise RFM (Recency, Frequency, Monetary)
SELECT
    user_id,
    users.name,
    DATEDIFF(NOW(), MAX(orders.created_at)) AS days_since_last_order,
    COUNT(orders.id) AS orders_count,
    SUM(orders.total) AS total_spent,
    CASE
        WHEN DATEDIFF(NOW(), MAX(orders.created_at)) <= 30 THEN 'Ativo'
        WHEN DATEDIFF(NOW(), MAX(orders.created_at)) <= 90 THEN 'Em risco'
        ELSE 'Perdido'
    END AS status_segment,
    CASE
        WHEN SUM(orders.total) > 10000 THEN 'VIP'
        WHEN SUM(orders.total) > 5000 THEN 'Premium'
        ELSE 'Regular'
    END AS value_segment
FROM users
INNER JOIN orders ON users.id = orders.user_id
WHERE orders.status = 'completed'
GROUP BY user_id, users.name
HAVING orders_count > 0;
```

**No Laravel:**

```php
use Illuminate\Support\Facades\DB;

// COUNT, SUM, AVG
$stats = DB::table('orders')
    ->where('status', 'completed')
    ->select(
        DB::raw('COUNT(*) as total_orders'),
        DB::raw('SUM(total) as revenue'),
        DB::raw('AVG(total) as avg_order')
    )
    ->first();

// GROUP BY com agregação
$userStats = DB::table('orders')
    ->select(
        'user_id',
        DB::raw('COUNT(*) as orders_count'),
        DB::raw('SUM(total) as total_spent')
    )
    ->where('status', 'completed')
    ->groupBy('user_id')
    ->having('orders_count', '>', 5)
    ->orderBy('total_spent', 'desc')
    ->get();

// Eloquent com agregação
$orderCount = Order::where('user_id', $userId)->count();
$totalSpent = Order::where('user_id', $userId)->sum('total');
$avgOrder = Order::where('user_id', $userId)->avg('total');

// WithCount (Eloquent relationship)
$users = User::withCount('orders')
    ->having('orders_count', '>', 10)
    ->get();

// WithSum, WithAvg
$users = User::withSum('orders', 'total')
    ->withAvg('orders', 'total')
    ->get();

foreach ($users as $user) {
    echo $user->orders_sum_total;  // Soma dos pedidos
    echo $user->orders_avg_total;  // Pedido médio
}
```

**Window Functions (MySQL 8.0+):**

```sql
-- Ranking de usuários por gasto
SELECT
    user_id,
    total_spent,
    ROW_NUMBER() OVER (ORDER BY total_spent DESC) AS rank,
    RANK() OVER (ORDER BY total_spent DESC) AS rank_with_ties,
    PERCENT_RANK() OVER (ORDER BY total_spent DESC) AS percentile
FROM (
    SELECT
        user_id,
        SUM(total) AS total_spent
    FROM orders
    GROUP BY user_id
) AS user_totals;

-- Running total (soma acumulada)
SELECT
    DATE(created_at) AS date,
    SUM(total) AS daily_revenue,
    SUM(SUM(total)) OVER (ORDER BY DATE(created_at)) AS running_total
FROM orders
WHERE status = 'completed'
GROUP BY DATE(created_at)
ORDER BY date;
```

---

## Na entrevista

> "Funções de agregação: COUNT (quantidade), SUM (soma), AVG (média), MIN/MAX (extremos). GROUP BY agrupa os dados. HAVING filtra depois do agrupamento, WHERE filtra antes. COUNT(*) conta todas as linhas, COUNT(coluna) só as não-NULL. No Laravel: DB::raw() para agregação em SQL, withCount/withSum/withAvg nas relationships do Eloquent. Window functions (ROW_NUMBER, RANK, running totals) no MySQL 8.0+."

---

## Exercícios práticos

### Exercício 1: Estatística por categoria

**Enunciado:** Para cada categoria de produto, mostre a quantidade de produtos, o preço médio, o mínimo e o máximo.

<details>
<summary>Solução</summary>

```sql
-- Query SQL
SELECT
    categories.id,
    categories.name,
    COUNT(products.id) AS products_count,
    AVG(products.price) AS avg_price,
    MIN(products.price) AS min_price,
    MAX(products.price) AS max_price
FROM categories
LEFT JOIN products ON categories.id = products.category_id
GROUP BY categories.id, categories.name
ORDER BY products_count DESC;

-- No Laravel Query Builder
$stats = DB::table('categories')
    ->leftJoin('products', 'categories.id', '=', 'products.category_id')
    ->select(
        'categories.id',
        'categories.name',
        DB::raw('COUNT(products.id) as products_count'),
        DB::raw('AVG(products.price) as avg_price'),
        DB::raw('MIN(products.price) as min_price'),
        DB::raw('MAX(products.price) as max_price')
    )
    ->groupBy('categories.id', 'categories.name')
    ->orderBy('products_count', 'desc')
    ->get();

// Eloquent com withCount e withAvg
$categories = Category::withCount('products')
    ->withAvg('products', 'price')
    ->withMin('products', 'price')
    ->withMax('products', 'price')
    ->get();
```
</details>

### Exercício 2: Clientes VIP

**Enunciado:** Encontre os usuários com mais de 10 pedidos E gasto acima de R$ 5.000. Mostre nome, quantidade de pedidos e soma total.

<details>
<summary>Solução</summary>

```sql
-- Query SQL
SELECT
    users.id,
    users.name,
    COUNT(orders.id) AS orders_count,
    SUM(orders.total) AS total_spent
FROM users
INNER JOIN orders ON users.id = orders.user_id
WHERE orders.status = 'completed'
GROUP BY users.id, users.name
HAVING orders_count > 10 AND total_spent > 5000
ORDER BY total_spent DESC;

-- No Laravel Query Builder
$vipUsers = DB::table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select(
        'users.id',
        'users.name',
        DB::raw('COUNT(orders.id) as orders_count'),
        DB::raw('SUM(orders.total) as total_spent')
    )
    ->where('orders.status', 'completed')
    ->groupBy('users.id', 'users.name')
    ->having('orders_count', '>', 10)
    ->having('total_spent', '>', 5000)
    ->orderBy('total_spent', 'desc')
    ->get();

// Variante Eloquent
$vipUsers = User::withCount(['orders' => function ($query) {
        $query->where('status', 'completed');
    }])
    ->withSum(['orders as total_spent' => function ($query) {
        $query->where('status', 'completed');
    }], 'total')
    ->having('orders_count', '>', 10)
    ->having('total_spent', '>', 5000)
    ->orderBy('total_spent', 'desc')
    ->get();
```
</details>

### Exercício 3: Vendas por mês

**Enunciado:** Mostre a quantidade de pedidos e a receita total por mês no último ano.

<details>
<summary>Solução</summary>

```sql
-- Query SQL
SELECT
    YEAR(created_at) AS year,
    MONTH(created_at) AS month,
    COUNT(*) AS orders_count,
    SUM(total) AS monthly_revenue,
    AVG(total) AS avg_order_value
FROM orders
WHERE status = 'completed'
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
GROUP BY YEAR(created_at), MONTH(created_at)
ORDER BY year DESC, month DESC;

-- No Laravel Query Builder
$monthlySales = DB::table('orders')
    ->select(
        DB::raw('YEAR(created_at) as year'),
        DB::raw('MONTH(created_at) as month'),
        DB::raw('COUNT(*) as orders_count'),
        DB::raw('SUM(total) as monthly_revenue'),
        DB::raw('AVG(total) as avg_order_value')
    )
    ->where('status', 'completed')
    ->where('created_at', '>=', now()->subYear())
    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
    ->orderByRaw('year DESC, month DESC')
    ->get();

// Formatação para ficar mais fácil de ler
$monthlySales = $monthlySales->map(function ($item) {
    $item->month_name = \Carbon\Carbon::create()->month($item->month)->format('F');
    return $item;
});
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
