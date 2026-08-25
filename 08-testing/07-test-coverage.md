# 7.7 Test Coverage

## Resumo

> **Test Coverage** — percentual do código coberto por testes. Métricas: line, function, class, branch coverage.
>
> **Como rodar:** `php artisan test --coverage`. Precisa de Xdebug ou PCOV. Relatório HTML: `--coverage-html coverage/`.
>
> **Importante:** Meta: lógica crítica 90%+, projeto inteiro 70-80%. 100% de coverage NÃO garante zero bugs. Mutation testing (Infection) checa a qualidade dos testes.

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
Test Coverage é o percentual do código coberto por testes. Mostra quais linhas, funções e classes foram testadas.

**Métricas:**
- Line Coverage — cobertura de linhas
- Function Coverage — cobertura de funções
- Class Coverage — cobertura de classes
- Branch Coverage — cobertura de branches (if/else)

---

## Como funciona

**Ligar o Xdebug:**

```bash
# macOS (Homebrew)
pecl install xdebug

# Ubuntu
apt-get install php-xdebug

# Conferir
php -v | grep Xdebug
```

**Rodar o coverage:**

```bash
# PHPUnit
php artisan test --coverage

# Relatório HTML
php artisan test --coverage-html coverage/

# Limite mínimo
php artisan test --min=80  # Mínimo 80%

# Pest
pest --coverage
pest --coverage --min=80
```

**Exemplo de relatório:**

```
Tests:  10 passed
Coverage:
  App\Services\Calculator ..... 100%
  App\Services\Payment ........ 75%
  App\Http\Controllers\User ... 60%
  Total ........................ 78%
```

---

## Quando usar

**Prós de coverage alto:**
- ✅ Menos bugs
- ✅ Confiança na hora de refatorar
- ✅ Documentação

**Contras de perseguir 100%:**
- ❌ Desenvolvimento lento
- ❌ Teste só para bater número
- ❌ Falsa segurança

**Metas:**
- Módulos críticos: 90%+
- Lógica de negócio: 80%+
- Controllers: 70%+
- Projeto inteiro: 70-80%

---

## Exemplo prático

**Analisando o coverage:**

```php
// Classe com 50% de coverage
class PaymentService
{
    public function charge(User $user, float $amount): bool
    {
        if ($user->balance < $amount) {
            throw new InsufficientFundsException();  // ❌ Não coberto
        }

        $user->decrement('balance', $amount);  // ✅ Coberto
        return true;  // ✅ Coberto
    }
}

// Único teste (caso de sucesso)
public function test_charges_user(): void
{
    $user = User::factory()->create(['balance' => 1000]);
    $service = new PaymentService();

    $result = $service->charge($user, 100);

    $this->assertTrue($result);
    $this->assertEquals(900, $user->fresh()->balance);
}

// Adicionar teste para saldo insuficiente → 100% coverage
public function test_throws_exception_for_insufficient_balance(): void
{
    $user = User::factory()->create(['balance' => 50]);
    $service = new PaymentService();

    $this->expectException(InsufficientFundsException::class);
    $service->charge($user, 100);
}
```

**Configuração do phpunit.xml:**

```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">app</directory>
    </include>
    <exclude>
        <directory>app/Console</directory>
        <file>app/Http/Kernel.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage"/>
        <text outputFile="php://stdout" showUncoveredFiles="false"/>
    </report>
</coverage>
```

**CI/CD com coverage:**

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Install dependencies
        run: composer install

      - name: Run tests with coverage
        run: php artisan test --coverage --min=80

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v2
        with:
          files: ./coverage.xml
```

**Ignorando código:**

```php
class Logger
{
    public function log(string $message): void
    {
        // @codeCoverageIgnoreStart
        if (app()->environment('testing')) {
            return;  // Não contar no coverage
        }
        // @codeCoverageIgnoreEnd

        file_put_contents('log.txt', $message);
    }
}
```

**Mutation Testing (nível avançado):**

```bash
# Instalar Infection
composer require --dev infection/infection

