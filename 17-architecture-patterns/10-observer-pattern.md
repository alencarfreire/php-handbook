# 10.10 Observer Pattern

## Resumo

> **Observer Pattern** — objetos se inscrevem em events e recebem notificação automática.
>
> **Componentes:** Subject (publicador), Observer (assinante), Event (evento).
>
> **Importante:** Laravel: Event + Listeners, Model Observers no Eloquent. ShouldQueue para listeners assíncronos.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Laravel Events e Listeners](#laravel-events-e-listeners)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Observer Pattern — objetos se inscrevem em events. Quando o event acontece, todos os assinantes recebem a notificação.

**Componentes:**
- Subject (publicador)
- Observer (assinante)
- Event (evento)

---

## Como funciona

**Observer básico:**

```php
// Subject (publicador)
interface Subject
{
    public function attach(Observer $observer): void;
    public function detach(Observer $observer): void;
    public function notify(): void;
}

// Observer (assinante)
interface Observer
{
    public function update(Subject $subject): void;
}

// Implementação do Subject
class Order implements Subject
{
    private array $observers = [];
    private string $status;

    public function attach(Observer $observer): void
    {
        $this->observers[] = $observer;
    }

    public function detach(Observer $observer): void
    {
        $key = array_search($observer, $this->observers, true);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->notify();  // Notifica todos os assinantes
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}

// Implementação do Observer
class EmailNotificationObserver implements Observer
{
    public function update(Subject $subject): void
    {
        if ($subject instanceof Order) {
            echo "Email enviado: status do pedido mudou para {$subject->getStatus()}\n";
        }
    }
}

class SmsNotificationObserver implements Observer
{
    public function update(Subject $subject): void
    {
        if ($subject instanceof Order) {
            echo "SMS enviado: status do pedido mudou para {$subject->getStatus()}\n";
        }
    }
}

// Uso
$order = new Order();
$order->attach(new EmailNotificationObserver());
$order->attach(new SmsNotificationObserver());

$order->setStatus('paid');  // Os dois observers recebem a notificação
```

---

## Laravel Events e Listeners

**Event:**

```php
// app/Events/OrderCreated.php
class OrderCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}
}
```

**Listeners:**

```php
// app/Listeners/SendOrderConfirmation.php
class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmationMail($event->order));
    }
}

// app/Listeners/UpdateInventory.php
class UpdateInventory
{
    public function handle(OrderCreated $event): void
    {
        foreach ($event->order->items as $item) {
            $item->product->decrement('stock', $item->quantity);
        }
    }
}

// app/Listeners/SendAdminNotification.php
class SendAdminNotification
{
    public function handle(OrderCreated $event): void
    {
        Notification::send(
            User::where('role', 'admin')->get(),
            new NewOrderNotification($event->order)
        );
    }
}
```

**EventServiceProvider:**

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    OrderCreated::class => [
        SendOrderConfirmation::class,
        UpdateInventory::class,
        SendAdminNotification::class,
    ],
];
```

**Dispatch do event:**

```php
class OrderController extends Controller
{
    public function store(Request $request)
    {
        $order = Order::create($request->validated());

        // Dispatch do event — todos os listeners são chamados
        event(new OrderCreated($order));

        return response()->json($order, 201);
    }
}
```

---

## Quando usar

**Observer para:**
- Notificações
- Log
- Atualizar dados relacionados
- Operações assíncronas

**NÃO use para:**
- Dependência direta entre objetos

---

## Exemplo prático

**Model Observers:**

```php
// app/Observers/UserObserver.php
class UserObserver
{
    public function creating(User $user): void
    {
        // Antes de criar
        $user->uuid = Str::uuid();
    }

    public function created(User $user): void
    {
        // Depois de criar
        event(new UserRegistered($user));
    }

    public function updating(User $user): void
    {
        // Antes de atualizar
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }
    }

    public function updated(User $user): void
    {
        // Depois de atualizar
        Cache::forget("user.{$user->id}");
    }

    public function deleted(User $user): void
    {
        // Depois de deletar
        $user->posts()->delete();
        $user->comments()->delete();
    }
}

