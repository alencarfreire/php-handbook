# 6.1 Fundamentos de SQL (SELECT, WHERE, JOIN)

## Resumo

> **SQL (Structured Query Language)** — linguagem de consulta para bancos relacionais. SELECT lê os dados, WHERE filtra, JOIN junta tabelas.
>
> **Comandos principais:** SELECT (selecionar), WHERE (filtrar), JOIN (juntar), ORDER BY (ordenar), LIMIT (limitar).
>
> **Importante:** INNER JOIN devolve só os matches. LEFT JOIN devolve tudo da tabela da esquerda + os matches da direita.

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
SQL é a linguagem de consulta para bancos relacionais. SELECT lê os dados, WHERE filtra, JOIN junta tabelas.

**Comandos principais:**
- SELECT — selecionar dados
- WHERE — filtrar
- JOIN — juntar tabelas
- ORDER BY — ordenar
- LIMIT — limitar a quantidade

---

## Como funciona

**SELECT:**

```sql
-- Selecionar todas as colunas
SELECT * FROM users;

-- Selecionar colunas específicas
SELECT id, name, email FROM users;

-- Com alias
SELECT
    id,
    name AS full_name,
    email AS user_email
FROM users;

-- DISTINCT (valores únicos)
SELECT DISTINCT status FROM orders;

-- COUNT
SELECT COUNT(*) FROM users;
SELECT COUNT(DISTINCT status) FROM orders;
```

**WHERE (filtro):**

```sql
-- Igualdade
SELECT * FROM users WHERE id = 1;
SELECT * FROM users WHERE status = 'active';

-- Comparação
SELECT * FROM users WHERE age > 18;
SELECT * FROM users WHERE age >= 18;
SELECT * FROM users WHERE status != 'banned';

-- LIKE (busca por padrão)
SELECT * FROM users WHERE name LIKE 'João%';  -- Começa com João
SELECT * FROM users WHERE email LIKE '%@gmail.com';  -- Termina com @gmail.com
SELECT * FROM users WHERE name LIKE '%silva%';  -- Contém silva

-- IN (está na lista)
SELECT * FROM users WHERE id IN (1, 2, 3);
SELECT * FROM users WHERE status IN ('active', 'pending');

-- BETWEEN (no intervalo)
SELECT * FROM users WHERE age BETWEEN 18 AND 65;
SELECT * FROM orders WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31';

-- IS NULL / IS NOT NULL
SELECT * FROM users WHERE deleted_at IS NULL;
SELECT * FROM users WHERE email_verified_at IS NOT NULL;

-- AND / OR
SELECT * FROM users
WHERE status = 'active' AND age > 18;

SELECT * FROM users
WHERE status = 'active' OR status = 'pending';

-- Combinação
SELECT * FROM users
WHERE (status = 'active' OR status = 'pending')
  AND age > 18;
```

**ORDER BY (ordenação):**

```sql
-- Crescente (ASC)
SELECT * FROM users ORDER BY name ASC;

-- Decrescente (DESC)
SELECT * FROM users ORDER BY created_at DESC;

-- Por várias colunas
SELECT * FROM users
ORDER BY status ASC, created_at DESC;
```

**LIMIT e OFFSET (paginação):**

```sql
-- Primeiros 10 registros
SELECT * FROM users LIMIT 10;

-- Registros de 11 a 20 (paginação)
SELECT * FROM users LIMIT 10 OFFSET 10;

-- Forma curta
SELECT * FROM users LIMIT 10, 10;  -- OFFSET 10, LIMIT 10
```

**JOIN (juntar tabelas):**

```sql
-- INNER JOIN (só os matches)
SELECT
    users.id,
    users.name,
    orders.id AS order_id,
    orders.total
FROM users
INNER JOIN orders ON users.id = orders.user_id;

-- LEFT JOIN (tudo da tabela da esquerda)
SELECT
    users.id,
    users.name,
    COUNT(orders.id) AS orders_count
FROM users
LEFT JOIN orders ON users.id = orders.user_id
GROUP BY users.id, users.name;

-- RIGHT JOIN (tudo da tabela da direita)
SELECT
    orders.id,
    users.name
FROM users
RIGHT JOIN orders ON users.id = orders.user_id;

-- Vários JOINs
SELECT
    users.name,
    orders.id AS order_id,
    products.name AS product_name,
    order_items.quantity
FROM users
INNER JOIN orders ON users.id = orders.user_id
INNER JOIN order_items ON orders.id = order_items.order_id
INNER JOIN products ON order_items.product_id = products.id;
```