# Rodar
vendor/bin/infection

# Infection altera o código (mutações) e checa se os testes pegam o erro
# Se os testes passam com a mutação → o teste é fraco
```

**Exemplo de mutação:**

```php
// Código original
if ($user->balance >= $amount) {
    // ...
}

// Mutação 1: >= → >
if ($user->balance > $amount) {  // Alterado
    // ...
}

// Mutação 2: >= → <=
if ($user->balance <= $amount) {  // Alterado
    // ...
}

// Se os testes não pegam a mutação → o teste está incompleto
```

**Coverage Badge (para o README):**

```markdown
# Meu Projeto

![Tests](https://github.com/user/repo/workflows/tests/badge.svg)
![Coverage](https://codecov.io/gh/user/repo/branch/main/graph/badge.svg)
```

**O que NÃO cobrir com testes:**

```php
// ❌ Não testar o framework
Route::get('/users', [UserController::class, 'index']);

// ❌ Não testar config
config(['app.name' => 'MyApp']);

// ❌ Não testar getters/setters
class User {
    private string $name;
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
}

// ✅ Testar lógica de negócio
class OrderService {
    public function calculateTotal(array $items): float {
        // Lógica de cálculo
    }
}
```

**Equilíbrio entre coverage e pragmatismo:**

```php
// 100% de coverage NÃO significa zero bugs

// Teste ruim (100% coverage, mas inútil)
public function test_user_has_name(): void
{
    $user = new User();
    $user->name = 'João';

    $this->assertEquals('João', $user->name);  // Óbvio
}

// Teste bom (checa o comportamento de verdade)
public function test_user_cannot_be_created_with_invalid_email(): void
{
    $this->expectException(ValidationException::class);

    User::create([
        'name' => 'João',
        'email' => 'invalid-email',  // Checa a validação
    ]);
}
```

---

## Na entrevista

> "Test Coverage é o percentual de código coberto por testes. Liga com Xdebug. Roda com php artisan test --coverage. Métricas: line, function, class, branch coverage. Meta: módulos críticos 90%+, lógica de negócio 80%+, projeto 70-80%. 100% de coverage não garante zero bugs. Mutation testing com Infection checa se o teste é bom de verdade. Não testo framework, config, getter/setter. No CI/CD eu coloco um mínimo (--min=80)."

---

## Exercícios práticos

### Exercício 1: Análise e melhoria do coverage

Dada uma classe com 50% de coverage. Analise o relatório e escreva os testes que faltam para 100% de cobertura.

<details>
<summary>Solução</summary>

```php
// app/Services/OrderCalculator.php
namespace App\Services;

class OrderCalculator
{
    public function calculateTotal(array $items, ?string $couponCode = null): float
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Aplicar cupom (NÃO COBERTO)
        if ($couponCode) {
            $discount = $this->getDiscount($couponCode);
            $subtotal -= $subtotal * ($discount / 100);
        }

        // Frete (NÃO COBERTO)
        $shipping = $this->calculateShipping($subtotal);

        return round($subtotal + $shipping, 2);
    }

    private function getDiscount(string $couponCode): int
    {
        // NÃO COBERTO
        $coupons = [
            'SAVE10' => 10,
            'SAVE20' => 20,
            'SAVE50' => 50,
        ];

        return $coupons[$couponCode] ?? 0;
    }

    private function calculateShipping(float $subtotal): float
    {
        // NÃO COBERTO
        if ($subtotal >= 100) {
            return 0;  // Frete grátis
        }

        return 10;  // Custo fixo
    }
}

// tests/Unit/OrderCalculatorTest.php - ESTADO INICIAL (50% coverage)
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\OrderCalculator;

class OrderCalculatorTest extends TestCase
{
    private OrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OrderCalculator();
    }

    // ÚNICO teste (cobre só o cenário básico)
    public function test_calculates_total_without_coupon(): void
    {
        $items = [
            ['price' => 50, 'quantity' => 2],
            ['price' => 30, 'quantity' => 1],
        ];

        $total = $this->calculator->calculateTotal($items);

        // subtotal: 130, frete: 10 (130 < 100)
        $this->assertEquals(140, $total);
    }
}

