# 6.5 Normalização de banco

## Resumo

> **Normalização** — organizar os dados para reduzir redundância e anomalia.
>
> **Formas normais:** 1NF (valores atômicos), 2NF (sem dependência parcial), 3NF (sem dependência transitiva).
>
> **Importante:** Desnormalização vale para dado que se lê muito (contador, estatística). Snapshot para dado histórico (preço no momento do pedido).

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
Normalização — organizar os dados para reduzir redundância e anomalia.

**Formas normais:**
- **1NF** — valores atômicos
- **2NF** — sem dependência parcial
- **3NF** — sem dependência transitiva
- **BCNF** — todo determinante é chave

---

## Como funciona

**1NF (Primeira forma normal):**

Cada campo guarda só um valor atômico (não array, não lista).

```sql
-- ❌ NÃO é 1NF (vários telefones no mesmo campo)
CREATE TABLE users (
    id INT,
    name VARCHAR(255),
    phones VARCHAR(255)  -- '11999991111, 11999992222'
);

-- ✅ 1NF (valores atômicos)
CREATE TABLE users (
    id INT,
    name VARCHAR(255)
);

CREATE TABLE user_phones (
    id INT,
    user_id INT,
    phone VARCHAR(20)
);
```

**2NF (Segunda forma normal):**

Sem dependência parcial (todo campo que não é chave depende da chave inteira).

```sql
-- ❌ NÃO é 2NF (product_name depende só de product_id, não de (order_id, product_id))
CREATE TABLE order_items (
    order_id INT,
    product_id INT,
    product_name VARCHAR(255),  -- Depende só de product_id
    quantity INT,
    PRIMARY KEY (order_id, product_id)
);

-- ✅ 2NF (extrair product_name para outra tabela)
CREATE TABLE order_items (
    order_id INT,
    product_id INT,
    quantity INT,
    price DECIMAL(10, 2),
    PRIMARY KEY (order_id, product_id)
);

CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    price DECIMAL(10, 2)
);
```

**3NF (Terceira forma normal):**

Sem dependência transitiva (campo que não é chave depende só da chave).

```sql
-- ❌ NÃO é 3NF (category_name depende de category_id, não de product_id)
CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    category_id INT,
    category_name VARCHAR(255)  -- Depende de category_id
);

-- ✅ 3NF (extrair category para outra tabela)
CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    category_id INT,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE categories (
    id INT PRIMARY KEY,
    name VARCHAR(255)
);
```

---

## Quando usar

**Prós da normalização:**
- ✅ Sem redundância (economiza espaço)
- ✅ Sem anomalia de update
- ✅ Integridade dos dados

**Contras da normalização:**
- ❌ Mais JOIN (mais lento)
- ❌ Queries mais complexas

**Quando desnormalizar:**
- Lê mais do que escreve
- Leitura precisa ser rápida
- Banco analítico (data warehouse)

---

## Exemplo prático

**Normalização no e-commerce:**

```sql
-- ❌ Tabela desnormalizada (ruim)
CREATE TABLE orders (
    id INT PRIMARY KEY,
    user_id INT,
    user_name VARCHAR(255),       -- Cópia de users
    user_email VARCHAR(255),      -- Cópia de users
    product_id INT,
    product_name VARCHAR(255),    -- Cópia de products
    product_price DECIMAL(10, 2), -- Cópia de products
    quantity INT,
    total DECIMAL(10, 2)
);

-- Problemas:
-- 1. Se user_name mudar, precisa atualizar todos os pedidos
-- 2. Se product_price mudar, pedidos antigos mudam também (!)
-- 3. Redundância de dados

-- ✅ Estrutura normalizada
CREATE TABLE users (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255)
);

CREATE TABLE products (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    price DECIMAL(10, 2)
);

CREATE TABLE orders (
    id INT PRIMARY KEY,
    user_id INT,
    created_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE order_items (
    id INT PRIMARY KEY,
    order_id INT,
    product_id INT,
    quantity INT,
    price DECIMAL(10, 2),  -- Preço no momento do pedido (não é cópia!)
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);
```

**Quando a desnormalização vale a pena:**

```php
// Exemplo: cache de contadores

// ❌ Lento (JOIN e COUNT toda vez)
$users = User::with(['posts' => function ($query) {
    $query->select('user_id', DB::raw('COUNT(*) as posts_count'))
          ->groupBy('user_id');
}])->get();

// ✅ Desnormalização: guardar posts_count em users
Schema::table('users', function (Blueprint $table) {
    $table->integer('posts_count')->default(0);
});

// Atualizar ao criar/apagar o post
class Post extends Model
{
    protected static function booted(): void
    {
        static::created(function (Post $post) {
            $post->user->increment('posts_count');
        });

        static::deleted(function (Post $post) {
            $post->user->decrement('posts_count');
        });
    }
}

// Agora é rápido
$users = User::where('posts_count', '>', 10)->get();
```