// app/Providers/EventServiceProvider.php
public function boot(): void
{
    User::observe(UserObserver::class);
}
```

**Queue Listeners (assíncrono):**

```php
// app/Listeners/SendOrderConfirmation.php
class SendOrderConfirmation implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'emails';
    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new OrderConfirmationMail($event->order));
    }

    public function failed(OrderCreated $event, Throwable $exception): void
    {
        Log::error("Falha ao enviar confirmação do pedido", [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Event Subscribers (vários events):**

```php
// app/Listeners/UserEventSubscriber.php
class UserEventSubscriber
{
    public function handleUserLogin(UserLoggedIn $event): void
    {
        $event->user->update(['last_login_at' => now()]);
    }

    public function handleUserLogout(UserLoggedOut $event): void
    {
        Cache::forget("user.session.{$event->user->id}");
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            UserLoggedIn::class => 'handleUserLogin',
            UserLoggedOut::class => 'handleUserLogout',
        ];
    }
}

// EventServiceProvider
protected $subscribe = [
    UserEventSubscriber::class,
];
```

**Conditional Listeners:**

```php
class SendInvoiceEmail implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user->email)
            ->send(new InvoiceEmail($event->order));
    }

    // Condição: só enfileira pedidos pagos
    public function shouldQueue(OrderCreated $event): bool
    {
        return $event->order->status === 'paid';
    }
}
```

---

## Na entrevista

> "Observer Pattern é inscrição em events. O Subject avisa os Observers. No Laravel: Event + Listeners. Dispatch com `event()`. Listeners entram no EventServiceProvider. Model Observers cobrem events do Eloquent (creating, created, updating, deleted). ShouldQueue para listener assíncrono. Event Subscribers quando um listener cobre vários events. Prós: baixo acoplamento, fácil de estender. Uso: notificação, log, limpar cache, atualizar dado relacionado."

---

## Exercícios práticos

### Exercício 1: Crie um Model Observer para Product

Implemente um `ProductObserver` que cria o slug sozinho, limpa o cache e grava log das mudanças.

<details>
<summary>Solução</summary>

```php
// app/Observers/ProductObserver.php
namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    public function creating(Product $product): void
    {
        // Cria o slug antes de persistir
        if (empty($product->slug)) {
            $product->slug = Str::slug($product->name);
        }

        // Valores padrão
        if (!isset($product->is_active)) {
            $product->is_active = true;
        }

        Log::info('Criando produto', ['name' => $product->name]);
    }

    public function created(Product $product): void
    {
        // Depois de criar, limpa o cache
        Cache::forget('products.all');
        Cache::forget('products.featured');

        Log::info('Produto criado', [
            'id' => $product->id,
            'name' => $product->name
        ]);

        // Dispara o event
        event(new \App\Events\ProductCreated($product));
    }

    public function updating(Product $product): void
    {
        // Se o nome mudou, atualiza o slug
        if ($product->isDirty('name')) {
            $product->slug = Str::slug($product->name);
        }

        // Se desativou, zera o stock
        if ($product->isDirty('is_active') && !$product->is_active) {
            $product->stock = 0;
        }

        Log::info('Atualizando produto', [
            'id' => $product->id,
            'changes' => $product->getDirty()
        ]);
    }

    public function updated(Product $product): void
    {
        // Limpa o cache desse produto
        Cache::forget("product.{$product->id}");
        Cache::forget('products.all');

        // Se o preço mudou, notifica os assinantes
        if ($product->wasChanged('price')) {
            event(new \App\Events\ProductPriceChanged($product));
        }

        Log::info('Produto atualizado', ['id' => $product->id]);
    }

    public function deleted(Product $product): void
    {
        // Apaga dados relacionados
        $product->reviews()->delete();
        $product->images()->delete();

        // Limpa o cache
        Cache::forget("product.{$product->id}");
        Cache::forget('products.all');

        Log::warning('Produto excluído', [
            'id' => $product->id,
            'name' => $product->name
        ]);
    }

    public function restored(Product $product): void
    {
        // Ao restaurar do soft delete
        Cache::forget('products.all');

        Log::info('Produto restaurado', ['id' => $product->id]);
    }

    public function forceDeleted(Product $product): void
    {
        // Na exclusão definitiva
        // Apaga os arquivos de imagem
        if ($product->image_url) {
            Storage::delete($product->image_url);
        }

        Log::warning('Produto excluído em definitivo', ['id' => $product->id]);
    }
}

// app/Providers/EventServiceProvider.php
namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;

class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
    }
}
```
</details>

### Exercício 2: Crie um Event com vários Listeners

Implemente o Event `UserRegistered` com 3 Listeners: envio de email, criação de perfil, bônus de boas-vindas.

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

    public function __construct(
        public User $user
    ) {}
}

// app/Listeners/SendWelcomeEmail.php
namespace App\Listeners;

use App\Events\UserRegistered;
use App\Mail\WelcomeEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'emails';
    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function handle(UserRegistered $event): void
    {
        Mail::to($event->user->email)
            ->send(new WelcomeEmail($event->user));
    }

    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        Log::error('Falha ao enviar email de boas-vindas', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}

// app/Listeners/CreateUserProfile.php
namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\UserProfile;

class CreateUserProfile
{
    public function handle(UserRegistered $event): void
    {
        UserProfile::create([
            'user_id' => $event->user->id,
            'bio' => '',
            'avatar' => 'default-avatar.png',
            'theme' => 'light',
            'language' => 'pt-BR',
            'notifications_enabled' => true,
        ]);
    }
}

// app/Listeners/GiveWelcomeBonus.php
namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\UserBonus;

class GiveWelcomeBonus
{
    public function handle(UserRegistered $event): void
    {
        UserBonus::create([
            'user_id' => $event->user->id,
            'amount' => 100,
            'type' => 'welcome',
            'description' => 'Bônus de boas-vindas',
            'expires_at' => now()->addDays(30),
        ]);
    }
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    UserRegistered::class => [
        SendWelcomeEmail::class,
        CreateUserProfile::class,
        GiveWelcomeBonus::class,
    ],
];

// Uso no Controller
class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $user = User::create($request->validated());

        // Dispatch do event — todos os listeners são chamados
        event(new UserRegistered($user));

        return redirect()->route('dashboard')
            ->with('success', 'Bem-vindo! Confira seu email.');
    }
}
```
</details>

### Exercício 3: Implemente um Event Subscriber

Crie um `UserActivitySubscriber` que escuta vários events do usuário.

<details>
<summary>Solução</summary>

```php
// Events
namespace App\Events;

