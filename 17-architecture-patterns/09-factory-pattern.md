# 10.9 Factory Pattern

## Resumo

> **Factory Pattern** — padrão para criar objetos encapsulando a lógica de criação.
>
> **Tipos:** Simple Factory (método estático), Factory Method (herança), Abstract Factory (família de objetos).
>
> **Importante:** Use quando a inicialização é complexa ou o tipo do objeto depende de uma condição. No Laravel: Model Factories para testes.

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
Factory Pattern — padrão para criar objetos. Encapsula a lógica de criação.

**Tipos:**
- Simple Factory (método estático)
- Factory Method (via herança)
- Abstract Factory (família de objetos)

---

## Como funciona

**Simple Factory:**

```php
// app/Factories/PaymentFactory.php
class PaymentFactory
{
    public static function create(string $type): PaymentGateway
    {
        return match ($type) {
            'stripe' => new StripePayment(
                apiKey: config('services.stripe.key')
            ),
            'paypal' => new PayPalPayment(
                clientId: config('services.paypal.client_id'),
                secret: config('services.paypal.secret')
            ),
            'cash' => new CashPayment(),
            default => throw new InvalidArgumentException("Tipo de pagamento desconhecido: {$type}")
        };
    }
}

// Uso
$payment = PaymentFactory::create('stripe');
$payment->charge(100);
```

**Factory Method:**

```php
// Classe base
abstract class NotificationService
{
    abstract protected function createNotifier(): Notifier;

    public function send(string $message): void
    {
        $notifier = $this->createNotifier();
        $notifier->send($message);
    }
}

// Implementações
class EmailNotificationService extends NotificationService
{
    protected function createNotifier(): Notifier
    {
        return new EmailNotifier(config('mail.from'));
    }
}

class SmsNotificationService extends NotificationService
{
    protected function createNotifier(): Notifier
    {
        return new SmsNotifier(config('sms.api_key'));
    }
}

// Uso
$service = new EmailNotificationService();
$service->send('Olá!');
```

**Abstract Factory:**

```php
// Interface da factory
interface UIFactory
{
    public function createButton(): Button;
    public function createCheckbox(): Checkbox;
}

// Factories concretas
class WebUIFactory implements UIFactory
{
    public function createButton(): Button
    {
        return new HtmlButton();
    }

    public function createCheckbox(): Checkbox
    {
        return new HtmlCheckbox();
    }
}

class MobileUIFactory implements UIFactory
{
    public function createButton(): Button
    {
        return new MobileButton();
    }

    public function createCheckbox(): Checkbox
    {
        return new MobileCheckbox();
    }
}

// Client
class Application
{
    private Button $button;
    private Checkbox $checkbox;

    public function __construct(UIFactory $factory)
    {
        $this->button = $factory->createButton();
        $this->checkbox = $factory->createCheckbox();
    }
}
```

---

## Quando usar

**Factory para:**
- Lógica complexa de criação de objetos
- Tipos diferentes de objeto conforme a condição
- Isolar a lógica de criação

**NÃO use para:**
- Um `new Class()` simples

---

## Exemplo prático

**Factory para models Eloquent:**

```php
// app/Factories/UserFactory.php
class UserFactory
{
    public static function createAdmin(array $data): User
    {
        return User::create([
            'role' => 'admin',
            'permissions' => ['*'],
            'password' => Hash::make($data['password']),
            ...$data,
        ]);
    }

    public static function createRegularUser(array $data): User
    {
        return User::create([
            'role' => 'user',
            'permissions' => ['read'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => null,  // Precisa de verificação
            ...$data,
        ]);
    }

    public static function createFromSocialProvider(
        string $provider,
        array $providerData
    ): User {
        return User::create([
            'name' => $providerData['name'],
            'email' => $providerData['email'],
            'password' => Hash::make(Str::random(32)),
            'email_verified_at' => now(),  // Já verificado
            "{$provider}_id" => $providerData['id'],
        ]);
    }
}

// Uso
$user = UserFactory::createAdmin([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => 'secret',
]);
```

**Factory para API Responses:**

```php
// app/Factories/ResponseFactory.php
class ResponseFactory
{
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?array $errors = null
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}

// Controller
class UserController extends Controller
{
    public function index()
    {
        $users = User::paginate(20);

        return ResponseFactory::paginated($users);
    }

    public function store(Request $request)
    {
        try {
            $user = User::create($request->validated());

            return ResponseFactory::success($user, 201);
        } catch (\Exception $e) {
            return ResponseFactory::error('Falha ao criar usuário', 500);
        }
    }
}
```

