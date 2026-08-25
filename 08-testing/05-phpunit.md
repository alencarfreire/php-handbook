# 7.5 PHPUnit

## Resumo

> **PHPUnit** — framework de testes padrão do PHP. O Laravel usa PHPUnit com um wrapper TestCase.
>
> **Rodar:** `php artisan test`, `--filter`, `--testsuite=Unit`, `--parallel`. Assertions: `assertEquals()`, `assertTrue()`, `expectException()`.
>
> **Importante:** Data providers para parametrizar. `setUp()`/`tearDown()` para preparar. Mock com `createMock()`, `expects()`, `willReturn()`. Annotations: `@test`, `@dataProvider`, `@group`.

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
PHPUnit é o framework de testes padrão do PHP. O Laravel usa PHPUnit por padrão.

**O essencial:**
- `php artisan test` — roda os testes
- Assertions — as checagens
- Data Providers — testes parametrizados
- setUp/tearDown — preparar/limpar

---

## Como funciona

**Estrutura do teste:**

```php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    // Roda antes de cada teste
    protected function setUp(): void
    {
        parent::setUp();
        // Preparação
    }

    // Roda depois de cada teste
    protected function tearDown(): void
    {
        // Limpeza
        parent::tearDown();
    }

    // Teste (começa com test_ ou tem @test)
    public function test_example(): void
    {
        $this->assertTrue(true);
    }

    /**
     * @test
     */
    public function it_works(): void
    {
        $this->assertTrue(true);
    }
}
```

**Como rodar os testes:**

```bash
# Todos os testes
php artisan test

# Um arquivo
php artisan test tests/Unit/ExampleTest.php

# Um método
php artisan test --filter test_example

# Testsuite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Parallel (mais rápido)
php artisan test --parallel

# Coverage
php artisan test --coverage
php artisan test --coverage-html coverage/

# Com verbose
php artisan test --verbose
```

**Assertions principais:**

```php
// Igualdade
$this->assertEquals(expected, actual);
$this->assertSame(expected, actual);  // ===
$this->assertNotEquals(expected, actual);

// Boolean
$this->assertTrue($value);
$this->assertFalse($value);

// Null
$this->assertNull($value);
$this->assertNotNull($value);

// Empty
$this->assertEmpty($array);
$this->assertNotEmpty($array);

// Arrays/Strings
$this->assertContains('item', $array);
$this->assertCount(3, $array);
$this->assertArrayHasKey('key', $array);
$this->assertStringContainsString('olá', 'olá mundo');

// Numeric
$this->assertGreaterThan(5, 10);
$this->assertLessThan(10, 5);
$this->assertEqualsWithDelta(1.0, 1.1, 0.2);  // ±0.2

// Instance/Type
$this->assertInstanceOf(User::class, $user);
$this->assertIsArray($value);
$this->assertIsString($value);
$this->assertIsInt($value);

// Exceptions
$this->expectException(InvalidArgumentException::class);
$this->expectExceptionMessage('Invalid');
someFunction();

// Arquivo
$this->assertFileExists('/path/to/file');
$this->assertFileIsReadable('/path/to/file');
```

---

## Quando usar

**PHPUnit vs Pest:**
- PHPUnit — o padrão, classes, mais boilerplate
- Pest — moderno, funções, menos código

**Use PHPUnit quando:**
- Abordagem padrão
- Time grande (todo mundo conhece PHPUnit)
- Projeto legacy

---

## Exemplo prático

**Data Providers:**

```php
class CalculatorTest extends TestCase
{
    /**
     * @dataProvider additionProvider
     */
    public function test_adds_numbers(int $a, int $b, int $expected): void
    {
        $calculator = new Calculator();
        $result = $calculator->add($a, $b);

        $this->assertEquals($expected, $result);
    }

    public static function additionProvider(): array
    {
        return [
            'números positivos' => [2, 3, 5],
            'números negativos' => [-2, -3, -5],
            'mistos' => [10, -5, 5],
            'zeros' => [0, 0, 0],
        ];
    }
}
```

**setUp/tearDown:**

```php
class DatabaseTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Abre a conexão
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE users (id INTEGER, name TEXT)');
    }

    protected function tearDown(): void
    {
        // Fecha a conexão
        $this->pdo = null;

        parent::tearDown();
    }

    public function test_inserts_user(): void
    {
        $this->pdo->exec("INSERT INTO users VALUES (1, 'João')");
        $result = $this->pdo->query("SELECT * FROM users")->fetch();

        $this->assertEquals('João', $result['name']);
    }
}
```

**Test Doubles (Mock, Stub, Spy):**

