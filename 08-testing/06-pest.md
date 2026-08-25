# 7.6 Pest

## Resumo

> **Pest** — testing framework moderno em cima do PHPUnit. Estilo funcional, sem classes, sintaxe elegante.
>
> **Sintaxe:** `it()` para testes, `expect()->toBe()` no lugar de assertions. Datasets no lugar de Data Providers. `beforeEach()`/`afterEach()` para setup.
>
> **Importante:** Higher Order Tests para checagens curtas. Testes de arquitetura para dependências. Parallel execution de fábrica. Menos boilerplate, mais legível.

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
Pest é um testing framework moderno para PHP, com sintaxe elegante. Roda em cima do PHPUnit.

**O essencial:**
- Estilo funcional (sem classes)
- Menos boilerplate
- Mais legível

---

## Como funciona

**Instalação:**

```bash
composer require pestphp/pest --dev --with-all-dependencies
php artisan pest:install
```

**Teste básico:**

```php
// PHPUnit
class ExampleTest extends TestCase
{
    public function test_example(): void
    {
        $result = 2 + 2;
        $this->assertEquals(4, $result);
    }
}

// Pest (mais curto)
test('example', function () {
    $result = 2 + 2;
    expect($result)->toBe(4);
});
```

**Expectations (equivalente às assertions):**

```php
// Igualdade
expect($value)->toBe(5);  // ===
expect($value)->toEqual(5);  // ==
expect($value)->not->toBe(3);

// Boolean
expect($value)->toBeTrue();
expect($value)->toBeFalse();

// Null
expect($value)->toBeNull();
expect($value)->not->toBeNull();

// Arrays
expect($array)->toHaveCount(3);
expect($array)->toContain('item');
expect($array)->toHaveKey('name');

// Strings
expect($string)->toContain('hello');
expect($string)->toStartWith('Hello');
expect($string)->toEndWith('world');

// Instance
expect($user)->toBeInstanceOf(User::class);

// Exceptions
expect(fn() => throw new Exception())->toThrow(Exception::class);
```

---

## Quando usar

**Pest vs PHPUnit:**

| PHPUnit | Pest |
|---------|------|
| Classes | Funções |
| Mais código | Menos código |
| Padrão | Moderno |
| $this->assert | expect()->to |

**Use Pest quando:**
- Projeto novo
- Quer sintaxe mais limpa
- O time topa

---

## Exemplo prático

**Feature tests:**

```php
// tests/Feature/PostTest.php
use App\Models\{User, Post};

it('shows post list', function () {
    Post::factory()->count(5)->create();

    $response = $this->get('/posts');

    $response->assertStatus(200);
    $response->assertViewIs('posts.index');
});

it('creates post', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/posts', [
        'title' => 'Test Post',
        'body' => 'Content',
    ]);

    $response->assertStatus(302);
    expect(Post::count())->toBe(1);
});

it('validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/posts', []);

    $response->assertSessionHasErrors(['title', 'body']);
});
```

**Datasets (equivalente aos Data Providers):**

```php
it('adds numbers', function (int $a, int $b, int $expected) {
    $calculator = new Calculator();
    $result = $calculator->add($a, $b);

    expect($result)->toBe($expected);
})->with([
    [2, 3, 5],
    [10, 5, 15],
    [-5, 5, 0],
]);

// Datasets nomeados
dataset('users', [
    'admin' => [User::factory()->admin()->create()],
    'regular' => [User::factory()->create()],
]);

it('can view dashboard', function (User $user) {
    $response = $this->actingAs($user)->get('/dashboard');
    $response->assertStatus(200);
})->with('users');
```

**Hooks (setup/teardown):**

```php
// Antes de cada teste
beforeEach(function () {
    $this->user = User::factory()->create();
});

// Depois de cada teste
afterEach(function () {
    // Cleanup
});

// Uma vez antes de todos os testes
beforeAll(function () {
    // Setup
});

// Uma vez depois de todos os testes
afterAll(function () {
    // Cleanup
});

// Uso
it('can login', function () {
    $response = $this->actingAs($this->user)->get('/profile');
    $response->assertStatus(200);
});
```

