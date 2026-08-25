# 12.5 Estratégias de deploy

## Resumo

> **Deployment strategies** — estratégias de deploy da app sem downtime.
>
> **Blue-Green:** dois ambientes idênticos, deploy no inativo, depois a troca. **Rolling:** troca as instâncias aos poucos. **Canary:** versão nova numa fatia pequena do tráfego.
>
> **Database migrations:** backward-compatible, Expand-Contract pattern. Health checks para checar se está pronto. Rollback via symlink para o release anterior.

---

## Conteúdo

- [O que é](#o-que-é)
- [Blue-Green Deployment](#blue-green-deployment)
- [Rolling Deployment](#rolling-deployment)
- [Canary Deployment](#canary-deployment)
- [Recreate](#recreate-com-downtime)
- [Database Migrations em production](#database-migrations-em-production)
- [Exemplos práticos](#exemplos-práticos)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Deployment strategies — estratégias de deploy da app em production sem downtime.

**Estratégias principais:**
- Blue-Green Deployment
- Rolling Deployment
- Canary Deployment
- Recreate (com downtime)

---

## Blue-Green Deployment

**Princípio:**
Dois ambientes idênticos (Blue e Green). Deploy no inativo, depois a troca.

**Esquema:**

```
Users → Load Balancer → Blue (current, v1.0)
                     → Green (idle, v1.1)

Depois do deploy:
Users → Load Balancer → Blue (idle, v1.0)
                     → Green (current, v1.1)
```

**Implementação com Docker:**

```bash
# docker-compose.blue.yml
version: '3.8'
services:
  app:
    image: myapp:1.0
    ports:
      - "8000:80"

# docker-compose.green.yml
version: '3.8'
services:
  app:
    image: myapp:1.1
    ports:
      - "8001:80"
```

**Deploy script:**

```bash
#!/bin/bash
set -e

# Descobrir a cor atual
if docker ps | grep -q "blue"; then
    CURRENT="blue"
    NEW="green"
    NEW_PORT=8001
else
    CURRENT="green"
    NEW="blue"
    NEW_PORT=8000
fi

echo "Fazendo deploy em $NEW"

# Subir a versão nova
docker-compose -f docker-compose.$NEW.yml up -d

# Esperar ficar pronto
sleep 10

# Health check
if curl -f http://localhost:$NEW_PORT/health; then
    echo "Health check passou"

    # Trocar o nginx
    sed -i "s/proxy_pass http:\/\/localhost:[0-9]\+/proxy_pass http:\/\/localhost:$NEW_PORT/g" /etc/nginx/sites-available/default
    nginx -s reload

    # Parar a versão antiga
    sleep 30  # Esperar as requests atuais terminarem
    docker-compose -f docker-compose.$CURRENT.yml down

    echo "Deploy concluído"
else
    echo "Health check falhou, fazendo rollback"
    docker-compose -f docker-compose.$NEW.yml down
    exit 1
fi
```

**Prós:**
- ✅ Zero downtime
- ✅ Instant rollback
- ✅ Teste em ambiente igual ao de production

**Contras:**
- ❌ Recursos em dobro
- ❌ Migrations de DB complicam

---

## Rolling Deployment

**Princípio:**
Troca as instâncias uma a uma.

**Esquema:**

```
Antes:
Server 1 (v1.0) → v1.1
Server 2 (v1.0)
Server 3 (v1.0)

Passo 1:
Server 1 (v1.1)
Server 2 (v1.0) → v1.1
Server 3 (v1.0)

Passo 2:
Server 1 (v1.1)
Server 2 (v1.1)
Server 3 (v1.0) → v1.1

Pronto:
Server 1 (v1.1)
Server 2 (v1.1)
Server 3 (v1.1)
```

**Kubernetes Rolling Update:**

```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  strategy:
    type: RollingUpdate
    rollingUpdate:
      maxUnavailable: 1  # No máximo 1 pod fora
      maxSurge: 1        # No máximo 1 pod além das replicas
  template:
    spec:
      containers:
      - name: app
        image: myapp:1.1
```

**Capistrano (para PHP):**

```ruby
# config/deploy.rb
set :application, 'myapp'
set :repo_url, 'git@github.com:user/myapp.git'
set :deploy_to, '/var/www/html'

# Rolling deploy em 3 servidores
server 'server1.example.com', roles: [:app, :web, :db]
server 'server2.example.com', roles: [:app, :web]
server 'server3.example.com', roles: [:app, :web]

namespace :deploy do
  task :restart do
    on roles(:app), in: :sequence, wait: 30 do
      execute :sudo, :systemctl, :reload, 'php8.2-fpm'
    end
  end
end
```

**Prós:**
- ✅ Zero downtime
- ✅ Não precisa de recursos em dobro
- ✅ Rollout gradual

**Contras:**
- ❌ Mais lento que blue-green
- ❌ Duas versões no ar ao mesmo tempo

---

## Canary Deployment

**Princípio:**
Versão nova numa fatia pequena do tráfego. Depois você aumenta aos poucos.

**Esquema:**

```
Users (95%) → v1.0
Users (5%)  → v1.1 (canary)

Se estiver ok:
Users (50%) → v1.0
Users (50%) → v1.1

Depois:
Users (100%) → v1.1
```

**Nginx canary:**

```nginx
upstream backend {
    server backend1.example.com weight=95;  # v1.0
    server backend2.example.com weight=5;   # v1.1 (canary)
}

server {
    location / {
        proxy_pass http://backend;
    }
}
```

**Kubernetes canary:**

```yaml
# v1 deployment (90% do tráfego)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-v1
spec:
  replicas: 9

---

# v2 deployment (10% do tráfego)
apiVersion: apps/v1
kind: Deployment
metadata:
  name: app-v2
spec:
  replicas: 1
```

**Prós:**
- ✅ Teste com usuário de verdade
- ✅ Risco baixo
- ✅ Dá para fazer rollback só da fatia pequena

**Contras:**
- ❌ Setup mais chato
- ❌ Precisa monitorar métricas

---

## Recreate (com downtime)

**Princípio:**
Para a versão antiga, sobe a nova.

**Implementação:**

```bash
#!/bin/bash
# Deploy simples com downtime

php artisan down  # Maintenance mode

git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo systemctl reload php8.2-fpm

php artisan up  # Sair do maintenance
```

**Prós:**
- ✅ Simples
- ✅ Sem dor de cabeça com migrations

**Contras:**
- ❌ Downtime

---

## Database Migrations em production

**Problema:**
Blue-Green e Rolling deployment com migrations incompatíveis.

**Solução: migrations backward-compatible**

```php
// ❌ RUIM: quebra a versão antiga
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('old_field');
    $table->renameColumn('name', 'full_name');
});

// ✅ BOM: compatível com a versão antiga
// Passo 1: adicionar o campo novo
Schema::table('users', function (Blueprint $table) {
    $table->string('full_name')->nullable();
});

// Passo 2 (próximo deploy): preencher os dados
DB::table('users')->whereNull('full_name')->update([
    'full_name' => DB::raw('name')
]);

// Passo 3 (próximo deploy): remover o campo antigo
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn('name');
});
```

**Expand-Contract Pattern:**

```
1. Expand: adicionar campos/tabelas novos
   → Deploy da versão nova (funciona com os dois campos)
2. Migrate: migrar os dados
3. Contract: remover os campos antigos
   → Deploy da versão final
```

---

## Exemplos práticos

**Health check endpoint:**

```php
// routes/web.php
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        Cache::get('health-check');

        return response()->json([
            'status' => 'healthy',
            'version' => config('app.version'),
            'timestamp' => now(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
        ], 500);
    }
});
```

**Graceful shutdown:**

```php
// app/Console/Commands/GracefulShutdown.php
public function handle()
{
    // Parar de receber jobs novos
    Artisan::call('queue:restart');

    // Esperar os jobs atuais terminarem
    while (Queue::size() > 0) {
        $this->info('Esperando ' . Queue::size() . ' jobs...');
        sleep(5);
    }

    $this->info('Shutdown concluído');
}
```

**Feature flags:**

```php
// Ligar a feature nova só para 10%
if (random_int(1, 100) <= 10) {
    // Feature nova
} else {
    // Feature antiga
}

// Ou via config
if (config('features.new_payment_flow')) {
    // Feature nova
}
```

---

## Na entrevista

**Resposta estruturada:**

**Blue-Green:**
- Dois ambientes idênticos (Blue e Green)
- Deploy no ambiente inativo, testa
- Troca o load balancer para o ambiente novo
- Instant rollback — volta para o ambiente antigo
- Contra: recursos em dobro, migrations de DB complicam

**Rolling:**
- Troca as instâncias uma a uma
- maxUnavailable — quantas podem ficar fora
- maxSurge — quantas podem passar do total
- Não precisa de recursos em dobro
- Contra: duas versões no ar ao mesmo tempo

**Canary:**
- Versão nova numa fatia pequena do tráfego (5-10%)
- Aumenta aos poucos se estiver ok
- Monitora métricas (erro, latency)
- Rollback se as métricas piorarem
- Para mudanças críticas

**Database migrations:**
- Migrations backward-compatible são obrigatórias
- Expand-Contract pattern:
  1. Adiciona o campo novo
  2. Deploy (funciona com os dois campos)
  3. Migra os dados
  4. Remove o campo antigo
- Nunca faça breaking change no mesmo deploy

**Health checks:**
- Endpoint `/health` para checar se está pronto
- Checa DB, Cache, Queue
- Graceful shutdown para terminar os jobs atuais
- Feature flags para rollout gradual de features

---

## Exercícios práticos

### Exercício 1: Implemente Blue-Green deployment com Docker

Crie um Blue-Green deployment para Laravel com health check automático e troca do nginx.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.blue.yml
version: '3.8'

services:
  app-blue:
    image: myapp:${VERSION}
    container_name: app-blue
    environment:
      - APP_ENV=production
      - APP_VERSION=${VERSION}
      - DB_HOST=mysql
    ports:
      - "8000:80"
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 10s
      timeout: 5s
      retries: 3

networks:
  app-network:
    external: true
```

```yaml
# docker-compose.green.yml
version: '3.8'

services:
  app-green:
    image: myapp:${VERSION}
    container_name: app-green
    environment:
      - APP_ENV=production
      - APP_VERSION=${VERSION}
      - DB_HOST=mysql
    ports:
      - "8001:80"
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 10s
      timeout: 5s
      retries: 3

networks:
  app-network:
    external: true
```

```nginx
# /etc/nginx/sites-available/myapp
upstream backend {
    server localhost:8000;  # Vai alternar entre 8000/8001
}

server {
    listen 80;
    server_name example.com;

    location / {
        proxy_pass http://backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    location /health {
        access_log off;
        proxy_pass http://backend/health;
    }
}
```

```bash
#!/bin/bash
# deploy-blue-green.sh
set -e

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Uso: $0 <version>"
    exit 1
fi

echo "🚀 Iniciando Blue-Green deployment da versão $VERSION"

# Descobrir a cor atual
CURRENT_COLOR="blue"
if docker ps | grep -q "app-blue"; then
    CURRENT_COLOR="blue"
    NEW_COLOR="green"
    NEW_PORT=8001
else
    CURRENT_COLOR="green"
    NEW_COLOR="blue"
    NEW_PORT=8000
fi

echo "Atual: $CURRENT_COLOR"
echo "Deploy em: $NEW_COLOR na porta $NEW_PORT"

# Subir a versão nova
echo "📦 Baixando a image da versão $VERSION..."
docker pull myapp:$VERSION

echo "🚀 Iniciando o ambiente $NEW_COLOR..."
VERSION=$VERSION docker-compose -f docker-compose.$NEW_COLOR.yml up -d

# Esperar ficar pronto
echo "⏳ Esperando $NEW_COLOR ficar pronto..."
MAX_RETRIES=30
RETRY=0

while [ $RETRY -lt $MAX_RETRIES ]; do
    if curl -f http://localhost:$NEW_PORT/health > /dev/null 2>&1; then
        echo "✅ Health check passou!"
        break
    fi

    RETRY=$((RETRY+1))
    echo "Tentativa $RETRY/$MAX_RETRIES..."
    sleep 2
done

if [ $RETRY -eq $MAX_RETRIES ]; then
    echo "❌ Health check falhou depois de $MAX_RETRIES tentativas"
    echo "🔄 Fazendo rollback..."
    docker-compose -f docker-compose.$NEW_COLOR.yml down
    exit 1
fi

# Rodar as migrations
echo "🗄️  Rodando as migrations..."
docker-compose -f docker-compose.$NEW_COLOR.yml exec -T app-$NEW_COLOR php artisan migrate --force

# Checar de novo depois das migrations
sleep 3
if ! curl -f http://localhost:$NEW_PORT/health > /dev/null 2>&1; then
    echo "❌ Health check falhou depois das migrations"
    echo "🔄 Fazendo rollback das migrations e do container..."
    docker-compose -f docker-compose.$NEW_COLOR.yml exec -T app-$NEW_COLOR php artisan migrate:rollback --force
    docker-compose -f docker-compose.$NEW_COLOR.yml down
    exit 1
fi

# Trocar o nginx para a porta nova
echo "🔄 Trocando o nginx para $NEW_COLOR (porta $NEW_PORT)..."
sudo sed -i "s/server localhost:[0-9]\+;/server localhost:$NEW_PORT;/" /etc/nginx/sites-available/myapp
sudo nginx -t && sudo nginx -s reload

echo "✅ Nginx apontando para $NEW_COLOR"

# Esperar as requests atuais terminarem
echo "⏳ Esperando as requests atuais terminarem (30s)..."
sleep 30

# Parar a versão antiga
echo "🛑 Parando o ambiente $CURRENT_COLOR..."
docker-compose -f docker-compose.$CURRENT_COLOR.yml down

echo "======================================"
echo "🎉 Deploy concluído com sucesso!"
echo "======================================"
echo "Version: $VERSION"
echo "Ambiente ativo: $NEW_COLOR"
echo "Port: $NEW_PORT"
```

```bash
# rollback-blue-green.sh
#!/bin/bash
set -e

echo "⏪ Iniciando o rollback..."

# Descobrir a cor atual e a anterior
if docker ps | grep -q "app-blue"; then
    CURRENT_COLOR="blue"
    PREVIOUS_COLOR="green"
    CURRENT_PORT=8000
    PREVIOUS_PORT=8001
else
    CURRENT_COLOR="green"
    PREVIOUS_COLOR="blue"
    CURRENT_PORT=8001
    PREVIOUS_PORT=8000
fi

echo "Atual: $CURRENT_COLOR (porta $CURRENT_PORT)"
echo "Rollback para: $PREVIOUS_COLOR (porta $PREVIOUS_PORT)"

# Checar se a versão anterior ainda existe
if ! docker ps -a | grep -q "app-$PREVIOUS_COLOR"; then
    echo "❌ Ambiente anterior ($PREVIOUS_COLOR) não encontrado!"
    exit 1
fi

# Se a versão anterior estiver parada — subir
if ! docker ps | grep -q "app-$PREVIOUS_COLOR"; then
    echo "🚀 Iniciando o ambiente $PREVIOUS_COLOR..."
    docker-compose -f docker-compose.$PREVIOUS_COLOR.yml up -d

    # Esperar ficar pronto
    sleep 10
fi

# Health check
if ! curl -f http://localhost:$PREVIOUS_PORT/health > /dev/null 2>&1; then
    echo "❌ Health check falhou no $PREVIOUS_COLOR"
    exit 1
fi

# Trocar o nginx de volta
echo "🔄 Trocando o nginx para $PREVIOUS_COLOR..."
sudo sed -i "s/server localhost:[0-9]\+;/server localhost:$PREVIOUS_PORT;/" /etc/nginx/sites-available/myapp
sudo nginx -t && sudo nginx -s reload

sleep 10

# Parar a versão atual (que não está ok)
echo "🛑 Parando o ambiente $CURRENT_COLOR..."
docker-compose -f docker-compose.$CURRENT_COLOR.yml down

# Rollback das migrations
echo "🗄️  Fazendo rollback das migrations..."
docker-compose -f docker-compose.$PREVIOUS_COLOR.yml exec -T app-$PREVIOUS_COLOR php artisan migrate:rollback --force

echo "======================================"
echo "✅ Rollback concluído!"
echo "======================================"
echo "Ambiente ativo: $PREVIOUS_COLOR"
```

```bash
# Uso:

# Deploy da versão nova
./deploy-blue-green.sh v1.2.0

# Rollback para a versão anterior
./rollback-blue-green.sh

# Checar o status atual
docker ps
curl http://localhost/health
```
</details>

### Exercício 2: Implemente migrations backward-compatible

Você tem o campo `users.name`. Precisa quebrar em `first_name` e `last_name` sem downtime.

<details>
<summary>Solução</summary>

```php
// database/migrations/2024_01_01_000001_add_first_last_name_to_users.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passo 1: adicionar os campos novos
     * Deploy: v1.0 → v1.1 (suporta os dois formatos)
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Campos novos como nullable
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
```

```php
// app/Models/User.php (v1.1 — funciona com os dois formatos)
class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'password',
    ];

    // Accessor para retrocompatibilidade
    public function getNameAttribute($value)
    {
        // Se tem name — usa ele
        if ($value) {
            return $value;
        }

        // Senão monta com first_name + last_name
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }

        return $this->first_name ?? $this->last_name ?? '';
    }

    // Mutator para sincronizar
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value;

        // Sincroniza name se já tem last_name
        if (isset($this->attributes['last_name'])) {
            $this->attributes['name'] = $value . ' ' . $this->attributes['last_name'];
        }
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value;

        // Sincroniza name se já tem first_name
        if (isset($this->attributes['first_name'])) {
            $this->attributes['name'] = $this->attributes['first_name'] . ' ' . $value;
        }
    }
}
```

```php
// database/migrations/2024_01_02_000001_migrate_name_data.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Passo 2: migrar os dados (depois do deploy da v1.1)
     * Deploy: v1.1 continua, só migra os dados
     */
    public function up()
    {
        // Migrar em lotes se a tabela for grande
        DB::table('users')
            ->whereNull('first_name')
            ->whereNull('last_name')
            ->whereNotNull('name')
            ->chunk(1000, function ($users) {
                foreach ($users as $user) {
                    $parts = explode(' ', $user->name, 2);

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'first_name' => $parts[0] ?? '',
                            'last_name' => $parts[1] ?? '',
                        ]);
                }
            });
    }

    public function down()
    {
        // Restaurar name a partir de first_name + last_name
        DB::table('users')
            ->whereNotNull('first_name')
            ->chunk(1000, function ($users) {
                foreach ($users as $user) {
                    $name = trim($user->first_name . ' ' . ($user->last_name ?? ''));

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['name' => $name]);
                }
            });
    }
};
```

```php
// app/Models/User.php (v1.2 — usa só os campos novos)
class User extends Authenticatable
{
    protected $fillable = [
        'first_name',  // name saiu do fillable
        'last_name',
        'email',
        'password',
    ];

