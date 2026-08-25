# 9.5 Locks (bloqueios)

> **TL;DR:** Locks impedem acesso concorrente aos dados. Shared Lock (FOR SHARE) deixa ler. Exclusive Lock (FOR UPDATE) bloqueia tudo. SKIP LOCKED para queue workers. Optimistic Locking checa a versão sem travar. Advisory Locks para lock no nível da aplicação. Deadlock se resolve travando na mesma ordem.

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de lock](#tipos-de-lock)
  - [Shared Lock](#1-shared-lock-s-lock--read-lock)
  - [Exclusive Lock](#2-exclusive-lock-x-lock--write-lock)
  - [Row-Level Lock](#3-row-level-lock-bloqueio-de-linha)
  - [Table-Level Lock](#4-table-level-lock-bloqueio-de-tabela)
- [FOR UPDATE SKIP LOCKED](#for-update-skip-locked)
- [FOR UPDATE NOWAIT](#for-update-nowait)
- [Optimistic Locking](#optimistic-locking-bloqueio-otimista)
- [Advisory Locks](#advisory-locks-postgresql)
- [Lock Wait Timeout](#lock-wait-timeout)
- [Deadlocks](#deadlocks-bloqueio-mútuo)
- [Monitoramento de locks](#monitoramento-de-locks)
- [Boas práticas](#boas-práticas)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Locks (bloqueios):**
Mecanismo que impede duas transações de alterarem o mesmo dado ao mesmo tempo.

**Para quê:**
- Evitar race conditions
- Garantir consistência dos dados
- Controlar acesso concorrente

**Trade-off:**
- Mais locks → mais consistência, menos concorrência
- Menos locks → menos consistência, mais concorrência

---

## Tipos de lock

### 1. Shared Lock (S-lock) — READ Lock

**O que faz:**
- Deixa os outros lerem
- Impede os outros de escreverem

```sql
-- PostgreSQL
BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR SHARE;
-- Outros podem SELECT, mas não UPDATE/DELETE
COMMIT;
```

---

### 2. Exclusive Lock (X-lock) — WRITE Lock

**O que faz:**
- Impede os outros de ler e de escrever

```sql
-- PostgreSQL
BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;
-- Outros NÃO podem SELECT FOR UPDATE/SHARE, UPDATE, DELETE
COMMIT;
```

**Laravel:**

```php
// Shared lock (FOR SHARE)
$user = User::lockForShare()->find($id);

// Exclusive lock (FOR UPDATE)
$user = User::lockForUpdate()->find($id);
```

---

### 3. Row-Level Lock (bloqueio de linha)

**PostgreSQL:**

```sql
-- Trava a linha específica
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;
```

**Use case: transferência bancária**

```php
DB::transaction(function () use ($fromId, $toId, $amount) {
    // Trava as duas linhas na ordem do ID (prevenção de deadlock)
    $accounts = Account::whereIn('id', [$fromId, $toId])
        ->orderBy('id')
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    $from = $accounts[$fromId];
    $to = $accounts[$toId];

    if ($from->balance < $amount) {
        throw new InsufficientFundsException();
    }

    // Altera com segurança (ninguém mais consegue)
    $from->decrement('balance', $amount);
    $to->increment('balance', $amount);
});
```

---

### 4. Table-Level Lock (bloqueio de tabela)

```sql
-- PostgreSQL
LOCK TABLE accounts IN ACCESS EXCLUSIVE MODE;
-- Ninguém pode ler/escrever
```

**Modos:**

```sql
-- ACCESS SHARE (leitura permitida)
LOCK TABLE accounts IN ACCESS SHARE MODE;

-- ROW EXCLUSIVE (UPDATE/DELETE comuns)
LOCK TABLE accounts IN ROW EXCLUSIVE MODE;

-- ACCESS EXCLUSIVE (ninguém pode nada)
LOCK TABLE accounts IN ACCESS EXCLUSIVE MODE;
```

**Quando usar:**
- ❌ Raro em production (trava a tabela inteira)
- ✅ Operações de maintenance (ALTER TABLE, TRUNCATE)

---

## FOR UPDATE SKIP LOCKED

**Problema: Queue Workers**

```sql
-- Worker 1
BEGIN;
SELECT * FROM jobs WHERE status = 'pending' LIMIT 1 FOR UPDATE;
-- Pegou o job #1

-- Worker 2 (ao mesmo tempo)
SELECT * FROM jobs WHERE status = 'pending' LIMIT 1 FOR UPDATE;
-- Espera o Worker 1 terminar (fica bloqueado!)
```

**Solução: SKIP LOCKED**

```sql
-- Worker 1
BEGIN;
SELECT * FROM jobs WHERE status = 'pending' LIMIT 1 FOR UPDATE SKIP LOCKED;
-- Pegou o job #1

-- Worker 2
SELECT * FROM jobs WHERE status = 'pending' LIMIT 1 FOR UPDATE SKIP LOCKED;
-- Pulou o job #1, pegou o job #2 (não espera!)
```

**Laravel Queue Worker:**

```php
class ProcessNextJob
{
    public function handle()
    {
        // Pega o próximo job sem ficar esperando lock
        $job = Job::where('status', 'pending')
            ->orderBy('created_at')
            ->limit(1)
            ->lockForUpdate()  // FOR UPDATE SKIP LOCKED
            ->first();

        if (!$job) {
            return;  // Sem jobs disponíveis
        }

        $job->update(['status' => 'processing']);

        // Processa o job...
    }
}
```

---

## FOR UPDATE NOWAIT

**O que faz:**
Em vez de esperar, estoura erro na hora.

```sql
BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR UPDATE NOWAIT;
-- Se já estiver travado → ERROR: could not obtain lock
```

**Laravel:**

```php
try {
    $account = Account::where('id', 1)
        ->lockForUpdate()  // dá para adicionar NOWAIT via raw
        ->first();

} catch (QueryException $e) {
    if ($e->getCode() === '55P03') {  // lock_not_available
        return response()->json(['error' => 'Recurso bloqueado'], 423);
    }
}
```

---

## Optimistic Locking (bloqueio otimista)

**Ideia:**
Não trava. Na hora de salvar, checa a versão.

**Implementação:**

```php
// Migration
Schema::table('products', function (Blueprint $table) {
    $table->integer('version')->default(0);
});

// Model
class Product extends Model
{
    public function updateStock($quantity)
    {
        $currentVersion = $this->version;

        // Tentativa de update
        $updated = DB::table('products')
            ->where('id', $this->id)
            ->where('version', $currentVersion)  // Checagem da versão
            ->update([
                'stock' => DB::raw('stock - ' . $quantity),
                'version' => $currentVersion + 1,  // Incrementa a versão
            ]);

        if ($updated === 0) {
            // Alguém alterou antes da gente
            throw new OptimisticLockException('Produto foi modificado por outra transação');
        }

        $this->refresh();
    }
}

// Uso
try {
    $product->updateStock(5);
} catch (OptimisticLockException $e) {
    // Retry ou avisa o usuário
    return response()->json(['error' => 'Produto foi modificado, tente de novo'], 409);
}
```

**Prós:**
- ✅ Alta concorrência (não trava)
- ✅ Sem deadlocks

**Contras:**
- ❌ Precisa de retries
- ❌ Pode falhar bastante com concorrência alta

---

## Advisory Locks (PostgreSQL)

**O que é:**
Locks no nível da aplicação (não presos à transação).

```sql
-- Obter o lock
SELECT pg_advisory_lock(123);

-- Checar disponibilidade
SELECT pg_try_advisory_lock(123);  -- true/false

-- Liberar
SELECT pg_advisory_unlock(123);
```

**Use case: evitar jobs duplicados**

```php
class ProcessUniqueTask
{
    public function handle($taskId)
    {
        // Tenta obter o lock
        $locked = DB::selectOne("SELECT pg_try_advisory_lock(?) as locked", [$taskId])->locked;

        if (!$locked) {
            // Outro processo já está processando
            Log::info("Task {$taskId} já está em processamento");
            return;
        }

        try {
            // Processa a task...
            $this->processTask($taskId);

        } finally {
            // Libera o lock
            DB::statement("SELECT pg_advisory_unlock(?)", [$taskId]);
        }
    }
}
```

**Use case: Singleton Scheduler**

```php
// Garante que só 1 instância do scheduler roda
class Scheduler
{
    private const LOCK_ID = 999999;

    public function run()
    {
        $locked = DB::selectOne(
            "SELECT pg_try_advisory_lock(?) as locked",
            [self::LOCK_ID]
        )->locked;

        if (!$locked) {
            die("Scheduler já está em execução\n");
        }

        // Loop do scheduler...
        while (true) {
            $this->processTasks();
            sleep(60);
        }

        // O lock libera quando o processo termina
    }
}
```

---

## Lock Wait Timeout

**PostgreSQL:**

```sql
-- Definir timeout de espera do lock
SET lock_timeout = '5s';

BEGIN;
SELECT * FROM accounts WHERE id = 1 FOR UPDATE;
-- Se não pegar o lock em 5s → ERROR
COMMIT;
```

**Config do Laravel:**

```php
// config/database.php
'pgsql' => [
    'options' => [
        '--client_encoding=utf8',
        '--lock_timeout=5000',  // 5 segundos
    ],
],
```

---

## Deadlocks (bloqueio mútuo)

**Problema:**

```sql
-- Transaction A
BEGIN;
UPDATE accounts SET balance = balance - 100 WHERE id = 1;  -- Lock row 1
-- waiting...
UPDATE accounts SET balance = balance + 100 WHERE id = 2;  -- Need row 2

-- Transaction B
BEGIN;
UPDATE accounts SET balance = balance - 50 WHERE id = 2;   -- Lock row 2
-- waiting...
UPDATE accounts SET balance = balance + 50 WHERE id = 1;   -- Need row 1

-- DEADLOCK!
```

**O PostgreSQL detecta e dá rollback em uma transação:**

```
ERROR: deadlock detected
DETAIL: Process 1234 waits for ShareLock on transaction 5678
```

**Solução: travar na mesma ordem**

```php
DB::transaction(function () use ($fromId, $toId, $amount) {
    // Sempre travar na ordem do ID
    $ids = [$fromId, $toId];
    sort($ids);

    $accounts = Account::whereIn('id', $ids)
        ->orderBy('id')  // ← Crítico!
        ->lockForUpdate()
        ->get()
        ->keyBy('id');

    // Agora é seguro
    $accounts[$fromId]->decrement('balance', $amount);
    $accounts[$toId]->increment('balance', $amount);
});
```

---

## Monitoramento de locks

**PostgreSQL:**

```sql
-- Locks atuais
SELECT
    pid,
    usename,
    pg_blocking_pids(pid) as blocked_by,
    query,
    state
FROM pg_stat_activity
WHERE cardinality(pg_blocking_pids(pid)) > 0;

-- Queries esperando
SELECT
    wait_event_type,
    wait_event,
    query,
    state,
    state_change
FROM pg_stat_activity
WHERE wait_event IS NOT NULL;
```

**MySQL:**

```sql
-- InnoDB locks
SELECT * FROM information_schema.INNODB_LOCKS;

-- Transações esperando
SELECT * FROM information_schema.INNODB_LOCK_WAITS;
```

---

## Boas práticas

```
✓ Encurtar o tempo do lock (transações curtas)
✓ Travar na mesma ordem (por ID) para evitar deadlock
✓ Usar SKIP LOCKED em queue workers
✓ Usar Optimistic Locking em dados read-mostly com alta concorrência
✓ Monitorar deadlocks e locks longos
✓ Evitar Table-Level lock em production
✓ Definir lock_timeout
✓ Operação crítica: SELECT FOR UPDATE + versionamento
```

---

## Exercícios práticos

### Exercício 1: Queue Worker com SKIP LOCKED

**Enunciado:** Implemente um queue worker que processa tasks sem ficar preso em lock.

<details>
<summary>Solução</summary>

```php
// app/Jobs/ProcessQueueJob.php
namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessQueueJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // PostgreSQL
        $job = $this->getNextJobPostgres();

        // MySQL (versões antigas não suportam SKIP LOCKED)
        // $job = $this->getNextJobMySQL();

        if (!$job) {
            return; // Sem tasks disponíveis
        }

        try {
            // Processa a task
            $this->processJob($job);

            // Marca como concluída
            DB::table('queue_jobs')
                ->where('id', $job->id)
                ->update(['status' => 'completed', 'completed_at' => now()]);

        } catch (\Exception $e) {
            // Marca como failed
            DB::table('queue_jobs')
                ->where('id', $job->id)
                ->update([
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_at' => now()
                ]);
        }
    }

    private function getNextJobPostgres()
    {
        return DB::transaction(function () {
            $job = DB::table('queue_jobs')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(1)
                ->lockForUpdate('skip locked')
                ->first();

            if ($job) {
                // Já marca como processing
                DB::table('queue_jobs')
                    ->where('id', $job->id)
                    ->update(['status' => 'processing', 'started_at' => now()]);
            }

            return $job;
        });
    }

    private function getNextJobMySQL()
    {
        return DB::transaction(function () {
            // MySQL: usamos UPDATE com WHERE
            $affected = DB::table('queue_jobs')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(1)
                ->update([
                    'status' => 'processing',
                    'started_at' => now()
                ]);

            if ($affected === 0) {
                return null;
            }

            return DB::table('queue_jobs')
                ->where('status', 'processing')
                ->whereNull('completed_at')
                ->orderBy('started_at', 'desc')
                ->first();
        });
    }

    private function processJob($job)
    {
        // Simulação do processamento
        $data = json_decode($job->payload, true);

        match($job->type) {
            'send_email' => $this->sendEmail($data),
            'process_image' => $this->processImage($data),
            'generate_report' => $this->generateReport($data),
            default => throw new \Exception("Tipo de job desconhecido: {$job->type}")
        };
    }
}

// Migration da queue_jobs
Schema::create('queue_jobs', function (Blueprint $table) {
    $table->id();
    $table->string('type');
    $table->json('payload');
    $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
    $table->text('error')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->timestamps();

    $table->index(['status', 'created_at']);
});
```

</details>

### Exercício 2: Optimistic Locking em updates de Product

**Enunciado:** Implemente optimistic locking para evitar lost updates.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::table('products', function (Blueprint $table) {
    $table->integer('version')->default(0)->after('id');
});

// app/Models/Product.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Exceptions\OptimisticLockException;

class Product extends Model
{
    protected $fillable = ['name', 'price', 'stock', 'version'];

    public function updateWithOptimisticLock(array $data): bool
    {
        $currentVersion = $this->version;

        // Incrementa a versão
        $data['version'] = $currentVersion + 1;

        // Atualiza só se a versão não mudou
        $updated = static::where('id', $this->id)
            ->where('version', $currentVersion)
            ->update($data);

        if ($updated === 0) {
            throw new OptimisticLockException(
                "Product #{$this->id} foi modificado por outra transação. Recarregue e tente de novo."
            );
        }

        // Atualiza o model
        $this->refresh();

        return true;
    }

    public function decrementStock(int $quantity): void
    {
        $currentVersion = $this->version;

        $updated = static::where('id', $this->id)
            ->where('version', $currentVersion)
            ->where('stock', '>=', $quantity) // Atomic check
            ->update([
                'stock' => DB::raw("stock - {$quantity}"),
                'version' => $currentVersion + 1,
            ]);

        if ($updated === 0) {
            $fresh = $this->fresh();

            if ($fresh->version !== $currentVersion) {
                throw new OptimisticLockException("Produto foi modificado por outra transação");
            }

            if ($fresh->stock < $quantity) {
                throw new OutOfStockException("Estoque insuficiente");
            }

            throw new \Exception("Falha ao atualizar estoque");
        }

        $this->refresh();
    }
}

// app/Http/Controllers/ProductController.php
public function update(Request $request, Product $product)
{
    $maxRetries = 3;
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            $product->updateWithOptimisticLock($request->validated());

            return response()->json([
                'message' => 'Produto atualizado com sucesso',
                'product' => $product
            ]);

        } catch (OptimisticLockException $e) {
            $attempt++;

            if ($attempt >= $maxRetries) {
                return response()->json([
                    'error' => 'Produto foi modificado várias vezes. Recarregue e tente de novo.'
                ], 409);
            }

            // Exponential backoff
            usleep(50000 * $attempt); // 50ms, 100ms, 150ms

            // Recarrega o model
            $product->refresh();
        }
    }
}
```

</details>

### Exercício 3: Advisory Locks para tasks singleton

**Enunciado:** Use advisory locks do PostgreSQL para garantir uma única instância da task.

<details>
<summary>Solução</summary>

```php
// app/Services/AdvisoryLockService.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdvisoryLockService
{
    public function tryLock(int $lockId): bool
    {
        $result = DB::selectOne("SELECT pg_try_advisory_lock(?) as locked", [$lockId]);
        return $result->locked;
    }

    public function lock(int $lockId): void
    {
        DB::statement("SELECT pg_advisory_lock(?)", [$lockId]);
    }

    public function unlock(int $lockId): void
    {
        DB::statement("SELECT pg_advisory_unlock(?)", [$lockId]);
    }

    public function withLock(int $lockId, callable $callback)
    {
        if (!$this->tryLock($lockId)) {
            throw new \Exception("Falha ao obter lock {$lockId}");
        }

        try {
            return $callback();
        } finally {
            $this->unlock($lockId);
        }
    }
}

// app/Console/Commands/ProcessUniqueTask.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AdvisoryLockService;

class ProcessUniqueTask extends Command
{
    protected $signature = 'task:process-unique {task-id}';
    protected $description = 'Processa uma task que deve rodar só uma vez';

    public function __construct(
        private AdvisoryLockService $lockService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $taskId = $this->argument('task-id');

        try {
            $this->lockService->withLock($taskId, function () use ($taskId) {
                $this->info("Processando task {$taskId}...");

                // Processamento longo
                sleep(10);

                $this->info("Task {$taskId} concluída");
            });

        } catch (\Exception $e) {
            $this->error("Task {$taskId} já está em processamento");
            return 1;
        }
    }
}

// app/Console/Commands/SingletonScheduler.php
class SingletonScheduler extends Command
{
    protected $signature = 'scheduler:run-singleton';
    private const LOCK_ID = 999999;

    public function __construct(
        private AdvisoryLockService $lockService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        if (!$this->lockService->tryLock(self::LOCK_ID)) {
            $this->error('Scheduler já está em execução');
            return 1;
        }

        $this->info('Scheduler iniciado');

        try {
            // Scheduler loop
            while (true) {
                $this->processTasks();
                sleep(60);
            }
        } finally {
            $this->lockService->unlock(self::LOCK_ID);
        }
    }

    private function processTasks()
    {
        // Processa as tasks agendadas
        $this->info('Processando tasks...');
    }
}
```

</details>

---

## Na entrevista

> "Locks impedem acesso concorrente aos dados. Shared Lock (FOR SHARE) deixa ler. Exclusive Lock (FOR UPDATE) bloqueia tudo. Row-Level lock na linha, Table-Level na tabela. SKIP LOCKED para queue workers — não espera linha ocupada. Optimistic Locking: checa a versão na hora de salvar, sem travar. Advisory Locks no PostgreSQL para lock no nível da aplicação. Deadlock se resolve travando na mesma ordem (por ID). Monitoramento via pg_stat_activity. Boas práticas: transação curta, lock timeout, evitar Table lock."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