**Snapshot de dados (dados históricos):**

```php
// Pedidos: guardar product_name e price no momento do pedido
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained();
    $table->foreignId('product_id')->constrained();
    $table->string('product_name');  // Snapshot
    $table->decimal('price', 10, 2);  // Snapshot (preço no momento do pedido)
    $table->integer('quantity');
});

class OrderService
{
    public function createOrder(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            $order = Order::create(['user_id' => $user->id]);

            foreach ($items as $item) {
                $product = Product::find($item['product_id']);

                $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,      // Guardar o snapshot
                    'price' => $product->price,            // Preço no momento do pedido
                    'quantity' => $item['quantity'],
                ]);
            }

            return $order;
        });
    }
}
```

**Materialized Views (visões materializadas):**

```sql
-- Query pesada (lenta)
SELECT
    users.id,
    users.name,
    COUNT(orders.id) AS orders_count,
    SUM(orders.total) AS total_spent
FROM users
LEFT JOIN orders ON users.id = orders.user_id
GROUP BY users.id, users.name;

-- Criar materialized view (PostgreSQL)
CREATE MATERIALIZED VIEW user_stats AS
SELECT
    users.id,
    users.name,
    COUNT(orders.id) AS orders_count,
    COALESCE(SUM(orders.total), 0) AS total_spent
FROM users
LEFT JOIN orders ON users.id = orders.user_id
GROUP BY users.id, users.name;

-- Atualizar os dados
REFRESH MATERIALIZED VIEW user_stats;

-- Agora é rápido
SELECT * FROM user_stats WHERE orders_count > 10;
```

**No Laravel (via tabela):**

```php
// Migration para dados desnormalizados
Schema::create('user_stats', function (Blueprint $table) {
    $table->foreignId('user_id')->primary();
    $table->integer('posts_count')->default(0);
    $table->integer('orders_count')->default(0);
    $table->decimal('total_spent', 10, 2)->default(0);
    $table->timestamp('updated_at');
});

// Command para atualizar estatística
class UpdateUserStats extends Command
{
    public function handle(): void
    {
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                DB::table('user_stats')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'posts_count' => $user->posts()->count(),
                        'orders_count' => $user->orders()->count(),
                        'total_spent' => $user->orders()->sum('total'),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}

// Rodar no cron
// schedule:run a cada hora
```

---

## Na entrevista

> "Normalização reduz redundância. 1NF — valores atômicos, 2NF — sem dependência parcial, 3NF — sem dependência transitiva. Prós: sem duplicata, integridade. Contras: mais JOIN, mais lento. Desnormalização vale para dado que se lê muito (contador, estatística). Snapshot para registro histórico (preço no momento do pedido). Materialized view para agregação pesada. No Laravel: guardar contadores (posts_count), atualizar via events."

---

## Exercícios práticos

### Exercício 1: Normalize a tabela

**Enunciado:** Leve esta tabela para 3NF. Quais problemas você vê?

```sql
CREATE TABLE orders (
    id INT PRIMARY KEY,
    customer_name VARCHAR(255),
    customer_email VARCHAR(255),
    customer_phone VARCHAR(20),
    product_names TEXT,  -- 'Produto 1, Produto 2, Produto 3'
    product_prices TEXT, -- '100, 200, 300'
    total DECIMAL(10, 2),
    discount_percent INT,
    discount_name VARCHAR(255)  -- 'Desconto VIP'
);
```

<details>
<summary>Solução</summary>