**Factory com Builder:**

```php
// app/Factories/QueryFactory.php
class QueryFactory
{
    public static function createUserQuery(): Builder
    {
        return User::query()
            ->where('active', true)
            ->whereNotNull('email_verified_at');
    }

    public static function createAdminQuery(): Builder
    {
        return self::createUserQuery()
            ->where('role', 'admin');
    }

    public static function createRecentUsersQuery(int $days = 7): Builder
    {
        return self::createUserQuery()
            ->where('created_at', '>=', now()->subDays($days));
    }
}

// Uso
$admins = QueryFactory::createAdminQuery()->get();
$recentUsers = QueryFactory::createRecentUsersQuery(30)->count();
```

**Laravel Model Factories (para testes):**

```php
// database/factories/UserFactory.php
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}

// tests/Feature/UserTest.php
$user = User::factory()->admin()->create();
$users = User::factory()->count(10)->unverified()->create();
```

---

## Na entrevista

> "Factory encapsula a criação de objetos. Simple Factory é um método estático com match/switch. Factory Method usa herança: a subclasse decide qual objeto criar. Abstract Factory é para uma família de objetos. No Laravel: Model Factories para testes. Dá para criar factories próprias para API response, query, user com roles diferentes. Prós: isola a lógica de criação, flexibilidade, DRY. Uso quando a inicialização do objeto é complexa."

---

## Exercícios práticos

### Exercício 1: Crie uma Simple Factory para notificações

**Enunciado:** Implemente um `NotificationFactory` que cria tipos diferentes de notificação conforme a condição.

<details>
<summary>Solução</summary>

```php
// app/Factories/NotificationFactory.php
namespace App\Factories;

use App\Notifications\EmailNotification;
use App\Notifications\SmsNotification;
use App\Notifications\PushNotification;
use App\Notifications\SlackNotification;

class NotificationFactory
{
    public static function create(
        string $type,
        string $message,
        array $options = []
    ): NotificationInterface {
        return match ($type) {
            'email' => new EmailNotification(
                message: $message,
                subject: $options['subject'] ?? 'Notificação',
                from: $options['from'] ?? config('mail.from.address')
            ),
            'sms' => new SmsNotification(
                message: $message,
                phone: $options['phone'] ?? throw new \InvalidArgumentException('Telefone obrigatório')
            ),
            'push' => new PushNotification(
                message: $message,
                title: $options['title'] ?? 'Notificação',
                deviceToken: $options['device_token'] ?? null
            ),
            'slack' => new SlackNotification(
                message: $message,
                channel: $options['channel'] ?? '#general',
                webhookUrl: config('services.slack.webhook_url')
            ),
            default => throw new \InvalidArgumentException("Tipo de notificação desconhecido: {$type}")
        };
    }

    public static function createMultiple(array $types, string $message): array
    {
        return array_map(
            fn($type) => self::create($type, $message),
            $types
        );
    }

    public static function createForUser(User $user, string $message): array
    {
        $notifications = [];

        if ($user->email) {
            $notifications[] = self::create('email', $message);
        }

        if ($user->phone) {
            $notifications[] = self::create('sms', $message, [
                'phone' => $user->phone
            ]);
        }

        if ($user->push_enabled) {
            $notifications[] = self::create('push', $message, [
                'device_token' => $user->device_token
            ]);
        }

        return $notifications;
    }
}

// Uso
$notification = NotificationFactory::create('email', 'Olá!', [
    'subject' => 'Saudação',
    'from' => 'noreply@example.com'
]);

$notification->send();
```
</details>

### Exercício 2: Implemente Factory Method para relatórios

**Enunciado:** Crie o `ReportGenerator` base e as classes concretas para PDF, Excel e CSV.

<details>
<summary>Solução</summary>

