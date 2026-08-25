# 13.6 Scaling e Load Balancing

## Resumo

> **Scaling** — aumentar a capacidade do sistema. Vertical (mais recurso) vs Horizontal (mais servidores).
>
> **Load Balancer** — distribui a carga entre os servidores (Nginx). Estratégias: round-robin, least_conn, ip_hash.
>
> **Problemas:** sessões (solução: Redis), cache (Redis), files (S3, NFS). Database: read replicas, master-slave.

---

## Conteúdo

- [O que é](#o-que-é)
- [Vertical vs Horizontal Scaling](#vertical-vs-horizontal-scaling)
- [Load Balancer](#load-balancer)
- [Session management](#session-management)
- [Cache synchronization](#cache-synchronization)
- [File storage synchronization](#file-storage-synchronization)
- [Database scaling](#database-scaling)
- [Queue workers scaling](#queue-workers-scaling)
- [Exemplos práticos](#exemplos-práticos)
- [CDN para scaling](#cdn-para-scaling)
- [Monitoring no scaling](#monitoring-no-scaling)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Scaling — aumentar a capacidade do sistema para aguentar mais carga.

**Tipos:**
- **Vertical scaling** (mais recurso no servidor)
- **Horizontal scaling** (mais servidores)

**Load Balancing:**
Distribui a carga entre os servidores.

---

## Vertical vs Horizontal Scaling

**Vertical:**

```
Antes:
Server: 2 CPU, 4GB RAM

Depois:
Server: 8 CPU, 16GB RAM
```

**Prós:**
- ✅ Mais simples (não muda a arquitetura)
- ✅ Não precisa sincronizar

**Contras:**
- ❌ Tem teto (não dá para aumentar pra sempre)
- ❌ Single point of failure
- ❌ Downtime no upgrade

**Horizontal:**

```
Antes:
Server 1 (100% da carga)

Depois:
Load Balancer
├─ Server 1 (33%)
├─ Server 2 (33%)
└─ Server 3 (33%)
```

**Prós:**
- ✅ Scaling quase sem teto
- ✅ High availability
- ✅ Zero downtime deploy

**Contras:**
- ❌ Arquitetura mais complexa
- ❌ Precisa sincronizar (sessions, cache, files)

---

## Load Balancer

**Nginx Load Balancer:**

```nginx
# /etc/nginx/conf.d/load-balancer.conf

upstream backend {
    # Round-robin (padrão)
    server 192.168.1.10:80;
    server 192.168.1.11:80;
    server 192.168.1.12:80;
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
}
```

**Estratégias de balanceamento:**

```nginx
upstream backend {
    # 1. Round-robin (na vez)
    server server1.com;
    server server2.com;

    # 2. Least connections (servidor com menos conexões)
    least_conn;
    server server1.com;
    server server2.com;

    # 3. IP hash (um cliente → um servidor)
    ip_hash;
    server server1.com;
    server server2.com;

    # 4. Weighted (com pesos)
    server server1.com weight=3;  # 3x mais tráfego
    server server2.com weight=1;

    # Health checks
    server server1.com max_fails=3 fail_timeout=30s;
    server server2.com backup;  # Entra se os outros estiverem fora
}
```

---

## Session management

**Problema:**
A sessão está no Server 1, mas o próximo request cai no Server 2.

**Solução 1: Sticky sessions**

```nginx
upstream backend {
    ip_hash;  # Um IP → um servidor
    server server1.com;
    server server2.com;
}
```

**Solução 2: Centralized sessions (Redis)**

```env
# .env
SESSION_DRIVER=redis
```

```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),

'connection' => 'session',

// config/database.php
'redis' => [
    'session' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 2,
    ],
],
```

Agora todos os servidores leem as sessões no mesmo Redis.

---

## Cache synchronization

**Problema:**
O cache do Server 1 não está sincronizado com o Server 2.

**Solução: Centralized cache (Redis)**

```env
CACHE_DRIVER=redis
```

```php
// Todos os servidores usam o mesmo Redis
Cache::put('key', 'value', 3600);  // Disponível em todos os servidores
```

---

## File storage synchronization

**Problema:**
O arquivo foi enviado no Server 1, mas não existe no Server 2.

**Solução 1: Shared storage (NFS, GlusterFS)**

```bash
# Montar shared storage em todos os servidores
mount -t nfs storage-server:/shared /var/www/html/storage
```

**Solução 2: Cloud storage (S3)**

```env
FILESYSTEM_DISK=s3
```

```php
// Arquivos ficam no S3, acessíveis a todos os servidores
Storage::disk('s3')->put('avatars/1.jpg', $file);
$url = Storage::disk('s3')->url('avatars/1.jpg');
```

---

## Database scaling

**Read replicas:**

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            '192.168.1.20',  // Read replica 1
            '192.168.1.21',  // Read replica 2
        ],
    ],
    'write' => [
        'host' => ['192.168.1.10'],  // Master
    ],
    'sticky' => true,  // Depois de escrever, lê no master
],
```

```php
// Laravel roteia as queries sozinho
User::create($data);  // → write (master)
User::all();          // → read (replica)

// Forçar write connection
DB::connection('mysql')->useWriteConnection()->select(...);
```

**Database sharding:**

```php
// Dividir os dados em shards (por user_id)
$shard = $userId % 4;  // 4 shards

DB::connection("mysql_shard_$shard")->table('orders')
    ->where('user_id', $userId)
    ->get();
```

---

## Queue workers scaling

**Supervisor com vários workers:**

```ini
; /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
numprocs=8  ; 8 workers em paralelo
user=www-data
```

**Horizontal scaling de workers:**

```
Server 1: 8 workers
Server 2: 8 workers
Server 3: 8 workers
     ↓
Shared Redis Queue
```

Todos os workers consomem a mesma queue no Redis.

---

## Exemplos práticos

**Laravel Octane (carga alta):**

```bash
composer require laravel/octane

# Swoole
pecl install swoole
php artisan octane:install --server=swoole

# Iniciar
php artisan octane:start --workers=8 --task-workers=4
```

**Performance:**

```
Apache + mod_php: 100 req/sec
PHP-FPM: 500 req/sec
Octane (Swoole): 2000+ req/sec
```

**Auto-scaling na AWS:**

```yaml
# aws-autoscaling.yml
Resources:
  AutoScalingGroup:
    Type: AWS::AutoScaling::AutoScalingGroup
    Properties:
      MinSize: 2
      MaxSize: 10
      DesiredCapacity: 2
      TargetGroupARNs:
        - !Ref TargetGroup
      LaunchTemplate:
        LaunchTemplateId: !Ref LaunchTemplate

      # Scale up quando CPU > 70%
      ScalingPolicies:
        - PolicyName: scale-up
          ScalingAdjustment: 2
          Cooldown: 300
          MetricAggregationType: Average
          TargetValue: 70
```

**Docker Swarm:**

```bash
# Inicializar
docker swarm init

# Deploy com 3 réplicas
docker stack deploy -c docker-compose.yml myapp

# docker-compose.yml
services:
  app:
    image: myapp:latest
    deploy:
      replicas: 3
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
      restart_policy:
        condition: on-failure
```

**Kubernetes:**

```yaml
# deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel
  template:
    metadata:
      labels:
        app: laravel
    spec:
      containers:
      - name: app
        image: myapp:latest
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"

---
# hpa.yaml (Horizontal Pod Autoscaler)
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-app
  minReplicas: 3
  maxReplicas: 10
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
```

---

## CDN para scaling

**CloudFlare:**

```
Users → CloudFlare CDN → Origin Server
        (cache de estáticos)
```

**Cache de páginas:**

```php
// Adicionar cache headers
return response()->view('home')
    ->header('Cache-Control', 'public, max-age=3600')
    ->header('CDN-Cache-Control', 'max-age=86400');
```

---

## Monitoring no scaling

**Metrics por servidor:**

```php
// app/Http/Middleware/MetricsMiddleware.php
public function handle($request, Closure $next)
{
    $start = microtime(true);

    $response = $next($request);

    $duration = microtime(true) - $start;

    // Enviar métricas
    Cache::increment('server.' . gethostname() . '.requests');
    Cache::set('server.' . gethostname() . '.response_time', $duration);

    return $response;
}
```

---

## Na entrevista

> "Scaling: vertical (mais recurso) vs horizontal (mais servidores). Load Balancer (Nginx) distribui as requests: round-robin, least_conn, ip_hash. Problemas: sessões (solução: Redis), cache (Redis), files (S3, NFS). Database: read replicas para leitura, master para escrita. Queue workers: supervisor com numprocs, Redis queue compartilhada. Laravel Octane para performance alta. Auto-scaling na nuvem (AWS, K8s HPA). CDN para estático."

---

## Exercícios práticos

### Exercício 1: Configure o Nginx Load Balancer

Configure o Nginx como load balancer para 3 servidores Laravel, com health checks e sticky sessions.

<details>
<summary>Solução</summary>

```nginx
# /etc/nginx/conf.d/load-balancer.conf

upstream laravel_backend {
    # Estratégia de balanceamento
    least_conn;  # Menor quantidade de conexões

    # Servidores
    server 192.168.1.10:80 weight=3 max_fails=3 fail_timeout=30s;
    server 192.168.1.11:80 weight=2 max_fails=3 fail_timeout=30s;
    server 192.168.1.12:80 weight=1 max_fails=3 fail_timeout=30s backup;

    # Health check (precisa de nginx-plus ou módulo)
    # health_check interval=5s fails=3 passes=2;

    # Sticky sessions (para apps stateful)
    # ip_hash;  # Um IP → um servidor
}

server {
    listen 80;
    server_name example.com;

    # Logs
    access_log /var/log/nginx/loadbalancer-access.log;
    error_log /var/log/nginx/loadbalancer-error.log;

    location / {
        proxy_pass http://laravel_backend;

        # Headers
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Timeouts
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;

        # Buffer
        proxy_buffering on;
        proxy_buffer_size 4k;
        proxy_buffers 8 4k;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "OK\n";
        add_header Content-Type text/plain;
    }
}

# Testar
# sudo nginx -t
# sudo systemctl reload nginx

# Checar o balanceamento
# for i in {1..10}; do curl -s http://example.com | grep "Server"; done
```
</details>

### Exercício 2: Configure sessões centralizadas no Redis

Configure o Laravel para vários servidores usando Redis para sessões.

<details>
<summary>Solução</summary>

```bash
# 1. Instalar Redis num servidor separado
sudo apt-get install redis-server

# /etc/redis/redis.conf
bind 0.0.0.0  # Escutar em todas as interfaces
requirepass your_strong_password
maxmemory 2gb
maxmemory-policy allkeys-lru  # Remove as chaves antigas

# Iniciar
sudo systemctl start redis
sudo systemctl enable redis
```

```env
# .env em todos os servidores Laravel
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=192.168.1.100  # IP do servidor Redis
REDIS_PASSWORD=your_strong_password
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

```php
// config/session.php
'driver' => env('SESSION_DRIVER', 'redis'),
'connection' => 'session',

// config/database.php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),

    'session' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 2,  # DB separado para sessões
    ],

    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 1,  # DB separado para cache
    ],

    'queue' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', 6379),
        'database' => 0,  # DB separado para queue
    ],
],

// Testar
// routes/web.php
Route::get('/test-session', function () {
    session(['test_key' => 'Server: ' . gethostname()]);
    return session('test_key');
});

// Chamar várias vezes pelo load balancer
// curl http://example.com/test-session
// Tem que devolver o mesmo valor, independente do servidor
```
</details>

### Exercício 3: Auto-scaling com Docker Swarm

Configure auto-scaling da app Laravel com Docker Swarm.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    image: myapp:latest
    networks:
      - app-network
    environment:
      - APP_ENV=production
      - DB_HOST=mysql
      - REDIS_HOST=redis
    deploy:
      replicas: 3  # Quantidade inicial
      update_config:
        parallelism: 1
        delay: 10s
        order: start-first
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
      resources:
        limits:
          cpus: '0.5'
          memory: 512M
        reservations:
          cpus: '0.25'
          memory: 256M

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    networks:
      - app-network
    deploy:
      replicas: 2
      placement:
        constraints:
          - node.role == manager
    configs:
      - source: nginx_config
        target: /etc/nginx/nginx.conf

  mysql:
    image: mysql:8.0
    networks:
      - app-network
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: laravel
    volumes:
      - mysql-data:/var/lib/mysql
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.labels.db == true

  redis:
    image: redis:alpine
    networks:
      - app-network
    deploy:
      replicas: 1

networks:
  app-network:
    driver: overlay

volumes:
  mysql-data:

configs:
  nginx_config:
    file: ./nginx.conf
```

```bash
# Inicializar o Swarm
docker swarm init

# Deploy stack
docker stack deploy -c docker-compose.yml myapp

# Listar services
docker service ls

# Escalar na mão
docker service scale myapp_app=5

# Auto-scaling (via serviço externo)
# Usar Prometheus + Alertmanager + script customizado

# Monitoring
docker service ps myapp_app

# Rolling update
docker service update --image myapp:v2 myapp_app

# Logs
docker service logs myapp_app -f
```

```nginx
# nginx.conf para load balancing
upstream app_backend {
    least_conn;
    server app:9000;  # Docker Swarm DNS round-robin
}

server {
    listen 80;

    location / {
        fastcgi_pass app_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        include fastcgi_params;
    }
}
```

```bash
# Métricas para auto-scaling
# docker-compose.metrics.yml
version: '3.8'

services:
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin

# prometheus.yml
scrape_configs:
  - job_name: 'docker'
    static_configs:
      - targets: ['cadvisor:8080']

# Auto-scaling script (Python)
# if cpu_usage > 70% → scale up
# if cpu_usage < 30% → scale down
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
