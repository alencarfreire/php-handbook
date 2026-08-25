# 5.6 Notifications

## Resumo

> **Notifications** — sistema único para enviar notificações por canais diferentes (mail, database, SMS, Slack, broadcast).
>
> **Criar:** `make:notification OrderShipped`. O método `via()` define os canais, `toMail()`/`toArray()` — o formato.
>
> **Enviar:** `$user->notify(new Notification())`. O canal database guarda no banco, `markAsRead()` marca como lida. `ShouldQueue` para ir para a queue.

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
Notifications — sistema para enviar notificações por canais diferentes (email, SMS, Slack, database). Uma interface só para todos os tipos.

**Canais:**
- Mail (email)
- Database (no banco)
- Broadcast (WebSockets)
- SMS (Vonage/Twilio)
- Slack

---

## Como funciona

**Criar a Notification:**

```bash
php artisan make:notification OrderShipped
```

```php
namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    // Canais de entrega
    public function via(object $notifiable): array
    {
        // Enviar por email e database
        return ['mail', 'database'];
    }

    // Notificação por email
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pedido enviado')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Seu pedido #{$this->order->id} foi enviado.")
            ->action('Ver pedido', url("/orders/{$this->order->id}"))
            ->line('Obrigado pela compra!');
    }

    // Notificação no database
    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'message' => "Seu pedido #{$this->order->number} foi enviado.",
        ];
    }
}
```

**Enviar notificações:**

```php
use App\Notifications\OrderShipped;

// Enviar para um usuário
$user = User::find(1);
$user->notify(new OrderShipped($order));

// Enviar para vários
Notification::send($users, new OrderShipped($order));

// Enviar para anônimo (sem model User)
Notification::route('mail', 'guest@email.com')
    ->notify(new OrderShipped($order));
```

**Trait Notifiable no model:**

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    // Email customizado para notificações
    public function routeNotificationForMail(): string
    {
        return $this->notification_email ?: $this->email;
    }

    // Telefone customizado para SMS
    public function routeNotificationForVonage(): string
    {
        return $this->phone;
    }

    // Slack webhook customizado
    public function routeNotificationForSlack(): string
    {
        return $this->slack_webhook_url;
    }
}
```

---

## Quando usar

**Use Notifications quando:**
- Precisa enviar por vários canais
- Notificações para o usuário (pedidos, comentários, etc.)
- Precisa de queue (`ShouldQueue`)
- Precisa de histórico (canal database)

**Não use quando:**
- Email simples (use Mailable direto)
- Log de sistema (use Log)

---

## Exemplo prático

**Notificação completa com escolha de canal:**

```php
namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Messages\{MailMessage, SlackMessage};
use Illuminate\Notifications\Notification;

class OrderCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Email para usuários
        if ($notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }

        // SMS para usuários premium
        if ($notifiable->isPremium()) {
            $channels[] = 'vonage';
        }

        // Slack para admins
        if ($notifiable->isAdmin()) {
            $channels[] = 'slack';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo pedido #' . $this->order->number)
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Seu pedido #{$this->order->number} foi criado.")
            ->line("Total: R$ {$this->order->total}")
            ->action('Ver pedido', route('orders.show', $this->order))
            ->line('Obrigado pelo pedido!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'total' => $this->order->total,
            'message' => "Pedido #{$this->order->number} criado.",
        ];
    }

    public function toVonage(object $notifiable): array
    {
        return [
            'content' => "Seu pedido #{$this->order->number} foi criado. Total: R$ {$this->order->total}",
        ];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->from('Bot de Pedidos', ':package:')
            ->to('#orders')
            ->content("Novo pedido #{$this->order->number}")
            ->attachment(function ($attachment) {
                $attachment->title('Detalhes do pedido')
                    ->fields([
                        'Pedido' => $this->order->number,
                        'Cliente' => $this->order->user->name,
                        'Total' => 'R$ ' . $this->order->total,
                    ])
                    ->action('Ver pedido', route('admin.orders.show', $this->order));
            });
    }
}
```

**Database Notifications (guardar no banco):**

```bash
# Criar a tabela
php artisan notifications:table
php artisan migrate
```

```php
// Pegar as notificações do usuário
$notifications = $user->notifications;  // Todas
$unread = $user->unreadNotifications;  // Não lidas

// Marcar como lida
$notification = $user->notifications()->first();
$notification->markAsRead();

// Marcar todas como lidas
$user->unreadNotifications->markAsRead();

// Apagar a notificação
$notification->delete();

// Filtrar
$orderNotifications = $user->notifications()
    ->where('type', OrderShipped::class)
    ->get();
```

**API de notificações:**

```php
// Controller
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()
            ->notifications()
            ->paginate(20);
    }

    public function unread(Request $request)
    {
        return $request->user()
            ->unreadNotifications()
            ->get();
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->markAsRead();

        return response()->noContent();
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return response()->noContent();
    }
}
```

**On-Demand Notifications (sem model):**

```php
use Illuminate\Support\Facades\Notification;

// Enviar para um guest
Notification::route('mail', 'guest@email.com')
    ->route('vonage', '+5511987654321')
    ->notify(new InvoicePaid($invoice));

// Enviar para vários canais
Notification::route('mail', [
    'suporte@email.com',
    'admin@email.com',
])->notify(new ErrorOccurred($error));
```

**Canal customizado:**

```bash
php artisan make:notification-channel TelegramChannel
```

```php
namespace App\Channels;

use Illuminate\Notifications\Notification;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        // Pegar o Telegram chat ID
        $chatId = $notifiable->routeNotificationFor('telegram');

        if (!$chatId) {
            return;
        }

        // Pegar os dados da notification
        $message = $notification->toTelegram($notifiable);

        // Enviar no Telegram
        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
        ]);
    }
}

