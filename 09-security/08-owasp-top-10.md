# 8.8 OWASP Top 10

## Resumo

> **OWASP Top 10** é a lista das dez vulnerabilidades mais críticas em apps web. Atualiza a cada 3-4 anos.
>
> **Principais ameaças:** Broken Access Control, Cryptographic Failures, Injection, Insecure Design, Security Misconfiguration.
>
> **Importante:** `composer audit` nas dependências, `APP_DEBUG=false` em production, Gate/Policy no controle de acesso, Query Builder contra SQL Injection.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como se proteger](#como-se-proteger)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
OWASP Top 10 é a lista das 10 vulnerabilidades mais críticas em apps web. Atualiza a cada 3-4 anos.

**OWASP Top 10 (2021):**
1. Broken Access Control
2. Cryptographic Failures
3. Injection
4. Insecure Design
5. Security Misconfiguration
6. Vulnerable Components
7. Identification and Authentication Failures
8. Software and Data Integrity Failures
9. Security Logging and Monitoring Failures
10. Server-Side Request Forgery (SSRF)

---

## Como se proteger

**1. Broken Access Control:**

```php
// ❌ RUIM: sem checagem de permissão
Route::put('/posts/{post}', function (Post $post) {
    $post->update(request()->all());
});

// ✅ BOM: checagem via Policy
Route::put('/posts/{post}', function (Post $post) {
    Gate::authorize('update', $post);
    $post->update(request()->validated());
});

// ✅ Middleware
Route::middleware('can:update,post')->put('/posts/{post}', ...);
```

**2. Cryptographic Failures:**

```php
// ❌ RUIM: senha em texto puro
User::create(['password' => $request->password]);

// ✅ BOM: hash
User::create(['password' => Hash::make($request->password)]);

// ✅ Criptografe dados sensíveis
$user->credit_card = Crypt::encryptString($request->credit_card);

// ✅ HTTPS é obrigatório
// Middleware ForceHttps
```

**3. Injection (SQL, XSS, Command):**

```php
// ❌ SQL Injection
DB::select("SELECT * FROM users WHERE email = '{$email}'");

// ✅ Prepared statements
DB::table('users')->where('email', $email)->get();

// ❌ XSS
{!! $user->bio !!}

// ✅ Escape
{{ $user->bio }}

// ❌ Command Injection
exec("ping -c 4 {$request->host}");

// ✅ Validação e escape
$host = escapeshellarg($request->host);
exec("ping -c 4 {$host}");
```

**4. Insecure Design:**

```php
// ❌ RUIM: sem rate limiting
Route::post('/login', [AuthController::class, 'login']);

// ✅ BOM: rate limiting
Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1');  // 5 tentativas por minuto

// ✅ Email verification
class User extends Authenticatable implements MustVerifyEmail {}

// ✅ 2FA
// Use google2fa ou equivalente
```

**5. Security Misconfiguration:**

```php
// ❌ RUIM: debug em production
// .env
APP_DEBUG=true  // ❌

// ✅ BOM
APP_DEBUG=false

// ❌ Default credentials
DB_USERNAME=root
DB_PASSWORD=

// ✅ Senhas fortes
DB_PASSWORD=random_strong_password

// ✅ Desative métodos HTTP que você não usa
// Nginx
limit_except GET POST { deny all; }

// ✅ Remova dependências que não usa
composer remove unused/package
```

**6. Vulnerable Components:**

```bash
# ✅ Atualize as dependências com frequência
composer update

# ✅ Cheque vulnerabilidades
composer audit

# ✅ Atualize o Laravel
composer require laravel/framework:^10.0

# ✅ Atualize o PHP
# Use a última versão estável do PHP
```

**7. Authentication Failures:**

```php
// ❌ RUIM: senhas fracas
'password' => 'required|min:6'

// ✅ BOM: senhas fortes
'password' => 'required|min:8|confirmed|regex:/[A-Z]/|regex:/[0-9]/'

// ✅ Rate limiting
RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});

// ✅ MFA
// Use 2FA com google2fa

// ✅ Session timeout
// config/session.php
'lifetime' => 120,  // 2 horas
```

**8. Software and Data Integrity Failures:**

```php
// ✅ Valide a integridade dos arquivos enviados
$request->validate([
    'file' => 'required|file|mimes:pdf,docx|max:10240',
]);

// ✅ Signed URLs
$url = URL::temporarySignedRoute('download', now()->addMinutes(30), ['file' => $fileId]);

// ✅ Tokens CSRF
@csrf

// ✅ Subresource Integrity (SRI) para CDN
<script src="https://cdn.example.com/script.js"
        integrity="sha384-..."
        crossorigin="anonymous"></script>
```

**9. Security Logging and Monitoring:**

```php
// ✅ Log de eventos críticos
use Illuminate\Support\Facades\Log;

// Login
Log::info('User logged in', ['user_id' => $user->id, 'ip' => $request->ip()]);

// Tentativa falha
Log::warning('Failed login attempt', ['email' => $email, 'ip' => $request->ip()]);

// Troca de senha
Log::info('Password changed', ['user_id' => $user->id]);

// Exclusão de dados importantes
Log::warning('Post deleted', ['post_id' => $post->id, 'user_id' => $user->id]);

// ✅ Monitoramento com alertas
// Sentry, Bugsnag, Laravel Telescope

// ✅ Rotação de logs
// config/logging.php
'daily' => [
    'driver' => 'daily',
    'path' => storage_path('logs/laravel.log'),
    'level' => 'debug',
    'days' => 14,  // Guarde 14 dias
],
```

**10. Server-Side Request Forgery (SSRF):**

```php
// ❌ RUIM: fetch de URL que o usuário mandou
$url = $request->input('url');
$content = file_get_contents($url);  // vulnerabilidade SSRF

// ✅ BOM: whitelist de domínios
$allowedDomains = ['example.com', 'api.example.com'];
$parsedUrl = parse_url($url);

if (!in_array($parsedUrl['host'], $allowedDomains)) {
    abort(403, 'Invalid domain');
}

$content = file_get_contents($url);

// ✅ Bloqueie IPs internos
$ip = gethostbyname($parsedUrl['host']);

if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
    $content = file_get_contents($url);
} else {
    abort(403, 'Internal IP blocked');
}
```

**Security Headers comuns:**

```php
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        return $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('X-XSS-Protection', '1; mode=block')
            ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->header('Content-Security-Policy', "default-src 'self'")
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'geolocation=(), microphone=()');
    }
}
```

**Security Checklist:**

```php
// .env
APP_DEBUG=false  ✅
APP_ENV=production  ✅
SESSION_SECURE_COOKIE=true  ✅
SESSION_SAME_SITE=lax  ✅

// Composer
composer audit  ✅

// Permissions
chmod 644 .env  ✅
chmod 755 storage bootstrap/cache  ✅

// HTTPS
Force HTTPS middleware  ✅
HSTS header  ✅

// Proteção
CSRF tokens  ✅
XSS escaping  ✅
SQL injection (Query Builder)  ✅
Rate limiting  ✅
Strong passwords  ✅
Email verification  ✅

// Logs
Security events logging  ✅
Monitoring (Sentry)  ✅
```

---

## Na entrevista

> "OWASP Top 10 são as vulnerabilidades críticas. 1) Access Control — Gate/Policy. 2) Crypto — Hash::make(), Crypt::encrypt(). 3) Injection — Query Builder, {{ }}. 4) Design — rate limiting, 2FA. 5) Misconfiguration — APP_DEBUG=false. 6) Components — composer audit. 7) Auth — senhas fortes, MFA. 8) Integrity — signed URLs, CSRF. 9) Logging — Log::info() nos eventos críticos. 10) SSRF — whitelist de domínios, bloqueio de IP interno. Security headers: X-Frame-Options, CSP, HSTS."

---

## Exercícios práticos

### Exercício 1: Faça um Security Audit do app

**Enunciado:** Monte um checklist e cheque o app Laravel nas vulnerabilidades principais do OWASP Top 10.

<details>
<summary>Solução</summary>

```php
// security-audit.php (comando artisan)
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SecurityAudit extends Command
{
    protected $signature = 'security:audit';
    protected $description = 'Auditoria de segurança do app';

    private array $issues = [];

    public function handle()
    {
        $this->info('🔍 Começando a auditoria de segurança...');
        $this->newLine();

        $this->checkEnvironment();
        $this->checkDependencies();
        $this->checkConfiguration();
        $this->checkFiles();
        $this->checkDatabase();
        $this->checkRoutes();

        $this->newLine();
        $this->displayResults();
    }

    private function checkEnvironment(): void
    {
        $this->info('1. Checando o ambiente...');

        // APP_DEBUG
        if (config('app.debug') === true && app()->environment('production')) {
            $this->addIssue('HIGH', 'APP_DEBUG=true em production');
        } else {
            $this->success('APP_DEBUG ok');
        }

        // APP_KEY
        if (empty(config('app.key'))) {
            $this->addIssue('CRITICAL', 'APP_KEY não está definido');
        } else {
            $this->success('APP_KEY definido');
        }

        // SESSION_SECURE_COOKIE
        if (!config('session.secure') && app()->environment('production')) {
            $this->addIssue('MEDIUM', 'SESSION_SECURE_COOKIE precisa ser true');
        } else {
            $this->success('SESSION_SECURE_COOKIE ok');
        }
    }

    private function checkDependencies(): void
    {
        $this->info('2. Checando dependências...');

        // Roda composer audit
        exec('composer audit --format=json 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->addIssue('HIGH', 'Dependências vulneráveis (composer audit)');
        } else {
            $this->success('Dependências ok');
        }

        // Checa a versão do PHP
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->addIssue('MEDIUM', 'Versão antiga do PHP: ' . PHP_VERSION);
        } else {
            $this->success('Versão do PHP: ' . PHP_VERSION);
        }
    }

    private function checkConfiguration(): void
    {
        $this->info('3. Checando a configuração...');

        // CSRF protection
        $csrfMiddleware = file_get_contents(app_path('Http/Kernel.php'));
        if (!str_contains($csrfMiddleware, 'VerifyCsrfToken')) {
            $this->addIssue('CRITICAL', 'CSRF middleware não encontrado');
        } else {
            $this->success('CSRF protection ligado');
        }

        // Configuração de CORS
        if (config('cors.supports_credentials') && config('cors.allowed_origins')[0] === '*') {
            $this->addIssue('HIGH', 'CORS: supports_credentials=true com allowed_origins=*');
        } else {
            $this->success('CORS ok');
        }
    }

    private function checkFiles(): void
    {
        $this->info('4. Checando arquivos...');

        // .env no diretório público
        if (File::exists(public_path('.env'))) {
            $this->addIssue('CRITICAL', 'arquivo .env no diretório public');
        } else {
            $this->success('.env fora do public');
        }

        // Permissões dos arquivos
        $permissions = substr(sprintf('%o', fileperms(base_path('.env'))), -4);
        if ($permissions !== '0644') {
            $this->addIssue('MEDIUM', ".env está com permissão {$permissions} (precisa ser 0644)");
        } else {
            $this->success('Permissão do .env ok');
        }
    }

    private function checkDatabase(): void
    {
        $this->info('5. Checando o banco...');

        // Default credentials
        if (config('database.connections.mysql.username') === 'root' &&
            empty(config('database.connections.mysql.password'))) {
            $this->addIssue('CRITICAL', 'Banco usa root sem senha');
        } else {
            $this->success('Credenciais do banco ok');
        }

        // Checa usuários sem senha
        try {
            $usersWithoutPassword = DB::table('users')
                ->whereNull('password')
                ->orWhere('password', '')
                ->count();

            if ($usersWithoutPassword > 0) {
                $this->addIssue('HIGH', "{$usersWithoutPassword} usuários sem senha");
            } else {
                $this->success('Todos os usuários têm senha');
            }
        } catch (\Exception $e) {
            $this->warn('Não deu para checar os usuários: ' . $e->getMessage());
        }
    }

    private function checkRoutes(): void
    {
        $this->info('6. Checando as rotas...');

        $routes = \Route::getRoutes();
        $unprotectedRoutes = [];

        foreach ($routes as $route) {
            $middleware = $route->middleware();

            // Checa POST/PUT/DELETE sem CSRF
            if (in_array($route->methods()[0], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
                if (!in_array('web', $middleware) && !in_array('api', $middleware)) {
                    $unprotectedRoutes[] = $route->uri();
                }
            }
        }

        if (!empty($unprotectedRoutes)) {
            $this->addIssue('HIGH', 'Rotas sem middleware: ' . implode(', ', $unprotectedRoutes));
        } else {
            $this->success('Todas as rotas estão protegidas');
        }
    }

    private function addIssue(string $severity, string $message): void
    {
        $this->issues[] = compact('severity', 'message');

        $color = match($severity) {
            'CRITICAL' => 'red',
            'HIGH' => 'yellow',
            'MEDIUM' => 'blue',
            default => 'gray',
        };

        $this->line("  <fg={$color}>[{$severity}]</> {$message}");
    }

    private function success(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
    }

    private function displayResults(): void
    {
        if (empty($this->issues)) {
            $this->info('✅ Nenhum problema encontrado!');
            return;
        }

        $this->error('❌ Problemas encontrados: ' . count($this->issues));
        $this->newLine();

        $critical = array_filter($this->issues, fn($i) => $i['severity'] === 'CRITICAL');
        $high = array_filter($this->issues, fn($i) => $i['severity'] === 'HIGH');
        $medium = array_filter($this->issues, fn($i) => $i['severity'] === 'MEDIUM');

        $this->table(
            ['Severity', 'Count'],
            [
                ['CRITICAL', count($critical)],
                ['HIGH', count($high)],
                ['MEDIUM', count($medium)],
            ]
        );

        $this->newLine();
        $this->warn('Corrija os problemas críticos antes do deploy!');
    }
}

// Execução
php artisan security:audit
```
</details>

### Exercício 2: Implemente proteção contra SSRF

**Enunciado:** Crie um service para fetch seguro de URL externa, com proteção contra SSRF.

<details>
<summary>Solução</summary>

```php
// app/Services/SafeHttpClient.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class SafeHttpClient
{
    private const ALLOWED_SCHEMES = ['http', 'https'];
    private const BLOCKED_IP_RANGES = [
        // Redes privadas
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        // Loopback
        '::1/128',
        // Link-local
        '169.254.0.0/16',
        'fe80::/10',
    ];

    private array $allowedDomains = [];
    private int $timeout = 5;

    public function __construct(array $allowedDomains = [])
    {
        $this->allowedDomains = $allowedDomains;
    }

    /**
     * GET seguro
     */
    public function get(string $url): array
    {
        $this->validateUrl($url);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['https'], // Só redirect HTTPS
                    ],
                ])
                ->get($url);

            return [
                'success' => true,
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valida a URL
     */
    private function validateUrl(string $url): void
    {
        // 1. Validação básica da URL
        $validator = Validator::make(['url' => $url], [
            'url' => 'required|url|max:2048',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid URL format');
        }

        // 2. Parse da URL
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            throw new \InvalidArgumentException('Invalid URL');
        }

        // 3. Checa o scheme
        if (!in_array($parsed['scheme'] ?? '', self::ALLOWED_SCHEMES)) {
            throw new \InvalidArgumentException('Invalid URL scheme');
        }

        // 4. Whitelist de domínios (se tiver)
        if (!empty($this->allowedDomains)) {
            if (!in_array($parsed['host'], $this->allowedDomains)) {
                throw new \InvalidArgumentException('Domain not allowed');
            }
        }

        // 5. Bloqueia IP privado
        $this->validateIp($parsed['host']);

        // 6. Bloqueia domínios especiais
        $blockedDomains = ['localhost', 'metadata.google.internal', '169.254.169.254'];
        if (in_array(strtolower($parsed['host']), $blockedDomains)) {
            throw new \InvalidArgumentException('Blocked domain');
        }
    }

    /**
     * Valida o IP
     */
    private function validateIp(string $host): void
    {
        // Resolve o IP do host
        $ip = gethostbyname($host);

        // Se o host não resolve, gethostbyname devolve o próprio host
        if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return; // Não é um IP
        }

        // Garante que o IP não é privado
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new \InvalidArgumentException('Private IP addresses are blocked');
        }

        // Checa o IP na blocklist
        foreach (self::BLOCKED_IP_RANGES as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new \InvalidArgumentException("IP {$ip} is in blocked range");
            }
        }
    }

    /**
     * Checa se o IP está no range CIDR
     */
    private function ipInRange(string $ip, string $range): bool
    {
        if (str_contains($range, '/')) {
            [$subnet, $bits] = explode('/', $range);
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet &= $mask;
            return ($ip & $mask) == $subnet;
        }

        return $ip === $range;
    }
}

// Uso
class WebhookController extends Controller
{
    public function fetchExternal(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url',
        ]);

        // Só permite esses domínios
        $client = new SafeHttpClient([
            'api.example.com',
            'webhook.example.com',
        ]);

        $result = $client->get($validated['url']);

        if (!$result['success']) {
            return response()->json([
                'error' => 'Failed to fetch URL',
            ], 400);
        }

        return response()->json([
            'data' => $result['body'],
        ]);
    }
}

// Testes
class SafeHttpClientTest extends TestCase
{
    public function test_blocks_private_ips(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $client = new SafeHttpClient();
        $client->get('http://127.0.0.1');
    }

    public function test_blocks_metadata_endpoint(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $client = new SafeHttpClient();
        $client->get('http://169.254.169.254/latest/meta-data/');
    }

    public function test_allows_whitelisted_domain(): void
    {
        $client = new SafeHttpClient(['example.com']);

        $result = $client->get('https://example.com');

        $this->assertTrue($result['success']);
    }

    public function test_blocks_non_whitelisted_domain(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $client = new SafeHttpClient(['example.com']);
        $client->get('https://evil.com');
    }
}
```
</details>

### Exercício 3: Crie um security middleware completo

**Enunciado:** Implemente um middleware que checa os pontos principais de segurança do request.

<details>
<summary>Solução</summary>

```php
// app/Http/Middleware/ComprehensiveSecurity.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ComprehensiveSecurity
{
    private const SUSPICIOUS_PATTERNS = [
        'sql' => '/(\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP)\b)/i',
        'xss' => '/<script|javascript:|onerror=|onload=/i',
        'path_traversal' => '/\.\.(\/|\\\\)/i',
        'command_injection' => '/[;&|`$]/i',
    ];

    public function handle(Request $request, Closure $next)
    {
        // 1. Rate limiting por IP
        $this->checkRateLimit($request);

        // 2. Valida o User-Agent
        $this->validateUserAgent($request);

        // 3. Procura padrões suspeitos
        $this->scanForThreats($request);

        // 4. Valida o tamanho do request
        $this->validateRequestSize($request);

        // 5. Valida o Referer (operações críticas)
        if ($this->isCriticalOperation($request)) {
            $this->validateReferer($request);
        }

        $response = $next($request);

        // 6. Adiciona security headers
        $this->addSecurityHeaders($response);

        // 7. Log de atividade suspeita
        $this->logSuspiciousActivity($request);

        return $response;
    }

    private function checkRateLimit(Request $request): void
    {
        $key = 'security:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 100)) {
            $this->blockIp($request->ip());

            Log::warning('Rate limit exceeded', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(429, 'Too many requests');
        }

        RateLimiter::hit($key, 60);
    }

    private function validateUserAgent(Request $request): void
    {
        $userAgent = $request->userAgent();

        // Bloqueia request sem User-Agent
        if (empty($userAgent)) {
            $this->logThreat('Missing User-Agent', $request);
            abort(403, 'Invalid request');
        }

        // Bloqueia bots suspeitos
        $blockedAgents = ['sqlmap', 'nikto', 'nmap', 'masscan'];

        foreach ($blockedAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                $this->logThreat("Blocked bot: {$agent}", $request);
                $this->blockIp($request->ip());
                abort(403, 'Forbidden');
            }
        }
    }

    private function scanForThreats(Request $request): void
    {
        $input = json_encode($request->all());

        foreach (self::SUSPICIOUS_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $input)) {
                $this->logThreat("Potential {$type} attack detected", $request);

                // Incrementa o contador de atividade suspeita
                $key = 'threats:' . $request->ip();
                $threats = RateLimiter::hit($key, 3600);

                // Bloqueia depois de 5 ameaças em 1 hora
                if ($threats > 5) {
                    $this->blockIp($request->ip());
                    abort(403, 'Suspicious activity detected');
                }

                // Não bloqueia na hora, só registra no log
                return;
            }
        }
    }

    private function validateRequestSize(Request $request): void
    {
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($request->header('Content-Length') > $maxSize) {
            $this->logThreat('Request too large', $request);
            abort(413, 'Request entity too large');
        }
    }

    private function validateReferer(Request $request): void
    {
        $referer = $request->headers->get('referer');
        $appUrl = config('app.url');

        if ($referer && !str_starts_with($referer, $appUrl)) {
            $this->logThreat('Invalid referer for critical operation', $request);
            abort(403, 'Invalid referer');
        }
    }

    private function isCriticalOperation(Request $request): bool
    {
        $criticalPaths = [
            '/admin/',
            '/api/users/delete',
            '/api/payments',
        ];

        $path = $request->path();

        foreach ($criticalPaths as $criticalPath) {
            if (str_starts_with($path, $criticalPath)) {
                return true;
            }
        }

        return false;
    }

    private function addSecurityHeaders($response): void
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
    }

    private function logThreat(string $message, Request $request): void
    {
        Log::warning('Security threat detected', [
            'message' => $message,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'input' => $request->except(['password', 'password_confirmation']),
        ]);
    }

    private function logSuspiciousActivity(Request $request): void
    {
        // Log de tentativas de login que falharam
        if ($request->is('login') && $request->isMethod('POST')) {
            if (!auth()->check()) {
                Log::info('Failed login attempt', [
                    'ip' => $request->ip(),
                    'email' => $request->input('email'),
                ]);
            }
        }
    }

    private function blockIp(string $ip): void
    {
        // Guarda no cache por 24 horas
        cache()->put("blocked_ip:{$ip}", true, now()->addHours(24));

        Log::alert('IP blocked', ['ip' => $ip]);

        // Opcional: notifique o admin
        // Notification::send(User::admin()->first(), new IpBlockedNotification($ip));
    }
}

// Registro
protected $middleware = [
    // ...
    \App\Http\Middleware\ComprehensiveSecurity::class,
];

// Middleware que checa IP bloqueado
class CheckBlockedIp
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        if (cache()->has("blocked_ip:{$ip}")) {
            abort(403, 'Your IP has been blocked');
        }

        return $next($request);
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