    // Accessor para retrocompatibilidade (se alguém ainda usa name)
    public function getNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
```

```php
// database/migrations/2024_01_03_000001_remove_name_from_users.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passo 3: remover o campo antigo (depois do deploy da v1.2)
     * Deploy: v1.2 → v1.3 (usa só os campos novos)
     */
    public function up()
    {
        // Garantir que todos os dados foram migrados
        $unmigrated = DB::table('users')
            ->whereNull('first_name')
            ->whereNull('last_name')
            ->whereNotNull('name')
            ->count();

        if ($unmigrated > 0) {
            throw new \Exception("Encontrou $unmigrated usuários com name ainda sem migrar. Rode a migration 2024_01_02_000001 primeiro.");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable();
        });

        // Restaurar os dados
        DB::table('users')->chunk(1000, function ($users) {
            foreach ($users as $user) {
                $name = trim($user->first_name . ' ' . ($user->last_name ?? ''));
                DB::table('users')->where('id', $user->id)->update(['name' => $name]);
            }
        });
    }
};
```

```bash
# Processo de deploy:

# Deploy v1.1 (adiciona os campos novos)
git pull
composer install
php artisan migrate  # Roda 2024_01_01_000001
# A app agora suporta os dois formatos (name e first_name/last_name)

# Deploy v1.1 (migra os dados) — na hora ou um pouco depois
php artisan migrate  # Roda 2024_01_02_000001
# Dados migrados, mas o campo name ainda existe

