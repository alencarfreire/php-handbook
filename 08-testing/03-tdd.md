# 7.3 TDD (Test-Driven Development)

## Resumo

> **TDD** — metodologia de desenvolvimento em que os testes vêm ANTES do código. Ciclo Red-Green-Refactor: teste falhando → código mínimo → refatoração.
>
> **Vantagens:** API pensada, cobertura alta (100%), menos bugs. Testes como documentação.
>
> **Importante:** Serve para lógica de negócio, API, algoritmo. NÃO serve para protótipo e UI. Cada ciclo é curto (5-10 minutos).

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
TDD — metodologia de desenvolvimento em que os testes vêm ANTES do código. Ciclo: Red → Green → Refactor.

**Ciclo TDD:**
1. **Red** — escrever o teste falhando
2. **Green** — escrever o código mínimo para passar
3. **Refactor** — melhorar o código

---

## Como funciona

**Ciclo Red-Green-Refactor:**

```php
// 1. RED: Escrever o teste falhando
class CalculatorTest extends TestCase
{
    public function test_adds_two_numbers(): void
    {
        $calculator = new Calculator();  // A classe ainda não existe
        $result = $calculator->add(2, 3);

        $this->assertEquals(5, $result);
    }
}

// Rodar: php artisan test
// Erro: Class Calculator not found

// 2. GREEN: Código mínimo para passar
class Calculator
{
    public function add(int $a, int $b): int
    {
        return $a + $b;  // Implementação mais simples
    }
}

// Rodar: php artisan test
// ✅ PASS

// 3. REFACTOR: Melhorar (se precisar)
// Aqui o código já está bom
```

**Ciclo completo:**

```php
// Objetivo: criar um PriceCalculator com desconto

// 1. RED: Teste sem desconto
public function test_calculates_price_without_discount(): void
{
    $calculator = new PriceCalculator();  // A classe não existe
    $result = $calculator->calculate(100, 2);

    $this->assertEquals(200, $result);
}

// 2. GREEN: Criar a classe
class PriceCalculator
{
    public function calculate(float $price, int $quantity): float
    {
        return $price * $quantity;
    }
}
// ✅ PASS

// 3. RED: Adicionar teste com desconto
public function test_applies_10_percent_discount(): void
{
    $calculator = new PriceCalculator();
    $result = $calculator->calculate(100, 2, 10);  // 10% de desconto

    $this->assertEquals(180, $result);  // 200 - 10%
}
// ❌ FAIL: Missing argument 3

// 4. GREEN: Adicionar o parâmetro discount
class PriceCalculator
{
    public function calculate(float $price, int $quantity, float $discount = 0): float
    {
        $total = $price * $quantity;
        return $total - ($total * $discount / 100);
    }
}
// ✅ PASS

// 5. REFACTOR: Melhorar a leitura
class PriceCalculator
{
    public function calculate(float $price, int $quantity, float $discountPercent = 0): float
    {
        $subtotal = $this->calculateSubtotal($price, $quantity);
        $discount = $this->calculateDiscount($subtotal, $discountPercent);

        return $subtotal - $discount;
    }

    private function calculateSubtotal(float $price, int $quantity): float
    {
        return $price * $quantity;
    }

    private function calculateDiscount(float $amount, float $percent): float
    {
        return $amount * $percent / 100;
    }
}
// ✅ PASS (os testes não mudaram, mas o código ficou melhor)
```

---

## Quando usar

**Prós do TDD:**
- ✅ Design da API (você pensa na interface pública)
- ✅ Cobertura alta (100% por padrão)
- ✅ Documentação (testes como exemplos)
- ✅ Menos bugs

**Contras do TDD:**
- ❌ Começo mais lento
- ❌ Pede experiência
- ❌ Nem sempre cabe (UI, protótipo)

**Quando usar:**
- Lógica de negócio crítica
- Public API/Library
- Algoritmos complexos

**Quando NÃO usar:**
- Protótipo
- UI/Frontend
- CRUD simples

---

## Exemplo prático

**TDD para Validation Rule:**

