# 13.5 Otimização de PHP

## Resumo

> **Otimização de PHP** — ajustar código e config do PHP para máxima performance.
>
> **O principal:** OPcache cacheia bytecode (`validate_timestamps=0` em production), config do PHP-FPM (`pm.max_children`).
>
> **Métodos:** Memory management (chunk/lazy), PHP 8 JIT, profiling (Xdebug, Blackfire), Laravel optimize, typed properties.

---

## Conteúdo

- [O que é](#o-que-é)
- [OPcache](#opcache)
- [Configuração do PHP-FPM](#configuração-do-php-fpm)
- [Gerenciamento de memória](#gerenciamento-de-memória)
- [Otimizações do PHP 8.x](#otimizações-do-php-8x)
- [Profiling](#profiling)
- [Otimização de código](#otimização-de-código)
- [Otimizações no Laravel](#otimizações-no-laravel)
- [Exemplos práticos](#exemplos-práticos)
- [Monitoramento de performance](#monitoramento-de-performance)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Ajustar código e config do PHP para máxima performance.

**Frentes principais:**
- OPcache
- Configuração do PHP-FPM
- Memory management
- Profiling

---

## OPcache

**O que é:**
OPcache cacheia o bytecode compilado. Assim o PHP não faz parse em todo request.

**Configuração (php.ini):**

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256        ; MB de memória
opcache.interned_strings_buffer=16   ; Para strings
opcache.max_accelerated_files=10000  ; Quantidade de arquivos
opcache.validate_timestamps=0        ; Production: não checar mudanças
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; Development: checar mudanças
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

**Checagem:**

```php
// Ver o status
<?php
opcache_get_status();

// Limpar OPcache
opcache_reset();
```

**Comando Artisan:**

```bash
# Limpar OPcache
php artisan opcache:clear

# Esquentar o cache (requisitar todas as rotas)
php artisan opcache:compile
```

---

## Configuração do PHP-FPM

**Config do pool (/etc/php/8.2/fpm/pool.d/www.conf):**

```ini
[www]
user = www-data
group = www-data

; Process manager
pm = dynamic
pm.max_children = 50          ; Máximo de processos
pm.start_servers = 5          ; Na subida
pm.min_spare_servers = 5      ; Mínimo idle
pm.max_spare_servers = 10     ; Máximo idle
pm.max_requests = 500         ; Reinicia depois de N requests

; Para carga alta
; pm = static
; pm.max_children = 100

; Timeouts
request_terminate_timeout = 30s
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm-slow.log
```

**Cálculo do pm.max_children:**

```
Memória disponível: 8GB
Memória média por processo: 50MB
Reservar para o sistema: 2GB

max_children = (8GB - 2GB) / 50MB = 120
```

**Checar o status:**

```php
// Ligar a status page
// pm.status_path = /status

// http://localhost/status
// pool:                 www
// process manager:      dynamic
// active processes:     5
// idle processes:       10
```

---

## Gerenciamento de memória

**Memory limit:**

```ini
; php.ini
memory_limit = 256M  ; Requests normais
memory_limit = 512M  ; Comandos Artisan
```

```php
// Aumentar neste script
ini_set('memory_limit', '512M');

// Comando Artisan
php -d memory_limit=512M artisan queue:work
```

**Liberar memória:**

```php
// ❌ RUIM: segura tudo na memória
$users = User::all();  // 100k usuários na memória
foreach ($users as $user) {
    $this->process($user);
}

// ✅ BOM: em pedaços
User::chunk(1000, function ($users) {
    foreach ($users as $user) {
        $this->process($user);
    }
});

// Ou cursor
foreach (User::lazy() as $user) {
    $this->process($user);
}

// Liberar a variável na mão
unset($users);
```

---

## Otimizações do PHP 8.x

**JIT (Just-In-Time compilation):**

```ini
; php.ini
opcache.jit=tracing       ; Modo JIT
opcache.jit_buffer_size=100M
```

**Vantagens do PHP 8:**

```php
// Union types (menos checagens)
function process(int|float $value): int|float
{
    return $value * 2;
}

// Match (mais rápido que switch)
$result = match($status) {
    'pending' => 'Pendente',
    'processing' => 'Processando',
    'completed' => 'Concluído',
};

// Named arguments (menos memória)
User::create(
    name: 'João',
    email: 'joao@email.com'
);

// Nullsafe operator
$country = $user?->address?->country;

// Attributes (no lugar de annotations)
#[Route('/api/users')]
class UserController {}
```

---

## Profiling

**Xdebug profiler:**

```ini
; php.ini
xdebug.mode=profile
xdebug.output_dir=/tmp/xdebug
xdebug.profiler_output_name=cachegrind.out.%p
```

**Análise no PhpStorm:**

```
Tools → Analyze Xdebug Profiler Snapshot
```

**Blackfire:**

```bash
# Instalar
sudo apt-get install blackfire-agent blackfire-php

# Perfilar
blackfire curl http://localhost/slow-page

# Web UI: https://blackfire.io
```

**Laravel Telescope:**

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate

# http://localhost/telescope
# Mostra requests lentos, queries, jobs
```

---

## Otimização de código

**Evitar cálculo à toa:**

```php
// ❌ RUIM: cálculo no loop
for ($i = 0; $i < count($array); $i++) {
    // count() roda em toda iteração
}

// ✅ BOM
$count = count($array);
for ($i = 0; $i < $count; $i++) {
    // ...
}

// ✅ Ainda melhor: foreach
foreach ($array as $item) {
    // ...
}
```

**Operações de string:**

```php
// ❌ RUIM: concatenação lenta
$result = '';
foreach ($items as $item) {
    $result .= $item . "\n";
}

// ✅ BOM: implode
$result = implode("\n", $items);

// ✅ Ou array join
$result = array_reduce($items, fn($carry, $item) => $carry . $item . "\n", '');
```

**Usar tipagem:**

```php
// ❌ RUIM: sem tipos (PHP checa tipo em runtime)
function calculate($a, $b)
{
    return $a + $b;
}

// ✅ BOM: com tipos (o compilador otimiza)
function calculate(int $a, int $b): int
{
    return $a + $b;
}
```

---

## Otimizações no Laravel

**Config cache:**

```bash
# Production: cachear tudo
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Um comando só
php artisan optimize
```

**Autoload optimization:**

```bash
# Production: autoload otimizado
composer install --optimize-autoloader --no-dev

# Ou
composer dump-autoload --optimize
```

**Eager loading:**

```php
// ❌ RUIM: N+1
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;  // N queries
}

// ✅ BOM: 2 queries
$posts = Post::with('user')->get();
```

---

## Exemplos práticos

**Otimizar response da API:**

```php
// ❌ RUIM
public function index()
{
    return User::all();  // Tabela inteira, todos os campos
}

// ✅ BOM
public function index()
{
    return User::select(['id', 'name', 'email'])
        ->paginate(20);
}

// ✅ Com cache
public function index()
{
    return Cache::remember('users.list', 300, function () {
        return User::select(['id', 'name', 'email'])
            ->paginate(20);
    });
}
```

**Otimizar Job:**

```php
// ❌ RUIM: muita memória
class ProcessUsers implements ShouldQueue
{
    public function handle()
    {
        $users = User::all();  // Tabela inteira na memória

        foreach ($users as $user) {
            $this->process($user);
        }
    }
}

// ✅ BOM: chunking
class ProcessUsers implements ShouldQueue
{
    public function handle()
    {
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                $this->process($user);
            }
        });
    }
}
```

**Preloading (PHP 7.4+):**

```php
// preload.php
<?php
opcache_compile_file(__DIR__ . '/vendor/autoload.php');

$files = [
    __DIR__ . '/app/Models/User.php',
    __DIR__ . '/app/Models/Post.php',
    // ... classes usadas com frequência
];

foreach ($files as $file) {
    opcache_compile_file($file);
}
```

```ini
; php.ini
opcache.preload=/path/to/preload.php
```

---

## Monitoramento de performance

**APM (Application Performance Monitoring):**

```bash
# New Relic
composer require newrelic/newrelic-php-agent

# Blackfire
composer require blackfire/blackfire-php-sdk
```

**Métricas customizadas:**

```php
// Medir o tempo de execução
$start = microtime(true);

// ... código ...

$time = microtime(true) - $start;
Log::info('Tempo de processamento', ['time' => $time]);

// Ou via helper
$result = timer(function () {
    return $this->heavyOperation();
});
```

---

## Na entrevista

> "Otimização de PHP: OPcache para cachear bytecode (validate_timestamps=0 em production). PHP-FPM: pm.max_children calculado pela memória, pm=dynamic/static. Memory management: chunk/lazy para volume grande. PHP 8 JIT para tarefa CPU-intensive. Profiling: Xdebug, Blackfire, Telescope. Laravel optimize (config, route, view cache). Autoloader optimization. Typed properties para o JIT otimizar. Evito count() no loop, uso implode no lugar de concatenação."

---

## Exercícios práticos

### Exercício 1: Configure o OPcache para production

**Enunciado:** Crie a config ótima de OPcache para um app Laravel em production.

<details>
<summary>Solução</summary>

```ini
; /etc/php/8.2/fpm/conf.d/10-opcache.ini

[opcache]
; Ligar o OPcache
opcache.enable=1
opcache.enable_cli=0  ; Desligar no CLI (comandos artisan)

; Memória
opcache.memory_consumption=256        ; 256MB para o cache
opcache.interned_strings_buffer=16   ; 16MB para strings
opcache.max_accelerated_files=20000  ; Laravel ~10k arquivos

; Config de production
opcache.validate_timestamps=0        ; NÃO checar mudança de arquivo
opcache.revalidate_freq=0
opcache.fast_shutdown=1

; Otimizações
opcache.save_comments=1              ; Guardar comentários (para Doctrine annotations)
opcache.enable_file_override=1

; Arquivos enormes
opcache.max_file_size=0              ; Sem limite

; JIT (PHP 8+)
opcache.jit=tracing
opcache.jit_buffer_size=100M

; Monitoramento
opcache.error_log=/var/log/php-opcache-errors.log

# Recarregar o PHP-FPM depois da mudança
sudo systemctl reload php8.2-fpm

# Checar o status
php -r "var_dump(opcache_get_status());"

# Limpar OPcache depois do deploy
php artisan opcache:clear
# ou via FPM
sudo systemctl reload php8.2-fpm

# Config de development (diferenças)
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.jit=off
```
</details>

### Exercício 2: Otimizar comando memory-intensive

**Enunciado:** Otimize o comando Artisan que exporta 1 milhão de usuários para CSV.

<details>
<summary>Solução</summary>

```php
// ❌ RUIM: tabela inteira na memória
class ExportUsersCommand extends Command
{
    public function handle()
    {
        $users = User::all();  // 1M users × 500 bytes = 500MB!

        $csv = fopen('users.csv', 'w');

        foreach ($users as $user) {
            fputcsv($csv, [
                $user->id,
                $user->name,
                $user->email,
            ]);
        }

        fclose($csv);
    }
}

// ✅ BOM: Chunk + generator
class ExportUsersCommand extends Command
{
    private const CHUNK_SIZE = 1000;

    public function handle()
    {
        $csv = fopen('users.csv', 'w');

        // Headers
        fputcsv($csv, ['ID', 'Nome', 'Email']);

        $processed = 0;

        // Chunk: carrega de 1000 em 1000
        User::select(['id', 'name', 'email'])
            ->chunk(self::CHUNK_SIZE, function ($users) use ($csv, &$processed) {
                foreach ($users as $user) {
                    fputcsv($csv, [
                        $user->id,
                        $user->name,
                        $user->email,
                    ]);
                }

                $processed += $users->count();
                $this->info("Processados: {$processed}");

                // Liberar memória
                unset($users);
                gc_collect_cycles();
            });

        fclose($csv);

        $this->info('Export concluído!');
    }
}

// ✅ AINDA MELHOR: LazyCollection (PHP 8+)
class ExportUsersCommand extends Command
{
    public function handle()
    {
        $csv = fopen('users.csv', 'w');
        fputcsv($csv, ['ID', 'Nome', 'Email']);

        User::select(['id', 'name', 'email'])
            ->lazy()  // Generator pattern
            ->each(function ($user) use ($csv) {
                fputcsv($csv, [
                    $user->id,
                    $user->name,
                    $user->email,
                ]);
            });

        fclose($csv);
    }
}

// Memória:
// ❌ all(): ~500MB
// ✅ chunk(): ~5MB
// ✅ lazy(): ~1MB

// Executar com memory_limit maior
php -d memory_limit=512M artisan export:users
```
</details>

### Exercício 3: Otimizações do PHP 8

**Enunciado:** Reescreva o código com os recursos do PHP 8 para ganhar performance.

<details>
<summary>Solução</summary>

```php
// ❌ Estilo PHP 7
class OrderService
{
    private $paymentGateway;
    private $logger;

    public function __construct($paymentGateway, $logger)
    {
        $this->paymentGateway = $paymentGateway;
        $this->logger = $logger;
    }

    public function calculateDiscount($order)
    {
        $discount = 0;

        if ($order->type === 'standard') {
            $discount = 5;
        } elseif ($order->type === 'premium') {
            $discount = 10;
        } elseif ($order->type === 'vip') {
            $discount = 20;
        }

        return $discount;
    }

    public function getTotal($order)
    {
        if ($order === null) {
            return null;
        }

        if ($order->user === null) {
            return null;
        }

        if ($order->user->discount === null) {
            return null;
        }

        return $order->user->discount->amount;
    }
}

// ✅ PHP 8 otimizado
class OrderService
{
    // Constructor property promotion (menos código, menos memória)
    public function __construct(
        private PaymentGateway $paymentGateway,
        private LoggerInterface $logger
    ) {}

    // Match expression (mais rápido que switch, menos memória)
    public function calculateDiscount(Order $order): int
    {
        return match($order->type) {
            OrderType::Standard => 5,
            OrderType::Premium => 10,
            OrderType::VIP => 20,
            default => 0,
        };
    }

    // Nullsafe operator (menos checagens)
    public function getTotal(?Order $order): ?float
    {
        return $order?->user?->discount?->amount;
    }

    // Union types (menos checagens em runtime)
    public function process(int|float $amount): int|float
    {
        return $amount * 1.1;
    }

    // Named arguments (legibilidade + menos memória)
    public function createOrder(
        int $userId,
        array $items,
        ?string $discountCode = null,
        bool $isGift = false
    ): Order {
        // ...
    }

    // Uso
    // $order = $service->createOrder(
    //     userId: 1,
    //     items: $items,
    //     isGift: true
    // );
}

// Performance:
// - Constructor promotion: -10% memória
// - Match vs switch: +5% velocidade
// - Nullsafe: +15% velocidade (menos if)
// - Typed properties: +10% velocidade (otimização do JIT)
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