```php
// Mock
$mock = $this->createMock(PaymentGateway::class);
$mock->expects($this->once())  // Espera 1 chamada
    ->method('charge')
    ->with($this->equalTo(100))
    ->willReturn(true);

// Stub (sem checar as chamadas)
$stub = $this->createStub(PaymentGateway::class);
$stub->method('charge')
    ->willReturn(true);

// Partial Mock
$mock = $this->getMockBuilder(PaymentGateway::class)
    ->onlyMethods(['charge'])  // Mock só do charge()
    ->getMock();
```

**Annotations:**

```php
class ExampleTest extends TestCase
{
    /**
     * @test
     * @group slow
     * @requires PHP >= 8.1
     * @covers \App\Services\Calculator::add
     */
    public function it_adds_numbers(): void
    {
        // ...
    }

    /**
     * @test
     * @depends test_user_can_be_created
     */
    public function test_user_can_be_updated(User $user): User
    {
        // Pega o $user do teste anterior
        $user->update(['name' => 'Atualizado']);
        return $user;
    }

    /**
     * @test
     * @dataProvider invalidEmailProvider
     */
    public function test_validates_email(string $email): void
    {
        $this->assertFalse(filter_var($email, FILTER_VALIDATE_EMAIL));
    }

    public static function invalidEmailProvider(): array
    {
        return [
            ['invalid'],
            ['@example.com'],
            ['user@'],
        ];
    }
}
```

**Configuração do phpunit.xml:**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Console</directory>
        </exclude>
    </coverage>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
    </php>
</phpunit>
```

---

## Na entrevista

> "PHPUnit é o framework de testes padrão do PHP. Estrutura: setUp() para preparar, métodos test, tearDown() para limpar. Assertions: assertEquals, assertTrue, assertInstanceOf, expectException. Data providers para testes parametrizados. Mock com createMock(), expects(), willReturn(). Annotations: @test, @dataProvider, @group. Rodar: php artisan test, --filter, --testsuite, --parallel. O Laravel usa PHPUnit com um wrapper TestCase."

---

## Exercícios práticos

### Exercício 1: Data Provider com testes nomeados

Crie um teste para `StringHelper::slugify()` com Data Provider. Cubra strings diferentes (espaços, caracteres especiais, unicode).

<details>
<summary>Solução</summary>

```php
// app/Helpers/StringHelper.php
namespace App\Helpers;

class StringHelper
{
    public static function slugify(string $text): string
    {
        // Troca tudo que não for letra, número ou hífen
        $text = preg_replace('/[^A-Za-z0-9-]+/', '-', $text);

        // Tira hífens repetidos
        $text = preg_replace('/-+/', '-', $text);

        // Tira hífens no começo e no fim
        $text = trim($text, '-');

        return strtolower($text);
    }

    public static function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . $suffix;
    }
}

// tests/Unit/StringHelperTest.php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Helpers\StringHelper;

class StringHelperTest extends TestCase
{
    /**
     * @test
     * @dataProvider slugifyProvider
     */
    public function it_converts_string_to_slug(string $input, string $expected): void
    {
        $result = StringHelper::slugify($input);

        $this->assertEquals($expected, $result);
    }

    public static function slugifyProvider(): array
    {
        return [
            'texto simples' => ['Hello World', 'hello-world'],
            'com espaços' => ['  Multiple   Spaces  ', 'multiple-spaces'],
            'caracteres especiais' => ['Hello @ World!', 'hello-world'],
            'hífens' => ['Already-Has-Dashes', 'already-has-dashes'],
            'maiúsculas e minúsculas' => ['MiXeD CaSe', 'mixed-case'],
            'só caracteres especiais' => ['@#$%^&*()', ''],
            'números' => ['Test 123', 'test-123'],
            'hífens repetidos' => ['Too---Many---Dashes', 'too-many-dashes'],
        ];
    }

    /**
     * @test
     * @dataProvider truncateProvider
     */
    public function it_truncates_string(
        string $text,
        int $length,
        string $suffix,
        string $expected
    ): void {
        $result = StringHelper::truncate($text, $length, $suffix);

        $this->assertEquals($expected, $result);
    }

    public static function truncateProvider(): array
    {
        return [
            'texto curto' => ['Hello', 10, '...', 'Hello'],
            'texto longo' => ['Hello World', 5, '...', 'Hello...'],
            'tamanho exato' => ['Hello', 5, '...', 'Hello'],
            'sufixo customizado' => ['Long text here', 8, '…', 'Long tex…'],
            'sem sufixo' => ['Very long text', 8, '', 'Very lon'],
        ];
    }
}
```
</details>

### Exercício 2: setUp e tearDown para testes de Database

Crie um teste com um SQLite temporário. Use setUp para criar o schema e tearDown para limpar.

<details>
<summary>Solução</summary>

```php
// tests/Unit/DatabaseRepositoryTest.php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PDO;

