# 14.5 OPcache

## Resumo

> **OPcache** — cache de bytecode do PHP. Guarda o PHP já compilado. 2-3x mais rápido.
>
> **Production:** `validate_timestamps=0` (não checa se o arquivo mudou), limpa depois do deploy (`php artisan opcache:clear`). **Development:** `validate_timestamps=1`, `revalidate_freq=0` (vê a mudança na hora).
>
> **Preloading** (PHP 7.4+): carrega arquivos na memória quando o PHP-FPM sobe. Monitoring: **hit rate > 99%**, memória suficiente (256-512MB). Laravel: package `appstract/laravel-opcache`.

---

## Conteúdo

- [O que é](#o-que-é)
- [Instalação e configuração](#instalação-e-configuração)
- [Configuração de production](#configuração-de-production)
- [Configuração de development](#configuração-de-development)
- [Limpar o OPcache](#limpar-o-opcache)
- [Preloading](#preloading-php-74)
- [Monitorar o OPcache](#monitorar-o-opcache)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**OPcache:**
Cache de bytecode do PHP. Guarda o PHP já compilado (bytecode), para não compilar de novo em todo request.

**Para quê:**
- Bem mais rápido (não precisa parsear e compilar o arquivo PHP)
- Menos uso de CPU
- 2-3x de performance

**Como funciona:**

```
Sem OPcache:
Request → PHP parseia o arquivo → compila → executa → Response

Com OPcache:
Request → OPcache (bytecode cache) → executa → Response
          ↑ se não está no cache
          PHP parseia → compila → grava no cache
```

---

## Instalação e configuração

**Checar se está instalado:**

```bash
php -v
# Tem que aparecer: with Zend OPcache

php -m | grep opcache
# opcache
```

**php.ini:**

```ini
[opcache]
; Ligar o OPcache
opcache.enable=1

; Ligar no CLI (opcional)
opcache.enable_cli=0

; Memory (MB)
opcache.memory_consumption=128

; Interned strings buffer (MB)
opcache.interned_strings_buffer=8

; Max accelerated files
opcache.max_accelerated_files=10000

; Revalidate frequency (seconds)
opcache.revalidate_freq=2

; Validate timestamps (checar se o arquivo mudou)
opcache.validate_timestamps=1

; Fast shutdown
opcache.fast_shutdown=1
```

---

## Configuração de production

**php.ini (production):**

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000

; IMPORTANTE: desligar revalidate para performance máxima
opcache.validate_timestamps=0  # não checar se o arquivo mudou
opcache.revalidate_freq=0

opcache.fast_shutdown=1
opcache.enable_file_override=1
```

**Depois do deploy:**

```bash
# Limpar o OPcache
php artisan opcache:clear  # Laravel

# Ou via nginx/apache
curl http://example.com/opcache-clear.php
```

---

## Configuração de development

**php.ini (development):**

```ini
[opcache]
opcache.enable=1
opcache.validate_timestamps=1  # checar mudanças
opcache.revalidate_freq=0       # checar toda vez
```

**Por quê:**
- A mudança no código aparece na hora (não precisa recarregar o PHP-FPM)

---

## Package Laravel OPcache

**Composer:**

```bash
composer require appstract/laravel-opcache
```

**Routes:**

```php
// Registram sozinhas
// /opcache/clear
// /opcache/config
// /opcache/status
```

**Artisan:**

```bash
# Limpar o OPcache
php artisan opcache:clear

# Status
php artisan opcache:status

# Config
php artisan opcache:config

# Optimize (preload)
php artisan opcache:optimize
```

---

## Limpar o OPcache

### 1. CLI

```bash
php artisan opcache:clear
```

---

### 2. Endpoint HTTP

**routes/web.php:**

```php
Route::get('/opcache/clear', function () {
    if (function_exists('opcache_reset')) {
        opcache_reset();
        return 'OPcache limpo';
    }
    return 'OPcache não disponível';
})->middleware('auth');  // Proteja!
```

---

### 3. Deploy

**Script de deploy:**

```bash
#!/bin/bash

# Puxar o código
git pull

# Instalar as dependências
composer install --no-dev --optimize-autoloader

# Limpar os caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Limpar o OPcache
php artisan opcache:clear

# Recarregar o PHP-FPM
sudo systemctl reload php8.2-fpm
```

---

## Preloading (PHP 7.4+)

**O que é:**
Carregar arquivos PHP na memória quando o PHP-FPM sobe.

**php.ini:**

```ini
opcache.preload=/var/www/html/preload.php
opcache.preload_user=www-data
```

**preload.php:**

```php
<?php

// Script de preload do Laravel
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Preload do core do Laravel
opcache_compile_file(__DIR__ . '/vendor/laravel/framework/src/Illuminate/Foundation/Application.php');
opcache_compile_file(__DIR__ . '/vendor/laravel/framework/src/Illuminate/Http/Request.php');
// ... outros arquivos usados com frequência
```

**Preload automático no Laravel:**

```bash
# Gerar o arquivo de preload
php artisan opcache:optimize

# Cria bootstrap/cache/opcache.php
```

---

## Monitorar o OPcache

**Código PHP:**

```php
$status = opcache_get_status();

echo "Memória usada: " . $status['memory_usage']['used_memory'] / 1024 / 1024 . " MB\n";
echo "Memória livre: " . $status['memory_usage']['free_memory'] / 1024 / 1024 . " MB\n";
echo "Hit Rate: " . ($status['opcache_statistics']['opcache_hit_rate']) . "%\n";
echo "Scripts em cache: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
echo "Máximo de scripts em cache: " . $status['opcache_statistics']['max_cached_scripts'] . "\n";
```

**Artisan:**

```bash
php artisan opcache:status
```

---

## Métricas

**Métricas importantes:**

### 1. Hit Rate

```
opcache_hit_rate = (hits / (hits + misses)) * 100

> 99% — ótimo
< 95% — precisa de mais memória ou max_accelerated_files
```

---

### 2. Memory Usage

```
Se used_memory está perto de memory_consumption:
→ aumenta opcache.memory_consumption
```

---

### 3. Cached Scripts

```
Se num_cached_scripts está perto de max_cached_scripts:
→ aumenta opcache.max_accelerated_files
```

---

## Problemas e soluções

### 1. Código velho depois do deploy

**Problema:**
Depois do deploy o código velho fica no OPcache.

**Solução:**

```bash
# Limpar o OPcache
php artisan opcache:clear

# Ou recarregar o PHP-FPM
sudo systemctl reload php8.2-fpm
```

---

### 2. Out of Memory

**Problema:**
O OPcache ficou sem memória.

**Solução:**

```ini
; Aumentar a memória
opcache.memory_consumption=512  # era 256
```

---

### 3. Arquivos demais

**Problema:**
Estourou o `max_accelerated_files`.

**Solução:**

```ini
opcache.max_accelerated_files=30000  # era 10000
```

---

## Boas práticas

```
✓ Production: validate_timestamps=0 (performance máxima)
✓ Development: validate_timestamps=1, revalidate_freq=0
✓ Memória suficiente (256-512 MB)
✓ max_accelerated_files > quantidade de arquivos PHP
✓ Limpar o OPcache depois do deploy
✓ Preloading nos arquivos usados com frequência (PHP 7.4+)
✓ Monitorar hit rate (> 99%)
✓ Proteger o endpoint /opcache/clear (auth)
```

---

## Comparação com outros caches

| Cache | O que cacheia | Scope |
|-------|---------------|-------|
| OPcache | PHP bytecode | Por worker do PHP-FPM |
| APCu | User data (key-value) | Por worker do PHP-FPM |
| Redis | Application data | Shared (todos os workers) |
| Memcached | Application data | Shared (todos os workers) |

**OPcache — cache de baixo nível (PHP bytecode).**

**Redis/Memcached — cache de alto nível (application data).**

---

## Automação

**Hook de deploy:**

```yaml
# .gitlab-ci.yml
deploy:
  script:
    - git pull
    - composer install --no-dev --optimize-autoloader
    - php artisan config:cache
    - php artisan route:cache
    - php artisan view:cache
    - php artisan opcache:clear  # Limpar o OPcache
    - sudo systemctl reload php8.2-fpm
```

---

## Na entrevista

> "OPcache é o cache de bytecode do PHP. Guarda o código já compilado. Dá 2-3x de performance. Em production: validate_timestamps=0, não checa se o arquivo mudou, limpa depois do deploy. Em development: validate_timestamps=1, revalidate_freq=0, você vê a mudança na hora. Preloading no PHP 7.4+: carrega arquivos na memória quando o PHP-FPM sobe. Monitoring: hit rate acima de 99%, memória suficiente. No Laravel: package appstract/laravel-opcache, php artisan opcache:clear. Boas práticas: 256-512MB de memória, max_accelerated_files maior que a quantidade de arquivos, proteger o endpoint de clear, automatizar o clear no deploy."

---

## Exercícios práticos

### Exercício 1: Dashboard de monitoramento do OPcache

**Enunciado:** Crie um service para monitorar o OPcache com as métricas: hit rate, memory usage, cached scripts. Inclua um artisan command que imprime a estatística.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

class OPcacheMonitoringService
{
    /**
     * Pegar o status do OPcache
     */
    public function getStatus(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false];
        }

        $status = opcache_get_status(false);
        $config = opcache_get_configuration();

        if ($status === false) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'version' => $config['version']['version'] ?? 'unknown',
            'memory' => $this->getMemoryStats($status),
            'statistics' => $this->getStatistics($status),
            'scripts' => $this->getScriptsStats($status),
            'config' => $this->getImportantConfig($config),
        ];
    }

    private function getMemoryStats(array $status): array
    {
        $memory = $status['memory_usage'];

        $total = $memory['used_memory'] + $memory['free_memory'];
        $usagePercent = $total > 0 ? round(($memory['used_memory'] / $total) * 100, 2) : 0;

        return [
            'used_mb' => round($memory['used_memory'] / 1024 / 1024, 2),
            'free_mb' => round($memory['free_memory'] / 1024 / 1024, 2),
            'wasted_mb' => round($memory['wasted_memory'] / 1024 / 1024, 2),
            'total_mb' => round($total / 1024 / 1024, 2),
            'usage_percent' => $usagePercent,
            'wasted_percent' => $total > 0
                ? round(($memory['wasted_memory'] / $total) * 100, 2)
                : 0,
        ];
    }

    private function getStatistics(array $status): array
    {
        $stats = $status['opcache_statistics'];

        $total = $stats['hits'] + $stats['misses'];
        $hitRate = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;

        return [
            'hits' => $stats['hits'],
            'misses' => $stats['misses'],
            'hit_rate' => $hitRate,
            'blacklist_misses' => $stats['blacklist_misses'] ?? 0,
            'num_cached_scripts' => $stats['num_cached_scripts'],
            'max_cached_scripts' => $stats['max_cached_scripts'],
            'scripts_usage_percent' => $stats['max_cached_scripts'] > 0
                ? round(($stats['num_cached_scripts'] / $stats['max_cached_scripts']) * 100, 2)
                : 0,
            'oom_restarts' => $stats['oom_restarts'] ?? 0,
            'hash_restarts' => $stats['hash_restarts'] ?? 0,
            'manual_restarts' => $stats['manual_restarts'] ?? 0,
        ];
    }

    private function getScriptsStats(array $status): array
    {
        $scripts = $status['scripts'] ?? [];

        return [
            'count' => count($scripts),
            'total_size_mb' => round(
                array_sum(array_column($scripts, 'memory_consumption')) / 1024 / 1024,
                2
            ),
        ];
    }

    private function getImportantConfig(array $config): array
    {
        $directives = $config['directives'];

        return [
            'memory_consumption' => $directives['opcache.memory_consumption'] ?? 0,
            'interned_strings_buffer' => $directives['opcache.interned_strings_buffer'] ?? 0,
            'max_accelerated_files' => $directives['opcache.max_accelerated_files'] ?? 0,
            'validate_timestamps' => (bool)($directives['opcache.validate_timestamps'] ?? false),
            'revalidate_freq' => $directives['opcache.revalidate_freq'] ?? 0,
            'preload' => $directives['opcache.preload'] ?? '',
        ];
    }

    /**
     * Checar a saúde do OPcache
     */
    public function healthCheck(): array
    {
        $status = $this->getStatus();

        if (!$status['enabled']) {
            return [
                'healthy' => false,
                'issues' => ['OPcache não está ligado'],
            ];
        }

        $issues = [];

        // Checar hit rate
        if ($status['statistics']['hit_rate'] < 95) {
            $issues[] = "Hit rate baixo: {$status['statistics']['hit_rate']}% (tem que ser > 95%)";
        }

        // Checar memory usage
        if ($status['memory']['usage_percent'] > 90) {
            $issues[] = "Uso de memória alto: {$status['memory']['usage_percent']}% (tem que ser < 90%)";
        }

        // Checar scripts usage
        if ($status['statistics']['scripts_usage_percent'] > 90) {
            $issues[] = "Uso de scripts alto: {$status['statistics']['scripts_usage_percent']}% (tem que ser < 90%)";
        }

        // Checar restarts
        if ($status['statistics']['oom_restarts'] > 0) {
            $issues[] = "Restarts por OOM detectados: {$status['statistics']['oom_restarts']}";
        }

        return [
            'healthy' => empty($issues),
            'issues' => $issues,
        ];
    }
}

// Artisan Command
namespace App\Console\Commands;

use App\Services\OPcacheMonitoringService;
use Illuminate\Console\Command;

class OPcacheStatusCommand extends Command
{
    protected $signature = 'opcache:status {--health : Mostrar só o health check}';
    protected $description = 'Mostra status e estatísticas do OPcache';

    public function handle(OPcacheMonitoringService $service)
    {
        if ($this->option('health')) {
            $health = $service->healthCheck();

            if ($health['healthy']) {
                $this->info('OPcache está saudável');
            } else {
                $this->error('OPcache tem problemas:');
                foreach ($health['issues'] as $issue) {
                    $this->line("  - {$issue}");
                }
            }

            return $health['healthy'] ? 0 : 1;
        }

        $status = $service->getStatus();

        if (!$status['enabled']) {
            $this->error('OPcache não está ligado');
            return 1;
        }

        $this->info('Status do OPcache:');
        $this->newLine();

        // Memória
        $this->line('<fg=yellow>Uso de memória:</>');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Used', "{$status['memory']['used_mb']} MB"],
                ['Free', "{$status['memory']['free_mb']} MB"],
                ['Wasted', "{$status['memory']['wasted_mb']} MB"],
                ['Total', "{$status['memory']['total_mb']} MB"],
                ['Usage', "{$status['memory']['usage_percent']}%"],
            ]
        );

        // Estatísticas
        $this->line('<fg=yellow>Estatísticas:</>');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Hits', number_format($status['statistics']['hits'])],
                ['Misses', number_format($status['statistics']['misses'])],
                ['Hit Rate', "{$status['statistics']['hit_rate']}%"],
                ['Cached Scripts', "{$status['statistics']['num_cached_scripts']} / {$status['statistics']['max_cached_scripts']}"],
                ['Scripts Usage', "{$status['statistics']['scripts_usage_percent']}%"],
            ]
        );

        // Restarts
        if ($status['statistics']['oom_restarts'] > 0 || $status['statistics']['manual_restarts'] > 0) {
            $this->line('<fg=red>Restarts:</>');
            $this->table(
                ['Tipo', 'Quantidade'],
                [
                    ['OOM', $status['statistics']['oom_restarts']],
                    ['Manual', $status['statistics']['manual_restarts']],
                    ['Hash', $status['statistics']['hash_restarts']],
                ]
            );
        }

        // Health check
        $health = $service->healthCheck();
        $this->newLine();

        if ($health['healthy']) {
            $this->info('✓ OPcache está saudável');
        } else {
            $this->error('✗ OPcache tem problemas:');
            foreach ($health['issues'] as $issue) {
                $this->line("  - {$issue}");
            }
        }

        return 0;
    }
}

// Controller do dashboard
namespace App\Http\Controllers;

use App\Services\OPcacheMonitoringService;

class OPcacheController extends Controller
{
    public function __construct(
        private OPcacheMonitoringService $service
    ) {
        $this->middleware('auth');
    }

    public function status()
    {
        return response()->json([
            'status' => $this->service->getStatus(),
            'health' => $this->service->healthCheck(),
        ]);
    }

    public function clear()
    {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            return response()->json(['message' => 'OPcache limpo']);
        }

        return response()->json(['message' => 'OPcache não disponível'], 400);
    }
}
```
</details>

### Exercício 2: Gerador inteligente de preload do OPcache

**Enunciado:** Crie um service que gera o arquivo preload.php sozinho, com base nas classes mais usadas.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\File;

class OPcachePreloadGenerator
{
    private array $classes = [];
    private string $basePath;

    public function __construct()
    {
        $this->basePath = base_path();
    }

    /**
     * Adicionar classe no preload
     */
    public function addClass(string $class): self
    {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * Adicionar todas as classes de um diretório
     */
    public function addDirectory(string $directory): self
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->addFileToPreload($file->getPathname());
            }
        }

        return $this;
    }

    /**
     * Adicionar as classes core do Laravel
     */
    public function addLaravelCore(): self
    {
        $coreClasses = [
            \Illuminate\Foundation\Application::class,
            \Illuminate\Http\Request::class,
            \Illuminate\Http\Response::class,
            \Illuminate\Routing\Router::class,
            \Illuminate\Support\Facades\Facade::class,
            \Illuminate\Database\Eloquent\Model::class,
            \Illuminate\Database\Eloquent\Builder::class,
            \Illuminate\Support\Collection::class,
        ];

        foreach ($coreClasses as $class) {
            $this->addClass($class);
        }

        return $this;
    }

    /**
     * Adicionar os models do app
     */
    public function addModels(): self
    {
        return $this->addDirectory(app_path('Models'));
    }

    /**
     * Adicionar os controllers
     */
    public function addControllers(): self
    {
        return $this->addDirectory(app_path('Http/Controllers'));
    }

    /**
     * Adicionar o middleware
     */
    public function addMiddleware(): self
    {
        return $this->addDirectory(app_path('Http/Middleware'));
    }

    /**
     * Gerar o arquivo preload.php
     */
    public function generate(string $outputPath = null): string
    {
        $outputPath = $outputPath ?? $this->basePath . '/bootstrap/cache/preload.php';

        $content = $this->generateContent();

        File::put($outputPath, $content);

        return $outputPath;
    }

    private function generateContent(): string
    {
        $files = $this->resolveFiles();

        $content = "<?php\n\n";
        $content .= "// Arquivo de preload do OPcache gerado automaticamente\n";
        $content .= "// Gerado em: " . date('Y-m-d H:i:s') . "\n\n";

        $content .= "// Carrega o autoloader do Composer\n";
        $content .= "require __DIR__ . '/../../vendor/autoload.php';\n\n";

        $content .= "// Preload dos arquivos\n";
        foreach ($files as $file) {
            $content .= "opcache_compile_file('{$file}');\n";
        }

        $content .= "\n// Total de arquivos no preload: " . count($files) . "\n";

        return $content;
    }

    private function resolveFiles(): array
    {
        $files = [];

        foreach ($this->classes as $class) {
            try {
                $reflection = new \ReflectionClass($class);
                $file = $reflection->getFileName();

                if ($file && file_exists($file)) {
                    $files[] = $file;
                }
            } catch (\ReflectionException $e) {
                // Classe não encontrada
                continue;
            }
        }

        return array_unique($files);
    }

    private function addFileToPreload(string $filepath): void
    {
        // Tentar descobrir a classe pelo arquivo
        $content = file_get_contents($filepath);

        // Regex simples de namespace e class
        if (preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatch)) {
            $namespace = $namespaceMatch[1];

            if (preg_match('/class\s+(\w+)/', $content, $classMatch)) {
                $className = $namespace . '\\' . $classMatch[1];
                $this->addClass($className);
            }
        }
    }
}

// Artisan Command
namespace App\Console\Commands;

use App\Services\OPcachePreloadGenerator;
use Illuminate\Console\Command;

class GenerateOPcachePreloadCommand extends Command
{
    protected $signature = 'opcache:generate-preload
                            {--core : Incluir o core do Laravel}
                            {--models : Incluir models}
                            {--controllers : Incluir controllers}
                            {--middleware : Incluir middleware}
                            {--all : Incluir tudo}';

    protected $description = 'Gera o arquivo de preload do OPcache';

    public function handle(OPcachePreloadGenerator $generator)
    {
        $this->info('Gerando o arquivo de preload do OPcache...');

        if ($this->option('all')) {
            $generator->addLaravelCore()
                ->addModels()
                ->addControllers()
                ->addMiddleware();
        } else {
            if ($this->option('core')) {
                $generator->addLaravelCore();
                $this->line('✓ Classes core do Laravel adicionadas');
            }

            if ($this->option('models')) {
                $generator->addModels();
                $this->line('✓ Models adicionados');
            }

            if ($this->option('controllers')) {
                $generator->addControllers();
                $this->line('✓ Controllers adicionados');
            }

            if ($this->option('middleware')) {
                $generator->addMiddleware();
                $this->line('✓ Middleware adicionado');
            }
        }

        $outputPath = $generator->generate();

        $this->info("Arquivo de preload gerado: {$outputPath}");
        $this->newLine();
        $this->line('Coloque no php.ini:');
        $this->line("opcache.preload={$outputPath}");
        $this->line('opcache.preload_user=www-data');

        return 0;
    }
}

// Service Provider para registrar
namespace App\Providers;

use App\Services\OPcachePreloadGenerator;
use Illuminate\Support\ServiceProvider;

class OPcacheServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(OPcachePreloadGenerator::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\GenerateOPcachePreloadCommand::class,
            ]);
        }
    }
}
```
</details>

### Exercício 3: Deploy automatizado com gestão de OPcache

**Enunciado:** Crie um script de deploy que gerencia o OPcache sozinho: warm up (aquecer o cache) depois do deploy, monitoring, rollback se der problema.

<details>
<summary>Solução</summary>

```php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeployOPcacheManager
{
    private string $appUrl;
    private array $servers;

    public function __construct()
    {
        $this->appUrl = config('app.url');
        $this->servers = config('deploy.servers', []);
    }

    /**
     * Pre-deploy: guardar as métricas
     */
    public function preDeploy(): array
    {
        $this->log('Começando a checagem de OPcache no pre-deploy...');

        $metrics = [];

        foreach ($this->servers as $server) {
            $status = $this->getServerOPcacheStatus($server);
            $metrics[$server] = $status;

            $this->log("Server {$server}: Hit Rate = {$status['hit_rate']}%");
        }

        // Guardar as métricas para comparar
        cache()->put('deploy:opcache:pre_metrics', $metrics, 3600);

        return $metrics;
    }

    /**
     * Post-deploy: limpar o OPcache em todos os servidores
     */
    public function postDeploy(): bool
    {
        $this->log('Limpando o OPcache em todos os servidores...');

        $results = [];

        foreach ($this->servers as $server) {
            $success = $this->clearServerOPcache($server);
            $results[$server] = $success;

            if ($success) {
                $this->log("✓ OPcache limpo em {$server}");
            } else {
                $this->log("✗ Falha ao limpar o OPcache em {$server}", 'error');
            }
        }

        return !in_array(false, $results, true);
    }

    /**
     * Warm up: aquecer o cache depois do deploy
     */
    public function warmUp(): bool
    {
        $this->log('Aquecendo o OPcache...');

        $urls = $this->getWarmUpUrls();

        foreach ($urls as $url) {
            try {
                Http::timeout(10)->get($url);
                $this->log("✓ Warm up feito: {$url}");
            } catch (\Exception $e) {
                $this->log("✗ Falha no warm up de {$url}: {$e->getMessage()}", 'error');
            }

            usleep(100000); // delay de 100ms entre requests
        }

        return true;
    }

    /**
     * Verify: checar se o deploy deu certo
     */
    public function verify(): array
    {
        $this->log('Verificando o OPcache depois do deploy...');

        sleep(5); // Esperar um pouco para as métricas acumularem

        $issues = [];

        foreach ($this->servers as $server) {
            $status = $this->getServerOPcacheStatus($server);

            // Checar hit rate
            if ($status['hit_rate'] < 80) {
                $issues[] = "Hit rate baixo em {$server}: {$status['hit_rate']}%";
            }

            // Checar memory
            if ($status['memory_usage'] > 95) {
                $issues[] = "Uso de memória alto em {$server}: {$status['memory_usage']}%";
            }

            // Checar restarts
            if ($status['oom_restarts'] > 0) {
                $issues[] = "Restarts por OOM em {$server}";
            }
        }

        if (empty($issues)) {
            $this->log('✓ Verificação do OPcache ok');
        } else {
            foreach ($issues as $issue) {
                $this->log("✗ {$issue}", 'error');
            }
        }

        return [
            'success' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Rollback: voltar ao estado anterior
     */
    public function rollback(): bool
    {
        $this->log('Fazendo rollback do OPcache...');

        // Limpar o cache para recarregar o código antigo
        return $this->postDeploy();
    }

    private function getServerOPcacheStatus(string $server): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$server}/api/opcache/status");

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'hit_rate' => $data['statistics']['hit_rate'] ?? 0,
                    'memory_usage' => $data['memory']['usage_percent'] ?? 0,
                    'oom_restarts' => $data['statistics']['oom_restarts'] ?? 0,
                ];
            }
        } catch (\Exception $e) {
            $this->log("Falha ao pegar o status do OPcache em {$server}: {$e->getMessage()}", 'error');
        }

        return [
            'hit_rate' => 0,
            'memory_usage' => 0,
            'oom_restarts' => 0,
        ];
    }

    private function clearServerOPcache(string $server): bool
    {
        try {
            $response = Http::timeout(5)
                ->post("{$server}/api/opcache/clear");

            return $response->successful();
        } catch (\Exception $e) {
            $this->log("Falha ao limpar o OPcache em {$server}: {$e->getMessage()}", 'error');
            return false;
        }
    }

    private function getWarmUpUrls(): array
    {
        return [
            $this->appUrl . '/',
            $this->appUrl . '/api/health',
            $this->appUrl . '/blog',
            // Coloque outras URLs importantes
        ];
    }

    private function log(string $message, string $level = 'info'): void
    {
        Log::channel('deploy')->{$level}("[OPcache Deploy] {$message}");
    }
}