**Higher Order Tests:**

```php
// Arquivar
it('archives post')
    ->actingAs(User::factory()->create())
    ->post('/posts/1/archive')
    ->assertStatus(200);

// JSON API
it('returns user')
    ->getJson('/api/users/1')
    ->assertStatus(200)
    ->assertJson(['id' => 1]);
```

**Custom Expectations:**

```php
// Criar um expect customizado
expect()->extend('toBeWithinRange', function (int $min, int $max) {
    return $this->toBeGreaterThanOrEqual($min)
        ->toBeLessThanOrEqual($max);
});

// Uso
test('age is valid', function () {
    $user = User::factory()->create(['age' => 25]);
    expect($user->age)->toBeWithinRange(18, 65);
});
```

**Grupos:**

```php
// Marcar com grupo
it('slow test')->group('slow');

it('api test')->group('api', 'slow');

// Rodar o grupo
pest --group=api

// Excluir o grupo
pest --exclude-group=slow
```

**Execução em paralelo:**

```bash
# Mais rápido em máquinas com vários núcleos
pest --parallel

# Definir o número de processos
pest --parallel --processes=4
```

**Pest.php (configuração global):**

```php
// tests/Pest.php
uses(TestCase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

// Hooks globais
beforeEach(function () {
    // Roda antes de cada teste
});

// Funções customizadas
function createUser(array $attributes = []): User
{
    return User::factory()->create($attributes);
}

// Uso nos testes
it('creates user', function () {
    $user = createUser(['name' => 'João']);
    expect($user->name)->toBe('João');
});
```

**Plugins:**

```bash
# Plugin Laravel (já vem ligado)
composer require pestphp/pest-plugin-laravel --dev

# Faker plugin
composer require pestphp/pest-plugin-faker --dev

# Livewire plugin
composer require pestphp/pest-plugin-livewire --dev
```

**Snapshots (testar o output):**

```php
it('generates correct output', function () {
    $output = view('emails.welcome', ['name' => 'João'])->render();

    expect($output)->toMatchSnapshot();
});

// Na primeira execução cria o snapshot
// Nas próximas, compara
```

**Testes de arquitetura:**

```php
// Checar dependências
arch('controllers')
    ->expect('App\Http\Controllers')
    ->toOnlyUse([
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Services',
    ]);

// Checar naming
arch('models')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model')
    ->toHaveSuffix('Model');

// Checar o que não pode usar
arch('services')
    ->expect('App\Services')
    ->not->toUse('Illuminate\Support\Facades');
```

---

## Na entrevista

> "Pest é um testing framework moderno em cima do PHPUnit. Estilo funcional, sem classes. expect()->toBe() no lugar de $this->assertEquals(). it() para os testes, beforeEach()/afterEach() para o setup. Datasets no lugar de Data Providers. Higher Order Tests para checagens curtas. Testes de arquitetura para checar dependências. Parallel execution de fábrica. Plugins para Laravel, Livewire, Faker. Menos boilerplate, mais legível."

---

## Exercícios práticos

### Exercício 1: Converter teste PHPUnit para Pest

Converta o teste PHPUnit do `Calculator` para a sintaxe do Pest. Use datasets para parametrizar.

<details>
<summary>Solução</summary>