# Deploy v1.2 (código passa a usar os campos novos)
git pull
composer install
# Código usa first_name/last_name, mas name ainda está lá

# Deploy v1.3 (remove o campo antigo)
php artisan migrate  # Roda 2024_01_03_000001
# Campo name removido

# Importante: entre cada passo dá para pausar e checar
```

```php
// Testes de backward compatibility
// tests/Feature/UserMigrationTest.php
class UserMigrationTest extends TestCase
{
    /** @test */
    public function it_supports_old_name_field()
    {
        // Criar pela API antiga
        $user = User::create([
            'name' => 'João Silva',
            'email' => 'joao@email.com',
            'password' => bcrypt('password'),
        ]);

        // Checar se funciona
        $this->assertEquals('João', $user->first_name);
        $this->assertEquals('Silva', $user->last_name);
        $this->assertEquals('João Silva', $user->name);
    }

    /** @test */
    public function it_supports_new_fields()
    {
        // Criar pela API nova
        $user = User::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@email.com',
            'password' => bcrypt('password'),
        ]);

        // Checar se funciona
        $this->assertEquals('Maria Santos', $user->name);
    }
}
```
</details>

### Exercício 3: Canary deployment com métricas

Implemente canary deployment: rode a versão nova para 10% dos usuários, monitore erros, faça rollback automático se o error rate passar de 5%.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.canary.yml
version: '3.8'

services:
  # Production v1 (90%)
  app-v1-1:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-2:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-3:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-4:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-5:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-6:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-7:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-8:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  app-v1-9:
    image: myapp:1.0.0
    environment:
      - APP_VERSION=1.0.0
    networks:
      - app-network
    labels:
      - "version=1.0.0"
      - "canary=false"

  # Canary v2 (10%)
  app-v2-canary:
    image: myapp:2.0.0
    environment:
      - APP_VERSION=2.0.0
      - CANARY=true
    networks:
      - app-network
    labels:
      - "version=2.0.0"
      - "canary=true"

networks:
  app-network:
```