// Artisan Command para deploy
namespace App\Console\Commands;

use App\Services\DeployOPcacheManager;
use Illuminate\Console\Command;

class DeployCommand extends Command
{
    protected $signature = 'deploy {--skip-opcache : Pular a gestão do OPcache}';
    protected $description = 'Faz deploy do app com gestão de OPcache';

    public function handle(DeployOPcacheManager $opcacheManager)
    {
        $this->info('Começando o deploy...');

        // Checagens de pre-deploy
        if (!$this->option('skip-opcache')) {
            $this->info('Rodando as checagens de OPcache no pre-deploy...');
            $opcacheManager->preDeploy();
        }

        // Deploy do código (git pull, composer install, etc.)
        $this->info('Fazendo deploy do código...');
        $this->call('down');

        exec('git pull origin main');
        exec('composer install --no-dev --optimize-autoloader');

        $this->call('migrate', ['--force' => true]);
        $this->call('config:cache');
        $this->call('route:cache');
        $this->call('view:cache');

        // Gestão do OPcache no post-deploy
        if (!$this->option('skip-opcache')) {
            $this->info('Limpando o OPcache...');
            if (!$opcacheManager->postDeploy()) {
                $this->error('Falhou ao limpar o OPcache em alguns servidores');

                if ($this->confirm('Fazer rollback?', true)) {
                    $this->call('deploy:rollback');
                    return 1;
                }
            }

            $this->info('Aquecendo o OPcache...');
            $opcacheManager->warmUp();

            $this->info('Verificando o deploy...');
            $result = $opcacheManager->verify();

            if (!$result['success']) {
                $this->error('A verificação do deploy falhou:');
                foreach ($result['issues'] as $issue) {
                    $this->line("  - {$issue}");
                }

                if ($this->confirm('Fazer rollback?', true)) {
                    $this->call('deploy:rollback');
                    return 1;
                }
            }
        }

        $this->call('up');
        $this->info('Deploy concluído com sucesso!');

        return 0;
    }
}

// Script bash de deploy
/*
#!/bin/bash

# deploy.sh

set -e

echo "Começando o deploy..."

# Pre-deploy
php artisan deploy:pre-check

# Puxar o código
git pull origin main

# Instalar as dependências
composer install --no-dev --optimize-autoloader

# Otimizações do Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Limpar o OPcache
php artisan opcache:clear

# Recarregar o PHP-FPM
sudo systemctl reload php8.2-fpm

# Warm up
php artisan cache:warm
php artisan opcache:warm

# Verificar
php artisan deploy:verify

echo "Deploy concluído!"
*/
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