```php
// Interface base
interface ReportFormatterInterface
{
    public function format(array $data): string;
    public function getContentType(): string;
    public function getFileExtension(): string;
}

// Implementações
class PdfFormatter implements ReportFormatterInterface
{
    public function format(array $data): string
    {
        // Gera o PDF
        return '...conteúdo pdf...';
    }

    public function getContentType(): string
    {
        return 'application/pdf';
    }

    public function getFileExtension(): string
    {
        return 'pdf';
    }
}

class ExcelFormatter implements ReportFormatterInterface
{
    public function format(array $data): string
    {
        // Gera o Excel
        return '...conteúdo excel...';
    }

    public function getContentType(): string
    {
        return 'application/vnd.ms-excel';
    }

    public function getFileExtension(): string
    {
        return 'xlsx';
    }
}

class CsvFormatter implements ReportFormatterInterface
{
    public function format(array $data): string
    {
        $csv = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        return $content;
    }

    public function getContentType(): string
    {
        return 'text/csv';
    }

    public function getFileExtension(): string
    {
        return 'csv';
    }
}

// Factory Method — classe base
abstract class ReportGenerator
{
    abstract protected function createFormatter(): ReportFormatterInterface;

    public function generate(array $data): string
    {
        $formatter = $this->createFormatter();
        return $formatter->format($data);
    }

    public function download(array $data, string $filename): Response
    {
        $formatter = $this->createFormatter();
        $content = $formatter->format($data);

        return response($content)
            ->header('Content-Type', $formatter->getContentType())
            ->header('Content-Disposition',
                "attachment; filename={$filename}.{$formatter->getFileExtension()}");
    }
}

// Geradores concretos
class PdfReportGenerator extends ReportGenerator
{
    protected function createFormatter(): ReportFormatterInterface
    {
        return new PdfFormatter();
    }
}

class ExcelReportGenerator extends ReportGenerator
{
    protected function createFormatter(): ReportFormatterInterface
    {
        return new ExcelFormatter();
    }
}

class CsvReportGenerator extends ReportGenerator
{
    protected function createFormatter(): ReportFormatterInterface
    {
        return new CsvFormatter();
    }
}

// Controller
class ReportController extends Controller
{
    public function download(Request $request)
    {
        $data = Order::with('user')->get()->toArray();
        $format = $request->query('format', 'pdf');

        $generator = match ($format) {
            'pdf' => new PdfReportGenerator(),
            'excel' => new ExcelReportGenerator(),
            'csv' => new CsvReportGenerator(),
            default => new PdfReportGenerator(),
        };

        return $generator->download($data, 'relatorio_pedidos');
    }
}
```
</details>

### Exercício 3: Use Laravel Model Factory

**Enunciado:** Crie uma Model Factory para `Product` com states diferentes (active, inactive, featured).

<details>
<summary>Solução</summary>

```php
// database/factories/ProductFactory.php
namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'is_featured' => false,
            'category_id' => Category::factory(),
            'image_url' => fake()->imageUrl(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    // State: produto inativo
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'stock' => 0,
        ]);
    }

    // State: produto em destaque
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'stock' => fake()->numberBetween(50, 200),
        ]);
    }

    // State: produto em promoção
    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'original_price' => $attributes['price'],
            'price' => $attributes['price'] * 0.8, // 20% de desconto
            'is_on_sale' => true,
        ]);
    }

    // State: produto sem imagem
    public function withoutImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_url' => null,
        ]);
    }

    // State: produto de uma categoria específica
    public function inCategory(Category $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category->id,
        ]);
    }

    // Sequência ao criar vários produtos
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            // Adiciona tags depois de criar
            $product->tags()->attach(
                fake()->randomElements([1, 2, 3, 4, 5], rand(2, 4))
            );
        });
    }
}

// database/seeders/ProductSeeder.php
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 50 produtos ativos
        Product::factory()->count(50)->create();

        // 10 produtos em destaque
        Product::factory()->featured()->count(10)->create();

        // 20 produtos inativos
        Product::factory()->inactive()->count(20)->create();

        // 15 produtos em promoção
        Product::factory()->onSale()->count(15)->create();

        // Produtos de uma categoria específica
        $electronics = Category::where('name', 'Eletrônicos')->first();
        Product::factory()
            ->inCategory($electronics)
            ->count(30)
            ->create();
    }
}

// Uso nos testes
class ProductTest extends TestCase
{
    public function test_can_purchase_active_product(): void
    {
        $product = Product::factory()->create(['stock' => 10]);

        $this->assertTrue($product->isAvailable());
    }

    public function test_cannot_purchase_inactive_product(): void
    {
        $product = Product::factory()->inactive()->create();

        $this->assertFalse($product->isAvailable());
    }

    public function test_featured_products_have_high_stock(): void
    {
        $product = Product::factory()->featured()->create();

        $this->assertGreaterThanOrEqual(50, $product->stock);
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
