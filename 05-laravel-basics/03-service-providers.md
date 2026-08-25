# 4.3 Service Providers

## Resumo

> **Service Provider** — classe que registra serviços no Service Container (container de serviços).
>
> **Métodos:** `register()` para bindings no container, `boot()` para o resto (view composers, validators, observers).
>
> **Importante:** Deferred providers carregam sob demanda. `publishes()` publica recursos do pacote.

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
Service Provider é o lugar onde você registra serviços no Service Container. Todo serviço do Laravel entra por um provider.

**Métodos principais:**
- `register()` — bindings no container
- `boot()` — roda depois de registrar todos os providers

---

## Como funciona

**Estrutura do Service Provider:**

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends ServiceProvider
{
    // register() — só binding no container
    public function register(): void
    {
        // Só bindings no container
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                config('services.payment.key')
            );
        });
    }

    // boot() — depois de registrar todos os providers
    public function boot(): void
    {
        // Aqui você pode usar outros serviços
        View::composer('*', function ($view) {
            $view->with('appName', config('app.name'));
        });

        // Model observers
        User::observe(UserObserver::class);

        // Regras de validação customizadas
        Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^\+55\d{10,11}$/', $value);
        });
    }
}
```

**Registro do provider em config/app.php:**

```php
// config/app.php
'providers' => [
    // Providers do framework
    Illuminate\Auth\AuthServiceProvider::class,
    Illuminate\Broadcasting\BroadcastServiceProvider::class,

    // Providers da app
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\PaymentServiceProvider::class,  // Customizado
],
```

**Deferred Providers (carga sob demanda):**

```php
// Carrega só quando precisa
class PaymentServiceProvider extends ServiceProvider
{
    // Carga sob demanda
    protected $defer = true;  // Deprecated no Laravel 11+

    public function register(): void
    {
        $this->app->singleton(PaymentService::class, function () {
            return new PaymentService();
        });
    }

    // O que disponibiliza
    public function provides(): array
    {
        return [PaymentService::class];
    }
}

// Laravel 11+ (sem $defer)
class PaymentServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class, function () {
            return new PaymentService();
        });
    }

    public function provides(): array
    {
        return [PaymentService::class];
    }
}
```

---

## Quando usar

**Use register() para:**
- Registrar serviços no container
- Bind, singleton, instance
- Config sem depender de outros serviços

**Use boot() para:**
- View composers
- Route macros
- Validation rules
- Event listeners
- Model observers
- Publicar recursos (config, migrations)

---

## Exemplo prático

**Payment Provider customizado:**

```php
// app/Providers/PaymentServiceProvider.php
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind da interface
        $this->app->bind(
            PaymentGateway::class,
            fn() => match (config('payment.driver')) {
                'stripe' => new StripeGateway(config('payment.stripe.key')),
                'paypal' => new PayPalGateway(config('payment.paypal.key')),
                default => throw new \Exception('Driver de pagamento desconhecido')
            }
        );

        // Singleton do PaymentService
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(PaymentGateway::class),
                $app->make('log')
            );
        });
    }

    public function boot(): void
    {
        // Publica o config
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');

        // Event listener
        Event::listen(
            OrderCreated::class,
            ProcessPayment::class
        );
    }
}
```

**Route Service Provider:**

```php
// app/Providers/RouteServiceProvider.php
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Model binding com lógica customizada
        Route::bind('post', function (string $value) {
            return Post::where('slug', $value)
                ->orWhere('id', $value)
                ->firstOrFail();
        });

        // Route macro
        Route::macro('apiResource', function (string $name, string $controller) {
            Route::prefix($name)->group(function () use ($controller) {
                Route::get('/', [$controller, 'index']);
                Route::post('/', [$controller, 'store']);
                Route::get('/{id}', [$controller, 'show']);
                Route::put('/{id}', [$controller, 'update']);
                Route::delete('/{id}', [$controller, 'destroy']);
            });
        });
    }
}
```

**Event Service Provider:**

```php
// app/Providers/EventServiceProvider.php
class EventServiceProvider extends ServiceProvider
{
    // Registro dos listeners
    protected $listen = [
        OrderCreated::class => [
            SendOrderConfirmation::class,
            UpdateInventory::class,
            NotifyAdmin::class,
        ],

        UserRegistered::class => [
            SendWelcomeEmail::class,
        ],
    ];

