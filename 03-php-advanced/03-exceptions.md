# 3.3 Exceções e erros

## Resumo

> **Exceções** — tratamento de erro com try-catch-finally.
>
> **O essencial:** `throw new Exception()`, exceções customizadas, Error (PHP 7.0+), Throwable.
>
> **Laravel:** `abort()` para HTTP, `findOrFail()` para model, Handler para tratamento global.

---

## Conteúdo

- [try-catch-finally](#try-catch-finally)
- [throw (lançar exceção)](#throw-lançar-exceção)
- [Exceções customizadas](#exceções-customizadas)
- [Métodos de Exception](#métodos-de-exception)
- [Error (PHP 7.0+)](#error-php-70)
- [set_exception_handler e set_error_handler](#set_exception_handler-e-set_error_handler)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## try-catch-finally

**O que é:**
Mecanismo de tratamento de exceções.

**Como funciona:**
```php
try {
    // Código que pode lançar exceção
    $user = User::findOrFail($id);  // Lança ModelNotFoundException
    $user->delete();
} catch (ModelNotFoundException $e) {
    // Trata a exceção específica
    echo "Usuário não encontrado: {$e->getMessage()}";
} catch (Exception $e) {
    // Trata as demais exceções
    echo "Erro: {$e->getMessage()}";
} finally {
    // Roda sempre (mesmo se teve exceção)
    DB::disconnect();
    Log::info('Operação concluída');
}

// finally (PHP 5.5+)
try {
    $file = fopen('file.txt', 'r');
    // Trabalho com o arquivo
} finally {
    if (isset($file)) {
        fclose($file);  // Fecha de qualquer jeito
    }
}
```

**Quando usar:**
Erro que você consegue prever: arquivo não existe, banco fora.

**Exemplo prático:**
```php
// Request de API com tratamento de erros
public function store(Request $request)
{
    try {
        DB::beginTransaction();

        $user = User::create($request->validated());
        $user->roles()->attach($request->input('roles'));

        // API externa
        $this->notificationService->send($user);

        DB::commit();

        return response()->json($user, 201);
    } catch (ValidationException $e) {
        DB::rollBack();
        return response()->json(['errors' => $e->errors()], 422);
    } catch (ApiException $e) {
        DB::rollBack();
        Log::error('API error', ['message' => $e->getMessage()]);
        return response()->json(['error' => 'Falha ao enviar notificação'], 500);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Unexpected error', ['message' => $e->getMessage()]);
        return response()->json(['error' => 'Erro interno'], 500);
    }
}

// Liberar recursos
public function processFile(string $path): array
{
    $handle = fopen($path, 'r');

    try {
        $data = [];
        while (($line = fgets($handle)) !== false) {
            $data[] = json_decode($line, true);
        }

        return $data;
    } finally {
        fclose($handle);  // Fecha de qualquer jeito
    }
}
```

**Na entrevista:**
> "try-catch trata exceções. Dá para ter vários catch, um por tipo. finally roda sempre (para liberar recurso). No Laravel eu uso em transação, API externa e arquivo."

---

## throw (lançar exceção)

**O que é:**
Criar e lançar uma exceção.

**Como funciona:**
```php
function divide(int $a, int $b): float
{
    if ($b === 0) {
        throw new InvalidArgumentException('Divisão por zero');
    }

    return $a / $b;
}

try {
    $result = divide(10, 0);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage();  // "Divisão por zero"
}

// Exceções nativas do PHP
throw new Exception('Erro geral');
throw new RuntimeException('Erro de runtime');
throw new LogicException('Erro de lógica');
throw new InvalidArgumentException('Argumento inválido');
throw new OutOfBoundsException('Fora dos limites');
throw new BadMethodCallException('Chamada de método inválida');

// Com código de erro e previous exception
try {
    $result = externalApi();
} catch (ApiException $e) {
    throw new RuntimeException('Falha ao chamar a API', 500, $e);
}
```

**Quando usar:**
Quando o método não consegue seguir: validação falhou, recurso indisponível.

**Exemplo prático:**
```php
// Validação no service
class OrderService
{
    public function create(array $data): Order
    {
        if (empty($data['user_id'])) {
            throw new InvalidArgumentException('User ID é obrigatório');
        }

        if ($data['amount'] <= 0) {
            throw new InvalidArgumentException('Valor precisa ser positivo');
        }

        $user = User::find($data['user_id']);

        if ($user === null) {
            throw new RuntimeException("Usuário {$data['user_id']} não encontrado");
        }

        return Order::create($data);
    }
}

// Eloquent findOrFail
$user = User::findOrFail($id);  // Lança ModelNotFoundException

// abort() no Laravel (lança HttpException)
if (!auth()->check()) {
    abort(401, 'Não autorizado');
}

if (!Gate::allows('update', $post)) {
    abort(403, 'Acesso negado');
}

// Exceção customizada com contexto
class InsufficientFundsException extends Exception
{
    public function __construct(
        string $message,
        private int $balance,
        private int $required,
    ) {
        parent::__construct($message);
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getRequired(): int
    {
        return $this->required;
    }
}

if ($wallet->balance < $amount) {
    throw new InsufficientFundsException(
        'Saldo insuficiente',
        $wallet->balance,
        $amount
    );
}
```

**Na entrevista:**
> "throw lança a exceção. As nativas: Exception, RuntimeException, InvalidArgumentException. No Laravel: abort() para erro HTTP, findOrFail() lança ModelNotFoundException. Eu crio exceção customizada para regra de negócio."

---

## Exceções customizadas

**O que é:**
Classes de exceção suas, para erro específico.

**Como funciona:**
```php
// Exceção customizada base
class OrderException extends Exception {}

class PaymentFailedException extends OrderException {}

class InsufficientStockException extends OrderException {}

// Uso
try {
    $order = $this->createOrder($data);
    $this->processPayment($order);
    $this->reserveStock($order);
} catch (PaymentFailedException $e) {
    // Trata erro de pagamento
    $this->refundOrder($order);
    throw $e;
} catch (InsufficientStockException $e) {
    // Trata falta de estoque
    $this->notifySupplier($e->getProduct());
} catch (OrderException $e) {
    // Tratamento geral de erro do pedido
    Log::error('Order error', ['message' => $e->getMessage()]);
}

// Com contexto extra
class ValidationException extends Exception
{
    public function __construct(
        string $message,
        private array $errors,
    ) {
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}

throw new ValidationException('Validação falhou', [
    'email' => ['E-mail inválido'],
    'password' => ['Senha muito curta'],
]);
```

**Quando usar:**
Erro de domínio, regra de negócio, caso específico.

**Exemplo prático:**
```php
// Exceções HTTP no Laravel
namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected int $statusCode;

    public function __construct(
        string $message,
        int $statusCode = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render()
    {
        return response()->json([
            'error' => $this->getMessage(),
        ], $this->statusCode);
    }
}

// Uso
if (!$token) {
    throw new ApiException('Token é obrigatório', 401);
}

// Exceções de domínio
class UserAlreadyExistsException extends Exception
{
    public function __construct(string $email)
    {
        parent::__construct("Já existe usuário com o e-mail {$email}");
    }
}

class OrderNotFoundException extends Exception
{
    public function __construct(int $orderId)
    {
        parent::__construct("Pedido {$orderId} não encontrado");
    }
}

// Service
public function register(array $data): User
{
    $exists = User::where('email', $data['email'])->exists();

    if ($exists) {
        throw new UserAlreadyExistsException($data['email']);
    }

    return User::create($data);
}

// Handler
public function render($request, Throwable $exception)
{
    if ($exception instanceof UserAlreadyExistsException) {
        return response()->json([
            'error' => $exception->getMessage(),
        ], 409);
    }

    if ($exception instanceof OrderNotFoundException) {
        return response()->json([
            'error' => $exception->getMessage(),
        ], 404);
    }

    return parent::render($request, $exception);
}
```

**Na entrevista:**
> "Exceção customizada é para erro de domínio. Eu herdo de Exception ou RuntimeException. Coloco contexto (balance, product). No Laravel crio ApiException, DomainException. O Handler trata e devolve JSON."

---

## Métodos de Exception

**O que é:**
Métodos do objeto Exception para pegar informação do erro.

**Como funciona:**
```php
try {
    throw new Exception('Mensagem de erro', 500);
} catch (Exception $e) {
    // Métodos de Exception
    echo $e->getMessage();     // "Mensagem de erro"
    echo $e->getCode();        // 500
    echo $e->getFile();        // /path/to/file.php
    echo $e->getLine();        // 42
    echo $e->getTrace();       // Array (stack trace)
    echo $e->getTraceAsString(); // String (stack trace formatado)
    echo $e->getPrevious();    // Previous exception (ou null)

    // __toString()
    echo $e;  // Informação completa da exceção
}

// Previous exception (cadeia)
try {
    throw new Exception('Erro original');
} catch (Exception $original) {
    throw new RuntimeException('Erro encapsulado', 0, $original);
}

// Percorre a cadeia
try {
    // ...
} catch (Exception $e) {
    while ($e !== null) {
        echo $e->getMessage() . "\n";
        $e = $e->getPrevious();
    }
}
```

**Quando usar:**
Log, debug, cadeia de exceções.

**Exemplo prático:**
```php
// Log da exceção
try {
    $this->externalApi->call();
} catch (ApiException $e) {
    Log::error('API call failed', [
        'message' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    throw new RuntimeException('API externa falhou', 0, $e);
}

// Laravel Exception Handler
public function report(Throwable $exception)
{
    if ($this->shouldReport($exception)) {
        Log::error($exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Envio para o Sentry
        if (app()->bound('sentry')) {
            app('sentry')->captureException($exception);
        }
    }
}

// Exceção customizada com contexto extra
class DatabaseException extends Exception
{
    public function __construct(
        string $message,
        private string $query,
        private array $bindings,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'query' => $this->query,
            'bindings' => $this->bindings,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}

// Log com contexto
try {
    DB::select($query, $bindings);
} catch (QueryException $e) {
    $exception = new DatabaseException(
        'Query no banco falhou',
        $query,
        $bindings,
        $e
    );

    Log::error('Database error', $exception->getContext());
    throw $exception;
}
```

**Na entrevista:**
> "Métodos de Exception: getMessage(), getCode(), getFile(), getLine(), getTrace(). getPrevious() para a cadeia. Uso no log e no Sentry. Na customizada eu coloco getContext() com informação extra."

---

## Error (PHP 7.0+)

**O que é:**
Erro fatal do PHP agora lança Error (dá para pegar).

**Como funciona:**
```php
// PHP < 7.0: erro fatal (não dá para pegar)
// PHP 7.0+: lança Error (dá para pegar)

try {
    nonExistentFunction();  // ParseError
} catch (Error $e) {
    echo "Erro: {$e->getMessage()}";
}

try {
    $obj->nonExistentMethod();  // Error
} catch (Error $e) {
    echo "Erro: {$e->getMessage()}";
}

// Tipos de Error
try {
    // Erros diferentes
} catch (ParseError $e) {
    // Erro de sintaxe
} catch (TypeError $e) {
    // Erro de tipo
} catch (ArithmeticError $e) {
    // Erro aritmético
} catch (DivisionByZeroError $e) {
    // Divisão por zero
} catch (Error $e) {
    // Os demais Error
}

// Error vs Exception
// Error — erro interno do PHP
// Exception — exceção sua

// Throwable (PHP 7.0+)
try {
    // ...
} catch (Throwable $e) {
    // Pega Exception e Error
}
```

**Quando usar:**
Erro fatal que antes matava o script.

**Exemplo prático:**
```php
// Tratamento de TypeError
function add(int $a, int $b): int
{
    return $a + $b;
}

try {
    $result = add(5, 'string');  // TypeError
} catch (TypeError $e) {
    Log::error('Type error', ['message' => $e->getMessage()]);
    return 0;
}

// Tratamento de DivisionByZeroError
try {
    $result = intdiv(10, 0);  // DivisionByZeroError
} catch (DivisionByZeroError $e) {
    Log::error('Division by zero');
    return null;
}

// Laravel Exception Handler (pega tudo)
public function render($request, Throwable $exception)
{
    // Throwable pega Exception e Error

    if ($exception instanceof ModelNotFoundException) {
        return response()->json(['error' => 'Não encontrado'], 404);
    }

    if ($exception instanceof TypeError) {
        Log::error('Type error', [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        return response()->json(['error' => 'Erro interno'], 500);
    }

    return parent::render($request, $exception);
}

// Handler universal
set_exception_handler(function (Throwable $e) {
    Log::error('Unhandled exception', [
        'type' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    echo "Ocorreu um erro";
    exit(1);
});
```

**Na entrevista:**
> "Error (PHP 7.0+) é o erro fatal do PHP, e agora dá para pegar. TypeError, ParseError, DivisionByZeroError. Throwable é a interface base de Exception e Error. No Handler do Laravel eu uso Throwable para tratar tudo."

---

## set_exception_handler e set_error_handler

**O que é:**
Handlers globais de exceção e erro.

**Como funciona:**
```php
// Handler de exceção não capturada
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    echo "Ocorreu um erro. Tente de novo mais tarde.";
    exit(1);
});

throw new Exception('Unhandled exception');
// Imprime: "Ocorreu um erro. Tente de novo mais tarde."

// Handler de erro do PHP (warnings, notices)
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Agora warning vira exceção
try {
    $file = fopen('nonexistent.txt', 'r');  // Warning → Exception
} catch (ErrorException $e) {
    echo "Erro de arquivo: {$e->getMessage()}";
}

// Restaura os handlers
restore_exception_handler();
restore_error_handler();
```

**Quando usar:**
Tratamento global: log, envio para o Sentry.

**Exemplo prático:**
```php
// Laravel bootstrap/app.php (simplificado)
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

// app/Exceptions/Handler.php
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    // Trata toda exceção não capturada
    public function render($request, Throwable $exception)
    {
        // Log
        $this->report($exception);

        // JSON para API
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], $this->getStatusCode($exception));
        }

        // HTML para o browser
        return parent::render($request, $exception);
    }

    public function report(Throwable $exception)
    {
        // Envio para o Sentry
        if (app()->bound('sentry') && $this->shouldReport($exception)) {
            app('sentry')->captureException($exception);
        }

        parent::report($exception);
    }

    private function getStatusCode(Throwable $exception): int
    {
        return method_exists($exception, 'getStatusCode')
            ? $exception->getStatusCode()
            : 500;
    }
}

// Error handler customizado
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Ignora erro suprimido (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    Log::warning('PHP error', [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
    ]);

    // Converte em exceção
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
```

**Na entrevista:**
> "set_exception_handler trata exceção não capturada. set_error_handler transforma warning/notice em exceção. O Laravel usa a classe Handler para o tratamento global. Eu mando para o Sentry, logo e devolvo JSON na API."

---

## Recapitulando

**O essencial:**
- `try-catch-finally` — tratamento de exceções
- `throw` — lança a exceção
- Nativas: Exception, RuntimeException, InvalidArgumentException
- Exceção customizada para erro de domínio
- Métodos: getMessage(), getCode(), getFile(), getLine(), getTrace()
- `Error` (PHP 7.0+) — erro fatal (dá para pegar)
- `Throwable` — interface base (Exception + Error)
- `set_exception_handler` — handler global

**Error vs Exception:**
- Error — erro interno do PHP (TypeError, ParseError)
- Exception — exceção sua

**Importante na entrevista:**
- finally roda sempre (liberar recurso)
- Throwable pega Exception e Error
- Laravel: abort() para HTTP, findOrFail() para model
- Exceção customizada com contexto (getContext())
- Handler para tratamento global e envio ao Sentry
- Cadeia de exceções com getPrevious()

---

## Exercícios práticos

### Exercício 1: Crie uma exceção customizada com contexto

**Enunciado:** Crie a exceção `InsufficientFundsException`, que guarda o saldo, o valor pedido e expõe o método `getContext()`.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(
        string $message,
        private int $balance,
        private int $required,
        private string $currency = 'BRL',
    ) {
        parent::__construct($message);
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getRequired(): int
    {
        return $this->required;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getShortage(): int
    {
        return $this->required - $this->balance;
    }

    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'balance' => $this->balance,
            'required' => $this->required,
            'shortage' => $this->getShortage(),
            'currency' => $this->currency,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    public function render()
    {
        return response()->json([
            'error' => 'insufficient_funds',
            'message' => $this->getMessage(),
            'balance' => $this->balance,
            'required' => $this->required,
            'shortage' => $this->getShortage(),
        ], 422);
    }
}

// Uso
class WalletService
{
    public function withdraw(Wallet $wallet, int $amount): void
    {
        if ($wallet->balance < $amount) {
            throw new InsufficientFundsException(
                'Saldo insuficiente na conta',
                $wallet->balance,
                $amount,
                $wallet->currency
            );
        }

        $wallet->balance -= $amount;
        $wallet->save();
    }
}

// Tratamento
try {
    $walletService->withdraw($wallet, 10000);
} catch (InsufficientFundsException $e) {
    Log::warning('Insufficient funds', $e->getContext());

    return response()->json([
        'error' => 'Saldo insuficiente',
        'shortage' => $e->getShortage(),
    ], 422);
}
```
</details>

### Exercício 2: Implemente o tratamento global de erros

**Enunciado:** Crie um Laravel Exception Handler que loga todos os erros, manda os críticos para o Telegram e devolve JSON na API.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        //
    ];

    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log de todos os erros
            Log::error($e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Erro crítico vai para o Telegram
            if ($this->shouldReportToTelegram($e)) {
                $this->sendToTelegram($e);
            }
        });
    }

    public function render($request, Throwable $e): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        // JSON para request de API
        if ($request->expectsJson()) {
            return $this->renderJsonException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function renderJsonException($request, Throwable $e): JsonResponse
    {
        $status = $this->getStatusCode($e);

        $response = [
            'error' => $this->getErrorMessage($e),
        ];

        // No modo debug, inclui os detalhes
        if (config('app.debug')) {
            $response['exception'] = get_class($e);
            $response['file'] = $e->getFile();
            $response['line'] = $e->getLine();
            $response['trace'] = explode("\n", $e->getTraceAsString());
        }

        return response()->json($response, $status);
    }

    private function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return match (true) {
            $e instanceof NotFoundHttpException => 404,
            $e instanceof \Illuminate\Auth\AuthenticationException => 401,
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
            $e instanceof \Illuminate\Validation\ValidationException => 422,
            default => 500,
        };
    }

    private function getErrorMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof NotFoundHttpException => 'Recurso não encontrado',
            $e instanceof \Illuminate\Auth\AuthenticationException => 'Não autenticado',
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 'Acesso negado',
            default => config('app.debug') ? $e->getMessage() : 'Erro interno do servidor',
        };
    }

    private function shouldReportToTelegram(Throwable $e): bool
    {
        // Só erro crítico
        return $e instanceof \Error
            || $e instanceof \PDOException
            || $this->getStatusCode($e) >= 500;
    }

    private function sendToTelegram(Throwable $e): void
    {
        try {
            $message = "🔴 *Erro em " . config('app.name') . "*\n\n";
            $message .= "*Type:* " . get_class($e) . "\n";
            $message .= "*Message:* " . $e->getMessage() . "\n";
            $message .= "*File:* " . $e->getFile() . ":" . $e->getLine() . "\n";
            $message .= "*URL:* " . request()->fullUrl();

            // Envio para o Telegram (pacote ou HTTP client)
            // TelegramService::send($message);
        } catch (\Exception $telegramException) {
            // Ignora erro de envio no Telegram
            Log::warning('Failed to send to Telegram', [
                'error' => $telegramException->getMessage(),
            ]);
        }
    }
}
```
</details>

### Exercício 3: Transações com exceções

**Enunciado:** Crie um service que cria o pedido e cobra. Se o pagamento falhar, faça rollback da transação e lance a exceção.

<details>
<summary>Solução</summary>

```php
<?php

namespace App\Services;

use App\Exceptions\PaymentFailedException;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function __construct(
        private PaymentGateway $paymentGateway,
    ) {}

    public function createAndPay(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            try {
                // 1. Criar o pedido
                $order = Order::create([
                    'user_id' => $data['user_id'],
                    'amount' => $data['amount'],
                    'status' => 'pending',
                ]);

                Log::info('Order created', ['order_id' => $order->id]);

                // 2. Reservar os produtos
                $this->reserveProducts($order, $data['products']);

                // 3. Cobrar o pagamento
                $payment = $this->paymentGateway->charge(
                    $order->amount,
                    $data['payment_method']
                );

                // 4. Atualizar o status
                $order->update([
                    'status' => 'paid',
                    'payment_id' => $payment->id,
                ]);

                Log::info('Order paid', ['order_id' => $order->id]);

                return $order;

            } catch (PaymentFailedException $e) {
                // Pagamento falhou — a transação dá rollback
                Log::error('Payment failed', [
                    'order_id' => $order->id ?? null,
                    'error' => $e->getMessage(),
                ]);

                throw $e;  // A transação dá rollback sozinha

            } catch (\Exception $e) {
                // Qualquer outro erro
                Log::error('Order creation failed', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);

                throw new \RuntimeException(
                    'Falha ao criar o pedido: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        });
    }

    private function reserveProducts(Order $order, array $products): void
    {
        foreach ($products as $productData) {
            $product = Product::find($productData['id']);

            if ($product->stock < $productData['quantity']) {
                throw new \RuntimeException(
                    "Produto {$product->name} sem estoque"
                );
            }

            $order->products()->attach($product->id, [
                'quantity' => $productData['quantity'],
                'price' => $product->price,
            ]);

            $product->decrement('stock', $productData['quantity']);
        }
    }
}

// Uso
try {
    $order = $orderService->createAndPay([
        'user_id' => 1,
        'amount' => 5000,
        'products' => [
            ['id' => 1, 'quantity' => 2],
            ['id' => 2, 'quantity' => 1],
        ],
        'payment_method' => 'card',
    ]);

    return response()->json($order, 201);

} catch (PaymentFailedException $e) {
    return response()->json([
        'error' => 'payment_failed',
        'message' => $e->getMessage(),
    ], 422);

} catch (\RuntimeException $e) {
    return response()->json([
        'error' => 'order_creation_failed',
        'message' => $e->getMessage(),
    ], 500);
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