// Rodar o coverage:
// php artisan test --coverage
// Output:
// OrderCalculator.php ........ 50%
//   - Line 14-16: NOT COVERED (cupom)
//   - Line 23-31: NOT COVERED (getDiscount)
//   - Line 33-40: NOT COVERED (calculateShipping para >= 100)

// VERSÃO MELHORADA (100% coverage)
class OrderCalculatorTest extends TestCase
{
    private OrderCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new OrderCalculator();
    }

    public function test_calculates_total_without_coupon(): void
    {
        $items = [
            ['price' => 50, 'quantity' => 2],
            ['price' => 30, 'quantity' => 1],
        ];

        $total = $this->calculator->calculateTotal($items);

        $this->assertEquals(140, $total);  // 130 + 10 de frete
    }

    // NOVO: cobrir o cupom
    public function test_applies_valid_coupon(): void
    {
        $items = [
            ['price' => 50, 'quantity' => 2],
        ];

        $total = $this->calculator->calculateTotal($items, 'SAVE20');

        // subtotal: 100, desconto: 20%, resultado: 80, frete: 0
        $this->assertEquals(80, $total);
    }

    // NOVO: cobrir cupom inválido
    public function test_ignores_invalid_coupon(): void
    {
        $items = [
            ['price' => 50, 'quantity' => 2],
        ];

        $total = $this->calculator->calculateTotal($items, 'INVALID');

        // subtotal: 100, sem desconto, frete: 0
        $this->assertEquals(100, $total);
    }

    // NOVO: cobrir frete grátis
    public function test_free_shipping_over_100(): void
    {
        $items = [
            ['price' => 60, 'quantity' => 2],
        ];

        $total = $this->calculator->calculateTotal($items);

        // subtotal: 120, frete: 0 (>= 100)
        $this->assertEquals(120, $total);
    }

    // NOVO: cobrir frete pago
    public function test_charges_shipping_under_100(): void
    {
        $items = [
            ['price' => 40, 'quantity' => 2],
        ];

        $total = $this->calculator->calculateTotal($items);

        // subtotal: 80, frete: 10 (< 100)
        $this->assertEquals(90, $total);
    }

    // NOVO: cenário complexo
    public function test_complex_calculation(): void
    {
        $items = [
            ['price' => 30, 'quantity' => 3],
            ['price' => 20, 'quantity' => 2],
        ];

        $total = $this->calculator->calculateTotal($items, 'SAVE10');

        // subtotal: 130, desconto: 13, resultado: 117, frete: 0
        $this->assertEquals(117, $total);
    }
}

// Rodar de novo:
// php artisan test --coverage
// Output:
// OrderCalculator.php ........ 100% ✅
```
</details>

### Exercício 2: Coverage no CI/CD com limite mínimo

Configure o GitHub Actions para checar coverage automaticamente com mínimo de 80%. Se cair abaixo, o CI falha.

<details>
<summary>Solução</summary>

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
          coverage: xdebug

      - name: Install Composer dependencies
        run: composer install --prefer-dist --no-interaction --no-progress

      - name: Copy .env
        run: php -r "file_exists('.env') || copy('.env.example', '.env');"

      - name: Generate application key
        run: php artisan key:generate

      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Run tests with coverage
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password

      - name: Generate coverage report
        if: always()
        run: php artisan test --coverage-html coverage/

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: true

      - name: Upload coverage artifact
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: coverage-report
          path: coverage/

# phpunit.xml - configuração do coverage
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">app/Services</directory>
            <directory suffix=".php">app/Actions</directory>
            <directory suffix=".php">app/Http/Controllers</directory>
        </include>
        <exclude>
            <directory>app/Console</directory>
            <file>app/Http/Kernel.php</file>
            <directory>app/Exceptions</directory>
        </exclude>
        <report>
            <html outputDirectory="coverage"/>
            <xml outputFile="coverage.xml"/>
            <text outputFile="php://stdout" showUncoveredFiles="true"/>
        </report>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_DATABASE" value="testing"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
</phpunit>

# composer.json - adicionar script
{
    "scripts": {
        "test": "php artisan test",
        "test:coverage": "php artisan test --coverage --min=80",
        "test:coverage-html": "php artisan test --coverage-html coverage/",
        "test:unit": "php artisan test --testsuite=Unit",
        "test:feature": "php artisan test --testsuite=Feature"
    }
}

# Agora dá para rodar:
# composer test:coverage
```
</details>

