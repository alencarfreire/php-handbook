# 18.4 Service Discovery

> **TL;DR**
> Service Discovery é o mecanismo para achar microsserviços automaticamente num sistema distribuído. Tipos: Client-Side (o cliente acha o serviço no Registry) e Server-Side (o Load Balancer faz o discovery). Consul: registro via HTTP API, health checks, client-side load balancing. Kubernetes: DNS-based discovery automático. Health checks: liveness (vivo?) e readiness (pronto?). Fallback para config estático se o Registry cair.

## Conteúdo
- [O que é](#o-que-é)
- [Tipos de Service Discovery](#tipos-de-service-discovery)
- [Consul (HashiCorp)](#consul-hashicorp)
- [Kubernetes Service Discovery](#kubernetes-service-discovery)
- [Eureka (Netflix)](#eureka-netflix)
- [Laravel Package para Service Discovery](#laravel-package-para-service-discovery)
- [Caching Service Discovery](#caching-service-discovery)
- [Graceful Degradation](#graceful-degradation)
- [Health Checks](#health-checks)
- [Best Practices](#best-practices)
- [Alternativas](#alternativas)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Service Discovery:**
Mecanismo para achar automaticamente o endereço de rede dos serviços num sistema distribuído.

**Problema:**
Em arquitetura de microsserviços os serviços sobem e descem o tempo todo. IP e porta mudam.

```
Antes (monolito):
Order Service → http://payment-service:8080

Agora (microsserviços):
Order Service → Payment Service (onde está?)
                ├ instance-1: 10.0.1.5:8080
                ├ instance-2: 10.0.1.7:8080 (autoscaling adicionou)
                └ instance-3: 10.0.1.9:8080
```

**Para quê:**
- Roteamento dinâmico
- Autoscaling (adicionar/remover instances)
- Health checks
- Load balancing

---

## Tipos de Service Discovery

### 1. Client-Side Discovery

**O cliente acha o serviço sozinho:**

```
1. Client → Service Registry: "Onde está o Payment Service?"
2. Service Registry → Client: [10.0.1.5:8080, 10.0.1.7:8080]
3. Client escolhe a instance (client-side load balancing)
4. Client → Payment Service instance
```

**Exemplos:** Consul, Eureka

---

### 2. Server-Side Discovery

**O Load Balancer acha o serviço:**

```
1. Client → Load Balancer
2. Load Balancer → Service Registry: "Onde está o Payment Service?"
3. Service Registry → Load Balancer: [instances]
4. Load Balancer escolhe a instance
5. Load Balancer → Payment Service instance
```

**Exemplos:** Kubernetes Service, AWS ELB, Nginx

---

## Consul (HashiCorp)

**O que é:**
Service mesh com service discovery, health checks, KV store.

**Instalação:**

```bash
docker run -d --name=consul \
  -p 8500:8500 \
  -p 8600:8600/udp \
  consul agent -server -ui -bootstrap-expect=1 -client=0.0.0.0
```

**Web UI:** http://localhost:8500

---

### Registro do serviço

**Laravel Service Provider:**

```php
// app/Providers/ConsulServiceProvider.php
class ConsulServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerService();
    }

    private function registerService()
    {
        $serviceId = config('app.name') . '-' . gethostname();

        $data = [
            'ID' => $serviceId,
            'Name' => config('app.name'),
            'Address' => gethostname(),
            'Port' => (int) config('app.port', 8000),
            'Check' => [
                'HTTP' => url('/health'),
                'Interval' => '10s',
                'Timeout' => '5s',
            ],
        ];

        Http::put('http://consul:8500/v1/agent/service/register', $data);

        // Deregister no shutdown
        register_shutdown_function(function () use ($serviceId) {
            Http::put("http://consul:8500/v1/agent/service/deregister/{$serviceId}");
        });
    }
}
```

**Health Check Endpoint:**

```php
// routes/web.php
Route::get('/health', function () {
    // Checar banco, Redis, etc.
    try {
        DB::connection()->getPdo();
        Redis::ping();

        return response()->json(['status' => 'healthy']);
    } catch (Exception $e) {
        return response()->json(['status' => 'unhealthy'], 503);
    }
});
```

---

### Service Discovery (obter o serviço)

```php
class ConsulServiceDiscovery
{
    public function getService(string $serviceName): ?string
    {
        // Pegar todas as instances healthy
        $response = Http::get("http://consul:8500/v1/health/service/{$serviceName}", [
            'passing' => true,  // só healthy
        ]);

        $instances = $response->json();

        if (empty($instances)) {
            throw new ServiceNotFoundException("Serviço {$serviceName} não encontrado");
        }

        // Client-side load balancing (random)
        $instance = $instances[array_rand($instances)];

        $address = $instance['Service']['Address'];
        $port = $instance['Service']['Port'];

        return "http://{$address}:{$port}";
    }
}

// Uso
$discovery = new ConsulServiceDiscovery();
$paymentUrl = $discovery->getService('payment-service');

$response = Http::post("{$paymentUrl}/api/charge", [
    'amount' => 100,
]);
```

---

### Load Balancing Strategies

**Round Robin:**

```php
class ConsulServiceDiscovery
{
    private array $counters = [];

    public function getService(string $serviceName): string
    {
        $instances = $this->getInstances($serviceName);

        // Round Robin
        if (!isset($this->counters[$serviceName])) {
            $this->counters[$serviceName] = 0;
        }

        $index = $this->counters[$serviceName] % count($instances);
        $this->counters[$serviceName]++;

        $instance = $instances[$index];

        return "http://{$instance['address']}:{$instance['port']}";
    }
}
```

**Weighted (por carga):**

```php
public function getService(string $serviceName): string
{
    $instances = $this->getInstances($serviceName);

    // Escolher a instance com menor carga
    usort($instances, fn($a, $b) => $a['load'] <=> $b['load']);

    $instance = $instances[0];

    return "http://{$instance['address']}:{$instance['port']}";
}
```

---

## Kubernetes Service Discovery

**Kubernetes DNS:**

```yaml
# payment-service deployment
apiVersion: v1
kind: Service
metadata:
  name: payment-service
spec:
  selector:
    app: payment
  ports:
    - port: 80
      targetPort: 8000
```

**DNS name:** `payment-service.default.svc.cluster.local`

**Laravel:**

```php
// Só use o nome do serviço
$response = Http::post('http://payment-service/api/charge', [
    'amount' => 100,
]);

// Kubernetes resolve o DNS e faz o balance sozinho
```

**Como funciona:**
1. Kubernetes cria o registro DNS do Service
2. O registro DNS aponta para o ClusterIP
3. kube-proxy faz o balance entre os Pods

---

## Eureka (Netflix)

**Ecossistema Spring Boot, para Java/Kotlin.**

**Não existe cliente PHP, mas dá para usar a REST API:**

```php
// Registro no Eureka
Http::post('http://eureka:8761/eureka/apps/PAYMENT-SERVICE', [
    'instance' => [
        'hostName' => gethostname(),
        'app' => 'PAYMENT-SERVICE',
        'ipAddr' => '10.0.1.5',
        'port' => ['$' => 8000, '@enabled' => true],
        'healthCheckUrl' => url('/health'),
    ],
]);

// Pegar as instances
$response = Http::get('http://eureka:8761/eureka/apps/PAYMENT-SERVICE');
$instances = $response->json()['application']['instance'];
```

---

## Laravel Package para Service Discovery

**Composer:**

```bash
composer require illuminate/http-client
```

**Service Provider:**

```php
class ServiceDiscoveryServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ServiceDiscovery::class, function () {
            $driver = config('services.discovery.driver');

            return match ($driver) {
                'consul' => new ConsulServiceDiscovery(),
                'kubernetes' => new KubernetesServiceDiscovery(),
                'static' => new StaticServiceDiscovery(),
                default => throw new Exception("Driver desconhecido: {$driver}"),
            };
        });
    }
}

// config/services.php
'discovery' => [
    'driver' => env('SERVICE_DISCOVERY_DRIVER', 'consul'),
    'consul' => [
        'host' => env('CONSUL_HOST', 'consul:8500'),
    ],
],
```

**Uso:**

```php
class PaymentService
{
    public function __construct(private ServiceDiscovery $discovery) {}

    public function charge(int $amount): Payment
    {
        $url = $this->discovery->getService('payment-service');

        $response = Http::post("{$url}/api/charge", ['amount' => $amount]);

        return new Payment($response->json());
    }
}
```

---

## Caching Service Discovery

**Problema:**
Cada request no Consul/Eureka deixa o sistema mais lento.

**Solução: Cache**

```php
class CachedServiceDiscovery implements ServiceDiscovery
{
    public function __construct(
        private ServiceDiscovery $discovery,
        private CacheInterface $cache
    ) {}

    public function getService(string $serviceName): string
    {
        $cacheKey = "service_discovery:{$serviceName}";

        return $this->cache->remember($cacheKey, 60, function () use ($serviceName) {
            return $this->discovery->getService($serviceName);
        });
    }
}
```

---

## Graceful Degradation

**Fallback quando o Service Registry está indisponível:**

```php
class ResilientServiceDiscovery implements ServiceDiscovery
{
    public function getService(string $serviceName): string
    {
        try {
            // Tenta pelo Consul
            return $this->consulDiscovery->getService($serviceName);

        } catch (ConsulUnavailableException $e) {
            // Fallback: config estático
            Log::warning("Consul indisponível, usando config estático");

            return config("services.static.{$serviceName}");
        }
    }
}

// config/services.php
'static' => [
    'payment-service' => 'http://payment.internal:8080',
    'user-service' => 'http://user.internal:8080',
],
```

---

## Health Checks

**Tipos:**

### 1. Liveness Probe

**Checagem: "o serviço está vivo?"**

```php
// /health/live
Route::get('/health/live', function () {
    return response()->json(['status' => 'alive']);
});
```

### 2. Readiness Probe

**Checagem: "o serviço está pronto para receber requests?"**

```php
// /health/ready
Route::get('/health/ready', function () {
    try {
        // Checar as dependências
        DB::connection()->getPdo();
        Redis::ping();

        return response()->json(['status' => 'ready']);
    } catch (Exception $e) {
        return response()->json(['status' => 'not ready'], 503);
    }
});
```

---

## Best Practices

```
✓ Health checks com dependências (banco, Redis, etc.)
✓ Graceful shutdown (deregister antes de parar)
✓ Client-side caching (não spammar o Service Registry)
✓ Fallback para config estático
✓ Retry com exponential backoff
✓ Load balancing strategy (round robin, weighted)
✓ Monitoring: eventos de registro/deregistration
✓ TTL nos health checks
```

---

## Alternativas

**Para casos simples:**

### 1. Env Variables

```env
PAYMENT_SERVICE_URL=http://payment-service:8080
USER_SERVICE_URL=http://user-service:8080
```

```php
Http::post(config('services.payment.url') . '/api/charge');
```

**Prós:**
- ✅ Simples
- ✅ Sem dependências

**Contras:**
- ❌ Sem autoscaling
- ❌ Sem health checks
- ❌ Precisa de redeploy quando muda

---

### 2. DNS Round Robin

```
payment-service.example.com → 10.0.1.5
payment-service.example.com → 10.0.1.7
payment-service.example.com → 10.0.1.9
```

```php
Http::post('http://payment-service.example.com/api/charge');
```

**Prós:**
- ✅ Load balancing simples

**Contras:**
- ❌ Sem health checks (pode rotear para instance morta)
- ❌ Problemas de DNS caching

---

## Exercícios práticos

<details>
<summary>Exercício 1: Consul Service Registration</summary>

**Enunciado:**
Crie um Service Provider para registrar o app Laravel no Consul automaticamente na subida.

**Solução:**

```php
// app/Providers/ConsulServiceProvider.php
namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class ConsulServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!app()->environment('local')) {
            $this->registerInConsul();
            $this->deregisterOnShutdown();
        }
    }

    private function registerInConsul(): void
    {
        $serviceId = config('app.name') . '-' . gethostname();
        $port = config('app.port', 8000);

        $data = [
            'ID' => $serviceId,
            'Name' => config('app.name'),
            'Address' => gethostbyname(gethostname()),
            'Port' => (int) $port,
            'Check' => [
                'HTTP' => url('/health/ready'),
                'Interval' => '10s',
                'Timeout' => '5s',
                'DeregisterCriticalServiceAfter' => '30s',
            ],
            'Tags' => ['laravel', 'api', config('app.env')],
        ];

        try {
            Http::put('http://consul:8500/v1/agent/service/register', $data);
            logger()->info("Registrado no Consul como {$serviceId}");
        } catch (\Exception $e) {
            logger()->error("Falha ao registrar no Consul: {$e->getMessage()}");
        }
    }

    private function deregisterOnShutdown(): void
    {
        $serviceId = config('app.name') . '-' . gethostname();

        register_shutdown_function(function () use ($serviceId) {
            try {
                Http::put("http://consul:8500/v1/agent/service/deregister/{$serviceId}");
                logger()->info("Deregistrado do Consul: {$serviceId}");
            } catch (\Exception $e) {
                logger()->error("Falha ao fazer deregister no Consul: {$e->getMessage()}");
            }
        });
    }
}

// config/app.php — adicionar em providers
'providers' => [
    // ...
    App\Providers\ConsulServiceProvider::class,
],
```
</details>

<details>
<summary>Exercício 2: Service Discovery com cache</summary>

**Enunciado:**
Implemente um cliente de Service Discovery com cache dos resultados e round-robin load balancing.

**Solução:**

```php
class ConsulServiceDiscovery
{
    private array $counters = [];

    public function getServiceUrl(string $serviceName): string
    {
        $cacheKey = "service_discovery:{$serviceName}";

        // Cache de 60 segundos
        $instances = Cache::remember($cacheKey, 60, function () use ($serviceName) {
            return $this->fetchHealthyInstances($serviceName);
        });

        if (empty($instances)) {
            throw new ServiceNotFoundException("Serviço {$serviceName} não encontrado");
        }

        // Round-robin load balancing
        if (!isset($this->counters[$serviceName])) {
            $this->counters[$serviceName] = 0;
        }

        $index = $this->counters[$serviceName] % count($instances);
        $this->counters[$serviceName]++;

        $instance = $instances[$index];

        return "http://{$instance['address']}:{$instance['port']}";
    }

    private function fetchHealthyInstances(string $serviceName): array
    {
        try {
            $response = Http::timeout(3)->get("http://consul:8500/v1/health/service/{$serviceName}", [
                'passing' => true, // só instances healthy
            ]);

            return collect($response->json())->map(function ($item) {
                return [
                    'address' => $item['Service']['Address'],
                    'port' => $item['Service']['Port'],
                ];
            })->toArray();

        } catch (\Exception $e) {
            logger()->error("Falha ao buscar o serviço no Consul: {$e->getMessage()}");
            return [];
        }
    }
}

// Uso
$discovery = app(ConsulServiceDiscovery::class);
$url = $discovery->getServiceUrl('payment-service');
$response = Http::post("{$url}/api/charge", ['amount' => 100]);
```
</details>

<details>
<summary>Exercício 3: Health Check Endpoints</summary>

**Enunciado:**
Crie endpoints de health check para liveness e readiness.

**Solução:**

```php
// routes/web.php
Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);

// app/Http/Controllers/HealthController.php
class HealthController extends Controller
{
    // Liveness: o app está vivo?
    public function live()
    {
        return response()->json([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // Readiness: pronto para receber requests?
    public function ready()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = collect($checks)->every(fn($check) => $check === true);

        return response()->json([
            'status' => $allHealthy ? 'ready' : 'not ready',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            logger()->error("Health check do banco falhou: {$e->getMessage()}");
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            logger()->error("Health check do Redis falhou: {$e->getMessage()}");
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            // Checar se o queue worker está rodando
            return Cache::store('redis')->get('queue:heartbeat', 0) > (time() - 60);
        } catch (\Exception $e) {
            return false;
        }
    }
}
```
</details>

---

## Na entrevista

> "Service Discovery é o mecanismo para achar serviços automaticamente em microsserviços. Tipos: Client-Side (o cliente pega a lista de instances no Registry e escolhe) e Server-Side (o Load Balancer faz o discovery). Consul: registro via HTTP API, health checks, client-side load balancing (random, round robin, weighted). Kubernetes: DNS-based discovery, o Service resolve sozinho no ClusterIP. Laravel: Service Provider registra o serviço na subida, deregister no shutdown, cache nos requests de discovery. Health checks: liveness (está vivo?), readiness (pronto para receber requests?). Best practices: caching, fallback para config estático, graceful shutdown, monitoring."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