```php
// Versão PHPUnit (estilo antigo)
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

    public function test_adds_numbers(): void
    {
        $result = $this->calculator->add(5, 3);
        $this->assertEquals(8, $result);
    }

    /**
     * @dataProvider numbersProvider
     */
    public function test_multiplies_numbers(int $a, int $b, int $expected): void
    {
        $result = $this->calculator->multiply($a, $b);
        $this->assertEquals($expected, $result);
    }

    public static function numbersProvider(): array
    {
        return [
            [2, 3, 6],
            [5, 4, 20],
            [0, 10, 0],
        ];
    }
}

// Versão Pest (estilo novo)
// tests/Unit/CalculatorPestTest.php
use App\Services\Calculator;

beforeEach(function () {
    $this->calculator = new Calculator();
});

it('adds numbers', function () {
    $result = $this->calculator->add(5, 3);

    expect($result)->toBe(8);
});

it('subtracts numbers', function () {
    $result = $this->calculator->subtract(10, 4);

    expect($result)->toBe(6);
});

it('multiplies numbers', function (int $a, int $b, int $expected) {
    $result = $this->calculator->multiply($a, $b);

    expect($result)->toBe($expected);
})->with([
    [2, 3, 6],
    [5, 4, 20],
    [0, 10, 0],
    [7, 7, 49],
]);

it('divides numbers', function () {
    $result = $this->calculator->divide(20, 4);

    expect($result)->toBe(5.0);
});

it('throws exception on division by zero', function () {
    $this->calculator->divide(10, 0);
})->throws(DivisionByZeroError::class);

// Datasets nomeados
dataset('math operations', [
    'positive numbers' => [10, 5, 50],
    'negative numbers' => [-3, -2, 6],
    'mixed signs' => [-4, 5, -20],
    'with zero' => [0, 100, 0],
]);

it('handles various multiplication scenarios', function ($a, $b, $expected) {
    expect($this->calculator->multiply($a, $b))->toBe($expected);
})->with('math operations');

// Cadeia de expectations
it('performs complex calculation', function () {
    $result = $this->calculator->add(10, 5);

    expect($result)
        ->toBe(15)
        ->toBeGreaterThan(10)
        ->toBeLessThan(20)
        ->toBeNumeric();
});
```
</details>

### Exercício 2: Higher Order Tests para API

Crie um conjunto de testes de API com Higher Order Tests e cadeias de expectations.

<details>
<summary>Solução</summary>

```php
// tests/Feature/Api/PostApiTest.php
use App\Models\{User, Post};

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

// Higher Order Test (sintaxe curta)
it('shows posts list')
    ->get('/api/posts')
    ->assertStatus(200)
    ->assertJsonStructure([
        'data' => [
            '*' => ['id', 'title', 'content', 'author'],
        ],
    ]);

// Teste comum com expectations
it('creates post', function () {
    $response = $this->postJson('/api/posts', [
        'title' => 'Test Post',
        'content' => 'Test content',
    ]);

    $response->assertStatus(201);

    expect(Post::count())->toBe(1);

    $post = Post::first();
    expect($post)
        ->title->toBe('Test Post')
        ->content->toBe('Test content')
        ->user_id->toBe($this->user->id);
});

// Dataset para validação
it('validates required fields', function ($field, $value) {
    $data = [
        'title' => 'Valid Title',
        'content' => 'Valid content',
    ];

    $data[$field] = $value;

    $response = $this->postJson('/api/posts', $data);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([$field]);
})->with([
    ['title', ''],
    ['title', null],
    ['content', ''],
    ['content', null],
]);

// Agrupar testes
describe('Post filtering', function () {
    beforeEach(function () {
        Post::factory()->count(3)->create(['published' => true]);
        Post::factory()->count(2)->create(['published' => false]);
    });

    it('filters published posts', function () {
        $response = $this->getJson('/api/posts?status=published');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    it('filters draft posts', function () {
        $response = $this->getJson('/api/posts?status=draft');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(2);
    });

    it('shows all posts without filter', function () {
        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        expect($response->json('data'))->toHaveCount(5);
    });
});

// Custom expectations
expect()->extend('toBeValidPost', function () {
    return $this
        ->toHaveKey('id')
        ->toHaveKey('title')
        ->toHaveKey('content')
        ->toHaveKey('author');
});

it('returns valid post structure', function () {
    $post = Post::factory()->create();

    $response = $this->getJson("/api/posts/{$post->id}");

    expect($response->json('data'))->toBeValidPost();
});

// Testes com tags (grupos)
it('handles pagination', function () {
    Post::factory()->count(30)->create();

    $response = $this->getJson('/api/posts?page=2&per_page=10');

    expect($response->json())
        ->toHaveKey('data')
        ->and($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.current_page'))->toBe(2)
        ->and($response->json('meta.total'))->toBe(30);
})->group('pagination', 'slow');

it('searches posts by title', function () {
    Post::factory()->create(['title' => 'Laravel Testing']);
    Post::factory()->create(['title' => 'PHP Best Practices']);

    $response = $this->getJson('/api/posts?search=Laravel');

    expect($response->json('data'))
        ->toHaveCount(1)
        ->and($response->json('data.0.title'))->toContain('Laravel');
})->group('search');
```
</details>