class UserLoggedIn
{
    public function __construct(public User $user) {}
}

class UserLoggedOut
{
    public function __construct(public User $user) {}
}

class UserProfileUpdated
{
    public function __construct(public User $user) {}
}

class UserPasswordChanged
{
    public function __construct(public User $user) {}
}

// app/Listeners/UserActivitySubscriber.php
namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Events\UserProfileUpdated;
use App\Events\UserPasswordChanged;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserActivitySubscriber
{
    public function handleUserLogin(UserLoggedIn $event): void
    {
        // Atualiza last_login_at
        $event->user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        // Log
        Log::info('Usuário fez login', [
            'user_id' => $event->user->id,
            'ip' => request()->ip(),
        ]);

        // Guarda no cache
        Cache::put(
            "user.session.{$event->user->id}",
            true,
            now()->addHours(2)
        );
    }

    public function handleUserLogout(UserLoggedOut $event): void
    {
        // Tira a sessão do cache
        Cache::forget("user.session.{$event->user->id}");

        Log::info('Usuário fez logout', [
            'user_id' => $event->user->id,
        ]);
    }

    public function handleProfileUpdate(UserProfileUpdated $event): void
    {
        // Limpa o cache do perfil
        Cache::forget("user.profile.{$event->user->id}");

        Log::info('Perfil atualizado', [
            'user_id' => $event->user->id,
            'changes' => $event->user->getChanges(),
        ]);

        // Se o email mudou, precisa verificar de novo
        if ($event->user->wasChanged('email')) {
            $event->user->update(['email_verified_at' => null]);
            event(new \App\Events\EmailVerificationRequired($event->user));
        }
    }

    public function handlePasswordChange(UserPasswordChanged $event): void
    {
        // Avisa o usuário da troca de senha
        Mail::to($event->user->email)
            ->send(new \App\Mail\PasswordChangedMail($event->user));

        Log::warning('Senha alterada', [
            'user_id' => $event->user->id,
            'ip' => request()->ip(),
        ]);

        // Invalida as outras sessões
        $event->user->sessions()
            ->where('id', '!=', session()->getId())
            ->delete();
    }

    // Registro dos events
    public function subscribe(Dispatcher $events): array
    {
        return [
            UserLoggedIn::class => 'handleUserLogin',
            UserLoggedOut::class => 'handleUserLogout',
            UserProfileUpdated::class => 'handleProfileUpdate',
            UserPasswordChanged::class => 'handlePasswordChange',
        ];
    }
}

// app/Providers/EventServiceProvider.php
protected $subscribe = [
    UserActivitySubscriber::class,
];

// Outra forma de registrar no subscribe()
public function subscribe(Dispatcher $events): void
{
    $events->listen(
        UserLoggedIn::class,
        [UserActivitySubscriber::class, 'handleUserLogin']
    );

    $events->listen(
        UserLoggedOut::class,
        [UserActivitySubscriber::class, 'handleUserLogout']
    );

    $events->listen(
        UserProfileUpdated::class,
        [UserActivitySubscriber::class, 'handleProfileUpdate']
    );

    $events->listen(
        UserPasswordChanged::class,
        [UserActivitySubscriber::class, 'handlePasswordChange']
    );
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