    public function boot(): void
    {
        // Model events
        User::creating(function (User $user) {
            $user->uuid = Str::uuid();
        });

        // Subscriber
        Event::subscribe(OrderEventSubscriber::class);
    }

    // Descoberta automática de listeners
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }
}
```

**Package Service Provider (criar um pacote):**

```php
// packages/analytics/src/AnalyticsServiceProvider.php
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge do config
        $this->mergeConfigFrom(
            __DIR__.'/../config/analytics.php',
            'analytics'
        );

        // Registro do serviço
        $this->app->singleton(Analytics::class, function ($app) {
            return new Analytics(config('analytics'));
        });

        // Alias
        $this->app->alias(Analytics::class, 'analytics');
    }

    public function boot(): void
    {
        // Publica o config
        $this->publishes([
            __DIR__.'/../config/analytics.php' => config_path('analytics.php'),
        ], 'analytics-config');

        // Publica as views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'analytics');
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/analytics'),
        ], 'analytics-views');

        // Publica as migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publica as routes
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                AnalyticsCommand::class,
            ]);
        }
    }
}
```

**Authorization Service Provider:**

```php
// app/Providers/AuthServiceProvider.php
class AuthServiceProvider extends ServiceProvider
{
    // Policies
    protected $policies = [
        Post::class => PostPolicy::class,
        Comment::class => CommentPolicy::class,
    ];

    public function boot(): void
    {
        // Gates
        Gate::define('view-admin', function (User $user) {
            return $user->isAdmin();
        });

        Gate::define('edit-post', function (User $user, Post $post) {
            return $user->id === $post->user_id;
        });

        // Passport/Sanctum routes
        // Passport::routes();
    }
}
```

**Database Service Provider (macros):**

```php
// app/Providers/DatabaseServiceProvider.php
class DatabaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Macro do Query Builder
        Builder::macro('whereLike', function (string $column, string $value) {
            return $this->where($column, 'LIKE', "%{$value}%");
        });

        // Macro da Collection
        Collection::macro('toUpper', function () {
            return $this->map(fn($value) => strtoupper($value));
        });

        // Uso
        // User::whereLike('name', 'João')->get();
        // collect(['a', 'b'])->toUpper(); // ['A', 'B']
    }
}
```

**Comando para criar o provider:**

```bash
# Criar o provider
php artisan make:provider PaymentServiceProvider

# Registra em config/app.php sozinho (Laravel 11+)
# ou você adiciona na mão
```

---

## Na entrevista

> "Service Provider registra serviços no Service Container. register() é só binding — bind, singleton. boot() é o resto: view composer, validator, observer. Deferred provider carrega só quando precisa. publishes() publica recurso de pacote. A lista fica em config/app.php."

---

## Exercícios práticos

### Exercício 1: Crie um Service Provider customizado

**Enunciado:** Crie um `AnalyticsServiceProvider` para registrar o `AnalyticsService`, que envia eventos para o Google Analytics.

<details>
<summary>Solução</summary>

```php
// 1. Criar o provider
// php artisan make:provider AnalyticsServiceProvider

// 2. Implementação (app/Providers/AnalyticsServiceProvider.php)
namespace App\Providers;

