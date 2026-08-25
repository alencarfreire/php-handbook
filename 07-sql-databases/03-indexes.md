# 6.3 Índices

## Resumo

> **Índice** — estrutura de dados que acelera a busca na tabela, como o índice de um livro.
>
> **Tipos:** PRIMARY KEY (único, não NULL), UNIQUE (valores únicos), INDEX (comum), COMPOSITE (várias colunas).
>
> **Importante:** Índices aceleram SELECT, mas deixam INSERT/UPDATE/DELETE mais lentos. Composite index (A, B) funciona para WHERE A, mas não para WHERE B.

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
Índice é uma estrutura de dados que acelera a busca na tabela. Analogia: o índice de um livro.

**Tipos de índice:**
- PRIMARY KEY — chave primária (único, não NULL)
- UNIQUE — valores únicos
- INDEX — índice comum
- FULLTEXT — busca full-text
- COMPOSITE — composto (várias colunas)

---

## Como funciona

**Criar índices:**

```sql
-- PRIMARY KEY (automático ao criar a tabela)
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL
);

-- Índice UNIQUE
CREATE UNIQUE INDEX idx_users_email ON users(email);
ALTER TABLE users ADD UNIQUE INDEX idx_users_email (email);

-- INDEX comum
CREATE INDEX idx_users_status ON users(status);
ALTER TABLE users ADD INDEX idx_users_status (status);

-- Índice COMPOSITE (várias colunas)
CREATE INDEX idx_users_status_created ON users(status, created_at);

-- FULLTEXT (busca em texto)
CREATE FULLTEXT INDEX idx_posts_title_body ON posts(title, body);

-- Remover índice
DROP INDEX idx_users_status ON users;
ALTER TABLE users DROP INDEX idx_users_status;
```

**Nas migrations do Laravel:**

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();  // PRIMARY KEY
    $table->string('email')->unique();  // UNIQUE INDEX
    $table->string('status')->index();  // INDEX
    $table->string('first_name');
    $table->string('last_name');
    $table->timestamps();

    // Composite index
    $table->index(['status', 'created_at']);

    // Índice nomeado
    $table->index('email', 'idx_users_email');

    // Full-text
    $table->fullText(['title', 'body']);
});

// Adicionar índice em tabela existente
Schema::table('users', function (Blueprint $table) {
    $table->index('status');
});

// Remover índice
Schema::table('users', function (Blueprint $table) {
    $table->dropIndex(['status']);
    $table->dropIndex('idx_users_email');  // Pelo nome
});
```

---

## Quando usar

**Prós dos índices:**
- ✅ Aceleram SELECT (WHERE, ORDER BY, JOIN)
- ✅ Aceleram checagem UNIQUE
- ✅ Aceleram MIN/MAX

**Contras dos índices:**
- ❌ Deixam INSERT/UPDATE/DELETE mais lentos
- ❌ Ocupam espaço em disco
- ❌ Precisam de manutenção

**Quando criar:**
- Condições WHERE frequentes
- Colunas de JOIN
- Colunas de ORDER BY
- Foreign keys

**Quando NÃO criar:**
- Tabela pequena (< 1000 linhas)
- Coluna com pouca seletividade (boolean, por exemplo)
- Coluna pouco usada
- Muito INSERT/UPDATE

---

## Exemplo prático

**Otimizar queries com índices:**

```sql
-- ❌ Lento (sem índice)
SELECT * FROM users WHERE email = 'joao@email.com';
-- Varre a tabela inteira (Full Table Scan)

-- ✅ Rápido (com índice em email)
CREATE UNIQUE INDEX idx_users_email ON users(email);
SELECT * FROM users WHERE email = 'joao@email.com';
-- Usa o índice (Index Seek)

-- ❌ Lento (sem índice composto)
SELECT * FROM orders
WHERE status = 'completed'
  AND created_at > '2024-01-01'
ORDER BY created_at DESC
LIMIT 100;

-- ✅ Rápido (com índice composto)
CREATE INDEX idx_orders_status_created ON orders(status, created_at);
-- O índice cobre WHERE, ORDER BY e o filtro
```

**Composite Index (a ordem importa):**

```sql
-- Índice (status, created_at)
CREATE INDEX idx_orders_status_created ON orders(status, created_at);

-- ✅ Usa o índice (começa por status)
SELECT * FROM orders WHERE status = 'completed';
SELECT * FROM orders WHERE status = 'completed' AND created_at > '2024-01-01';

-- ❌ NÃO usa o índice (não começa por status)
SELECT * FROM orders WHERE created_at > '2024-01-01';

