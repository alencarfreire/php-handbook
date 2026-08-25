# 9.8 Partitioning

> **TL;DR:** Partitioning divide uma tabela grande em pedaços menores para acelerar as queries. Range (por data), List (por categoria), Hash (distribuição uniforme). Partition pruning escaneia só as partições certas. PostgreSQL: partition key na PK, índices automaticamente nas partições. Laravel: automatize criar/dropar pelo scheduler.

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de Partitioning](#tipos-de-partitioning)
  - [Range Partitioning](#1-range-partitioning-por-intervalo)
  - [List Partitioning](#2-list-partitioning-por-lista)
  - [Hash Partitioning](#3-hash-partitioning-por-hash)
- [Implementação no Laravel](#implementação-no-laravel)
- [Criação automática de partições](#criação-automática-de-partições)
- [Remoção de partições antigas](#remoção-de-partições-antigas)
- [Exemplo prático: logs](#exemplo-prático-logs)
- [Partition pruning](#partition-pruning)
- [Índices nas partições](#índices-nas-partições)
- [Sub-partitioning](#sub-partitioning)
- [Boas práticas](#boas-práticas)
- [Partitioning no MySQL](#partitioning-no-mysql)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Partitioning:**
Dividir uma tabela grande em pedaços físicos menores (partitions), que logicamente continuam sendo uma tabela só.

**Para quê:**
- Acelerar queries (query só nas partições certas)
- Arquivar fica simples (DROP das partições velhas)
- Maintenance mais fácil (VACUUM, REINDEX mais rápidos)
- Operações em paralelo

**Trade-off:**
- ✅ Performance em tabela grande (milhões+ de rows)
- ❌ Setup mais complexo
- ❌ Em tabela pequena, quase nunca vale a pena

---

## Tipos de Partitioning

### 1. Range Partitioning (por intervalo)

**Caso de uso:** dados temporais (logs, pedidos por data)

```sql
-- Parent table
CREATE TABLE orders (
    id BIGSERIAL,
    user_id BIGINT,
    total DECIMAL(10, 2),
    created_at TIMESTAMP NOT NULL,
    PRIMARY KEY (id, created_at)  -- partition key precisa estar na PK
) PARTITION BY RANGE (created_at);

-- Child partitions
CREATE TABLE orders_2024_01 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE TABLE orders_2024_02 PARTITION OF orders
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');

CREATE TABLE orders_2024_03 PARTITION OF orders
    FOR VALUES FROM ('2024-03-01') TO ('2024-04-01');
```

**As queries caem na partição certa sozinhas:**

```sql
-- PostgreSQL escolhe a partição certa sozinho
SELECT * FROM orders
WHERE created_at >= '2024-02-15' AND created_at < '2024-02-20';
-- Escaneia só orders_2024_02 (não todas as partições!)
```

---

### 2. List Partitioning (por lista)

**Caso de uso:** categorias, regiões, status

```sql
CREATE TABLE products (
    id BIGSERIAL,
    name VARCHAR(255),
    category VARCHAR(50) NOT NULL,
    price DECIMAL(10, 2),
    PRIMARY KEY (id, category)
) PARTITION BY LIST (category);

-- Partições por categoria
CREATE TABLE products_electronics PARTITION OF products
    FOR VALUES IN ('electronics', 'computers', 'phones');

CREATE TABLE products_clothing PARTITION OF products
    FOR VALUES IN ('clothing', 'shoes', 'accessories');

CREATE TABLE products_food PARTITION OF products
    FOR VALUES IN ('food', 'beverages');
```

---

### 3. Hash Partitioning (por hash)

**Caso de uso:** distribuição uniforme dos dados

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    name VARCHAR(255)
) PARTITION BY HASH (id);

-- 4 partições (distribuição uniforme)
CREATE TABLE users_p0 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 0);

CREATE TABLE users_p1 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 1);

CREATE TABLE users_p2 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 2);

CREATE TABLE users_p3 PARTITION OF users
    FOR VALUES WITH (MODULUS 4, REMAINDER 3);
```

---

## Implementação no Laravel

**Migration de Range Partitioning:**

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('total', 10, 2);
    $table->timestamp('created_at');

    // Partition key precisa estar na primary key
    $table->primary(['id', 'created_at']);
});

// Criar partições com raw SQL
DB::statement("
    ALTER TABLE orders
    PARTITION BY RANGE (created_at)
");

// Partição de janeiro de 2024
DB::statement("
    CREATE TABLE orders_2024_01 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01')
");

// Índices são criados automaticamente em cada partição
DB::statement("CREATE INDEX ON orders_2024_01(user_id)");
```

---

## Criação automática de partições

**Command para criar partições futuras:**

```php
class CreateMonthlyPartitions extends Command
{
    protected $signature = 'partitions:create-monthly {table} {--months=3}';

    public function handle()
    {
        $table = $this->argument('table');
        $months = $this->option('months');

        $start = now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $date = $start->copy()->addMonths($i);
            $nextDate = $date->copy()->addMonth();

            $partitionName = "{$table}_{$date->format('Y_m')}";

            // Checar se já existe
            $exists = DB::selectOne("
                SELECT 1 FROM pg_tables
                WHERE tablename = ?
            ", [$partitionName]);

            if ($exists) {
                $this->info("Partição {$partitionName} já existe");
                continue;
            }

            // Criar a partição
            DB::statement("
                CREATE TABLE {$partitionName} PARTITION OF {$table}
                FOR VALUES FROM ('{$date->format('Y-m-d')}')
                             TO ('{$nextDate->format('Y-m-d')}')
            ");

            // Índices
            DB::statement("CREATE INDEX ON {$partitionName}(user_id)");

            $this->info("✓ Partição {$partitionName} criada");
        }
    }
}

// Scheduler: criar partições 3 meses à frente
$schedule->command('partitions:create-monthly orders --months=3')->monthly();
```

---

## Remoção de partições antigas

**Arquivar dados antigos:**

```php
class DropOldPartitions extends Command
{
    protected $signature = 'partitions:drop-old {table} {--months-ago=12}';

    public function handle()
    {
        $table = $this->argument('table');
        $monthsAgo = $this->option('months-ago');

        $cutoffDate = now()->subMonths($monthsAgo)->startOfMonth();

        // Achar todas as partições mais antigas que o cutoff
        $partitions = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE tablename LIKE '{$table}_%'
              AND tablename < '{$table}_{$cutoffDate->format('Y_m')}'
        ");

        foreach ($partitions as $partition) {
            $name = $partition->tablename;

            if ($this->confirm("Dropar a partição {$name}?")) {
                // Opcional: exportar para o arquivo
                $this->exportToArchive($name);

                // Remover a partição
                DB::statement("DROP TABLE {$name}");

                $this->info("✓ Partição {$name} removida");
            }
        }
    }

    private function exportToArchive(string $partition)
    {
        // Export para S3, filesystem, etc.
        $data = DB::table($partition)->get();
        Storage::disk('archive')->put(
            "{$partition}.json",
            json_encode($data)
        );
    }
}

// Scheduler: dropar partições com mais de 12 meses
$schedule->command('partitions:drop-old orders --months-ago=12')->monthly();
```

---

## Exemplo prático: logs

**Cenário:** milhões de logs por dia, guardar 3 meses.

```php
// Migration
Schema::create('logs', function (Blueprint $table) {
    $table->id();
    $table->string('level');
    $table->text('message');
    $table->text('context')->nullable();
    $table->timestamp('created_at');

    $table->primary(['id', 'created_at']);
});

DB::statement("ALTER TABLE logs PARTITION BY RANGE (created_at)");

// Criar partições 3 meses à frente
for ($i = 0; $i < 3; $i++) {
    $date = now()->addMonths($i)->startOfMonth();
    $nextDate = $date->copy()->addMonth();

    DB::statement("
        CREATE TABLE logs_{$date->format('Y_m')} PARTITION OF logs
        FOR VALUES FROM ('{$date->format('Y-m-d')}')
                     TO ('{$nextDate->format('Y-m-d')}')
    ");
}

// Scheduler
$schedule->command('partitions:create-monthly logs --months=3')->monthly();
$schedule->command('partitions:drop-old logs --months-ago=3')->daily();
```

**Uso:**

```php
// Laravel trabalha com partições sem você perceber
Log::info('Usuário fez login', ['user_id' => 123]);

// Query só na partição certa
DB::table('logs')
    ->where('created_at', '>=', now()->subDays(7))
    ->where('level', 'error')
    ->get();
// Scan só das partições dos últimos 7 dias
```

---

## Partition pruning

**EXPLAIN mostra quais partições são escaneadas:**

```sql
EXPLAIN SELECT * FROM orders
WHERE created_at >= '2024-02-01' AND created_at < '2024-03-01';

-- Seq Scan on orders_2024_02
-- (só 1 partição!)
```

**Sem partition key no WHERE — escaneia TODAS as partições:**

```sql
EXPLAIN SELECT * FROM orders WHERE user_id = 123;

-- Append
--   -> Seq Scan on orders_2024_01
--   -> Seq Scan on orders_2024_02
--   -> Seq Scan on orders_2024_03
-- (todas as partições!)
```

**Solução: coloque a partition key no WHERE**

```sql
SELECT * FROM orders
WHERE user_id = 123
  AND created_at >= '2024-02-01'  -- partition key!
  AND created_at < '2024-03-01';
```

---

## Índices nas partições

**Índice no parent → cai automático em todas as partições:**

```sql
-- Criar índice na parent table
CREATE INDEX idx_orders_user ON orders(user_id);

-- Índices criados automaticamente:
-- idx_orders_user_2024_01 em orders_2024_01
-- idx_orders_user_2024_02 em orders_2024_02
-- ...
```

**Laravel:**

```php
Schema::table('orders', function (Blueprint $table) {
    $table->index('user_id');
    // Automaticamente em todas as partições
});
```

---

## Sub-partitioning

**Partition por data, sub-partition por região:**

```sql
CREATE TABLE orders (
    id BIGSERIAL,
    user_id BIGINT,
    region VARCHAR(50) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    PRIMARY KEY (id, created_at, region)
) PARTITION BY RANGE (created_at);

-- Partição do mês
CREATE TABLE orders_2024_01 PARTITION OF orders
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01')
    PARTITION BY LIST (region);

-- Subpartições por região
CREATE TABLE orders_2024_01_eu PARTITION OF orders_2024_01
    FOR VALUES IN ('EU', 'UK');

CREATE TABLE orders_2024_01_us PARTITION OF orders_2024_01
    FOR VALUES IN ('US', 'CA');
```

---

## Boas práticas

```
✓ Partitioning para tabelas grandes (milhões+ de rows)
✓ Range partitioning para dados temporais (logs, pedidos)
✓ List partitioning para categorias (region, status)
✓ Hash partitioning para distribuição uniforme
✓ Partition key precisa estar na primary key
✓ Sempre coloque a partition key no WHERE para pruning
✓ Automatize criar/dropar partições (scheduler)
✓ Crie índices na parent table (caem automático nas partições)
✓ EXPLAIN para checar partition pruning
✓ Não use partitioning em tabela pequena (overhead)
```

---

## Partitioning no MySQL

**MySQL tem, mas com restrições:**

```sql
-- Range partitioning (MySQL)
CREATE TABLE orders (
    id BIGINT AUTO_INCREMENT,
    created_at TIMESTAMP,
    total DECIMAL(10, 2),
    PRIMARY KEY (id, created_at)
)
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

**Limitações do MySQL:**
- Foreign keys NÃO são suportadas
- Partition key precisa estar na primary key
- Menos flexível que o PostgreSQL

---

## Exercícios práticos

### Exercício 1: Tabela de logs particionada

**Enunciado:** Criar uma tabela de logs particionada com gestão automática das partições.

<details>
<summary>Solução</summary>

```php
// database/migrations/xxxx_create_logs_partitioned.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Criamos a parent table
        DB::statement("
            CREATE TABLE logs (
                id BIGSERIAL,
                level VARCHAR(20),
                message TEXT,
                context JSONB,
                created_at TIMESTAMP NOT NULL,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        ");

        // Índices no parent (caem automático em todas as partições)
        DB::statement("CREATE INDEX ON logs (level, created_at)");
        DB::statement("CREATE INDEX ON logs USING gin (context)");

        // Criamos partições 3 meses à frente
        $this->createPartitions(3);
    }

    private function createPartitions(int $months)
    {
        for ($i = 0; $i < $months; $i++) {
            $date = now()->addMonths($i)->startOfMonth();
            $nextDate = $date->copy()->addMonth();

            $partitionName = "logs_" . $date->format('Y_m');

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partitionName}
                PARTITION OF logs
                FOR VALUES FROM ('{$date->format('Y-m-d')}')
                             TO ('{$nextDate->format('Y-m-d')}')
            ");
        }
    }

    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS logs CASCADE");
    }
};

// app/Console/Commands/ManageLogPartitions.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageLogPartitions extends Command
{
    protected $signature = 'logs:manage-partitions
                            {--create-future=3 : Cria partições N meses à frente}
                            {--drop-old=3 : Dropa partições com mais de N meses}';

    public function handle()
    {
        $this->createFuturePartitions();
        $this->dropOldPartitions();
    }

    private function createFuturePartitions()
    {
        $months = $this->option('create-future');
        $this->info("Criando partições para os próximos {$months} meses...");

        for ($i = 0; $i < $months; $i++) {
            $date = now()->addMonths($i)->startOfMonth();
            $nextDate = $date->copy()->addMonth();

            $partitionName = "logs_" . $date->format('Y_m');

            // Checamos se já existe
            $exists = DB::selectOne("
                SELECT 1 FROM pg_tables
                WHERE tablename = ?
            ", [$partitionName]);

            if ($exists) {
                $this->comment("Partição {$partitionName} já existe");
                continue;
            }

            DB::statement("
                CREATE TABLE {$partitionName}
                PARTITION OF logs
                FOR VALUES FROM ('{$date->format('Y-m-d')}')
                             TO ('{$nextDate->format('Y-m-d')}')
            ");

            $this->info("✓ Partição {$partitionName} criada");
        }
    }

    private function dropOldPartitions()
    {
        $months = $this->option('drop-old');
        $cutoffDate = now()->subMonths($months)->startOfMonth();

        $this->info("Dropando partições anteriores a {$cutoffDate->format('Y-m')}...");

        // Achar partições antigas
        $partitions = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE tablename LIKE 'logs_%'
              AND tablename < ?
        ", ["logs_" . $cutoffDate->format('Y_m')]);

        foreach ($partitions as $partition) {
            $name = $partition->tablename;

            if (!$this->confirm("Dropar a partição {$name}?", true)) {
                continue;
            }

            // Opcional: exportar para o arquivo
            $this->exportToArchive($name);

            // Dropamos a partição
            DB::statement("DROP TABLE IF EXISTS {$name}");

            $this->info("✓ Partição {$name} removida");
        }
    }

    private function exportToArchive(string $partition)
    {
        // Export para S3, filesystem, etc.
        $this->comment("Exportando {$partition} para o arquivo...");

        $data = DB::table($partition)->get();

        Storage::disk('archive')->put(
            "{$partition}.json",
            json_encode($data)
        );
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Gerenciar partições todo mês
    $schedule->command('logs:manage-partitions --create-future=3 --drop-old=3')
        ->monthly();
}

// Uso (transparente para o app)
DB::table('logs')->insert([
    'level' => 'error',
    'message' => 'Algo deu errado',
    'context' => json_encode(['user_id' => 123]),
    'created_at' => now(),
]);

// A query usa partition pruning sozinha
$recentErrors = DB::table('logs')
    ->where('level', 'error')
    ->where('created_at', '>=', now()->subDays(7))
    ->get();
// Escaneia só as partições dos últimos 7 dias!
```

</details>

### Exercício 2: Partitioning de pedidos por data

**Enunciado:** Particionar a tabela de pedidos por data para arquivar pedidos antigos rápido.

<details>
<summary>Solução</summary>

```php
// Migration
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Criamos a tabela particionada
        DB::statement("
            CREATE TABLE orders (
                id BIGSERIAL,
                user_id BIGINT NOT NULL,
                total DECIMAL(10, 2),
                status VARCHAR(20),
                created_at TIMESTAMP NOT NULL,
                updated_at TIMESTAMP,
                PRIMARY KEY (id, created_at)
            ) PARTITION BY RANGE (created_at)
        ");

        // Índices
        DB::statement("CREATE INDEX ON orders (user_id, created_at)");
        DB::statement("CREATE INDEX ON orders (status, created_at)");

        // Criar partições dos últimos 12 meses
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i)->startOfMonth();
            $nextDate = $date->copy()->addMonth();

            $partitionName = "orders_" . $date->format('Y_m');

            DB::statement("
                CREATE TABLE {$partitionName}
                PARTITION OF orders
                FOR VALUES FROM ('{$date->format('Y-m-d')}')
                             TO ('{$nextDate->format('Y-m-d')}')
            ");
        }

        // Partição para pedidos futuros
        DB::statement("
            CREATE TABLE orders_future
            PARTITION OF orders
            FOR VALUES FROM ('{$this->getNextMonthStart()}')
                         TO (MAXVALUE)
        ");
    }

    private function getNextMonthStart()
    {
        return now()->addMonth()->startOfMonth()->format('Y-m-d');
    }

    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS orders CASCADE");
    }
};

// app/Models/Order.php (funciona transparente com partições)
class Order extends Model
{
    // Model padrão, partitioning transparente
}

// Queries usam partition pruning
Order::where('created_at', '>=', now()->subDays(30))->get();
// Escaneia só a partição do mês atual!

// app/Console/Commands/ArchiveOldOrders.php
class ArchiveOldOrders extends Command
{
    protected $signature = 'orders:archive {--months-ago=12}';

    public function handle()
    {
        $monthsAgo = $this->option('months-ago');
        $cutoffDate = now()->subMonths($monthsAgo)->startOfMonth();

        $this->info("Arquivando pedidos anteriores a {$cutoffDate->format('Y-m')}...");

        // Achar partições antigas
        $partitions = DB::select("
            SELECT tablename
            FROM pg_tables
            WHERE tablename LIKE 'orders_%'
              AND tablename != 'orders_future'
              AND tablename < ?
            ORDER BY tablename
        ", ["orders_" . $cutoffDate->format('Y_m')]);

        foreach ($partitions as $partition) {
            $name = $partition->tablename;

            $this->info("Processando a partição {$name}...");

            // 1. Export para o storage de arquivo
            $this->exportPartition($name);

            // 2. Dropar a partição
            if ($this->confirm("Dropar a partição {$name}?")) {
                DB::statement("DROP TABLE {$name}");
                $this->info("✓ {$name} dropada");
            }
        }
    }

    private function exportPartition(string $partition)
    {
        // Export em pedaços (chunk)
        $exported = 0;

        DB::table($partition)
            ->orderBy('id')
            ->chunk(1000, function ($orders) use ($partition, &$exported) {
                // Salvar no S3/file storage
                Storage::disk('archive')->append(
                    "{$partition}.jsonl",
                    $orders->map(fn($o) => json_encode($o))->implode("\n")
                );

                $exported += $orders->count();
            });

        $this->info("  {$exported} pedidos exportados");
    }
}
```

</details>

### Exercício 3: Checar partition pruning

**Enunciado:** Criar um command para analisar partition pruning nas queries.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/AnalyzePartitionPruning.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzePartitionPruning extends Command
{
    protected $signature = 'partition:analyze {query}';
    protected $description = 'Analisa partition pruning de uma query';

    public function handle()
    {
        $query = $this->argument('query');

        $this->info("Analisando a query:");
        $this->line($query);
        $this->newLine();

        // EXPLAIN query
        $plan = DB::select("EXPLAIN {$query}");

        $this->info("Plano de execução:");

        $partitionsScanned = [];
        $pruningDetected = false;

        foreach ($plan as $row) {
            $line = $row->{'QUERY PLAN'};
            $this->line($line);

            // Detectar scans de partição
            if (preg_match('/Seq Scan on (\w+)/', $line, $matches)) {
                $partitionsScanned[] = $matches[1];
            }

            // Detectar pruning
            if (str_contains($line, 'Partitions')) {
                $pruningDetected = true;
            }
        }

        $this->newLine();

        if ($pruningDetected) {
            $this->info("✓ Partition pruning ATIVO");
            $this->info("Partições escaneadas: " . count($partitionsScanned));

            $this->table(['Partição'], array_map(fn($p) => [$p], $partitionsScanned));
        } else {
            $this->warn("⚠ Partition pruning NÃO detectado");
            $this->warn("Essa query pode escanear TODAS as partições!");
            $this->comment("Dica: coloque a partition key no WHERE");
        }
    }
}

// Exemplos de uso:
// php artisan partition:analyze "SELECT * FROM orders WHERE created_at >= '2024-01-01'"
// php artisan partition:analyze "SELECT * FROM logs WHERE level = 'error' AND created_at > NOW() - INTERVAL '7 days'"

// app/Console/Commands/ShowPartitionStats.php
class ShowPartitionStats extends Command
{
    protected $signature = 'partition:stats {table}';

    public function handle()
    {
        $table = $this->argument('table');

        // Pegar info das partições
        $partitions = DB::select("
            SELECT
                c.relname as partition_name,
                pg_size_pretty(pg_total_relation_size(c.oid)) as size,
                n_tup_ins as inserts,
                n_tup_upd as updates,
                n_tup_del as deletes,
                n_live_tup as live_rows,
                n_dead_tup as dead_rows
            FROM pg_class c
            JOIN pg_stat_user_tables s ON c.oid = s.relid
            WHERE c.relname LIKE ?
            ORDER BY c.relname
        ", ["{$table}_%"]);

        $this->table(
            ['Partição', 'Tamanho', 'Inserts', 'Updates', 'Deletes', 'Linhas vivas', 'Linhas mortas'],
            array_map(fn($p) => [
                $p->partition_name,
                $p->size,
                number_format($p->inserts),
                number_format($p->updates),
                number_format($p->deletes),
                number_format($p->live_rows),
                number_format($p->dead_rows),
            ], $partitions)
        );

        // Total
        $totalSize = DB::selectOne("
            SELECT pg_size_pretty(pg_total_relation_size(?::regclass)) as size
        ", [$table])->size;

        $this->newLine();
        $this->info("Tamanho total da tabela: {$totalSize}");
    }
}
```

</details>

---

## Na entrevista

> "Partitioning é dividir uma tabela grande em pedaços físicos menores. Tipos: Range (por data), List (por categoria), Hash (distribuição uniforme). Vantagem: a query só bate nas partições certas (partition pruning), arquivar é DROP TABLE, maintenance fica rápido. PostgreSQL tem partitioning declarativo; partition key precisa estar na PK. Índice no parent cai automático nas partições. Laravel: cria com DB::statement, automatiza no scheduler (criar as futuras, dropar as velhas). Best practice: milhões+ de rows, sempre partition key no WHERE, EXPLAIN pra checar o pruning. MySQL também tem, mas sem foreign keys."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
