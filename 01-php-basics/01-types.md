# 1.1 Tipos em PHP

> **TL;DR**
> PHP tem 8 tipos principais: int, float, string, bool (escalares), array, object (compostos), null, resource (especiais). Dinheiro: use int em centavos, não float (perde precisão). Array usa copy-on-write, objeto passa por referência. PHP 8.0 trouxe mixed, union types, never. Sempre use tipagem estrita (`declare(strict_types=1)`).

## Conteúdo

- [Tipos escalares](#tipos-escalares)
  - [int (Número inteiro)](#int-número-inteiro)
  - [float (Número de ponto flutuante)](#float-número-de-ponto-flutuante)
  - [string (String)](#string-string)
  - [bool (Booleano)](#bool-booleano)
- [Tipos compostos](#tipos-compostos)
  - [array (Array)](#array-array)
  - [object (Objeto)](#object-objeto)
- [Tipos especiais](#tipos-especiais)
  - [null](#null)
  - [resource](#resource)
- [Pseudotipos (PHP 7.0+)](#pseudotipos-php-70)
  - [mixed (PHP 8.0+)](#mixed-php-80)
  - [callable](#callable)
  - [iterable (PHP 7.1+)](#iterable-php-71)
  - [void (PHP 7.1+)](#void-php-71)
  - [never (PHP 8.1+)](#never-php-81)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Tipos escalares

### int (Número inteiro)

**O que é:**
Números inteiros, sem parte fracionária (positivo, negativo, zero).

**Como funciona:**
```php
$age = 25;
$temperature = -10;
$zero = 0;

// O máximo depende do sistema (32-bit / 64-bit)
echo PHP_INT_MAX;  // 9223372036854775807 (em 64-bit)

// Overflow → vira float sozinho
$big = PHP_INT_MAX + 1;  // float
```

**Quando usar:**
Contadores, ID, quantidade, idade, ano, mês, paginação.

**Exemplo prático:**
```php
// Paginação
$page = 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// ID do usuário
$userId = User::find($id)->id;  // int
```

**Na entrevista:**
> "int é número inteiro. Uso para ID, contador, paginação. Se passar de PHP_INT_MAX, vira float sozinho."

---

### float (Número de ponto flutuante)

**O que é:**
Número com parte fracionária.

**Como funciona:**
```php
$price = 99.99;
$temperature = 36.6;
$pi = 3.14159;

// Notação científica
$bigNumber = 1.5e10;  // 15000000000
```

**⚠️ Problema de precisão:**
```php
$a = 0.1 + 0.2;
var_dump($a === 0.3);  // FALSE! 😱
// $a = 0.30000000000000004 (representação interna)

// Comparação correta
$epsilon = 0.00001;
var_dump(abs($a - 0.3) < $epsilon);  // TRUE
```

**Quando usar:**
Cálculo matemático, grandeza física (peso, altura, temperatura).

**❌ Não use para dinheiro!**

**Exemplo prático:**
```php
// RUIM para dinheiro
$total = 10.1 + 10.2;  // 20.30000000000000004

// BOM — int em centavos
$totalCents = 1010 + 1020;  // 2030 (exato)
$totalReais = $totalCents / 100;  // 20.30 (só para exibir)

// Ou uma lib
use Brick\Money\Money;
$price = Money::of(99.99, 'BRL');
```

**Na entrevista:**
> "float é número de ponto flutuante. O problema clássico é precisão: 0.1 + 0.2 ≠ 0.3. Para dinheiro eu uso int em centavos ou Brick\Money."

---

### string (String)

**O que é:**
Sequência de caracteres.

**Como funciona:**
```php
// Aspas simples (literal)
$name = 'João';

// Aspas duplas (interpolação)
$greeting = "Olá, $name!";  // "Olá, João!"

// Heredoc (várias linhas, com interpolação)
$html = <<<HTML
<div>
    <h1>$name</h1>
</div>
HTML;

// Nowdoc (várias linhas, sem interpolação)
$text = <<<'TEXT'
Olá, $name!
TEXT;
// Resultado: "Olá, $name!" (literal)
```

**Quando usar:**
Nome, email, texto, HTML, JSON, mensagem, log.

**Exemplo prático:**
```php
// Template de email
$user = User::find($userId);
$subject = "Olá, {$user->name}!";

// SQL em várias linhas
$query = <<<SQL
    SELECT u.name, COUNT(p.id) as posts_count
    FROM users u
    LEFT JOIN posts p ON u.id = p.user_id
    WHERE u.is_active = true
    GROUP BY u.id
SQL;
```

**Na entrevista:**
> "string é texto. Aspas simples é literal (mais rápido), aspas duplas interpolam. Para Unicode eu uso as funções mb_* (mb_strlen, mb_substr)."

---

### bool (Booleano)

**O que é:**
Valor lógico: `true` ou `false`.

**Como funciona:**
```php
$isActive = true;
$isAdmin = false;

// Cast para bool (valores falsy)
var_dump((bool) 0);          // false
var_dump((bool) '0');        // false
var_dump((bool) '');         // false
var_dump((bool) null);       // false
var_dump((bool) []);         // false
var_dump((bool) 'false');    // TRUE! (string não vazia)
```

**Quando usar:**
Flags (is_active, is_admin), resultado de checagem, condição.

**Exemplo prático:**
```php
// Checar permissão
if ($user->isAdmin()) {
    // ...
}

// Feature flags
$featureEnabled = config('features.new_ui');

// Validação
$isValid = filter_var($email, FILTER_VALIDATE_EMAIL);
```

**Na entrevista:**
> "bool é true ou false. O pulo do gato são os falsy: 0, '0', '', null, [], false — tudo isso vira false. A string 'false' vira true, porque não está vazia."

---

## Tipos compostos

### array (Array)

**O que é:**
Coleção ordenada de pares chave-valor (hash table associativa).

**Como funciona:**
```php
// Array indexado
$numbers = [1, 2, 3, 4, 5];

// Array associativo
$user = [
    'id' => 1,
    'name' => 'João',
    'email' => 'joao@email.com',
];

// Arrays aninhados
$users = [
    ['id' => 1, 'name' => 'João'],
    ['id' => 2, 'name' => 'Pedro'],
];

// Acesso
echo $user['name'];  // "João"
echo $users[0]['id'];  // 1
```

**Copy-on-Write:**
```php
$a = [1, 2, 3];  // Alocou memória
$b = $a;         // Ainda não copia (só a referência)
$b[] = 4;        // AQUI cria a cópia (copy-on-write)

var_dump($a);  // [1, 2, 3]
var_dump($b);  // [1, 2, 3, 4]
```

**Quando usar:**
Lista, coleção, config, parâmetros, resultado de query.

**Exemplo prático:**
```php
// Resultado de query
$users = DB::table('users')->get()->toArray();

// Passar parâmetros
Post::create([
    'title' => 'Post Title',
    'content' => 'Content...',
    'author_id' => 1,
]);

// Route middleware
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth', 'role:admin']);
```

**Na entrevista:**
> "array é uma hash table ordenada. Serve para indexado e associativo. Usa copy-on-write: a cópia só nasce quando você altera."

---

### object (Objeto)

**O que é:**
Instância de uma classe, com dados (propriedades) e comportamento (métodos).

**Como funciona:**
```php
class User
{
    public string $name;
    public string $email;

    public function __construct(string $name, string $email)
    {
        $this->name = $name;
        $this->email = $email;
    }

    public function greet(): string
    {
        return "Olá, {$this->name}!";
    }
}

$user = new User('João', 'joao@email.com');
echo $user->greet();  // "Olá, João!"
```

**Passa por referência (no comportamento):**
```php
function changeEmail(User $user): void
{
    $user->email = 'novo@email.com';  // Muda o original
}

$user = new User('João', 'joao@email.com');
changeEmail($user);
echo $user->email;  // "novo@email.com" (mudou!)
```

**Quando usar:**
Models (User, Post), services (PaymentService), Value Objects (Money, Email).

**Exemplo prático:**
```php
// Model Eloquent
$user = User::find(1);
$user->update(['name' => 'Novo nome']);

// Service
class OrderService
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }
}

// Value Object
class Money
{
    public function __construct(
        private int $amount,
        private string $currency
    ) {}

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new \Exception('Currency mismatch');
        }
        return new Money($this->amount + $other->amount, $this->currency);
    }
}
```

**Na entrevista:**
> "object é instância de classe. Na prática passa por referência: se você muda a propriedade dentro da função, o original muda. Uso para model, service, value object."

---

## Tipos especiais

### null

**O que é:**
Ausência de valor.

**Como funciona:**
```php
$value = null;

// Checagem
if ($value === null) {
    echo 'Valor não definido';
}

// isset() checa se existe E !== null
isset($value);  // false

// ?? (null coalescing) - PHP 7.0+
$name = $user->name ?? 'Visitante';

// ?-> (nullsafe) - PHP 8.0+
$street = $user->address?->street;  // Se address = null → devolve null (sem erro)
```

**Quando usar:**
Parâmetro opcional, resultado de busca (achou / não achou), propriedade opcional.

**Exemplo prático:**
```php
// Eloquent
$user = User::find($userId);  // User | null

if ($user === null) {
    abort(404);
}

// Nullable type hint (PHP 7.1+)
function getUser(?int $id): ?User
{
    return $id ? User::find($id) : null;
}

// Nullsafe chain (PHP 8.0+)
$city = $user?->address?->city ?? 'Não informado';
```

**Na entrevista:**
> "null é ausência de valor. PHP 8.0 trouxe o operador nullsafe (?->), PHP 7.0 o null coalescing (??). Uso em parâmetro opcional e resultado de busca."

---

### resource

**O que é:**
Referência a um recurso externo (arquivo, conexão de banco, curl).

**Como funciona:**
```php
// Arquivo
$file = fopen('file.txt', 'r');
var_dump($file);  // resource(3) of type (stream)
fclose($file);

// cURL (PHP < 8.0)
$ch = curl_init('https://api.example.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
```

**PHP 8.0+ — objeto no lugar de resource:**
```php
$ch = curl_init();  // CurlHandle object (não resource)
$file = fopen('file.txt', 'r');  // resource (stream) → no futuro também vira objeto
```

**Quando usar:**
Código legado (PHP < 8.0), trabalho baixo nível com arquivo/rede.

**Na entrevista:**
> "resource é referência a recurso externo (arquivo, conexão). No PHP 8+ isso vai virando objeto (curl_init() → CurlHandle). O ponto é não esquecer de fechar."

---

## Pseudotipos (PHP 7.0+)

### mixed (PHP 8.0+)

**O que é:**
Qualquer tipo (equivalente a não ter type hint).

```php
function process(mixed $value): mixed
{
    // Aceita qualquer coisa, devolve qualquer coisa
    return $value;
}
```

---

### callable

**O que é:**
Função, closure, método de classe.

```php
function hello(): void
{
    echo 'Hello!';
}

$callback = 'hello';
$callback();  // "Hello!"

// Em array_map
array_map(fn($x) => $x * 2, [1, 2, 3]);  // [2, 4, 6]
```

---

### iterable (PHP 7.1+)

**O que é:**
Array ou objeto que implementa Traversable.

```php
function printItems(iterable $items): void
{
    foreach ($items as $item) {
        echo $item;
    }
}

printItems([1, 2, 3]);  // array
printItems(new ArrayIterator([1, 2, 3]));  // Traversable
```

---

### void (PHP 7.1+)

**O que é:**
A função não devolve nada.

```php
function log(string $message): void
{
    file_put_contents('log.txt', $message);
    // return; — pode, mas não é obrigatório
}
```

---

### never (PHP 8.1+)

**O que é:**
A função nunca devolve o controle (exit, exception).

```php
function redirect(string $url): never
{
    header("Location: $url");
    exit;
}

function fail(string $message): never
{
    throw new Exception($message);
}
```

---

## Recapitulando

**Escalares:**
- `int` — inteiro (ID, contador)
- `float` — fracionário (**não use para dinheiro**)
- `string` — texto (nome, conteúdo)
- `bool` — true/false (flags)

**Compostos:**
- `array` — hash table (copy-on-write)
- `object` — instância de classe (por referência)

**Especiais:**
- `null` — ausência de valor
- `resource` — recurso externo (legado no PHP 8+)

**Pseudotipos:**
- `mixed` — qualquer tipo (PHP 8.0+)
- `callable` — função
- `iterable` — array ou Traversable (PHP 7.1+)
- `void` — não devolve nada (PHP 7.1+)
- `never` — nunca devolve (PHP 8.1+)

**Importante na entrevista:**
- float: perde precisão → int para dinheiro
- array: copy-on-write
- object: passa por referência (no comportamento)
- null: ??, ?->
- Falsy: 0, '0', '', null, [], false

---

## Exercícios práticos

### Exercício 1: Trabalhar com tipos
**Enunciado:** Crie uma função que recebe mixed e devolve informação sobre o tipo e o valor.

<details>
<summary>Solução</summary>

```php
<?php
declare(strict_types=1);

function analyzeType(mixed $value): array
{
    return [
        'type' => get_debug_type($value),
        'value' => $value,
        'is_scalar' => is_scalar($value),
        'is_truthy' => (bool) $value,
        'string_representation' => var_export($value, true),
    ];
}

// Testes
var_dump(analyzeType(42));
// ['type' => 'int', 'value' => 42, 'is_scalar' => true, 'is_truthy' => true, ...]

var_dump(analyzeType(0));
// ['type' => 'int', 'value' => 0, 'is_scalar' => true, 'is_truthy' => false, ...]

var_dump(analyzeType(''));
// ['type' => 'string', 'value' => '', 'is_scalar' => true, 'is_truthy' => false, ...]
```

**Pontos-chave:**
- `get_debug_type()` (PHP 8.0) devolve o tipo exato
- `is_scalar()` checa tipos escalares
- Cast para bool mostra truthy/falsy
</details>

### Exercício 2: Dinheiro
**Enunciado:** Implemente a classe Money para somar valores sem perder precisão.

<details>
<summary>Solução</summary>

```php
<?php
declare(strict_types=1);

class Money
{
    public function __construct(
        private int $amountInCents,
        private string $currency = 'BRL'
    ) {}

    public static function fromReais(float $reais, string $currency = 'BRL'): self
    {
        return new self((int) round($reais * 100), $currency);
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }

        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function toReais(): float
    {
        return $this->amountInCents / 100;
    }

    public function format(): string
    {
        return 'R$ ' . number_format($this->toReais(), 2, ',', '.');
    }
}

// Testes
$price1 = Money::fromReais(10.10, 'BRL');
$price2 = Money::fromReais(20.20, 'BRL');

$total = $price1->add($price2);
echo $total->format();  // "R$ 30,30"
```

**Pontos-chave:**
- Guarda em centavos (int) para não perder precisão
- Converte só na hora de exibir
- Valida a moeda nas operações
</details>

### Exercício 3: Type Juggling e Strict Types
**Enunciado:** Explique a diferença de comportamento da função com e sem strict_types.

<details>
<summary>Solução</summary>

```php
<?php
// Arquivo: without_strict.php (SEM strict_types)

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add(5, 10);      // 15 ✅
echo add(5, '10');    // 15 ✅ (converte '10' → 10)
echo add('5', '10');  // 15 ✅ (converte os dois para int)
```

```php
<?php
// Arquivo: with_strict.php (COM strict_types)
declare(strict_types=1);

function add(int $a, int $b): int
{
    return $a + $b;
}

echo add(5, 10);      // 15 ✅
echo add(5, '10');    // ❌ TypeError: Argument #2 must be of type int, string given
echo add('5', '10');  // ❌ TypeError
```

**Pontos-chave:**
- Sem `strict_types` o PHP converte o tipo sozinho
- Com `strict_types=1` exige o tipo exato
- `strict_types` vale só no arquivo onde foi declarado
- Use sempre `strict_types=1` para o comportamento ficar previsível
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
