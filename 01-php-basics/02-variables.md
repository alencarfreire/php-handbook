# 1.2 Variáveis em PHP

> **TL;DR**
> Variáveis começam com $, tipagem dinâmica. isset() checa existência e não null, empty() checa falsy. & cria referência (faça unset depois do foreach). Evite variável global, use DI. Superglobais ($_GET, $_POST, $_SERVER) no Laravel viram o objeto Request. Constantes: const na classe para status.

## Conteúdo

- [Declaração e atribuição](#declaração-e-atribuição)
- [isset() vs empty()](#isset-vs-empty)
- [Variáveis variáveis](#variáveis-variáveis)
- [Referências (References)](#referências-references)
- [Variáveis globais](#variáveis-globais)
- [Variáveis superglobais](#variáveis-superglobais)
- [Constantes](#constantes)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Declaração e atribuição

**O que é:**
No PHP a variável começa com `$` e não precisa de tipo explícito.

**Como funciona:**
```php
$name = 'João';
$age = 25;
$price = 99.99;

// Tipagem dinâmica
$value = 10;        // int
$value = 'string';  // agora string (reatribuição)
```

**Quando usar:**
Para guardar qualquer dado no código (string, número, objeto, array).

**Exemplo prático:**
```php
// Controller
public function store(Request $request)
{
    $data = $request->validated();
    $user = User::create($data);

    return response()->json($user, 201);
}
```

**Na entrevista:**
> "No PHP variável começa com $, tipagem dinâmica. O tipo entra na atribuição e pode mudar."

---

## isset() vs empty()

**O que é:**
Funções para checar se a variável existe e qual o valor.

**Como funciona:**
```php
$var = null;

// isset() — existe E não é null
var_dump(isset($var));        // false (null)
var_dump(isset($undefined));  // false (não existe)

$var = 0;
var_dump(isset($var));        // true (existe, mesmo sendo 0)

// empty() — valor "vazio"
var_dump(empty(0));          // true
var_dump(empty('0'));        // true
var_dump(empty(''));         // true
var_dump(empty(null));       // true
var_dump(empty([]));         // true
var_dump(empty(false));      // true

var_dump(empty('hello'));    // false
var_dump(empty(1));          // false
```

**Quando usar:**
- `isset()` — checar se a variável está definida (não null)
- `empty()` — checar se o valor é "vazio" (falsy)

**Exemplo prático:**
```php
// Checar query params
public function index(Request $request)
{
    // isset — checa se o parâmetro veio
    if (isset($request->query()['status'])) {
        $status = $request->query('status');
    }

    // empty — checa se o valor não está vazio
    if (!empty($request->input('search'))) {
        $query->where('name', 'like', "%{$request->input('search')}%");
    }
}

// Checar array
$filters = $request->input('filters', []);
if (!empty($filters)) {
    // Aplica os filtros
}
```

**Na entrevista:**
> "isset() checa se a variável existe e não é null. empty() checa falsy: 0, '0', '', null, [], false. O pulo do gato: empty('0') = true. isset() não olha o valor, só se existe."

---

## Variáveis variáveis

**O que é:**
Usar o valor de uma variável como nome de outra.

**Como funciona:**
```php
$name = 'value';
$value = 'Hello!';

echo $$name;  // "Hello!" (acessa $value)

// Sintaxe mais explícita
echo ${$name};  // "Hello!"

// Perigoso!
$field = $_GET['field'];  // Pode ser qualquer coisa
echo $$field;  // Vulnerabilidade potencial
```

**Quando usar:**
Raro. Prefira array ou objeto.

**Exemplo prático:**
```php
// RUIM (não faça isso)
$status_active = 'Ativo';
$status_blocked = 'Bloqueado';
$currentStatus = 'active';
echo ${"status_$currentStatus"};  // "Ativo"

// BOM (use array)
$statuses = [
    'active' => 'Ativo',
    'blocked' => 'Bloqueado',
];
echo $statuses[$currentStatus];  // "Ativo"
```

**Na entrevista:**
> "$$var é variável variável. Uso pouco, prefiro array ou objeto. É perigoso se o nome vem de fora (XSS, RCE)."

---

## Referências (References)

**O que é:**
Criar um alias da variável com `&`.

**Como funciona:**
```php
$a = 10;
$b = &$a;  // $b — referência a $a

$b = 20;
echo $a;  // 20 (mudou via $b, $a também mudou)

// Passar por referência para a função
function increment(&$value) {
    $value++;
}

$count = 5;
increment($count);
echo $count;  // 6 (o original mudou)
```

**Quando usar:**
- Quando precisa mudar a variável original dentro da função
- Evite sem necessidade (complica o código)

**Exemplo prático:**
```php
// Modificar array no loop
$users = [
    ['name' => 'João', 'active' => false],
    ['name' => 'Pedro', 'active' => false],
];

// Com referência
foreach ($users as &$user) {
    $user['active'] = true;  // Muda o array original
}
unset($user);  // IMPORTANTE! Limpar a referência depois do loop

var_dump($users);
// Todos os usuários agora active = true

// Sem referência (não muda)
foreach ($users as $user) {
    $user['active'] = false;  // Muda só a cópia
}
// $users não mudou
```

**⚠️ Erro clássico:**
```php
$array = [1, 2, 3];

foreach ($array as &$value) {
    $value *= 2;
}
// NÃO esquecer unset($value)!

// Se esquecer o unset():
foreach ($array as $value) {
    // $value ainda é referência ao último elemento!
    // Isso sobrescreve o último elemento
}

var_dump($array);  // [2, 4, 4] (e não [2, 4, 6])

// Certo:
unset($value);  // Limpar a referência depois do primeiro foreach
```

**Na entrevista:**
> "& cria referência. Uso para mudar o original na função ou no foreach. Depois do foreach com referência, unset() é obrigatório — senão o último elemento pode ser sobrescrito."

---

## Variáveis globais

**O que é:**
Variáveis visíveis em qualquer escopo via `global` ou `$GLOBALS`.

**Como funciona:**
```php
$name = 'João';  // Variável global

function greet() {
    global $name;  // Acesso à variável global
    echo "Olá, $name!";
}

greet();  // "Olá, João!"

// Ou via $GLOBALS
function greet2() {
    echo "Olá, {$GLOBALS['name']}!";
}
```

**Quando usar:**
❌ Evite variável global! Use:
- Dependency Injection
- Parâmetros de função
- Classes e propriedades

**Exemplo prático:**
```php
// RUIM
$db = new Database();

function getUsers() {
    global $db;  // Ruim: depende de variável global
    return $db->query('SELECT * FROM users');
}

// BOM (Dependency Injection)
class UserRepository
{
    public function __construct(private Database $db) {}

    public function getUsers(): array
    {
        return $this->db->query('SELECT * FROM users');
    }
}

// Laravel
$users = app(UserRepository::class)->getUsers();
```

**Na entrevista:**
> "global dá acesso a variável global. Evito, prefiro Dependency Injection. No Laravel as dependências passam pelo Service Container."

---

## Variáveis superglobais

**O que é:**
Arrays nativos do PHP, disponíveis em todo lugar.

**Como funciona:**
```php
// $_GET — parâmetros da URL (?name=Joao)
$name = $_GET['name'] ?? 'Visitante';

// $_POST — dados do form
$email = $_POST['email'] ?? null;

// $_SERVER — info do servidor
$ip = $_SERVER['REMOTE_ADDR'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$method = $_SERVER['REQUEST_METHOD'];

// $_SESSION — dados da sessão
$_SESSION['user_id'] = 1;
$userId = $_SESSION['user_id'] ?? null;

// $_COOKIE — cookies
$token = $_COOKIE['auth_token'] ?? null;

// $_FILES — arquivos enviados
$file = $_FILES['avatar'] ?? null;

// $_ENV — variáveis de ambiente
$dbHost = $_ENV['DB_HOST'];
```

**Quando usar:**
Em PHP legado. No Laravel use:
- `$request->input()` no lugar de `$_GET` / `$_POST`
- `$request->session()` no lugar de `$_SESSION`
- `$request->cookie()` no lugar de `$_COOKIE`
- `$request->file()` no lugar de `$_FILES`
- `env()` ou `config()` no lugar de `$_ENV`

**Exemplo prático:**
```php
// JEITO ANTIGO (plain PHP)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if ($email && $password) {
        // ...
    }
}

// MODERNO (Laravel)
public function login(Request $request)
{
    $credentials = $request->only('email', 'password');

    if (Auth::attempt($credentials)) {
        return redirect()->intended('dashboard');
    }
}
```

**Na entrevista:**
> "Superglobais ($_GET, $_POST, $_SERVER, $_SESSION, $_COOKIE, $_FILES) estão em todo lugar. No Laravel eu não uso direto: trabalho com o objeto Request e as facades."

---

## Constantes

**O que é:**
Valores imutáveis, definidos com `define()` ou `const`.

**Como funciona:**
```php
// define() — funciona em qualquer lugar
define('APP_NAME', 'My App');
echo APP_NAME;  // "My App"

// const — só no nível superior ou na classe
const VERSION = '1.0.0';
echo VERSION;

// Na classe
class Config
{
    public const DB_HOST = 'localhost';
    public const DB_PORT = 5432;
}

echo Config::DB_HOST;  // "localhost"
```

**Quando usar:**
Para valor que não muda (config, versão, constante).

**Exemplo prático:**
```php
// Laravel config
// config/app.php
return [
    'name' => env('APP_NAME', 'Laravel'),
    'version' => '10.0',
];

// Uso
$appName = config('app.name');

// Constantes na classe (status, tipos)
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}

// Uso
if ($order->status === Order::STATUS_PAID) {
    // ...
}
```

**Na entrevista:**
> "Constantes via define() ou const. Na classe uso const para status e tipo. No Laravel a config vai em config(), não em constante."

---

## Recapitulando

**O essencial:**
- `$var` — variável (tipagem dinâmica)
- `isset()` — existe e não é null
- `empty()` — valor falsy (0, '0', '', null, [], false)
- `&` — referência (muda o original)
- `global` / `$GLOBALS` — evite, use DI
- Superglobais: `$_GET`, `$_POST`, `$_SERVER` — no Laravel via Request
- Constantes: `define()` ou `const`

**Importante na entrevista:**
- `empty('0')` = true (pergunta clássica)
- Depois do foreach com `&$value`, faça `unset($value)`
- Evite variável global, use Dependency Injection
- No Laravel trabalhe com Request, não com superglobal

---

## Exercícios práticos

### Exercício 1: isset() vs empty()
**Enunciado:** Determine o resultado para valores diferentes da variável.

<details>
<summary>Solução</summary>

```php
<?php

function testVariable($value): array
{
    return [
        'value' => $value,
        'isset' => isset($value),
        'empty' => empty($value),
        'is_null' => is_null($value),
        'bool_cast' => (bool) $value,
    ];
}

// Testes
var_dump(testVariable(null));
// ['isset' => false, 'empty' => true, 'is_null' => true, 'bool_cast' => false]

var_dump(testVariable(0));
// ['isset' => true, 'empty' => true, 'is_null' => false, 'bool_cast' => false]

var_dump(testVariable('0'));
// ['isset' => true, 'empty' => true, 'is_null' => false, 'bool_cast' => false]

var_dump(testVariable(''));
// ['isset' => true, 'empty' => true, 'is_null' => false, 'bool_cast' => false]

var_dump(testVariable([]));
// ['isset' => true, 'empty' => true, 'is_null' => false, 'bool_cast' => false]

var_dump(testVariable(false));
// ['isset' => true, 'empty' => true, 'is_null' => false, 'bool_cast' => false]

var_dump(testVariable('false'));
// ['isset' => true, 'empty' => false, 'is_null' => false, 'bool_cast' => true] ⚠️
```

**Pontos-chave:**
- `isset()`: false só para null e variável inexistente
- `empty()`: true para todo valor falsy
- A string `'false'` NÃO é vazia (é truthy)
</details>

### Exercício 2: Problema das referências no foreach
**Enunciado:** Encontre e corrija o erro no código.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ ERRADO
$numbers = [1, 2, 3];

foreach ($numbers as &$n) {
    $n *= 2;
}
// Esqueceu o unset($n)!

foreach ($numbers as $n) {
    echo $n . ' ';
}
// Saída: 2 4 4 (e não 2 4 6)
// Último elemento sobrescrito!

// ✅ CERTO
$numbers = [1, 2, 3];

foreach ($numbers as &$n) {
    $n *= 2;
}
unset($n);  // OBRIGATÓRIO!

foreach ($numbers as $n) {
    echo $n . ' ';
}
// Saída: 2 4 6 ✅

// ✅ AINDA MELHOR (sem referência)
$numbers = array_map(fn($n) => $n * 2, $numbers);
// Ou no Laravel:
$numbers = collect($numbers)->map(fn($n) => $n * 2)->toArray();
```

**Pontos-chave:**
- Depois do foreach com `&` a variável continua referência ao último elemento
- O segundo foreach sobrescreve o último elemento
- Sempre faça `unset()` depois do foreach com referência
- Prefira o jeito funcional (array_map, Collection)
</details>

### Exercício 3: Dependency Injection no lugar de variáveis globais
**Enunciado:** Refatore o código com variáveis globais para DI.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ RUIM (variáveis globais)
$db = new PDO('mysql:host=localhost;dbname=test', 'root', '');
$logger = new Logger('app.log');

function getUsers(): array
{
    global $db, $logger;

    $logger->info('Buscando usuários');
    return $db->query('SELECT * FROM users')->fetchAll();
}

// ✅ BOM (Dependency Injection)
class UserRepository
{
    public function __construct(
        private PDO $db,
        private Logger $logger
    ) {}

    public function getAll(): array
    {
        $this->logger->info('Buscando usuários');
        return $this->db->query('SELECT * FROM users')->fetchAll();
    }
}

// Uso
$repository = new UserRepository($db, $logger);
$users = $repository->getAll();

// ✅ LARAVEL (Service Container)
class UserRepository
{
    public function __construct(
        private DB $db,
        private Log $logger
    ) {}

    public function getAll(): array
    {
        $this->logger->info('Buscando usuários');
        return $this->db->table('users')->get();
    }
}

// Laravel injeta as dependências sozinho
$repository = app(UserRepository::class);
$users = $repository->getAll();
```

**Pontos-chave:**
- Variável global complica o teste
- DI deixa a dependência explícita
- O Service Container do Laravel resolve as dependências sozinho
- Fica mais fácil mockar no teste
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
