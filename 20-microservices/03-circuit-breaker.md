# 18.3 Circuit Breaker

> **TL;DR**
> Circuit Breaker protege contra falhas em cascata em microsserviços. 3 estados: CLOSED (funciona normal), OPEN (fail fast sem chamar o serviço), HALF-OPEN (testa se voltou). Se failures > threshold, vai para OPEN; depois do timeout tenta HALF-OPEN. Fallback strategies: valor default, dados em cache, degraded mode, queue. Bulkhead Pattern para isolar recursos.

## Conteúdo
- [O que é](#o-que-é)
- [O problema sem Circuit Breaker](#o-problema-sem-circuit-breaker)
- [Estados do Circuit Breaker](#estados-do-circuit-breaker)
- [Implementação em PHP](#implementação-em-php)
- [Laravel Package](#laravel-package)
- [Fallback Strategy](#fallback-strategy)
- [Bulkhead Pattern (complemento)](#bulkhead-pattern-complemento)
- [Monitoring](#monitoring)
- [Best Practices](#best-practices)
- [Circuit Breaker + Retry](#circuit-breaker--retry)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**Circuit Breaker:**
Padrão para proteger de falhas em cascata em sistemas distribuídos. Impede chamadas a um serviço fora do ar.

**Para quê:**
- Proteção contra cascade failures
- Fail fast em vez de timeout
- Dar tempo do serviço se recuperar
- Graceful degradation

**Analogia:**
Como o disjuntor na rede elétrica. Se der curto-circuito → o disjuntor abre o circuito.

---

## O problema sem Circuit Breaker

**Cenário:**

```
Order Service → Payment Service (lento/caiu)
   ↓
Cada request espera 30 segundos de timeout
   ↓
Threads travam
   ↓
Order Service para de responder
   ↓
Cascade failure: o sistema inteiro caiu
```

**Com Circuit Breaker:**

```
Order Service → Circuit Breaker → Payment Service
                    ↓
        se o Payment caiu:
        open circuit → fail fast
                    ↓
        Order Service continua funcionando
```

---

## Estados do Circuit Breaker

```
      ┌───────────┐
      │  CLOSED   │ ← Funcionamento normal
      │ (working) │
      └─────┬─────┘
            │
    Failures > threshold
            │
            ↓
      ┌───────────┐
      │   OPEN    │ ← Todos os requests fail fast
      │  (broken) │
      └─────┬─────┘
            │
      After timeout
            │
            ↓
      ┌───────────┐
      │ HALF-OPEN │ ← Tenta 1 request
      │  (testing)│
      └─────┬─────┘
            │
    ┌───────┴───────┐
Success           Failure
    │                │
    ↓                ↓
CLOSED            OPEN
```

### 1. CLOSED (Fechado)

**Funcionamento normal:**
- Requests passam para o serviço
- Conta as failures
- Se failures > threshold → OPEN

---

### 2. OPEN (Aberto)

**Serviço fora do ar:**
- Todos os requests fail fast (sem chamar o serviço)
- Não sobrecarrega o serviço caído
- Depois do timeout → HALF-OPEN

---

### 3. HALF-OPEN (Meio aberto)

**Teste de recuperação:**
- Deixa passar 1 request de teste
- Se success → CLOSED
- Se failure → OPEN

---

## Implementação em PHP

**Implementação básica:**

```php
class CircuitBreaker
{
    private string $service;
    private int $failureThreshold = 5;
    private int $timeout = 60;  // segundos

    public function call(callable $callback)
    {
        $state = $this->getState();

        // OPEN: fail fast
        if ($state === 'open') {
            if ($this->shouldAttemptReset()) {
                return $this->attemptReset($callback);
            }

            throw new CircuitBreakerOpenException("Serviço {$this->service} indisponível");
        }

        // CLOSED ou HALF-OPEN: tenta a chamada
        try {
            $result = $callback();

            // Success: zera as failures
            $this->onSuccess();

            return $result;

        } catch (Exception $e) {
            // Failure: incrementa o contador
            $this->onFailure();

            throw $e;
        }
    }

    private function getState(): string
    {
        $failures = Cache::get("circuit:{$this->service}:failures", 0);
        $openedAt = Cache::get("circuit:{$this->service}:opened_at");

        if ($failures >= $this->failureThreshold) {
            return 'open';
        }

        return 'closed';
    }

    private function shouldAttemptReset(): bool
    {
        $openedAt = Cache::get("circuit:{$this->service}:opened_at");

        if (!$openedAt) {
            return false;
        }

        return (time() - $openedAt) >= $this->timeout;
    }

    private function attemptReset(callable $callback)
    {
        try {
            $result = $callback();

            // Success: fecha o circuit
            $this->reset();

            return $result;

        } catch (Exception $e) {
            // Ainda falhando: reabre
            Cache::put("circuit:{$this->service}:opened_at", time(), 3600);

            throw $e;
        }
    }

    private function onSuccess(): void
    {
        $this->reset();
    }

    private function onFailure(): void
    {
        $failures = Cache::increment("circuit:{$this->service}:failures");

        if ($failures >= $this->failureThreshold) {
            Cache::put("circuit:{$this->service}:opened_at", time(), 3600);
        }
    }

    private function reset(): void
    {
        Cache::forget("circuit:{$this->service}:failures");
        Cache::forget("circuit:{$this->service}:opened_at");
    }
}
```

**Uso:**

```php
$circuitBreaker = new CircuitBreaker('payment-service');

try {
    $result = $circuitBreaker->call(function () {
        return Http::timeout(5)
            ->post('http://payment-service/api/charge', [...])
            ->throw()
            ->json();
    });

} catch (CircuitBreakerOpenException $e) {
    // Circuit OPEN: fail fast
    Log::warning('Serviço de pagamento indisponível');

    return response()->json([
        'error' => 'Serviço de pagamento temporariamente indisponível'
    ], 503);

} catch (Exception $e) {
    // Outro erro
    Log::error('Pagamento falhou', ['error' => $e->getMessage()]);

    return response()->json([
        'error' => 'Pagamento falhou'
    ], 500);
}
```

---

## Laravel Package

**Composer:**

```bash
composer require opis/circuit-breaker
```

**Uso:**

```php
use Opis\CircuitBreaker\CircuitBreaker;

$breaker = new CircuitBreaker();

$result = $breaker->call('payment-service', function () {
    return Http::post('http://payment-service/api/charge', [...])->json();
}, [
    'failure_threshold' => 5,
    'success_threshold' => 2,
    'timeout' => 60,
]);
```

---

## Fallback Strategy

**O que fazer quando o circuit está OPEN?**

### 1. Devolver valor default

```php
try {
    $recommendations = $circuitBreaker->call(function () {
        return Http::get('http://recommendation-service/api/products')->json();
    });
} catch (CircuitBreakerOpenException $e) {
    // Fallback: produtos populares
    $recommendations = Product::orderBy('views', 'desc')->limit(10)->get();
}
```

---

### 2. Cached data

```php
try {
    $user = $circuitBreaker->call(function () use ($userId) {
        return Http::get("http://user-service/api/users/{$userId}")->json();
    });
} catch (CircuitBreakerOpenException $e) {
    // Fallback: dados do cache
    $user = Cache::get("user:{$userId}");

    if (!$user) {
        throw new UserNotFoundException();
    }
}
```

---

### 3. Degraded mode

```php
try {
    $analytics = $circuitBreaker->call(function () {
        return Http::get('http://analytics-service/api/stats')->json();
    });
} catch (CircuitBreakerOpenException $e) {
    // Degraded: mostra a página sem analytics
    $analytics = null;
}

return view('dashboard', [
    'analytics' => $analytics,  // pode ser null
]);
```

---

### 4. Queue para retry

```php
try {
    $circuitBreaker->call(function () use ($email) {
        Http::post('http://notification-service/api/send', ['email' => $email]);
    });
} catch (CircuitBreakerOpenException $e) {
    // Fallback: coloca na queue
    SendEmailJob::dispatch($email)->delay(now()->addMinutes(5));
}
```

---

## Bulkhead Pattern (complemento)

**Problema:**
Um serviço lento come todas as threads.

**Solução:**
Isolar recursos por serviço.

```php
class BulkheadCircuitBreaker extends CircuitBreaker
{
    private int $maxConcurrentCalls = 10;

    public function call(callable $callback)
    {
        $currentCalls = Cache::get("bulkhead:{$this->service}:calls", 0);

        if ($currentCalls >= $this->maxConcurrentCalls) {
            throw new BulkheadFullException("Chamadas concorrentes demais para {$this->service}");
        }

        Cache::increment("bulkhead:{$this->service}:calls");

        try {
            return parent::call($callback);
        } finally {
            Cache::decrement("bulkhead:{$this->service}:calls");
        }
    }
}
```

---

## Monitoring

**Métricas:**

```php
class CircuitBreaker
{
    private function onSuccess(): void
    {
        $this->reset();

        // Métricas
        Metrics::increment("circuit_breaker.{$this->service}.success");
    }

    private function onFailure(): void
    {
        $failures = Cache::increment("circuit:{$this->service}:failures");

        // Métricas
        Metrics::increment("circuit_breaker.{$this->service}.failure");

        if ($failures >= $this->failureThreshold) {
            Cache::put("circuit:{$this->service}:opened_at", time(), 3600);

            // Alert
            Log::critical("Circuit breaker aberto para {$this->service}");
            Metrics::increment("circuit_breaker.{$this->service}.opened");
        }
    }
}
```

**Dashboard (Grafana):**

```
Métricas:
- circuit_breaker.{service}.success_rate (%)
- circuit_breaker.{service}.state (closed/open/half-open)
- circuit_breaker.{service}.failures (count)
```

---

## Best Practices

```
✓ Timeout: use um timeout razoável (3-5 segundos)
✓ Failure threshold: 5-10 failures
✓ Reset timeout: 30-60 segundos
✓ Exponential backoff: aumenta o timeout depois de cada falha
✓ Fallback: sempre tenha um plano B
✓ Monitoring: acompanhe o estado dos circuits
✓ Alerts: avise quando o circuit abre
✓ Granularity: um circuit por serviço/endpoint
```

---

## Circuit Breaker + Retry

**Combinação:**

```php
class ResilientHttpClient
{
    public function call(string $service, callable $callback, int $maxRetries = 3)
    {
        $circuitBreaker = new CircuitBreaker($service);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $circuitBreaker->call($callback);

            } catch (CircuitBreakerOpenException $e) {
                // Circuit OPEN: não faz retry
                throw $e;

            } catch (Exception $e) {
                if ($attempt === $maxRetries) {
                    throw $e;
                }

                // Exponential backoff
                sleep(pow(2, $attempt));
            }
        }
    }
}

// Uso
$client = new ResilientHttpClient();

$result = $client->call('payment-service', function () {
    return Http::post('http://payment-service/api/charge', [...])->json();
});
```

---

## Exercícios práticos

<details>
<summary>Exercício 1: Circuit Breaker básico</summary>

**Enunciado:**
Implemente um Circuit Breaker simples com três estados para proteger o Payment Service de falhas.

**Solução:**

```php
class SimpleCircuitBreaker
{
    private string $service;
    private int $failureThreshold = 3;
    private int $timeout = 30; // segundos

    public function __construct(string $service)
    {
        $this->service = $service;
    }

    public function call(callable $callback)
    {
        $failures = Cache::get("cb:{$this->service}:failures", 0);
        $openedAt = Cache::get("cb:{$this->service}:opened_at");

        // OPEN state
        if ($failures >= $this->failureThreshold) {
            if ($openedAt && (time() - $openedAt) >= $this->timeout) {
                // HALF-OPEN: tenta um request
                return $this->attemptReset($callback);
            }

            throw new CircuitBreakerOpenException("Serviço {$this->service} fora do ar");
        }

        // CLOSED: operação normal
        try {
            $result = $callback();
            Cache::forget("cb:{$this->service}:failures");
            return $result;
        } catch (Exception $e) {
            $failures = Cache::increment("cb:{$this->service}:failures");

            if ($failures >= $this->failureThreshold) {
                Cache::put("cb:{$this->service}:opened_at", time(), 3600);
                Log::error("Circuit breaker aberto para {$this->service}");
            }

            throw $e;
        }
    }

    private function attemptReset(callable $callback)
    {
        try {
            $result = $callback();
            // Success: fecha o circuit
            Cache::forget("cb:{$this->service}:failures");
            Cache::forget("cb:{$this->service}:opened_at");
            Log::info("Circuit breaker fechado para {$this->service}");
            return $result;
        } catch (Exception $e) {
            Cache::put("cb:{$this->service}:opened_at", time(), 3600);
            throw $e;
        }
    }
}

// Uso
$cb = new SimpleCircuitBreaker('payment-service');

try {
    $result = $cb->call(fn() => Http::timeout(5)->post('http://payment-service/api/charge')->json());
} catch (CircuitBreakerOpenException $e) {
    // Fallback
    return ['status' => 'queued'];
}
```
</details>

<details>
<summary>Exercício 2: Fallback Strategy com cache</summary>

**Enunciado:**
Implemente um Circuit Breaker com fallback para dados em cache quando o serviço estiver fora do ar.

**Solução:**

```php
class RecommendationService
{
    public function __construct(private CircuitBreaker $cb) {}

    public function getRecommendations(int $userId): array
    {
        $cacheKey = "recommendations:{$userId}";

        try {
            $recommendations = $this->cb->call(function () use ($userId) {
                return Http::timeout(3)
                    ->get("http://recommendation-service/api/users/{$userId}/recommendations")
                    ->json();
            });

            // Atualiza o cache se deu certo
            Cache::put($cacheKey, $recommendations, 3600);

            return $recommendations;

        } catch (CircuitBreakerOpenException $e) {
            // Fallback: dados do cache
            $cached = Cache::get($cacheKey);

            if ($cached) {
                Log::info("Usando recommendations em cache para o usuário {$userId}");
                return $cached;
            }

            // Fallback final: produtos populares
            return Product::orderBy('views', 'desc')->limit(10)->pluck('id')->toArray();
        }
    }
}
```
</details>

<details>
<summary>Exercício 3: Circuit Breaker com Retry e Exponential Backoff</summary>

**Enunciado:**
Combine Circuit Breaker com retry e exponential backoff.

**Solução:**

```php
class ResilientHttpClient
{
    public function call(string $service, callable $callback, int $maxRetries = 3)
    {
        $circuitBreaker = new CircuitBreaker($service);

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $circuitBreaker->call($callback);

            } catch (CircuitBreakerOpenException $e) {
                // Circuit OPEN: não faz retry
                Log::warning("Circuit breaker aberto para {$service}, pulando retry");
                throw $e;

            } catch (RequestException $e) {
                if ($attempt === $maxRetries) {
                    Log::error("Todas as {$maxRetries} tentativas falharam para {$service}");
                    throw $e;
                }

                // Exponential backoff: 2^attempt segundos
                $delay = pow(2, $attempt);
                Log::info("Retry {$attempt}/{$maxRetries} para {$service} em {$delay}s");
                sleep($delay);
            }
        }
    }
}

// Uso
$client = new ResilientHttpClient();

$result = $client->call('user-service', function () use ($userId) {
    return Http::timeout(5)->get("http://user-service/api/users/{$userId}")->throw()->json();
}, maxRetries: 3);
```
</details>

---

## Na entrevista

> "Circuit Breaker protege contra falhas em cascata. 3 estados: CLOSED (funciona), OPEN (fail fast), HALF-OPEN (testa se voltou). Parâmetros: failure threshold (5-10), timeout (30-60s). Transições: CLOSED → OPEN se failures > threshold, OPEN → HALF-OPEN depois do timeout, HALF-OPEN → CLOSED se success. Fallback strategies: valor default, dados em cache, degraded mode, queue para retry. Bulkhead Pattern para isolar recursos. Monitoring: métricas de sucesso/failures, alerts quando abre. Best practices: timeout, exponential backoff, circuits granulares, fallbacks."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