class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(string $name, string $email): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email) VALUES (?, ?)'
        );
        $stmt->execute([$name, $email]);

        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM users ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        return $stmt->execute([$id]);
    }
}

class DatabaseRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria o SQLite in-memory
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cria a tabela
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repository = new UserRepository($this->pdo);
    }

    protected function tearDown(): void
    {
        // Fecha a conexão
        $this->pdo = null;
        $this->repository = null;

        parent::tearDown();
    }

    public function test_creates_user(): void
    {
        $id = $this->repository->create('João Silva', 'joao@email.com');

        $this->assertGreaterThan(0, $id);

        $user = $this->repository->find($id);
        $this->assertEquals('João Silva', $user['name']);
        $this->assertEquals('joao@email.com', $user['email']);
    }

    public function test_finds_user_by_id(): void
    {
        $id = $this->repository->create('Maria Silva', 'maria@email.com');

        $user = $this->repository->find($id);

        $this->assertIsArray($user);
        $this->assertEquals($id, $user['id']);
        $this->assertEquals('Maria Silva', $user['name']);
    }

    public function test_returns_null_for_nonexistent_user(): void
    {
        $user = $this->repository->find(999);

        $this->assertNull($user);
    }

    public function test_gets_all_users(): void
    {
        $this->repository->create('Usuário 1', 'usuario1@email.com');
        $this->repository->create('Usuário 2', 'usuario2@email.com');
        $this->repository->create('Usuário 3', 'usuario3@email.com');

        $users = $this->repository->all();

        $this->assertCount(3, $users);
        $this->assertEquals('Usuário 1', $users[0]['name']);
        $this->assertEquals('Usuário 3', $users[2]['name']);
    }

    public function test_deletes_user(): void
    {
        $id = $this->repository->create('Para excluir', 'excluir@email.com');

        $result = $this->repository->delete($id);

        $this->assertTrue($result);
        $this->assertNull($this->repository->find($id));
    }
}
```
</details>

### Exercício 3: Annotations e Groups

Crie um conjunto de testes com grupos diferentes (`@group slow`, `@group api`, `@group unit`). Use `@depends` nos testes que dependem de outro.

<details>
<summary>Solução</summary>

```php
// tests/Feature/UserManagementTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     * @group unit
     * @group fast
     */
    public function user_factory_creates_valid_user(): void
    {
        $user = User::factory()->make();

        $this->assertInstanceOf(User::class, $user);
        $this->assertNotEmpty($user->name);
        $this->assertNotEmpty($user->email);
    }

    /**
     * @test
     * @group api
     * @group integration
     */
    public function api_returns_users_list(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/users');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    /**
     * @test
     * @group api
     * @group slow
     * Este teste é lento por causa da paginação de um dataset grande
     */
    public function api_paginates_large_user_list(): void
    {
        User::factory()->count(100)->create();

        $response = $this->getJson('/api/users?page=1&per_page=10');

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 100);
    }

    /**
     * @test
     * @group api
     * @group authentication
     */
    public function authenticated_user_can_create_post(): User
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => 'Post de teste',
            'content' => 'Conteúdo',
        ]);

        $response->assertStatus(201);

        return $user;  // Retorna para o teste dependente
    }

    /**
     * @test
     * @group api
     * @depends authenticated_user_can_create_post
     */
    public function user_can_see_own_posts(User $user): void
    {
        $response = $this->actingAs($user)->getJson('/api/my-posts');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /**
     * @test
     * @group validation
     * @dataProvider invalidUserDataProvider
     */
    public function validates_user_creation(array $data, array $expectedErrors): void
    {
        $response = $this->postJson('/api/users', $data);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrors);
    }

    public static function invalidUserDataProvider(): array
    {
        return [
            'nome vazio' => [
                ['name' => '', 'email' => 'teste@email.com'],
                ['name'],
            ],
            'email inválido' => [
                ['name' => 'João', 'email' => 'not-an-email'],
                ['email'],
            ],
            'campos faltando' => [
                [],
                ['name', 'email'],
            ],
        ];
    }

    /**
     * @test
     * @group security
     * @group slow
     */
    public function prevents_sql_injection(): void
    {
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->postJson('/api/users/search', [
            'query' => $maliciousInput,
        ]);

        // Tem que devolver resultado vazio, não erro
        $response->assertStatus(200);
        $response->assertJsonCount(0, 'data');

        // Confere que a tabela ainda existe
        $this->assertDatabaseCount('users', 0);
    }
}

// Rodar grupos específicos:
// php artisan test --group=fast
// php artisan test --group=api
// php artisan test --exclude-group=slow
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