```php
// 1. RED: Teste de cupom válido
class ValidPromoCodeTest extends TestCase
{
    public function test_passes_for_valid_promo_code(): void
    {
        $rule = new ValidPromoCode();  // A classe não existe
        $result = $rule->passes('promo_code', 'SAVE10');

        $this->assertTrue($result);
    }
}
// ❌ FAIL

// 2. GREEN: Criar a classe
class ValidPromoCode implements Rule
{
    public function passes($attribute, $value): bool
    {
        return true;  // Implementação mais simples
    }

    public function message(): string
    {
        return 'Cupom inválido';
    }
}
// ✅ PASS

// 3. RED: Teste de cupom expirado
public function test_fails_for_expired_promo_code(): void
{
    // Criar um cupom expirado
    PromoCode::factory()->create([
        'code' => 'EXPIRED',
        'expires_at' => now()->subDay(),
    ]);

    $rule = new ValidPromoCode();
    $result = $rule->passes('promo_code', 'EXPIRED');

    $this->assertFalse($result);
}
// ❌ FAIL (sempre retorna true)

// 4. GREEN: Implementar a checagem
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
// ✅ PASS

// 5. RED: Teste de cupom inexistente
public function test_fails_for_nonexistent_promo_code(): void
{
    $rule = new ValidPromoCode();
    $result = $rule->passes('promo_code', 'NONEXISTENT');

    $this->assertFalse($result);
}
// ✅ PASS (já funciona)

// 6. REFACTOR: Melhorar (se precisar)
// O código já está bom
```

**TDD para Service com dependências:**

```php
// Objetivo: OrderService deve criar o pedido e debitar o saldo

// 1. RED: Teste de criação do pedido
class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_order(): void
    {
        $user = User::factory()->create(['balance' => 1000]);
        $product = Product::factory()->create(['price' => 100]);

        $service = new OrderService();  // A classe não existe
        $order = $service->create($user, [
            ['product_id' => $product->id, 'quantity' => 2],
        ]);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(200, $order->total);
    }
}
// ❌ FAIL

// 2. GREEN: Implementação mais simples
class OrderService
{
    public function create(User $user, array $items): Order
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        return Order::create([
            'user_id' => $user->id,
            'total' => $total,
        ]);
    }
}
// ✅ PASS

// 3. RED: Teste do débito do saldo
public function test_deducts_balance(): void
{
    $user = User::factory()->create(['balance' => 1000]);
    $product = Product::factory()->create(['price' => 100]);

    $service = new OrderService();
    $service->create($user, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]);

    $this->assertEquals(800, $user->fresh()->balance);  // 1000 - 200
}
// ❌ FAIL (o saldo não mudou)

// 4. GREEN: Adicionar o débito
class OrderService
{
    public function create(User $user, array $items): Order
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total' => $total,
        ]);

        $user->decrement('balance', $total);

        return $order;
    }
}
// ✅ PASS

// 5. RED: Teste de saldo insuficiente
public function test_throws_exception_for_insufficient_balance(): void
{
    $user = User::factory()->create(['balance' => 50]);
    $product = Product::factory()->create(['price' => 100]);

    $this->expectException(InsufficientFundsException::class);

    $service = new OrderService();
    $service->create($user, [
        ['product_id' => $product->id, 'quantity' => 2],
    ]);
}
// ❌ FAIL

// 6. GREEN: Adicionar a checagem
class OrderService
{
    public function create(User $user, array $items): Order
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        if ($user->balance < $total) {
            throw new InsufficientFundsException();
        }

        $order = Order::create([
            'user_id' => $user->id,
            'total' => $total,
        ]);

        $user->decrement('balance', $total);

        return $order;
    }
}
// ✅ PASS

// 7. REFACTOR: Extrair métodos
class OrderService
{
    public function create(User $user, array $items): Order
    {
        $total = $this->calculateTotal($items);

        $this->validateBalance($user, $total);

        DB::transaction(function () use ($user, $items, $total) {
            $order = $this->createOrder($user, $items, $total);
            $this->deductBalance($user, $total);

            return $order;
        });
    }

    private function calculateTotal(array $items): float
    {
        $total = 0;
        foreach ($items as $item) {
            $product = Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }
        return $total;
    }

    private function validateBalance(User $user, float $total): void
    {
        if ($user->balance < $total) {
            throw new InsufficientFundsException();
        }
    }

    private function createOrder(User $user, array $items, float $total): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'total' => $total,
        ]);
    }

    private function deductBalance(User $user, float $total): void
    {
        $user->decrement('balance', $total);
    }
}
// ✅ PASS (todos os testes passam, o código ficou melhor)
```

---

## Na entrevista

> "TDD — os testes vêm ANTES do código. Ciclo: Red (teste falhando) → Green (código mínimo) → Refactor (melhorar). Prós: design da API, cobertura alta, menos bugs. Contras: o começo é mais lento, pede experiência. Uso em lógica de negócio crítica, API, algoritmo complexo. Não uso em protótipo, UI. Os testes viram documentação e exemplo de uso."

