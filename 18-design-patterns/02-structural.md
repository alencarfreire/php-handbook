# 11.2 Padrões estruturais (Structural Patterns)

## Resumo

> **Structural Patterns** — padrões para compor classes e objetos em estruturas maiores.
>
> **Principais:** Adapter (adaptar interfaces), Decorator (adicionar comportamento), Facade (simplificar a API), Proxy (controlar acesso), Composite (estruturas em árvore).
>
> **Exemplos no Laravel:** Cache drivers (Adapter), Middleware (Decorator), Facades (Facade), relations do Eloquent (Proxy).

---

## Conteúdo

- [O que é](#o-que-é)
- [Adapter](#1-adapter-adaptador)
- [Decorator](#2-decorator-decorador)
- [Facade](#3-facade-fachada)
- [Proxy](#4-proxy)
- [Composite](#5-composite)
- [Comparação](#comparação)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Structural Patterns:**
Padrões para compor classes e objetos em estruturas maiores.

**Para quê:**
- Simplificar estruturas complexas
- Adaptar interfaces
- Adicionar funcionalidade sem mudar o código

**Padrões principais:**
1. Adapter
2. Decorator
3. Facade
4. Proxy
5. Composite
6. Bridge
7. Flyweight

---

## 1. Adapter (Adaptador)

**O que é:**
Converte a interface de uma classe na interface que o cliente espera.

**Quando usar:**
- Integrar código legacy
- Usar biblioteca de terceiros com API incompatível

**Problema:**

```php
// Nossa interface
interface PaymentGateway
{
    public function charge(int $amount): Payment;
}

// Biblioteca de terceiros Stripe
class StripeClient
{
    public function createCharge(array $params): array
    {
        // Chamada à API do Stripe
        return ['id' => 'ch_123', 'status' => 'succeeded'];
    }
}

// Interfaces incompatíveis!
```

**Solução: Adapter**

```php
class StripeAdapter implements PaymentGateway
{
    public function __construct(
        private StripeClient $stripe
    ) {}

    public function charge(int $amount): Payment
    {
        // Adaptamos: nossa interface → API do Stripe
        $result = $this->stripe->createCharge([
            'amount' => $amount * 100,  // centavos
            'currency' => 'brl',
        ]);

        return new Payment(
            id: $result['id'],
            status: $result['status'],
            amount: $amount
        );
    }
}

// Uso
$stripe = new StripeClient();
$gateway = new StripeAdapter($stripe);

$payment = $gateway->charge(100);  // Interface única
```

**Laravel Cache Adapter:**

```php
// Laravel Cache adapta drivers diferentes a uma interface única
Cache::store('redis')->put('key', 'value', 3600);
Cache::store('memcached')->put('key', 'value', 3600);
Cache::store('file')->put('key', 'value', 3600);

// Uma interface, implementações diferentes
```

---

## 2. Decorator (Decorador)

**O que é:**
Adiciona funcionalidade a um objeto em runtime, sem mudar a estrutura dele.

**Quando usar:**
- Adicionar comportamento em runtime
- Evitar herança (composition over inheritance)
- Muitas combinações de funcionalidade

**Problema sem Decorator:**

```php
// Ruim: uma classe para cada combinação
class SimpleCoffee {}
class CoffeeWithMilk {}
class CoffeeWithSugar {}
class CoffeeWithMilkAndSugar {}
class CoffeeWithMilkAndSugarAndCaramel {}
// Explosão combinatória!
```

**Solução: Decorator**

```php
interface Coffee
{
    public function getCost(): float;
    public function getDescription(): string;
}

class SimpleCoffee implements Coffee
{
    public function getCost(): float
    {
        return 10;
    }

    public function getDescription(): string
    {
        return 'Café simples';
    }
}

abstract class CoffeeDecorator implements Coffee
{
    public function __construct(
        protected Coffee $coffee
    ) {}
}

class MilkDecorator extends CoffeeDecorator
{
    public function getCost(): float
    {
        return $this->coffee->getCost() + 2;
    }

    public function getDescription(): string
    {
        return $this->coffee->getDescription() . ', leite';
    }
}

class SugarDecorator extends CoffeeDecorator
{
    public function getCost(): float
    {
        return $this->coffee->getCost() + 1;
    }

    public function getDescription(): string
    {
        return $this->coffee->getDescription() . ', açúcar';
    }
}

// Uso: composição de decorators
$coffee = new SimpleCoffee();
$coffee = new MilkDecorator($coffee);
$coffee = new SugarDecorator($coffee);

echo $coffee->getDescription();  // "Café simples, leite, açúcar"
echo $coffee->getCost();  // 13
```

**Laravel Middleware = Decorator Pattern:**

```php
// Middleware decora Request/Response
class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Antes
        if (!auth()->check()) {
            return redirect('/login');
        }

        $response = $next($request);  // Objeto decorado

        // Depois
        $response->header('X-Authenticated', 'true');

        return $response;
    }
}

// Route com decorators
Route::middleware(['auth', 'verified', 'throttle:60,1'])
    ->get('/dashboard', [DashboardController::class, 'index']);
```

---

## 3. Facade (Fachada)

**O que é:**
Oferece uma interface simples para um subsistema complexo.

**Quando usar:**
- Subsistema complexo com muitas classes
- Cliente precisa de uma API simples

**Problema sem Facade:**

```php
// O cliente precisa conhecer todas as classes
$socket = new Socket();
$socket->connect('smtp.example.com', 587);

$connection = new SmtpConnection($socket);
$connection->authenticate('user', 'pass');

$message = new EmailMessage();
$message->setFrom('from@example.com');
$message->setTo('to@example.com');
$message->setSubject('Olá');
$message->setBody('Mundo');

$sender = new EmailSender($connection);
$sender->send($message);

$connection->close();
$socket->disconnect();

// Complicado demais!
```

**Solução: Facade**

```php
class EmailFacade
{
    public static function send(string $to, string $subject, string $body): void
    {
        // Escondemos a complexidade
        $socket = new Socket();
        $socket->connect(config('mail.host'), config('mail.port'));

        $connection = new SmtpConnection($socket);
        $connection->authenticate(config('mail.username'), config('mail.password'));

        $message = new EmailMessage();
        $message->setFrom(config('mail.from'));
        $message->setTo($to);
        $message->setSubject($subject);
        $message->setBody($body);

        $sender = new EmailSender($connection);
        $sender->send($message);

        $connection->close();
        $socket->disconnect();
    }
}

// Uso: simples!
EmailFacade::send('to@example.com', 'Olá', 'Mundo');
```

**Laravel Facades:**

```php
// Laravel Facade = Facade Pattern
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// API simples para subsistemas complexos
Cache::put('key', 'value', 3600);
DB::table('users')->where('active', true)->get();

// Por baixo:
Cache::put() → CacheManager → Repository → Store (Redis/Memcached/File)
```

---

## 4. Proxy

**O que é:**
Objeto substituto que controla o acesso a outro objeto.

**Tipos de Proxy:**
- **Virtual Proxy** — lazy loading
- **Protection Proxy** — access control
- **Remote Proxy** — objeto remoto
- **Caching Proxy** — cache de resultados

**Virtual Proxy (Lazy Loading):**

```php
interface Image
{
    public function render(): string;
}

class RealImage implements Image
{
    private string $data;

    public function __construct(private string $filename)
    {
        // Operação cara
        $this->loadFromDisk();
    }

    private function loadFromDisk(): void
    {
        echo "Carregando imagem: {$this->filename}\n";
        sleep(2);  // Simula operação pesada
        $this->data = file_get_contents($this->filename);
    }

    public function render(): string
    {
        return $this->data;
    }
}

class ImageProxy implements Image
{
    private ?RealImage $realImage = null;

    public function __construct(private string $filename) {}

    public function render(): string
    {
        // Lazy loading: carrega só quando precisa
        if ($this->realImage === null) {
            $this->realImage = new RealImage($this->filename);
        }

        return $this->realImage->render();
    }
}

// Uso
$image = new ImageProxy('large.jpg');
// Imagem NÃO carregada

// ... bastante código ...

echo $image->render();  // Carrega AQUI
```

**Protection Proxy (Access Control):**

```php
interface Document
{
    public function read(): string;
    public function write(string $content): void;
}

class RealDocument implements Document
{
    private string $content = '';

    public function read(): string
    {
        return $this->content;
    }

    public function write(string $content): void
    {
        $this->content = $content;
    }
}

class ProtectedDocumentProxy implements Document
{
    public function __construct(
        private RealDocument $document,
        private User $user
    ) {}

    public function read(): string
    {
        // Access control
        if (!$this->user->hasPermission('read')) {
            throw new AccessDeniedException();
        }

        return $this->document->read();
    }

    public function write(string $content): void
    {
        // Access control
        if (!$this->user->hasPermission('write')) {
            throw new AccessDeniedException();
        }

        $this->document->write($content);
    }
}
```

**Laravel Eloquent Lazy Loading = Virtual Proxy:**

```php
$user = User::find(1);

// posts NÃO carregados (proxy)
$user->posts;  // Carregam AQUI (lazy loading)
```

---

## 5. Composite

**O que é:**
Compõe objetos em estrutura de árvore. O cliente trata objeto único e composição do mesmo jeito.

**Quando usar:**
- Estrutura em árvore (menu, sistema de arquivos)
- Cliente precisa tratar objeto e grupo do mesmo jeito

**Exemplo: estrutura de menu**

```php
interface MenuComponent
{
    public function render(): string;
}

class MenuItem implements MenuComponent
{
    public function __construct(
        private string $name,
        private string $url
    ) {}

    public function render(): string
    {
        return "<li><a href='{$this->url}'>{$this->name}</a></li>";
    }
}

class MenuComposite implements MenuComponent
{
    private array $children = [];

    public function __construct(private string $name) {}

    public function add(MenuComponent $component): void
    {
        $this->children[] = $component;
    }

    public function render(): string
    {
        $html = "<li>{$this->name}<ul>";

        foreach ($this->children as $child) {
            $html .= $child->render();  // Recursão
        }

        $html .= "</ul></li>";

        return $html;
    }
}

// Uso
$menu = new MenuComposite('Menu');

$menu->add(new MenuItem('Início', '/'));
$menu->add(new MenuItem('Sobre', '/about'));

$products = new MenuComposite('Produtos');
$products->add(new MenuItem('Notebooks', '/products/laptops'));
$products->add(new MenuItem('Celulares', '/products/phones'));

$menu->add($products);

echo $menu->render();
```

---

## Comparação

| Pattern | Uso | Exemplo no Laravel |
|---------|----------|-----------------|
| Adapter | Adaptar interfaces | Cache drivers |
| Decorator | Adicionar comportamento | Middleware |
| Facade | Simplificar a API | Laravel Facades |
| Proxy | Controle de acesso, lazy loading | Relations do Eloquent |
| Composite | Estruturas em árvore | Menu, categorias |

---

## Na entrevista

> "Structural Patterns são para composição de objetos. Adapter: adaptar interfaces incompatíveis — o Laravel Cache adapta drivers diferentes. Decorator: adicionar comportamento em runtime, Middleware é o exemplo (composition over inheritance). Facade: API simples para um subsistema complexo, as Laravel Facades. Proxy: controle de acesso ou lazy loading — as relations do Eloquent. Composite: estruturas em árvore (menu, categorias). Decorator e Facade são os mais comuns no Laravel. Middleware = Decorator, Facades = Facade."

---

## Exercícios práticos

### Exercício 1: Implemente um Adapter

Você tem um `LegacyLogger` antigo com o método `writeLog($message)` e a interface nova `Logger` com o método `log($level, $message)`. Crie o adapter.

<details>
<summary>Solução</summary>

```php
// Interface nova
interface Logger
{
    public function log(string $level, string $message): void;
}

// Classe antiga (não dá para mudar)
class LegacyLogger
{
    public function writeLog(string $message): void
    {
        file_put_contents('app.log', $message . PHP_EOL, FILE_APPEND);
    }
}

// Adapter
class LegacyLoggerAdapter implements Logger
{
    public function __construct(
        private LegacyLogger $legacyLogger
    ) {}

    public function log(string $level, string $message): void
    {
        $formattedMessage = "[{$level}] {$message}";
        $this->legacyLogger->writeLog($formattedMessage);
    }
}

// Uso
$legacy = new LegacyLogger();
$logger = new LegacyLoggerAdapter($legacy);

$logger->log('ERROR', 'Algo deu errado');  // Funciona!
```
</details>

### Exercício 2: Implemente um Decorator de cache

Crie um `CachedRepository` que decora o `UserRepository` e adiciona cache no método `find()`.

<details>
<summary>Solução</summary>

```php
interface UserRepository
{
    public function find(int $id): ?User;
    public function save(User $user): void;
}

class DatabaseUserRepository implements UserRepository
{
    public function find(int $id): ?User
    {
        // Consulta no banco
        return User::find($id);
    }

    public function save(User $user): void
    {
        $user->save();
    }
}

class CachedUserRepository implements UserRepository
{
    public function __construct(
        private UserRepository $repository,
        private CacheInterface $cache
    ) {}

    public function find(int $id): ?User
    {
        $key = "user:{$id}";

        return $this->cache->remember($key, 3600, function () use ($id) {
            return $this->repository->find($id);
        });
    }

    public function save(User $user): void
    {
        $this->repository->save($user);

        // Invalida o cache
        $this->cache->forget("user:{$user->id}");
    }
}

// Uso
$repository = new DatabaseUserRepository();
$cachedRepository = new CachedUserRepository($repository, $cache);

$user = $cachedRepository->find(1);  // Consulta no banco
$user = $cachedRepository->find(1);  // Cache hit!
```
</details>

### Exercício 3: Qual a diferença entre Adapter e Facade?

Explique a diferença e dê exemplos de quando usar cada um.

<details>
<summary>Solução</summary>

| Aspecto | Adapter | Facade |
|--------|---------|---------|
| **Função** | Converter a interface | Simplificar a interface |
| **Quantidade de classes** | Geralmente 1 classe | Geralmente várias classes |
| **Muda a interface** | Sim, adapta | Não, simplifica a existente |
| **Quando usar** | Integrar código incompatível | Subsistema complexo |

**Adapter — quando as interfaces são incompatíveis:**
```php
// Stripe API: createCharge(array $params)
// Nossa API: charge(int $amount)
class StripeAdapter implements PaymentGateway
{
    public function charge(int $amount): Payment
    {
        $result = $this->stripe->createCharge(['amount' => $amount * 100]);
        return new Payment($result['id'], $result['status']);
    }
}
```

**Facade — quando o subsistema é complexo:**
```php
// Em vez de lidar com Socket, SmtpConnection, EmailMessage, EmailSender
// API simples:
class EmailFacade
{
    public static function send(string $to, string $subject, string $body): void
    {
        // Escondemos toda a complexidade
    }
}

EmailFacade::send('to@example.com', 'Olá', 'Mundo');
```

**Diferença principal:**
- Adapter converte uma interface em outra
- Facade oferece uma API simples para um sistema complexo
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