use App\Services\AnalyticsService;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton do AnalyticsService
        $this->app->singleton(AnalyticsService::class, function ($app) {
            return new AnalyticsService(
                apiKey: config('services.analytics.key'),
                enabled: config('services.analytics.enabled', false)
            );
        });

        // Alias para facilitar
        $this->app->alias(AnalyticsService::class, 'analytics');

        // Merge do config (se for pacote)
        $this->mergeConfigFrom(
            __DIR__.'/../../config/analytics.php',
            'analytics'
        );
    }

    public function boot(): void
    {
        // Publica o config
        $this->publishes([
            __DIR__.'/../../config/analytics.php' => config_path('analytics.php'),
        ], 'analytics-config');

        // Macro da Collection
        \Illuminate\Support\Collection::macro('track', function (string $event) {
            app(AnalyticsService::class)->track($event, [
                'count' => $this->count(),
            ]);
            return $this;
        });

        // Event listener
        \Event::listen(
            \App\Events\OrderCreated::class,
            function ($event) {
                app('analytics')->track('order_created', [
                    'order_id' => $event->order->id,
                    'total' => $event->order->total,
                ]);
            }
        );
    }
}

// 3. Service (app/Services/AnalyticsService.php)
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    public function __construct(
        private string $apiKey,
        private bool $enabled
    ) {}

    public function track(string $event, array $data = []): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            Http::post('https://analytics.google.com/api/track', [
                'api_key' => $this->apiKey,
                'event' => $event,
                'data' => $data,
            ]);

            Log::info("Evento de analytics enviado: {$event}");
        } catch (\Exception $e) {
            Log::error("Falha no tracking de analytics: {$e->getMessage()}");
        }
    }
}

// 4. Config (config/analytics.php)
return [
    'key' => env('ANALYTICS_KEY'),
    'enabled' => env('ANALYTICS_ENABLED', false),
];

// 5. Registrar (config/app.php)
'providers' => [
    // ...
    App\Providers\AnalyticsServiceProvider::class,
],

// 6. Uso
use App\Services\AnalyticsService;

class OrderController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics
    ) {}

    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        // Track
        $this->analytics->track('order_created', [
            'order_id' => $order->id,
            'total' => $order->total,
        ]);

        return new OrderResource($order);
    }
}

// Ou pelo alias
app('analytics')->track('page_view', ['url' => request()->url()]);

// Ou pela macro
$orders = Order::all()->track('orders_fetched');
```
</details>

### Exercício 2: register() vs boot()

**Enunciado:** Qual a diferença? Quando usar cada um?

<details>
<summary>Solução</summary>

```php
// ✅ register() — SÓ para registrar no container
// - Roda PRIMEIRO em TODOS os providers
// - NÃO use outros serviços (podem ainda não estar registrados)
// - Só bind(), singleton(), instance()

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ PODE
        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService(
                config('payment.driver')  // Config já está disponível
            );
        });

        // ✅ PODE
        $this->app->bind(PaymentGateway::class, StripeGateway::class);

        // ✅ PODE
        $this->mergeConfigFrom(__DIR__.'/../../config/payment.php', 'payment');

        // ❌ NÃO — o outro serviço pode ainda não estar registrado
        $logger = app(LoggerInterface::class);  // Erro!

        // ❌ NÃO — View ainda não está pronto
        View::composer('*', function ($view) {});  // Erro!

        // ❌ NÃO — DB pode ainda não estar pronta
        User::observe(UserObserver::class);  // Erro!
    }
}

// ✅ boot() — para todo o resto
// - Roda DEPOIS de registrar TODOS os providers
// - Pode usar QUALQUER serviço do container
// - View composers, validators, observers, macros, event listeners

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ View Composers (View já está disponível)
        View::composer('layouts.app', function ($view) {
            $view->with('appName', config('app.name'));
        });

        // ✅ Model Observers (Eloquent já está disponível)
        User::observe(UserObserver::class);

        // ✅ Validation Rules (Validator já está disponível)
        Validator::extend('phone', function ($attribute, $value) {
            return preg_match('/^\+55\d{10,11}$/', $value);
        });

        // ✅ Route Macros (Router já está disponível)
        Route::macro('apiResource', function (string $name, string $controller) {
            // ...
        });

        // ✅ Collection Macros
        Collection::macro('toUpper', function () {
            return $this->map(fn($v) => strtoupper($v));
        });

        // ✅ Event Listeners (Event já está disponível)
        Event::listen(OrderCreated::class, SendOrderConfirmation::class);

        // ✅ Publicar recursos
        $this->publishes([
            __DIR__.'/../../config/payment.php' => config_path('payment.php'),
        ], 'payment-config');

        // ✅ Carregar migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // ✅ Carregar routes
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // ✅ Usar outros serviços
        $logger = app(LoggerInterface::class);  // OK!
        $logger->info('AppServiceProvider bootou');
    }
}