---

## Exercícios práticos

### Exercício 1: Discount Calculator com TDD

**Enunciado:** Usando TDD, crie `DiscountCalculator` com o método `calculate(price, discountPercent)`. Vá por etapas: cálculo básico → validação de valores negativos → arredondamento em 2 casas.

<details>
<summary>Solução</summary>

```php
// PASSO 1: RED - Escrever o primeiro teste
// tests/Unit/DiscountCalculatorTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DiscountCalculator;

class DiscountCalculatorTest extends TestCase
{
    public function test_calculates_discount(): void
    {
        $calculator = new DiscountCalculator();  // A classe ainda não existe
        $result = $calculator->calculate(100, 10);

        $this->assertEquals(90, $result);
    }
}

// Rodar: php artisan test
// ❌ FAIL: Class DiscountCalculator not found

// PASSO 2: GREEN - Criar a classe mínima
// app/Services/DiscountCalculator.php
namespace App\Services;

class DiscountCalculator
{
    public function calculate(float $price, float $discountPercent): float
    {
        return $price - ($price * $discountPercent / 100);
    }
}

// ✅ PASS

// PASSO 3: RED - Adicionar teste para valores negativos
public function test_throws_exception_for_negative_price(): void
{
    $calculator = new DiscountCalculator();

    $this->expectException(\InvalidArgumentException::class);
    $calculator->calculate(-100, 10);
}

// ❌ FAIL: Expected exception not thrown

// PASSO 4: GREEN - Adicionar a validação
class DiscountCalculator
{
    public function calculate(float $price, float $discountPercent): float
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('O preço não pode ser negativo');
        }

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('O desconto deve estar entre 0 e 100');
        }

        return $price - ($price * $discountPercent / 100);
    }
}

// ✅ PASS

// PASSO 5: RED - Teste de arredondamento
public function test_rounds_to_two_decimals(): void
{
    $calculator = new DiscountCalculator();
    $result = $calculator->calculate(99.99, 15);

    $this->assertEquals(84.99, $result);
}

// ❌ FAIL: Expected 84.99, got 84.9915

// PASSO 6: GREEN - Adicionar o arredondamento
class DiscountCalculator
{
    public function calculate(float $price, float $discountPercent): float
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('O preço não pode ser negativo');
        }

        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('O desconto deve estar entre 0 e 100');
        }

        $finalPrice = $price - ($price * $discountPercent / 100);
        return round($finalPrice, 2);
    }
}

// ✅ PASS

// PASSO 7: REFACTOR - Melhorar a leitura
class DiscountCalculator
{
    public function calculate(float $price, float $discountPercent): float
    {
        $this->validatePrice($price);
        $this->validateDiscount($discountPercent);

        $discountAmount = $this->calculateDiscountAmount($price, $discountPercent);
        $finalPrice = $price - $discountAmount;

        return round($finalPrice, 2);
    }

    private function validatePrice(float $price): void
    {
        if ($price < 0) {
            throw new \InvalidArgumentException('O preço não pode ser negativo');
        }
    }

    private function validateDiscount(float $discountPercent): void
    {
        if ($discountPercent < 0 || $discountPercent > 100) {
            throw new \InvalidArgumentException('O desconto deve estar entre 0 e 100');
        }
    }

    private function calculateDiscountAmount(float $price, float $discountPercent): float
    {
        return $price * $discountPercent / 100;
    }
}

// ✅ PASS - Todos os testes passam, o código ficou melhor
```
</details>

### Exercício 2: Shopping Cart com TDD

**Enunciado:** Crie `ShoppingCart` com TDD. Funções: `addItem()`, `removeItem()`, `getTotal()`, `isEmpty()`, `clear()`. Cada passo é um teste novo.

<details>
<summary>Solução</summary>