### Exercício 3: Testes de arquitetura

Crie testes de arquitetura para checar a estrutura do projeto (dependências, naming conventions).

<details>
<summary>Solução</summary>

```php
// tests/Architecture/ControllersTest.php

// Todo controller fica no namespace App\Http\Controllers
arch('controllers are in correct namespace')
    ->expect('App\Http\Controllers')
    ->toBeClasses()
    ->toHaveSuffix('Controller');

// Controllers só podem usar certas classes
arch('controllers follow dependency rules')
    ->expect('App\Http\Controllers')
    ->toOnlyUse([
        'Illuminate\Http',
        'Illuminate\Routing',
        'App\Http\Requests',
        'App\Http\Resources',
        'App\Services',
        'App\Models',
    ]);

// Controllers NÃO usam DB direto
arch('controllers do not use DB directly')
    ->expect('App\Http\Controllers')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'Illuminate\Database\Query\Builder',
    ]);

// tests/Architecture/ModelsTest.php

// Todo model estende Eloquent Model
arch('models extend eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

// Models podem usar traits
arch('models can use specific traits')
    ->expect('App\Models')
    ->toOnlyUse([
        'Illuminate\Database\Eloquent',
        'Illuminate\Database\Eloquent\Factories',
        'Illuminate\Notifications',
    ]);

// tests/Architecture/ServicesTest.php

// Services ficam no namespace certo
arch('services are in correct namespace')
    ->expect('App\Services')
    ->toBeClasses()
    ->toHaveSuffix('Service');

// Services NÃO usam a camada HTTP
arch('services do not use HTTP layer')
    ->expect('App\Services')
    ->not->toUse([
        'Illuminate\Http\Request',
        'Illuminate\Routing',
    ]);

// tests/Architecture/GeneralTest.php

// Nenhum código de production usa dd() ou dump()
arch('no debugging functions in production code')
    ->expect(['dd', 'dump', 'var_dump', 'print_r'])
    ->not->toBeUsed();

// Helpers globais do Laravel são permitidos
arch('can use laravel helpers')
    ->expect('App')
    ->toUse([
        'config',
        'cache',
        'route',
        'view',
    ]);

// tests/Architecture/NamingTest.php

// Classes Request têm o sufixo Request
arch('request classes have Request suffix')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request');

// Classes Resource têm o sufixo Resource
arch('resource classes have Resource suffix')
    ->expect('App\Http\Resources')
    ->toHaveSuffix('Resource');

// Classes Job têm o sufixo Job
arch('job classes have Job suffix')
    ->expect('App\Jobs')
    ->toHaveSuffix('Job')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

// Events ficam no namespace certo
arch('events are in correct namespace')
    ->expect('App\Events')
    ->toBeClasses()
    ->not->toBeAbstract();

// Listeners tratam eventos
arch('listeners have handle method')
    ->expect('App\Listeners')
    ->toHaveMethod('handle');

// tests/Architecture/LayersTest.php

// Models não conhecem a camada HTTP
arch('models are independent of HTTP')
    ->expect('App\Models')
    ->not->toUse([
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Http\Resources',
    ]);

// Rodar os testes de arquitetura:
// pest --filter=Architecture
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
