# 9.4 Níveis de isolamento de transações

> **TL;DR:** Isolation Levels controlam o que uma transação vê enquanto outras transações concorrentes mudam os dados. Read Committed (default no PostgreSQL) só vê dados committed. Repeatable Read (default no MySQL) usa snapshot isolation. Serializable se comporta como execução em série. Trade-off: mais isolamento → mais consistency, menos performance. Deadlock se resolve com retry e lock na mesma ordem.

## Conteúdo

- [O que é](#o-que-é)
- [Problemas de acesso concurrent](#problemas-de-acesso-concurrent)
  - [Dirty Read](#1-dirty-read-leitura-suja)
  - [Non-Repeatable Read](#2-non-repeatable-read-leitura-não-repetível)
  - [Phantom Read](#3-phantom-read-leitura-fantasma)
- [Read Uncommitted](#1-read-uncommitted)
- [Read Committed](#2-read-committed-default-postgresql)
- [Repeatable Read](#3-repeatable-read-default-mysql)
- [Serializable](#4-serializable)
- [Tabela comparativa](#tabela-comparativa)
- [Laravel](#laravel)
- [Exemplos práticos](#exemplos-práticos)
- [Deadlocks](#deadlocks)
- [Monitoramento](#monitoramento)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Isolation Levels:**
Configuração que controla o que a transação vê quando outras transações alteram os dados em paralelo.

**Trade-off:**
- Mais isolamento → mais consistency, menos performance
- Menos isolamento → menos consistency, mais performance

**4 níveis (do mais fraco ao mais forte):**
1. Read Uncommitted
2. Read Committed (default no PostgreSQL)
3. Repeatable Read (default no MySQL)
4. Serializable

---

## Problemas de acesso concurrent

### 1. Dirty Read (leitura suja)

**Problema:** Você lê dados uncommitted de outra transação.

```sql
-- Transaction A
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE id = 1;
-- SEM commit

-- Transaction B (em paralelo)
BEGIN;
SELECT balance FROM accounts WHERE id = 1;  -- Vê -100
COMMIT;

-- Transaction A
ROLLBACK;  -- Deu rollback!

-- Transaction B leu dados que nunca existiram
```

---

### 2. Non-Repeatable Read (leitura não repetível)

**Problema:** Você lê a mesma linha duas vezes e recebe valores diferentes.

```sql
-- Transaction A
BEGIN;
SELECT balance FROM accounts WHERE id = 1;  -- 1000

-- Transaction B (em paralelo)
BEGIN;
UPDATE accounts SET balance = 500 WHERE id = 1;
COMMIT;

-- Transaction A (continua)
SELECT balance FROM accounts WHERE id = 1;  -- 500 (era 1000!)
COMMIT;
```

---

### 3. Phantom Read (leitura fantasma)

**Problema:** A mesma query devolve uma quantidade diferente de linhas.

```sql
-- Transaction A
BEGIN;
SELECT COUNT(*) FROM orders WHERE status = 'pending';  -- 10

-- Transaction B (em paralelo)
BEGIN;
INSERT INTO orders (status) VALUES ('pending');
COMMIT;

-- Transaction A (continua)
SELECT COUNT(*) FROM orders WHERE status = 'pending';  -- 11 (era 10!)
COMMIT;
```

---

## 1. Read Uncommitted

**O que permite:**
- ✅ Dirty Read
- ✅ Non-Repeatable Read
- ✅ Phantom Read

**Uso:**

```sql
SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;
BEGIN;
SELECT * FROM accounts;  -- Pode ler dados uncommitted
COMMIT;
```

**Quando usar:**
- Estatística aproximada (não é crítico)
- ❌ Quase nunca entra em production

---

## 2. Read Committed (default PostgreSQL)

**O que bloqueia:**
- ❌ Dirty Read
- ✅ Non-Repeatable Read
- ✅ Phantom Read

**Garantia:** Você só vê dados committed.

```sql
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
BEGIN;

SELECT balance FROM accounts WHERE id = 1;  -- 1000

-- Outra transação alterou e fez commit
-- O próximo SELECT vê o valor novo

SELECT balance FROM accounts WHERE id = 1;  -- 500

COMMIT;
```

**PostgreSQL default:**

```php
DB::transaction(function () {
    // Read Committed por padrão
    $user = User::find(1);
});
```

---

## 3. Repeatable Read (default MySQL)

**O que bloqueia:**
- ❌ Dirty Read
- ❌ Non-Repeatable Read
- ✅ Phantom Read (no PostgreSQL é bloqueado, no MySQL é permitido)

**Garantia:** A mesma linha devolve o mesmo valor durante a transação.

```sql
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;
BEGIN;

SELECT balance FROM accounts WHERE id = 1;  -- 1000

-- Outra transação alterou e fez commit
-- Mas a gente ainda vê o valor antigo

SELECT balance FROM accounts WHERE id = 1;  -- 1000 (não mudou!)

COMMIT;
```

**Implementação:** Snapshot isolation (cada transação vê o snapshot do momento em que começou).

**MySQL:**

```php
DB::transaction(function () {
    // Repeatable Read por padrão
    $balance1 = Account::find(1)->balance;
    sleep(5);  // Outra transação alterou o balance
    $balance2 = Account::find(1)->balance;
    // $balance1 === $balance2 (mesmo snapshot)
});
```

---

## 4. Serializable

**O que bloqueia:**
- ❌ Dirty Read
- ❌ Non-Repeatable Read
- ❌ Phantom Read

**Garantia:** As transações rodam como se fossem em série (serial).

```sql
SET TRANSACTION ISOLATION LEVEL SERIALIZABLE;
BEGIN;

SELECT COUNT(*) FROM orders WHERE status = 'pending';  -- 10

-- Outra transação tenta INSERT
-- Fica bloqueada ou devolve serialization error

SELECT COUNT(*) FROM orders WHERE status = 'pending';  -- 10

COMMIT;
```

**Desvantagens:**
- ❌ Lento (muitos locks)
- ❌ Serialization errors (precisa de retry)

**Quando usar:**
- Operação financeira crítica
- Quando você precisa de consistency absoluta

---

## Tabela comparativa

| Level              | Dirty Read | Non-Repeatable | Phantom | Performance |
|--------------------|------------|----------------|---------|-------------|
| Read Uncommitted   | ✅ Sim     | ✅ Sim         | ✅ Sim  | Rápido      |
| Read Committed     | ❌ Não     | ✅ Sim         | ✅ Sim  | Médio       |
| Repeatable Read    | ❌ Não     | ❌ Não         | ✅ Sim* | Médio       |
| Serializable       | ❌ Não     | ❌ Não         | ❌ Não  | Lento       |

\* PostgreSQL bloqueia, MySQL permite

---

## Laravel

**Definir o nível de isolamento:**

```php
// Global em config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
    // MySQL não aceita isolamento via options
],

// Para uma transação específica
DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
DB::transaction(function () {
    // ...
});

// Ou via raw
DB::beginTransaction();
DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
// ... queries ...
DB::commit();
```

---

## Exemplos práticos

### 1. Bank Transfer (precisa de isolamento)

```php
// ❌ Read Committed: race condition
DB::transaction(function () use ($from, $to, $amount) {
    $fromAccount = Account::find($from);

    if ($fromAccount->balance < $amount) {
        throw new InsufficientFundsException();
    }

    // Outra transação pode fazer withdrawal ao mesmo tempo
    // e o balance fica negative!

    $fromAccount->decrement('balance', $amount);
    Account::find($to)->increment('balance', $amount);
});

// ✅ Serializable: seguro
DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
DB::transaction(function () use ($from, $to, $amount) {
    $fromAccount = Account::lockForUpdate()->find($from);

    if ($fromAccount->balance < $amount) {
        throw new InsufficientFundsException();
    }

    $fromAccount->decrement('balance', $amount);
    Account::find($to)->increment('balance', $amount);
});
```

---

### 2. Inventory Check (Repeatable Read basta)

```php
DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
DB::transaction(function () use ($productId, $quantity) {
    $product = Product::find($productId);

    // Vemos o snapshot, não muda durante a transação
    if ($product->stock < $quantity) {
        throw new OutOfStockException();
    }

    $product->decrement('stock', $quantity);
    Order::create([...]);
});
```

---

### 3. Analytics Report (Read Committed OK)

```php
// Read Committed basta para reports
// Não é crítico se os dados ficarem um pouco inconsistentes
DB::transaction(function () {
    $totalUsers = User::count();
    $totalOrders = Order::count();
    $totalRevenue = Order::sum('total');

    return [
        'users' => $totalUsers,
        'orders' => $totalOrders,
        'revenue' => $totalRevenue,
    ];
});
```

---

## Deadlocks

**Problema:** Duas transações esperam uma pela outra.

```sql
-- Transaction A
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE id = 1;  -- Trava a row 1
-- Espera...
UPDATE accounts SET balance = balance + 100 WHERE id = 2;  -- Precisa da row 2

-- Transaction B (em paralelo)
BEGIN;
UPDATE accounts SET balance = balance - 50 WHERE id = 2;   -- Trava a row 2
-- Espera...
UPDATE accounts SET balance = balance + 50 WHERE id = 1;   -- Precisa da row 1

-- DEADLOCK! As duas esperam uma pela outra
```

**O que o banco faz:** Dá rollback automático em uma das transações.

**Como evitar:**

```php
// 1. Sempre travar na mesma ordem (por ID)
$accounts = Account::whereIn('id', [$from, $to])
    ->orderBy('id')  // Sempre a mesma ordem!
    ->lockForUpdate()
    ->get();

// 2. Retry no deadlock
$maxRetries = 3;
for ($i = 0; $i < $maxRetries; $i++) {
    try {
        DB::transaction(function () {
            // ...
        });
        break;
    } catch (DeadlockException $e) {
        if ($i === $maxRetries - 1) {
            throw $e;
        }
        usleep(100000 * ($i + 1));  // Exponential backoff
    }
}
```

---

## Monitoramento

**PostgreSQL:**

```sql
-- Ver locks atuais
SELECT * FROM pg_locks WHERE NOT granted;

-- Ver queries bloqueadas
SELECT
    blocked_locks.pid AS blocked_pid,
    blocking_locks.pid AS blocking_pid,
    blocked_activity.query AS blocked_query,
    blocking_activity.query AS blocking_query
FROM pg_locks blocked_locks
JOIN pg_stat_activity blocked_activity ON blocked_activity.pid = blocked_locks.pid
JOIN pg_locks blocking_locks ON blocking_locks.locktype = blocked_locks.locktype
JOIN pg_stat_activity blocking_activity ON blocking_activity.pid = blocking_locks.pid
WHERE NOT blocked_locks.granted AND blocking_locks.granted;
```

**MySQL:**

```sql
-- Deadlocks
SHOW ENGINE INNODB STATUS;

-- Transactions
SELECT * FROM information_schema.INNODB_TRX;
```

---

## Exercícios práticos

### Exercício 1: Transferência bancária segura

**Enunciado:** Implemente uma transferência bancária com o isolation level certo para evitar race conditions.

<details>
<summary>Solução</summary>

```php
// app/Services/BankTransferService.php
namespace App\Services;

use App\Models\Account;
use App\Exceptions\InsufficientFundsException;
use Illuminate\Support\Facades\DB;

class BankTransferService
{
    public function transfer(int $fromAccountId, int $toAccountId, float $amount): void
    {
        // Serializable para operação financeira crítica
        DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');

        DB::transaction(function () use ($fromAccountId, $toAccountId, $amount) {
            // Trava as contas na ordem do ID (evita deadlock)
            $ids = [$fromAccountId, $toAccountId];
            sort($ids);

            $accounts = Account::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $fromAccount = $accounts[$fromAccountId];
            $toAccount = $accounts[$toAccountId];

            // Checagem de saldo
            if ($fromAccount->balance < $amount) {
                throw new InsufficientFundsException(
                    "Saldo insuficiente. Disponível: {$fromAccount->balance}, Necessário: {$amount}"
                );
            }

            // Faz o transfer
            $fromAccount->decrement('balance', $amount);
            $toAccount->increment('balance', $amount);

            // Log da transação
            DB::table('transactions')->insert([
                'from_account_id' => $fromAccountId,
                'to_account_id' => $toAccountId,
                'amount' => $amount,
                'created_at' => now(),
            ]);
        });
    }

    // Variante com retry no deadlock
    public function transferWithRetry(int $fromAccountId, int $toAccountId, float $amount): void
    {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->transfer($fromAccountId, $toAccountId, $amount);
                return; // Deu certo
            } catch (\Illuminate\Database\QueryException $e) {
                // Deadlock error code
                if ($e->getCode() === '40P01' && $attempt < $maxAttempts) {
                    // Exponential backoff
                    usleep(100000 * $attempt); // 100ms, 200ms, 300ms
                    continue;
                }
                throw $e;
            }
        }
    }
}
```

</details>

### Exercício 2: Demonstração de Isolation Levels

**Enunciado:** Crie uma Artisan command para demonstrar os problemas de acesso concurrent.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/DemoIsolationLevels.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoIsolationLevels extends Command
{
    protected $signature = 'demo:isolation {level}';
    protected $description = 'Demonstra o comportamento dos isolation levels';

    public function handle()
    {
        $level = $this->argument('level');

        match($level) {
            'dirty-read' => $this->demoDirtyRead(),
            'non-repeatable' => $this->demoNonRepeatableRead(),
            'phantom' => $this->demoPhantomRead(),
            default => $this->error('Nível inválido')
        };
    }

    private function demoDirtyRead()
    {
        $this->info('=== Dirty Read Demo ===');

        // Transaction A (em outro processo, simulamos com sleep)
        $this->comment('Transaction A: BEGIN');
        DB::statement('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        DB::beginTransaction();

        $this->comment('Transaction A: UPDATE balance = 500');
        DB::table('accounts')->where('id', 1)->update(['balance' => 500]);

        // Transaction B lê dados uncommitted
        $this->comment('Transaction B: SELECT balance');
        $balance = DB::table('accounts')->where('id', 1)->value('balance');
        $this->line("Transaction B vê: balance = {$balance}");

        // Transaction A dá rollback
        $this->comment('Transaction A: ROLLBACK');
        DB::rollBack();

        $actualBalance = DB::table('accounts')->where('id', 1)->value('balance');
        $this->error("Balance real: {$actualBalance}");
        $this->error('Transaction B leu dados que não existem!');
    }

    private function demoNonRepeatableRead()
    {
        $this->info('=== Non-Repeatable Read Demo ===');

        DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        DB::beginTransaction();

        // Primeira leitura
        $balance1 = DB::table('accounts')->where('id', 1)->value('balance');
        $this->line("Primeira leitura: balance = {$balance1}");

        $this->comment('Outra transação alterou o balance...');
        // Simula mudança em outra transação
        DB::commit();
        DB::table('accounts')->where('id', 1)->update(['balance' => 999]);
        DB::beginTransaction();

        // Segunda leitura
        $balance2 = DB::table('accounts')->where('id', 1)->value('balance');
        $this->line("Segunda leitura: balance = {$balance2}");

        if ($balance1 !== $balance2) {
            $this->error('Non-Repeatable Read: os valores são diferentes!');
        }

        DB::commit();
    }

    private function demoPhantomRead()
    {
        $this->info('=== Phantom Read Demo ===');

        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        DB::beginTransaction();

        // Primeira contagem
        $count1 = DB::table('orders')->where('status', 'pending')->count();
        $this->line("Primeira contagem: {$count1} pending orders");

        $this->comment('Outra transação inseriu um order...');
        DB::commit();
        DB::table('orders')->insert(['status' => 'pending', 'total' => 100]);
        DB::beginTransaction();

        // Segunda contagem
        $count2 = DB::table('orders')->where('status', 'pending')->count();
        $this->line("Segunda contagem: {$count2} pending orders");

        if ($count1 !== $count2) {
            $this->error('Phantom Read: a quantidade de linhas mudou!');
        }

        DB::commit();
    }
}
```

</details>

### Exercício 3: Inventory Management com Repeatable Read

**Enunciado:** Implemente um sistema de estoque com o isolamento certo.

<details>
<summary>Solução</summary>

```php
// app/Services/InventoryService.php
namespace App\Services;

use App\Models\Product;
use App\Models\Order;
use App\Exceptions\OutOfStockException;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function createOrder(int $productId, int $quantity): Order
    {
        // Repeatable Read basta para inventory
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        return DB::transaction(function () use ($productId, $quantity) {
            // Trava o produto
            $product = Product::lockForUpdate()->findOrFail($productId);

            // Checa o stock (vê o snapshot do início da transação)
            if ($product->stock < $quantity) {
                throw new OutOfStockException(
                    "Produto {$product->name} está sem estoque. Disponível: {$product->stock}, Pedido: {$quantity}"
                );
            }

            // Diminui o stock
            $product->decrement('stock', $quantity);

            // Cria o pedido
            $order = Order::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'total' => $product->price * $quantity,
            ]);

            return $order;
        });
    }

    // Batch order com tratamento de deadlock
    public function createBatchOrder(array $items): array
    {
        DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');

        return DB::transaction(function () use ($items) {
            $orders = [];

            // Ordena por product_id para evitar deadlock
            usort($items, fn($a, $b) => $a['product_id'] <=> $b['product_id']);

            foreach ($items as $item) {
                $orders[] = $this->createOrder(
                    $item['product_id'],
                    $item['quantity']
                );
            }

            return $orders;
        });
    }
}
```

</details>

---

## Na entrevista

> "Isolation Levels controlam o que uma transação vê enquanto outras transações concorrentes mudam os dados. 4 níveis: Read Uncommitted (dirty reads OK), Read Committed (default no PostgreSQL, só dados committed), Repeatable Read (default no MySQL, snapshot isolation), Serializable (como execução em série). Problemas: Dirty Read (uncommitted), Non-Repeatable Read (valores diferentes), Phantom Read (quantidade de linhas diferente). Trade-off: mais isolamento → mais consistency, menos performance. Deadlock: transações esperam uma pela outra. Solução: retry e lock na mesma ordem."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
