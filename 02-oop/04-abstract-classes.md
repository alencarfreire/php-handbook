# 2.4 Classes abstratas

## Resumo

> **Classe abstrata** — classe que você não instancia direto, só herda. Pode ter método comum (com implementação) e método abstrato (sem).
>
> **Conceitos-chave:** métodos abstract (as classes filhas são obrigadas a implementar), Template Method Pattern, combinação com interfaces.
>
> **Importante:** Classe abstrata é para lógica comum (COMO fazer), interface é para contrato (O QUÊ fazer). Dá para combinar: interface + classe abstract.

---

## Conteúdo

- [O que é classe abstrata](#o-que-é-classe-abstrata)
- [Métodos abstratos](#métodos-abstratos)
- [Classe abstrata vs Interface](#classe-abstrata-vs-interface)
- [protected em classes abstratas](#protected-em-classes-abstratas)
- [Template Method Pattern](#template-method-pattern)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é classe abstrata

**O que é:**
Classe que você não instancia direto (só herda). Pode ter método comum (com implementação) e método abstrato (sem implementação).

**Como funciona:**
```php
abstract class Animal
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    // Método comum (com implementação)
    public function eat(): string
    {
        return "{$this->name} come";
    }

    // Método abstrato (SEM implementação)
    abstract public function makeSound(): string;
}

class Dog extends Animal
{
    // OBRIGATÓRIO implementar o método abstrato
    public function makeSound(): string
    {
        return "{$this->name} late: Au au!";
    }
}

class Cat extends Animal
{
    public function makeSound(): string
    {
        return "{$this->name} mia: Miau!";
    }
}

// $animal = new Animal('Animal');  // ❌ Cannot instantiate abstract class

$dog = new Dog('Rex');
echo $dog->eat();        // "Rex come" (herdado)
echo $dog->makeSound();  // "Rex late: Au au!" (implementado em Dog)

$cat = new Cat('Mimi');
echo $cat->makeSound();  // "Mimi mia: Miau!"
```

**Quando usar:**
Quando um grupo de classes compartilha lógica, mas parte dos métodos cada filha implementa do seu jeito.

**Exemplo prático:**
```php
// Repository base
abstract class BaseRepository
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    // Métodos comuns (com implementação)
    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    // Métodos abstratos (cada repository implementa o seu)
    abstract public function findByCustomCriteria(array $criteria): Collection;
}

class UserRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new User());
    }

    public function findByCustomCriteria(array $criteria): Collection
    {
        $query = $this->model->query();

        if (isset($criteria['department_id'])) {
            $query->where('department_id', $criteria['department_id']);
        }

        if (isset($criteria['is_active'])) {
            $query->where('is_active', $criteria['is_active']);
        }

        return $query->get();
    }
}

// Uso
$repository = new UserRepository();
$user = $repository->find(1);  // Método herdado
$users = $repository->findByCustomCriteria(['is_active' => true]);
```

**Na entrevista:**
> "Classe abstrata você não instancia direto, só herda. Pode ter método comum (com implementação) e método abstract (sem). As filhas são obrigadas a implementar todos os abstract. Uso para classe base com lógica comum."

---

## Métodos abstratos

**O que é:**
Métodos sem implementação (só a declaração). As classes filhas **são obrigadas** a implementar.

**Como funciona:**
```php
abstract class Shape
{
    protected string $color;

    public function __construct(string $color)
    {
        $this->color = $color;
    }

    // Método comum
    public function getColor(): string
    {
        return $this->color;
    }

    // Métodos abstratos (SEM implementação)
    abstract public function calculateArea(): float;
    abstract public function calculatePerimeter(): float;
}

class Circle extends Shape
{
    private float $radius;

    public function __construct(string $color, float $radius)
    {
        parent::__construct($color);
        $this->radius = $radius;
    }

    // OBRIGATÓRIO implementar
    public function calculateArea(): float
    {
        return pi() * $this->radius ** 2;
    }

    public function calculatePerimeter(): float
    {
        return 2 * pi() * $this->radius;
    }
}

class Rectangle extends Shape
{
    private float $width;
    private float $height;

    public function __construct(string $color, float $width, float $height)
    {
        parent::__construct($color);
        $this->width = $width;
        $this->height = $height;
    }

    public function calculateArea(): float
    {
        return $this->width * $this->height;
    }

    public function calculatePerimeter(): float
    {
        return 2 * ($this->width + $this->height);
    }
}

// Polimorfismo
function printShapeInfo(Shape $shape): void
{
    echo "Cor: {$shape->getColor()}\n";
    echo "Área: {$shape->calculateArea()}\n";
    echo "Perímetro: {$shape->calculatePerimeter()}\n";
}

$circle = new Circle('vermelho', 5);
$rectangle = new Rectangle('azul', 4, 6);

printShapeInfo($circle);
printShapeInfo($rectangle);
```

**Quando usar:**
Quando o algoritmo é o mesmo, mas a implementação muda em cada classe.

**Exemplo prático:**
```php
// Payment Gateway
abstract class PaymentGateway
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct(string $apiKey, string $apiUrl)
    {
        $this->apiKey = $apiKey;
        $this->apiUrl = $apiUrl;
    }

    // Lógica comum (com implementação)
    protected function logTransaction(string $type, int $amount): void
    {
        Log::info("Payment {$type}: {$amount}", [
            'gateway' => static::class,
        ]);
    }

    public function processPayment(int $amount, string $currency): bool
    {
        $this->logTransaction('charge', $amount);

        try {
            return $this->charge($amount, $currency);
        } catch (\Exception $e) {
            $this->logTransaction('failed', $amount);
            throw $e;
        }
    }

    // Métodos abstratos (cada gateway implementa o seu)
    abstract protected function charge(int $amount, string $currency): bool;
    abstract public function refund(string $transactionId, int $amount): bool;
    abstract public function getBalance(): int;
}

class StripeGateway extends PaymentGateway
{
    protected function charge(int $amount, string $currency): bool
    {
        // Stripe API
        $stripe = new \Stripe\StripeClient($this->apiKey);
        $charge = $stripe->charges->create([
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return $charge->status === 'succeeded';
    }

    public function refund(string $transactionId, int $amount): bool
    {
        // Stripe refund API
        return true;
    }

    public function getBalance(): int
    {
        // Stripe balance API
        return 10000;
    }
}

class PayPalGateway extends PaymentGateway
{
    protected function charge(int $amount, string $currency): bool
    {
        // PayPal API
        return true;
    }

    public function refund(string $transactionId, int $amount): bool
    {
        // PayPal refund API
        return true;
    }

    public function getBalance(): int
    {
        // PayPal balance API
        return 5000;
    }
}

// Dependency Injection
class OrderService
{
    public function __construct(
        private PaymentGateway $gateway,  // Qualquer gateway
    ) {}

    public function charge(Order $order): bool
    {
        return $this->gateway->processPayment($order->amount, 'BRL');
    }
}
```

**Na entrevista:**
> "Método abstrato não tem implementação, só a assinatura. As filhas são obrigadas a implementar. Uso para definir o esqueleto do algoritmo: a classe base monta a estrutura, as filhas implementam o detalhe."

---

## Classe abstrata vs Interface

**Comparação:**

| Classe abstrata | Interface |
|-------------------|-----------|
| Pode ter implementação | Só declaração de métodos |
| Pode ter propriedades | Só constantes |
| Só herança simples | Pode implementar várias |
| Pode ter construtor | Sem construtor |
| Métodos: public, protected, private | Métodos: só public |
| Para lógica comum ("COMO") | Para contrato ("O QUÊ") |

**Classe abstrata:**
```php
abstract class PaymentGateway
{
    protected string $apiKey;  // Propriedades

    public function __construct(string $apiKey)  // Construtor
    {
        $this->apiKey = $apiKey;
    }

    // Método com implementação
    protected function log(string $message): void
    {
        Log::info($message);
    }

    // Método abstrato
    abstract public function charge(int $amount): bool;
}
```

**Interface:**
```php
interface PaymentGatewayInterface
{
    public const STATUS_SUCCESS = 'success';  // Constante

    // Só declaração
    public function charge(int $amount): bool;
    public function refund(string $id): bool;
}
```

**Quando usar:**
- **Classe abstrata** — quando há lógica comum para um grupo de classes
- **Interface** — para contrato, polimorfismo, DI

**Dá para combinar:**
```php
interface PaymentGatewayInterface
{
    public function charge(int $amount): bool;
    public function refund(string $id): bool;
}

abstract class BasePaymentGateway implements PaymentGatewayInterface
{
    protected string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function log(string $message): void
    {
        Log::info($message);
    }

    // Método comum para todo gateway
    public function processPayment(int $amount): bool
    {
        $this->log("Processing payment: {$amount}");

        try {
            return $this->charge($amount);
        } catch (\Exception $e) {
            $this->log("Payment failed: {$e->getMessage()}");
            return false;
        }
    }

    // Método abstrato (cada gateway implementa o seu)
    abstract public function charge(int $amount): bool;
}

class StripeGateway extends BasePaymentGateway
{
    public function charge(int $amount): bool
    {
        // Stripe API
        return true;
    }

    public function refund(string $id): bool
    {
        // Stripe refund
        return true;
    }
}

// DI pela interface
function pay(PaymentGatewayInterface $gateway, int $amount): bool
{
    return $gateway->charge($amount);
}

$stripe = new StripeGateway('api_key');
pay($stripe, 1000);
```

**Exemplo prático:**
```php
// Laravel Job
interface ShouldQueue  // Interface (contrato)
{
    public function handle(): void;
}

abstract class Job implements ShouldQueue  // Classe abstrata (lógica comum)
{
    public int $tries = 3;
    public int $timeout = 60;

    protected function log(string $message): void
    {
        Log::info($message);
    }

    // Método abstrato
    abstract public function handle(): void;
}

class SendEmailJob extends Job
{
    public function __construct(
        private User $user,
        private string $message,
    ) {}

    public function handle(): void
    {
        $this->log("Sending email to {$this->user->email}");
        Mail::to($this->user)->send(new MessageMail($this->message));
    }
}

// Dá para usar como ShouldQueue ou Job
dispatch(new SendEmailJob($user, 'Olá'));
```

**Na entrevista:**
> "Classe abstrata é para lógica comum (COMO), pode ter implementação. Interface é para contrato (O QUÊ), só declaração. Classe abstrata tem um pai só, interface você implementa várias. Muitas vezes eu combino: interface + classe abstrata."

---

## protected em classes abstratas

**O que é:**
Métodos abstratos podem ser protected — só as classes filhas enxergam.

**Como funciona:**
```php
abstract class BaseController
{
    // Método abstrato protected
    abstract protected function authorize(): bool;

    public function index()
    {
        if (!$this->authorize()) {
            abort(403);
        }

        return $this->getData();
    }

    // As filhas implementam este método
    abstract protected function getData(): array;
}

class AdminController extends BaseController
{
    protected function authorize(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getData(): array
    {
        return ['users' => User::all()];
    }
}

class UserController extends BaseController
{
    protected function authorize(): bool
    {
        return auth()->check();
    }

    protected function getData(): array
    {
        return ['user' => auth()->user()];
    }
}
```

**Quando usar:**
Para método que só existe dentro da hierarquia.

**Exemplo prático:**
```php
// Service base
abstract class BaseService
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    // protected — só para as classes filhas
    abstract protected function validate(array $data): bool;
    abstract protected function process(array $data): mixed;

    // public — API externa
    public function execute(array $data): mixed
    {
        $this->logger->info('Executing service', ['data' => $data]);

        if (!$this->validate($data)) {
            throw new \InvalidArgumentException('Invalid data');
        }

        $result = $this->process($data);

        $this->logger->info('Service completed', ['result' => $result]);

        return $result;
    }
}

class OrderService extends BaseService
{
    protected function validate(array $data): bool
    {
        return isset($data['user_id']) && isset($data['amount']);
    }

    protected function process(array $data): mixed
    {
        return Order::create($data);
    }
}

// O cliente só vê execute()
$service = new OrderService($logger);
$order = $service->execute(['user_id' => 1, 'amount' => 1000]);
```

**Na entrevista:**
> "Método abstrato pode ser protected — só as filhas enxergam. Uso para lógica interna da hierarquia. public é API externa, protected é implementação interna."

---

## Template Method Pattern

**O que é:**
Padrão de projeto: a classe base define o algoritmo, as filhas implementam os passos.

**Como funciona:**
```php
abstract class DataImporter
{
    // Template method (define o algoritmo)
    final public function import(string $file): void
    {
        $this->validate($file);
        $data = $this->parse($file);
        $this->transform($data);
        $this->save($data);
        $this->cleanup();
    }

    // Passos do algoritmo (abstratos)
    abstract protected function validate(string $file): void;
    abstract protected function parse(string $file): array;
    abstract protected function transform(array &$data): void;
    abstract protected function save(array $data): void;

    // Passo opcional (com implementação padrão)
    protected function cleanup(): void
    {
        // Por padrão, não faz nada
    }
}

class CsvImporter extends DataImporter
{
    protected function validate(string $file): void
    {
        if (!str_ends_with($file, '.csv')) {
            throw new \InvalidArgumentException('Não é um arquivo CSV');
        }
    }

    protected function parse(string $file): array
    {
        $handle = fopen($file, 'r');
        $data = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }

        fclose($handle);
        return $data;
    }

    protected function transform(array &$data): void
    {
        // Transformação dos dados CSV
    }

    protected function save(array $data): void
    {
        foreach ($data as $row) {
            User::create($row);
        }
    }

    protected function cleanup(): void
    {
        // Remove o arquivo temporário
        unlink($this->file);
    }
}

class JsonImporter extends DataImporter
{
    protected function validate(string $file): void
    {
        if (!str_ends_with($file, '.json')) {
            throw new \InvalidArgumentException('Não é um arquivo JSON');
        }
    }

    protected function parse(string $file): array
    {
        return json_decode(file_get_contents($file), true);
    }

    protected function transform(array &$data): void
    {
        // Transformação dos dados JSON
    }

    protected function save(array $data): void
    {
        User::insert($data);
    }
}

// Uso
$importer = new CsvImporter();
$importer->import('users.csv');

$importer = new JsonImporter();
$importer->import('users.json');
```

**Quando usar:**
Quando o algoritmo é o mesmo, mas os passos variam.

**Exemplo prático:**
```php
// Laravel: Middleware pipeline
abstract class BaseMiddleware
{
    final public function handle($request, Closure $next)
    {
        // Passo 1: Before (antes de processar)
        $this->before($request);

        // Passo 2: Processamento principal (pode interromper)
        if (!$this->authorize($request)) {
            return $this->deny($request);
        }

        // Passo 3: Passa adiante
        $response = $next($request);

        // Passo 4: After (depois de processar)
        $this->after($request, $response);

        return $response;
    }

    // Passos (abstratos)
    abstract protected function authorize($request): bool;

    // Passos opcionais (com implementação padrão)
    protected function before($request): void {}
    protected function after($request, $response): void {}

    protected function deny($request)
    {
        abort(403);
    }
}

class AdminMiddleware extends BaseMiddleware
{
    protected function authorize($request): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function before($request): void
    {
        Log::info('Admin access attempt', ['user' => auth()->id()]);
    }
}
```

**Na entrevista:**
> "Template Method Pattern: a classe base define o algoritmo (template method), as filhas implementam os passos. O template method é final, não pode sobrescrever. Uso para padronizar processo com passos que variam."

---

## Recapitulando

**O essencial:**
- `abstract class` — você não instancia direto, só herda
- Pode ter método comum (com implementação) e abstract (sem)
- Métodos abstract — as filhas são obrigadas a implementar
- Pode ter propriedade, construtor, métodos protected/private
- Só herança simples (um pai)
- Para lógica comum de um grupo de classes

**Classe abstrata vs Interface:**
- Abstrata — lógica comum (COMO fazer), pode ter implementação
- Interface — contrato (O QUÊ fazer), só declaração
- Muitas vezes eu combino: interface + abstract class

**Template Method Pattern:**
- Método final define o algoritmo
- Métodos abstract — passos do algoritmo
- As filhas implementam os passos

**Importante na entrevista:**
- abstract class vs interface: implementação vs contrato
- Métodos abstract as filhas são obrigadas a implementar
- protected abstract — para hierarquia interna
- Template Method Pattern — padronizar algoritmos
- No Laravel: BaseController, BaseMiddleware, Job

---

## Exercícios práticos

### Exercício 1: Template Method Pattern para importar dados

**Enunciado:** Crie um `DataImporter` abstrato com os passos: validate, parse, transform, save. Implemente `CsvImporter` e `JsonImporter`.

<details>
<summary>Solução</summary>

```php
abstract class DataImporter
{
    protected array $errors = [];
    protected array $imported = [];

    // Template method (final — não pode sobrescrever)
    final public function import(string $file): array
    {
        $this->reset();

        // Passo 1: Validação do arquivo
        if (!$this->validate($file)) {
            return [
                'success' => false,
                'errors' => $this->errors,
            ];
        }

        // Passo 2: Parse
        $data = $this->parse($file);

        if (empty($data)) {
            $this->errors[] = 'Nenhum dado encontrado no arquivo';
            return ['success' => false, 'errors' => $this->errors];
        }

        // Passo 3: Transformação
        $transformed = $this->transform($data);

        // Passo 4: Persistência
        $this->save($transformed);

        // Passo 5: Limpeza (opcional)
        $this->cleanup($file);

        return [
            'success' => true,
            'imported' => count($this->imported),
            'data' => $this->imported,
        ];
    }

    // Métodos abstratos (as filhas são obrigadas a implementar)
    abstract protected function validate(string $file): bool;
    abstract protected function parse(string $file): array;
    abstract protected function save(array $data): void;

    // Métodos com implementação padrão (pode sobrescrever)
    protected function transform(array $data): array
    {
        // Por padrão — sem transformação
        return $data;
    }

    protected function cleanup(string $file): void
    {
        // Por padrão — não faz nada
    }

    protected function reset(): void
    {
        $this->errors = [];
        $this->imported = [];
    }

    protected function addError(string $error): void
    {
        $this->errors[] = $error;
    }
}

class CsvImporter extends DataImporter
{
    protected function validate(string $file): bool
    {
        if (!file_exists($file)) {
            $this->addError('Arquivo não encontrado');
            return false;
        }

        if (!str_ends_with($file, '.csv')) {
            $this->addError('O arquivo precisa ser CSV');
            return false;
        }

        return true;
    }

    protected function parse(string $file): array
    {
        $data = [];
        $handle = fopen($file, 'r');

        // Primeira linha — cabeçalhos
        $headers = fgetcsv($handle);

        while (($row = fgetcsv($handle)) !== false) {
            $data[] = array_combine($headers, $row);
        }

        fclose($handle);
        return $data;
    }

    protected function transform(array $data): array
    {
        return array_map(function ($row) {
            // Limpeza dos dados
            return array_map('trim', $row);
        }, $data);
    }

    protected function save(array $data): void
    {
        foreach ($data as $row) {
            // Salva no banco (simplificado)
            echo "INSERT INTO users: {$row['name']}, {$row['email']}\n";
            $this->imported[] = $row;
        }
    }

    protected function cleanup(string $file): void
    {
        // Remove o arquivo temporário
        echo "Removendo arquivo temporário: {$file}\n";
    }
}

class JsonImporter extends DataImporter
{
    protected function validate(string $file): bool
    {
        if (!file_exists($file)) {
            $this->addError('Arquivo não encontrado');
            return false;
        }

        if (!str_ends_with($file, '.json')) {
            $this->addError('O arquivo precisa ser JSON');
            return false;
        }

        return true;
    }

    protected function parse(string $file): array
    {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->addError('JSON inválido: ' . json_last_error_msg());
            return [];
        }

        return $data;
    }

    protected function transform(array $data): array
    {
        return array_map(function ($row) {
            // Conversão de datas
            if (isset($row['created_at'])) {
                $row['created_at'] = date('Y-m-d H:i:s', strtotime($row['created_at']));
            }
            return $row;
        }, $data);
    }

    protected function save(array $data): void
    {
        // Batch insert
        echo "Batch INSERT INTO users (" . count($data) . " linhas)\n";
        $this->imported = $data;
    }
}

// Uso
$csvImporter = new CsvImporter();
$result = $csvImporter->import('users.csv');
print_r($result);

$jsonImporter = new JsonImporter();
$result = $jsonImporter->import('users.json');
print_r($result);
```
</details>

### Exercício 2: Payment Gateway abstrato

**Enunciado:** Crie um `PaymentGateway` abstrato com lógica comum de log e métodos abstratos charge e refund.

<details>
<summary>Solução</summary>

```php
abstract class PaymentGateway
{
    protected array $logs = [];

    public function __construct(
        protected string $apiKey,
        protected bool $isProduction = false,
    ) {}

    // Lógica comum (implementada na classe base)
    protected function log(string $type, string $message, array $context = []): void
    {
        $log = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'gateway' => static::class,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->logs[] = $log;

        echo "[{$type}] " . static::class . ": {$message}\n";
    }

    public function processPayment(int $amount, string $currency, array $metadata = []): array
    {
        $this->log('info', "Processando pagamento", [
            'amount' => $amount,
            'currency' => $currency,
        ]);

        try {
            // Chama o método abstrato
            $result = $this->charge($amount, $currency, $metadata);

            $this->log('success', "Pagamento concluído", [
                'transaction_id' => $result['transaction_id'],
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log('error', "Pagamento falhou: {$e->getMessage()}");
            throw $e;
        }
    }

    public function processRefund(string $transactionId, int $amount): array
    {
        $this->log('info', "Processando reembolso", [
            'transaction_id' => $transactionId,
            'amount' => $amount,
        ]);

        try {
            $result = $this->refund($transactionId, $amount);

            $this->log('success', "Reembolso concluído", [
                'refund_id' => $result['refund_id'],
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->log('error', "Reembolso falhou: {$e->getMessage()}");
            throw $e;
        }
    }

    // Métodos abstratos (cada gateway implementa do seu jeito)
    abstract protected function charge(int $amount, string $currency, array $metadata): array;
    abstract protected function refund(string $transactionId, int $amount): array;
    abstract public function getBalance(): int;

    // Getter dos logs
    public function getLogs(): array
    {
        return $this->logs;
    }

    // Método helper
    protected function buildApiUrl(string $endpoint): string
    {
        $baseUrl = $this->isProduction
            ? $this->getProductionUrl()
            : $this->getSandboxUrl();

        return rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    abstract protected function getProductionUrl(): string;
    abstract protected function getSandboxUrl(): string;
}

class StripeGateway extends PaymentGateway
{
    protected function charge(int $amount, string $currency, array $metadata): array
    {
        // Stripe API
        $url = $this->buildApiUrl('/v1/charges');

        return [
            'transaction_id' => 'stripe_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'fee' => (int) ($amount * 0.029 + 30),
            'metadata' => $metadata,
        ];
    }

    protected function refund(string $transactionId, int $amount): array
    {
        $url = $this->buildApiUrl('/v1/refunds');

        return [
            'refund_id' => 'refund_' . uniqid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'succeeded',
        ];
    }

    public function getBalance(): int
    {
        $url = $this->buildApiUrl('/v1/balance');
        return 1000000;
    }

    protected function getProductionUrl(): string
    {
        return 'https://api.stripe.com';
    }

    protected function getSandboxUrl(): string
    {
        return 'https://api.stripe.com/test';
    }
}

class YooKassaGateway extends PaymentGateway
{
    protected function charge(int $amount, string $currency, array $metadata): array
    {
        $url = $this->buildApiUrl('/v3/payments');

        return [
            'transaction_id' => 'yookassa_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded',
            'fee' => (int) ($amount * 0.035),
            'metadata' => $metadata,
        ];
    }

    protected function refund(string $transactionId, int $amount): array
    {
        $url = $this->buildApiUrl('/v3/refunds');

        return [
            'refund_id' => 'yookassa_refund_' . uniqid(),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'status' => 'succeeded',
        ];
    }

    public function getBalance(): int
    {
        $url = $this->buildApiUrl('/v3/me');
        return 500000;
    }

    protected function getProductionUrl(): string
    {
        return 'https://api.yookassa.ru';
    }

    protected function getSandboxUrl(): string
    {
        return 'https://api.yookassa.ru/sandbox';
    }
}

// Uso
$stripe = new StripeGateway('sk_test_xxx', false);
$payment = $stripe->processPayment(100000, 'BRL', ['order_id' => 123]);
print_r($payment);
print_r($stripe->getLogs());

$yookassa = new YooKassaGateway('shop_xxx', false);
$payment = $yookassa->processPayment(100000, 'BRL', ['order_id' => 456]);
```
</details>

### Exercício 3: Validator abstrato com Template Method

**Enunciado:** Crie um `Validator` abstrato com o método `validate()`. Implemente `UserValidator` e `OrderValidator`.

<details>
<summary>Solução</summary>

```php
abstract class Validator
{
    protected array $errors = [];
    protected array $data = [];

    final public function validate(array $data): bool
    {
        $this->reset();
        $this->data = $data;

        // Passo 1: Validação básica
        $this->validateRequired();

        // Passo 2: Validação de tipos
        $this->validateTypes();

        // Passo 3: Validação custom (implementada nas filhas)
        $this->validateCustom();

        return empty($this->errors);
    }

    // Métodos abstratos
    abstract protected function getRequiredFields(): array;
    abstract protected function getFieldTypes(): array;
    abstract protected function validateCustom(): void;

    // Métodos comuns
    protected function validateRequired(): void
    {
        foreach ($this->getRequiredFields() as $field) {
            if (!isset($this->data[$field]) || $this->data[$field] === '') {
                $this->addError($field, "Campo {$field} é obrigatório");
            }
        }
    }

    protected function validateTypes(): void
    {
        foreach ($this->getFieldTypes() as $field => $type) {
            if (!isset($this->data[$field])) {
                continue;
            }

            $value = $this->data[$field];

            $isValid = match($type) {
                'string' => is_string($value),
                'int' => is_int($value),
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'array' => is_array($value),
                default => true,
            };

            if (!$isValid) {
                $this->addError($field, "Campo {$field} precisa ser {$type}");
            }
        }
    }

    protected function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    protected function reset(): void
    {
        $this->errors = [];
        $this->data = [];
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}

class UserValidator extends Validator
{
    protected function getRequiredFields(): array
    {
        return ['name', 'email', 'password'];
    }

    protected function getFieldTypes(): array
    {
        return [
            'name' => 'string',
            'email' => 'email',
            'password' => 'string',
            'age' => 'int',
        ];
    }

    protected function validateCustom(): void
    {
        // Valida o tamanho da senha
        if (isset($this->data['password']) && strlen($this->data['password']) < 8) {
            $this->addError('password', 'A senha precisa ter pelo menos 8 caracteres');
        }

        // Valida a idade
        if (isset($this->data['age']) && $this->data['age'] < 18) {
            $this->addError('age', 'O usuário precisa ter 18 anos ou mais');
        }

        // Valida email único (simplificado)
        if (isset($this->data['email']) && $this->data['email'] === 'ocupado@email.com') {
            $this->addError('email', 'Email já cadastrado');
        }
    }
}

class OrderValidator extends Validator
{
    protected function getRequiredFields(): array
    {
        return ['user_id', 'amount', 'items'];
    }

    protected function getFieldTypes(): array
    {
        return [
            'user_id' => 'int',
            'amount' => 'int',
            'items' => 'array',
        ];
    }

    protected function validateCustom(): void
    {
        // Valida o valor
        if (isset($this->data['amount']) && $this->data['amount'] < 100) {
            $this->addError('amount', 'Valor mínimo do pedido é 100');
        }

        // Valida items
        if (isset($this->data['items']) && count($this->data['items']) === 0) {
            $this->addError('items', 'O pedido precisa ter pelo menos um item');
        }

        // Valida cada item
        if (isset($this->data['items'])) {
            foreach ($this->data['items'] as $index => $item) {
                if (!isset($item['product_id'])) {
                    $this->addError("items.{$index}", "Product ID é obrigatório");
                }

                if (!isset($item['quantity']) || $item['quantity'] < 1) {
                    $this->addError("items.{$index}", "A quantidade precisa ser pelo menos 1");
                }
            }
        }
    }
}

// Uso
$userValidator = new UserValidator();

$isValid = $userValidator->validate([
    'name' => 'João',
    'email' => 'joao@email.com',
    'password' => '12345',  // Curta
    'age' => 16,  // Menor de 18
]);

echo "Válido: " . ($isValid ? 'Sim' : 'Não') . "\n";
print_r($userValidator->getErrors());
// [
//   'password' => ['A senha precisa ter pelo menos 8 caracteres'],
//   'age' => ['O usuário precisa ter 18 anos ou mais']
// ]

$orderValidator = new OrderValidator();
$isValid = $orderValidator->validate([
    'user_id' => 1,
    'amount' => 50,  // Abaixo do mínimo
    'items' => [
        ['product_id' => 10, 'quantity' => 2],
        ['quantity' => 1],  // Sem product_id
    ],
]);

print_r($orderValidator->getErrors());
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
