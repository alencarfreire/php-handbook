# 8.7 HTTPS / SSL

## Resumo

> **HTTPS (SSL/TLS)** — protocolo de transferência segura pela internet. Criptografa o tráfego entre cliente e servidor.
>
> **Proteção:** Bloqueia ataque Man-in-the-Middle, escuta de tráfego, roubo de senha e cookie.
>
> **Importante:** Let's Encrypt para certificado grátis, header HSTS para forçar HTTPS, middleware TrustProxies para funcionar certo atrás do load balancer.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
HTTPS — HTTP por cima de SSL/TLS. Criptografa o tráfego entre cliente e servidor. Protege contra escuta e ataque MITM.

**Por que precisa:**
- Criptografia dos dados
- Proteção contra Man-in-the-Middle
- Boost de SEO (Google)
- Confiança do usuário

---

## Como funciona

**Pegar o certificado SSL:**

```bash
# Let's Encrypt (gratuito)
sudo certbot --nginx -d example.com -d www.example.com

# Renovação automática
sudo certbot renew --dry-run
```

**Configuração no Laravel:**

```php
// .env
APP_URL=https://example.com

// config/app.php
'url' => env('APP_URL', 'https://example.com'),

// Middleware para forçar HTTPS
// app/Http/Middleware/ForceHttps.php
class ForceHttps
{
    public function handle($request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}

// Registrar no Kernel.php
protected $middleware = [
    \App\Http\Middleware\ForceHttps::class,
];
```

**Trust Proxies (para load balancers):**

```php
// app/Http/Middleware/TrustProxies.php
class TrustProxies extends Middleware
{
    protected $proxies = '*';  // Confiar em todos os proxies

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

---

## Quando usar

**Use sempre HTTPS:**
- ✅ Production (obrigatório)
- ✅ Staging (recomendado)
- ❌ Local dev (opcional, mas dá pra usar)

---

## Exemplo prático

**Configuração Nginx:**

```nginx
server {
    listen 80;
    server_name example.com www.example.com;
    return 301 https://$server_name$request_uri;  # Redirect HTTP → HTTPS
}

server {
    listen 443 ssl http2;
    server_name example.com www.example.com;

    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Configuração SSL
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
    ssl_prefer_server_ciphers on;

    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

**HSTS (HTTP Strict Transport Security):**

```php
// app/Http/Middleware/AddSecurityHeaders.php
class AddSecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Força HTTPS por 1 ano
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        return $response;
    }
}
```

**Mixed Content (HTTP em página HTTPS):**

```blade
{{-- ❌ RUIM: HTTP em página HTTPS --}}
<script src="http://example.com/script.js"></script>

{{-- ✅ BOM: HTTPS --}}
<script src="https://example.com/script.js"></script>

{{-- ✅ BOM: URL protocol-relative --}}
<script src="//example.com/script.js"></script>

{{-- ✅ BOM: asset() usa o protocolo certo sozinho --}}
<script src="{{ asset('js/app.js') }}"></script>
```

**SSL no ambiente local:**

```bash
# Valet (macOS)
valet secure example.test

# Laravel Homestead (automático)
# https://homestead.test

# mkcert (qualquer SO)
brew install mkcert
mkcert -install
mkcert example.test "*.example.test"

# Usar no nginx
ssl_certificate example.test.pem;
ssl_certificate_key example.test-key.pem;
```

**Checar a configuração SSL:**

```bash
# SSL Labs
https://www.ssllabs.com/ssltest/analyze.html?d=example.com

# OpenSSL
openssl s_client -connect example.com:443

# Curl
curl -I https://example.com

# Inspecionar o certificado
openssl x509 -in cert.pem -text -noout
```

**Tratar erros de SSL:**

```php
// app/Exceptions/Handler.php
public function render($request, Throwable $exception)
{
    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
        if (str_contains($exception->getMessage(), 'SSL')) {
            Log::error('Erro SSL', ['message' => $exception->getMessage()]);
            return response()->json(['error' => 'Falha na conexão SSL'], 500);
        }
    }

    return parent::render($request, $exception);
}
```

**CloudFlare (CDN com SSL):**

```nginx
# Pegar o IP real via CloudFlare
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
# ... outros IP ranges do CloudFlare
real_ip_header CF-Connecting-IP;
```

```php
// TrustProxies para CloudFlare
protected $proxies = [
    '103.21.244.0/22',
    '103.22.200.0/22',
    // ...
];
```

**Cookies via HTTPS:**

```php
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),  // Só HTTPS

// Na mão
return response('Content')->cookie(
    'name',
    'value',
    $minutes = 60,
    $path = '/',
    $domain = null,
    $secure = true,  // Só HTTPS
    $httpOnly = true
);
```

---

## Na entrevista

> "HTTPS criptografa o tráfego com SSL/TLS. Let's Encrypt para certificado grátis. Force HTTPS no middleware com redirect()->secure(). Header HSTS força HTTPS (max-age). Trust Proxies para o protocolo sair certo atrás do load balancer. asset() gera a URL certa sozinho. Mixed Content é recurso HTTP em página HTTPS — o browser bloqueia. SESSION_SECURE_COOKIE=true manda cookie só por HTTPS. SSL Labs para checar a configuração."

---

## Exercícios práticos

### Exercício 1: Crie um middleware para forçar HTTPS

**Enunciado:** Implemente um middleware que redireciona toda request HTTP para HTTPS em production.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/ForceHttps.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceHttps
{
    /**
     * Redirect para HTTPS em production
     */
    public function handle(Request $request, Closure $next)
    {
        // Checa só em production
        if (!app()->environment('local')) {
            // Checa se a request não veio por HTTPS
            if (!$request->secure()) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
        }

        return $next($request);
    }
}

// Alternativa: checar pelos headers (para load balancers)
class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!app()->environment('local')) {
            // Checa o header X-Forwarded-Proto (do load balancer)
            $proto = $request->header('X-Forwarded-Proto');

