# 17.3 Redis Pub/Sub

## Resumo

> **Redis Pub/Sub** — sistema fire-and-forget para mensagens em real-time. O publisher manda no channel, todos os subscribers online recebem na hora.
>
> **O pulo do gato:** Sem garantia de entrega e sem persistência. Se o subscriber está offline — a mensagem some.
>
> **Casos de uso:** Notificações em real-time, WebSockets, chat, live updates, cache invalidation.

---

## Conteúdo

- [O que é](#o-que-é)
- [O básico](#o-básico)
- [Exemplo em PHP](#exemplo-em-php)
- [Laravel Broadcasting](#laravel-broadcasting)
- [Private Channels](#private-channels)
- [Presence Channels](#presence-channels)
- [Laravel Reverb](#laravel-reverb-novo-no-laravel-11)
- [Exemplos práticos](#exemplos-práticos)
- [Limitações do Redis Pub/Sub](#limitações-do-redis-pubsub)
- [Redis Pub/Sub vs Streams](#redis-pubsub-vs-streams)
- [Monitoring](#monitoring)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Redis Pub/Sub:**
Sistema simples de publish/subscribe para mensagens em real-time via Redis.

**Para quê:**
- Notificações em real-time
- Broadcasting de events
- Backend de WebSocket
- Alternativa simples ao RabbitMQ/Kafka nos casos simples

**Diferença da Queue:**
```
Queue (Redis List):
- A mensagem some depois de recebida
- Garantia de entrega
- Persistência

Pub/Sub:
- Fire and forget (sem garantia de entrega)
- Subscribers só recebem se estiverem online
- Sem persistência
```

---

## O básico

**Componentes:**

```
Publisher → Channel → Subscriber 1
                    → Subscriber 2
                    → Subscriber 3
```

**Comandos:**

```bash
# Publisher
PUBLISH channel "message"

# Subscriber
SUBSCRIBE channel

# Pattern subscription
PSUBSCRIBE news.*  # Subscribe em news.sport, news.tech, etc.
```

---

## Exemplo em PHP

**Publisher:**

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Publish da mensagem no channel
$redis->publish('notifications', json_encode([
    'type' => 'new_message',
    'user_id' => 123,
    'message' => 'Olá!'
]));

// Devolve quantos subscribers receberam
```

**Subscriber:**

```php
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Subscribe no channel
$redis->subscribe(['notifications'], function ($redis, $channel, $message) {
    echo "Channel: $channel\n";
    echo "Message: $message\n";

    $data = json_decode($message, true);

    // Processar...
});

// Bloqueia a execução e fica escutando
```

**Pattern Subscribe:**

```php
$redis->psubscribe(['user.*'], function ($redis, $pattern, $channel, $message) {
    // Recebe mensagens de:
    // user.123, user.456, user.created, etc.

    echo "Pattern: $pattern\n";
    echo "Channel: $channel\n";
    echo "Message: $message\n";
});
```

---

## Laravel Broadcasting

**config/broadcasting.php:**

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
],
```

**.env:**

```env
BROADCAST_DRIVER=redis
```

**Event:**

```php
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewMessage implements ShouldBroadcast
{
    public function __construct(
        public string $message,
        public int $userId
    ) {}

    public function broadcastOn()
    {
        return new Channel('notifications');
    }

    public function broadcastAs()
    {
        return 'new.message';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'user_id' => $this->userId,
        ];
    }
}

// Trigger
event(new NewMessage('Olá!', 123));
```

**Frontend (Laravel Echo):**

```javascript
import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: window.location.hostname + ':6001'
});

// Subscribe no channel
Echo.channel('notifications')
    .listen('.new.message', (e) => {
        console.log('Nova mensagem:', e);
    });
```

---

## Private Channels

**Event:**

```php
class NewMessage implements ShouldBroadcast
{
    public function broadcastOn()
    {
        // Private channel de um user específico
        return new PrivateChannel('user.' . $this->userId);
    }
}
```

**routes/channels.php:**

```php
// Autorização dos private channels
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

**Frontend:**

```javascript
// Private channel exige auth
Echo.private('user.123')
    .listen('.new.message', (e) => {
        console.log(e);
    });
```

---

## Presence Channels

**Para "who's online":**

**Event:**

```php
class UserTyping implements ShouldBroadcast
{
    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->chatId);
    }
}
```

**Frontend:**

```javascript
Echo.join('chat.1')
    .here((users) => {
        // Lista de users online agora
        console.log('Online:', users);
    })
    .joining((user) => {
        // User entrou
        console.log('Entrou:', user);
    })
    .leaving((user) => {
        // User saiu
        console.log('Saiu:', user);
    })
    .listen('.user.typing', (e) => {
        console.log('User digitando:', e.user);
    });
```

---

## Laravel Reverb (novo no Laravel 11)

**O que é:**
WebSocket server oficial do Laravel (alternativa a Pusher, Socket.io).

**Instalação:**

```bash
php artisan install:broadcasting
```

**Rodar:**

```bash
php artisan reverb:start
```

**Configuração:**

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=my-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
```

**Frontend:**

```javascript
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
});
```

---

## Exemplos práticos

### 1. Notificações em real-time

**Backend:**

```php
// Quando o user recebe a notification
class NotificationSent
{
    public function handle(DatabaseNotification $notification)
    {
        broadcast(new NewNotification($notification));
    }
}

class NewNotification implements ShouldBroadcast
{
    public function __construct(
        public DatabaseNotification $notification
    ) {}

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->notification->notifiable_id);
    }
}
```

**Frontend:**

```javascript
Echo.private(`user.${userId}`)
    .notification((notification) => {
        // Mostrar toast
        toastr.success(notification.message);

        // Atualizar o badge
        updateNotificationBadge();
    });
```

---

### 2. Chat

**Backend:**

```php
class MessageSent implements ShouldBroadcast
{
    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn()
    {
        return new PresenceChannel('chat.' . $this->message->chat_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'user' => $this->message->user->only(['id', 'name', 'avatar']),
            'text' => $this->message->text,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}

// Enviar mensagem
public function sendMessage(Request $request, Chat $chat)
{
    $message = $chat->messages()->create([
        'user_id' => auth()->id(),
        'text' => $request->text,
    ]);

    broadcast(new MessageSent($message))->toOthers();  // Não envia para você mesmo

    return response()->json($message);
}
```

**Frontend:**

```javascript
// Entrar no chat
Echo.join(`chat.${chatId}`)
    .here((users) => {
        renderOnlineUsers(users);
    })
    .joining((user) => {
        addOnlineUser(user);
    })
    .leaving((user) => {
        removeOnlineUser(user);
    })
    .listen('MessageSent', (e) => {
        appendMessage(e.message);
    });

// Enviar mensagem
function sendMessage(text) {
    axios.post(`/chats/${chatId}/messages`, { text })
        .then(response => {
            // Adicionar a própria mensagem localmente
            appendMessage(response.data);
        });
}
```

---

### 3. Live updates no dashboard

**Backend:**

```php
// A cada minuto
class UpdateDashboardMetrics
{
    public function handle()
    {
        $metrics = [
            'users_online' => Cache::get('users:online:count'),
            'revenue_today' => Order::whereDate('created_at', today())->sum('total'),
            'orders_today' => Order::whereDate('created_at', today())->count(),
        ];

        broadcast(new DashboardMetricsUpdated($metrics));
    }
}
```

**Frontend:**

```javascript
Echo.channel('dashboard')
    .listen('DashboardMetricsUpdated', (e) => {
        document.getElementById('users-online').textContent = e.users_online;
        document.getElementById('revenue').textContent = e.revenue_today;
        document.getElementById('orders').textContent = e.orders_today;
    });
```

---

## Limitações do Redis Pub/Sub

**❌ O que NÃO faz:**

```
1. Sem garantia de entrega
   - Se o subscriber está offline → a mensagem some

2. Sem persistência
   - As mensagens não são salvas

3. Sem replay
   - Não dá para ler mensagens antigas

4. At-most-once delivery
   - A mensagem pode sumir, mas não duplica
```

**✅ Quando usar:**

```
- Notificações em real-time (pode perder)
- Live updates (dashboard, chat)
- Pub/Sub dentro de um app só
- Casos simples, sem garantia rígida
```

**❌ Quando NÃO usar:**

```
- Mensagens críticas (billing, pedidos)
- Precisa de garantia de entrega
- Precisa de replay dos events
- Carga alta (> 10k msg/sec)
```

---

## Redis Pub/Sub vs Streams

**Redis Streams (alternativa):**

```redis
# Streams = pub/sub com persistência
XADD mystream * field1 value1 field2 value2

# Consumer groups
XREADGROUP GROUP mygroup consumer1 STREAMS mystream >
```

**Vantagens do Streams:**
- ✅ Persistência (as mensagens ficam salvas)
- ✅ Consumer groups
- ✅ Acknowledgments
- ✅ Replay de mensagens antigas

**Use Streams quando:**
- Precisa de garantia de entrega
- O consumer pode ficar offline
- Precisa de replay

---

## Monitoring

**Redis CLI:**

```bash
# Lista de channels
PUBSUB CHANNELS

# Quantos subscribers no channel
PUBSUB NUMSUB channel_name

# Quantas pattern subscriptions
PUBSUB NUMPAT
```

**Laravel Horizon:**
```bash
composer require laravel/horizon
php artisan horizon:install

# http://localhost/horizon
# Mostra os broadcasting events
```

---

## Na entrevista

> "Redis Pub/Sub é um sistema simples de mensagens em real-time. O publisher manda no channel, os subscribers recebem se estiverem online. Fire-and-forget: sem garantia de entrega, sem persistência. No Laravel Broadcasting: events com ShouldBroadcast, private e presence channels. Laravel Echo no frontend. Laravel Reverb é o WebSocket server oficial. Casos de uso: notifications, chat, live dashboard. Limitações: at-most-once, sem replay. Redis Streams quando precisa de persistência e guarantees. Monitoramento com os comandos PUBSUB e o Horizon."

---

## Exercícios práticos

### Exercício 1: Notificações em real-time com Broadcasting

Crie um sistema de notificações em real-time para os users via Laravel Broadcasting e Redis.

<details>
<summary>Solução</summary>

```php
// app/Events/NewNotification.php
namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'message' => $this->notification->message,
            'created_at' => $this->notification->created_at->toIso8601String(),
        ];
    }
}

// routes/channels.php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// config/broadcasting.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
],

// .env
BROADCAST_DRIVER=redis

// app/Http/Controllers/NotificationController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use App\Events\NewNotification;

class NotificationController extends Controller
{
    public function send(User $user, array $data)
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'message' => $data['message'],
        ]);

        // Broadcast em real-time
        broadcast(new NewNotification($notification));

        return response()->json($notification);
    }
}

// resources/js/notifications.js
import Echo from 'laravel-echo';
import io from 'socket.io-client';

window.Echo = new Echo({
    broadcaster: 'socket.io',
    host: window.location.hostname + ':6001'
});

// Subscribe no private channel
Echo.private(`user.${userId}`)
    .listen('.notification.new', (e) => {
        // Mostrar toast
        showToast(e.message, e.type);

        // Atualizar o contador
        updateNotificationBadge();

        // Adicionar na lista
        addNotificationToList(e);
    });

function showToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 5000);
}

function updateNotificationBadge() {
    const badge = document.querySelector('.notification-badge');
    const count = parseInt(badge.textContent) + 1;
    badge.textContent = count;
    badge.style.display = 'block';
}
```
</details>

### Exercício 2: Live Chat com Presence Channel

Implemente um chat com users online e indicador de digitação.

<details>
<summary>Solução</summary>

```php
// app/Events/MessageSent.php
namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Message $message
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('chat.' . $this->message->chat_id);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar' => $this->message->user->avatar_url,
            ],
            'text' => $this->message->text,
            'created_at' => $this->message->created_at->diffForHumans(),
        ];
    }
}

// app/Events/UserTyping.php
class UserTyping implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $chatId,
        public int $userId,
        public string $userName
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('chat.' . $this->chatId);
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'user_name' => $this->userName,
        ];
    }
}

// routes/channels.php
Broadcast::channel('chat.{chatId}', function ($user, $chatId) {
    // Checar se o user é participante do chat
    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar_url,
    ];
});

// app/Http/Controllers/ChatController.php
namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Message;
use App\Events\MessageSent;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function sendMessage(Request $request, Chat $chat)
    {
        $request->validate([
            'text' => 'required|string|max:1000',
        ]);

        $message = Message::create([
            'chat_id' => $chat->id,
            'user_id' => auth()->id(),
            'text' => $request->text,
        ]);

        // Broadcast só para os outros users
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message);
    }

    public function typing(Request $request, Chat $chat)
    {
        broadcast(new UserTyping(
            $chat->id,
            auth()->id(),
            auth()->user()->name
        ))->toOthers();

        return response()->json(['status' => 'ok']);
    }
}

// resources/js/chat.js
const chatId = 1;
const currentUserId = window.userId;

// Conectar no Presence Channel
Echo.join(`chat.${chatId}`)
    .here((users) => {
        // Users online agora
        renderOnlineUsers(users);
    })
    .joining((user) => {
        // User entrou
        addOnlineUser(user);
        showSystemMessage(`${user.name} entrou no chat`);
    })
    .leaving((user) => {
        // User saiu
        removeOnlineUser(user);
        showSystemMessage(`${user.name} saiu do chat`);
    })
    .listen('MessageSent', (e) => {
        // Nova mensagem
        appendMessage(e);
    })
    .listenForWhisper('typing', (e) => {
        // Alguém está digitando
        showTypingIndicator(e.user_name);
    });

// Enviar mensagem
document.getElementById('send-btn').addEventListener('click', () => {
    const text = document.getElementById('message-input').value;

    axios.post(`/chats/${chatId}/messages`, { text })
        .then(response => {
            // Adicionar a própria mensagem
            appendMessage({
                ...response.data,
                user: {
                    id: currentUserId,
                    name: 'Você',
                }
            });

            document.getElementById('message-input').value = '';
        });
});

// Indicador de digitação
let typingTimeout;
document.getElementById('message-input').addEventListener('input', () => {
    clearTimeout(typingTimeout);

    // Evento whisper (client-to-client via Redis)
    Echo.join(`chat.${chatId}`)
        .whisper('typing', {
            user_name: window.userName,
        });

    typingTimeout = setTimeout(() => {
        hideTypingIndicator();
    }, 2000);
});

function renderOnlineUsers(users) {
    const list = document.getElementById('online-users');
    list.innerHTML = users.map(user => `
        <div class="user-online">
            <img src="${user.avatar}" alt="${user.name}">
            <span>${user.name}</span>
            <span class="status-dot"></span>
        </div>
    `).join('');
}

function showTypingIndicator(userName) {
    const indicator = document.getElementById('typing-indicator');
    indicator.textContent = `${userName} está digitando...`;
    indicator.style.display = 'block';
}

function hideTypingIndicator() {
    document.getElementById('typing-indicator').style.display = 'none';
}

function appendMessage(message) {
    const messageHtml = `
        <div class="message ${message.user.id === currentUserId ? 'own' : ''}">
            <img src="${message.user.avatar}" class="avatar">
            <div class="content">
                <div class="header">
                    <span class="name">${message.user.name}</span>
                    <span class="time">${message.created_at}</span>
                </div>
                <div class="text">${message.text}</div>
            </div>
        </div>
    `;

    document.getElementById('messages').insertAdjacentHTML('beforeend', messageHtml);
    scrollToBottom();
}
```
</details>

### Exercício 3: Live Dashboard com métricas

Crie um live dashboard que atualiza as métricas em tempo real via Broadcasting.

<details>
<summary>Solução</summary>

```php
// app/Events/MetricsUpdated.php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MetricsUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $metrics
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('dashboard');
    }

    public function broadcastWith(): array
    {
        return $this->metrics;
    }
}

// app/Console/Commands/UpdateDashboardMetrics.php
namespace App\Console\Commands;

use App\Events\MetricsUpdated;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateDashboardMetrics extends Command
{
    protected $signature = 'dashboard:update-metrics';

    public function handle()
    {
        $metrics = [
            'users_online' => $this->getUsersOnline(),
            'revenue_today' => $this->getRevenueToday(),
            'revenue_this_month' => $this->getRevenueThisMonth(),
            'orders_today' => $this->getOrdersToday(),
            'orders_pending' => $this->getOrdersPending(),
            'new_users_today' => $this->getNewUsersToday(),
            'conversion_rate' => $this->getConversionRate(),
        ];

        // Broadcast das métricas
        broadcast(new MetricsUpdated($metrics));

        $this->info('Métricas atualizadas e enviadas no broadcast');
    }

    private function getUsersOnline(): int
    {
        return Cache::get('users:online:count', 0);
    }

    private function getRevenueToday(): float
    {
        return Order::whereDate('created_at', today())
            ->where('status', 'paid')
            ->sum('total');
    }

    private function getRevenueThisMonth(): float
    {
        return Order::whereMonth('created_at', now()->month)
            ->where('status', 'paid')
            ->sum('total');
    }

    private function getOrdersToday(): int
    {
        return Order::whereDate('created_at', today())->count();
    }

    private function getOrdersPending(): int
    {
        return Order::where('status', 'pending')->count();
    }

    private function getNewUsersToday(): int
    {
        return User::whereDate('created_at', today())->count();
    }

    private function getConversionRate(): float
    {
        $visitors = Cache::get('visitors:today', 1);
        $orders = $this->getOrdersToday();

        return round(($orders / $visitors) * 100, 2);
    }
}

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Atualizar métricas a cada minuto
    $schedule->command('dashboard:update-metrics')->everyMinute();
}

// routes/channels.php
Broadcast::channel('dashboard', function ($user) {
    // Só admin pode se inscrever
    return $user->isAdmin();
});

// resources/views/dashboard.blade.php
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard ao vivo</title>
    <script src="{{ mix('js/app.js') }}"></script>
</head>
<body>
    <div class="dashboard">
        <div class="metric-card">
            <h3>Usuários online</h3>
            <div class="value" id="users-online">0</div>
        </div>

        <div class="metric-card">
            <h3>Receita de hoje</h3>
            <div class="value" id="revenue-today">R$ 0</div>
        </div>

        <div class="metric-card">
            <h3>Receita do mês</h3>
            <div class="value" id="revenue-month">R$ 0</div>
        </div>

        <div class="metric-card">
            <h3>Pedidos de hoje</h3>
            <div class="value" id="orders-today">0</div>
        </div>

        <div class="metric-card">
            <h3>Pedidos pendentes</h3>
            <div class="value" id="orders-pending">0</div>
        </div>

        <div class="metric-card">
            <h3>Novos users hoje</h3>
            <div class="value" id="new-users">0</div>
        </div>

        <div class="metric-card">
            <h3>Taxa de conversão</h3>
            <div class="value" id="conversion-rate">0%</div>
        </div>
    </div>

    <script>
        Echo.channel('dashboard')
            .listen('MetricsUpdated', (e) => {
                updateMetrics(e);
            });

        function updateMetrics(metrics) {
            animateValue('users-online', metrics.users_online);
            animateValue('revenue-today', 'R$ ' + formatNumber(metrics.revenue_today));
            animateValue('revenue-month', 'R$ ' + formatNumber(metrics.revenue_this_month));
            animateValue('orders-today', metrics.orders_today);
            animateValue('orders-pending', metrics.orders_pending);
            animateValue('new-users', metrics.new_users_today);
            animateValue('conversion-rate', metrics.conversion_rate + '%');
        }

        function animateValue(elementId, newValue) {
            const element = document.getElementById(elementId);
            element.classList.add('updated');
            element.textContent = newValue;

            setTimeout(() => {
                element.classList.remove('updated');
            }, 500);
        }

        function formatNumber(num) {
            return num.toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        // Carregar métricas iniciais
        axios.get('/api/dashboard/metrics')
            .then(response => updateMetrics(response.data));
    </script>
</body>
</html>
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
