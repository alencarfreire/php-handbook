# 7.1 Testes unitários

## Resumo

> **Testes unitários** — testam uma peça isolada (função, método, classe). Rápidos, sem banco/HTTP. Estrutura AAA: Arrange-Act-Assert.
>
> **Criar:** `php artisan make:test UserTest --unit`. Assertions: `assertEquals()`, `assertTrue()`, `expectException()`.
>
> **Importante:** Mock de dependências com Mockery. Data providers para testes parametrizados. `setUp()`/`tearDown()` para preparar e limpar.

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
Testes unitários testam uma peça isolada (função, método, classe), sem o resto do sistema.

**O essencial:**
- Testam uma "unidade" de código
- Rápidos (sem banco, HTTP, arquivo)
- Isolados (mock das dependências)

---

## Como funciona

**Criar o teste:**

```bash
# Unit test (sem banco)
php artisan make:test UserTest --unit

# Ou criar na mão em tests/Unit/
```

**Estrutura do teste:**

```php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Calculator;

class CalculatorTest extends TestCase
{
    public function test_adds_two_numbers(): void
    {
        // Arrange (preparação)
        $calculator = new Calculator();

        // Act (ação)
        $result = $calculator->add(2, 3);

        // Assert (checagem)
        $this->assertEquals(5, $result);
    }

    public function test_subtracts_two_numbers(): void
    {
        $calculator = new Calculator();
        $result = $calculator->subtract(10, 4);

        $this->assertEquals(6, $result);
    }
}
```

**Assertions (checagens):**

```php
// Igualdade
$this->assertEquals(expected, actual);
$this->assertSame(expected, actual);  // Estrito (===)

// Boolean
$this->assertTrue($value);
$this->assertFalse($value);

// Null
$this->assertNull($value);
$this->assertNotNull($value);

// Arrays
$this->assertContains('maçã', ['maçã', 'banana']);
$this->assertCount(3, $array);
$this->assertEmpty($array);

// Strings
$this->assertStringContainsString('olá', 'olá mundo');
$this->assertStringStartsWith('olá', 'olá mundo');

// Exceptions
$this->expectException(InvalidArgumentException::class);
$this->expectExceptionMessage('Valor inválido');
someFunction();

// Instance
$this->assertInstanceOf(User::class, $user);
```

---

## Quando usar

**Testes unitários para:**
- Lógica de negócio (Services, Actions)
- Cálculos (Calculator, Formatter)
- Validação (Custom Rules)
- Funções utility

**NÃO use para:**
- Controllers (Feature tests)
- Acesso a banco (Feature tests)
- Requests HTTP (Feature tests)

---

## Exemplo prático

**Testando um Service:**

```php
// app/Services/PriceCalculator.php
class PriceCalculator
{
    public function calculate(Product $product, int $quantity, ?string $promoCode = null): float
    {
        $total = $product->price * $quantity;

        if ($promoCode) {
            $discount = $this->getDiscount($promoCode);
            $total -= $total * ($discount / 100);
        }

        return round($total, 2);
    }

    private function getDiscount(string $promoCode): int
    {
        $discounts = [
            'SAVE10' => 10,
            'SAVE20' => 20,
            'SAVE50' => 50,
        ];

        return $discounts[$promoCode] ?? 0;
    }
}

// tests/Unit/PriceCalculatorTest.php
class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PriceCalculator();
    }

    public function test_calculates_price_without_promo_code(): void
    {
        $product = new Product(['price' => 100]);
        $result = $this->calculator->calculate($product, 2);

        $this->assertEquals(200, $result);
    }

    public function test_applies_10_percent_discount(): void
    {
        $product = new Product(['price' => 100]);
        $result = $this->calculator->calculate($product, 2, 'SAVE10');

        $this->assertEquals(180, $result);  // 200 - 10% = 180
    }

    public function test_applies_20_percent_discount(): void
    {
        $product = new Product(['price' => 100]);
        $result = $this->calculator->calculate($product, 1, 'SAVE20');

        $this->assertEquals(80, $result);  // 100 - 20% = 80
    }

    public function test_ignores_invalid_promo_code(): void
    {
        $product = new Product(['price' => 100]);
        $result = $this->calculator->calculate($product, 1, 'INVALID');

        $this->assertEquals(100, $result);  // Sem desconto
    }

    public function test_rounds_to_two_decimals(): void
    {
        $product = new Product(['price' => 99.99]);
        $result = $this->calculator->calculate($product, 3, 'SAVE10');

        $this->assertEquals(269.97, $result);  // (99.99 * 3) - 10%
    }
}
```