```nginx
# /etc/nginx/sites-available/myapp-canary
upstream backend_v1 {
    # 90% do tráfego na v1
    server app-v1-1:80;
    server app-v1-2:80;
    server app-v1-3:80;
    server app-v1-4:80;
    server app-v1-5:80;
    server app-v1-6:80;
    server app-v1-7:80;
    server app-v1-8:80;
    server app-v1-9:80;
}

upstream backend_v2 {
    # 10% do tráfego na v2 (canary)
    server app-v2-canary:80;
}

# Escolher o backend no random
split_clients "${remote_addr}${http_user_agent}${date_gmt}" $backend {
    90%     backend_v1;
    *       backend_v2;
}

server {
    listen 80;
    server_name example.com;

    location / {
        proxy_pass http://$backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;

        # Adicionar a versão no header para debug
        add_header X-App-Version $upstream_addr always;
    }

    location /metrics {
        stub_status on;
        access_log off;
        allow 127.0.0.1;
        deny all;
    }
}
```

```php
// app/Http/Middleware/CanaryMetrics.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CanaryMetrics
{
    public function handle($request, Closure $next)
    {
        $version = config('app.version');
        $isCanary = config('app.canary', false);

        $startTime = microtime(true);

        try {
            $response = $next($request);

            // Registrar request com sucesso
            $this->recordRequest($version, $isCanary, 'success');

            // Registrar latency
            $latency = (microtime(true) - $startTime) * 1000;
            $this->recordLatency($version, $latency);

            return $response;

        } catch (\Exception $e) {
            // Registrar erro
            $this->recordRequest($version, $isCanary, 'error');

            Log::error('Request failed', [
                'version' => $version,
                'canary' => $isCanary,
                'error' => $e->getMessage(),
                'path' => $request->path(),
            ]);

            throw $e;
        }
    }

    private function recordRequest(string $version, bool $isCanary, string $status)
    {
        $key = "metrics:{$version}:{$status}";
        Cache::increment($key);
        Cache::expire($key, 3600); // TTL de 1 hora

        if ($isCanary) {
            Cache::increment("metrics:canary:{$status}");
        }
    }

    private function recordLatency(string $version, float $latency)
    {
        $key = "metrics:{$version}:latency";
        $latencies = Cache::get($key, []);
        $latencies[] = $latency;

        // Guardar só os últimos 1000 valores
        if (count($latencies) > 1000) {
            $latencies = array_slice($latencies, -1000);
        }

        Cache::put($key, $latencies, 3600);
    }
}
```

