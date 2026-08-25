# 1.6 Arrays em PHP

> **TL;DR**
> Array em PHP é uma hash table ordenada com copy-on-write. in_array por padrão não é estrito (precisa do terceiro parâmetro true). unset() não reindexa (precisa de array_values). array_key_exists funciona com null, isset() não. Spread (...) é mais rápido que array_merge. No Laravel, prefira os métodos de Collection (map, filter, reduce) no lugar de array_*.

## Conteúdo

- [Criação de arrays](#criação-de-arrays)
- [Adição e remoção de elementos](#adição-e-remoção-de-elementos)
- [array_map, array_filter, array_reduce](#array_map-array_filter-array_reduce)
- [Ordenação de arrays](#ordenação-de-arrays)
- [array_merge, array_combine, array_diff](#array_merge-array_combine-array_diff)
- [in_array, array_key_exists, array_search](#in_array-array_key_exists-array_search)
- [array_column, array_unique, array_flip](#array_column-array_unique-array_flip)
- [Desestruturação de arrays (PHP 7.1+)](#desestruturação-de-arrays-php-71)
- [Spread Operator (PHP 7.4+)](#spread-operator-php-74)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## Criação de arrays

**O que é:**
Coleção ordenada de pares chave-valor.

**Como funciona:**
```php
// Array indexado (numeric keys)
$numbers = [1, 2, 3, 4, 5];
$numbers = array(1, 2, 3, 4, 5);  // Sintaxe antiga

// Array associativo (string keys)
$user = [
    'id' => 1,
    'name' => 'João',
    'email' => 'joao@email.com',
];

// Array misto
$mixed = [
    0 => 'first',
    'key' => 'value',
    1 => 'second',
];

// Arrays aninhados
$users = [
    ['id' => 1, 'name' => 'João'],
    ['id' => 2, 'name' => 'Pedro'],
];

// Acesso aos elementos
echo $numbers[0];      // 1
echo $user['name'];    // "João"
echo $users[0]['id'];  // 1
```

**Quando usar:**
Lista, coleção, config, parâmetros de request.

**Exemplo prático:**
```php
// Request data
$data = $request->only(['name', 'email', 'password']);

// Config
$dbConfig = [
    'host' => env('DB_HOST'),
    'port' => env('DB_PORT'),
    'database' => env('DB_DATABASE'),
];

// Response
return response()->json([
    'status' => 'success',
    'data' => $users->toArray(),
    'meta' => [
        'total' => $total,
        'page' => $page,
    ],
]);
```

**Na entrevista:**
> "Array em PHP é uma hash table ordenada. Aceita chave numérica (indexado) e string (associativo). Por baixo é a mesma estrutura."

---

## Adição e remoção de elementos

**Como funciona:**
```php
$arr = [1, 2, 3];

// Adicionar no final
$arr[] = 4;  // [1, 2, 3, 4]
array_push($arr, 5, 6);  // [1, 2, 3, 4, 5, 6]

// Adicionar no início
array_unshift($arr, 0);  // [0, 1, 2, 3, 4, 5, 6]

// Remover do final
$last = array_pop($arr);  // 6, array [0, 1, 2, 3, 4, 5]

// Remover do início
$first = array_shift($arr);  // 0, array [1, 2, 3, 4, 5]

// Remover por chave
unset($arr[2]);  // [1, 2, 4, 5] (chaves: 0, 1, 3, 4)

// Remover e reindexar
$arr = array_values($arr);  // [1, 2, 4, 5] (chaves: 0, 1, 2, 3)

// Arrays associativos
$user = ['name' => 'João', 'email' => 'joao@email.com'];
$user['age'] = 25;  // Adicionar
unset($user['email']);  // Remover
```

**Exemplo prático:**
```php
// Adicionar filtros
$filters = [];

if ($request->has('status')) {
    $filters['status'] = $request->input('status');
}

if ($request->has('department_id')) {
    $filters['department_id'] = $request->input('department_id');
}

// Merge de arrays
$defaultParams = ['per_page' => 20, 'sort' => 'created_at'];
$params = array_merge($defaultParams, $request->only(['search', 'status']));

// Remover valores vazios
$data = array_filter($request->all(), fn($value) => $value !== null && $value !== '');
```

**Na entrevista:**
> "Adicionar: [] ou array_push (final), array_unshift (início). Remover: array_pop (final), array_shift (início), unset (por chave). unset não reindexa — precisa de array_values."

---

## array_map, array_filter, array_reduce

**O que é:**
Funções de ordem superior para trabalhar com arrays.

**Como funciona:**
```php
$numbers = [1, 2, 3, 4, 5];

// array_map — transforma cada elemento
$squared = array_map(fn($n) => $n ** 2, $numbers);
// [1, 4, 9, 16, 25]

// array_filter — filtra elementos
$even = array_filter($numbers, fn($n) => $n % 2 === 0);
// [2, 4] (as chaves ficam: [1 => 2, 3 => 4])

// array_reduce — reduz a um valor
$sum = array_reduce($numbers, fn($carry, $n) => $carry + $n, 0);
// 15 (1 + 2 + 3 + 4 + 5)

// Combinando
$result = array_reduce(
    array_filter($numbers, fn($n) => $n % 2 === 0),
    fn($carry, $n) => $carry + $n ** 2,
    0
);
// 20 (2² + 4² = 4 + 16)
```

**Quando usar:**
Transformar, filtrar, agregar dados.

**Exemplo prático:**
```php
// Extrair IDs
$userIds = array_map(fn($user) => $user['id'], $users);

// Filtrar usuários ativos
$active = array_filter($users, fn($user) => $user['is_active']);

// Somar pedidos
$total = array_reduce($orders, fn($sum, $order) => $sum + $order['amount'], 0);

// Laravel Collection (melhor que array_*)
$users = User::all();

$ids = $users->map(fn($user) => $user->id);
$active = $users->filter(fn($user) => $user->is_active);
$total = $orders->sum('amount');

// Prós da Collection: encadeia, lazy, lê melhor
$result = $users
    ->filter(fn($u) => $u->is_active)
    ->map(fn($u) => $u->name)
    ->sort()
    ->values();
```

**Na entrevista:**
> "array_map transforma, array_filter filtra, array_reduce reduz a um valor. No Laravel eu prefiro Collection (map, filter, reduce) — lê melhor e encadeia."

---

## Ordenação de arrays

**Como funciona:**
```php
$numbers = [3, 1, 4, 1, 5, 9];

// sort — por valor (reindexa as chaves)
sort($numbers);  // [1, 1, 3, 4, 5, 9]

// rsort — por valor (ordem inversa)
rsort($numbers);  // [9, 5, 4, 3, 1, 1]

// asort — por valor (mantém as chaves)
$ages = ['João' => 25, 'Pedro' => 30, 'Maria' => 20];
asort($ages);  // ['Maria' => 20, 'João' => 25, 'Pedro' => 30]

// arsort — por valor, ordem inversa (mantém as chaves)
arsort($ages);  // ['Pedro' => 30, 'João' => 25, 'Maria' => 20]

// ksort — por chave
ksort($ages);  // ['João' => 25, 'Maria' => 20, 'Pedro' => 30]

// krsort — por chave, ordem inversa
krsort($ages);  // ['Pedro' => 30, 'Maria' => 20, 'João' => 25]

// usort — sort custom
$users = [
    ['name' => 'João', 'age' => 25],
    ['name' => 'Pedro', 'age' => 30],
    ['name' => 'Maria', 'age' => 20],
];

usort($users, fn($a, $b) => $a['age'] <=> $b['age']);
// [['Maria', 20], ['João', 25], ['Pedro', 30]]
```

**Quando usar:**
Ordenar dados por um critério.

**Exemplo prático:**
```php
// Ordenar produtos por preço
usort($products, fn($a, $b) => $a['price'] <=> $b['price']);

// Ordem decrescente
usort($products, fn($a, $b) => $b['price'] <=> $a['price']);

// Ordenação em mais de um nível
usort($products, function($a, $b) {
    // Primeiro por categoria, depois por preço
    $categoryCompare = $a['category'] <=> $b['category'];
    if ($categoryCompare !== 0) {
        return $categoryCompare;
    }
    return $a['price'] <=> $b['price'];
});

// Laravel Collection
$sorted = $products->sortBy('price');
$sorted = $products->sortByDesc('price');
$sorted = $products->sortBy([
    ['category', 'asc'],
    ['price', 'asc'],
]);
```

**Na entrevista:**
> "sort ordena por valor e reindexa. asort ordena por valor e guarda as chaves. ksort ordena por chave. usort é sort custom com callback. No Laravel eu uso Collection::sortBy()."

---

## array_merge, array_combine, array_diff

**Como funciona:**
```php
// array_merge — junta arrays
$arr1 = [1, 2, 3];
$arr2 = [4, 5, 6];
$merged = array_merge($arr1, $arr2);  // [1, 2, 3, 4, 5, 6]

// Associativo — o último valor sobrescreve
$user1 = ['name' => 'João', 'age' => 25];
$user2 = ['age' => 30, 'email' => 'joao@email.com'];
$merged = array_merge($user1, $user2);
// ['name' => 'João', 'age' => 30, 'email' => 'joao@email.com']

// Spread operator (PHP 7.4+)
$merged = [...$arr1, ...$arr2];

// array_combine — monta o array a partir de chaves e valores
$keys = ['name', 'age', 'email'];
$values = ['João', 25, 'joao@email.com'];
$user = array_combine($keys, $values);
// ['name' => 'João', 'age' => 25, 'email' => 'joao@email.com']

// array_diff — diferença entre arrays (por valor)
$arr1 = [1, 2, 3, 4, 5];
$arr2 = [3, 4, 5, 6, 7];
$diff = array_diff($arr1, $arr2);  // [1, 2]

// array_intersect — interseção
$intersect = array_intersect($arr1, $arr2);  // [3, 4, 5]
```

**Quando usar:**
Juntar, comparar, montar arrays.

**Exemplo prático:**
```php
// Merge dos parâmetros do request com defaults
$defaults = ['per_page' => 20, 'sort' => 'created_at', 'order' => 'desc'];
$params = array_merge($defaults, $request->only(['per_page', 'sort', 'order']));

// Spread no merge (mais rápido)
$params = [...$defaults, ...$request->only(['per_page', 'sort', 'order'])];

// Montar array a partir do resultado da query
$users = User::all();
$ids = $users->pluck('id')->toArray();
$names = $users->pluck('name')->toArray();
$usersById = array_combine($ids, $names);
// [1 => 'João', 2 => 'Pedro', ...]

// Achar roles removidas do usuário
$oldRoles = [1, 2, 3, 4];
$newRoles = [2, 3, 5];
$removed = array_diff($oldRoles, $newRoles);  // [1, 4]
$added = array_diff($newRoles, $oldRoles);    // [5]

// Sincronizar roles
$user->roles()->sync($newRoles);  // O Laravel acha o diff sozinho
```

**Na entrevista:**
> "array_merge junta arrays (o último valor sobrescreve). array_combine monta o array a partir de chaves e valores. array_diff é a diferença, array_intersect é a interseção. Spread (...) é mais rápido que array_merge."

---

## in_array, array_key_exists, array_search

**Como funciona:**
```php
$numbers = [1, 2, 3, 4, 5];
$user = ['name' => 'João', 'age' => 25];

// in_array — checa o valor (NÃO estrito por padrão)
var_dump(in_array(3, $numbers));     // true
var_dump(in_array('3', $numbers));   // true (converte '3' → 3)

// Checagem estrita (terceiro parâmetro)
var_dump(in_array('3', $numbers, true));  // false

// array_key_exists — checa a chave
var_dump(array_key_exists('name', $user));  // true
var_dump(array_key_exists('email', $user)); // false

// isset() vs array_key_exists()
$arr = ['key' => null];
var_dump(isset($arr['key']));              // false (valor null)
var_dump(array_key_exists('key', $arr));   // true (a chave existe)

// array_search — acha a chave pelo valor
$fruits = ['apple', 'banana', 'orange'];
$key = array_search('banana', $fruits);  // 1
$key = array_search('grape', $fruits);   // false (não achou)
```

**Quando usar:**
Checar se o valor existe, se a chave existe, achar a posição.

**Exemplo prático:**
```php
// Checar roles do usuário
$userRoles = ['editor', 'author'];

if (in_array('admin', $userRoles, true)) {
    // Usuário é admin
}

// Laravel Collection
if ($user->roles->contains('name', 'admin')) {
    // ...
}

// Checar se o parâmetro veio
if (array_key_exists('status', $request->query())) {
    $status = $request->query('status');
}

// Laravel
if ($request->has('status')) {
    $status = $request->input('status');
}

// Achar o índice do elemento
$statuses = ['pending', 'paid', 'shipped', 'delivered'];
$currentIndex = array_search($order->status, $statuses);
$nextStatus = $statuses[$currentIndex + 1] ?? null;
```

**Na entrevista:**
> "in_array checa o valor (por padrão não é estrito — precisa do terceiro parâmetro true). array_key_exists checa a chave (funciona com null). array_search devolve a chave pelo valor. No Laravel eu uso Collection::contains()."

---

## array_column, array_unique, array_flip

**Como funciona:**
```php
$users = [
    ['id' => 1, 'name' => 'João', 'age' => 25],
    ['id' => 2, 'name' => 'Pedro', 'age' => 30],
    ['id' => 3, 'name' => 'Maria', 'age' => 20],
];

// array_column — extrai a coluna
$names = array_column($users, 'name');  // ['João', 'Pedro', 'Maria']
$ids = array_column($users, 'id');      // [1, 2, 3]

// Com índice de outra coluna
$usersById = array_column($users, null, 'id');
// [1 => [...], 2 => [...], 3 => [...]]

$nameById = array_column($users, 'name', 'id');
// [1 => 'João', 2 => 'Pedro', 3 => 'Maria']

// array_unique — tira duplicatas
$numbers = [1, 2, 2, 3, 3, 3, 4];
$unique = array_unique($numbers);  // [1, 2, 3, 4] (as chaves ficam!)

// array_flip — troca chave e valor
$map = ['a' => 'apple', 'b' => 'banana'];
$flipped = array_flip($map);  // ['apple' => 'a', 'banana' => 'b']
```

**Quando usar:**
- `array_column` — extrair campos de um array de objetos/arrays
- `array_unique` — tirar duplicatas
- `array_flip` — montar o reverse map (valor → chave)

**Exemplo prático:**
```php
// Pegar o ID de todos os usuários
$users = User::all()->toArray();
$ids = array_column($users, 'id');

// Laravel Collection (melhor)
$ids = User::all()->pluck('id')->toArray();

// Indexar por ID
$usersById = array_column($users, null, 'id');

// Laravel Collection
$usersById = User::all()->keyBy('id');

// Tirar categorias duplicadas
$categories = [1, 2, 2, 3, 3, 3];
$unique = array_unique($categories);

// Laravel Collection
$unique = collect($categories)->unique()->values();

// Reverse map para busca rápida
$statusNames = ['pending' => 'Aguardando', 'paid' => 'Pago'];
$nameToStatus = array_flip($statusNames);
// ['Aguardando' => 'pending', 'Pago' => 'paid']
```

**Na entrevista:**
> "array_column extrai uma coluna de um array de arrays. array_unique tira duplicatas (as chaves ficam). array_flip troca chave e valor. No Laravel eu uso os métodos de Collection (pluck, unique, keyBy)."

---

## Desestruturação de arrays (PHP 7.1+)

**Como funciona:**
```php
// Array indexado
$user = ['João', 'joao@email.com', 25];

// Jeito antigo
$name = $user[0];
$email = $user[1];
$age = $user[2];

// Desestruturação (PHP 7.1+)
[$name, $email, $age] = $user;

// Pular elementos
[$name, , $age] = $user;  // Pulou $email

// Array associativo (PHP 7.1+)
$user = ['name' => 'João', 'email' => 'joao@email.com', 'age' => 25];
['name' => $name, 'age' => $age] = $user;

// No foreach
$users = [
    ['name' => 'João', 'age' => 25],
    ['name' => 'Pedro', 'age' => 30],
];

foreach ($users as ['name' => $name, 'age' => $age]) {
    echo "$name: $age anos";
}

// Nos parâmetros da função
function greet(['name' => $name, 'age' => $age]): string
{
    return "Olá, $name! Você tem $age anos.";
}

echo greet(['name' => 'João', 'age' => 25]);
```

**Quando usar:**
Desempacotar arrays que devolvem vários valores.

**Exemplo prático:**
```php
// Pegar min/max
$prices = [100, 200, 150, 300];
[$min, $max] = [min($prices), max($prices)];

// Paginação
function paginate(int $page, int $perPage): array
{
    $offset = ($page - 1) * $perPage;
    $total = User::count();

    return [
        'data' => User::skip($offset)->take($perPage)->get(),
        'meta' => ['total' => $total, 'page' => $page],
    ];
}

['data' => $users, 'meta' => $meta] = paginate(1, 20);

// Laravel
[$users, $total] = [User::paginate(20)->items(), User::count()];

// Coordenadas
$point = [-23.5505, -46.6333];  // São Paulo
[$lat, $lng] = $point;
```

**Na entrevista:**
> "Desestruturação (PHP 7.1+) desempacota o array em variáveis: [$a, $b] = [1, 2]. Funciona com indexado e associativo. Uso para desempacotar o retorno da função e no foreach."

---

## Spread Operator (PHP 7.4+)

**Como funciona:**
```php
// Desempacotar arrays
$arr1 = [1, 2, 3];
$arr2 = [4, 5, 6];

// array_merge
$merged = array_merge($arr1, $arr2);  // [1, 2, 3, 4, 5, 6]

// Spread (mais rápido e mais curto)
$merged = [...$arr1, ...$arr2];  // [1, 2, 3, 4, 5, 6]

// Inserir elementos
$extended = [...$arr1, 99, ...$arr2];  // [1, 2, 3, 99, 4, 5, 6]

// Arrays associativos (PHP 8.1+)
$user = ['name' => 'João', 'age' => 25];
$extra = ['city' => 'São Paulo'];
$merged = [...$user, ...$extra];
// ['name' => 'João', 'age' => 25, 'city' => 'São Paulo']

// Funções variadic
function sum(int ...$numbers): int
{
    return array_sum($numbers);
}

$numbers = [1, 2, 3, 4, 5];
echo sum(...$numbers);  // 15
```

**Quando usar:**
Juntar arrays (mais rápido que array_merge), passar lista de argumentos.

**Exemplo prático:**
```php
// Merge de configs
$defaults = ['timeout' => 30, 'retries' => 3];
$custom = ['timeout' => 60];
$config = [...$defaults, ...$custom];  // timeout fica 60

// Adicionar middleware
$baseMiddleware = ['auth', 'verified'];
$adminMiddleware = [...$baseMiddleware, 'admin'];

Route::middleware($adminMiddleware)->group(function() {
    // ...
});

// Passar parâmetros
$params = ['status' => 'active', 'department_id' => 5];
$users = User::where(...$params)->get();  // ❌ Não funciona assim

// Certo
$users = User::where($params)->get();
// ou
foreach ($params as $key => $value) {
    $query->where($key, $value);
}
```

**Na entrevista:**
> "Spread (...) desempacota o array (PHP 7.4+). É mais rápido que array_merge. PHP 8.1 passou a aceitar array associativo. Uso para juntar arrays e passar lista de argumentos."

---

## Recapitulando

**O essencial:**
- Criação: `[1, 2, 3]` ou `['key' => 'value']`
- Adicionar: `[]` (final), `array_push`, `array_unshift`
- Remover: `array_pop`, `array_shift`, `unset`
- Funções de ordem superior: `array_map`, `array_filter`, `array_reduce`
- Ordenação: `sort`, `asort`, `ksort`, `usort`
- Merge: `array_merge`, spread `[...$a, ...$b]`
- Busca: `in_array`, `array_key_exists`, `array_search`
- Utilitários: `array_column`, `array_unique`, `array_flip`
- Desestruturação: `[$a, $b] = [1, 2]` (PHP 7.1+)
- Spread: `...$array` (PHP 7.4+)

**Importante na entrevista:**
- Array em PHP é hash table (aceita índice e chave)
- `in_array` por padrão não é estrito (precisa do terceiro parâmetro `true`)
- `unset()` não reindexa — precisa de `array_values()`
- `array_key_exists()` funciona com `null`, `isset()` não
- Spread `...` é mais rápido que `array_merge`
- No Laravel eu prefiro os métodos de Collection no lugar de array_*

---

## Exercícios práticos

### Exercício 1: in_array com comparação não estrita
**Enunciado:** Ache e corrija o problema de segurança na checagem de roles.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ PERIGOSO (comparação não estrita)
function hasRole(User $user, string $role): bool
{
    $userRoles = $user->roles->pluck('name')->toArray();
    return in_array($role, $userRoles);  // Inseguro!
}

// O problema:
$userRoles = ['editor', 'author'];

var_dump(in_array('admin', $userRoles));      // false ✅
var_dump(in_array('0', $userRoles));          // false ✅
var_dump(in_array(0, $userRoles));            // TRUE! ❌ (0 == 'editor' = false, mas...)
var_dump(in_array(true, $userRoles));         // TRUE! ❌ (true == 'editor' = true)

// ✅ CERTO (comparação estrita)
function hasRoleCorrect(User $user, string $role): bool
{
    $userRoles = $user->roles->pluck('name')->toArray();
    return in_array($role, $userRoles, true);  // Comparação estrita
}

// Ou Laravel Collection
function hasRoleCollection(User $user, string $role): bool
{
    return $user->roles->contains('name', $role);  // Usa ===
}

// Testes
$admin = new User(['roles' => ['admin', 'editor']]);
$guest = new User(['roles' => ['guest']]);

var_dump(hasRoleCorrect($admin, 'admin'));    // true
var_dump(hasRoleCorrect($admin, 'moderator')); // false
var_dump(hasRoleCorrect($admin, '0'));         // false
var_dump(hasRoleCorrect($admin, 0));           // TypeError (strict_types)
```

**Pontos-chave:**
- `in_array()` por padrão usa `==` (não estrito)
- O terceiro parâmetro `true` liga a comparação estrita `===`
- Sem comparação estrita: `0` e `true` podem passar na checagem
- No Laravel, os métodos de Collection usam comparação estrita
</details>

### Exercício 2: array_key_exists vs isset
**Enunciado:** Explique a diferença entre array_key_exists e isset.

<details>
<summary>Solução</summary>

```php
<?php

$data = [
    'name' => 'João',
    'age' => 0,
    'email' => null,
    'active' => false,
];

// isset() — checa se existe E !== null
var_dump(isset($data['name']));     // true
var_dump(isset($data['age']));      // true (0 não é null)
var_dump(isset($data['email']));    // false (null)
var_dump(isset($data['active']));   // true (false não é null)
var_dump(isset($data['missing']));  // false (não existe)

// array_key_exists() — checa só se a chave existe
var_dump(array_key_exists('name', $data));     // true
var_dump(array_key_exists('age', $data));      // true
var_dump(array_key_exists('email', $data));    // true (a chave está lá!)
var_dump(array_key_exists('active', $data));   // true
var_dump(array_key_exists('missing', $data));  // false

// Exemplo prático
function getConfig(array $config, string $key, mixed $default = null): mixed
{
    // ❌ ERRADO
    if (isset($config[$key])) {
        return $config[$key];
    }
    // Se $config[$key] = null, devolve o default (surpresa!)

    // ✅ CERTO
    if (array_key_exists($key, $config)) {
        return $config[$key];
    }

    return $default;
}

$config = [
    'timeout' => null,  // Explicitamente null
    'retries' => 3,
];

echo getConfig($config, 'timeout', 30);  // null (não 30!)
echo getConfig($config, 'missing', 30);  // 30

// Helper do Laravel (usa array_key_exists)
$timeout = data_get($config, 'timeout', 30);  // null
$missing = data_get($config, 'missing', 30);  // 30
```

**Pontos-chave:**
- `isset()` devolve false para valor null
- `array_key_exists()` checa só se a chave existe
- Em config com null, use `array_key_exists()`
- O `data_get()` do Laravel usa `array_key_exists()`
</details>

### Exercício 3: Spread vs array_merge
**Enunciado:** Compare performance e comportamento de spread e array_merge.

<details>
<summary>Solução</summary>

```php
<?php

// Arrays indexados
$arr1 = [1, 2, 3];
$arr2 = [4, 5, 6];

// array_merge
$merged1 = array_merge($arr1, $arr2);  // [1, 2, 3, 4, 5, 6]

// Spread (mais rápido)
$merged2 = [...$arr1, ...$arr2];       // [1, 2, 3, 4, 5, 6]

// Arrays associativos
$user1 = ['name' => 'João', 'age' => 25];
$user2 = ['age' => 30, 'city' => 'São Paulo'];

// array_merge (o último valor sobrescreve)
$merged3 = array_merge($user1, $user2);
// ['name' => 'João', 'age' => 30, 'city' => 'São Paulo']

// Spread (PHP 8.1+)
$merged4 = [...$user1, ...$user2];
// ['name' => 'João', 'age' => 30, 'city' => 'São Paulo']

// Spread com chaves numéricas
$arr1 = [0 => 'a', 1 => 'b'];
$arr2 = [0 => 'c', 1 => 'd'];

array_merge($arr1, $arr2);  // [0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd'] (reindexa)
[...$arr1, ...$arr2];       // [0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd'] (também reindexa)

// Spread com chaves string (mantém)
$arr1 = ['a' => 1, 'b' => 2];
$arr2 = ['c' => 3];

[...$arr1, ...$arr2];  // ['a' => 1, 'b' => 2, 'c' => 3]

// Exemplo prático (Laravel)
class UserController
{
    public function store(Request $request)
    {
        $defaults = [
            'is_active' => true,
            'email_verified' => false,
        ];

        // Spread (mais rápido e mais curto)
        $data = [...$defaults, ...$request->validated()];

        // array_merge (jeito antigo)
        $data = array_merge($defaults, $request->validated());

        return User::create($data);
    }
}

// Benchmark (aproximado)
// array_merge: ~1.5x mais lento
// spread: ~1.5x mais rápido
```

**Pontos-chave:**
- Spread `...` é mais rápido que `array_merge`
- Spread em array associativo pede PHP 8.1+
- Os dois reindexam chave numérica
- O último valor sobrescreve os anteriores
- Spread é mais curto e lê melhor
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
