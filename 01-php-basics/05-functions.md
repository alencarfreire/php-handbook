# 1.5 Funções em PHP

> **TL;DR**
> Sempre declare tipos de parâmetro e retorno com declare(strict_types=1). Arrow functions (fn) capturam variáveis automaticamente (não precisa de use). Generators (yield) economizam memória em consultas grandes. Funções variadic (...$args) aceitam quantidade variável de argumentos. Recursão precisa de caso base + limite de profundidade. PHP 8.1 trouxe first-class callable (func(...)).

## Conteúdo

- [Declaração e chamada de funções](#declaração-e-chamada-de-funções)
- [Type Hints (Tipagem de parâmetros e retorno)](#type-hints-tipagem-de-parâmetros-e-retorno)
- [Funções anônimas (Closures)](#funções-anônimas-closures)
- [Arrow Functions (PHP 7.4+)](#arrow-functions-php-74)
- [Variadic Functions (Quantidade variável de argumentos)](#variadic-functions-quantidade-variável-de-argumentos)
- [Generators (Geradores)](#generators-geradores)
- [Recursão](#recursão)
- [Callable e First-Class Callable (PHP 8.1+)](#callable-e-first-class-callable-php-81)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Declaração e chamada de funções

**O que é:**
Bloco de código com nome, que você chama quantas vezes quiser.

**Como funciona:**
```php
// Função simples
function greet(string $name): string
{
    return "Olá, $name!";
}

echo greet('João');  // "Olá, João!"

// Sem return (void)
function log(string $message): void
{
    file_put_contents('log.txt', $message . PHP_EOL, FILE_APPEND);
}

// Valores padrão
function getUsers(int $limit = 10, string $sort = 'created_at'): array
{
    return User::orderBy($sort)->limit($limit)->get()->toArray();
}

getUsers();              // limit=10, sort='created_at'
getUsers(20);            // limit=20, sort='created_at'
getUsers(20, 'name');    // limit=20, sort='name'

// Argumentos nomeados (PHP 8.0+)
getUsers(sort: 'name', limit: 5);  // A ordem não importa
```

**Quando usar:**
Código repetido, extrair lógica, facilitar a leitura.

**Exemplo prático:**
```php
// Formatação de preço
function formatPrice(int $cents, string $currency = 'BRL'): string
{
    $reais = $cents / 100;
    return number_format($reais, 2, ',', '.') . " $currency";
}

echo formatPrice(199900);  // "1.999,00 BRL"

// Checagem de permissão
function canEdit(User $user, Post $post): bool
{
    return $user->isAdmin() || $user->id === $post->author_id;
}

if (canEdit($currentUser, $post)) {
    // Editar
}

// No Laravel, melhor via Gate/Policy
Gate::define('update', fn(User $user, Post $post) => $user->id === $post->author_id);
```

**Na entrevista:**
> "Função é um bloco de código com nome. PHP 8.0 trouxe argumentos nomeados. Uso para lógica repetida, mas no Laravel eu prefiro classes (Services, Actions) em vez de função global."

---

## Type Hints (Tipagem de parâmetros e retorno)

**O que é:**
Declarar o tipo dos parâmetros e do valor de retorno.

**Como funciona:**
```php
// Tipos escalares (PHP 7.0+)
function add(int $a, int $b): int
{
    return $a + $b;
}

// Tipos nullable (PHP 7.1+)
function findUser(?int $id): ?User
{
    return $id ? User::find($id) : null;
}

// Union types (PHP 8.0+)
function process(int|string $value): string
{
    return (string) $value;
}

// Mixed (PHP 8.0+)
function log(mixed $value): void
{
    file_put_contents('log.txt', print_r($value, true));
}

// Intersection types (PHP 8.1+)
function save(Countable&Iterator $collection): void
{
    // $collection precisa implementar as DUAS interfaces
}

// Never (PHP 8.1+)
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}
```

**Quando usar:**
**Sempre** declare os tipos (com `declare(strict_types=1)`).

**Exemplo prático:**
```php
<?php
declare(strict_types=1);

namespace App\Services;

class OrderService
{
    public function __construct(
        private OrderRepository $repository,
        private PaymentGateway $gateway,
    ) {}

    public function create(int $userId, array $items, ?string $promoCode = null): Order
    {
        $amount = $this->calculateTotal($items);

        if ($promoCode !== null) {
            $amount = $this->applyPromoCode($amount, $promoCode);
        }

        $order = $this->repository->create([
            'user_id' => $userId,
            'amount' => $amount,
        ]);

        return $order;
    }

    private function calculateTotal(array $items): int
    {
        return array_reduce($items, fn($sum, $item) => $sum + $item['price'], 0);
    }

    private function applyPromoCode(int $amount, string $code): int
    {
        $discount = PromoCode::where('code', $code)->value('discount');
        return (int) ($amount * (1 - $discount / 100));
    }
}
```

**Na entrevista:**
> "Type hints declaram tipos de parâmetro e retorno. PHP 8.0 trouxe union types (int|string), PHP 8.1 intersection types. Sempre uso tipagem estrita (declare(strict_types=1))."

---

## Funções anônimas (Closures)

**O que é:**
Função sem nome. Você atribui a uma variável ou passa como argumento.

**Como funciona:**
```php
// Função anônima
$greet = function(string $name): string {
    return "Olá, $name!";
};

echo $greet('João');  // "Olá, João!"

// Usar variáveis de fora (use)
$prefix = 'Mr. ';

$addPrefix = function(string $name) use ($prefix): string {
    return $prefix . $name;
};

echo $addPrefix('Smith');  // "Mr. Smith"

// Por referência
$counter = 0;

$increment = function() use (&$counter): void {
    $counter++;
};

$increment();
$increment();
echo $counter;  // 2

// Funções de callback
$numbers = [1, 2, 3, 4, 5];

$squared = array_map(function($n) {
    return $n ** 2;
}, $numbers);

var_dump($squared);  // [1, 4, 9, 16, 25]
```

**Quando usar:**
Callbacks (array_map, array_filter, usort), Laravel Collection, eventos.

**Exemplo prático:**
```php
// Laravel Collection
$users = User::all();

$active = $users->filter(function($user) {
    return $user->is_active;
});

$names = $users->map(function($user) {
    return $user->name;
});

// Eager load com condição
$posts = Post::with(['comments' => function($query) {
    $query->where('approved', true)
          ->orderBy('created_at', 'desc')
          ->limit(5);
}])->get();

// Middleware
Route::get('/admin', function() {
    // ...
})->middleware(function($request, $next) {
    if (!auth()->user()?->isAdmin()) {
        abort(403);
    }
    return $next($request);
});

// Event Listener
Event::listen('user.created', function(User $user) {
    Mail::to($user->email)->send(new WelcomeMail($user));
});
```

**Na entrevista:**
> "Closures são funções sem nome. Para acessar variável de fora, uso use. No Laravel aparece muito em Collection (map, filter), Eloquent (with), middleware."

---

## Arrow Functions (PHP 7.4+)

**O que é:**
Sintaxe curta para closure simples.

**Como funciona:**
```php
// Função anônima comum
$squared = array_map(function($n) {
    return $n ** 2;
}, [1, 2, 3]);

// Arrow function
$squared = array_map(fn($n) => $n ** 2, [1, 2, 3]);

// use automático (sem declarar)
$multiplier = 10;

// Comum (precisa de use)
$multiply = function($n) use ($multiplier) {
    return $n * $multiplier;
};

// Arrow function (captura $multiplier sozinha)
$multiply = fn($n) => $n * $multiplier;

// ⚠️ Só expressão de uma linha
$process = fn($x) => $x * 2 + 1;  // ✅ OK

// Não aceita várias linhas
$process = fn($x) => {  // ❌ Syntax error
    $result = $x * 2;
    return $result + 1;
};
```

**Quando usar:**
Callback simples (uma linha, uma expressão).

**Exemplo prático:**
```php
// Laravel Collection
$users = User::all();

$activeNames = $users
    ->filter(fn($user) => $user->is_active)
    ->map(fn($user) => $user->name)
    ->toArray();

// Ordenação
$sorted = $users->sortBy(fn($user) => $user->created_at);

// groupBy com transformação
$grouped = $posts->groupBy(fn($post) => $post->created_at->format('Y-m'));

// usort
usort($items, fn($a, $b) => $a['priority'] <=> $b['priority']);

// Route model binding
Route::get('/posts/{post}', fn(Post $post) => view('posts.show', compact('post')));

// Gate
Gate::define('update', fn(User $user, Post $post) => $user->id === $post->author_id);
```

**Na entrevista:**
> "Arrow functions (fn) são o atalho para função de uma linha (PHP 7.4+). Capturam variáveis do escopo de fora automaticamente (não precisa de use). Uso em Collection, ordenação, Gates."

---

## Variadic Functions (Quantidade variável de argumentos)

**O que é:**
Função que aceita quantidade livre de argumentos via `...`.

**Como funciona:**
```php
// Parâmetro variadic
function sum(int ...$numbers): int
{
    return array_sum($numbers);
}

echo sum(1, 2, 3);        // 6
echo sum(1, 2, 3, 4, 5);  // 15

// Os primeiros parâmetros são normais, o último é variadic
function format(string $format, mixed ...$args): string
{
    return sprintf($format, ...$args);
}

echo format('Olá, %s! Você tem %d anos.', 'João', 25);

// Unpack do array em argumentos
$numbers = [1, 2, 3, 4, 5];
echo sum(...$numbers);  // 15

// Tipagem do parâmetro variadic
function addUsers(User ...$users): void
{
    foreach ($users as $user) {
        $user->save();
    }
}
```

**Quando usar:**
Quando a quantidade de argumentos é desconhecida (log, função matemática, builders).

**Exemplo prático:**
```php
// Logger
class Logger
{
    public function log(string $level, string $message, mixed ...$context): void
    {
        $formatted = sprintf('[%s] %s %s', $level, $message, json_encode($context));
        file_put_contents('log.txt', $formatted . PHP_EOL, FILE_APPEND);
    }
}

$logger->log('ERROR', 'Usuário não encontrado', ['user_id' => 123, 'ip' => '127.0.0.1']);

// Query builder
class QueryBuilder
{
    public function select(string ...$columns): self
    {
        $this->columns = $columns;
        return $this;
    }
}

$query->select('id', 'name', 'email');

// Event dispatcher
class EventDispatcher
{
    public function fire(string $event, mixed ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$args);
        }
    }
}

$dispatcher->fire('user.created', $user, $timestamp);

// Laravel Eloquent with()
Post::with('author', 'category', 'comments')->get();
// Por baixo dos panos: with(...$relations)
```

**Na entrevista:**
> "Funções variadic aceitam quantidade variável de argumentos com ... (PHP 5.6+). Dentro da função vira array. Dá para fazer unpack: func(...$array). Uso em log e builders."

---

## Generators (Geradores)

**O que é:**
Função que devolve um iterator via `yield` em vez de `return`.

**Como funciona:**
```php
// Função comum (carrega tudo na memória)
function getNumbersArray(): array
{
    $result = [];
    for ($i = 1; $i <= 1000000; $i++) {
        $result[] = $i;
    }
    return $result;  // Toda a memória ocupada
}

// Generator (um item por vez)
function getNumbersGenerator(): Generator
{
    for ($i = 1; $i <= 1000000; $i++) {
        yield $i;  // Devolve um por vez, memória livre
    }
}

// Uso
foreach (getNumbersGenerator() as $number) {
    echo $number;  // Recebe um por vez
}

// yield key => value
function getUsersGenerator(): Generator
{
    $users = User::cursor();  // Leitura linha a linha do banco

    foreach ($users as $user) {
        yield $user->id => $user->name;
    }
}

foreach (getUsersGenerator() as $id => $name) {
    echo "$id: $name";
}
```

**Quando usar:**
Consulta grande no banco, arquivo, API (sem carregar tudo na memória).

**Exemplo prático:**
```php
// Leitura linha a linha de arquivo grande
function readCsv(string $filePath): Generator
{
    $handle = fopen($filePath, 'r');

    while (($line = fgets($handle)) !== false) {
        yield str_getcsv($line);
    }

    fclose($handle);
}

foreach (readCsv('large.csv') as $row) {
    // Processa uma linha por vez (não carrega o arquivo inteiro)
    $this->processRow($row);
}

// Eloquent cursor (usa generator por baixo)
foreach (User::cursor() as $user) {
    // Carrega um registro por vez do banco
    $this->processUser($user);
}

// Paginação de API
function fetchAllPages(string $url): Generator
{
    $page = 1;

    do {
        $response = Http::get($url, ['page' => $page]);
        $data = $response->json('data');

        foreach ($data as $item) {
            yield $item;
        }

        $page++;
    } while (!empty($data));
}

foreach (fetchAllPages('/api/products') as $product) {
    // Processa um produto por vez
}
```

**Na entrevista:**
> "Generator devolve um iterator via yield em vez de return. Não carrega tudo na memória, entrega um item por vez. Uso em consulta grande no banco (cursor), arquivo, API. Eloquent::cursor() usa generator."

---

## Recursão

**O que é:**
Função que chama ela mesma.

**Como funciona:**
```php
// Fatorial
function factorial(int $n): int
{
    if ($n <= 1) {
        return 1;  // Caso base (parada)
    }

    return $n * factorial($n - 1);  // Chamada recursiva
}

echo factorial(5);  // 5 * 4 * 3 * 2 * 1 = 120

// Percorrer árvore de categorias
function getCategoryTree(int $parentId = null): array
{
    $categories = Category::where('parent_id', $parentId)->get();

    return $categories->map(function($category) {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'children' => getCategoryTree($category->id),  // Recursão
        ];
    })->toArray();
}

$tree = getCategoryTree();  // A hierarquia inteira
```

**Quando usar:**
Estrutura em árvore (menu, categoria, comentário), percorrer array aninhado.

**Exemplo prático:**
```php
// Menu com submenu
function buildMenu(int $parentId = null, int $depth = 0): string
{
    if ($depth > 3) {
        return '';  // Proteção contra recursão infinita
    }

    $items = MenuItem::where('parent_id', $parentId)
                     ->orderBy('order')
                     ->get();

    if ($items->isEmpty()) {
        return '';
    }

    $html = '<ul>';
    foreach ($items as $item) {
        $html .= "<li>{$item->title}";
        $html .= buildMenu($item->id, $depth + 1);  // Recursão para o submenu
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

// Buscar arquivo no diretório
function findFile(string $directory, string $filename): ?string
{
    $files = scandir($directory);

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $file;

        if (is_file($path) && $file === $filename) {
            return $path;
        }

        if (is_dir($path)) {
            $found = findFile($path, $filename);  // Recursão
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}
```

**Na entrevista:**
> "Recursão é a função chamando ela mesma. Precisa de caso base (condição de parada), senão vira infinita. Uso em estrutura em árvore (menu, categoria) e array aninhado. O ponto é limitar a profundidade."

---

## Callable e First-Class Callable (PHP 8.1+)

**O que é:**
Formas de passar funções como valor.

**Como funciona:**
```php
// Tipos callable
// 1. Nome da função (string)
$callback = 'strtoupper';
echo $callback('hello');  // "HELLO"

// 2. Função anônima
$callback = function($str) {
    return strtoupper($str);
};

// 3. Arrow function
$callback = fn($str) => strtoupper($str);

// 4. Método de classe
$callback = [$object, 'methodName'];
$callback = [ClassName::class, 'staticMethod'];

// 5. Invocar o objeto (__invoke)
class Transformer
{
    public function __invoke(string $str): string
    {
        return strtoupper($str);
    }
}

$callback = new Transformer();
echo $callback('hello');  // "HELLO"

// First-Class Callable (PHP 8.1+)
$callback = strtoupper(...);  // Mais curto que fn($x) => strtoupper($x)
echo $callback('hello');  // "HELLO"

$callback = $object->method(...);
$callback = ClassName::staticMethod(...);
```

**Quando usar:**
Callbacks, strategies, factories, middleware.

**Exemplo prático:**
```php
// Strategy pattern
class PriceCalculator
{
    public function calculate(int $price, callable $strategy): int
    {
        return $strategy($price);
    }
}

$calculator = new PriceCalculator();

// Estratégias diferentes
$withTax = fn($price) => (int) ($price * 1.2);
$withDiscount = fn($price) => (int) ($price * 0.9);

echo $calculator->calculate(1000, $withTax);       // 1200
echo $calculator->calculate(1000, $withDiscount);  // 900

// Laravel Pipeline
$result = Pipeline::send($request)
    ->through([
        fn($req, $next) => $this->authenticate($req, $next),
        fn($req, $next) => $this->authorize($req, $next),
        fn($req, $next) => $this->validate($req, $next),
    ])
    ->then(fn($req) => $this->handle($req));

// array_map com first-class callable (PHP 8.1)
$names = ['joao', 'pedro'];
$upper = array_map(strtoupper(...), $names);  // ['JOAO', 'PEDRO']
```

**Na entrevista:**
> "Callable é o tipo de coisa que você pode chamar. PHP 8.1 trouxe first-class callable (func(...)), mais curto que fn($x) => func($x). Uso em callback, strategy, Pipeline."

---

## Recapitulando

**O essencial:**
- Declaração: `function name(params): returnType { ... }`
- Type hints: sempre declare os tipos + `declare(strict_types=1)`
- Funções anônimas: `function() use ($var) { ... }`
- Arrow functions: `fn($x) => $x * 2` (PHP 7.4+)
- Variadic: `function(...$args)` (quantidade variável de argumentos)
- Generator: `yield` para consulta grande (economia de memória)
- Callable: passar funções como valor
- First-class callable: `func(...)` (PHP 8.1+)

**Importante na entrevista:**
- Arrow functions capturam variáveis automaticamente (não precisa de use)
- Generator (yield) não carrega tudo na memória
- Eloquent::cursor() usa generator
- Recursão: caso base obrigatório + limite de profundidade
- `declare(strict_types=1)` — use sempre

---

## Exercícios práticos

### Exercício 1: Arrow Function vs Closure
**Enunciado:** Compare o comportamento de arrow function e closure com variáveis de fora.

<details>
<summary>Solução</summary>

```php
<?php

$multiplier = 10;

// Closure (precisa de use)
$closure = function($n) use ($multiplier) {
    return $n * $multiplier;
};

// Arrow function (use automático)
$arrow = fn($n) => $n * $multiplier;

echo $closure(5);  // 50
echo $arrow(5);    // 50

// Mudança da variável de fora
$multiplier = 20;

echo $closure(5);  // 50 (ficou com o valor antigo)
echo $arrow(5);    // 100 (pegou o valor novo)

// Por referência na closure
$counter = 0;

$increment = function() use (&$counter) {
    $counter++;
};

$increment();
$increment();
echo $counter;  // 2

// ⚠️ Arrow function não aceita use por referência
// $arrowIncrement = fn() => $counter++;  // Não altera a variável de fora

// Exemplo prático (Laravel Collection)
$users = User::all();
$minAge = 18;

// Closure
$adults = $users->filter(function($user) use ($minAge) {
    return $user->age >= $minAge;
});

// Arrow function (mais curto)
$adults = $users->filter(fn($user) => $user->age >= $minAge);

// Encadeamento
$result = $users
    ->filter(fn($u) => $u->is_active)
    ->map(fn($u) => $u->name)
    ->sortBy(fn($name) => mb_strtolower($name))
    ->values();
```

**Pontos-chave:**
- Arrow function captura variáveis automaticamente
- Arrow function só aceita expressão de uma linha
- Arrow function não aceita use por referência
- Arrow function é mais curta e mais legível no caso simples
</details>

### Exercício 2: Generator para paginação de API
**Enunciado:** Crie um generator para buscar todas as páginas de uma API.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ RUIM (carrega todas as páginas na memória)
function fetchAllPagesBad(string $url): array
{
    $allData = [];
    $page = 1;

    do {
        $response = Http::get($url, ['page' => $page]);
        $data = $response->json('data');

        $allData = array_merge($allData, $data);
        $page++;
    } while (!empty($data));

    return $allData;  // O resultado inteiro na memória!
}

// ✅ BOM (generator, economiza memória)
function fetchAllPages(string $url): Generator
{
    $page = 1;

    do {
        $response = Http::get($url, ['page' => $page]);
        $data = $response->json('data');

        foreach ($data as $item) {
            yield $item;  // Devolve um item por vez
        }

        $hasMore = $response->json('meta.has_more', false);
        $page++;

    } while ($hasMore);
}

// Uso
function syncProducts(): array
{
    $synced = [];
    $errors = [];

    foreach (fetchAllPages('/api/products') as $product) {
        try {
            Product::updateOrCreate(
                ['external_id' => $product['id']],
                [
                    'name' => $product['name'],
                    'price' => $product['price'],
                ]
            );

            $synced[] = $product['id'];

        } catch (\Exception $e) {
            $errors[] = [
                'product_id' => $product['id'],
                'error' => $e->getMessage(),
            ];
        }
    }

    return [
        'synced' => count($synced),
        'errors' => count($errors),
        'details' => $errors,
    ];
}

// Generator com chaves
function fetchUsersWithKeys(): Generator
{
    foreach (User::cursor() as $user) {
        yield $user->id => $user->name;
    }
}

foreach (fetchUsersWithKeys() as $id => $name) {
    echo "$id: $name\n";
}
```

**Pontos-chave:**
- Generator não carrega todos os dados na memória
- `yield` devolve um item por vez
- Serve para API com paginação
- Eloquent::cursor() também usa generator
</details>

### Exercício 3: Montar árvore de categorias com recursão
**Enunciado:** Monte uma árvore hierárquica de categorias com proteção contra recursão infinita.

<details>
<summary>Solução</summary>

```php
<?php

class CategoryTree
{
    private const MAX_DEPTH = 5;

    /**
     * Monta a árvore de categorias
     *
     * @param int|null $parentId ID da categoria pai
     * @param int $depth Profundidade atual da recursão
     * @return array
     */
    public function buildTree(?int $parentId = null, int $depth = 0): array
    {
        // Proteção contra recursão infinita
        if ($depth >= self::MAX_DEPTH) {
            Log::warning("Profundidade máxima atingida para parent_id: $parentId");
            return [];
        }

        $categories = Category::where('parent_id', $parentId)
            ->orderBy('position')
            ->get();

        return $categories->map(function($category) use ($depth) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'depth' => $depth,
                'children' => $this->buildTree($category->id, $depth + 1),
            ];
        })->toArray();
    }

    /**
     * Caminho até a categoria (breadcrumbs)
     */
    public function findPath(int $categoryId): array
    {
        $path = [];
        $category = Category::find($categoryId);

        while ($category !== null) {
            array_unshift($path, [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ]);

            $category = $category->parent;
        }

        return $path;
    }

    /**
     * Checa se a categoria é descendente de outra
     */
    public function isDescendantOf(int $childId, int $ancestorId, int $maxDepth = 10): bool
    {
        $category = Category::find($childId);
        $depth = 0;

        while ($category !== null && $depth < $maxDepth) {
            if ($category->parent_id === $ancestorId) {
                return true;
            }

            $category = $category->parent;
            $depth++;
        }

        return false;
    }
}

// Uso
$tree = (new CategoryTree())->buildTree();
/*
[
    [
        'id' => 1,
        'name' => 'Eletrônicos',
        'children' => [
            [
                'id' => 2,
                'name' => 'Celulares',
                'children' => [...]
            ]
        ]
    ]
]
*/

$breadcrumbs = (new CategoryTree())->findPath(5);
// [['id' => 1, 'name' => 'Eletrônicos'], ['id' => 2, 'name' => 'Celulares'], ...]
```

**Pontos-chave:**
- Proteção obrigatória contra recursão infinita (MAX_DEPTH)
- Caso base (quando parar a recursão)
- Passar a profundidade no parâmetro
- Log ao atingir o limite
- Alternativa — loop while (findPath, isDescendantOf)
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