```php
// routes/web.php - endpoint de métricas
Route::get('/canary/metrics', function () {
    $v1Metrics = [
        'success' => Cache::get('metrics:1.0.0:success', 0),
        'error' => Cache::get('metrics:1.0.0:error', 0),
        'latency' => Cache::get('metrics:1.0.0:latency', []),
    ];

    $v2Metrics = [
        'success' => Cache::get('metrics:2.0.0:success', 0),
        'error' => Cache::get('metrics:2.0.0:error', 0),
        'latency' => Cache::get('metrics:2.0.0:latency', []),
    ];

    // Calcular error rate
    $v1Total = $v1Metrics['success'] + $v1Metrics['error'];
    $v1ErrorRate = $v1Total > 0 ? ($v1Metrics['error'] / $v1Total) * 100 : 0;

    $v2Total = $v2Metrics['success'] + $v2Metrics['error'];
    $v2ErrorRate = $v2Total > 0 ? ($v2Metrics['error'] / $v2Total) * 100 : 0;

    // Latency média
    $v1AvgLatency = count($v1Metrics['latency']) > 0
        ? array_sum($v1Metrics['latency']) / count($v1Metrics['latency'])
        : 0;

    $v2AvgLatency = count($v2Metrics['latency']) > 0
        ? array_sum($v2Metrics['latency']) / count($v2Metrics['latency'])
        : 0;

    return response()->json([
        'v1' => [
            'requests' => $v1Total,
            'errors' => $v1Metrics['error'],
            'error_rate' => round($v1ErrorRate, 2),
            'avg_latency_ms' => round($v1AvgLatency, 2),
        ],
        'v2_canary' => [
            'requests' => $v2Total,
            'errors' => $v2Metrics['error'],
            'error_rate' => round($v2ErrorRate, 2),
            'avg_latency_ms' => round($v2AvgLatency, 2),
        ],
        'comparison' => [
            'error_rate_diff' => round($v2ErrorRate - $v1ErrorRate, 2),
            'latency_diff_ms' => round($v2AvgLatency - $v1AvgLatency, 2),
        ],
    ]);
})->middleware('auth:sanctum');
```

