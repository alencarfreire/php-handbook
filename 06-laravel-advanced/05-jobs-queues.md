# 5.5 Jobs & Queues

## Resumo

> **Jobs & Queues** — sistema de execução assíncrona em background. Job é a classe com a lógica (SendEmail, ProcessFile). Queue (fila) é a fila de jobs.
>
> **Dispatch:** `JobName::dispatch()` manda para a queue. O worker (`queue:work`) processa os jobs.
>
> **Importante:** `ShouldQueue` para assíncrono, `delay()` para adiar, `tries` para retry. Job chains para sequência, batches para jobs em paralelo.

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
Jobs — tarefas em background (enviar email, processar arquivo). Queues — filas de jobs para execução assíncrona.

**O essencial:**
- Job — tarefa em background
- Queue — fila de jobs
- Worker — processo que executa os jobs

---

## Como funciona

**Criar o Job:**

```bash
php artisan make:job SendWelcomeEmail
```

```php
namespace App\Jobs;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Tentativas
    public $tries = 3;

    // Timeout (segundos)
    public $timeout = 60;

    // Queue
    public $queue = 'emails';

    public function __construct(public User $user)
    {
    }

    public function handle(): void
    {
        // Enviar email
        $this->user->notify(new WelcomeNotification());
    }

    // Tratamento de erro
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send welcome email', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Dispatch (chamar) o Job:**

```php
use App\Jobs\SendWelcomeEmail;

// Mandar para a queue
SendWelcomeEmail::dispatch($user);

// Dispatch com delay
SendWelcomeEmail::dispatch($user)->delay(now()->addMinutes(10));

// Dispatch em uma queue específica
SendWelcomeEmail::dispatch($user)->onQueue('emails');

// Dispatch síncrono (sem queue)
SendWelcomeEmail::dispatchSync($user);

// Dispatch depois do DB commit
SendWelcomeEmail::dispatch($user)->afterCommit();

// Dispatch se a condição for verdadeira
SendWelcomeEmail::dispatchIf($user->isActive(), $user);
SendWelcomeEmail::dispatchUnless($user->isBanned(), $user);
```

**Configurar as queues (.env):**

```env
# database/migrations/xxxx_create_jobs_table.php
php artisan queue:table
php artisan migrate

QUEUE_CONNECTION=database

# Ou Redis
QUEUE_CONNECTION=redis

# Ou Sync (sem queue, síncrono)
QUEUE_CONNECTION=sync
```

**Rodar o Worker:**

```bash
# Iniciar o worker (processa os jobs)
php artisan queue:work

# Com uma queue específica
php artisan queue:work --queue=emails,default

# Com timeout
php artisan queue:work --timeout=60

# Reinicia quando o código muda
php artisan queue:work --timeout=60 --tries=3

# Parar o worker depois do job atual
php artisan queue:restart

# Um job (para cron)
php artisan queue:work --once
```

---

## Quando usar

**Use Jobs quando:**
- Operação longa (enviar email, processar arquivo)
- Não bloquear o HTTP response
- Tarefa pesada
- Integração com API externa

**Não use quando:**
- Operação simples e rápida
- Precisa do resultado na hora

---

## Exemplo prático

**Processar arquivo enviado:**

```php
// Job
namespace App\Jobs;

use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

class ProcessUploadedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;  // 5 minutos

    public function __construct(public Upload $upload)
    {
    }

    public function handle(): void
    {
        // Pegar o arquivo do storage
        $filePath = $this->upload->file_path;
        $content = Storage::get($filePath);

        // Processar o arquivo
        $processedData = $this->process($content);

        // Salvar o resultado
        Storage::put(
            str_replace('.csv', '_processed.csv', $filePath),
            $processedData
        );

        // Atualizar o status
        $this->upload->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    private function process(string $content): string
    {
        // Lógica de processamento
        return $content;
    }

    public function failed(\Throwable $exception): void
    {
        $this->upload->update([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }
}

// Controller
class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv|max:10240',
        ]);

        // Salvar o arquivo
        $path = $request->file('file')->store('uploads');

        // Criar o registro
        $upload = Upload::create([
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        // Mandar para a queue
        ProcessUploadedFile::dispatch($upload);

        return response()->json([
            'message' => 'Arquivo enviado, processamento iniciado',
            'upload_id' => $upload->id,
        ], 202);
    }
}
```

**Job Chains (cadeia de jobs):**

```php
use Illuminate\Support\Facades\Bus;