-- Regra: a ordem das colunas no índice importa
-- Índice (A, B, C) funciona para:
-- - WHERE A
-- - WHERE A AND B
-- - WHERE A AND B AND C
-- NÃO funciona para:
-- - WHERE B
-- - WHERE C
-- - WHERE B AND C
```

**EXPLAIN (análise da query):**

```sql
-- Checar se usa índice
EXPLAIN SELECT * FROM users WHERE email = 'joao@email.com';

-- Resultado:
-- type: const (melhor) — por PRIMARY KEY ou UNIQUE
-- type: ref — por índice
-- type: range — intervalo (BETWEEN, >, <)
-- type: index — varredura do índice
-- type: ALL — varredura completa da tabela (RUIM)

-- key: nome do índice usado
-- rows: quantidade aproximada de linhas checadas

-- No Laravel
DB::connection()->enableQueryLog();
User::where('email', 'joao@email.com')->get();
dd(DB::getQueryLog());
```

**Covering Index (índice de cobertura):**

```sql
-- A query seleciona só email e status
SELECT email, status FROM users WHERE status = 'active';

-- Criar índice com todas as colunas necessárias
CREATE INDEX idx_users_status_email ON users(status, email);

-- Agora o MySQL pega tudo do índice
-- sem ir na tabela (Index-Only Scan)
```

**Índices de foreign key:**

```php
// O Laravel cria índice sozinho para foreign key
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();  // Cria INDEX em user_id
    $table->timestamps();
});

// Equivale a:
$table->unsignedBigInteger('user_id')->index();
$table->foreign('user_id')->references('id')->on('users');
```

**Busca FULLTEXT:**

```php
// Migration
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('body');
    $table->timestamps();

    // Índice full-text
    $table->fullText(['title', 'body']);
});

// Busca
$posts = Post::whereFullText(['title', 'body'], 'texto de busca')->get();

// SQL
SELECT * FROM posts
WHERE MATCH(title, body) AGAINST('texto de busca' IN NATURAL LANGUAGE MODE);
```

**Quando o índice NÃO é usado:**

```sql
-- Função no WHERE (índice não entra)
-- ❌ RUIM
SELECT * FROM users WHERE YEAR(created_at) = 2024;

-- ✅ BOM (usa o índice)
SELECT * FROM users
WHERE created_at >= '2024-01-01'
  AND created_at < '2025-01-01';

-- Condição OR (pode não usar o índice)
-- ❌ RUIM
SELECT * FROM users WHERE status = 'active' OR age > 18;

-- ✅ BOM (UNION)
SELECT * FROM users WHERE status = 'active'
UNION
SELECT * FROM users WHERE age > 18;

-- LIKE com % no começo (índice não entra)
-- ❌ RUIM
SELECT * FROM users WHERE email LIKE '%@gmail.com';

-- ✅ BOM (FULLTEXT ou busca pela direita)
SELECT * FROM users WHERE email LIKE 'joao%';
```

**Monitoramento e manutenção:**

```sql
-- Mostrar índices da tabela
SHOW INDEXES FROM users;

-- Índices não usados (MySQL 8.0+)
SELECT * FROM sys.schema_unused_indexes;

-- Índices duplicados
SELECT * FROM sys.schema_redundant_indexes;

-- Otimizar a tabela
OPTIMIZE TABLE users;

-- Reconstruir o índice
ALTER TABLE users DROP INDEX idx_users_status, ADD INDEX idx_users_status (status);
```

---

## Na entrevista

> "Índice acelera a busca como o índice de um livro. PRIMARY KEY é único e não NULL, UNIQUE para valores únicos, INDEX é o comum. Composite index (A, B) funciona para WHERE A ou WHERE A AND B, mas não para WHERE B. EXPLAIN mostra o query plan: type const/ref/range é bom, ALL é ruim. Índice deixa INSERT/UPDATE mais lento e ocupa espaço. Foreign key ganha índice sozinho. FULLTEXT é busca em texto. Covering index tem todas as colunas que a query precisa."

---

## Exercícios práticos

### Exercício 1: Otimize a query com índices

Você tem uma query lenta. Quais índices criar?

```sql
SELECT * FROM orders
WHERE status = 'pending'
  AND user_id = 123
  AND created_at > '2024-01-01'
ORDER BY created_at DESC
LIMIT 20;
```

<details>
<summary>Solução</summary>

```sql
-- Índice composto ótimo (a ordem importa!)
CREATE INDEX idx_orders_user_status_created
ON orders(user_id, status, created_at);

-- Por que nessa ordem?
-- 1. user_id - coluna mais seletiva (condição =)
-- 2. status - segunda condição (=)
-- 3. created_at - entra no ORDER BY