// Notification
public function via(object $notifiable): array
{
    return [TelegramChannel::class];
}

public function toTelegram(object $notifiable): string
{
    return "Pedido #{$this->order->number} criado!";
}

// User model
public function routeNotificationForTelegram(): string
{
    return $this->telegram_chat_id;
}
```

**Notifications condicionais:**

```php
class OrderShipped extends Notification
{
    public function via(object $notifiable): array
    {
        $channels = [];

        // Email só se as notificações estiverem ligadas
        if ($notifiable->notify_via_email) {
            $channels[] = 'mail';
        }

        // SMS só para premium
        if ($notifiable->isPremium() && $notifiable->phone) {
            $channels[] = 'vonage';
        }

        // Sempre no database
        $channels[] = 'database';

        return $channels;
    }
}
```

**Broadcast Notifications (real-time):**

```php
use Illuminate\Notifications\Messages\BroadcastMessage;

class NewMessage extends Notification
{
    public function via(object $notifiable): array
    {
        return ['broadcast', 'database'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'message' => $this->message,
            'user' => $this->user->name,
        ]);
    }

    // Canal do broadcast
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->notifiable->id}")];
    }
}

// Frontend (Laravel Echo)
Echo.private(`user.${userId}`)
    .notification((notification) => {
        console.log(notification);
        // Mostrar a notificação
    });
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Notifications — sistema para enviar notificações por canais diferentes
- Uma interface só para mail, database, SMS, Slack, broadcast
- Criar: `php artisan make:notification OrderShipped`

**Estrutura:**
```php
via()      // Define os canais ['mail', 'database']
toMail()   // Formato do email
toArray()  // Formato do database/broadcast
```

**Envio:**
```php
$user->notify(new OrderShipped($order));           // Um usuário
Notification::send($users, new OrderShipped());    // Vários
Notification::route('mail', 'email@email.com')     // Sem model
    ->notify(new OrderShipped());
```

**Database Notifications:**
- Tabela: `notifications:table` + `migrate`
- Pegar: `$user->notifications`, `$user->unreadNotifications`
- Lida: `$notification->markAsRead()`

**Avançado:**
- **ShouldQueue** — envio na queue (assíncrono)
- **Custom channels** — canais próprios (Telegram, etc.)
- **Broadcast** — real-time via WebSockets
- **Conditional** — escolha do canal por condição

---

## Exercícios práticos

### Exercício 1: Notification com escolha de canal

**Enunciado:** Crie `CommentPostedNotification`. Se o usuário tiver notificações por email ligadas — envie email. Se não — só no database. Usuários premium também recebem SMS.

<details>
<summary>Solução</summary>

```php
namespace App\Notifications;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentPostedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Email se as notificações estiverem ligadas
        if ($notifiable->email_notifications_enabled) {
            $channels[] = 'mail';
        }

        // SMS para premium
        if ($notifiable->isPremium() && $notifiable->phone) {
            $channels[] = 'vonage';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo comentário no seu post')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("{$this->comment->user->name} comentou no seu post:")
            ->line("\"{$this->comment->body}\"")
            ->action('Ver comentário', route('posts.show', $this->comment->post_id))
            ->line('Obrigado por usar nosso app!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'post_id' => $this->comment->post_id,
            'author' => $this->comment->user->name,
            'body' => $this->comment->body,
            'message' => "{$this->comment->user->name} comentou no seu post",
        ];
    }

    public function toVonage(object $notifiable): array
    {
        return [
            'content' => "Novo comentário de {$this->comment->user->name}",
        ];
    }
}

// Envio
$post->user->notify(new CommentPostedNotification($comment));
```
</details>

### Exercício 2: API de Database Notifications

**Enunciado:** Crie um API endpoint para notificações: lista todas, só as não lidas, marcar como lida, apagar.

<details>
<summary>Solução</summary>

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread', [NotificationController::class, 'unread']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
});

// app/Http/Controllers/NotificationController.php
namespace App\Http/Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unread(Request $request)
    {
        $notifications = $request->user()
            ->unreadNotifications()
            ->get();

        return response()->json([
            'count' => $notifications->count(),
            'data' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notificação marcada como lida',
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->unreadNotifications()
            ->markAsRead();

        return response()->json([
            'message' => 'Todas as notificações marcadas como lidas',
        ]);
    }

    public function destroy(Request $request, string $id)
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return response()->noContent();
    }
}
```
</details>

### Exercício 3: Custom Notification Channel (Telegram)

**Enunciado:** Crie um canal customizado para enviar notificações no Telegram.

<details>
<summary>Solução</summary>

```php
// app/Channels/TelegramChannel.php
namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        // Pegar o chat_id do usuário
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if (!$chatId) {
            return;
        }

        // Pegar a mensagem da notification
        $message = $notification->toTelegram($notifiable);

        // Enviar na API do Telegram
        Http::post("https://api.telegram.org/bot" . config('services.telegram.bot_token') . "/sendMessage", [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}

// app/Notifications/OrderShipped.php
namespace App\Notifications;

use App\Channels\TelegramChannel;
use Illuminate\Notifications\Notification;

class OrderShipped extends Notification
{
    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database', TelegramChannel::class];
    }

    public function toTelegram(object $notifiable): string
    {
        return "<b>Pedido enviado</b>\n\n" .
               "Seu pedido #{$this->order->number} foi enviado!\n" .
               "Rastreio: {$this->order->tracking_number}";
    }

    // ... toMail(), toArray()
}

// app/Models/User.php
public function routeNotificationForTelegram(): ?string
{
    return $this->telegram_chat_id;
}

// config/services.php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
],

// Uso
$user->notify(new OrderShipped($order));
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