---

## Quando usar

**SELECT:**
- Ler dados do banco

**WHERE:**
- Filtrar por condição

**JOIN:**
- Juntar dados de tabelas diferentes
- INNER JOIN — só os matches
- LEFT JOIN — tudo da esquerda + matches da direita
- RIGHT JOIN — tudo da direita + matches da esquerda

---

## Exemplo prático

**Queries de e-commerce:**

```sql
-- Usuários ativos com pedidos
SELECT
    users.id,
    users.name,
    users.email,
    COUNT(orders.id) AS total_orders,
    SUM(orders.total) AS total_spent
FROM users
INNER JOIN orders ON users.id = orders.user_id
WHERE users.status = 'active'
  AND orders.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
GROUP BY users.id, users.name, users.email
HAVING total_orders > 5
ORDER BY total_spent DESC
LIMIT 100;

-- Produtos sem pedidos (LEFT JOIN + IS NULL)
SELECT
    products.id,
    products.name,
    products.price
FROM products
LEFT JOIN order_items ON products.id = order_items.product_id
WHERE order_items.id IS NULL
  AND products.is_active = 1;

-- Pedidos com detalhes
SELECT
    orders.id AS order_id,
    orders.number AS order_number,
    users.name AS customer_name,
    products.name AS product_name,
    order_items.quantity,
    order_items.price,
    (order_items.quantity * order_items.price) AS item_total
FROM orders
INNER JOIN users ON orders.user_id = users.id
INNER JOIN order_items ON orders.id = order_items.order_id
INNER JOIN products ON order_items.product_id = products.id
WHERE orders.created_at >= '2024-01-01'
  AND orders.status = 'completed'
ORDER BY orders.created_at DESC;
```

**Subquery:**

```sql
-- Usuários com pedidos acima da média
SELECT
    users.id,
    users.name,
    (
        SELECT SUM(total)
        FROM orders
        WHERE orders.user_id = users.id
    ) AS total_spent
FROM users
WHERE (
    SELECT SUM(total)
    FROM orders
    WHERE orders.user_id = users.id
) > (
    SELECT AVG(total)
    FROM orders
);

-- IN com subquery
SELECT * FROM users
WHERE id IN (
    SELECT user_id
    FROM orders
    WHERE total > 1000
);

-- EXISTS (checar se existe)
SELECT * FROM users
WHERE EXISTS (
    SELECT 1
    FROM orders
    WHERE orders.user_id = users.id
      AND orders.status = 'pending'
);
```

**GROUP BY e HAVING:**

```sql
-- Agrupar por status
SELECT
    status,
    COUNT(*) AS count,
    AVG(total) AS avg_total
FROM orders
GROUP BY status;

-- HAVING (filtro depois do GROUP BY)
SELECT
    user_id,
    COUNT(*) AS orders_count,
    SUM(total) AS total_spent
FROM orders
GROUP BY user_id
HAVING orders_count > 10 AND total_spent > 5000;
```

**UNION (unir resultados):**

```sql
-- Unir usuários active e VIP
SELECT id, name, 'active' AS type FROM users WHERE status = 'active'
UNION
SELECT id, name, 'vip' AS type FROM users WHERE is_vip = 1;

-- UNION ALL (com duplicatas)
SELECT id, name FROM customers
UNION ALL
SELECT id, name FROM suppliers;
```

**CASE (lógica condicional):**

```sql
SELECT
    id,
    name,
    total,
    CASE
        WHEN total > 10000 THEN 'Premium'
        WHEN total > 5000 THEN 'Gold'
        WHEN total > 1000 THEN 'Silver'
        ELSE 'Bronze'
    END AS tier
FROM orders;
```

**No Laravel Query Builder:**

```php
// SELECT com WHERE
$users = DB::table('users')
    ->select('id', 'name', 'email')
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// JOIN
$orders = DB::table('orders')
    ->join('users', 'users.id', '=', 'orders.user_id')
    ->join('products', 'products.id', '=', 'orders.product_id')
    ->select('orders.*', 'users.name as user_name', 'products.name as product_name')
    ->where('orders.status', 'completed')
    ->get();

// LEFT JOIN com COUNT
$users = DB::table('users')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.*', DB::raw('COUNT(orders.id) as orders_count'))
    ->groupBy('users.id')
    ->get();

// Subquery
$averageTotal = DB::table('orders')->avg('total');
$users = DB::table('users')
    ->whereIn('id', function ($query) use ($averageTotal) {
        $query->select('user_id')
              ->from('orders')
              ->where('total', '>', $averageTotal)
              ->groupBy('user_id');
    })
    ->get();
```

