# 6.4 Transações (ACID)

## Resumo

> **Transação** — grupo de operações SQL que rodam como um bloco só. Ou todas passam, ou todas desfazem.
>
> **ACID:** Atomicity (tudo ou nada), Consistency (integridade), Isolation (não se atrapalham), Durability (depois do commit está salvo).
>
> **Importante:** lockForUpdate() para pessimistic locking. Isolation levels: READ COMMITTED, REPEATABLE READ (default no MySQL), SERIALIZABLE.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Propriedades ACID](#propriedades-acid)
- [Níveis de isolamento](#níveis-de-isolamento)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Transação é um grupo de operações SQL que rodam como um bloco só. Ou todas passam, ou todas desfazem.

**Propriedades ACID:**
- **Atomicity** (atomicidade) — tudo ou nada
- **Consistency** (consistência) — os dados continuam corretos
- **Isolation** (isolamento) — as transações não se atrapalham
- **Durability** (durabilidade) — depois do commit os dados estão salvos

---

## Como funciona

**Sintaxe básica:**

```sql
-- Começar a transação
START TRANSACTION;
-- ou
BEGIN;

-- Executar as operações
UPDATE accounts SET balance = balance - 100 WHERE id = 1;
UPDATE accounts SET balance = balance + 100 WHERE id = 2;

-- Confirmar as alterações
COMMIT;

-- Ou desfazer se der erro
ROLLBACK;
```

**No Laravel:**

```php
use Illuminate\Support\Facades\DB;

// Transação automática
DB::transaction(function () {
    $user = User::find(1);
    $user->decrement('balance', 100);

    $recipient = User::find(2);
    $recipient->increment('balance', 100);

    Transaction::create([
        'from_user_id' => $user->id,
        'to_user_id' => $recipient->id,
        'amount' => 100,
    ]);
});
// COMMIT automático se der certo, ROLLBACK se lançar exceção

// Controle manual
DB::beginTransaction();

try {
    // Operações
    $user->decrement('balance', 100);
    $recipient->increment('balance', 100);

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

---

## Propriedades ACID

**Atomicity (atomicidade):**

Todas as operações rodam por completo ou nenhuma roda.

```php
// Exemplo: transferência de dinheiro
DB::transaction(function () {
    // Se qualquer operação falhar, todas desfazem
    User::where('id', 1)->decrement('balance', 100);
    User::where('id', 2)->increment('balance', 100);
    Transaction::create(['amount' => 100]);
});

// Impossível debitar sem creditar
```

**Consistency (consistência):**

O banco fica em estado válido (constraints não quebram).

```sql
-- Constraint: balance >= 0
ALTER TABLE users ADD CONSTRAINT check_balance CHECK (balance >= 0);

START TRANSACTION;

-- Esta operação falha se balance < 100
UPDATE users SET balance = balance - 100 WHERE id = 1;

-- A transação desfaz, balance continua >= 0
COMMIT;
```

**Isolation (isolamento):**

Transações simultâneas não se atrapalham.

```sql
-- Transação 1
START TRANSACTION;
SELECT balance FROM users WHERE id = 1;  -- balance = 1000
UPDATE users SET balance = balance - 100 WHERE id = 1;
-- Ainda sem COMMIT

-- Transação 2 (em outra conexão)
START TRANSACTION;
SELECT balance FROM users WHERE id = 1;  -- balance = 1000 (vê o valor antigo)
COMMIT;

-- Transação 1
COMMIT;  -- Agora balance = 900
```

**Durability (durabilidade):**

Depois do COMMIT os dados ficam salvos (mesmo se o servidor cair).

```sql
START TRANSACTION;
INSERT INTO orders (user_id, total) VALUES (1, 1000);
COMMIT;

-- Mesmo se o servidor cair logo após o COMMIT,
-- o registro continua no banco depois do restart
```

---

## Níveis de isolamento

**Níveis de isolamento (do mais fraco ao mais forte):**

1. READ UNCOMMITTED — vê mudanças ainda sem commit
2. READ COMMITTED — vê só o que já teve commit (default no PostgreSQL)
3. REPEATABLE READ — fixa um snapshot (default no MySQL)
4. SERIALIZABLE — isolamento total

```sql
-- Definir o nível
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
SET TRANSACTION ISOLATION LEVEL REPEATABLE READ;
```

**No Laravel:**

```php
// Default: REPEATABLE READ (MySQL)
DB::transaction(function () {
    // Operações
});

// Mudar o nível de isolamento
DB::transaction(function () {
    DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    // Operações
});
```

---

## Quando usar

**Use transação quando:**
- Várias operações ligadas (transferência de dinheiro)
- Precisa de atomicidade (tudo ou nada)
- Integridade dos dados é crítica

**Não use quando:**
- INSERT/UPDATE isolado
- Só leitura (SELECT)
- Operação longa (trava tabelas)

---

## Exemplo prático

**Transferência de dinheiro:**

```php
class TransferService
{
    public function transfer(User $from, User $to, int $amount): Transaction
    {
        return DB::transaction(function () use ($from, $to, $amount) {
            // Checar saldo com lock
            $from = User::where('id', $from->id)
                ->lockForUpdate()  // SELECT ... FOR UPDATE
                ->first();

            if ($from->balance < $amount) {
                throw new InsufficientFundsException();
            }

            // Debitar
            $from->decrement('balance', $amount);

            // Creditar
            $to = User::where('id', $to->id)
                ->lockForUpdate()
                ->first();
            $to->increment('balance', $amount);

            // Registrar a transação
            return Transaction::create([
                'from_user_id' => $from->id,
                'to_user_id' => $to->id,
                'amount' => $amount,
                'status' => 'completed',
            ]);
        });
    }
}
```

**Criar pedido e atualizar estoque:**

```php
class OrderService
{
    public function create(User $user, array $items): Order
    {
        return DB::transaction(function () use ($user, $items) {
            // Criar o pedido
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $this->calculateTotal($items),
                'status' => 'pending',
            ]);

            // Criar items e atualizar estoque
            foreach ($items as $item) {
                // Checar estoque com lock
                $product = Product::where('id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($product->stock < $item['quantity']) {
                    throw new OutOfStockException($product->name);
                }

                // Criar order item
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                // Diminuir stock
                $product->decrement('stock', $item['quantity']);
            }

            // Debitar o saldo
            $user->decrement('balance', $order->total);

            return $order;
        });
    }
}
```

**Transações aninhadas (savepoints):**

```php
DB::transaction(function () {
    // Operação 1
    User::create(['name' => 'João']);

    try {
        DB::transaction(function () {
            // Operação 2 (pode falhar)
            Post::create(['title' => 'Inválido']);
        });
    } catch (\Exception $e) {
        // Operação 2 desfez, mas a 1 continua
    }

    // Operação 3
    User::create(['name' => 'Ana']);
});
```

**Pessimistic locking (bloqueio pessimista):**

```php
// lockForUpdate() — SELECT ... FOR UPDATE (trava para escrita)
$user = User::where('id', 1)
    ->lockForUpdate()
    ->first();

