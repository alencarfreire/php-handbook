# 18.2 API Gateway

> **TL;DR**
> API Gateway — ponto único de entrada para todos os clients na arquitetura de microsserviços. Funções: routing para os microsserviços, autenticação/autorização, rate limiting, request aggregation (1 request no lugar de N), caching, protocol translation. Soluções comuns: Kong (baseado em Nginx), AWS API Gateway, custom no Laravel. Backend for Frontend (BFF) — gateways diferentes para cada tipo de client (mobile/web).

## Conteúdo
- [O que é](#o-que-é)
- [Funções do API Gateway](#funções-do-api-gateway)
- [API Gateways populares](#api-gateways-populares)
- [Backend for Frontend (BFF)](#backend-for-frontend-bff)
- [Service Discovery](#service-discovery)
- [Boas práticas](#boas-práticas)
- [Exercícios práticos](#exercícios-práticos)

## O que é

**API Gateway:**
Ponto único de entrada para todos os clients. Encaminha os requests para os microsserviços certos.

**Para quê:**
- Routing centralizado
- Authentication/Authorization
- Rate limiting
- Request/Response transformation
- Caching
- Load balancing

**Sem API Gateway:**

```
Mobile App ──┐
Web App ────┤
             ├──→ User Service
Desktop ────┤    Order Service
API ────────┘    Payment Service
                 Notification Service

Problemas:
❌ Clients conhecem todos os microsserviços
❌ Auth duplicada
❌ CORS em cada serviço
❌ Código do client fica complexo
```

**Com API Gateway:**

```
Mobile App ──┐
Web App ────┤
             ├──→ API Gateway ──┬──→ User Service
Desktop ────┤                   ├──→ Order Service
API ────────┘                   ├──→ Payment Service
                                └──→ Notification Service

Vantagens:
✅ Um endpoint para os clients
✅ Auth centralizada
✅ Lógica de routing num só lugar
✅ Código do client fica simples
```

---

## Funções do API Gateway

### 1. Routing (roteamento)

```
GET  /api/users/*       → User Service
GET  /api/orders/*      → Order Service
POST /api/payments/*    → Payment Service
```

**Configuração do Kong:**

```yaml
services:
  - name: user-service
    url: http://user-service:8080
    routes:
      - name: user-route
        paths:
          - /api/users

  - name: order-service
    url: http://order-service:8081
    routes:
      - name: order-route
        paths:
          - /api/orders
```

---

### 2. Authentication

**API Gateway verifica o JWT:**

```
Client → API Gateway (verify JWT) → Microservice
                ↓
           if invalid → 401 Unauthorized
```

**Kong JWT plugin:**

```yaml
plugins:
  - name: jwt
    config:
      key_claim_name: iss
```

**Laravel (Gateway custom):**

```php
// routes/api.php (Gateway)
Route::middleware('auth:sanctum')->group(function () {
    Route::any('/users/{any}', function (Request $request) {
        return Http::asForm()
            ->withToken($request->bearerToken())
            ->send(
                $request->method(),
                "http://user-service/api/{$request->path()}"
            );
    })->where('any', '.*');
});
```

---

### 3. Rate Limiting

```yaml
# Kong
plugins:
  - name: rate-limiting
    config:
      minute: 100
      hour: 10000
      policy: local
```

**Laravel:**

```php
Route::middleware('throttle:100,1')->group(function () {
    Route::any('/api/{service}/{path}', [GatewayController::class, 'proxy'])
        ->where('path', '.*');
});
```

---

### 4. Request Aggregation

**Problema:**

```
O app mobile precisa:
- User data
- User orders
- User notifications

Sem Gateway: 3 requests
Com Gateway: 1 request
```

**Implementação:**

```php
// API Gateway
class ProfileController extends Controller
{
    public function show($userId)
    {
        // Requests em paralelo
        [$user, $orders, $notifications] = Promise\Utils::unwrap([
            Http::async()->get("http://user-service/api/users/{$userId}"),
            Http::async()->get("http://order-service/api/users/{$userId}/orders"),
            Http::async()->get("http://notif-service/api/users/{$userId}/notifications"),
        ]);

        return response()->json([
            'user' => $user->json(),
            'orders' => $orders->json(),
            'notifications' => $notifications->json(),
        ]);
    }
}

// Client faz 1 request
GET /api/profile/123
```

---

### 5. Protocol Translation

```
Client (HTTP/REST) → API Gateway → Microservice (gRPC)
                                → Microservice (GraphQL)
                                → Microservice (SOAP)
```

---

### 6. Caching

```php
class GatewayController extends Controller
{
    public function proxy($service, $path)
    {
        $cacheKey = "gateway:{$service}:{$path}:" . request()->query();

        return Cache::remember($cacheKey, 300, function () use ($service, $path) {
            return Http::get("http://{$service}/{$path}", request()->query())->json();
        });
    }
}
```

---

## API Gateways populares

### 1. Kong

**O que é:** API Gateway open-source baseado em Nginx.

**Instalação:**

```bash
docker run -d --name kong \
  -e "KONG_DATABASE=off" \
  -e "KONG_PROXY_ACCESS_LOG=/dev/stdout" \
  -e "KONG_ADMIN_ACCESS_LOG=/dev/stdout" \
  -p 8000:8000 \
  -p 8443:8443 \
  -p 8001:8001 \
  kong:latest
```

**Adicionar serviço:**

```bash
curl -i -X POST http://localhost:8001/services/ \
  --data "name=user-service" \
  --data "url=http://user-service:8080"

curl -i -X POST http://localhost:8001/services/user-service/routes \
  --data "paths[]=/api/users"
```

**Plugins:**
- JWT authentication
- Rate limiting
- CORS
- Request/Response transformation
- Caching
- Logging

---

### 2. AWS API Gateway

**Managed service da AWS.**

**Terraform:**

```hcl
resource "aws_api_gateway_rest_api" "api" {
  name = "my-api"
}

resource "aws_api_gateway_resource" "users" {
  rest_api_id = aws_api_gateway_rest_api.api.id
  parent_id   = aws_api_gateway_rest_api.api.root_resource_id
  path_part   = "users"
}

resource "aws_api_gateway_method" "get_users" {
  rest_api_id   = aws_api_gateway_rest_api.api.id
  resource_id   = aws_api_gateway_resource.users.id
  http_method   = "GET"
  authorization = "NONE"
}

resource "aws_api_gateway_integration" "lambda" {
  rest_api_id = aws_api_gateway_rest_api.api.id
  resource_id = aws_api_gateway_resource.users.id
  http_method = aws_api_gateway_method.get_users.http_method
  type        = "AWS_PROXY"
  uri         = aws_lambda_function.user_service.invoke_arn
}
```

---

### 3. Nginx

**Gateway custom no Nginx:**

```nginx
upstream user_service {
    server user-service:8080;
}

upstream order_service {
    server order-service:8081;
}

server {
    listen 80;

    # JWT auth
    location / {
        auth_request /auth;
        auth_request_set $user_id $upstream_http_x_user_id;
        proxy_set_header X-User-Id $user_id;
    }

    location = /auth {
        internal;
        proxy_pass http://auth-service/verify;
        proxy_pass_request_body off;
        proxy_set_header Content-Length "";
    }

    # Routing
    location /api/users/ {
        proxy_pass http://user_service/;
    }

    location /api/orders/ {
        proxy_pass http://order_service/;
    }

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
    limit_req zone=api burst=20;
}
```

---

### 4. Laravel Custom Gateway

**GatewayController:**

```php
class GatewayController extends Controller
{
    private array $serviceMap = [
        'users' => 'http://user-service',
        'orders' => 'http://order-service',
        'payments' => 'http://payment-service',
    ];

    public function proxy(Request $request, string $service, string $path)
    {
        // 1. Checagem de auth
        if (!$request->bearerToken()) {
            return response()->json(['error' => 'Não autorizado'], 401);
        }

        // 2. Service discovery
        $serviceUrl = $this->serviceMap[$service] ?? null;
        if (!$serviceUrl) {
            return response()->json(['error' => 'Serviço não encontrado'], 404);
        }

        // 3. Encaminha o request
        $response = Http::asForm()
            ->withToken($request->bearerToken())
            ->withHeaders([
                'X-Forwarded-For' => $request->ip(),
                'X-Request-Id' => Str::uuid(),
            ])
            ->send(
                $request->method(),
                "$serviceUrl/$path",
                [
                    'query' => $request->query(),
                    'json' => $request->json()->all(),
                ]
            );

        // 4. Devolve a response
        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
}

// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {
    Route::any('/{service}/{path}', [GatewayController::class, 'proxy'])
        ->where('path', '.*');
});
```

---

## Backend for Frontend (BFF)

**Problema:**
Mobile e Web precisam de dados diferentes.

**Solução:**
Um Gateway separado para cada tipo de client.

```
Mobile App → Mobile BFF ──┬──→ User Service
                          ├──→ Order Service
                          └──→ Payment Service

Web App → Web BFF ────────┬──→ User Service
                          ├──→ Order Service
                          └──→ Analytics Service
```

**Mobile BFF (menos dados):**

```php
class MobileGatewayController extends Controller
{
    public function profile($userId)
    {
        $user = Http::get("http://user-service/api/users/{$userId}")->json();
        $orders = Http::get("http://order-service/api/users/{$userId}/orders")->json();

        // Dados mínimos para o mobile
        return response()->json([
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'avatar' => $user['avatar'],
            ],
            'orders_count' => count($orders),
        ]);
    }
}
```

**Web BFF (mais dados):**

```php
class WebGatewayController extends Controller
{
    public function profile($userId)
    {
        [$user, $orders, $analytics] = Promise\Utils::unwrap([
            Http::async()->get("http://user-service/api/users/{$userId}"),
            Http::async()->get("http://order-service/api/users/{$userId}/orders"),
            Http::async()->get("http://analytics-service/api/users/{$userId}"),
        ]);

        // Dados completos para o web
        return response()->json([
            'user' => $user->json(),
            'orders' => $orders->json(),
            'analytics' => $analytics->json(),
        ]);
    }
}
```

---

## Service Discovery

**Problema:**
Serviços podem mudar de lugar (IP dinâmico no Kubernetes).

**Solução:**
Service Discovery (Consul, Eureka, Kubernetes DNS).

**Exemplo com Consul:**

```php
class ServiceDiscovery
{
    public function getServiceUrl(string $service): string
    {
        // Consultar o Consul
        $response = Http::get("http://consul:8500/v1/catalog/service/{$service}");
        $instances = $response->json();

        // Escolher um instance aleatório (client-side load balancing)
        $instance = $instances[array_rand($instances)];

        return "http://{$instance['Address']}:{$instance['ServicePort']}";
    }
}

// Uso
$serviceUrl = $this->serviceDiscovery->getServiceUrl('user-service');
$user = Http::get("$serviceUrl/api/users/{$id}")->json();
```

---

## Boas práticas

```
✓ Stateless: o Gateway não guarda estado (dá para escalar)
✓ Timeout: defina timeout nos requests aos serviços
✓ Circuit Breaker: não spamme o serviço que caiu
✓ Monitoring: logue todos os requests (request ID para trace)
✓ Caching: cacheie onde der
✓ Compression: gzip na response
✓ CORS: configure para o frontend
✓ Versioning: /api/v1, /api/v2
```

---

## Exercícios práticos

<details>
<summary>Exercício 1: API Gateway simples no Laravel</summary>

**Enunciado:**
Crie um API Gateway básico com roteamento para dois microsserviços: users e orders.

**Solução:**

```php
// routes/api.php
Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {
    Route::any('/{service}/{path}', [GatewayController::class, 'proxy'])
        ->where('service', 'users|orders')
        ->where('path', '.*');
});

// app/Http/Controllers/GatewayController.php
class GatewayController extends Controller
{
    private array $serviceMap = [
        'users' => 'http://user-service:8080',
        'orders' => 'http://order-service:8081',
    ];

    public function proxy(Request $request, string $service, string $path)
    {
        $serviceUrl = $this->serviceMap[$service] ?? null;

        if (!$serviceUrl) {
            return response()->json(['error' => 'Serviço não encontrado'], 404);
        }

        $response = Http::timeout(5)
            ->withToken($request->bearerToken())
            ->withHeaders(['X-Request-Id' => Str::uuid()])
            ->send(
                $request->method(),
                "{$serviceUrl}/{$path}",
                ['query' => $request->query(), 'json' => $request->json()->all()]
            );

        return response($response->body(), $response->status())
            ->withHeaders($response->headers());
    }
}
```
</details>

<details>
<summary>Exercício 2: Request Aggregation</summary>

**Enunciado:**
Implemente um endpoint no API Gateway que agrega dados de três microsserviços em paralelo.

**Solução:**

```php
class ProfileController extends Controller
{
    public function show(Request $request, int $userId)
    {
        // Requests em paralelo para três serviços
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('user')->get("http://user-service/api/users/{$userId}"),
            $pool->as('orders')->get("http://order-service/api/users/{$userId}/orders"),
            $pool->as('notifications')->get("http://notification-service/api/users/{$userId}/notifications"),
        ]);

        return response()->json([
            'user' => $responses['user']->json(),
            'orders' => $responses['orders']->json(),
            'notifications' => $responses['notifications']->json(),
        ]);
    }
}

// 1 request do client no lugar de 3!
// GET /api/profile/123
```
</details>

<details>
<summary>Exercício 3: Backend for Frontend (BFF)</summary>

**Enunciado:**
Crie endpoints diferentes para os apps Mobile e Web, com volume de dados diferente.

**Solução:**

```php
// routes/api.php
Route::prefix('mobile')->group(function () {
    Route::get('/profile/{id}', [MobileBFFController::class, 'profile']);
});

Route::prefix('web')->group(function () {
    Route::get('/profile/{id}', [WebBFFController::class, 'profile']);
});

// Mobile BFF (mínimo de dados)
class MobileBFFController extends Controller
{
    public function profile(int $id)
    {
        $user = Http::get("http://user-service/api/users/{$id}")->json();

        return response()->json([
            'id' => $user['id'],
            'name' => $user['name'],
            'avatar' => $user['avatar'], // só o avatar
        ]);
    }
}

// Web BFF (dados completos)
class WebBFFController extends Controller
{
    public function profile(int $id)
    {
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('user')->get("http://user-service/api/users/{$id}"),
            $pool->as('analytics')->get("http://analytics-service/api/users/{$id}"),
            $pool->as('preferences')->get("http://preference-service/api/users/{$id}"),
        ]);

        return response()->json([
            'user' => $responses['user']->json(),
            'analytics' => $responses['analytics']->json(),
            'preferences' => $responses['preferences']->json(),
        ]);
    }
}
```
</details>

---

## Na entrevista

> "API Gateway é o ponto único de entrada para os clients. Funções: routing para os microsserviços, autenticação (JWT), rate limiting, request aggregation (1 request no lugar de N), protocol translation, caching. Os mais usados: Kong (baseado em Nginx), AWS API Gateway, custom no Laravel/Nginx. Backend for Frontend (BFF): gateways diferentes para mobile e web. Service Discovery (Consul, K8s DNS) para IP dinâmico. Best practices: stateless, timeout, circuit breaker, monitoring, compression."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