-- Esse índice cobre:
-- - WHERE user_id = 123 (primeira coluna)
-- - WHERE user_id = 123 AND status = 'pending' (as duas primeiras)
-- - WHERE user_id = 123 AND status = 'pending' AND created_at > X (as três)
-- - ORDER BY created_at (última coluna já está no índice)

-- Na migration do Laravel
Schema::table('orders', function (Blueprint $table) {
    $table->index(['user_id', 'status', 'created_at'], 'idx_orders_user_status_created');
});

-- Checar se o índice entra
EXPLAIN SELECT * FROM orders
WHERE status = 'pending'
  AND user_id = 123
  AND created_at > '2024-01-01'
ORDER BY created_at DESC
LIMIT 20;

-- Deve mostrar:
-- type: ref ou range
-- key: idx_orders_user_status_created
-- Extra: Using index condition (bom)
```
</details>

### Exercício 2: Encontre os problemas nos índices

O que está errado nesses índices?

```sql
CREATE INDEX idx_users_email ON users(email);
CREATE UNIQUE INDEX idx_users_email_unique ON users(email);

CREATE INDEX idx_posts_created ON posts(created_at);
CREATE INDEX idx_posts_status_created ON posts(status, created_at);
```

<details>
<summary>Solução</summary>

```sql
-- Problema 1: índices duplicados em email
-- UNIQUE já funciona como INDEX comum
-- Solução: remover idx_users_email
DROP INDEX idx_users_email ON users;
-- Deixar só o UNIQUE

-- Problema 2: índice redundante em created_at
-- O índice (status, created_at) já cobre queries com created_at
-- se elas usam status
-- idx_posts_created só precisa existir se houver query
-- WHERE created_at sem status

-- Análise:
-- Se existe query: WHERE created_at > '2024-01-01'
--   -> Precisa de idx_posts_created

-- Se só tem: WHERE status = 'published' AND created_at > '2024-01-01'
--   -> idx_posts_status_created basta
--   -> Pode remover idx_posts_created

-- Na migration do Laravel
Schema::table('users', function (Blueprint $table) {
    // Certo: um UNIQUE index
    $table->string('email')->unique();
});

Schema::table('posts', function (Blueprint $table) {
    // Índice composto para a query frequente
    $table->index(['status', 'created_at']);

    // Índice separado só se precisar
    // $table->index('created_at'); // Só se houver query sem status
});

-- Checar índices duplicados
SELECT * FROM sys.schema_redundant_indexes;
```
</details>

### Exercício 3: Quando o índice NÃO é usado?

Por que essas queries não usam o índice em email?

```sql
-- O índice existe
CREATE INDEX idx_users_email ON users(email);

-- Query 1
SELECT * FROM users WHERE LOWER(email) = 'joao@email.com';

-- Query 2
SELECT * FROM users WHERE email LIKE '%@gmail.com';
```

<details>
<summary>Solução</summary>

```sql
-- Query 1: função no WHERE
-- ❌ Problema: LOWER(email) — função na coluna
-- O MySQL não usa o índice: precisa calcular a função em cada linha

-- ✅ Solução 1: tirar a função (se o dado já está no mesmo case)
SELECT * FROM users WHERE email = 'joao@email.com';

-- ✅ Solução 2: functional index (MySQL 8.0+)
CREATE INDEX idx_users_email_lower ON users((LOWER(email)));
-- Agora a query usa o índice

-- ✅ Solução 3: guardar versão normalizada (Laravel)
Schema::table('users', function (Blueprint $table) {
    $table->string('email_normalized')->virtualAs('LOWER(email)');
    $table->index('email_normalized');
});

-- Query 2: LIKE com % no começo
-- ❌ Problema: '%@gmail.com' — busca pelo fim da string
-- Índice funciona como livro: achar palavra que começa com "A" é rápido,
-- achar palavra que termina com "A" não é

-- ✅ Solução 1: LIKE sem % no começo
SELECT * FROM users WHERE email LIKE 'joao%';  -- Usa o índice

-- ✅ Solução 2: índice FULLTEXT
CREATE FULLTEXT INDEX idx_users_email_fulltext ON users(email);
SELECT * FROM users
WHERE MATCH(email) AGAINST('@gmail.com' IN BOOLEAN MODE);

-- ✅ Solução 3: índice reverso (busca pelo sufixo)
Schema::table('users', function (Blueprint $table) {
    $table->string('email_reversed')->virtualAs('REVERSE(email)');
    $table->index('email_reversed');
});

SELECT * FROM users
WHERE email_reversed LIKE REVERSE('%@gmail.com');

-- No Laravel
// ❌ Ruim
User::whereRaw('LOWER(email) = ?', ['joao@email.com'])->get();

// ✅ Bom
User::where('email', 'joao@email.com')->get();
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