$user->increment('balance', 100);

// sharedLock() — SELECT ... LOCK IN SHARE MODE (trava para leitura)
$user = User::where('id', 1)
    ->sharedLock()
    ->first();
```

**Optimistic locking (via version):**

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->integer('version')->default(0);
});

// Update com checagem de versão
$user = User::find(1);
$currentVersion = $user->version;

$updated = User::where('id', $user->id)
    ->where('version', $currentVersion)
    ->update([
        'balance' => $user->balance + 100,
        'version' => $currentVersion + 1,
    ]);

if (!$updated) {
    throw new ConcurrentModificationException();
}
```

**Deadlock (bloqueio mútuo):**

```php
// Transação 1
DB::transaction(function () {
    User::where('id', 1)->lockForUpdate()->first();  // Trava o user 1
    sleep(1);
    User::where('id', 2)->lockForUpdate()->first();  // Espera o user 2
});

// Transação 2 (ao mesmo tempo)
DB::transaction(function () {
    User::where('id', 2)->lockForUpdate()->first();  // Trava o user 2
    sleep(1);
    User::where('id', 1)->lockForUpdate()->first();  // Espera o user 1
});

// Resultado: DEADLOCK

// Solução: sempre travar na mesma ordem (menor id primeiro)
$ids = [$fromUserId, $toUserId];
sort($ids);

foreach ($ids as $id) {
    User::where('id', $id)->lockForUpdate()->first();
}
```

---

## Na entrevista

> "Transação é um grupo de operações que rodam de forma atômica. ACID: Atomicity (tudo ou nada), Consistency (integridade), Isolation (não se atrapalham), Durability (depois do commit está salvo). No Laravel: DB::transaction() faz commit/rollback sozinho, DB::beginTransaction/commit/rollBack na mão. lockForUpdate() para pessimistic locking (SELECT FOR UPDATE). Isolation levels: READ COMMITTED, REPEATABLE READ (default no MySQL), SERIALIZABLE. Deadlock é quando duas transações esperam uma pela outra — solução: travar na mesma ordem."

---

## Exercícios práticos

### Exercício 1: Transferência segura de pontos

O usuário pode transferir pontos para outro usuário. Implemente a transação com checagem de saldo e sem race condition.

<details>
<summary>Solução</summary>