```php
// tests/Unit/ShoppingCartTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ShoppingCart;

class ShoppingCartTest extends TestCase
{
    // PASSO 1: RED
    public function test_new_cart_is_empty(): void
    {
        $cart = new ShoppingCart();

        $this->assertTrue($cart->isEmpty());
    }

    // PASSO 2: RED
    public function test_can_add_item(): void
    {
        $cart = new ShoppingCart();
        $cart->addItem('Maçã', 1.50, 3);

        $this->assertFalse($cart->isEmpty());
    }

    // PASSO 3: RED
    public function test_calculates_total(): void
    {
        $cart = new ShoppingCart();
        $cart->addItem('Maçã', 1.50, 3);  // 4.50
        $cart->addItem('Banana', 2.00, 2);  // 4.00

        $this->assertEquals(8.50, $cart->getTotal());
    }

    // PASSO 4: RED
    public function test_can_remove_item(): void
    {
        $cart = new ShoppingCart();
        $cart->addItem('Maçã', 1.50, 3);
        $cart->removeItem('Maçã');

        $this->assertTrue($cart->isEmpty());
    }

    // PASSO 5: RED
    public function test_can_clear_cart(): void
    {
        $cart = new ShoppingCart();
        $cart->addItem('Maçã', 1.50, 3);
        $cart->addItem('Banana', 2.00, 2);
        $cart->clear();

        $this->assertTrue($cart->isEmpty());
        $this->assertEquals(0, $cart->getTotal());
    }

    // PASSO 6: RED
    public function test_throws_exception_for_negative_quantity(): void
    {
        $cart = new ShoppingCart();

        $this->expectException(\InvalidArgumentException::class);
        $cart->addItem('Maçã', 1.50, -1);
    }
}

// Implementação depois de todos os testes
// app/Services/ShoppingCart.php
namespace App\Services;

class ShoppingCart
{
    private array $items = [];

    public function addItem(string $name, float $price, int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('A quantidade deve ser positiva');
        }

        if ($price < 0) {
            throw new \InvalidArgumentException('O preço não pode ser negativo');
        }

        $this->items[$name] = [
            'price' => $price,
            'quantity' => $quantity,
        ];
    }

    public function removeItem(string $name): void
    {
        unset($this->items[$name]);
    }

    public function getTotal(): float
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return round($total, 2);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function clear(): void
    {
        $this->items = [];
    }

    public function getItems(): array
    {
        return $this->items;
    }
}
```
</details>

### Exercício 3: Password Validator com TDD

**Enunciado:** Crie `PasswordValidator` com TDD. Requisitos: mínimo 8 caracteres, pelo menos uma maiúscula, uma minúscula, um dígito. Escreva os testes aos poucos.

<details>
<summary>Solução</summary>

```php
// tests/Unit/PasswordValidatorTest.php
namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PasswordValidator;

class PasswordValidatorTest extends TestCase
{
    private PasswordValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PasswordValidator();
    }

    // PASSO 1: RED - Tamanho mínimo
    public function test_rejects_password_shorter_than_8_chars(): void
    {
        $result = $this->validator->validate('Short1');

        $this->assertFalse($result);
    }

    public function test_accepts_password_with_8_chars(): void
    {
        $result = $this->validator->validate('Password1');

        $this->assertTrue($result);
    }

    // PASSO 2: RED - Letras maiúsculas
    public function test_rejects_password_without_uppercase(): void
    {
        $result = $this->validator->validate('password1');

        $this->assertFalse($result);
    }

    // PASSO 3: RED - Letras minúsculas
    public function test_rejects_password_without_lowercase(): void
    {
        $result = $this->validator->validate('PASSWORD1');

        $this->assertFalse($result);
    }

    // PASSO 4: RED - Dígitos
    public function test_rejects_password_without_digit(): void
    {
        $result = $this->validator->validate('Password');

        $this->assertFalse($result);
    }

    // PASSO 5: Testes combinados
    public function test_accepts_valid_passwords(): void
    {
        $validPasswords = [
            'Password1',
            'MyPass123',
            'SecureP@ss1',
            'Abcd1234',
        ];

        foreach ($validPasswords as $password) {
            $this->assertTrue(
                $this->validator->validate($password),
                "Falhou para a senha: {$password}"
            );
        }
    }

    public function test_returns_validation_errors(): void
    {
        $errors = $this->validator->getErrors('short');

        $this->assertContains('A senha deve ter pelo menos 8 caracteres', $errors);
        $this->assertContains('A senha deve ter pelo menos uma letra maiúscula', $errors);
        $this->assertContains('A senha deve ter pelo menos um dígito', $errors);
    }
}

// Implementação final
// app/Services/PasswordValidator.php
namespace App\Services;

class PasswordValidator
{
    private array $errors = [];

    public function validate(string $password): bool
    {
        $this->errors = [];

        if (strlen($password) < 8) {
            $this->errors[] = 'A senha deve ter pelo menos 8 caracteres';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $this->errors[] = 'A senha deve ter pelo menos uma letra maiúscula';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $this->errors[] = 'A senha deve ter pelo menos uma letra minúscula';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $this->errors[] = 'A senha deve ter pelo menos um dígito';
        }

        return empty($this->errors);
    }

    public function getErrors(string $password): array
    {
        $this->validate($password);
        return $this->errors;
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