```bash
#!/bin/bash
# canary-monitor.sh - Monitora o canary e faz rollback automático

set -e

CANARY_ERROR_THRESHOLD=5.0  # 5% error rate
CANARY_LATENCY_THRESHOLD=150  # 150% do baseline
CHECK_INTERVAL=60  # Checar a cada 60 segundos
MIN_REQUESTS=100  # Mínimo de requests para estatística

echo "🔍 Iniciando o monitoramento do canary..."
echo "Limite de erro: ${CANARY_ERROR_THRESHOLD}%"
echo "Limite de latency: ${CANARY_LATENCY_THRESHOLD}%"

while true; do
    # Buscar as métricas
    METRICS=$(curl -s http://localhost/canary/metrics)

    V1_ERROR_RATE=$(echo $METRICS | jq -r '.v1.error_rate')
    V2_ERROR_RATE=$(echo $METRICS | jq -r '.v2_canary.error_rate')
    V2_REQUESTS=$(echo $METRICS | jq -r '.v2_canary.requests')

    V1_LATENCY=$(echo $METRICS | jq -r '.v1.avg_latency_ms')
    V2_LATENCY=$(echo $METRICS | jq -r '.v2_canary.avg_latency_ms')

    echo "$(date '+%Y-%m-%d %H:%M:%S') - v1: ${V1_ERROR_RATE}% errors, ${V1_LATENCY}ms | v2: ${V2_ERROR_RATE}% errors, ${V2_LATENCY}ms (${V2_REQUESTS} requests)"

    # Checar o mínimo de requests
    if [ $(echo "$V2_REQUESTS < $MIN_REQUESTS" | bc) -eq 1 ]; then
        echo "⏳ Esperando mais requests ($V2_REQUESTS/$MIN_REQUESTS)..."
        sleep $CHECK_INTERVAL
        continue
    fi

    # Checar error rate
    if [ $(echo "$V2_ERROR_RATE > $CANARY_ERROR_THRESHOLD" | bc) -eq 1 ]; then
        echo "❌ ALERTA: error rate do canary alto demais: ${V2_ERROR_RATE}% (limite: ${CANARY_ERROR_THRESHOLD}%)"
        echo "🔄 Iniciando rollback automático..."

        # Rollback
        docker-compose stop app-v2-canary

        # Avisar no Slack
        curl -X POST $SLACK_WEBHOOK \
            -H 'Content-Type: application/json' \
            -d "{\"text\":\"🚨 Rollback do canary disparado! Error rate: ${V2_ERROR_RATE}%\"}"

        exit 1
    fi

    # Checar latency (não pode passar de 150% do baseline)
    LATENCY_PERCENT=$(echo "scale=2; ($V2_LATENCY / $V1_LATENCY) * 100" | bc)

    if [ $(echo "$LATENCY_PERCENT > $CANARY_LATENCY_THRESHOLD" | bc) -eq 1 ]; then
        echo "⚠️  AVISO: latency do canary alta: ${V2_LATENCY}ms vs ${V1_LATENCY}ms (${LATENCY_PERCENT}%)"
        # Sem rollback automático, só aviso
    fi

    # Se estiver ok depois de requests suficientes
    if [ $(echo "$V2_REQUESTS > 1000" | bc) -eq 1 ] && \
       [ $(echo "$V2_ERROR_RATE < $V1_ERROR_RATE" | bc) -eq 1 ] && \
       [ $(echo "$LATENCY_PERCENT < 110" | bc) -eq 1 ]; then
        echo "✅ Canary está bem! Pronto para promover."
        echo "Requests: $V2_REQUESTS"
        echo "Error rate: $V2_ERROR_RATE% (vs $V1_ERROR_RATE%)"
        echo "Latency: $V2_LATENCY ms (vs $V1_LATENCY ms)"

        # Avisar o sucesso
        curl -X POST $SLACK_WEBHOOK \
            -H 'Content-Type: application/json' \
            -d "{\"text\":\"✅ Canary deployment ok! Pronto para promover a 100%.\"}"
    fi

    sleep $CHECK_INTERVAL
done
```

```bash
# Subir o canary deployment

# 1. Subir o canary (10%)
docker-compose -f docker-compose.canary.yml up -d

# 2. Subir o monitoramento
./canary-monitor.sh

# 3. Se estiver ok depois de algumas horas — subir para 50%
# Mudar o nginx upstream e recarregar

# 4. Se estiver ok — promover a 100%
# Trocar todas as v1 por v2
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