```php
class PointsTransferService
{
    public function transfer(User $from, User $to, int $points): void
    {
        DB::transaction(function () use ($from, $to, $points) {
            // Pegar o remetente com lock
            $sender = User::where('id', $from->id)
                ->lockForUpdate()
                ->first();

            // Checar saldo
            if ($sender->points < $points) {
                throw new InsufficientPointsException(
                    "Pontos insuficientes. Disponível: {$sender->points}"
                );
            }

            // Pegar o destinatário com lock
            $receiver = User::where('id', $to->id)
                ->lockForUpdate()
                ->first();

            // Fazer a transferência
            $sender->decrement('points', $points);
            $receiver->increment('points', $points);

            // Registrar o histórico
            PointsTransaction::create([
                'from_user_id' => $sender->id,
                'to_user_id' => $receiver->id,
                'points' => $points,
                'type' => 'transfer',
                'created_at' => now(),
            ]);
        });
    }
}

// Teste
try {
    $service = new PointsTransferService();
    $service->transfer($user1, $user2, 100);
    echo "Transferência concluída";
} catch (InsufficientPointsException $e) {
    echo "Erro: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro de sistema: " . $e->getMessage();
}
```
</details>

### Exercício 2: Corrija o deadlock

Duas transações geram deadlock. Como corrigir?

```php
// Transação 1
DB::transaction(function () {
    User::where('id', 1)->lockForUpdate()->first();
    sleep(1);
    User::where('id', 2)->lockForUpdate()->first();
});

// Transação 2 (ao mesmo tempo)
DB::transaction(function () {
    User::where('id', 2)->lockForUpdate()->first();
    sleep(1);
    User::where('id', 1)->lockForUpdate()->first();
});
```

<details>
<summary>Solução</summary>

```php
// ❌ Problema: deadlock
// Transação 1 trava o user 1, espera o user 2
// Transação 2 trava o user 2, espera o user 1
// Resultado: bloqueio mútuo

// ✅ Solução: sempre travar na mesma ordem (por ID)
class SafeTransferService
{
    public function transfer(User $from, User $to, int $amount): void
    {
        DB::transaction(function () use ($from, $to, $amount) {
            // Definir a ordem do lock (menor ID primeiro)
            $ids = [$from->id, $to->id];
            sort($ids);

            // Travar na mesma ordem
            $users = User::whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sender = $users[$from->id];
            $receiver = $users[$to->id];

            // Checagens e transferência
            if ($sender->balance < $amount) {
                throw new InsufficientFundsException();
            }

            $sender->decrement('balance', $amount);
            $receiver->increment('balance', $amount);
        });
    }
}

// Solução alternativa: retry no deadlock
class RetryableTransferService
{
    public function transfer(User $from, User $to, int $amount): void
    {
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                DB::transaction(function () use ($from, $to, $amount) {
                    // Seu código da transação
                    $sender = User::where('id', $from->id)->lockForUpdate()->first();
                    $receiver = User::where('id', $to->id)->lockForUpdate()->first();

                    $sender->decrement('balance', $amount);
                    $receiver->increment('balance', $amount);
                });

                return; // Deu certo
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 40001 || str_contains($e->getMessage(), 'Deadlock')) {
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        throw $e;
                    }
                    usleep(100000 * $attempt); // Exponential backoff
                } else {
                    throw $e;
                }
            }
        }
    }
}
```
</details>

### Exercício 3: Optimistic vs Pessimistic Locking

Implemente as duas abordagens para atualizar o contador de views do artigo.

<details>
<summary>Solução</summary>

```php
// Pessimistic locking (trava na escrita)
class PessimisticViewCounter
{
    public function increment(Post $post): void
    {
        DB::transaction(function () use ($post) {
            // Travar o registro
            $lockedPost = Post::where('id', $post->id)
                ->lockForUpdate()
                ->first();

            // Atualizar o contador
            $lockedPost->increment('views');
        });
    }
}

// Prós: consistência garantida
// Contras: trava outras transações (mais lento)

// Optimistic locking (checagem de versão)
class OptimisticViewCounter
{
    public function increment(Post $post): void
    {
        $maxRetries = 5;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            // Ler a versão atual
            $currentVersion = $post->version;
            $currentViews = $post->views;

            // Tentar o update checando a versão
            $updated = Post::where('id', $post->id)
                ->where('version', $currentVersion) // Checagem de versão
                ->update([
                    'views' => $currentViews + 1,
                    'version' => $currentVersion + 1,
                ]);

            if ($updated) {
                return; // Deu certo
            }

            // Se a versão mudou, tenta de novo
            $post->refresh();
            $attempt++;
        }

        throw new ConcurrentModificationException('Não deu para atualizar depois de várias tentativas');
    }
}

// Prós: não trava, mais rápido quando o conflito é raro
// Contras: precisa de retry, pode ficar mais lento se o conflito for frequente

// Migration para optimistic locking
Schema::table('posts', function (Blueprint $table) {
    $table->integer('version')->default(0);
});

// Quando usar?
// Pessimistic: conflito frequente, integridade crítica (transferência de dinheiro)
// Optimistic: conflito raro, carga alta (contador de views)

// Abordagem híbrida: sem lock para contadores
class SimpleViewCounter
{
    public function increment(Post $post): void
    {
        // Update direto, sem lock
        Post::where('id', $post->id)->increment('views');

        // Para contagem mais precisa, dá para usar Redis
        Redis::incr("post:{$post->id}:views");

        // Sincronizar com o banco de tempos em tempos
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