```sql
-- Problemas:
-- 1. NÃO é 1NF: product_names e product_prices guardam vários valores
-- 2. NÃO é 2NF: customer_* se repetem em cada pedido
-- 3. NÃO é 3NF: discount_name depende de discount_percent

-- ✅ Estrutura normalizada (3NF)

-- Tabela de clientes
CREATE TABLE customers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20)
);

-- Tabela de produtos
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10, 2) NOT NULL
);

-- Tabela de descontos
CREATE TABLE discounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    percent INT NOT NULL
);

-- Tabela de pedidos
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    discount_id INT,
    total DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (discount_id) REFERENCES discounts(id)
);

-- Tabela de itens do pedido
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    price DECIMAL(10, 2) NOT NULL,  -- Preço no momento do pedido (snapshot)
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Nas migrations do Laravel
Schema::create('customers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('phone', 20)->nullable();
    $table->timestamps();
});

Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->decimal('price', 10, 2);
    $table->timestamps();
});

Schema::create('discounts', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->integer('percent');
    $table->timestamps();
});

Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained()->onDelete('cascade');
    $table->foreignId('discount_id')->nullable()->constrained();
    $table->decimal('total', 10, 2);
    $table->timestamps();
});

Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained();
    $table->integer('quantity')->default(1);
    $table->decimal('price', 10, 2);  // Snapshot do preço
    $table->timestamps();
});
```
</details>

### Exercício 2: Quando desnormalizar?

**Enunciado:** Você tem as tabelas `users` e `posts`. A query "usuários com mais de 10 posts" roda o tempo todo e está lenta. Como otimizar?

<details>
<summary>Solução</summary>

```php
// ❌ Query lenta (JOIN + COUNT toda vez)
$users = User::select('users.*')
    ->selectRaw('COUNT(posts.id) as posts_count')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->groupBy('users.id')
    ->having('posts_count', '>', 10)
    ->get();

// ✅ Solução: desnormalizar — adicionar contador em users
Schema::table('users', function (Blueprint $table) {
    $table->integer('posts_count')->default(0);
    $table->index('posts_count');  // Índice para filtrar rápido
});

// Atualizar o contador via Model Events
class Post extends Model
{
    protected static function booted(): void
    {
        static::created(function (Post $post) {
            $post->user()->increment('posts_count');
        });

        static::deleted(function (Post $post) {
            $post->user()->decrement('posts_count');
        });

        // Ao restaurar soft deleted
        static::restored(function (Post $post) {
            $post->user()->increment('posts_count');
        });
    }
}

// Agora a query é rápida (sem JOIN)
$users = User::where('posts_count', '>', 10)->get();

// Ou via Observer (código mais limpo)
class PostObserver
{
    public function created(Post $post): void
    {
        $post->user->increment('posts_count');
    }

    public function deleted(Post $post): void
    {
        $post->user->decrement('posts_count');
    }

    public function restored(Post $post): void
    {
        $post->user->increment('posts_count');
    }
}

// No AppServiceProvider
public function boot(): void
{
    Post::observe(PostObserver::class);
}

// Command para recalcular (se os contadores dessincronizarem)
class RecalculatePostsCounts extends Command
{
    public function handle(): void
    {
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $count = $user->posts()->count();
                $user->update(['posts_count' => $count]);
            }
        });

        $this->info('Contadores de posts recalculados');
    }
}
```
</details>

### Exercício 3: Snapshot de dados históricos

**Enunciado:** Crie um sistema de pedidos em que o preço do produto pode mudar, mas o pedido precisa guardar o preço no momento da compra.

<details>
<summary>Solução</summary>

```php
// Migration de order_items
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained();

    // Snapshot dos dados no momento do pedido
    $table->string('product_name');  // Nome no momento do pedido
    $table->decimal('price', 10, 2);  // Preço no momento do pedido
    $table->text('product_description')->nullable();  // Descrição

    $table->integer('quantity')->default(1);
    $table->timestamps();
});

// Service para criar o pedido
class OrderService
{
    public function createOrder(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            $order = Order::create([
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            $total = 0;

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Criar o item com snapshot dos dados
                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->name,  // Snapshot
                    'price' => $product->price,  // Snapshot (preço atual)
                    'product_description' => $product->description,  // Snapshot
                    'quantity' => $item['quantity'],
                ]);

                $total += $orderItem->price * $orderItem->quantity;
            }

            $order->update(['total' => $total]);

            return $order->fresh('items');
        });
    }
}

// Model OrderItem
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'product_description',
        'quantity',
    ];

    // Atributo calculado
    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }

    // Relationship com o produto atual (pode mudar)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relationship com o pedido
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

// Uso
$order = OrderService::createOrder($user, [
    ['product_id' => 1, 'quantity' => 2],
    ['product_id' => 5, 'quantity' => 1],
]);

// Mesmo se o preço do produto mudar, o pedido fica com o antigo
$product = Product::find(1);
$product->update(['price' => 9999]);  // Preço mudou

// No pedido o preço ficou o antigo
$orderItem = $order->items->first();
echo $orderItem->price;  // Preço antigo (snapshot)
echo $orderItem->product->price;  // Preço novo (9999)
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