**Data Providers (testes parametrizados):**

```php
class PriceCalculatorTest extends TestCase
{
    /**
     * @dataProvider priceDataProvider
     */
    public function test_calculates_price_with_various_inputs(
        float $price,
        int $quantity,
        ?string $promoCode,
        float $expected
    ): void {
        $product = new Product(['price' => $price]);
        $calculator = new PriceCalculator();
        $result = $calculator->calculate($product, $quantity, $promoCode);

        $this->assertEquals($expected, $result);
    }

    public static function priceDataProvider(): array
    {
        return [
            'sem cupom' => [100, 2, null, 200],
            'SAVE10' => [100, 2, 'SAVE10', 180],
            'SAVE20' => [100, 1, 'SAVE20', 80],
            'SAVE50' => [100, 1, 'SAVE50', 50],
            'cupom inválido' => [100, 1, 'INVALID', 100],
        ];
    }
}
```

**Testando com Mock:**

```php
// Service com dependência
class OrderService
{
    public function __construct(
        private PaymentGateway $paymentGateway,
        private NotificationService $notificationService
    ) {}

    public function create(User $user, array $items): Order
    {
        $total = $this->calculateTotal($items);

        // Cobrar o pagamento
        $this->paymentGateway->charge($user, $total);

        // Criar o pedido
        $order = Order::create([
            'user_id' => $user->id,
            'total' => $total,
        ]);

        // Enviar notificação
        $this->notificationService->send($user, "Pedido #{$order->id} criado");

        return $order;
    }

    private function calculateTotal(array $items): float
    {
        return array_sum(array_column($items, 'price'));
    }
}

// Unit test com mock
class OrderServiceTest extends TestCase
{
    public function test_creates_order_and_charges_payment(): void
    {
        // Arrange
        $paymentGateway = Mockery::mock(PaymentGateway::class);
        $notificationService = Mockery::mock(NotificationService::class);

        $user = new User(['id' => 1]);
        $items = [
            ['price' => 100],
            ['price' => 200],
        ];

        // Esperamos charge com total = 300
        $paymentGateway->shouldReceive('charge')
            ->once()
            ->with($user, 300);

        // Esperamos send
        $notificationService->shouldReceive('send')
            ->once()
            ->with($user, Mockery::type('string'));

        $service = new OrderService($paymentGateway, $notificationService);

        // Act & Assert
        $order = $service->create($user, $items);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(300, $order->total);
    }
}
```

**Testando Validation Rule:**

```php
// app/Rules/ValidPromoCode.php
class ValidPromoCode implements Rule
{
    public function passes($attribute, $value): bool
    {
        return PromoCode::where('code', $value)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function message(): string
    {
        return 'O cupom é inválido ou expirou';
    }
}

// tests/Unit/ValidPromoCodeTest.php
class ValidPromoCodeTest extends TestCase
{
    public function test_passes_for_valid_promo_code(): void
    {
        // Mock PromoCode query
        PromoCode::shouldReceive('where->where->exists')
            ->andReturn(true);

        $rule = new ValidPromoCode();
        $result = $rule->passes('promo_code', 'VALID');

        $this->assertTrue($result);
    }

    public function test_fails_for_expired_promo_code(): void
    {
        PromoCode::shouldReceive('where->where->exists')
            ->andReturn(false);

        $rule = new ValidPromoCode();
        $result = $rule->passes('promo_code', 'EXPIRED');

        $this->assertFalse($result);
    }

    public function test_returns_error_message(): void
    {
        $rule = new ValidPromoCode();
        $message = $rule->message();

        $this->assertEquals('O cupom é inválido ou expirou', $message);
    }
}
```

