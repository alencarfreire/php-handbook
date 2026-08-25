# 9.1 Replicação de banco

> **TL;DR:** Replicação copia os dados do Master para os Slaves. Escala leitura e aguenta o master cair. Master-Slave: grava no master, lê nos slaves. Assíncrona é rápida, mas tem replication lag. Laravel: read/write hosts, sticky sessions. Failover com Patroni/MHA.

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de replicação](#tipos-de-replicação)
  - [Master-Slave (Single Leader)](#1-master-slave-single-leader)
  - [Master-Master (Multi-Leader)](#2-master-master-multi-leader)
  - [Multi-Master com partitioning](#3-multi-master-com-partitioning)
- [Síncrona vs Assíncrona](#síncrona-vs-assíncrona)
- [Configuração no Laravel](#configuração-no-laravel)
- [Replicação no MySQL](#replicação-no-mysql)
- [Replicação no PostgreSQL](#replicação-no-postgresql)
- [Replication Lag](#replication-lag)
- [Monitoramento da replicação](#monitoramento-da-replicação)
- [Failover](#failover-promover-a-réplica)
- [Dicas práticas](#dicas-práticas)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Replicação:**
Copia os dados de um banco (master) para um ou mais bancos (réplicas). Serve para escalar leitura e não cair se o master cair.

**Para quê:**
- Escalar leitura (lê nas réplicas)
- Tolerância a falha (se o master cair)
- Distribuição geográfica
- Backup sem carga no master

---

## Tipos de replicação

### 1. Master-Slave (Single Leader)

**Esquema:**

```
Master (Write)
  ↓ replicate
Slave 1 (Read)
Slave 2 (Read)
Slave 3 (Read)
```

**Como funciona:**

```
1. Client grava no Master
2. Master escreve no binlog
3. Slaves leem o binlog e aplicam as mudanças
4. Clients leem nos Slaves
```

**Vantagens:**
- ✅ Setup simples
- ✅ Escala leitura
- ✅ Dá para fazer backup no Slave

**Desvantagens:**
- ❌ Single point of failure (master)
- ❌ Replication lag (atraso)
- ❌ Toda escrita só no master

---

### 2. Master-Master (Multi-Leader)

**Esquema:**

```
Master 1 (Read/Write) ←→ Master 2 (Read/Write)
```

**Vantagens:**
- ✅ Sem single point of failure
- ✅ Grava em qualquer master
- ✅ Distribuição geográfica

**Desvantagens:**
- ❌ Conflito se os dois gravarem ao mesmo tempo
- ❌ Setup difícil
- ❌ Resolver conflito é difícil

**Conflitos:**

```sql
-- Ao mesmo tempo, em masters diferentes:
-- Master 1:
UPDATE users SET name = 'John' WHERE id = 1;

-- Master 2:
UPDATE users SET name = 'Jane' WHERE id = 1;

-- Conflito! Quem ganha?
-- Estratégias:
-- 1. Last Write Wins (LWW) — pelo timestamp
-- 2. Version vectors
-- 3. Resolução na aplicação
```

---

### 3. Multi-Master com partitioning

**Esquema:**

```
Master 1 (users 1-1000) ←→ Master 2 (users 1001-2000)
```

Cada master cuida da sua fatia dos dados.

---

## Síncrona vs Assíncrona

### Replicação síncrona:

```
Client → Master
         ↓
      [WAIT] até o Slave confirmar
         ↓
      Response → Client
```

**Prós:**
- ✅ Dado garantido na réplica
- ✅ Sem perda de dado

**Contras:**
- ❌ Mais lento (espera a réplica)
- ❌ Réplica fora → a escrita trava

### Replicação assíncrona:

```
Client → Master → Response (na hora)
         ↓
      Slave (depois)
```

**Prós:**
- ✅ Rápido (não espera a réplica)
- ✅ Master não depende das réplicas

**Contras:**
- ❌ Replication lag
- ❌ Se o master cair, pode perder dado

---

## Configuração no Laravel

**config/database.php:**

```php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.2',  // Slave 1
            '192.168.1.3',  // Slave 2
        ],
    ],
    'write' => [
        'host' => ['192.168.1.1'],  // Master
    ],
    'sticky' => true,  // Lê do write depois de gravar
    'driver' => 'mysql',
    'database' => 'myapp',
    'username' => 'root',
    'password' => 'secret',
],
```

**Uso:**

```php
// Roteamento automático
User::create([...]);  // → Master (write)
User::all();          // → Slave (read)

// Forçar a write connection
DB::connection('mysql')->useWriteConnection()
    ->select('SELECT * FROM users');

// Sticky session: lê no master depois de gravar
$user = User::create([...]);  // Write → Master
$user->fresh();  // Read → Master (por causa do sticky=true)
```

---

## Replicação no MySQL

**Configurar o Master:**

```ini
# /etc/mysql/my.cnf
[mysqld]
server-id = 1
log_bin = /var/log/mysql/mysql-bin.log
binlog_format = ROW  # or MIXED
```

```sql
-- Criar o usuário de replicação
CREATE USER 'repl'@'%' IDENTIFIED BY 'password';
GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
FLUSH PRIVILEGES;

-- Ver a posição do binlog
SHOW MASTER STATUS;
-- File: mysql-bin.000001, Position: 154
```

**Configurar o Slave:**

```ini
# /etc/mysql/my.cnf
[mysqld]
server-id = 2
relay_log = /var/log/mysql/relay-bin.log
read_only = 1  # Slave só leitura
```

```sql
-- Ligar no master
CHANGE MASTER TO
  MASTER_HOST='192.168.1.1',
  MASTER_USER='repl',
  MASTER_PASSWORD='password',
  MASTER_LOG_FILE='mysql-bin.000001',
  MASTER_LOG_POS=154;

-- Subir a replicação
START SLAVE;

-- Checar o status
SHOW SLAVE STATUS\G

-- Seconds_Behind_Master: 0 (sem atraso)
-- Slave_IO_Running: Yes
-- Slave_SQL_Running: Yes
```

---

## Replicação no PostgreSQL

**Streaming Replication (assíncrona):**

**Master (primary):**

```ini
# postgresql.conf
wal_level = replica
max_wal_senders = 3
```

```
# pg_hba.conf
host replication repl 192.168.1.2/32 md5
```

```sql
-- Criar o usuário de replicação
CREATE ROLE repl WITH REPLICATION LOGIN PASSWORD 'password';
```

**Slave (standby):**

```bash
# Criar o base backup
pg_basebackup -h 192.168.1.1 -D /var/lib/postgresql/data -U repl -P

# standby.signal (criar arquivo vazio)
touch /var/lib/postgresql/data/standby.signal
```

```ini
# postgresql.conf
primary_conninfo = 'host=192.168.1.1 port=5432 user=repl password=password'
```

---

## Replication Lag

**O problema:**

```php
// User cria o post
$post = Post::create(['title' => 'Olá']);  // Write → Master

// Redirect e lê na hora
return redirect("/posts/{$post->id}");

// Read → Slave (mas tem replication lag!)
$post = Post::find($id);  // null ou dado velho
```

**Solução 1: Sticky sessions**

```php
'sticky' => true,  // Lê no master depois de gravar na sessão
```

**Solução 2: Ler no master na mão**

```php
$post = Post::create([...]);

// Ler no master
$post = DB::connection('mysql')
    ->useWriteConnection()
    ->table('posts')
    ->find($post->id);
```

**Solução 3: Retry com espera**

```php
$post = Post::create([...]);

// Esperar a replicação
sleep(1);

$post = Post::find($post->id);
```

---

## Monitoramento da replicação

**MySQL:**

```sql
SHOW SLAVE STATUS\G

-- Campos que importam:
-- Seconds_Behind_Master: atraso em segundos
-- Slave_IO_Running: recebendo o binlog
-- Slave_SQL_Running: aplicando as mudanças
-- Last_Error: erros de replicação
```

**PostgreSQL:**

```sql
-- No master
SELECT * FROM pg_stat_replication;

-- pg_wal_lsn_diff mostra o atraso
SELECT pg_wal_lsn_diff(sent_lsn, write_lsn) AS lag_bytes
FROM pg_stat_replication;
```

---

## Failover (promover a réplica)

**Failover automático:**

```
Master (down!) → Slave 1 (promovido a Master)
                 Slave 2 (reconecta no Master novo)
```

**Ferramentas:**
- MySQL: MHA (Master High Availability)
- PostgreSQL: Patroni, repmgr
- ProxySQL / HAProxy para roteamento automático

**Failover manual:**

```sql
-- No Slave:
STOP SLAVE;
RESET SLAVE ALL;

-- Virar Master
SET GLOBAL read_only = 0;

-- Apontar o app para o Master novo
```

---

## Dicas práticas

**Quando usar:**

```
✓ > 70% de leitura
✓ Precisa de alta disponibilidade
✓ Distribuição geográfica
✓ Backup sem carga no master
```

**Quando NÃO usar:**

```
❌ 50/50 read/write (não ganha throughput)
❌ Banco pequeno (< 1GB)
❌ Sem expertise para operar
```

**Boas práticas:**

```
✓ Monitorar replication lag
✓ Failover automático (Patroni, MHA)
✓ Failover drills de verdade (testar a troca)
✓ Backups consistentes (pg_basebackup, mysqldump)
```

---

## Exercícios práticos

### Exercício 1: Read/Write Splitting no Laravel

**Enunciado:** Configure o Laravel para ler nas réplicas e gravar no master.

<details>
<summary>Solução</summary>

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.2',  // Slave 1
            '192.168.1.3',  // Slave 2
        ],
    ],
    'write' => [
        'host' => ['192.168.1.1'],  // Master
    ],
    'sticky' => true,  // Lê do write depois de gravar na sessão
    'driver' => 'mysql',
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
],

// Uso
class UserController extends Controller
{
    public function store(Request $request)
    {
        // Escrita → Master
        $user = User::create($request->validated());

        // Leitura → Master (por causa do sticky=true)
        return new UserResource($user->fresh());
    }

    public function index()
    {
        // Leitura → Slave (réplica aleatória)
        return UserResource::collection(User::paginate(20));
    }

    public function forceWriteConnection()
    {
        // Ler no master na mão
        $users = DB::connection('mysql')
            ->useWriteConnection()
            ->table('users')
            ->get();

        return response()->json($users);
    }
}
```

</details>

### Exercício 2: Lidar com replication lag

**Enunciado:** Leia com segurança depois de gravar, mesmo com replication lag.

<details>
<summary>Solução</summary>

```php
class PostController extends Controller
{
    public function store(Request $request)
    {
        // Cria o post (grava no master)
        $post = Post::create($request->validated());

        // Solução 1: ler no master depois de gravar
        $freshPost = DB::connection('mysql')
            ->useWriteConnection()
            ->table('posts')
            ->find($post->id);

        return response()->json($freshPost);
    }

    // Solução 2: sticky sessions
    public function storeWithSticky(Request $request)
    {
        $post = Post::create($request->validated());

        // Com sticky=true, lê no master sozinho
        return new PostResource($post);
    }

    // Solução 3: retry com espera
    public function storeWithRetry(Request $request)
    {
        $post = Post::create($request->validated());

        // Esperar a replicação
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $found = Post::find($post->id);

            if ($found) {
                return new PostResource($found);
            }

            usleep(100000); // 100ms
            $attempt++;
        }

        // Fallback: ler no master
        return new PostResource(
            Post::on('mysql')->useWriteConnection()->find($post->id)
        );
    }
}
```

</details>

### Exercício 3: Monitorar replication lag

**Enunciado:** Crie um comando Artisan que checa o atraso da replicação.

<details>
<summary>Solução</summary>

```php
// app/Console/Commands/CheckReplicationLag.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckReplicationLag extends Command
{
    protected $signature = 'db:check-replication';
    protected $description = 'Checa o replication lag do banco';

    public function handle()
    {
        $driver = config('database.default');

        if ($driver === 'mysql') {
            $this->checkMySQLReplication();
        } elseif ($driver === 'pgsql') {
            $this->checkPostgreSQLReplication();
        }
    }

    private function checkMySQLReplication()
    {
        $status = DB::select('SHOW SLAVE STATUS')[0] ?? null;

        if (!$status) {
            $this->error('Replicação não está configurada');
            return;
        }

        $lag = $status->Seconds_Behind_Master;
        $ioRunning = $status->Slave_IO_Running;
        $sqlRunning = $status->Slave_SQL_Running;

        $this->info("Status da replicação:");
        $this->line("IO Running: {$ioRunning}");
        $this->line("SQL Running: {$sqlRunning}");
        $this->line("Lag: {$lag} segundos");

        if ($lag > 60) {
            $this->warn("⚠️  Replication lag alto: {$lag} segundos");
        } elseif ($lag > 10) {
            $this->comment("⚡ Lag moderado: {$lag} segundos");
        } else {
            $this->info("✓ Replicação saudável");
        }

        if ($status->Last_Error) {
            $this->error("Erro: {$status->Last_Error}");
        }
    }

    private function checkPostgreSQLReplication()
    {
        $replicas = DB::select('SELECT * FROM pg_stat_replication');

        if (empty($replicas)) {
            $this->error('Nenhuma réplica encontrada');
            return;
        }

        $this->info("Status da replicação:");

        foreach ($replicas as $replica) {
            $lag = DB::selectOne(
                'SELECT pg_wal_lsn_diff(sent_lsn, write_lsn) as lag_bytes FROM pg_stat_replication WHERE pid = ?',
                [$replica->pid]
            )->lag_bytes;

            $lagMB = round($lag / 1024 / 1024, 2);

            $this->line("Réplica: {$replica->application_name}");
            $this->line("  State: {$replica->state}");
            $this->line("  Lag: {$lagMB} MB");

            if ($lagMB > 100) {
                $this->warn("  ⚠️  Lag alto");
            } else {
                $this->info("  ✓ Saudável");
            }
        }
    }
}

// Registrar em Kernel.php
protected function schedule(Schedule $schedule)
{
    // Checar a cada 5 minutos
    $schedule->command('db:check-replication')
        ->everyFiveMinutes()
        ->emailOutputOnFailure('admin@example.com');
}
```

</details>

---

## Na entrevista

> "Replicação copia os dados do Master para os Slaves. Escala leitura e aguenta o master cair. Master-Slave: grava no master, lê nos slaves. Assíncrona é rápida, mas tem replication lag. Síncrona é mais lenta, sem perda de dado. Laravel: read/write hosts, sticky sessions. MySQL: replicação via binlog, server-id. PostgreSQL: streaming replication, WAL. Failover: automático com Patroni/MHA, ou você promove o slave na mão. Monitoramento: Seconds_Behind_Master, pg_stat_replication."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