Bus::chain([
    new ProcessUploadedFile($upload),
    new GenerateReport($upload),
    new SendReportEmail($upload),
])->dispatch();

// Com tratamento de erro
Bus::chain([
    new ProcessUploadedFile($upload),
    new GenerateReport($upload),
])->catch(function (\Throwable $e) {
    // Roda se qualquer job falhar
    Log::error('Job chain failed', ['error' => $e->getMessage()]);
})->dispatch();
```

**Job Batches (lote de jobs):**

```php
use Illuminate\Support\Facades\Bus;

// Criar o batch
$batch = Bus::batch([
    new ProcessUser($user1),
    new ProcessUser($user2),
    new ProcessUser($user3),
])->then(function () {
    // Todos os jobs terminaram
    Log::info('All users processed');
})->catch(function () {
    // Um dos jobs falhou
})->finally(function () {
    // Sempre roda
})->dispatch();

// Checar o status do batch
$batch = Bus::findBatch($batchId);
$batch->finished();  // Todos concluídos
$batch->cancelled();  // Cancelado
$batch->totalJobs;  // Total de jobs
$batch->processedJobs();  // Processados
$batch->pendingJobs;  // Pendentes
```

**Rate Limiting (limite de frequência):**

```php
use Illuminate\Support\Facades\RateLimiter;

class ProcessApiRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // No máximo 10 jobs por minuto
        RateLimiter::attempt(
            'api-requests',
            $perMinute = 10,
            function () {
                // Lógica da request
                Http::get('https://api.example.com');
            }
        );
    }

    // Ou via middleware
    public function middleware(): array
    {
        return [new RateLimited('api-requests')];
    }
}
```

**Unique Jobs (evitar duplicata):**

```php
use Illuminate\Contracts\Queue\ShouldBeUnique;

class ProcessOrder implements ShouldQueue, ShouldBeUnique
{
    public function __construct(public Order $order)
    {
    }

    // Chave única (um job por order)
    public function uniqueId(): string
    {
        return $this->order->id;
    }

    // Tempo de unicidade (segundos)
    public $uniqueFor = 3600;  // 1 hora

    public function handle(): void
    {
        // Processar o pedido
    }
}
```

**Job Middleware:**

```php
namespace App\Jobs\Middleware;

class RateLimited
{
    public function handle($job, $next)
    {
        RateLimiter::attempt(
            'process-orders',
            $perMinute = 10,
            function () use ($job, $next) {
                $next($job);
            },
            $decaySeconds = 60
        );
    }
}

// Uso no Job
public function middleware(): array
{
    return [new RateLimited()];
}
```

**Failed Jobs (tratar os que falharam):**

```bash
# Criar a tabela de failed jobs
php artisan queue:failed-table
php artisan migrate

# Ver os jobs que falharam
php artisan queue:failed

# Retentar o job
php artisan queue:retry {id}

# Retentar todos
php artisan queue:retry all

# Remover o job que falhou
php artisan queue:forget {id}

# Limpar todos os failed
php artisan queue:flush
```

**Monitorar as queues:**

```php
// No AppServiceProvider
use Illuminate\Support\Facades\Queue;

public function boot(): void
{
    Queue::before(function (JobProcessing $event) {
        // Antes de executar o job
        Log::info('Job starting', [
            'job' => $event->job->resolveName(),
        ]);
    });

    Queue::after(function (JobProcessed $event) {
        // Depois de executar o job
        Log::info('Job completed', [
            'job' => $event->job->resolveName(),
        ]);
    });

    Queue::failing(function (JobFailed $event) {
        // O job falhou
        Log::error('Job failed', [
            'job' => $event->job->resolveName(),
            'exception' => $event->exception->getMessage(),
        ]);
    });
}
```

**Supervisor (em production):**

```ini
; /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/worker.log
stopwaitsecs=3600
```

```bash
# Reiniciar o supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Jobs — tarefas em background (SendEmail, ProcessFile)
- Implementam `ShouldQueue` para rodar assíncrono
- O worker processa via `queue:work`

**Dispatch:**
```php
JobName::dispatch($data);              // Na queue
JobName::dispatch($data)->delay(10);   // Com delay
JobName::dispatchSync($data);          // Síncrono
```

**Configuração:**
- Drivers: `database`, `redis`, `sync`
- `tries` — número de tentativas
- `timeout` — tempo máximo de execução
- `queue` — nome da queue

