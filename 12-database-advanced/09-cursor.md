# 9.9 Cursor

> **TL;DR:** Cursor itera linha a linha sem carregar tudo na memória. Laravel `cursor()` usa server-side cursors. Casos de uso: exportar volume grande para CSV, processar millions+ rows. Chunk pagina (dá para UPDATE), cursor faz streaming (read-only). Lazy Collections = cursor com operações funcionais. Não use em seleção pequena.

## Conteúdo

- [O que é](#o-que-é)
- [PostgreSQL Cursor](#postgresql-cursor)
- [Laravel Cursor](#laravel-cursor-lazy-collections)
- [Chunk vs Cursor](#chunk-vs-cursor)
- [Lazy Collections](#lazy-collections)
- [Export para CSV](#export-para-csv)
- [Cursor com filtros](#cursor-com-filtros)
- [PostgreSQL Server-Side Cursor](#postgresql-server-side-cursor)
- [WITH HOLD](#with-hold-postgresql)
- [Cursor para UPDATE](#cursor-para-update)
- [MySQL Cursor](#mysql-cursor)
- [Boas práticas](#boas-práticas)
- [Quando NÃO usar cursor](#quando-não-usar-cursor)
- [Monitoramento](#monitoramento)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Cursor:**
Mecanismo para iterar o resultado de um SQL linha a linha.

**Para quê:**
- Processar volume grande (sem carregar tudo na memória)
- Processar linha a linha com lógica
- Streaming de dados

**Trade-off:**
- ✅ Não carrega tudo na memória
- ❌ Mais lento que operação bulk
- ❌ Mantém a conexão aberta

**Quando usar:**
- Milhões de linhas (não cabem na memória)
- Lógica complexa em cada linha
- Export de volume grande

---

## PostgreSQL Cursor

**Exemplo básico:**

```sql
-- Declarar o cursor
DECLARE my_cursor CURSOR FOR
    SELECT id, email, name FROM users;

-- Abrir o cursor
OPEN my_cursor;

-- Fetch das linhas
FETCH NEXT FROM my_cursor;  -- Uma linha
FETCH 10 FROM my_cursor;    -- 10 linhas

-- Fechar o cursor
CLOSE my_cursor;
```

---

## Laravel Cursor (Lazy Collections)

**Laravel oferece Eloquent `cursor()`:**

```php
// ❌ RUIM: carrega TUDO na memória
$users = User::all();  // 10 million users → OutOfMemoryError

foreach ($users as $user) {
    $this->process($user);
}

// ✅ BOM: cursor
foreach (User::cursor() as $user) {
    $this->process($user);
}
// Carrega de 1000 em 1000, libera memória depois de processar
```

**Como funciona:**

```php
// User::cursor() por baixo
public function cursor()
{
    return $this->applyScopes()
        ->query
        ->cursor();  // DB cursor (PostgreSQL DECLARE CURSOR)
}
```

---

## Chunk vs Cursor

**Chunk (por páginas):**

```php
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        $this->process($user);
    }
});

// Executa:
// SELECT * FROM users LIMIT 1000 OFFSET 0
// SELECT * FROM users LIMIT 1000 OFFSET 1000
// SELECT * FROM users LIMIT 1000 OFFSET 2000
// ...
```

**Cursor (streaming):**

```php
foreach (User::cursor() as $user) {
    $this->process($user);
}

// Executa:
// DECLARE cursor_name CURSOR FOR SELECT * FROM users
// FETCH 1000 FROM cursor_name
// FETCH 1000 FROM cursor_name
// ...
```

**Quando chunk, quando cursor:**

| Chunk | Cursor |
|-------|--------|
| Dá para alterar as linhas | Read-only |
| ORDER BY instável | ORDER BY estável |
| Lógica simples | Lógica complexa |
| Tabelas pequenas | Tabelas enormes |

---

## Lazy Collections

**Laravel Lazy Collections = cursor por baixo:**

```php
// Processar 10 milhões de usuários
User::cursor()
    ->filter(fn ($user) => $user->isActive())
    ->map(fn ($user) => [
        'email' => $user->email,
        'name' => $user->name,
    ])
    ->each(fn ($data) => $this->sendEmail($data));

// Tudo roda em streaming (não carrega tudo na memória)
```

---

## Export para CSV

**Ruim: carrega tudo na memória**

```php
// ❌ OutOfMemoryError em volume grande
$users = User::all();

$csv = Writer::createFromPath('users.csv', 'w+');
$csv->insertAll($users->toArray());
```

**Bom: cursor**

```php
        // ✅ Export em streaming
$csv = Writer::createFromPath('users.csv', 'w+');

foreach (User::cursor() as $user) {
    $csv->insertOne([
        $user->id,
        $user->email,
        $user->name,
    ]);
}
```

**Laravel Job:**

```php
class ExportUsersJob implements ShouldQueue
{
    public function handle()
    {
        $file = storage_path('exports/users.csv');
        $handle = fopen($file, 'w');

        // Cabeçalho
        fputcsv($handle, ['ID', 'Email', 'Nome']);

        // Cursor (streaming)
        foreach (User::cursor() as $user) {
            fputcsv($handle, [
                $user->id,
                $user->email,
                $user->name,
            ]);
        }

        fclose($handle);

        // Envia para o usuário...
    }
}
```

---

## Cursor com filtros

```php
// Só usuários ativos
foreach (User::where('is_active', true)->cursor() as $user) {
    $this->process($user);
}

// Com relationships (problema N+1!)
foreach (User::with('orders')->cursor() as $user) {
    $this->process($user);
}
```

---

## PostgreSQL Server-Side Cursor

**Para seleção enorme (bilhões de linhas):**

```php
class ProcessHugeTable extends Command
{
    public function handle()
    {
        DB::transaction(function () {
            // Declarar o cursor
            DB::statement("DECLARE my_cursor CURSOR FOR SELECT * FROM huge_table");

            while (true) {
                // Fetch de 1000 linhas
                $rows = DB::select("FETCH 1000 FROM my_cursor");

                if (empty($rows)) {
                    break;  // Fim dos dados
                }

                foreach ($rows as $row) {
                    $this->process($row);
                }
            }

            // Fechar o cursor
            DB::statement("CLOSE my_cursor");
        });
    }
}
```

---

## WITH HOLD (PostgreSQL)

**Cursor comum fecha depois do COMMIT:**

```sql
BEGIN;
DECLARE my_cursor CURSOR FOR SELECT * FROM users;
COMMIT;
-- Cursor fechado!
```

**WITH HOLD — o cursor continua aberto:**

```sql
BEGIN;
DECLARE my_cursor CURSOR WITH HOLD FOR SELECT * FROM users;
COMMIT;

-- Cursor ainda aberto
FETCH FROM my_cursor;
```

**Laravel:**

```php
DB::transaction(function () {
    DB::statement("DECLARE my_cursor CURSOR WITH HOLD FOR SELECT * FROM users");
});

// Cursor disponível fora da transação
$rows = DB::select("FETCH 1000 FROM my_cursor");

DB::statement("CLOSE my_cursor");
```

---

## Cursor para UPDATE

**Update em partes (para não bloquear a tabela):**

```php
class UpdateUsersInBatches extends Command
{
    public function handle()
    {
        $processed = 0;

        foreach (User::where('status', 'pending')->cursor() as $user) {
            $user->update(['status' => 'active']);

            $processed++;

            if ($processed % 1000 === 0) {
                $this->info("Processados: {$processed}");
            }
        }
    }
}
```

**Melhor: chunk com UPDATE:**

```php
User::where('status', 'pending')
    ->chunkById(1000, function ($users) {
        User::whereIn('id', $users->pluck('id'))
            ->update(['status' => 'active']);
    });
```

---

## MySQL Cursor

**MySQL NÃO tem server-side cursor em query de cliente!**

**Laravel `cursor()` no MySQL funciona diferente:**

```php
// MySQL: não é cursor de verdade, é unbuffered query
foreach (User::cursor() as $user) {
    // Ainda faz streaming, mas não é cursor de verdade
}
```

**Stored Procedure com cursor (MySQL):**

```sql
DELIMITER //

CREATE PROCEDURE process_users()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE user_id INT;
    DECLARE user_email VARCHAR(255);

    DECLARE cur CURSOR FOR SELECT id, email FROM users;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO user_id, user_email;
        IF done THEN
            LEAVE read_loop;
        END IF;

        -- Processa...

    END LOOP;

    CLOSE cur;
END //

DELIMITER ;

-- Chamada
CALL process_users();
```

**Problema:** complicado, quase ninguém usa.

---

## Boas práticas

```
✓ Cursor para tabelas ENORMES (millions+ rows)
✓ Laravel cursor() para Eloquent
✓ Lazy Collections para encadear operações
✓ CSV export via cursor (streaming)
✓ Chunk para UPDATE/DELETE (não cursor!)
✓ Evite cursor se dá para fazer bulk
✓ PostgreSQL: server-side cursor para bilhões de linhas
✓ MySQL: cursor só em stored procedures
✓ Monitore cursors abertos (pg_cursors)
```

---

## Quando NÃO usar cursor

**1. Dá para fazer bulk:**

```php
// ❌ Lento: cursor
foreach (User::cursor() as $user) {
    $user->update(['updated_at' => now()]);
}

// ✅ Rápido: bulk update
User::query()->update(['updated_at' => now()]);
```

**2. Seleção pequena:**

```php
// ❌ Overkill: cursor para 100 linhas
foreach (User::limit(100)->cursor() as $user) {
    //...
}

// ✅ Só get()
foreach (User::limit(100)->get() as $user) {
    //...
}
```

**3. Dá para paginar:**

```php
// Na paginação de API não é cursor, é paginate()
User::paginate(50);
```

---

## Monitoramento

**PostgreSQL: cursors abertos**

```sql
SELECT * FROM pg_cursors;

-- name           | statement                    | is_holdable | is_binary
-- my_cursor      | SELECT * FROM users          | f           | f
```

**Laravel: uso de memória**

```php
class ProcessHugeTable extends Command
{
    public function handle()
    {
        $this->info('Memória: ' . memory_get_usage() / 1024 / 1024 . ' MB');

        foreach (User::cursor() as $user) {
            $this->process($user);
        }

        $this->info('Memória: ' . memory_get_usage() / 1024 / 1024 . ' MB');
        // Tem que ficar mais ou menos igual
    }
}
```

---

## Exercícios práticos

### Exercício 1: CSV Export com Cursor

**Enunciado:** Exporte milhões de registros para CSV sem OutOfMemory.

<details>
<summary>Solução</summary>

```php
// app/Jobs/ExportUsersToCSV.php
namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportUsersToCSV implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora

    public function __construct(
        private array $filters = []
    ) {}

    public function handle()
    {
        $filename = 'exports/users_' . now()->format('Y-m-d_His') . '.csv';
        $tempPath = storage_path("app/{$filename}");

        // Abre o arquivo para escrita
        $handle = fopen($tempPath, 'w');

        // Cabeçalho
        fputcsv($handle, [
            'ID',
            'Nome',
            'Email',
            'Criado em',
            'Último login',
            'Qtd. pedidos',
            'Total gasto',
        ]);

        $exported = 0;
        $startTime = microtime(true);
        $memoryStart = memory_get_usage();

        // Cursor para streaming
        $query = User::query();

        // Aplica os filtros
        if (!empty($this->filters['created_from'])) {
            $query->where('created_at', '>=', $this->filters['created_from']);
        }

        if (!empty($this->filters['has_orders'])) {
            $query->whereHas('orders');
        }

        // Cursor pelas linhas
        foreach ($query->cursor() as $user) {
            fputcsv($handle, [
                $user->id,
                $user->name,
                $user->email,
                $user->created_at->format('Y-m-d H:i:s'),
                $user->last_login?->format('Y-m-d H:i:s'),
                $user->orders_count ?? 0,
                $user->total_spent ?? 0,
            ]);

            $exported++;

            // Log de progresso
            if ($exported % 10000 === 0) {
                $memoryUsed = round((memory_get_usage() - $memoryStart) / 1024 / 1024, 2);
                $elapsed = round(microtime(true) - $startTime, 2);
                $rate = round($exported / $elapsed);

                logger()->info("Progresso do export: {$exported} usuários ({$rate}/s, {$memoryUsed}MB)");
            }
        }

        fclose($handle);

        // Move para o cloud storage
        Storage::disk('s3')->put(
            $filename,
            file_get_contents($tempPath)
        );

        unlink($tempPath);

        $duration = round(microtime(true) - $startTime, 2);
        $memoryPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);

        logger()->info("Export concluído: {$exported} usuários em {$duration}s (pico de memória: {$memoryPeak}MB)");

        // Notifica o usuário
        // event(new ExportCompleted($filename));
    }
}

// app/Http/Controllers/ExportController.php
class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        $validated = $request->validate([
            'created_from' => 'sometimes|date',
            'has_orders' => 'sometimes|boolean',
        ]);

        // Dispara o job
        ExportUsersToCSV::dispatch($validated);

        return response()->json([
            'message' => 'Export iniciado. Você será notificado quando estiver pronto.',
        ]);
    }
}
```

</details>

### Exercício 2: Processamento em batch com Cursor

**Enunciado:** Processe milhões de registros com lógica complexa em cada um.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/ProcessInactiveUsers.php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessInactiveUsers extends Command
{
    protected $signature = 'users:process-inactive
                            {--dry-run : Roda sem fazer alterações}
                            {--limit= : Limita quantos usuários processar}';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('Processando usuários inativos...');

        if ($dryRun) {
            $this->warn('MODO DRY RUN — nenhuma alteração será feita');
        }

        $processed = 0;
        $deactivated = 0;
        $notified = 0;
        $deleted = 0;

        $progressBar = $this->output->createProgressBar();

        // Query de usuários inativos
        $query = User::where('last_login', '<', now()->subMonths(6))
            ->where('is_active', true);

        if ($limit) {
            $query->limit($limit);
        }

        // Cursor para processar
        foreach ($query->cursor() as $user) {
            $processed++;
            $progressBar->advance();

            // Lógica complexa para cada usuário
            $inactiveDays = now()->diffInDays($user->last_login);

            try {
                if ($inactiveDays > 365) {
                    // Mais de um ano — apaga
                    if (!$dryRun) {
                        $this->deleteUser($user);
                    }
                    $deleted++;
                    $this->line("\n[DELETE] Usuário {$user->id} — inativo há {$inactiveDays} dias");

                } elseif ($inactiveDays > 180) {
                    // Mais de 6 meses — desativa
                    if (!$dryRun) {
                        $user->update(['is_active' => false]);
                    }
                    $deactivated++;
                    $this->line("\n[DEACTIVATE] Usuário {$user->id} — inativo há {$inactiveDays} dias");

                } else {
                    // Envia lembrete
                    if (!$dryRun) {
                        $this->sendReactivationEmail($user);
                    }
                    $notified++;
                    $this->line("\n[NOTIFY] Usuário {$user->id} — inativo há {$inactiveDays} dias");
                }

            } catch (\Exception $e) {
                $this->error("\nErro ao processar o usuário {$user->id}: " . $e->getMessage());
            }

            // Checagem de memória
            if ($processed % 1000 === 0) {
                $memoryMB = round(memory_get_usage() / 1024 / 1024, 2);
                $this->comment("\nUso de memória: {$memoryMB}MB");

                // Força o garbage collection
                gc_collect_cycles();
            }
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info("Processamento concluído!");
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Total processado', number_format($processed)],
                ['Notificados', number_format($notified)],
                ['Desativados', number_format($deactivated)],
                ['Apagados', number_format($deleted)],
            ]
        );

        if ($dryRun) {
            $this->warn('Isso foi um DRY RUN — nenhuma alteração foi feita');
        }
    }

    private function deleteUser(User $user)
    {
        DB::transaction(function () use ($user) {
            // Apaga os dados relacionados
            $user->orders()->delete();
            $user->preferences()->delete();
            $user->sessions()->delete();

            // Apaga o usuário
            $user->delete();
        });
    }

    private function sendReactivationEmail(User $user)
    {
        // Envia o email
        // Mail::to($user)->send(new ReactivationReminder($user));
    }
}
```

</details>

### Exercício 3: Comparação Chunk vs Cursor

**Enunciado:** Crie um comando para comparar a performance de chunk e cursor.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/BenchmarkCursorVsChunk.php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class BenchmarkCursorVsChunk extends Command
{
    protected $signature = 'benchmark:cursor-vs-chunk {count=10000}';
    protected $description = 'Compara a performance de cursor vs chunk';

    public function handle()
    {
        $count = (int) $this->argument('count');

        $this->info("Benchmark com {$count} registros...");
        $this->newLine();

        // Benchmark 1: tudo de uma vez (RUIM)
        $this->info('1. Carregando todos de uma vez...');
        $result1 = $this->benchmarkAll($count);

        // Benchmark 2: Chunk
        $this->info('2. Usando chunk...');
        $result2 = $this->benchmarkChunk($count);

        // Benchmark 3: Cursor
        $this->info('3. Usando cursor...');
        $result3 = $this->benchmarkCursor($count);

        // Resultados
        $this->newLine();
        $this->table(
            ['Método', 'Tempo (s)', 'Pico de memória (MB)', 'Média de memória (MB)'],
            [
                [
                    'all()',
                    number_format($result1['time'], 3),
                    number_format($result1['peak_memory'], 2),
                    number_format($result1['avg_memory'], 2),
                ],
                [
                    'chunk(1000)',
                    number_format($result2['time'], 3),
                    number_format($result2['peak_memory'], 2),
                    number_format($result2['avg_memory'], 2),
                ],
                [
                    'cursor()',
                    number_format($result3['time'], 3),
                    number_format($result3['peak_memory'], 2),
                    number_format($result3['avg_memory'], 2),
                ],
            ]
        );

        // Vencedor
        $fastest = collect([$result1, $result2, $result3])->sortBy('time')->first();
        $this->info("Mais rápido: {$fastest['method']}");

        $leastMemory = collect([$result1, $result2, $result3])->sortBy('peak_memory')->first();
        $this->info("Menos memória: {$leastMemory['method']}");
    }

    private function benchmarkAll($count): array
    {
        $memoryStart = memory_get_usage();
        $timeStart = microtime(true);

        $users = User::limit($count)->get();

        foreach ($users as $user) {
            // Simula o processamento
            $this->processUser($user);
        }

        return [
            'method' => 'all()',
            'time' => microtime(true) - $timeStart,
            'peak_memory' => (memory_get_peak_usage() - $memoryStart) / 1024 / 1024,
            'avg_memory' => (memory_get_usage() - $memoryStart) / 1024 / 1024,
        ];
    }

    private function benchmarkChunk($count): array
    {
        $memoryStart = memory_get_usage();
        $timeStart = microtime(true);

        User::limit($count)->chunk(1000, function ($users) {
            foreach ($users as $user) {
                $this->processUser($user);
            }
        });

        return [
            'method' => 'chunk(1000)',
            'time' => microtime(true) - $timeStart,
            'peak_memory' => (memory_get_peak_usage() - $memoryStart) / 1024 / 1024,
            'avg_memory' => (memory_get_usage() - $memoryStart) / 1024 / 1024,
        ];
    }

    private function benchmarkCursor($count): array
    {
        $memoryStart = memory_get_usage();
        $timeStart = microtime(true);

        foreach (User::limit($count)->cursor() as $user) {
            $this->processUser($user);
        }

        return [
            'method' => 'cursor()',
            'time' => microtime(true) - $timeStart,
            'peak_memory' => (memory_get_peak_usage() - $memoryStart) / 1024 / 1024,
            'avg_memory' => (memory_get_usage() - $memoryStart) / 1024 / 1024,
        ];
    }

    private function processUser($user)
    {
        // Simula o processamento
        $data = [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ];

        // Algum cálculo
        $hash = md5(json_encode($data));
    }
}

// Uso:
// php artisan benchmark:cursor-vs-chunk 10000
// php artisan benchmark:cursor-vs-chunk 100000
```

</details>

---

## Na entrevista

> "Cursor itera linha a linha no resultado do SQL. Vantagem: não carrega tudo na memória. Laravel cursor() usa server-side cursor no PostgreSQL; no MySQL é unbuffered query. Casos de uso: exportar volume grande para CSV, processar millions+ rows. Chunk vs Cursor: chunk pagina (dá para UPDATE), cursor faz streaming (read-only, ORDER BY estável). Lazy Collections = cursor com operações funcionais. PostgreSQL WITH HOLD deixa o cursor aberto fora da transação. Boas práticas: cursor em tabela enorme, bulk no lugar de cursor para UPDATE, monitorar cursors abertos. Não use em seleção pequena nem quando dá para fazer bulk."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