### Exercício 3: Mutation Testing com Infection

Instale e configure o Infection para checar a qualidade dos testes. Encontre os testes fracos que não pegam mutações.

<details>
<summary>Solução</summary>

```bash
# Instalar Infection
composer require --dev infection/infection

# Inicializar
vendor/bin/infection --configure
```

```json
// infection.json.dist
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": [
            "app/Services",
            "app/Actions"
        ]
    },
    "logs": {
        "text": "infection.log",
        "html": "infection-report.html"
    },
    "mutators": {
        "@default": true
    },
    "minMsi": 70,
    "minCoveredMsi": 80
}
```

```php
// Exemplo: TESTE RUIM (não pega mutações)
// app/Services/PriceCalculator.php
class PriceCalculator
{
    public function calculateDiscount(float $price, int $percent): float
    {
        return $price - ($price * $percent / 100);  // Código original
    }
}

// tests/Unit/PriceCalculatorTest.php - TESTE RUIM
public function test_calculates_discount(): void
{
    $calculator = new PriceCalculator();
    $result = $calculator->calculateDiscount(100, 10);

    // Checagem fraca — deixa passar várias mutações
    $this->assertGreaterThan(0, $result);
}

// Infection cria mutações:
// Mutação 1: - → +
return $price + ($price * $percent / 100);  // O teste PASSA ❌

// Mutação 2: * → /
return $price - ($price / $percent / 100);  // O teste PASSA ❌

// Mutação 3: / 100 → / 101
return $price - ($price * $percent / 101);  // O teste PASSA ❌

// Relatório do Infection:
// Mutations: 10 total, 7 escaped, 3 killed
// MSI (Mutation Score Indicator): 30%  ❌ RUIM

// TESTE BOM (pega todas as mutações)
public function test_calculates_discount(): void
{
    $calculator = new PriceCalculator();
    $result = $calculator->calculateDiscount(100, 10);

    // Checagem exata
    $this->assertEquals(90, $result);
}

public function test_calculates_various_discounts(): void
{
    $calculator = new PriceCalculator();

    $this->assertEquals(80, $calculator->calculateDiscount(100, 20));
    $this->assertEquals(50, $calculator->calculateDiscount(100, 50));
    $this->assertEquals(99, $calculator->calculateDiscount(100, 1));
    $this->assertEquals(100, $calculator->calculateDiscount(100, 0));
}

// Agora o Infection:
// Mutations: 10 total, 1 escaped, 9 killed
// MSI: 90%  ✅ ÓTIMO

// Rodar o Infection
vendor/bin/infection --threads=4 --min-msi=70

// Adicionar no CI/CD
// .github/workflows/mutation-tests.yml
name: Mutation Tests

on:
  pull_request:
    branches: [main]

jobs:
  infection:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          coverage: xdebug

      - name: Install dependencies
        run: composer install

      - name: Run Infection
        run: vendor/bin/infection --min-msi=70 --min-covered-msi=80 --threads=4

      - name: Upload Infection report
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: infection-report
          path: infection-report.html
```

```bash
# Comandos úteis do Infection
vendor/bin/infection                     # Rodada básica
vendor/bin/infection --min-msi=80        # Com limite mínimo
vendor/bin/infection --filter=OrderService  # Classe específica
vendor/bin/infection --show-mutations    # Mostrar todas as mutações
vendor/bin/infection --only-covered      # Só código coberto
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