---

## Na entrevista

> "SELECT lê os dados, WHERE filtra (=, >, <, LIKE, IN, BETWEEN, IS NULL). ORDER BY ordena, LIMIT limita. JOIN junta tabelas: INNER JOIN (só os matches), LEFT JOIN (tudo da esquerda), RIGHT JOIN (tudo da direita). GROUP BY para agregar, HAVING para filtrar depois do agrupamento. Subquery no WHERE e no SELECT. No Laravel eu uso Query Builder: DB::table(), where(), join(), orderBy(), limit()."

---

## Exercícios práticos

### Exercício 1: Encontre usuários com pedidos

**Enunciado:** Escreva uma query que traga todos os usuários com pelo menos um pedido. Use dois jeitos: JOIN e subquery.

<details>
<summary>Solução</summary>

```sql
-- Forma 1: INNER JOIN
SELECT DISTINCT users.id, users.name, users.email
FROM users
INNER JOIN orders ON users.id = orders.user_id;

-- Forma 2: EXISTS (mais eficiente em tabelas grandes)
SELECT id, name, email
FROM users
WHERE EXISTS (
    SELECT 1
    FROM orders
    WHERE orders.user_id = users.id
);

-- Forma 3: IN com subquery
SELECT id, name, email
FROM users
WHERE id IN (
    SELECT DISTINCT user_id
    FROM orders
);

-- No Laravel Query Builder
$users = DB::table('users')
    ->join('orders', 'users.id', '=', 'orders.user_id')
    ->select('users.id', 'users.name', 'users.email')
    ->distinct()
    ->get();

// Ou com whereHas
$users = User::whereHas('orders')->get();
```
</details>

### Exercício 2: Top 5 produtos por vendas

**Enunciado:** Traga os 5 produtos com mais vendas (soma de quantity em order_items).

<details>
<summary>Solução</summary>

```sql
-- Query SQL
SELECT
    products.id,
    products.name,
    SUM(order_items.quantity) AS total_sold
FROM products
INNER JOIN order_items ON products.id = order_items.product_id
GROUP BY products.id, products.name
ORDER BY total_sold DESC
LIMIT 5;

-- No Laravel Query Builder
$topProducts = DB::table('products')
    ->join('order_items', 'products.id', '=', 'order_items.product_id')
    ->select(
        'products.id',
        'products.name',
        DB::raw('SUM(order_items.quantity) as total_sold')
    )
    ->groupBy('products.id', 'products.name')
    ->orderBy('total_sold', 'desc')
    ->limit(5)
    ->get();

// Variante Eloquent
$topProducts = Product::select('products.*')
    ->selectRaw('SUM(order_items.quantity) as total_sold')
    ->join('order_items', 'products.id', '=', 'order_items.product_id')
    ->groupBy('products.id')
    ->orderBy('total_sold', 'desc')
    ->limit(5)
    ->get();
```
</details>

### Exercício 3: Encontre produtos sem pedidos

**Enunciado:** Traga todos os produtos que nunca foram pedidos.

<details>
<summary>Solução</summary>

```sql
-- LEFT JOIN + IS NULL
SELECT
    products.id,
    products.name,
    products.price
FROM products
LEFT JOIN order_items ON products.id = order_items.product_id
WHERE order_items.id IS NULL;

-- NOT EXISTS (mais eficiente)
SELECT id, name, price
FROM products
WHERE NOT EXISTS (
    SELECT 1
    FROM order_items
    WHERE order_items.product_id = products.id
);

-- NOT IN (pode ser mais lento)
SELECT id, name, price
FROM products
WHERE id NOT IN (
    SELECT DISTINCT product_id
    FROM order_items
);

-- No Laravel
$unusedProducts = Product::leftJoin('order_items', 'products.id', '=', 'order_items.product_id')
    ->whereNull('order_items.id')
    ->select('products.*')
    ->get();

// Ou com doesntHave
$unusedProducts = Product::doesntHave('orderItems')->get();
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