**Rodar os testes:**

```bash
# Todos os unit tests
php artisan test --testsuite=Unit

# Arquivo específico
php artisan test tests/Unit/PriceCalculatorTest.php

# Método específico
php artisan test --filter test_calculates_price

# Com coverage
php artisan test --coverage
```

---

## Na entrevista

> "Unit tests testam uma peça isolada. Estrutura AAA: Arrange (preparação), Act (ação), Assert (checagem). Assertions: assertEquals, assertTrue, assertInstanceOf, expectException. Mock de dependência com Mockery (shouldReceive, once, with). Data providers para teste parametrizado. setUp() prepara, tearDown() limpa. Unit test é rápido — sem banco, sem HTTP. Eu testo Service, Rule, Helper."

---

## Exercícios práticos

### Exercício 1: Teste do Calculator com Data Provider

**Enunciado:** Crie a classe `Calculator` com os métodos `add()`, `subtract()`, `multiply()`, `divide()`. Escreva um unit test com Data Provider cobrindo todas as operações.

<details>
<summary>Solução</summary>

```php
// app/Services/Calculator.php
namespace App\Services;

class Calculator
{
    public function add(float $a, float $b): float
    {
        return $a + $b;
    }

    public function subtract(float $a, float $b): float
    {
        return $a - $b;
    }

    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    public function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new \InvalidArgumentException('Division by zero');
        }

        return $a / $b;
    }
}

// tests/Unit/CalculatorTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Calculator;

class CalculatorTest extends TestCase
{
    private Calculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new Calculator();
    }

    /**
     * @dataProvider mathOperationsProvider
     */
    public function test_performs_math_operations(
        string $operation,
        float $a,
        float $b,
        float $expected
    ): void {
        $result = $this->calculator->$operation($a, $b);
        $this->assertEquals($expected, $result);
    }

    public static function mathOperationsProvider(): array
    {
        return [
            'add positive' => ['add', 5, 3, 8],
            'add negative' => ['add', -5, -3, -8],
            'subtract' => ['subtract', 10, 4, 6],
            'multiply' => ['multiply', 6, 7, 42],
            'divide' => ['divide', 20, 4, 5],
        ];
    }

    public function test_divide_by_zero_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');

        $this->calculator->divide(10, 0);
    }
}
```
</details>

### Exercício 2: Mock de serviço externo

**Enunciado:** Crie um `WeatherService` que usa `HttpClient` para buscar o clima. Escreva um unit test com mock do cliente HTTP.

<details>
<summary>Solução</summary>

