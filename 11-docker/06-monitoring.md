# 12.6 Monitoring e Logging

## Resumo

> **Monitoring** — acompanhar o estado do app. **Logging** — registrar eventos.
>
> **Laravel Logging:** channels (single, daily, stack, slack), níveis (emergency, error, info). **APM:** Telescope no dev, New Relic/Sentry em production.
>
> **Centralized logging:** ELK Stack ou Loki. Health checks para uptime monitoring. Alerting no Slack/email em evento crítico.

---

## Conteúdo

- [O que é](#o-que-é)
- [Laravel Logging](#laravel-logging)
- [Structured Logging](#structured-logging)
- [Application Performance Monitoring (APM)](#application-performance-monitoring-apm)
- [Metrics e Monitoring](#metrics-e-monitoring)
- [Centralized Logging](#centralized-logging)
- [Health Checks e Uptime Monitoring](#health-checks-e-uptime-monitoring)
- [Alerting](#alerting)
- [Dicas práticas](#dicas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Monitoring — acompanhar o estado do app. Logging — registrar eventos.

**Para quê:**
- Detectar problemas
- Analisar performance
- Fazer debug de erro em production
- Alertar em evento crítico

---

## Laravel Logging

**Configuração (config/logging.php):**

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'slack'],
    ],

    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
        'days' => 14,
    ],

    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'level' => 'critical',
    ],
],
```

**Uso:**

```php
use Illuminate\Support\Facades\Log;

// Níveis
Log::emergency($message);  // Sistema fora do ar
Log::alert($message);      // Pede atenção imediata
Log::critical($message);   // Erro crítico
Log::error($message);      // Erro
Log::warning($message);    // Aviso
Log::notice($message);     // Normal, mas relevante
Log::info($message);       // Informação
Log::debug($message);      // Debug

// Com contexto
Log::info('Usuário fez login', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
]);

// Channel específico
Log::channel('slack')->critical('Payment gateway fora do ar!');
```

**Channel customizado:**

```php
// config/logging.php
'custom' => [
    'driver' => 'daily',
    'path' => storage_path('logs/payments.log'),
    'level' => 'info',
],

// Uso
Log::channel('custom')->info('Pagamento processado', ['amount' => 100]);
```

---

## Structured Logging

**Logging em JSON:**

```php
// config/logging.php
'json' => [
    'driver' => 'daily',
    'path' => storage_path('logs/json.log'),
    'formatter' => Monolog\Formatter\JsonFormatter::class,
],

// O log sai em JSON
Log::channel('json')->info('Ação do usuário', [
    'user_id' => 123,
    'action' => 'purchase',
    'amount' => 99.99,
]);

// Resultado:
// {"message":"Ação do usuário","context":{"user_id":123,"action":"purchase","amount":99.99},"level":200,"datetime":"2024-01-15T10:30:00+00:00"}
```

**Correlation ID (para tracing):**

```php
// app/Http/Middleware/AddCorrelationId.php
public function handle($request, Closure $next)
{
    $correlationId = $request->header('X-Correlation-ID') ?? Str::uuid();

    Log::withContext(['correlation_id' => $correlationId]);

    $response = $next($request);
    $response->headers->set('X-Correlation-ID', $correlationId);

    return $response;
}

// Agora todo log leva correlation_id
```

---

## Application Performance Monitoring (APM)

**Laravel Telescope (para development):**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

```php
// Disponível em /telescope
// Mostra:
// - Requests
// - Commands
// - Queries
// - Jobs
// - Exceptions
// - Logs
// - Cache
```

**New Relic:**

```bash
# Instalar a PHP extension
sudo apt-get install newrelic-php5

# Configuração
newrelic.appname = "Meu App Laravel"
newrelic.license = "YOUR_LICENSE_KEY"
```

**Sentry (para error tracking):**

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_DSN
```

```php
// Pega todas as exceções sozinho
// Ou na mão:
try {
    $this->processPayment();
} catch (\Exception $e) {
    Sentry::captureException($e);
    Log::error('Pagamento falhou', ['exception' => $e]);
}
```

---

## Metrics e Monitoring

**Prometheus + Grafana:**

```bash
# docker-compose.yml
services:
  prometheus:
    image: prom/prometheus
    ports:
      - "9090:9090"
    volumes:
      - ./prometheus.yml:/etc/prometheus/prometheus.yml

  grafana:
    image: grafana/grafana
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
```

**Endpoint de metrics no Laravel:**

```php
// routes/web.php
Route::get('/metrics', function () {
    return response()->text(
        "# HELP app_requests_total Total number of requests\n" .
        "# TYPE app_requests_total counter\n" .
        "app_requests_total " . Cache::get('metrics:requests', 0) . "\n\n" .

        "# HELP app_users_active Active users count\n" .
        "# TYPE app_users_active gauge\n" .
        "app_users_active " . User::where('last_seen_at', '>', now()->subMinutes(5))->count() . "\n"
    );
})->middleware('auth:sanctum');
```

**Metrics customizadas:**

```php
// app/Metrics/RequestCounter.php
class RequestCounter
{
    public static function increment(string $endpoint)
    {
        Cache::increment("metrics:requests:$endpoint");
    }
}

// Middleware
public function handle($request, Closure $next)
{
    RequestCounter::increment($request->path());
    return $next($request);
}
```

---

## Centralized Logging

**ELK Stack (Elasticsearch, Logstash, Kibana):**

```yaml
# docker-compose.yml
services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.5.0
    environment:
      - discovery.type=single-node
    ports:
      - "9200:9200"

  logstash:
    image: docker.elastic.co/logstash/logstash:8.5.0
    volumes:
      - ./logstash.conf:/usr/share/logstash/pipeline/logstash.conf
    depends_on:
      - elasticsearch

  kibana:
    image: docker.elastic.co/kibana/kibana:8.5.0
    ports:
      - "5601:5601"
    depends_on:
      - elasticsearch
```

**Config do Logstash:**

```
# logstash.conf
input {
  file {
    path => "/var/www/html/storage/logs/laravel.log"
    start_position => "beginning"
  }
}

filter {
  grok {
    match => { "message" => "%{TIMESTAMP_ISO8601:timestamp} %{LOGLEVEL:level}: %{GREEDYDATA:message}" }
  }
}

output {
  elasticsearch {
    hosts => ["elasticsearch:9200"]
    index => "laravel-logs-%{+YYYY.MM.dd}"
  }
}
```

**Loki + Grafana (alternativa mais leve):**

```yaml
# docker-compose.yml
services:
  loki:
    image: grafana/loki
    ports:
      - "3100:3100"

  promtail:
    image: grafana/promtail
    volumes:
      - /var/log:/var/log
      - ./promtail-config.yml:/etc/promtail/config.yml
    command: -config.file=/etc/promtail/config.yml
```

---

## Health Checks e Uptime Monitoring

**Laravel Health Check:**

```php
// routes/web.php
Route::get('/health', function () {
    $checks = [
        'database' => fn() => DB::connection()->getPdo() !== null,
        'cache' => fn() => Cache::has('health-check-test'),
        'queue' => fn() => Queue::size() < 1000,
        'storage' => fn() => is_writable(storage_path()),
    ];

    $results = [];
    $healthy = true;

    foreach ($checks as $name => $check) {
        try {
            $results[$name] = $check() ? 'OK' : 'FAILED';
            if ($results[$name] === 'FAILED') {
                $healthy = false;
            }
        } catch (\Exception $e) {
            $results[$name] = 'ERROR: ' . $e->getMessage();
            $healthy = false;
        }
    }

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $results,
        'timestamp' => now(),
    ], $healthy ? 200 : 503);
});
```

**Uptime monitoring (UptimeRobot, Pingdom):**

```
Checam o endpoint /health a cada 5 minutos
Alertas em downtime:
- Email
- Slack
- SMS
```

---

## Alerting

**Notificações no Slack:**

```php
// config/logging.php
'slack' => [
    'driver' => 'slack',
    'url' => env('LOG_SLACK_WEBHOOK_URL'),
    'username' => 'Laravel Bot',
    'emoji' => ':boom:',
    'level' => 'critical',
],

// Enviar o alerta
Log::channel('slack')->critical('Conexão com o banco caiu!', [
    'server' => gethostname(),
    'timestamp' => now(),
]);
```

**Alertas por email:**

```php
// app/Notifications/ServerAlert.php
class ServerAlert extends Notification
{
    public function via($notifiable)
    {
        return ['mail', 'slack'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->error()
            ->subject('Alerta do servidor: uso de memória alto')
            ->line('Uso de memória acima de 90%')
            ->action('Ver o dashboard', url('/admin'));
    }
}

// Enviar
$admins = User::where('role', 'admin')->get();
Notification::send($admins, new ServerAlert($details));
```

---

## Dicas práticas

**Log rotation:**

```bash
# /etc/logrotate.d/laravel
/var/www/html/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        php /var/www/html/artisan cache:clear
    endscript
}
```

**Não logar dado sensível:**

```php
// ❌ RUIM
Log::info('Usuário fez login', [
    'password' => $request->password,  // Nunca!
    'credit_card' => $card,
]);

// ✅ BOM
Log::info('Usuário fez login', [
    'user_id' => $user->id,
    'ip' => $request->ip(),
]);
```

---

## Na entrevista

**Resposta estruturada:**

**Laravel Logging:**
- Channels: single, daily, stack, slack
- Níveis: emergency, alert, critical, error, warning, notice, info, debug
- `Log::channel('name')` para um channel específico
- Structured logging com JSON formatter

**APM (Application Performance Monitoring):**
- **Telescope** no development (queries, requests, jobs, exceptions)
- **New Relic** em production (performance, errors, transactions)
- **Sentry** para error tracking e stack traces

**Metrics:**
- Prometheus + Grafana para metrics
- Custom metrics com Cache::increment()
- Endpoint `/metrics` no formato Prometheus

**Centralized Logging:**
- ELK Stack (Elasticsearch, Logstash, Kibana)
- Loki + Grafana (alternativa mais leve)
- Parse dos logs com grok patterns

**Health Checks:**
- Endpoint `/health` checa DB, Cache, Queue, Storage
- UptimeRobot/Pingdom para uptime monitoring
- Alerting no Slack/email quando quebra

**Boas práticas:**
- Log rotation para controlar o tamanho
- Correlation ID para tracing da request
- Não logar senha, token, cartão
- Formato JSON para parse fácil

---

## Exercícios práticos

### Exercício 1: Configure centralized logging com ELK Stack

**Enunciado:** Crie um ELK Stack completo para Laravel: logs → Logstash → Elasticsearch → visualização no Kibana.

<details>
<summary>Solução</summary>

```yaml
# docker-compose.elk.yml
version: '3.8'

services:
  # App Laravel
  app:
    image: myapp:latest
    volumes:
      - ./storage/logs:/var/www/html/storage/logs
    networks:
      - elk

  # Elasticsearch
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.5.0
    container_name: elasticsearch
    environment:
      - discovery.type=single-node
      - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
      - xpack.security.enabled=false
    ports:
      - "9200:9200"
      - "9300:9300"
    volumes:
      - elasticsearch-data:/usr/share/elasticsearch/data
    networks:
      - elk
    healthcheck:
      test: ["CMD-SHELL", "curl -f http://localhost:9200/_cluster/health || exit 1"]
      interval: 30s
      timeout: 10s
      retries: 5

  # Logstash
  logstash:
    image: docker.elastic.co/logstash/logstash:8.5.0
    container_name: logstash
    volumes:
      - ./elk/logstash/pipeline:/usr/share/logstash/pipeline
      - ./storage/logs:/var/log/laravel
    ports:
      - "5044:5044"
      - "9600:9600"
    environment:
      - "LS_JAVA_OPTS=-Xms256m -Xmx256m"
    networks:
      - elk
    depends_on:
      - elasticsearch

  # Kibana
  kibana:
    image: docker.elastic.co/kibana/kibana:8.5.0
    container_name: kibana
    ports:
      - "5601:5601"
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    networks:
      - elk
    depends_on:
      - elasticsearch

  # Filebeat (alternativa para enviar logs)
  filebeat:
    image: docker.elastic.co/beats/filebeat:8.5.0
    container_name: filebeat
    user: root
    volumes:
      - ./elk/filebeat/filebeat.yml:/usr/share/filebeat/filebeat.yml:ro
      - ./storage/logs:/var/log/laravel:ro
      - /var/lib/docker/containers:/var/lib/docker/containers:ro
      - /var/run/docker.sock:/var/run/docker.sock:ro
    command: filebeat -e -strict.perms=false
    networks:
      - elk
    depends_on:
      - elasticsearch
      - logstash

networks:
  elk:
    driver: bridge

volumes:
  elasticsearch-data:
```

```ruby
# elk/logstash/pipeline/laravel.conf
input {
  file {
    path => "/var/log/laravel/laravel.log"
    start_position => "beginning"
    sincedb_path => "/dev/null"
    codec => multiline {
      pattern => "^\[\d{4}-\d{2}-\d{2}"
      negate => true
      what => "previous"
    }
  }
}

filter {
  # Parse dos logs do Laravel
  grok {
    match => {
      "message" => "\[%{TIMESTAMP_ISO8601:timestamp}\] %{DATA:environment}\.%{DATA:level}: %{GREEDYDATA:log_message}"
    }
  }

  # Tentar parsear o JSON do context
  if [log_message] =~ /\{.*\}/ {
    grok {
      match => {
        "log_message" => "%{DATA:message} %{GREEDYDATA:context_json}"
      }
    }

    if [context_json] {
      json {
        source => "context_json"
        target => "context"
        remove_field => ["context_json"]
      }
    }
  }

  # Data
  date {
    match => ["timestamp", "ISO8601"]
    target => "@timestamp"
  }

  # Adicionar metadados
  mutate {
    add_field => {
      "application" => "laravel"
      "server" => "%{host}"
    }
    remove_field => ["timestamp", "host"]
  }
}

output {
  elasticsearch {
    hosts => ["elasticsearch:9200"]
    index => "laravel-logs-%{+YYYY.MM.dd}"
  }

  # Para debug
  stdout {
    codec => rubydebug
  }
}
```

```yaml
# elk/filebeat/filebeat.yml
filebeat.inputs:
  - type: log
    enabled: true
    paths:
      - /var/log/laravel/*.log
    multiline.pattern: '^\[\d{4}-\d{2}-\d{2}'
    multiline.negate: true
    multiline.match: after
    fields:
      app: laravel
      environment: production

output.elasticsearch:
  hosts: ["elasticsearch:9200"]
  index: "laravel-logs-%{+yyyy.MM.dd}"

setup.kibana:
  host: "kibana:5601"

logging.level: info
```

```php
// config/logging.php - formato JSON para parse fácil
'json' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => 'debug',
    'days' => 14,
    'formatter' => Monolog\Formatter\JsonFormatter::class,
],

// Atualizar no .env
LOG_CHANNEL=json
```

```bash
# Subir o ELK Stack
docker-compose -f docker-compose.elk.yml up -d

# Checar se o Elasticsearch está no ar
curl http://localhost:9200/_cluster/health

# Abrir o Kibana
# http://localhost:5601

# Criar o index pattern no Kibana:
# 1. Management → Index Patterns
# 2. Create index pattern: "laravel-logs-*"
# 3. Time field: @timestamp
# 4. Discover → selecionar laravel-logs-*

# Exemplos de busca no Kibana:
# - level: "error"
# - context.user_id: 123
# - message: "Pagamento falhou"
# - @timestamp: [now-1h TO now]

# Criar o dashboard:
# 1. Dashboard → Create
# 2. Add visualization
# 3. Metrics:
#    - Error count: level:error
#    - Request count by endpoint: context.endpoint
#    - Response time histogram: context.response_time
```

```php
// app/Http/Middleware/LogRequests.php - logar todas as requests
public function handle($request, Closure $next)
{
    $startTime = microtime(true);

    $response = $next($request);

    $duration = (microtime(true) - $startTime) * 1000;

    Log::info('Request HTTP', [
        'method' => $request->method(),
        'url' => $request->fullUrl(),
        'ip' => $request->ip(),
        'user_id' => auth()->id(),
        'status' => $response->status(),
        'duration_ms' => round($duration, 2),
    ]);

    return $response;
}
```
</details>

### Exercício 2: Implemente health checks completos

**Enunciado:** Crie um endpoint de health check completo que verifica os componentes críticos e devolve o status detalhado.

<details>
<summary>Solução</summary>

```php
// app/Services/HealthCheckService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class HealthCheckService
{
    private array $checks = [];
    private bool $isHealthy = true;

    public function runAll(): array
    {
        $this->checkDatabase();
        $this->checkCache();
        $this->checkRedis();
        $this->checkQueue();
        $this->checkStorage();
        $this->checkExternalServices();
        $this->checkDiskSpace();
        $this->checkMemory();

        return [
            'status' => $this->isHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $this->checks,
            'version' => config('app.version'),
            'environment' => config('app.env'),
        ];
    }

    private function checkDatabase(): void
    {
        $this->runCheck('database', function () {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
                'connection' => config('database.default'),
            ];
        });
    }

    private function checkCache(): void
    {
        $this->runCheck('cache', function () {
            $testKey = 'health_check_' . time();
            $testValue = 'test';

            Cache::put($testKey, $testValue, 10);
            $result = Cache::get($testKey) === $testValue;
            Cache::forget($testKey);

            if (!$result) {
                throw new \Exception('Falha de escrita/leitura no cache');
            }

            return [
                'status' => 'ok',
                'driver' => config('cache.default'),
            ];
        });
    }

    private function checkRedis(): void
    {
        $this->runCheck('redis', function () {
            $startTime = microtime(true);
            Redis::ping();
            $latency = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        });
    }

    private function checkQueue(): void
    {
        $this->runCheck('queue', function () {
            $connection = config('queue.default');
            $size = Queue::size();

            // Aviso se a queue estiver grande demais
            $warning = $size > 1000 ? 'Tamanho da queue está alto' : null;

            return [
                'status' => $size < 10000 ? 'ok' : 'degraded',
                'connection' => $connection,
                'size' => $size,
                'warning' => $warning,
            ];
        });
    }

    private function checkStorage(): void
    {
        $this->runCheck('storage', function () {
            $testFile = 'health_check.txt';
            $testContent = 'health check ' . time();

            Storage::put($testFile, $testContent);
            $readContent = Storage::get($testFile);
            Storage::delete($testFile);

            if ($readContent !== $testContent) {
                throw new \Exception('Falha de escrita/leitura no storage');
            }

            return [
                'status' => 'ok',
                'disk' => config('filesystems.default'),
                'writable' => is_writable(storage_path()),
            ];
        });
    }

    private function checkExternalServices(): void
    {
        $this->runCheck('external_services', function () {
            $services = [];

            // Checar a API
            if (config('services.payment.enabled')) {
                try {
                    $response = Http::timeout(5)->get(config('services.payment.health_url'));
                    $services['payment_gateway'] = [
                        'status' => $response->successful() ? 'ok' : 'down',
                        'latency_ms' => $response->transferStats?->getTransferTime() * 1000 ?? null,
                    ];
                } catch (\Exception $e) {
                    $services['payment_gateway'] = [
                        'status' => 'down',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return [
                'status' => 'ok',
                'services' => $services,
            ];
        });
    }

    private function checkDiskSpace(): void
    {
        $this->runCheck('disk_space', function () {
            $path = storage_path();
            $totalSpace = disk_total_space($path);
            $freeSpace = disk_free_space($path);
            $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            $status = 'ok';
            if ($usedPercent > 90) {
                $status = 'critical';
            } elseif ($usedPercent > 80) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 2),
            ];
        });
    }

    private function checkMemory(): void
    {
        $this->runCheck('memory', function () {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $usedPercent = ($memoryUsage / $memoryLimit) * 100;

            $status = 'ok';
            if ($usedPercent > 90) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'used_mb' => round($memoryUsage / 1024 / 1024, 2),
                'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'used_percent' => round($usedPercent, 2),
            ];
        });
    }

    private function runCheck(string $name, callable $check): void
    {
        try {
            $result = $check();
            $this->checks[$name] = $result;

            // Se o status não for 'ok', marca como unhealthy
            if (isset($result['status']) && $result['status'] !== 'ok') {
                $this->isHealthy = false;
            }
        } catch (\Exception $e) {
            $this->checks[$name] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
            $this->isHealthy = false;
        }
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
```

```php
// routes/web.php
use App\Services\HealthCheckService;

Route::get('/health', function (HealthCheckService $healthCheck) {
    $result = $healthCheck->runAll();

    return response()->json($result, $result['status'] === 'healthy' ? 200 : 503);
});

// Health check leve (só database)
Route::get('/health/liveness', function () {
    try {
        DB::connection()->getPdo();
        return response()->json(['status' => 'alive'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'dead', 'error' => $e->getMessage()], 503);
    }
});

// Readiness check (para Kubernetes)
Route::get('/health/readiness', function () {
    try {
        // Checar dependências críticas
        DB::connection()->getPdo();
        Cache::get('test');

        return response()->json(['status' => 'ready'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => 'not_ready', 'error' => $e->getMessage()], 503);
    }
});
```

```bash
# Uso

# Health check completo
curl http://localhost/health | jq

# Resultado:
# {
#   "status": "healthy",
#   "timestamp": "2024-01-15T10:30:00+00:00",
#   "checks": {
#     "database": {
#       "status": "ok",
#       "latency_ms": 2.5,
#       "connection": "mysql"
#     },
#     "cache": {
#       "status": "ok",
#       "driver": "redis"
#     },
#     "queue": {
#       "status": "ok",
#       "size": 45
#     },
#     "disk_space": {
#       "status": "ok",
#       "used_percent": 65.2
#     }
#   }
# }

# Liveness check (Kubernetes)
curl http://localhost/health/liveness

# Readiness check (Kubernetes)
curl http://localhost/health/readiness
```

```yaml
# Deployment Kubernetes com health checks
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-app
spec:
  replicas: 3
  template:
    spec:
      containers:
      - name: app
        image: myapp:latest
        ports:
        - containerPort: 80

        # Liveness probe — reinicia se não responder
        livenessProbe:
          httpGet:
            path: /health/liveness
            port: 80
          initialDelaySeconds: 30
          periodSeconds: 10
          timeoutSeconds: 5
          failureThreshold: 3

        # Readiness probe — não manda tráfego se não estiver pronto
        readinessProbe:
          httpGet:
            path: /health/readiness
            port: 80
          initialDelaySeconds: 10
          periodSeconds: 5
          timeoutSeconds: 3
          successThreshold: 1
          failureThreshold: 3
```

```php
// tests/Feature/HealthCheckTest.php
class HealthCheckTest extends TestCase
{
    /** @test */
    public function health_endpoint_returns_healthy_status()
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson(['status' => 'healthy']);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks' => [
                'database',
                'cache',
                'queue',
            ],
        ]);
    }

    /** @test */
    public function health_endpoint_returns_unhealthy_when_database_down()
    {
        // Mock da conexão do banco para falhar
        DB::shouldReceive('connection')->andThrow(new \Exception('Connection refused'));

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJson(['status' => 'unhealthy']);
    }
}
```
</details>

### Exercício 3: Configure alerting com notificação automática

**Enunciado:** Crie um sistema de alerting que avisa no Slack em erro crítico, carga alta e downtime.

<details>
<summary>Solução</summary>

```php
// app/Services/AlertService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AlertService
{
    private const ALERT_COOLDOWN = 300; // 5 minutos entre alertas

    public function criticalError(string $message, array $context = []): void
    {
        $alertKey = 'alert:critical:' . md5($message);

        if ($this->shouldSendAlert($alertKey)) {
            $this->sendSlackAlert([
                'level' => 'critical',
                'emoji' => ':fire:',
                'color' => 'danger',
                'title' => 'Erro crítico',
                'message' => $message,
                'context' => $context,
            ]);

            $this->sendEmailAlert($message, $context, 'critical');
            $this->markAlertSent($alertKey);
        }

        Log::critical($message, $context);
    }

    public function highLoad(array $metrics): void
    {
        $alertKey = 'alert:high_load';

        if ($this->shouldSendAlert($alertKey)) {
            $this->sendSlackAlert([
                'level' => 'warning',
                'emoji' => ':warning:',
                'color' => 'warning',
                'title' => 'Carga alta no servidor',
                'message' => 'Carga do servidor acima do limite',
                'context' => $metrics,
            ]);

            $this->markAlertSent($alertKey);
        }
    }

    public function serviceDown(string $service, string $error): void
    {
        $alertKey = "alert:service_down:$service";

        if ($this->shouldSendAlert($alertKey)) {
            $this->sendSlackAlert([
                'level' => 'critical',
                'emoji' => ':x:',
                'color' => 'danger',
                'title' => "Serviço fora: $service",
                'message' => $error,
                'context' => [
                    'service' => $service,
                    'timestamp' => now()->toIso8601String(),
                ],
            ]);

            $this->sendEmailAlert("Serviço fora: $service", ['error' => $error], 'critical');
            $this->markAlertSent($alertKey);
        }
    }

    public function slowQuery(string $query, float $time): void
    {
        $alertKey = 'alert:slow_query';

        if ($this->shouldSendAlert($alertKey, 60)) {
            $this->sendSlackAlert([
                'level' => 'warning',
                'emoji' => ':snail:',
                'color' => 'warning',
                'title' => 'Query lenta no banco',
                'message' => "Query levou {$time}ms",
                'context' => [
                    'query' => substr($query, 0, 200),
                    'time_ms' => $time,
                ],
            ]);

            $this->markAlertSent($alertKey, 60);
        }
    }

    private function shouldSendAlert(string $key, int $cooldown = self::ALERT_COOLDOWN): bool
    {
        return !Cache::has($key);
    }

    private function markAlertSent(string $key, int $cooldown = self::ALERT_COOLDOWN): void
    {
        Cache::put($key, true, $cooldown);
    }

    private function sendSlackAlert(array $alert): void
    {
        $webhookUrl = config('services.slack.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        $payload = [
            'username' => 'Laravel Alert',
            'icon_emoji' => $alert['emoji'],
            'attachments' => [
                [
                    'color' => $alert['color'],
                    'title' => $alert['title'],
                    'text' => $alert['message'],
                    'fields' => $this->formatContextFields($alert['context'] ?? []),
                    'footer' => config('app.name'),
                    'footer_icon' => 'https://laravel.com/img/favicon/favicon.ico',
                    'ts' => now()->timestamp,
                ],
            ],
        ];

        try {
            Http::post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::error('Falha ao enviar alerta no Slack', [
                'error' => $e->getMessage(),
                'alert' => $alert,
            ]);
        }
    }

    private function sendEmailAlert(string $message, array $context, string $level): void
    {
        $admins = config('monitoring.admin_emails', []);

        foreach ($admins as $email) {
            Mail::to($email)->send(new \App\Mail\AlertMail($message, $context, $level));
        }
    }

    private function formatContextFields(array $context): array
    {
        $fields = [];

        foreach ($context as $key => $value) {
            $fields[] = [
                'title' => ucfirst(str_replace('_', ' ', $key)),
                'value' => is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value,
                'short' => strlen($value) < 40,
            ];
        }

        return $fields;
    }
}
```

```php
// app/Listeners/MonitorApplicationHealth.php
<?php

namespace App\Listeners;

use App\Services\AlertService;
use Illuminate\Support\Facades\DB;

class MonitorApplicationHealth
{
    public function __construct(private AlertService $alert)
    {
    }

    public function handle($event): void
    {
        $this->checkDatabaseConnection();
        $this->checkQueueSize();
        $this->checkDiskSpace();
        $this->checkMemoryUsage();
    }

    private function checkDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $this->alert->serviceDown('Database', $e->getMessage());
        }
    }

    private function checkQueueSize(): void
    {
        $size = Queue::size();

        if ($size > 5000) {
            $this->alert->highLoad([
                'queue_size' => $size,
                'threshold' => 5000,
            ]);
        }
    }

    private function checkDiskSpace(): void
    {
        $path = storage_path();
        $totalSpace = disk_total_space($path);
        $freeSpace = disk_free_space($path);
        $usedPercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

        if ($usedPercent > 90) {
            $this->alert->criticalError('Espaço em disco crítico', [
                'used_percent' => round($usedPercent, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            ]);
        }
    }

    private function checkMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $usedPercent = ($memoryUsage / $memoryLimit) * 100;

        if ($usedPercent > 90) {
            $this->alert->highLoad([
                'memory_used_percent' => round($usedPercent, 2),
                'memory_used_mb' => round($memoryUsage / 1024 / 1024, 2),
            ]);
        }
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }

        return $value;
    }
}
```

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \Illuminate\Database\Events\QueryExecuted::class => [
        MonitorSlowQueries::class,
    ],
    \Illuminate\Queue\Events\JobFailed::class => [
        NotifyJobFailed::class,
    ],
];
```

```php
// app/Listeners/MonitorSlowQueries.php
<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use App\Services\AlertService;

class MonitorSlowQueries
{
    public function __construct(private AlertService $alert)
    {
    }

    public function handle(QueryExecuted $event): void
    {
        $threshold = config('monitoring.slow_query_threshold', 1000); // 1 segundo

        if ($event->time > $threshold) {
            $this->alert->slowQuery($event->sql, $event->time);
        }
    }
}
```

```php
// app/Listeners/NotifyJobFailed.php
<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use App\Services\AlertService;

class NotifyJobFailed
{
    public function __construct(private AlertService $alert)
    {
    }

    public function handle(JobFailed $event): void
    {
        $this->alert->criticalError('Job falhou', [
            'job' => $event->job->resolveName(),
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'exception' => $event->exception->getMessage(),
        ]);
    }
}
```

```php
// app/Console/Commands/MonitorHealth.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\HealthCheckService;
use App\Services\AlertService;

class MonitorHealth extends Command
{
    protected $signature = 'monitor:health';
    protected $description = 'Monitora a saúde do app e envia alertas';

    public function handle(HealthCheckService $healthCheck, AlertService $alert): void
    {
        $result = $healthCheck->runAll();

        if ($result['status'] !== 'healthy') {
            $failedChecks = collect($result['checks'])
                ->filter(fn($check) => $check['status'] !== 'ok')
                ->toArray();

            $alert->criticalError('Health check falhou', [
                'failed_checks' => array_keys($failedChecks),
                'details' => $failedChecks,
            ]);
        }

        $this->info('Health check concluído: ' . $result['status']);
    }
}
```

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    // Checar health a cada 5 minutos
    $schedule->command('monitor:health')->everyFiveMinutes();

    // Limpar alerts antigos
    $schedule->call(function () {
        Cache::tags('alerts')->flush();
    })->daily();
}
```

```php
// config/monitoring.php
return [
    'admin_emails' => env('MONITORING_ADMIN_EMAILS', '').explode(','),

    'slow_query_threshold' => env('MONITORING_SLOW_QUERY_MS', 1000),

    'thresholds' => [
        'disk_usage_percent' => 90,
        'memory_usage_percent' => 90,
        'queue_size' => 5000,
    ],
];
```

```bash
# .env
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
MONITORING_ADMIN_EMAILS=admin@example.com,ops@example.com
MONITORING_SLOW_QUERY_MS=1000

# Rodar o monitoramento na mão
php artisan monitor:health

# Ou via cron (já está no schedule)
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

```php
// Uso no código
use App\Services\AlertService;

// No controller ou no service
public function processPayment(AlertService $alert)
{
    try {
        $payment = $this->gateway->charge($amount);
    } catch (\Exception $e) {
        $alert->criticalError('Payment gateway falhou', [
            'amount' => $amount,
            'error' => $e->getMessage(),
        ]);

        throw $e;
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
