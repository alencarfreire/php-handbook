# 17.1 RabbitMQ

## Resumo

> **RabbitMQ** — message broker para comunicação assíncrona entre serviços pelo protocolo AMQP.
>
> **Componentes:** Producer envia mensagens para o Exchange, que roteia para a Queue. O Consumer pega e processa.
>
> **Tipos de Exchange:** Direct (routing key exato), Fanout (broadcast para todos), Topic (pattern matching), Headers (por headers).

---

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de Exchange](#tipos-de-exchange)
- [Laravel + RabbitMQ](#laravel--rabbitmq)
- [Direct PHP (sem Laravel)](#direct-php-sem-laravel)
- [Garantias de entrega](#garantias-de-entrega)
- [Dead Letter Exchange (DLX)](#dead-letter-exchange-dlx)
- [Priority Queues](#priority-queues)
- [Monitoramento](#monitoramento)
- [Boas práticas](#boas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**RabbitMQ:**
Message broker para comunicação assíncrona entre serviços. Implementa o protocolo AMQP.

**Para quê:**
- Processamento assíncrono de tarefas
- Desacoplamento de serviços
- Load leveling (suavizar a carga)
- Garantia de entrega das mensagens

**Componentes:**

```
Producer → Exchange → Queue → Consumer

Producer: envia mensagens
Exchange: roteia mensagens para as queues
Queue: guarda mensagens
Consumer: processa mensagens
```

---

## Tipos de Exchange

### 1. Direct Exchange

**Princípio:** A mensagem vai para a queue com o routing key exato.

```
Producer → [Direct Exchange] → Queue "emails"
                    ↓              (routing_key: email)
                Queue "sms"
                (routing_key: sms)
```

**Exemplo:**

```php
// Producer
$channel->basic_publish(
    $message,
    'notifications',      // exchange
    'email'              // routing_key
);

// Queue está bound com routing_key = 'email'
// A mensagem cai só nessa queue
```

---

### 2. Fanout Exchange

**Princípio:** Broadcast para todas as queues conectadas.

```
Producer → [Fanout Exchange] → Queue 1
                     ↓          Queue 2
                                Queue 3
```

**Exemplo:**

```php
// Producer
$channel->basic_publish(
    $message,
    'logs',  // fanout exchange
    ''       // routing_key é ignorado
);

// Todas as queues recebem a mensagem
```

**Caso de uso:** Logging, monitoramento.

---

### 3. Topic Exchange

**Princípio:** Pattern matching no routing key.

```
Routing keys:
- user.created
- user.updated
- order.created
- order.shipped

Pattern bindings:
Queue 1: user.*          (recebe user.created, user.updated)
Queue 2: order.*         (recebe order.created, order.shipped)
Queue 3: *.created       (recebe user.created, order.created)
Queue 4: #               (recebe tudo)
```

**Wildcards:**
- `*` — um segmento
- `#` — qualquer quantidade de segmentos

**Exemplo:**

```php
// Producer
$channel->basic_publish(
    $message,
    'events',            // topic exchange
    'order.shipped'      // routing_key
);

// Queue com pattern 'order.*' recebe a mensagem
```

---

### 4. Headers Exchange

**Princípio:** Routing por headers, não por routing key.

```php
// Producer
$message->set('application_headers', [
    'format' => 'pdf',
    'type' => 'report'
]);

// Queue está bound com a condição:
// headers: {format: pdf, type: report}
```

---

## Laravel + RabbitMQ

**Instalação:**

```bash
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

**config/queue.php:**

```php
'connections' => [
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'queue' => env('RABBITMQ_QUEUE', 'default'),
        'connection' => [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
        'options' => [
            'exchange' => [
                'name' => 'laravel-exchange',
                'type' => 'topic',  // direct, fanout, topic, headers
            ],
        ],
    ],
],
```

**Job:**

```php
class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $connection = 'rabbitmq';
    public $queue = 'emails';

    public function __construct(
        public string $email,
        public string $message
    ) {}

    public function handle()
    {
        Mail::raw($this->message, function ($mail) {
            $mail->to($this->email);
        });
    }
}

// Dispatch
SendEmailJob::dispatch('joao@email.com', 'Olá!');
```

**Worker:**

```bash
php artisan queue:work rabbitmq --queue=emails
```

---

## Direct PHP (sem Laravel)

**Producer:**

```php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Criar exchange
$channel->exchange_declare(
    'logs',      // exchange name
    'fanout',    // type
    false,       // passive
    true,        // durable
    false        // auto_delete
);

// Enviar mensagem
$message = new AMQPMessage(
    json_encode(['event' => 'user_created', 'user_id' => 123]),
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

$channel->basic_publish($message, 'logs');

$channel->close();
$connection->close();
```

**Consumer:**

```php
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

// Criar queue
$channel->queue_declare(
    'email_queue',  // queue name
    false,          // passive
    true,           // durable
    false,          // exclusive
    false           // auto_delete
);

// Bind da queue no exchange
$channel->queue_bind('email_queue', 'notifications', 'email');

// Callback de processamento
$callback = function ($msg) {
    $data = json_decode($msg->body, true);

    echo "Processando: {$data['email']}\n";

    // Processamento...

    // Acknowledge (confirmação)
    $msg->ack();
};

// Assinar a queue
$channel->basic_qos(null, 1, null);  // Prefetch 1 mensagem
$channel->basic_consume(
    'email_queue',
    '',
    false,        // no_ack
    false,        // exclusive
    false,        // no_local
    false,        // no_wait
    $callback
);

// Escutar
while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
```

---

## Garantias de entrega

### 1. Publisher Confirms

**Problema:** O Producer não sabe se a mensagem chegou.

**Solução:**

```php
$channel->confirm_select();

$channel->basic_publish($message, 'exchange');

$channel->wait_for_pending_acks(5);  // Timeout 5 segundos

// Se der timeout → mensagem não foi entregue
```

---

### 2. Consumer Acknowledgments

**Manual ACK:**

```php
$callback = function ($msg) {
    try {
        processMessage($msg->body);

        // ✅ Sucesso: acknowledge
        $msg->ack();
    } catch (Exception $e) {
        // ❌ Erro: reject e requeue
        $msg->nack(false, true);  // requeue = true
    }
};

$channel->basic_consume('queue', '', false, false, false, false, $callback);
//                                     ↑
//                                  no_ack = false (manual)
```

**Auto ACK (perigoso):**

```php
// A mensagem some assim que chega no consumer
// Se o consumer cair → mensagem perdida
$channel->basic_consume('queue', '', false, true, ...);
//                                          ↑
//                                       no_ack = true
```

---

### 3. Persistent Messages

```php
// Mensagem vai para o disco
$message = new AMQPMessage(
    $body,
    ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
);

// Queue também precisa ser durable
$channel->queue_declare('queue', false, true, false, false);
//                                       ↑
//                                    durable = true
```

---

## Dead Letter Exchange (DLX)

**O que é:** Queue para mensagens "mortas" (não processaram depois de N tentativas).

**Configuração:**

```php
// Queue principal
$channel->queue_declare('emails', false, true, false, false, false, [
    'x-dead-letter-exchange' => ['S', 'dlx'],
    'x-dead-letter-routing-key' => ['S', 'emails.failed'],
    'x-message-ttl' => ['I', 300000],  // 5 minutos
]);

// Dead Letter Exchange
$channel->exchange_declare('dlx', 'direct', false, true, false);

// Dead Letter Queue
$channel->queue_declare('emails.failed', false, true, false, false);
$channel->queue_bind('emails.failed', 'dlx', 'emails.failed');
```

**Uso:**

```php
$callback = function ($msg) {
    try {
        processEmail($msg->body);
        $msg->ack();
    } catch (Exception $e) {
        // Reject sem requeue → vai para o DLX
        $msg->nack(false, false);
    }
};
```

---

## Priority Queues

```php
// Queue com prioridades
$channel->queue_declare('tasks', false, true, false, false, false, [
    'x-max-priority' => ['I', 10]  // Prioridades 0-10
]);

// Enviar com prioridade
$message = new AMQPMessage($body, [
    'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
    'priority' => 5  // Prioridade 5
]);

// Mensagens high priority são processadas primeiro
```

---

## Monitoramento

**Management UI:**

```bash
# Ativar o plugin de management
rabbitmq-plugins enable rabbitmq_management

# http://localhost:15672
# Login: guest / guest
```

**CLI:**

```bash
# Lista de queues
rabbitmqctl list_queues name messages consumers

# Lista de exchanges
rabbitmqctl list_exchanges name type

# Lista de bindings
rabbitmqctl list_bindings

# Status
rabbitmqctl status
```

---

## Boas práticas

```
✓ Sempre use durable queues e persistent messages
✓ Manual ACK com try/catch
✓ Dead Letter Exchange para failed messages
✓ Publisher confirms para mensagens críticas
✓ Prefetch limit (1-10) para carga uniforme
✓ Monitoring (queue size, consumer lag)
✓ Idempotency (a mensagem pode chegar duas vezes)
✓ TTL para message expiration
```

---

## Na entrevista

> "RabbitMQ é um message broker para comunicação assíncrona. Componentes: Producer, Exchange (direct/fanout/topic), Queue, Consumer. Direct exchange: routing key exato. Fanout: broadcast. Topic: pattern matching (user.*, #). Laravel: vladimir-yuldashev/laravel-queue-rabbitmq, jobs com connection=rabbitmq. Garantias: publisher confirms, manual ACK, persistent messages. DLX para failed messages. Priority queues. Management UI para monitoramento. Boas práticas: durable queues, manual ACK, idempotency."

---

## Exercícios práticos

### Exercício 1: Crie um Job para RabbitMQ

Crie um Job que envia email via RabbitMQ com lógica de retry e tratamento de erros.

<details>
<summary>Solução</summary>

```php
// app/Jobs/SendEmailJob.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $connection = 'rabbitmq';
    public $queue = 'emails';

    // Quantidade de tentativas
    public $tries = 3;

    // Delay entre tentativas (segundos)
    public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

    // Timeout
    public $timeout = 120;

    public function __construct(
        public string $email,
        public string $subject,
        public string $message
    ) {}

    public function handle(): void
    {
        Mail::raw($this->message, function ($mail) {
            $mail->to($this->email)
                 ->subject($this->subject);
        });

        Log::info('Email enviado com sucesso', [
            'email' => $this->email,
            'attempts' => $this->attempts(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao enviar email depois de todas as tentativas', [
            'email' => $this->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Pode mandar para o Dead Letter Exchange
        // ou avisar o admin
    }
}

// config/queue.php
'connections' => [
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'queue' => env('RABBITMQ_QUEUE', 'default'),
        'connection' => [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
        'options' => [
            'exchange' => [
                'name' => 'laravel-exchange',
                'type' => 'direct',
                'declare' => true,
            ],
            'queue' => [
                'declare' => true,
                'bind' => true,
            ],
        ],
    ],
],

// Uso
SendEmailJob::dispatch('joao@email.com', 'Bem-vindo', 'Olá, mundo!');
```
</details>

### Exercício 2: Configure um Topic Exchange para eventos

Crie um Topic Exchange para rotear eventos de usuários (user.created, user.updated, user.deleted) para queues diferentes.

<details>
<summary>Solução</summary>

```php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQService
{
    private $connection;
    private $channel;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            'localhost', 5672, 'guest', 'guest'
        );
        $this->channel = $this->connection->channel();
    }

    public function setupTopicExchange(): void
    {
        // Criar Topic Exchange
        $this->channel->exchange_declare(
            'user_events',  // exchange name
            'topic',        // type
            false,          // passive
            true,           // durable
            false           // auto_delete
        );

        // Queue para todos os eventos de criação
        $this->channel->queue_declare('user_creations', false, true, false, false);
        $this->channel->queue_bind('user_creations', 'user_events', '*.created');

        // Queue para todos os eventos do usuário com ID 123
        $this->channel->queue_declare('user_123_events', false, true, false, false);
        $this->channel->queue_bind('user_123_events', 'user_events', 'user.123.*');

        // Queue para todos os eventos
        $this->channel->queue_declare('all_user_events', false, true, false, false);
        $this->channel->queue_bind('all_user_events', 'user_events', 'user.#');
    }

    public function publishUserEvent(string $routingKey, array $data): void
    {
        $message = new AMQPMessage(
            json_encode($data),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($message, 'user_events', $routingKey);
    }

    public function consume(string $queueName, callable $callback): void
    {
        $this->channel->basic_qos(null, 1, null);

        $this->channel->basic_consume(
            $queueName,
            '',
            false,  // no_ack
            false,  // exclusive
            false,  // no_local
            false,  // no_wait
            function ($msg) use ($callback) {
                try {
                    $data = json_decode($msg->body, true);
                    $callback($data);
                    $msg->ack();
                } catch (\Exception $e) {
                    $msg->nack(false, true); // requeue
                }
            }
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

// Uso
$service = new RabbitMQService();
$service->setupTopicExchange();

// Publicar eventos
$service->publishUserEvent('user.created', ['user_id' => 123, 'email' => 'joao@email.com']);
$service->publishUserEvent('user.123.updated', ['user_id' => 123, 'name' => 'Novo nome']);

// Consumer para todas as criações
$service->consume('user_creations', function ($data) {
    echo "Usuário criado: " . $data['user_id'] . "\n";
});
```
</details>

### Exercício 3: Implemente Dead Letter Exchange

Crie um sistema com Dead Letter Exchange para tratar mensagens failed.

<details>
<summary>Solução</summary>

```php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class DLXService
{
    private $connection;
    private $channel;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            'localhost', 5672, 'guest', 'guest'
        );
        $this->channel = $this->connection->channel();
    }

    public function setup(): void
    {
        // Dead Letter Exchange
        $this->channel->exchange_declare(
            'dlx',
            'direct',
            false,
            true,
            false
        );

        // Dead Letter Queue
        $this->channel->queue_declare(
            'failed_jobs',
            false,
            true,    // durable
            false,
            false
        );
        $this->channel->queue_bind('failed_jobs', 'dlx', 'failed');

        // Queue principal com config de DLX
        $args = new AMQPTable([
            'x-dead-letter-exchange' => 'dlx',
            'x-dead-letter-routing-key' => 'failed',
            'x-message-ttl' => 300000,  // 5 minutos de TTL
        ]);

        $this->channel->queue_declare(
            'main_queue',
            false,
            true,
            false,
            false,
            false,
            $args
        );
    }

    public function publishToMainQueue(array $data): void
    {
        $message = new AMQPMessage(
            json_encode($data),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );

        $this->channel->basic_publish($message, '', 'main_queue');
    }

    public function consumeMainQueue(): void
    {
        $callback = function ($msg) {
            $data = json_decode($msg->body, true);

            try {
                // Simular processamento
                if (rand(0, 1) === 0) {
                    throw new \Exception('Falha no processamento');
                }

                echo "Processado: " . json_encode($data) . "\n";
                $msg->ack();
            } catch (\Exception $e) {
                echo "Falhou: " . $e->getMessage() . "\n";

                // Reject sem requeue → vai para o DLX
                $msg->nack(false, false);
            }
        };

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume('main_queue', '', false, false, false, false, $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function consumeDeadLetterQueue(): void
    {
        $callback = function ($msg) {
            $data = json_decode($msg->body, true);

            echo "Dead letter: " . json_encode($data) . "\n";

            // Logar, avisar o admin, gravar no banco
            file_put_contents(
                'failed_jobs.log',
                date('Y-m-d H:i:s') . " - " . $msg->body . "\n",
                FILE_APPEND
            );

            $msg->ack();
        };

        $this->channel->basic_consume('failed_jobs', '', false, false, false, false, $callback);

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}

// Uso
$service = new DLXService();
$service->setup();

// Publicação
$service->publishToMainQueue(['task' => 'send_email', 'email' => 'joao@email.com']);

// Worker da queue principal
// $service->consumeMainQueue();

// Worker dos failed jobs
// $service->consumeDeadLetterQueue();
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
