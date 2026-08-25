# 9.2 Sharding

> **TL;DR:** Sharding divide os dados em bancos independentes para escalar escrita. Range-based por intervalo de ID, Hash-based de forma uniforme. Problemas: cross-shard queries, unique constraints, resharding. Laravel: multiple connections, ShardManager. Use quando passa de 1TB e tem write bottleneck.

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de sharding](#tipos-de-sharding)
  - [Range-based](#1-range-based-por-intervalo)
  - [Hash-based](#2-hash-based-por-hash)
  - [Geographic](#3-geographic-geográfico)
  - [Directory-based](#4-directory-based-diretório)
- [Implementação no Laravel](#implementação-no-laravel)
- [Problemas do sharding](#problemas-do-sharding)
  - [Cross-shard queries](#1-cross-shard-queries)
  - [Unique constraints](#2-unique-constraints)
  - [Resharding](#3-resharding-adicionar-shards)
- [Sharding vs Replicação](#sharding-vs-replicação)
- [Vitess](#vitess-mysql-sharding-solution)
- [Quando usar sharding](#quando-usar-sharding)
- [Exemplo prático](#exemplo-prático)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**Sharding:**
Particionamento horizontal: você quebra os dados em vários bancos independentes (shards). Cada shard guarda uma fatia.

**Para quê:**
- Escalar escrita (write scaling)
- Sair do teto de um banco só
- Distribuição geográfica
- Isolamento de dados

```
Antes do sharding:
Single DB (10TB, 10M users)

Depois do sharding:
Shard 1: users 1-2.5M    (2.5TB)
Shard 2: users 2.5M-5M   (2.5TB)
Shard 3: users 5M-7.5M   (2.5TB)
Shard 4: users 7.5M-10M  (2.5TB)
```

---

## Tipos de sharding

### 1. Range-based (por intervalo)

**Princípio:**

```
user_id 1-1000      → Shard 1
user_id 1001-2000   → Shard 2
user_id 2001-3000   → Shard 3
```

**Prós:**
- ✅ Fácil de entender
- ✅ Range queries funcionam (WHERE id BETWEEN 100 AND 200)
- ✅ Fácil adicionar um shard novo

**Contras:**
- ❌ Distribuição desigual (hotspots)
- ❌ Dados antigos concentram num shard só

**Exemplo:**

```php
function getShardByUserId(int $userId): string
{
    if ($userId <= 1000) return 'shard1';
    if ($userId <= 2000) return 'shard2';
    if ($userId <= 3000) return 'shard3';
    return 'shard4';
}

$shard = getShardByUserId($userId);
DB::connection($shard)->table('users')->find($userId);
```

---

### 2. Hash-based (por hash)

**Princípio:**

```
user_id 123  → hash(123) % 4 = 3 → Shard 3
user_id 456  → hash(456) % 4 = 0 → Shard 0
user_id 789  → hash(789) % 4 = 1 → Shard 1
```

**Prós:**
- ✅ Distribuição uniforme
- ✅ Sem hotspots

**Contras:**
- ❌ Não dá para fazer range queries
- ❌ Difícil adicionar um shard novo (rehashing)

**Exemplo:**

```php
function getShardByHash(int $userId): string
{
    $shardIndex = $userId % 4;  // 4 shards
    return "shard$shardIndex";
}

$shard = getShardByHash($userId);
DB::connection($shard)->table('users')->find($userId);
```

---

### 3. Geographic (geográfico)

**Princípio:**

```
users nos EUA     → Shard US
users na Europa   → Shard EU
users na Ásia     → Shard ASIA
```

**Prós:**
- ✅ Latência baixa para o usuário
- ✅ Compliance (GDPR — dados na UE)

**Contras:**
- ❌ Distribuição desigual
- ❌ Cross-region queries ficam complexas

**Exemplo:**

```php
function getShardByCountry(string $country): string
{
    return match($country) {
        'US', 'CA', 'MX' => 'shard_americas',
        'GB', 'DE', 'FR' => 'shard_europe',
        'CN', 'JP', 'IN' => 'shard_asia',
        default => 'shard_default'
    };
}
```

---

### 4. Directory-based (diretório)

**Princípio:**

Tabela de mapping à parte:

```sql
CREATE TABLE shard_directory (
    user_id INT PRIMARY KEY,
    shard_id VARCHAR(50)
);

-- user_id 123 → shard2
-- user_id 456 → shard1
```

**Prós:**
- ✅ Distribuição flexível
- ✅ Fácil mover o user entre shards

**Contras:**
- ❌ Lookup a mais
- ❌ Single point of failure (o directory)

---

## Implementação no Laravel

**config/database.php:**

```php
'connections' => [
    'shard_0' => [
        'driver' => 'mysql',
        'host' => '192.168.1.10',
        'database' => 'myapp_shard_0',
        // ...
    ],
    'shard_1' => [
        'driver' => 'mysql',
        'host' => '192.168.1.11',
        'database' => 'myapp_shard_1',
        // ...
    ],
    'shard_2' => [
        'driver' => 'mysql',
        'host' => '192.168.1.12',
        'database' => 'myapp_shard_2',
        // ...
    ],
    'shard_3' => [
        'driver' => 'mysql',
        'host' => '192.168.1.13',
        'database' => 'myapp_shard_3',
        // ...
    ],
],
```

**ShardManager:**

```php
class ShardManager
{
    private const SHARD_COUNT = 4;

    public static function getShardConnection(int $userId): string
    {
        $shardId = $userId % self::SHARD_COUNT;
        return "shard_$shardId";
    }

    public static function getAllShards(): array
    {
        return ['shard_0', 'shard_1', 'shard_2', 'shard_3'];
    }
}
```

**Repository com sharding:**

```php
class UserRepository
{
    public function find(int $userId): ?User
    {
        $shard = ShardManager::getShardConnection($userId);

        return DB::connection($shard)
            ->table('users')
            ->where('id', $userId)
            ->first();
    }

    public function create(array $data): User
    {
        $userId = $this->generateUserId();
        $shard = ShardManager::getShardConnection($userId);

        DB::connection($shard)
            ->table('users')
            ->insert([...$data, 'id' => $userId]);

        return $this->find($userId);
    }

    public function all(): Collection
    {
        // ❌ Problema: precisa consultar TODOS os shards
        $results = [];

        foreach (ShardManager::getAllShards() as $shard) {
            $users = DB::connection($shard)
                ->table('users')
                ->get();

            $results = array_merge($results, $users->toArray());
        }

        return collect($results);
    }
}
```

---

## Problemas do sharding

### 1. Cross-shard queries

**Problema:**

```sql
-- Não dá para fazer JOIN entre shards
SELECT users.name, orders.total
FROM users
JOIN orders ON users.id = orders.user_id
WHERE orders.status = 'pending';
```

**Solução 1: Duplicar os dados**

```php
// Em cada shard, guardar os dados necessários
// a tabela orders tem user_name (desnormalização)
```

**Solução 2: Application-level JOIN**

```php
// 1. Buscar orders de todos os shards
$orders = [];
foreach (ShardManager::getAllShards() as $shard) {
    $shardOrders = DB::connection($shard)
        ->table('orders')
        ->where('status', 'pending')
        ->get();

    $orders = array_merge($orders, $shardOrders->toArray());
}

// 2. Buscar users
$userIds = array_unique(array_column($orders, 'user_id'));
$users = [];
foreach ($userIds as $userId) {
    $shard = ShardManager::getShardConnection($userId);
    $user = DB::connection($shard)->table('users')->find($userId);
    $users[$userId] = $user;
}

// 3. Juntar no app
foreach ($orders as &$order) {
    $order->user = $users[$order->user_id];
}
```

---

### 2. Unique constraints

**Problema:**

```sql
-- email precisa ser único globalmente
-- Mas cada shard é um banco separado
```

**Solução 1: Global lookup table**

```sql
-- Banco separado para valores únicos
CREATE TABLE global_emails (
    email VARCHAR(255) PRIMARY KEY,
    user_id INT,
    shard_id VARCHAR(50)
);
```

**Solução 2: Distributed ID generation**

```php
// Snowflake ID: timestamp + shard_id + sequence
// Garante unicidade sem coordenação
function generateSnowflakeId(int $shardId): int
{
    $timestamp = (int)(microtime(true) * 1000);
    $sequence = $this->getSequence();

    return ($timestamp << 22) | ($shardId << 12) | $sequence;
}
```

---

### 3. Resharding (adicionar shards)

**Problema:**

```
Eram 4 shards → Precisa de 8 shards
user_id % 4 → user_id % 8
Os dados precisam ser redistribuídos!
```

**Solução: Consistent Hashing**

```php
class ConsistentHashing
{
    private array $ring = [];

    public function addNode(string $node): void
    {
        // Adicionar o node em várias posições (virtual nodes)
        for ($i = 0; $i < 100; $i++) {
            $hash = crc32("$node:$i");
            $this->ring[$hash] = $node;
        }
        ksort($this->ring);
    }

    public function getNode(int $userId): string
    {
        $hash = crc32((string)$userId);

        foreach ($this->ring as $ringHash => $node) {
            if ($hash <= $ringHash) {
                return $node;
            }
        }

        return reset($this->ring);  // First node
    }
}

// Ao adicionar um shard novo, só ~1/N dos dados se move
```

---

## Sharding vs Replicação

**Replicação:**
- Cópia dos dados em cada servidor
- Escala de leitura
- Master escreve, Slaves leem

**Sharding:**
- Dados diferentes em cada servidor
- Escala de escrita
- Cada shard é independente

**Combinando (recomendado):**

```
Shard 1 (Master) → Shard 1 (Slave)
Shard 2 (Master) → Shard 2 (Slave)
Shard 3 (Master) → Shard 3 (Slave)
```

---

## Vitess (MySQL sharding solution)

**O que é:**
Sistema open-source de sharding MySQL (usado no YouTube, Slack).

**O que faz:**
- Sharding automático
- Resharding sem downtime
- Connection pooling
- Query routing

**Arquitetura:**

```
Application
    ↓
VTGate (query router)
    ↓
VTTablet → MySQL Shard 1
VTTablet → MySQL Shard 2
VTTablet → MySQL Shard 3
```

---

## Quando usar sharding

**Use quando:**

```
✓ > 1TB de dados
✓ > 100M de registros
✓ Write bottleneck (replicação não resolve)
✓ Distribuição geográfica
✓ Regulatory compliance (dados na região)
```

**NÃO use quando:**

```
❌ < 100GB de dados (otimização prematura)
❌ Muitas cross-shard queries
❌ Sem expertise (a complexidade sobe 10x)
❌ Dá para escalar verticalmente
```

**Alternativas ao sharding:**

```
1. Escala vertical (mais RAM/CPU)
2. Particionamento (partition tables)
3. Arquivar dados antigos
4. Desnormalização
5. NoSQL (MongoDB, Cassandra - built-in sharding)
```

---

## Exemplo prático

**Instagram sharding:**

```
Sharding por user_id:
- 4000+ shards PostgreSQL
- ~1000 users por shard
- Photos ficam no mesmo shard do user

Lookup:
user_id → shard_id (consistent hashing)

Consequências:
✓ Todas as photos de um user no mesmo shard (JOIN local)
✓ Gerar o feed fica pesado (precisa consultar N shards para N followings)
✓ Celebrity problem (o shard da Beyoncé fica sobrecarregado)
```

---

## Exercícios práticos

### Exercício 1: Implementar o ShardManager

**Enunciado:** Crie um ShardManager com hash-based sharding para usuários.

<details>
<summary>Solução</summary>

```php
// app/Services/ShardManager.php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class ShardManager
{
    private const SHARD_COUNT = 4;
    private array $shards = ['shard_0', 'shard_1', 'shard_2', 'shard_3'];

    public function getShardConnection(int $userId): string
    {
        $shardId = $userId % self::SHARD_COUNT;
        return "shard_{$shardId}";
    }

    public function getAllShards(): array
    {
        return $this->shards;
    }

    public function query(int $userId, callable $callback)
    {
        $connection = $this->getShardConnection($userId);
        return $callback(DB::connection($connection));
    }

    public function queryAllShards(callable $callback): array
    {
        $results = [];

        foreach ($this->shards as $shard) {
            $shardResults = $callback(DB::connection($shard));
            $results = array_merge($results, $shardResults);
        }

        return $results;
    }
}

// app/Repositories/UserRepository.php
namespace App\Repositories;

use App\Services\ShardManager;

class UserRepository
{
    public function __construct(
        private ShardManager $shardManager
    ) {}

    public function find(int $userId): ?array
    {
        return $this->shardManager->query($userId, function ($db) use ($userId) {
            return $db->table('users')->where('id', $userId)->first();
        });
    }

    public function create(array $data): array
    {
        $userId = $this->generateUserId();
        $data['id'] = $userId;

        $this->shardManager->query($userId, function ($db) use ($data) {
            $db->table('users')->insert($data);
        });

        return $this->find($userId);
    }

    public function findByEmail(string $email): ?array
    {
        // Problema: precisa buscar em todos os shards
        $results = $this->shardManager->queryAllShards(function ($db) use ($email) {
            return $db->table('users')
                ->where('email', $email)
                ->get()
                ->toArray();
        });

        return $results[0] ?? null;
    }

    private function generateUserId(): int
    {
        // Snowflake-like ID generation
        return (int)(microtime(true) * 10000);
    }
}

// config/database.php
'connections' => [
    'shard_0' => [
        'driver' => 'mysql',
        'host' => env('DB_SHARD_0_HOST', '127.0.0.1'),
        'database' => env('DB_SHARD_0_DATABASE', 'app_shard_0'),
        // ...
    ],
    'shard_1' => [
        'driver' => 'mysql',
        'host' => env('DB_SHARD_1_HOST', '127.0.0.1'),
        'database' => env('DB_SHARD_1_DATABASE', 'app_shard_1'),
        // ...
    ],
    // shard_2, shard_3...
],
```

</details>

### Exercício 2: Resolver Unique Constraints

**Enunciado:** Implemente unicidade global de email com sharding de usuários.

<details>
<summary>Solução</summary>

```php
// Migration da global lookup table
Schema::create('global_emails', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->unsignedBigInteger('user_id');
    $table->string('shard_id');
    $table->timestamps();
});

// app/Services/EmailRegistry.php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class EmailRegistry
{
    public function register(string $email, int $userId, string $shardId): void
    {
        try {
            DB::table('global_emails')->insert([
                'email' => $email,
                'user_id' => $userId,
                'shard_id' => $shardId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') { // Duplicate entry
                throw new Exception("Email {$email} já está registrado");
            }
            throw $e;
        }
    }

    public function lookup(string $email): ?array
    {
        return DB::table('global_emails')
            ->where('email', $email)
            ->first();
    }

    public function delete(string $email): void
    {
        DB::table('global_emails')->where('email', $email)->delete();
    }
}

// UserRepository atualizado
class UserRepository
{
    public function __construct(
        private ShardManager $shardManager,
        private EmailRegistry $emailRegistry
    ) {}

    public function create(array $data): array
    {
        $email = $data['email'];

        // Checar unicidade
        if ($this->emailRegistry->lookup($email)) {
            throw new Exception("Email {$email} já existe");
        }

        $userId = $this->generateUserId();
        $shardId = $this->shardManager->getShardConnection($userId);

        DB::transaction(function () use ($email, $userId, $shardId, $data) {
            // 1. Registrar o email
            $this->emailRegistry->register($email, $userId, $shardId);

            // 2. Criar o usuário no shard
            $this->shardManager->query($userId, function ($db) use ($data, $userId) {
                $db->table('users')->insert([...$data, 'id' => $userId]);
            });
        });

        return $this->find($userId);
    }

    public function findByEmail(string $email): ?array
    {
        // Lookup rápido pelo registry
        $lookup = $this->emailRegistry->lookup($email);

        if (!$lookup) {
            return null;
        }

        return $this->find($lookup->user_id);
    }
}
```

</details>

### Exercício 3: Consistent Hashing para Resharding

**Enunciado:** Implemente consistent hashing para minimizar a movimentação de dados ao adicionar shards.

<details>
<summary>Solução</summary>

```php
// app/Services/ConsistentHashing.php
namespace App\Services;

class ConsistentHashing
{
    private array $ring = [];
    private const VIRTUAL_NODES = 150;

    public function __construct(array $nodes = [])
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }
    }

    public function addNode(string $node): void
    {
        // Adicionamos virtual nodes para distribuição uniforme
        for ($i = 0; $i < self::VIRTUAL_NODES; $i++) {
            $hash = crc32("{$node}:{$i}");
            $this->ring[$hash] = $node;
        }

        ksort($this->ring);
    }

    public function removeNode(string $node): void
    {
        for ($i = 0; $i < self::VIRTUAL_NODES; $i++) {
            $hash = crc32("{$node}:{$i}");
            unset($this->ring[$hash]);
        }
    }

    public function getNode(int $key): string
    {
        if (empty($this->ring)) {
            throw new \Exception('Nenhum node disponível');
        }

        $hash = crc32((string)$key);

        // Achar o primeiro node >= hash
        foreach ($this->ring as $ringHash => $node) {
            if ($hash <= $ringHash) {
                return $node;
            }
        }

        // Se não achou, devolve o primeiro node (wrap around)
        return reset($this->ring);
    }

    public function getNodes(): array
    {
        return array_unique(array_values($this->ring));
    }
}

// app/Services/ConsistentShardManager.php
namespace App\Services;

class ConsistentShardManager
{
    private ConsistentHashing $hashing;

    public function __construct()
    {
        $this->hashing = new ConsistentHashing([
            'shard_0',
            'shard_1',
            'shard_2',
            'shard_3',
        ]);
    }

    public function getShardConnection(int $userId): string
    {
        return $this->hashing->getNode($userId);
    }

    public function addShard(string $shardId): void
    {
        $this->hashing->addNode($shardId);

        // Depois de adicionar o shard, precisa migrar ~1/N dos dados
        $this->migrateData($shardId);
    }

    private function migrateData(string $newShardId): void
    {
        // Exemplo: checar cada usuário e mover se precisar
        // Em produção, fazer via background job

        foreach ($this->hashing->getNodes() as $oldShard) {
            if ($oldShard === $newShardId) {
                continue;
            }

            $users = DB::connection($oldShard)
                ->table('users')
                ->select(['id', 'email', 'name'])
                ->get();

            foreach ($users as $user) {
                $correctShard = $this->getShardConnection($user->id);

                if ($correctShard !== $oldShard) {
                    // Mover para o shard certo
                    DB::connection($correctShard)
                        ->table('users')
                        ->insert((array)$user);

                    DB::connection($oldShard)
                        ->table('users')
                        ->where('id', $user->id)
                        ->delete();
                }
            }
        }
    }
}
```

</details>

---

## Na entrevista

> "Sharding divide os dados em bancos independentes para escalar escrita. Range-based: por intervalo de ID, simples mas tem hotspots. Hash-based: distribuição uniforme, mas sem range queries. Geographic: por região, para latência. Problemas: cross-shard queries (JOIN no app), unique constraints (global lookup table), resharding (consistent hashing). No Laravel: multiple connections e um ShardManager para o routing. Combina com replicação. Vitess para sharding de MySQL. Uso quando passa de 1TB e tem write bottleneck. Alternativas: escala vertical, particionamento, NoSQL."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
