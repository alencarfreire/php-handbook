# 1.3 Operadores em PHP

> **TL;DR**
> Sempre use === em vez de == (comparação estrita). PHP 8.0 trouxe match (switch melhorado), operador nullsafe (?->), null coalescing (??). PHP 7.4 — spread (...). Spaceship (<=>) para ordenar. Short-circuit em && e ||. Arrow functions (fn) capturam variáveis sozinhas. Ternário ?: checa falsy, ?? checa só null.

## Conteúdo

- [Operadores aritméticos](#operadores-aritméticos)
- [Operadores de comparação](#operadores-de-comparação)
- [Operadores lógicos](#operadores-lógicos)
- [Operadores de null (PHP 7+)](#operadores-de-null-php-7)
- [Operador ternário](#operador-ternário)
- [match (PHP 8.0+)](#match-php-80)
- [Operador de concatenação](#operador-de-concatenação)
- [Operador spread (PHP 7.4+)](#operador-spread-php-74)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Operadores aritméticos

**O que é:**
Operadores para cálculo matemático.

**Como funciona:**
```php
$a = 10;
$b = 3;

echo $a + $b;   // 13 (soma)
echo $a - $b;   // 7  (subtração)
echo $a * $b;   // 30 (multiplicação)
echo $a / $b;   // 3.333... (divisão)
echo $a % $b;   // 1  (resto da divisão)
echo $a ** $b;  // 1000 (potência, PHP 5.6+)

// Unários
echo -$a;       // -10 (negação)
echo +$a;       // 10  (positivo)

// Incremento/decremento
$i = 5;
echo ++$i;      // 6 (incrementa primeiro, depois devolve)
echo $i++;      // 6 (devolve primeiro, depois incrementa)
echo $i;        // 7
```

**Quando usar:**
Cálculo (soma, quantidade, porcentagem, paginação).

**Exemplo prático:**
```php
// Paginação
$page = $request->input('page', 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$users = User::skip($offset)->take($perPage)->get();

// Cálculo de desconto
$price = 1000;
$discount = 15;  // 15%
$finalPrice = $price - ($price * $discount / 100);

// Arredondar para centavos (preço em centavos)
$priceInCents = 1099;
$priceInReais = $priceInCents / 100;  // 10.99
```

**Na entrevista:**
> "Operadores aritméticos: +, -, *, /, %, **. Incremento: ++$i incrementa primeiro, $i++ devolve primeiro. Para dinheiro eu uso int em centavos, não float."

---

## Operadores de comparação

**O que é:**
Operadores para comparar valores.

**Como funciona:**
```php
// == (igual, com conversão de tipos)
var_dump(5 == '5');      // true (string '5' → int 5)
var_dump(0 == false);    // true
var_dump('' == false);   // true

// === (idêntico, sem conversão de tipos)
var_dump(5 === '5');     // false (tipos diferentes)
var_dump(0 === false);   // false
var_dump('' === false);  // false

// != (diferente) vs !== (não idêntico)
var_dump(5 != '5');      // false
var_dump(5 !== '5');     // true

// Comparação
var_dump(5 > 3);         // true
var_dump(5 >= 5);        // true
var_dump(3 < 5);         // true
var_dump(3 <= 3);        // true

// <=> (spaceship, PHP 7.0+)
echo 1 <=> 2;   // -1 (esquerda menor)
echo 2 <=> 2;   //  0 (iguais)
echo 3 <=> 2;   //  1 (esquerda maior)
```

**Quando usar:**
- `===` / `!==` — sempre que der (comparação estrita)
- `==` / `!=` — só quando você quer conversão de tipos
- `<=>` — para ordenar

**Exemplo prático:**
```php
// Checar parâmetro
if ($request->input('status') === 'active') {
    // Comparação estrita (evita '0', false, null)
}

// RUIM
if ($user->role == 'admin') {  // '0' também passa!
    // ...
}

// BOM
if ($user->role === 'admin') {
    // ...
}

// Ordenação com <=>
usort($users, fn($a, $b) => $a['age'] <=> $b['age']);

// Laravel Collection
$sorted = $users->sortBy('age');  // Por baixo dos panos usa <=>
```

**Na entrevista:**
> "== converte tipo (5 == '5'), === checa tipo e valor. Eu sempre uso ===, salvo quando preciso da conversão. <=> (spaceship) é para ordenar: devolve -1, 0 ou 1."

---

## Operadores lógicos

**O que é:**
Operadores para expressão lógica.

**Como funciona:**
```php
// && (AND) — os dois precisam ser true
var_dump(true && true);   // true
var_dump(true && false);  // false

// || (OR) — pelo menos um true
var_dump(true || false);  // true
var_dump(false || false); // false

// ! (NOT) — inversão
var_dump(!true);          // false
var_dump(!false);         // true

// and, or, xor (precedência baixa)
$a = true and false;  // $a = true (atribui primeiro, depois o and)
$a = true && false;   // $a = false (&& primeiro, depois a atribuição)
```

**Quando usar:**
Condição, validação, checagem.

**Exemplo prático:**
```php
// Checar permissão
if ($user->isAdmin() && $user->isActive()) {
    // Usuário é admin E está ativo
}

// Checar se existe
if (isset($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    // Email existe E é válido
}

// Short-circuit evaluation (avaliação preguiçosa)
if ($user && $user->isAdmin()) {
    // Se $user = null, $user->isAdmin() NÃO roda (evita o erro)
}

// Gate no Laravel
if (Gate::allows('update', $post) && $post->isPublished()) {
    // Pode editar E o post está publicado
}
```

**Na entrevista:**
> "Operadores lógicos: && (AND), || (OR), ! (NOT). O PHP faz short-circuit: se o primeiro lado do && é false, o segundo nem roda. Isso evita erro tipo chamar método em null."

---

## Operadores de null (PHP 7+)

**O que é:**
Operadores para trabalhar com null.

**Como funciona:**
```php
// ?? (null coalescing, PHP 7.0+)
$name = $user->name ?? 'Visitante';
// Se $user->name = null ou não existe → 'Visitante'

// Equivale a:
$name = isset($user->name) ? $user->name : 'Visitante';

// Em cadeia
$value = $a ?? $b ?? $c ?? 'default';

// ??= (null coalescing assignment, PHP 7.4+)
$config['timeout'] ??= 30;
// Se $config['timeout'] = null ou não existe → atribui 30

// Equivale a:
$config['timeout'] = $config['timeout'] ?? 30;

// ?-> (nullsafe operator, PHP 8.0+)
$street = $user?->address?->street;
// Se $user = null → devolve null (sem erro)
// Se $address = null → devolve null (sem erro)

// Sem nullsafe (PHP < 8.0)
$street = $user && $user->address ? $user->address->street : null;
```

**Quando usar:**
- `??` — valor default
- `??=` — lazy init
- `?->` — acesso seguro a propriedade/método

**Exemplo prático:**
```php
// Query params com default
$page = $request->input('page') ?? 1;
$perPage = $request->input('per_page') ?? 20;
$sort = $request->input('sort') ?? 'created_at';

// Config com default
$timeout = config('api.timeout') ?? 30;

// Lazy init
class Cache
{
    private ?Redis $redis = null;

    public function getRedis(): Redis
    {
        $this->redis ??= new Redis();  // Cria só uma vez
        return $this->redis;
    }
}

// Nullsafe em objetos aninhados
$city = $user?->profile?->address?->city ?? 'Não informado';

// Eloquent
$department = $user?->department?->name ?? 'Sem departamento';
```

**Na entrevista:**
> "?? devolve o primeiro valor que não é null. ??= atribui se for null. ?-> (PHP 8) é acesso seguro: se o objeto é null, devolve null sem erro. Uso no lugar de isset() ? : default."

---

## Operador ternário

**O que é:**
if-else enxuto.

**Como funciona:**
```php
// Forma completa
$status = $isActive ? 'Ativo' : 'Inativo';

// Forma curta (Elvis operator, PHP 5.3+)
$name = $user->name ?: 'Visitante';
// Se $user->name = truthy → devolve ele
// Se $user->name = falsy (null, '', 0) → devolve 'Visitante'

// ⚠️ Diferença para ??
$value = 0;
echo $value ?: 'default';   // "default" (0 = falsy)
echo $value ?? 'default';   // "0" (?? checa só null)
```

**Quando usar:**
Condição simples. Se ficar complexo, use if-else.

**Exemplo prático:**
```php
// Condição curta
$role = $user->isAdmin() ? 'Administrador' : 'Usuário';

// Cor do badge
$badgeClass = match($status) {
    'active' => 'badge-success',
    'pending' => 'badge-warning',
    'blocked' => 'badge-danger',
    default => 'badge-secondary',
};

// No Blade (Laravel)
<span class="{{ $user->isActive() ? 'text-success' : 'text-danger' }}">
    {{ $user->isActive() ? 'Ativo' : 'Inativo' }}
</span>

// RUIM (complicou demais)
$result = $a > $b ? ($a > $c ? $a : $c) : ($b > $c ? $b : $c);

// BOM (mais claro)
$result = max($a, $b, $c);
```

**Na entrevista:**
> "Ternário: condition ? true : false. A forma curta ?: devolve o valor truthy ou o default. A diferença: ?: checa falsy (0, '', false), ?? checa só null."

---

## match (PHP 8.0+)

**O que é:**
Switch melhorado: devolve valor e compara com ===.

**Como funciona:**
```php
// switch (PHP < 8.0)
switch ($status) {
    case 'active':
        $message = 'Ativo';
        break;
    case 'pending':
        $message = 'Aguardando';
        break;
    default:
        $message = 'Desconhecido';
}

// match (PHP 8.0+)
$message = match($status) {
    'active' => 'Ativo',
    'pending' => 'Aguardando',
    default => 'Desconhecido',
};

// Vários valores
$type = match($code) {
    200, 201, 204 => 'success',
    400, 404 => 'client_error',
    500, 502, 503 => 'server_error',
    default => 'unknown',
};

// Condições
$category = match(true) {
    $age < 18 => 'child',
    $age < 65 => 'adult',
    default => 'senior',
};
```

**Prós e contras vs switch:**
1. **Comparação estrita** (===, não ==)
2. **Devolve valor** (não precisa de break)
3. **Erro se não tem default** e não deu match
4. **Menos código**

**Quando usar:**
No lugar de switch, quando você precisa devolver um valor.

**Exemplo prático:**
```php
// Status HTTP
$message = match($response->status()) {
    200 => 'OK',
    201 => 'Created',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    500 => 'Internal Server Error',
    default => 'Unknown Status',
};

// Enum (PHP 8.1)
enum Status: string {
    case Active = 'active';
    case Pending = 'pending';
    case Blocked = 'blocked';
}

$message = match($user->status) {
    Status::Active => 'Usuário ativo',
    Status::Pending => 'Aguardando confirmação',
    Status::Blocked => 'Bloqueado',
};

// Permissão
$canEdit = match(true) {
    $user->isAdmin() => true,
    $user->owns($post) => true,
    $post->isPublished() => false,
    default => false,
};
```

**Na entrevista:**
> "match é o switch melhorado do PHP 8. Diferenças: comparação estrita (===), devolve valor, não precisa de break. Se não der match e não tiver default, lança erro. Uso no lugar de switch quando preciso devolver valor."

---

## Operador de concatenação

**O que é:**
Juntar strings com `.` ou interpolação.

**Como funciona:**
```php
// Concatenação com .
$firstName = 'João';
$lastName = 'Silva';
$fullName = $firstName . ' ' . $lastName;  // "João Silva"

// .= (acrescenta na string)
$message = 'Olá, ';
$message .= $firstName;
$message .= '!';
echo $message;  // "Olá, João!"

// Interpolação (aspas duplas)
$greeting = "Olá, $firstName!";  // "Olá, João!"
$greeting = "Olá, {$firstName}!";  // Interpolação explícita

// Propriedade/método — {} é obrigatório
$greeting = "Olá, {$user->name}!";
```

**Quando usar:**
- Interpolação — caso simples
- Concatenação — expressão mais complexa

**Exemplo prático:**
```php
// Template de email
$subject = "Olá, {$user->name}!";
$body = "Seu pedido #{$order->id} está pronto para envio.";

// Query building
$sql = "SELECT * FROM users ";
$sql .= "WHERE is_active = true ";
$sql .= "ORDER BY created_at DESC";

// URL
$url = "/api/users/{$userId}/posts/{$postId}";

// Blade (Laravel)
<h1>Olá, {{ $user->name }}!</h1>
<p>Pedido #{{ $order->id }}</p>
```

**Na entrevista:**
> "Concatenação com . ou interpolação em aspas duplas. Variável: $var ou {$var}. Propriedade e método exigem {}: {$user->name}."

---

## Operador spread (PHP 7.4+)

**O que é:**
Unpacking de arrays com `...`.

**Como funciona:**
```php
// Unpacking do array
$arr1 = [1, 2, 3];
$arr2 = [4, 5, 6];
$merged = [...$arr1, ...$arr2];  // [1, 2, 3, 4, 5, 6]

// Equivale a array_merge, mas mais rápido
$merged = array_merge($arr1, $arr2);

// Unpacking na função
function sum(int ...$numbers): int {
    return array_sum($numbers);
}

echo sum(1, 2, 3, 4, 5);  // 15

$values = [1, 2, 3];
echo sum(...$values);  // 6 (unpacking do array em argumentos)

// Arrays associativos (PHP 8.1+)
$user = ['name' => 'João', 'age' => 25];
$extra = ['city' => 'São Paulo'];
$merged = [...$user, ...$extra];
// ['name' => 'João', 'age' => 25, 'city' => 'São Paulo']
```

**Quando usar:**
Juntar arrays, passar lista de argumentos.

**Exemplo prático:**
```php
// Merge de query params
$defaultFilters = ['status' => 'active', 'per_page' => 20];
$userFilters = $request->only(['search', 'category']);
$filters = [...$defaultFilters, ...$userFilters];

// Eloquent with() com relations extras
$baseRelations = ['author', 'category'];
$extraRelations = ['comments', 'tags'];
$posts = Post::with([...$baseRelations, ...$extraRelations])->get();

// Função variadic
class EventDispatcher
{
    public function fire(string $event, ...$args): void
    {
        foreach ($this->listeners[$event] ?? [] as $listener) {
            $listener(...$args);  // Passa todos os argumentos
        }
    }
}

$dispatcher->fire('user.created', $user, $timestamp);
```

**Na entrevista:**
> "... (spread) desempacota o array. Uso para juntar arrays (mais rápido que array_merge) e passar lista de argumentos. PHP 8.1 aceita spread em array associativo."

---

## Recapitulando

**Aritméticos:**
- `+, -, *, /, %, **`
- `++, --` (incremento/decremento)

**Comparação:**
- `==` vs `===` (sempre use ===)
- `<=>` (spaceship para ordenar)

**Lógicos:**
- `&&, ||, !` (short-circuit)

**Operadores de null:**
- `??` (null coalescing)
- `??=` (null coalescing assignment)
- `?->` (nullsafe, PHP 8.0)

**Condicionais:**
- `? :` (ternário)
- `match` (PHP 8.0, no lugar do switch)

**Strings:**
- `.` (concatenação)
- `"$var"` (interpolação)

**Arrays:**
- `...` (spread, PHP 7.4+)

**Importante na entrevista:**
- `===` vs `==` (estrita vs frouxa)
- `??` vs `?:` (null vs falsy)
- `match` vs `switch` (PHP 8.0)
- `?->` para acesso seguro (PHP 8.0)

---

## Exercícios práticos

### Exercício 1: Diferença entre == e ===
**Enunciado:** Preveja o resultado das comparações.

<details>
<summary>Solução</summary>

```php
<?php

// == (comparação frouxa)
var_dump(5 == '5');       // true (converte '5' → 5)
var_dump(0 == false);     // true
var_dump('' == false);    // true
var_dump(null == false);  // true
var_dump('0' == false);   // true
var_dump([] == false);    // true

// === (comparação estrita)
var_dump(5 === '5');      // false (tipos diferentes)
var_dump(0 === false);    // false
var_dump('' === false);   // false
var_dump(null === false); // false
var_dump('0' === false);  // false
var_dump([] === false);   // false

// Problema perigoso do ==
function findUserById(int $id, array $users): ?array
{
    foreach ($users as $user) {
        // ❌ PERIGOSO
        if ($user['id'] == $id) {
            return $user;
        }
        // Se $id = '1abc', acha o usuário com id = 1!
    }
    return null;
}

// ✅ CERTO
function findUserByIdCorrect(int $id, array $users): ?array
{
    foreach ($users as $user) {
        if ($user['id'] === $id) {
            return $user;
        }
    }
    return null;
}
```

**Pontos-chave:**
- `==` converte tipos (type juggling)
- `===` checa tipo e valor
- Sempre use `===` por segurança
</details>

### Exercício 2: ?? vs ?:
**Enunciado:** Explique a diferença entre ?? e ?:.

<details>
<summary>Solução</summary>

```php
<?php

$value = 0;

// ?: (Elvis operator) checa truthy/falsy
echo $value ?: 'default';   // "default" (0 = falsy)

// ?? (null coalescing) checa só null
echo $value ?? 'default';   // "0" (0 não é null)

// Exemplos
$name = '';
echo $name ?: 'Visitante';      // "Visitante" ('' = falsy)
echo $name ?? 'Visitante';      // "" ('' não é null)

$age = null;
echo $age ?: 18;            // 18
echo $age ?? 18;            // 18

// Exemplo prático
function getConfig(array $config): array
{
    return [
        // ?: para valores que podem vir vazios
        'title' => $config['title'] ?: 'Sem título',

        // ?? para parâmetros opcionais
        'timeout' => $config['timeout'] ?? 30,
        'retries' => $config['retries'] ?? 3,
    ];
}

// Exemplo Laravel
public function index(Request $request)
{
    // ?? para query params
    $page = $request->input('page') ?? 1;
    $perPage = $request->input('per_page') ?? 20;

    // ?: para fallback
    $search = $request->input('search') ?: null;
}
```

**Pontos-chave:**
- `?:` checa falsy (0, '', false, null, [])
- `??` checa só null
- `??` é mais seguro para número (0, '0')
</details>

### Exercício 3: match vs switch
**Enunciado:** Reescreva o switch usando match.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ switch (jeito antigo)
$status = 'pending';
$message = '';

switch ($status) {
    case 'pending':
        $message = 'Aguardando';
        break;
    case 'paid':
        $message = 'Pago';
        break;
    case 'shipped':
        $message = 'Enviado';
        break;
    default:
        $message = 'Desconhecido';
}

echo $message;

// ✅ match (PHP 8.0)
$message = match($status) {
    'pending' => 'Aguardando',
    'paid' => 'Pago',
    'shipped' => 'Enviado',
    default => 'Desconhecido',
};

echo $message;

// Vantagens do match
// 1. Comparação estrita (===)
$value = '1';

switch ($value) {
    case 1:  // Roda! (== converte '1' → 1)
        echo 'One';
        break;
}

match($value) {
    1 => 'One',  // NÃO roda (=== tipos diferentes)
    default => 'Other',
};

// 2. Devolve valor
$badge = match($status) {
    'pending' => 'badge-warning',
    'paid' => 'badge-success',
    'shipped' => 'badge-info',
    default => 'badge-secondary',
};

// 3. Vários valores
$httpCategory = match($code) {
    200, 201, 204 => 'success',
    400, 401, 403, 404 => 'client_error',
    500, 502, 503 => 'server_error',
    default => 'unknown',
};

// 4. Condições
$category = match(true) {
    $age < 18 => 'child',
    $age < 65 => 'adult',
    default => 'senior',
};
```

**Pontos-chave:**
- `match` usa comparação estrita (===)
- `match` devolve valor (não precisa de break)
- `match` lança UnhandledMatchError se não der match
- `match` é mais curto e mais legível
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