// Exemplo de ERRO
class BadServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ❌ RUIM: usa DB no register()
        $this->app->singleton(SettingsService::class, function ($app) {
            $settings = DB::table('settings')->first();  // Pode não funcionar!
            return new SettingsService($settings);
        });
    }

    // ✅ CERTO: usa DB no boot()
    public function boot(): void
    {
        $this->app->singleton(SettingsService::class, function ($app) {
            $settings = DB::table('settings')->first();  // Funciona!
            return new SettingsService($settings);
        });
    }
}
```

**Regra:**
- **register()** → só **bind(), singleton(), instance()**
- **boot()** → o resto (view, validators, observers, events, macros)
</details>

### Exercício 3: Deferred Provider

**Enunciado:** Crie um Deferred Provider para um serviço lento (por exemplo, um cliente de API) que carrega só quando precisa.

<details>
<summary>Solução</summary>

```php
// Laravel 10 e abaixo
namespace App\Providers;

use App\Services\SlowApiClient;
use Illuminate\Support\ServiceProvider;

class SlowApiServiceProvider extends ServiceProvider
{
    // Carga sob demanda
    protected $defer = true;

    public function register(): void
    {
        $this->app->singleton(SlowApiClient::class, function ($app) {
            // Inicialização lenta (5 segundos)
            sleep(5);

            return new SlowApiClient(
                apiKey: config('services.slow_api.key'),
                baseUrl: config('services.slow_api.url')
            );
        });
    }

    // O que disponibiliza (quando carregar)
    public function provides(): array
    {
        return [SlowApiClient::class];
    }
}

// Laravel 11+
namespace App\Providers;

use App\Services\SlowApiClient;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SlowApiServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(SlowApiClient::class, function ($app) {
            return new SlowApiClient(
                apiKey: config('services.slow_api.key'),
                baseUrl: config('services.slow_api.url')
            );
        });
    }

    public function provides(): array
    {
        return [SlowApiClient::class];
    }
}

// Registro (config/app.php)
'providers' => [
    // ...
    App\Providers\SlowApiServiceProvider::class,
],

// Uso
class ReportController extends Controller
{
    // O provider carrega SÓ quando este controller for chamado
    public function __construct(
        private SlowApiClient $apiClient
    ) {}

    public function generate()
    {
        $data = $this->apiClient->fetchData();
        // ...
    }
}

// Outros controllers NÃO carregam o SlowApiClient
class UserController extends Controller
{
    public function index()
    {
        // SlowApiServiceProvider NÃO carregou (mais rápido!)
        return User::all();
    }
}

// Service
namespace App\Services;

class SlowApiClient
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl
    ) {
        // Inicialização lenta
        // Carrega certificados, conecta na API etc.
    }

    public function fetchData(): array
    {
        // Request da API
        return [];
    }
}
```

**Quando usar Deferred Providers:**
- Serviços lentos (clientes de API, inicialização pesada)
- Serviços que quase ninguém usa
- Pacotes com dependência pesada

**Prós:**
- Acelera o boot da app
- Economiza memória

**Contras:**
- Não serve se outro provider precisa do serviço no `boot()`
- Não serve para serviço global (Logger, Cache)

```php
// Teste: quando o provider carrega
Route::get('/test', function () {
    // SlowApiServiceProvider ainda NÃO carregou

    app(SlowApiClient::class);  // Agora carrega (5 segundos)

    // SlowApiServiceProvider carregou

    return 'OK';
});
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