**Avançado:**
- **Job Chains** — execução em sequência: `Bus::chain([Job1, Job2])`
- **Job Batches** — jobs em paralelo: `Bus::batch([...])`
- **ShouldBeUnique** — evita duplicata
- **Rate Limiting** — limite de frequência
- **Supervisor** — em production
- **Failed Jobs** — `queue:failed`, `queue:retry`

---

## Exercícios práticos

### Exercício 1: Job com retry e timeout

Crie um `ProcessVideoJob` que processa vídeo. 3 tentativas, timeout de 5 minutos, queue `videos`. Se falhar, grave o log no banco.

<details>
<summary>Solução</summary>

```php
namespace App\Jobs;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300; // 5 minutos
    public $queue = 'videos';

    public function __construct(public Video $video)
    {
    }

    public function handle(): void
    {
        $this->video->update(['status' => 'processing']);

        // Processar o vídeo (ex.: conversão)
        $inputPath = Storage::path($this->video->original_path);
        $outputPath = Storage::path($this->video->processed_path);

        // Aqui entra a lógica de processar o vídeo
        // exec("ffmpeg -i {$inputPath} {$outputPath}");

        $this->video->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->video->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);

        Log::error('Video processing failed', [
            'video_id' => $this->video->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}

// Uso
ProcessVideoJob::dispatch($video);
```
</details>

### Exercício 2: Job Chain para upload de arquivo

Crie a chain: `DownloadFileJob` → `ProcessFileJob` → `NotifyUserJob`. Se qualquer passo falhar, envie email para o admin.

<details>
<summary>Solução</summary>

```php
namespace App\Jobs;

use App\Models\Import;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Job 1: Baixar o arquivo
class DownloadFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Import $import, public string $url)
    {
    }

    public function handle(): void
    {
        $content = Http::get($this->url)->body();
        Storage::put($this->import->file_path, $content);

        $this->import->update(['status' => 'downloaded']);
    }
}

// Job 2: Processar o arquivo
class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Import $import)
    {
    }

    public function handle(): void
    {
        $content = Storage::get($this->import->file_path);
        $rows = array_map('str_getcsv', explode("\n", $content));

        // Processar as linhas
        foreach ($rows as $row) {
            // Lógica de importação
        }

        $this->import->update([
            'status' => 'processed',
            'rows_count' => count($rows),
        ]);
    }
}

// Job 3: Notificar o usuário
class NotifyUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Import $import)
    {
    }

    public function handle(): void
    {
        $this->import->user->notify(
            new ImportCompletedNotification($this->import)
        );
    }
}

// Disparar a chain
Bus::chain([
    new DownloadFileJob($import, $url),
    new ProcessFileJob($import),
    new NotifyUserJob($import),
])->catch(function (\Throwable $e) use ($import) {
    Log::error('Import chain failed', [
        'import_id' => $import->id,
        'error' => $e->getMessage(),
    ]);

    Mail::to(config('app.admin_email'))->send(
        new ImportFailedMail($import, $e)
    );
})->dispatch();
```
</details>

### Exercício 3: Unique Job para export

Crie um `ExportUsersJob` que só pode rodar 1 vez por hora para cada usuário. Se o job já está na queue, não adicione outro.

<details>
<summary>Solução</summary>

```php
namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ExportUsersJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos
    public $tries = 2;

    // Unicidade por 1 hora
    public $uniqueFor = 3600;

    public function __construct(public User $requestedBy)
    {
    }

    // Chave única (um export por usuário)
    public function uniqueId(): string
    {
        return "export-users-{$this->requestedBy->id}";
    }

    public function handle(): void
    {
        $users = User::with('profile')->get();

        $csv = "ID,Nome,Email,Criado em\n";
        foreach ($users as $user) {
            $csv .= "{$user->id},{$user->name},{$user->email},{$user->created_at}\n";
        }

        $filename = "exports/users-" . now()->format('Y-m-d-His') . ".csv";
        Storage::put($filename, $csv);

        $this->requestedBy->notify(
            new ExportReadyNotification($filename)
        );
    }
}

// No controller
public function export(Request $request)
{
    // Tenta enfileirar
    ExportUsersJob::dispatch($request->user());

    return response()->json([
        'message' => 'Export iniciado. Você será notificado quando estiver pronto.',
    ], 202);
}

// Se o job já está na queue, não entra de novo
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