```php
// app/Services/WeatherService.php
namespace App\Services;

class WeatherService
{
    public function __construct(private HttpClient $http)
    {
    }

    public function getTemperature(string $city): float
    {
        $response = $this->http->get("https://api.weather.com/v1/current", [
            'q' => $city,
        ]);

        if (!isset($response['main']['temp'])) {
            throw new \RuntimeException('Invalid API response');
        }

        return $response['main']['temp'];
    }

    public function getForecast(string $city, int $days): array
    {
        $response = $this->http->get("https://api.weather.com/v1/forecast", [
            'q' => $city,
            'days' => $days,
        ]);

        return $response['forecast'] ?? [];
    }
}

// tests/Unit/WeatherServiceTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WeatherService;
use App\Services\HttpClient;
use Mockery;

class WeatherServiceTest extends TestCase
{
    public function test_gets_temperature_for_city(): void
    {
        // Mock HTTP client
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('get')
            ->once()
            ->with('https://api.weather.com/v1/current', ['q' => 'London'])
            ->andReturn([
                'main' => ['temp' => 18.5],
            ]);

        $service = new WeatherService($http);
        $temperature = $service->getTemperature('London');

        $this->assertEquals(18.5, $temperature);
    }

    public function test_throws_exception_for_invalid_response(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('get')
            ->andReturn([]);  // Resposta inválida

        $service = new WeatherService($http);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid API response');

        $service->getTemperature('London');
    }

    public function test_gets_forecast(): void
    {
        $http = Mockery::mock(HttpClient::class);
        $http->shouldReceive('get')
            ->once()
            ->with('https://api.weather.com/v1/forecast', [
                'q' => 'Paris',
                'days' => 7,
            ])
            ->andReturn([
                'forecast' => [
                    ['day' => 1, 'temp' => 20],
                    ['day' => 2, 'temp' => 22],
                ],
            ]);

        $service = new WeatherService($http);
        $forecast = $service->getForecast('Paris', 7);

        $this->assertCount(2, $forecast);
        $this->assertEquals(20, $forecast[0]['temp']);
    }
}
```
</details>

### Exercício 3: Teste de Validation Rule

**Enunciado:** Crie a validation rule customizada `StrongPassword` (mínimo 8 caracteres, 1 letra, 1 número, 1 caractere especial). Escreva unit tests para todos os casos.

<details>
<summary>Solução</summary>

```php
// app/Rules/StrongPassword.php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class StrongPassword implements Rule
{
    public function passes($attribute, $value): bool
    {
        // Mínimo 8 caracteres
        if (strlen($value) < 8) {
            return false;
        }

        // Precisa ter pelo menos uma letra
        if (!preg_match('/[a-zA-Z]/', $value)) {
            return false;
        }

        // Precisa ter pelo menos um número
        if (!preg_match('/[0-9]/', $value)) {
            return false;
        }

        // Precisa ter pelo menos um caractere especial
        if (!preg_match('/[^a-zA-Z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    public function message(): string
    {
        return 'A senha deve ter no mínimo 8 caracteres, incluindo letras, números e caracteres especiais.';
    }
}

// tests/Unit/StrongPasswordTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Rules\StrongPassword;

class StrongPasswordTest extends TestCase
{
    private StrongPassword $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new StrongPassword();
    }

    public function test_passes_for_strong_password(): void
    {
        $result = $this->rule->passes('password', 'Abc123!@');

        $this->assertTrue($result);
    }

    public function test_fails_for_short_password(): void
    {
        $result = $this->rule->passes('password', 'Ab1!');

        $this->assertFalse($result);
    }

    public function test_fails_without_letters(): void
    {
        $result = $this->rule->passes('password', '12345678!@');

        $this->assertFalse($result);
    }

    public function test_fails_without_digits(): void
    {
        $result = $this->rule->passes('password', 'Abcdefg!@');

        $this->assertFalse($result);
    }

    public function test_fails_without_special_chars(): void
    {
        $result = $this->rule->passes('password', 'Abcdefg123');

        $this->assertFalse($result);
    }

    /**
     * @dataProvider strongPasswordsProvider
     */
    public function test_accepts_various_strong_passwords(string $password): void
    {
        $result = $this->rule->passes('password', $password);

        $this->assertTrue($result);
    }

    public static function strongPasswordsProvider(): array
    {
        return [
            ['Password123!'],
            ['MyP@ssw0rd'],
            ['Str0ng#Pass'],
            ['C0mpl3x$Pass'],
        ];
    }

    public function test_returns_correct_error_message(): void
    {
        $message = $this->rule->message();

        $this->assertStringContainsString('mínimo 8 caracteres', $message);
        $this->assertStringContainsString('letras', $message);
        $this->assertStringContainsString('números', $message);
        $this->assertStringContainsString('caracteres especiais', $message);
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