            if ($proto !== 'https' && !$request->secure()) {
                return redirect()->secure($request->getRequestUri(), 301);
            }
        }

        return $next($request);
    }
}

// Registro no app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\ForceHttps::class,
];

// Alternativa 2: usar o método nativo
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!app()->environment('local')) {
            URL::forceScheme('https');
        }
    }
}

// Alternativa 3: redirect no Nginx
// nginx.conf
server {
    listen 80;
    server_name example.com www.example.com;
    return 301 https://$server_name$request_uri;
}

// Alternativa 4: via .htaccess (Apache)
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
</IfModule>
```
</details>

### Exercício 2: Configure os Security Headers

**Enunciado:** Crie um middleware que adiciona todos os security headers necessários, incluindo HSTS.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/SecurityHeaders.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // HSTS: força HTTPS por 1 ano
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload'
        );

        // Previne clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Previne MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS Protection (legado, mas para browsers antigos)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (substitui Feature-Policy)
        $permissions = implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
        ]);
        $response->headers->set('Permissions-Policy', $permissions);

        return $response;
    }
}

// Registro
protected $middleware = [
    // ...
    \App\Http\Middleware\SecurityHeaders::class,
];

// Alternativa: usar um pacote
composer require bepsvpt/secure-headers

// config/secure-headers.php (depois do publish)
return [
    'hsts' => [
        'enable' => true,
        'max-age' => 31536000,
        'include-sub-domains' => true,
        'preload' => true,
    ],

    'x-frame-options' => 'SAMEORIGIN',

    'x-content-type-options' => 'nosniff',

    'csp' => [
        'enable' => true,
        'default-src' => [
            'self',
        ],
        'script-src' => [
            'self',
            'unsafe-inline',
        ],
    ],
];

// Teste dos headers
class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
    }
}
```
</details>

### Exercício 3: Configure TrustProxies para AWS/CloudFlare

**Enunciado:** Configure o app para funcionar certo atrás de um load balancer ou CloudFlare.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/TrustProxies.php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxies confiáveis
     *
     * Opção 1: confiar em todos os proxies (AWS ELB, GCP Load Balancer)
     */
    protected $proxies = '*';

    /**
     * Opção 2: IPs específicos (CloudFlare)
     */
    // protected $proxies = [
    //     // CloudFlare IPv4
    //     '173.245.48.0/20',
    //     '103.21.244.0/22',
    //     '103.22.200.0/22',
    //     '103.31.4.0/22',
    //     '141.101.64.0/18',
    //     '108.162.192.0/18',
    //     '190.93.240.0/20',
    //     '188.114.96.0/20',
    //     '197.234.240.0/22',
    //     '198.41.128.0/17',
    //     '162.158.0.0/15',
    //     '104.16.0.0/13',
    //     '104.24.0.0/14',
    //     '172.64.0.0/13',
    //     '131.0.72.0/22',
    // ];

    /**
     * Headers que devem ser confiáveis
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}

// Config no .env
APP_URL=https://example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

// Configuração Nginx (atrás do load balancer)
server {
    listen 80;
    server_name example.com;

    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}

// Configuração CloudFlare
// 1. No CloudFlare: SSL/TLS -> Full (Strict)
// 2. Ligar "Always Use HTTPS"
// 3. Ligar HSTS

// app/Http/Middleware/CloudFlareProxies.php
class CloudFlareProxies extends Middleware
{
    protected $proxies;

    public function __construct()
    {
        // Busca os IPs do CloudFlare dinamicamente
        $this->proxies = $this->getCloudFlareIPs();
    }

    private function getCloudFlareIPs(): array
    {
        // Cache de 1 dia
        return cache()->remember('cloudflare_ips', 86400, function () {
            try {
                $ipv4 = file_get_contents('https://www.cloudflare.com/ips-v4');
                $ipv6 = file_get_contents('https://www.cloudflare.com/ips-v6');

                return array_merge(
                    explode("\n", trim($ipv4)),
                    explode("\n", trim($ipv6))
                );
            } catch (\Exception $e) {
                // Fallback: confiar em todos os proxies se não conseguir buscar
                return ['*'];
            }
        });
    }

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_CF_CONNECTING_IP; // CloudFlare header
}

// Checa se IP e protocolo foram detectados certo
Route::get('/debug-request', function (Request $request) {
    return response()->json([
        'ip' => $request->ip(),
        'scheme' => $request->getScheme(),
        'secure' => $request->secure(),
        'host' => $request->getHost(),
        'headers' => [
            'X-Forwarded-For' => $request->header('X-Forwarded-For'),
            'X-Forwarded-Proto' => $request->header('X-Forwarded-Proto'),
            'X-Forwarded-Host' => $request->header('X-Forwarded-Host'),
            'CF-Connecting-IP' => $request->header('CF-Connecting-IP'),
        ],
    ]);
})->middleware('auth');

// Test
class TrustProxiesTest extends TestCase
{
    public function test_https_is_detected_behind_proxy(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '1.2.3.4',
        ])->get('/');

        $this->assertTrue($response->baseResponse->isSecure());
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
