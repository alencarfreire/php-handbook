# 9.3 Trabalhando com Big Data

> **TL;DR**
> Big Data no banco — tabelas com millions/billions de rows. Problemas: queries lentas, migrations longas, lock contentions. Soluções: Partitioning (divide por tempo/região), Sharding (particionamento horizontal), Read Replicas (analytics na replica), Archiving (dados velhos no S3), Bulk INSERT (batch no lugar de insert um a um), Cursor (streaming sem estourar memória). Otimização: covering indexes, materialized views, index em JSONB.

## Conteúdo

- [O que é](#o-que-é)
- [Problemas com tabelas grandes](#problemas-com-tabelas-grandes)
  - [1. Slow SELECT](#1-slow-select)
  - [2. Slow INSERT/UPDATE](#2-slow-insertupdate)
  - [3. Migrations longas](#3-migrations-longas)
  - [4. Archiving de dados antigos](#4-archiving-de-dados-antigos)
  - [5. Read Replicas para analytics](#5-read-replicas-para-analytics)
  - [6. Cursor para batch processing](#6-cursor-para-batch-processing)
- [Otimização de queries em tabelas grandes](#otimização-de-queries-em-tabelas-grandes)
- [Monitoramento de tabelas grandes](#monitoramento-de-tabelas-grandes)
- [Boas práticas](#boas-práticas)
- [Ferramentas](#ferramentas)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Big Data no contexto de banco:**
Tabelas com millions/billions de rows. Sem abordagem específica, você não consegue trabalhar direito.

**Problemas:**
- Queries lentas
- Migrations longas
- Falta de memória
- Backup/restore lento
- Lock contentions

**Soluções:**
- Partitioning
- Sharding
- Read replicas
- Archiving
- Batch processing

---

## Problemas com tabelas grandes

### 1. Slow SELECT

**Problema:**

```sql
-- 100 million rows
SELECT * FROM logs WHERE user_id = 123;
-- Lento! (mesmo com índice)
```

**Soluções:**

**A. Partitioning**

```sql
-- Partition por mês
CREATE TABLE logs_2024_01 PARTITION OF logs
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Query só na partição certa
SELECT * FROM logs
WHERE created_at >= '2024-01-15'
  AND user_id = 123;
-- Scan só em logs_2024_01
```

**B. Sharding**

```php
// Dividir users por ID em bancos diferentes
function getUserShard($userId)
{
    return 'shard_' . ($userId % 4);  // 4 shards
}

$shardName = getUserShard(123);
$user = DB::connection($shardName)->table('users')->find(123);
```

---

### 2. Slow INSERT/UPDATE

**Problema:**

```php
// Bulk insert de 1 milhão de rows (lento)
foreach ($records as $record) {
    DB::table('logs')->insert($record);  // 1 milhão de queries!
}
```

**Soluções:**

**A. Bulk INSERT**

```php
// Batch insert (100x mais rápido)
$chunks = array_chunk($records, 1000);

foreach ($chunks as $chunk) {
    DB::table('logs')->insert($chunk);
}
```

**B. COPY (PostgreSQL)**

```php
// Mais rápido: PostgreSQL COPY
$file = '/tmp/logs.csv';

// Export para CSV
$fp = fopen($file, 'w');
foreach ($records as $record) {
    fputcsv($fp, $record);
}
fclose($fp);

// COPY do CSV (super rápido!)
DB::statement("
    COPY logs (user_id, action, created_at)
    FROM '{$file}'
    CSV
");
```

**C. LOAD DATA INFILE (MySQL)**

```php
// Equivalente no MySQL
DB::statement("
    LOAD DATA LOCAL INFILE '{$file}'
    INTO TABLE logs
    FIELDS TERMINATED BY ','
    LINES TERMINATED BY '\\n'
    (user_id, action, created_at)
");
```

---

### 3. Migrations longas

**Problema:**

```php
// Adicionar coluna em tabela grande (horas!)
Schema::table('logs', function (Blueprint $table) {
    $table->string('ip_address')->nullable();
});
// Locks table!
```

**Soluções:**

**A. Online Schema Change (PostgreSQL)**

```php
// PostgreSQL: adicionar coluna sem lock
DB::statement("
    ALTER TABLE logs
    ADD COLUMN ip_address VARCHAR(255) DEFAULT NULL
");
// Rápido (só muda metadata)
```

**B. pt-online-schema-change (MySQL)**

```bash
# Percona Toolkit
pt-online-schema-change \
  --alter "ADD COLUMN ip_address VARCHAR(255)" \
  D=mydb,t=logs \
  --execute
# Cria tabela nova, copia os dados, faz swap
```

**C. Batch UPDATE**

```php
// Em vez de UPDATE em todas as rows de uma vez
$lastId = 0;
$batchSize = 10000;

while (true) {
    $updated = DB::table('logs')
        ->where('id', '>', $lastId)
        ->whereNull('ip_address')
        ->limit($batchSize)
        ->update(['ip_address' => DB::raw('...')]);

    if ($updated === 0) {
        break;
    }

    $lastId = DB::table('logs')
        ->where('id', '>', $lastId)
        ->min('id');

    sleep(1);  // Pausa para não sobrecarregar o banco
}
```

---

### 4. Archiving de dados antigos

**Problema:**

```sql
-- Logs de 5 anos (bilhões de rows)
-- Só precisa dos últimos 3 meses
```

**Soluções:**

**A. Partitioning + DROP**

```php
// Dropar partição antiga (instantâneo!)
DB::statement("DROP TABLE logs_2023_01");
// Rápido (sem queries DELETE)
```

**B. Archive to cold storage**

```php
class ArchiveOldLogs extends Command
{
    public function handle()
    {
        $cutoffDate = now()->subMonths(3);

        // Export para S3
        $logs = DB::table('logs')
            ->where('created_at', '<', $cutoffDate)
            ->cursor();

        $file = storage_path('logs_archive_' . now()->format('Y-m-d') . '.csv');
        $fp = fopen($file, 'w');

        foreach ($logs as $log) {
            fputcsv($fp, (array) $log);
        }

        fclose($fp);

        // Upload to S3
        Storage::disk('s3')->put(
            'archives/' . basename($file),
            file_get_contents($file)
        );

        // Apagar dados antigos
        DB::table('logs')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        unlink($file);
    }
}

// Scheduler: archive uma vez por mês
$schedule->command('logs:archive')->monthly();
```

---

### 5. Read Replicas para analytics

**Problema:**

```sql
-- Queries pesadas de analytics travam o production
SELECT
    DATE_TRUNC('day', created_at) as date,
    COUNT(*) as count,
    AVG(response_time) as avg_response
FROM logs
WHERE created_at >= NOW() - INTERVAL '1 year'
GROUP BY DATE_TRUNC('day', created_at);
-- Query lenta trava as outras
```

**Solução: Read Replica**

```php
// config/database.php
'connections' => [
    'mysql' => [
        'write' => [
            'host' => 'master.db.example.com',
        ],
        'read' => [
            ['host' => 'replica1.db.example.com'],
            ['host' => 'replica2.db.example.com'],
        ],
    ],
],

// Analytics na replica
DB::connection('mysql')
    ->table('logs')
    ->where('created_at', '>=', now()->subYear())
    ->groupBy(DB::raw('DATE_TRUNC("day", created_at)'))
    ->get();
// Não sobrecarrega o master
```

---

### 6. Cursor para batch processing

**Problema:**

```php
// Carregar 100 milhões de rows na memória (crash!)
$logs = Log::all();
```

**Solução: Cursor**

```php
// Streaming (não carrega tudo na memória)
foreach (Log::cursor() as $log) {
    $this->process($log);
}

// Ou chunk
Log::chunk(10000, function ($logs) {
    foreach ($logs as $log) {
        $this->process($log);
    }
});
```

---

## Otimização de queries em tabelas grandes

### 1. Covering Index

```sql
-- Query
SELECT id, user_id, created_at
FROM logs
WHERE user_id = 123
ORDER BY created_at DESC
LIMIT 100;

-- Covering index (tem TODAS as colunas da query)
CREATE INDEX idx_logs_user_created_cover
ON logs (user_id, created_at DESC)
INCLUDE (id);

-- Agora o banco não lê a tabela (index-only scan)
```

---

### 2. Index em JSONB

```sql
-- Lento: scan na tabela inteira
SELECT * FROM products
WHERE attributes->>'brand' = 'Dell';

-- Rápido: GIN index
CREATE INDEX idx_products_attributes ON products USING gin (attributes);
```

---

### 3. Partial Index

```sql
-- Index só nas rows que importam
CREATE INDEX idx_logs_pending
ON logs (user_id)
WHERE status = 'pending';

-- Query usa o partial index
SELECT * FROM logs
WHERE user_id = 123 AND status = 'pending';
```

---

### 4. Materialized Views

```sql
-- Pre-compute expensive aggregations
CREATE MATERIALIZED VIEW daily_stats AS
SELECT
    DATE_TRUNC('day', created_at) as date,
    COUNT(*) as count,
    AVG(response_time) as avg_response
FROM logs
GROUP BY DATE_TRUNC('day', created_at);

-- Refresh periodically
REFRESH MATERIALIZED VIEW daily_stats;

-- Query rápida
SELECT * FROM daily_stats WHERE date >= NOW() - INTERVAL '30 days';
```

---

## Monitoramento de tabelas grandes

**PostgreSQL:**

```sql
-- Tamanho das tabelas
SELECT
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Slow queries
SELECT
    query,
    calls,
    total_time,
    mean_time
FROM pg_stat_statements
ORDER BY mean_time DESC
LIMIT 10;
```

**MySQL:**

```sql
-- Tamanho das tabelas
SELECT
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.TABLES
WHERE table_schema = 'mydb'
ORDER BY (data_length + index_length) DESC;
```

---

## Boas práticas

```
✓ Partitioning para dados temporais (logs, events)
✓ Sharding para horizontal scaling
✓ Read replicas para analytics
✓ Archive de dados antigos (S3, cold storage)
✓ Bulk INSERT no lugar de insert um a um
✓ Cursor para batch processing
✓ Covering indexes nas queries frequentes
✓ Materialized views para aggregations
✓ Monitorar tamanho das tabelas e slow queries
✓ Vacuum/Analyze de forma regular (PostgreSQL)
✓ Optimize Table de forma regular (MySQL)
```

---

## Ferramentas

**PostgreSQL:**
- `pg_partman` — particionamento automático
- `pgbadger` — análise de logs
- `pg_repack` — rebuild de tabelas sem downtime

**MySQL:**
- `pt-online-schema-change` — migrations sem downtime
- `pt-archiver` — arquivamento de dados
- `mysqldumper` — dump rápido de bancos grandes

---

## Exercícios práticos

### Exercício 1: Otimizar bulk insert

**Enunciado:** Você precisa importar 1 milhão de logs de um CSV. O jeito ingênuo é lento demais. Otimize.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: uma row por vez (horas!)
class ImportLogsCommand extends Command
{
    public function handle()
    {
        $file = fopen(storage_path('logs.csv'), 'r');

        while (($row = fgetcsv($file)) !== false) {
            Log::create([
                'user_id' => $row[0],
                'action' => $row[1],
                'created_at' => $row[2],
            ]);
        }

        fclose($file);
    }
}

// ✅ BOM: batch insert (minutos)
class ImportLogsCommand extends Command
{
    public function handle()
    {
        $file = fopen(storage_path('logs.csv'), 'r');
        $batch = [];
        $batchSize = 1000;

        while (($row = fgetcsv($file)) !== false) {
            $batch[] = [
                'user_id' => $row[0],
                'action' => $row[1],
                'created_at' => $row[2],
            ];

            // Inserir o lote
            if (count($batch) >= $batchSize) {
                DB::table('logs')->insert($batch);
                $batch = [];
            }
        }

        // Resto
        if (!empty($batch)) {
            DB::table('logs')->insert($batch);
        }

        fclose($file);
    }
}

// ⚡ SUPER RÁPIDO: PostgreSQL COPY (segundos!)
class ImportLogsCommand extends Command
{
    public function handle()
    {
        $file = storage_path('logs.csv');

        DB::statement("
            COPY logs (user_id, action, created_at)
            FROM '{$file}'
            CSV HEADER
        ");

        $this->info('Importado com sucesso!');
    }
}

// MySQL LOAD DATA INFILE
class ImportLogsCommand extends Command
{
    public function handle()
    {
        $file = storage_path('logs.csv');

        DB::statement("
            LOAD DATA LOCAL INFILE '{$file}'
            INTO TABLE logs
            FIELDS TERMINATED BY ','
            LINES TERMINATED BY '\\n'
            IGNORE 1 LINES
            (user_id, action, created_at)
        ");

        $this->info('Importado com sucesso!');
    }
}
```

**Performance:**
- Single INSERT: ~3 horas para 1M de registros
- Batch INSERT (1000): ~5 minutos para 1M de registros
- COPY/LOAD DATA: ~30 segundos para 1M de registros
</details>

---

### Exercício 2: Implementar archiving de dados antigos

**Enunciado:** A tabela `activity_logs` tem 500 milhões de registros em 5 anos. O app só precisa dos últimos 3 meses. Implemente o arquivamento.

<details>
<summary>Solução</summary>

```php
// Command para arquivar
class ArchiveOldLogsCommand extends Command
{
    protected $signature = 'logs:archive {--dry-run}';

    public function handle()
    {
        $cutoffDate = now()->subMonths(3);

        $this->info("Arquivando logs anteriores a {$cutoffDate}...");

        // Contar registros
        $count = DB::table('activity_logs')
            ->where('created_at', '<', $cutoffDate)
            ->count();

        $this->info("Encontrados {$count} registros para arquivar");

        if ($this->option('dry-run')) {
            return;
        }

        // Export para CSV
        $filename = 'logs_archive_' . now()->format('Y-m-d') . '.csv';
        $filepath = storage_path('archives/' . $filename);

        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $file = fopen($filepath, 'w');

        // Header
        fputcsv($file, ['id', 'user_id', 'action', 'ip_address', 'created_at']);

        // Streaming export (não carrega tudo na memória)
        DB::table('activity_logs')
            ->where('created_at', '<', $cutoffDate)
            ->orderBy('id')
            ->chunk(10000, function ($logs) use ($file) {
                foreach ($logs as $log) {
                    fputcsv($file, (array) $log);
                }
            });

        fclose($file);

        $this->info("Exportado para {$filepath}");

        // Compress
        $compressed = $filepath . '.gz';
        $this->info('Compactando...');

        exec("gzip {$filepath}");

        // Upload to S3
        $this->info('Enviando para o S3...');

        Storage::disk('s3')->put(
            'archives/' . basename($compressed),
            file_get_contents($compressed)
        );

        // Apagar do banco
        $this->info('Apagando registros antigos...');

        $deleted = 0;
        while (true) {
            $batch = DB::table('activity_logs')
                ->where('created_at', '<', $cutoffDate)
                ->limit(10000)
                ->delete();

            $deleted += $batch;
            $this->info("Apagados {$deleted} / {$count}");

            if ($batch === 0) {
                break;
            }

            sleep(1); // Pausa para não sobrecarregar o banco
        }

        // Apagar arquivo local
        unlink($compressed);

        $this->info('Arquivamento concluído!');
    }
}

// Scheduler: arquivar uma vez por mês
protected function schedule(Schedule $schedule)
{
    $schedule->command('logs:archive')
        ->monthlyOn(1, '02:00');
}

// Command para restaurar do arquivo
class RestoreLogsCommand extends Command
{
    protected $signature = 'logs:restore {file}';

    public function handle()
    {
        $file = $this->argument('file');

        // Download from S3
        $this->info('Baixando do S3...');
        $content = Storage::disk('s3')->get($file);

        $localFile = storage_path('temp/' . basename($file));
        file_put_contents($localFile, $content);

        // Decompress
        exec("gunzip {$localFile}");
        $csvFile = str_replace('.gz', '', $localFile);

        // Import
        $this->info('Importando...');

        DB::statement("
            COPY activity_logs (id, user_id, action, ip_address, created_at)
            FROM '{$csvFile}'
            CSV HEADER
        ");

        unlink($csvFile);

        $this->info('Restore concluído!');
    }
}
```

**Vantagens:**
- O banco fica pequeno e rápido
- Arquivos no S3 (storage barato)
- Dá para restaurar se precisar
- Batch delete (não trava o banco)
</details>

---

### Exercício 3: Otimizar query em tabela grande

**Enunciado:** Query lenta numa tabela com 100 milhões de registros:

```sql
SELECT * FROM orders
WHERE status = 'pending'
  AND created_at > NOW() - INTERVAL '7 days'
ORDER BY total DESC
LIMIT 100;
```

EXPLAIN mostra Seq Scan. Otimize.

<details>
<summary>Solução</summary>

```php
// Análise do problema
// EXPLAIN ANALYZE mostra:
// Seq Scan on orders (cost=0..1000000 rows=5000000)
//   Filter: (status = 'pending' AND created_at > ...)

// Solução 1: Composite Index
Schema::table('orders', function (Blueprint $table) {
    // Covering index (tem todas as colunas da query)
    $table->index(['status', 'created_at', 'total', 'id'], 'idx_orders_pending_recent');
});

// Solução 2: Partial Index (PostgreSQL)
DB::statement("
    CREATE INDEX idx_orders_pending ON orders (created_at, total)
    WHERE status = 'pending'
");
// Menor, mais rápido

// Solução 3: Materialized View para query frequente
DB::statement("
    CREATE MATERIALIZED VIEW pending_orders_recent AS
    SELECT *
    FROM orders
    WHERE status = 'pending'
      AND created_at > NOW() - INTERVAL '7 days'
");

// Refresh periódico
DB::statement("REFRESH MATERIALIZED VIEW pending_orders_recent");

// Query na materialized view (instantâneo!)
$orders = DB::table('pending_orders_recent')
    ->orderBy('total', 'desc')
    ->limit(100)
    ->get();

// Solução 4: Partitioning por created_at
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id');
    $table->string('status');
    $table->decimal('total', 10, 2);
    $table->timestamp('created_at');
    $table->timestamp('updated_at');
});

// PostgreSQL Partitioning
DB::statement("
    CREATE TABLE orders_2024_01 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01')
");
// A query escaneia só as partições certas

// Eloquent query builder (usa os índices)
class Order extends Model
{
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>', now()->subDays($days));
    }

    public function scopeHighestValue($query)
    {
        return $query->orderBy('total', 'desc');
    }
}

// Uso
$orders = Order::pending()
    ->recent(7)
    ->highestValue()
    ->limit(100)
    ->get();

// EXPLAIN depois da otimização:
// Index Scan using idx_orders_pending on orders (cost=0..500 rows=100)
//   Index Cond: (status = 'pending' AND created_at > ...)
//   Order By: total DESC
// 1000x mais rápido!
```

**Resultado:**
- ANTES: Seq Scan em 100M de rows, ~30 segundos
- DEPOIS: Index Scan em ~1000 rows, ~10ms
- Covering index evita ler a tabela
- Partial index economiza espaço
</details>

---

## Na entrevista

> "Big Data no banco é millions/billions de rows. Problemas: queries lentas, migrations longas, falta de memória. Soluções: Partitioning (divide por tempo), Sharding (particionamento horizontal), Read Replicas (analytics na replica), Archiving (dados velhos no S3), Bulk INSERT (batch no lugar de insert um a um), Cursor (streaming sem estourar memória). Otimização: covering indexes, partial indexes, materialized views, index em JSONB. Migrations: online schema change, batch UPDATE. Monitoring: tamanho das tabelas, slow queries. Tools: pg_partman, pt-online-schema-change, pt-archiver."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
