# 5.4 Events & Listeners

## Resumo

> **Events** — eventos no app (OrderCreated, UserRegistered). **Listeners** — quem trata esses events (SendEmail, LogActivity).
>
> **Padrão:** Event-Listener separa a lógica em módulos. Um event → vários handlers.
>
> **Registro:** no `EventServiceProvider`. **Dispatch:** `EventName::dispatch()`.

---

## Conteúdo

- [O que é](#o-que-é)
- [Criar Events/Listeners](#como-funciona)
- [Registro](#como-funciona)
- [Dispatch de events](#como-funciona)
- [Model Events/Observers](#exemplo-prático)
- [Queued Listeners](#exemplo-prático)
- [Quando usar](#quando-usar)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Events — o que aconteceu no app (usuário criado, pedido enviado). Listeners — quem reage (manda email, grava log).

- Event — o que aconteceu
- Listener — o que fazer
- Registro no EventServiceProvider

---

## Como funciona

**Criar o Event:**

```bash
php artisan make:event OrderCreated
```

```php
namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order)
    {
    }
}
```

**Criar o Listener:**

```bash
php artisan make:listener SendOrderConfirmation --event=OrderCreated
```

```php
namespace App\Listeners;

use App\Events\OrderCreated;
use App\Notifications\OrderConfirmation;

class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        $event->order->user->notify(
            new OrderConfirmation($event->order)
        );
    }
}
```

**Registro no EventServiceProvider:**

```php
namespace App\Providers;

use App\Events\OrderCreated;
use App\Listeners\{SendOrderConfirmation, UpdateInventory, NotifyAdmin};
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            SendOrderConfirmation::class,
            UpdateInventory::class,
            NotifyAdmin::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
```

**Dispatch (disparo) do event:**

```php
use App\Events\OrderCreated;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        // Dispara o event
        OrderCreated::dispatch($order);

        // Ou pelo helper
        event(new OrderCreated($order));

        return response()->json($order, 201);
    }
}
```

---

## Quando usar

**Use Events quando:**
- Uma ação gera várias consequências
- Partes diferentes do app precisam reagir ao event
- Precisa de processamento assíncrono (queue)
- Modularidade (separar a lógica)

**Não use quando:**
- Lógica sequencial simples (chame o service direto)
- Só um handler (melhor chamar direto)

---

## Exemplo prático

**Fluxo completo de um pedido:**

```php
// Event
namespace App\Events;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}

// Listeners
namespace App\Listeners;

class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        $event->order->user->notify(
            new OrderConfirmation($event->order)
        );
    }
}

class UpdateInventory
{
    public function handle(OrderCreated $event): void
    {
        foreach ($event->order->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }
}

class NotifyAdmin implements ShouldQueue  // Assíncrono
{
    public function handle(OrderCreated $event): void
    {
        if ($event->order->total > 10000) {
            // Notifica o admin de um pedido grande (acima de R$ 10.000)
            Admin::notify(new LargeOrderNotification($event->order));
        }
    }
}

class RecordAnalytics implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        Analytics::track('order_created', [
            'order_id' => $event->order->id,
            'amount' => $event->order->total,
        ]);
    }
}

// Registro
class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCreated::class => [
            SendOrderConfirmation::class,
            UpdateInventory::class,
            NotifyAdmin::class,
            RecordAnalytics::class,
        ],
    ];
}

// Uso
class OrderService
{
    public function create(User $user, array $data): Order
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $user->id,
                'total' => $this->calculateTotal($data),
            ]);

            foreach ($data['items'] as $item) {
                $order->items()->create($item);
            }

            DB::commit();

            // Dispara todos os listeners
            OrderCreated::dispatch($order);

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

**Event Subscribers (agrupa listeners):**

```php
namespace App\Listeners;

use App\Events\{OrderCreated, OrderPaid, OrderShipped};
use Illuminate\Events\Dispatcher;

class OrderEventSubscriber
{
    public function handleOrderCreated(OrderCreated $event): void
    {
        // Lógica do OrderCreated
    }

    public function handleOrderPaid(OrderPaid $event): void
    {
        // Lógica do OrderPaid
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        // Lógica do OrderShipped
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            OrderCreated::class,
            [OrderEventSubscriber::class, 'handleOrderCreated']
        );

        $events->listen(
            OrderPaid::class,
            [OrderEventSubscriber::class, 'handleOrderPaid']
        );

        $events->listen(
            OrderShipped::class,
            [OrderEventSubscriber::class, 'handleOrderShipped']
        );
    }
}

// Registro no EventServiceProvider
class EventServiceProvider extends ServiceProvider
{
    protected $subscribe = [
        OrderEventSubscriber::class,
    ];
}
```

**Model Events (nativos):**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    // Events automáticos: creating, created, updating, updated, deleting, deleted, etc.

    protected static function booted(): void
    {
        // Event no creating
        static::creating(function (Post $post) {
            $post->slug = Str::slug($post->title);
        });

        // Event depois do created
        static::created(function (Post $post) {
            Cache::forget('posts.all');
        });

        // Event no updating
        static::updating(function (Post $post) {
            if ($post->isDirty('status') && $post->status === 'published') {
                // Publicou
                event(new PostPublished($post));
            }
        });

        // Event no deleting
        static::deleting(function (Post $post) {
            // Apaga os comments relacionados
            $post->comments()->delete();
        });
    }
}
```

**Observer (alternativa aos model events):**

```bash
php artisan make:observer UserObserver --model=User
```

```php
namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function creating(User $user): void
    {
        $user->uuid = Str::uuid();
    }

    public function created(User $user): void
    {
        // Envia o welcome email
        $user->notify(new WelcomeNotification());
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('email')) {
            // Email mudou, manda confirmação
            $user->email_verified_at = null;
        }
    }

    public function deleted(User $user): void
    {
        // Apaga os dados relacionados
        $user->posts()->delete();
        $user->orders()->delete();
    }
}

// Registro no EventServiceProvider ou AppServiceProvider
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

**Queued Listeners (assíncronos):**

```php
namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmation implements ShouldQueue
{
    // Queue de execução
    public $queue = 'emails';

    // Delay antes de executar
    public $delay = 60;  // 60 segundos

    // Número de tentativas
    public $tries = 3;

    public function handle(OrderCreated $event): void
    {
        // Envia o email
    }

    // Tratamento de erro
    public function failed(OrderCreated $event, \Throwable $exception): void
    {
        Log::error('Falha ao enviar confirmação do pedido', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Dispatch condicional:**

```php
// Dispatch só se a condição for verdadeira
OrderCreated::dispatchIf(
    $order->total > 1000,
    $order
);

// Dispatch só se a condição for falsa
OrderCreated::dispatchUnless(
    $order->isFree(),
    $order
);
```

**After Response (roda depois de enviar a response):**

```php
namespace App\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

class OrderCreated implements ShouldDispatchAfterCommit
{
    // Dispatch depois do DB::commit()
}
```

**Closure Listeners (sem classe):**

```php
// No EventServiceProvider
use App\Events\OrderCreated;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen(OrderCreated::class, function (OrderCreated $event) {
        // Lógica simples, sem criar classe
        Log::info('Pedido criado', ['order_id' => $event->order->id]);
    });

    // Wildcard listener
    Event::listen('order.*', function (string $eventName, array $data) {
        // Escuta todos os events order.*
    });
}
```

---

## Na entrevista

> "Events são os eventos (OrderCreated), Listeners são quem trata (SendEmail, UpdateInventory). Registro no EventServiceProvider pelo $listen. Dispatch com EventName::dispatch() ou event(). Queued Listeners com ShouldQueue rodam assíncrono. Model events (creating, created, updating) no booted() ou no Observer. Event Subscriber agrupa listeners. dispatchIf/dispatchUnless para disparo condicional. ShouldDispatchAfterCommit dispara depois do commit."

---

## Exercícios práticos

### Exercício 1: Crie Event + Listeners

**Enunciado:** No registro do usuário precisa: mandar welcome email, criar perfil, gravar no log. Faça isso com Events.

<details>
<summary>Solução</summary>

```php
// app/Events/UserRegistered.php
namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $user) {}
}

// app/Listeners/SendWelcomeEmail.php
namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\WelcomeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendWelcomeEmail implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        $event->user->notify(new WelcomeNotification());
    }
}

// app/Listeners/CreateUserProfile.php
class CreateUserProfile
{
    public function handle(UserRegistered $event): void
    {
        $event->user->profile()->create([
            'bio' => '',
            'avatar' => 'default.png',
        ]);
    }
}

// app/Listeners/LogUserRegistration.php
class LogUserRegistration
{
    public function handle(UserRegistered $event): void
    {
        Log::info('Novo usuário registrado', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
        ]);
    }
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        CreateUserProfile::class,
        LogUserRegistration::class,
    ],
];

// No controller
public function register(Request $request)
{
    $user = User::create($request->validated());

    UserRegistered::dispatch($user);

    return response()->json($user, 201);
}
```
</details>

### Exercício 2: Observer para Post

**Enunciado:** Crie um Observer para o model Post que gera o slug na criação e limpa o cache na atualização.

<details>
<summary>Solução</summary>

```php
// app/Observers/PostObserver.php
namespace App\Observers;

use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

class PostObserver
{
    public function creating(Post $post): void
    {
        // Gera o slug automaticamente
        if (empty($post->slug)) {
            $post->slug = Str::slug($post->title);

            // Checa unicidade
            $originalSlug = $post->slug;
            $count = 1;

            while (Post::where('slug', $post->slug)->exists()) {
                $post->slug = "{$originalSlug}-{$count}";
                $count++;
            }
        }
    }

    public function created(Post $post): void
    {
        // Limpa o cache depois de criar
        Cache::forget('posts.all');
        Cache::forget("posts.category.{$post->category_id}");
    }

    public function updating(Post $post): void
    {
        // Se o status virou published
        if ($post->isDirty('status') && $post->status === 'published') {
            $post->published_at = now();
        }
    }

    public function updated(Post $post): void
    {
        // Limpa o cache depois de atualizar
        Cache::forget("posts.{$post->id}");
        Cache::forget('posts.all');
    }

    public function deleted(Post $post): void
    {
        // Apaga comments e likes
        $post->comments()->delete();
        $post->likes()->delete();

        // Limpa o cache
        Cache::forget("posts.{$post->id}");
        Cache::forget('posts.all');
    }
}

// Registro no AppServiceProvider ou EventServiceProvider
use App\Models\Post;
use App\Observers\PostObserver;

public function boot(): void
{
    Post::observe(PostObserver::class);
}
```
</details>

### Exercício 3: Queued Listener com retries

**Enunciado:** Crie um Listener de SMS que roda na queue, tenta 3 vezes com delay de 60 segundos e loga o erro.

<details>
<summary>Solução</summary>

```php
// app/Events/OrderShipped.php
namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}

// app/Listeners/SendShippingSms.php
namespace App\Listeners;

use App\Events\OrderShipped;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendShippingSms implements ShouldQueue
{
    use InteractsWithQueue;

    // Queue de execução
    public $queue = 'notifications';

    // Número de tentativas
    public $tries = 3;

    // Delay entre tentativas (segundos)
    public $backoff = 60;

    // Timeout de execução (segundos)
    public $timeout = 30;

    public function __construct(
        private SmsService $smsService
    ) {}

    public function handle(OrderShipped $event): void
    {
        $order = $event->order;

        $this->smsService->send(
            $order->user->phone,
            "Seu pedido #{$order->id} foi enviado!"
        );

        Log::info('SMS de envio enviado', [
            'order_id' => $order->id,
            'phone' => $order->user->phone,
        ]);
    }

    // Roda depois de esgotar as tentativas
    public function failed(OrderShipped $event, \Throwable $exception): void
    {
        Log::error('Falha ao enviar SMS de envio depois de todas as tentativas', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Dá para notificar o admin
        // Admin::notify(new SmsFailedNotification($event->order));
    }
}

// Registro no EventServiceProvider
protected $listen = [
    OrderShipped::class => [
        SendShippingSms::class,
    ],
];
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
