# 3.5 Geradores (Generators)

## Resumo

> **Geradores** — funções com `yield`, devolvem um iterator sem carregar todos os dados na memória.
>
> **O essencial:** `yield` no lugar de `return`, `yield from` para delegar, métodos: send(), getReturn().
>
> **Laravel:** `Eloquent::cursor()` usa geradores para economizar memória.

---

## Conteúdo

- [O que é um gerador](#o-que-é-um-gerador)
- [A palavra-chave yield](#a-palavra-chave-yield)
- [Métodos de Generator](#métodos-de-generator)
- [yield from (PHP 7.0+)](#yield-from-php-70)
- [Geradores vs arrays](#geradores-vs-arrays)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é um gerador

**O que é:**
Função que devolve um iterator via `yield` em vez de `return`. Não carrega todos os dados na memória de uma vez.

**Como funciona:**
```php
// Função comum (carrega tudo na memória)
function getNumbers(): array
{
    $result = [];
    for ($i = 1; $i <= 1000000; $i++) {
        $result[] = $i;
    }
    return $result;  // 1M de números na memória
}

$numbers = getNumbers();  // Vai ocupar muita memória

// Gerador (um item por vez)
function getNumbersGenerator(): Generator
{
    for ($i = 1; $i <= 1000000; $i++) {
        yield $i;  // Devolve um por vez
    }
}

foreach (getNumbersGenerator() as $number) {
    echo $number;  // Processa um por vez (economia de memória)
}

// O gerador devolve um objeto Generator
$gen = getNumbersGenerator();
var_dump($gen);  // object(Generator)
```

**Quando usar:**
Consulta grande no banco, arquivo, API (economia de memória).

**Exemplo prático:**
```php
// Processar um CSV grande
function readCsv(string $filePath): Generator
{
    $handle = fopen($filePath, 'r');

    try {
        while (($line = fgets($handle)) !== false) {
            yield str_getcsv($line);  // Uma linha por vez
        }
    } finally {
        fclose($handle);
    }
}

// Processamento (não carrega o arquivo inteiro)
foreach (readCsv('large-file.csv') as $row) {
    // Processar a linha
    processRow($row);
}

// Eloquent cursor() usa geradores
foreach (User::cursor() as $user) {
    // Carrega um usuário por vez do banco
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
            yield $item;  // Entrega um item por vez
        }

        $page++;
    } while (!empty($data));
}

foreach (fetchAllPages('/api/products') as $product) {
    // Processa um por vez
}
```

**Na entrevista:**
> "Gerador devolve um iterator via yield. Não carrega tudo na memória, entrega um item por vez. Economia de memória em consulta grande. Eloquent::cursor() usa geradores."

---

## A palavra-chave yield

**O que é:**
Devolve um valor e pausa a execução da função.

**Como funciona:**
```php
function simpleGenerator(): Generator
{
    echo "Início\n";
    yield 1;
    echo "Depois do primeiro yield\n";
    yield 2;
    echo "Depois do segundo yield\n";
    yield 3;
    echo "Fim\n";
}

foreach (simpleGenerator() as $value) {
    echo "Valor: {$value}\n";
}

// Saída:
// Início
// Valor: 1
// Depois do primeiro yield
// Valor: 2
// Depois do segundo yield
// Valor: 3
// Fim

// yield com chave
function getKeyValue(): Generator
{
    yield 'name' => 'João';
    yield 'age' => 25;
    yield 'email' => 'joao@email.com';
}

foreach (getKeyValue() as $key => $value) {
    echo "{$key}: {$value}\n";
}
// name: João
// age: 25
// email: joao@email.com
```

**Quando usar:**
Cálculo sob demanda (lazy), sequências infinitas.

**Exemplo prático:**
```php
// Geração de IDs
function generateIds(): Generator
{
    $id = 1;
    while (true) {
        yield $id++;
    }
}

$idGenerator = generateIds();
echo $idGenerator->current();  // 1
$idGenerator->next();
echo $idGenerator->current();  // 2

// Sequência de Fibonacci
function fibonacci(): Generator
{
    $a = 0;
    $b = 1;

    while (true) {
        yield $a;
        [$a, $b] = [$b, $a + $b];
    }
}

$fib = fibonacci();
for ($i = 0; $i < 10; $i++) {
    echo $fib->current() . " ";
    $fib->next();
}
// 0 1 1 2 3 5 8 13 21 34

// Leitura linha a linha do log
function tailLog(string $filePath): Generator
{
    $handle = fopen($filePath, 'r');

    // Ir para o final
    fseek($handle, 0, SEEK_END);

    while (true) {
        $line = fgets($handle);

        if ($line !== false) {
            yield $line;
        } else {
            usleep(100000);  // delay de 100ms
        }
    }
}

foreach (tailLog('/var/log/app.log') as $line) {
    echo $line;  // Imprime linhas novas conforme aparecem
}
```

**Na entrevista:**
> "yield devolve o valor e pausa a função. Na próxima iteração, continua de onde parou. yield key => value para dado associativo. Gerador infinito para sequência."

---

## Métodos de Generator

**O que é:**
Métodos do objeto Generator para controlar a iteração.

**Como funciona:**
```php
function simpleGenerator(): Generator
{
    yield 1;
    yield 2;
    yield 3;
}

$gen = simpleGenerator();

// current() — valor atual
echo $gen->current();  // 1

// next() — avança para o próximo
$gen->next();
echo $gen->current();  // 2

// key() — chave atual
echo $gen->key();  // 1

// valid() — ainda tem elementos
var_dump($gen->valid());  // true

// rewind() — reinicia (só funciona em alguns geradores)
$gen->rewind();
echo $gen->current();  // 1

// send() — envia um valor para o gerador
function echoGenerator(): Generator
{
    while (true) {
        $value = yield;  // Recebe o valor
        echo "Recebido: {$value}\n";
    }
}

$gen = echoGenerator();
$gen->send(null);  // Primeira chamada com null
$gen->send('Olá');  // Recebido: Olá
$gen->send('Mundo');  // Recebido: Mundo

// getReturn() — pega o valor do return (PHP 7.0+)
function generatorWithReturn(): Generator
{
    yield 1;
    yield 2;
    return 'Concluído';
}

$gen = generatorWithReturn();
foreach ($gen as $value) {
    echo $value;  // 1, 2
}
echo $gen->getReturn();  // "Concluído"
```

**Quando usar:**
Controlar o gerador, comunicação nos dois sentidos.

**Exemplo prático:**
```php
// Processamento com controle
function processItems(array $items): Generator
{
    foreach ($items as $item) {
        $result = yield $item;  // Recebe o resultado do processamento

        if ($result === 'skip') {
            continue;
        }

        if ($result === 'stop') {
            return 'Parado';
        }
    }

    return 'Concluído';
}

$gen = processItems([1, 2, 3, 4, 5]);
$gen->send(null);  // Primeira chamada

foreach ($gen as $item) {
    if ($item === 3) {
        $gen->send('skip');  // Pula o 3
    } elseif ($item === 5) {
        $gen->send('stop');  // Para no 5
        break;
    } else {
        $gen->send('continue');
    }
}

echo $gen->getReturn();  // "Parado"

// Pausa e retomada
class BatchProcessor
{
    private Generator $generator;

    public function start(array $items): void
    {
        $this->generator = $this->processItems($items);
        $this->generator->rewind();
    }

    public function processNext(): bool
    {
        if (!$this->generator->valid()) {
            return false;
        }

        $item = $this->generator->current();
        $this->process($item);
        $this->generator->next();

        return $this->generator->valid();
    }

    private function processItems(array $items): Generator
    {
        foreach ($items as $item) {
            yield $item;
        }
    }

    private function process($item): void
    {
        // Processa o item
    }
}

// Uso
$processor = new BatchProcessor();
$processor->start($items);

while ($processor->processNext()) {
    // Processa um item
    // Dá para interromper e continuar depois
}
```

**Na entrevista:**
> "Métodos do Generator: current(), next(), key(), valid(), send(), getReturn(). send() manda um valor para o gerador (comunicação nos dois sentidos). getReturn() pega o return depois que termina."

---

## yield from (PHP 7.0+)

**O que é:**
Delegação para outro gerador ou array.

**Como funciona:**
```php
// Sem yield from
function numbers(): Generator
{
    yield 1;
    yield 2;
    yield 3;
}

function letters(): Generator
{
    yield 'a';
    yield 'b';
    yield 'c';
}

function combined(): Generator
{
    foreach (numbers() as $number) {
        yield $number;
    }

    foreach (letters() as $letter) {
        yield $letter;
    }
}

// Com yield from (mais curto)
function combined(): Generator
{
    yield from numbers();
    yield from letters();
}

foreach (combined() as $value) {
    echo $value;  // 1, 2, 3, a, b, c
}

// yield from com array
function generator(): Generator
{
    yield from [1, 2, 3];
    yield 4;
    yield from range(5, 7);
}

foreach (generator() as $value) {
    echo $value;  // 1, 2, 3, 4, 5, 6, 7
}
```

**Quando usar:**
Compor geradores, delegar.

**Exemplo prático:**
```php
// Percorrer diretório de forma recursiva
function scanDirectory(string $dir): Generator
{
    $items = scandir($dir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (is_file($path)) {
            yield $path;
        } elseif (is_dir($path)) {
            yield from scanDirectory($path);  // Recursão
        }
    }
}

foreach (scanDirectory('/app') as $file) {
    echo $file . "\n";
}

// Juntar dados de fontes diferentes
function fetchUsersFromDb(): Generator
{
    foreach (User::cursor() as $user) {
        yield $user;
    }
}

function fetchUsersFromApi(): Generator
{
    $response = Http::get('/api/users');

    foreach ($response->json() as $userData) {
        yield User::make($userData);
    }
}

function getAllUsers(): Generator
{
    yield from fetchUsersFromDb();
    yield from fetchUsersFromApi();
}

foreach (getAllUsers() as $user) {
    // Processa usuários do banco e da API
}

// Processamento em chunks
function processInChunks(array $items, int $chunkSize): Generator
{
    $chunks = array_chunk($items, $chunkSize);

    foreach ($chunks as $chunk) {
        yield from $this->processChunk($chunk);
    }
}

function processChunk(array $chunk): Generator
{
    foreach ($chunk as $item) {
        yield $this->process($item);
    }
}
```

**Na entrevista:**
> "yield from delega para outro gerador ou array. Mais curto que foreach + yield. Uso para compor geradores, percorrer em recursão, juntar fontes de dados."

---

## Geradores vs arrays

**Comparação:**

| array | Gerador |
|--------|-----------|
| Tudo na memória | Um item por vez |
| return devolve array | yield devolve o item |
| Acesso rápido por índice | Só sequencial |
| Dá para iterar várias vezes | Itera uma vez só* |
| array_map, array_filter | Só foreach |

**Quando usar gerador:**
- Dados grandes (banco, arquivos)
- Sequências infinitas
- Cálculo sob demanda (lazy)

**Quando usar array:**
- Dados pequenos
- Precisa de acesso rápido
- Precisa iterar várias vezes

**Exemplo prático:**
```php
// RUIM: o resultado inteiro na memória
function getAllUsers(): array
{
    return User::all()->toArray();  // 100k+ registros na memória
}

$users = getAllUsers();
foreach ($users as $user) {
    processUser($user);
}

// BOM: gerador (economia de memória)
function getAllUsers(): Generator
{
    foreach (User::cursor() as $user) {
        yield $user;  // Um por vez
    }
}

foreach (getAllUsers() as $user) {
    processUser($user);
}

// Quando o array faz sentido
$numbers = [1, 2, 3, 4, 5];  // Array pequeno — OK

// Dá para iterar várias vezes
foreach ($numbers as $n) { /* ... */ }
foreach ($numbers as $n) { /* ... */ }  // ✅ OK

// Gerador não dá
$gen = getNumbers();
foreach ($gen as $n) { /* ... */ }
foreach ($gen as $n) { /* ... */ }  // ❌ Vazio (já iterou)

// Se precisar de novo — cria de novo
foreach (getNumbers() as $n) { /* ... */ }
foreach (getNumbers() as $n) { /* ... */ }  // ✅ OK (gerador novo)

// Ou converte para array (mas perde a economia de memória)
$gen = getNumbers();
$array = iterator_to_array($gen);
foreach ($array as $n) { /* ... */ }
foreach ($array as $n) { /* ... */ }  // ✅ OK
```

**Na entrevista:**
> "Gerador economiza memória (um item por vez), array carrega tudo de uma vez. Gerador para dado grande, array para dado pequeno. Gerador itera uma vez só (precisa criar de novo). iterator_to_array() converte para array."

---

## Recapitulando

**O essencial:**
- `yield` devolve o valor e pausa a função
- Gerador não carrega tudo na memória (um item por vez)
- `yield key => value` para dado associativo
- Métodos: current(), next(), key(), valid(), send(), getReturn()
- `yield from` delega para outro gerador
- `Generator` é o objeto (volta da função com yield)

**Gerador vs array:**
- Gerador — economia de memória, acesso sequencial
- array — tudo na memória, acesso rápido por índice

**Importante na entrevista:**
- Eloquent::cursor() usa geradores
- Gerador itera uma vez só (cria de novo se precisar repetir)
- yield from para compor geradores
- send() para comunicação nos dois sentidos
- Geradores infinitos (while true + yield)
- Economia de memória em arquivo grande, banco, API

---

## Exercícios práticos

### Exercício 1: Criar um gerador para processar CSV grande

**Enunciado:** Escreva um gerador que lê um CSV grande linha a linha e devolve os dados processados.

<details>
<summary>Solução</summary>

```php
<?php

function readCsvGenerator(string $filePath, bool $hasHeader = true): Generator
{
    if (!file_exists($filePath)) {
        throw new \RuntimeException("Arquivo {$filePath} não encontrado");
    }

    $handle = fopen($filePath, 'r');

    if ($handle === false) {
        throw new \RuntimeException("Não foi possível abrir o arquivo {$filePath}");
    }

    try {
        $headers = null;

        if ($hasHeader) {
            $headers = fgetcsv($handle);
        }

        $lineNumber = $hasHeader ? 1 : 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($headers) {
                // Monta um array associativo
                if (count($row) !== count($headers)) {
                    throw new \RuntimeException("Linha inválida na linha {$lineNumber}");
                }

                yield $lineNumber => array_combine($headers, $row);
            } else {
                yield $lineNumber => $row;
            }
        }
    } finally {
        fclose($handle);
    }
}

// Uso
// users.csv:
// name,email,age
// João,joao@email.com,25
// Pedro,pedro@email.com,30

foreach (readCsvGenerator('users.csv') as $lineNumber => $user) {
    echo "Linha {$lineNumber}: {$user['name']} ({$user['email']})\n";

    // Processa uma linha por vez (não carrega o arquivo inteiro na memória)
    User::create($user);
}

// Ou com filtro
function processCsvWithFilter(string $filePath): Generator
{
    foreach (readCsvGenerator($filePath) as $lineNumber => $row) {
        // Filtro: só maiores de idade
        if (isset($row['age']) && (int) $row['age'] >= 18) {
            yield $lineNumber => $row;
        }
    }
}

foreach (processCsvWithFilter('users.csv') as $lineNumber => $user) {
    echo "Usuário adulto: {$user['name']}\n";
}

// Command Laravel para importar
class ImportUsersCommand extends Command
{
    public function handle(): void
    {
        $file = $this->argument('file');

        $this->output->progressStart();

        foreach (readCsvGenerator($file) as $lineNumber => $userData) {
            try {
                User::create($userData);
                $this->output->progressAdvance();
            } catch (\Exception $e) {
                $this->error("Erro na linha {$lineNumber}: {$e->getMessage()}");
            }
        }

        $this->output->progressFinish();
        $this->info('Importação concluída');
    }
}
```
</details>

### Exercício 2: Implementar paginação de API com gerador

**Enunciado:** Crie um gerador que busca todas as páginas da API (paginação automática).

<details>
<summary>Solução</summary>

```php
<?php

use Illuminate\Support\Facades\Http;

function fetchAllPagesGenerator(string $url, array $queryParams = []): Generator
{
    $page = 1;
    $perPage = 100;

    do {
        $response = Http::get($url, [
            ...$queryParams,
            'page' => $page,
            'per_page' => $perPage,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Falha na requisição da API: {$response->status()}");
        }

        $data = $response->json();
        $items = $data['data'] ?? $data;

        if (empty($items)) {
            break;
        }

        foreach ($items as $item) {
            yield $item;  // Entrega um item por vez
        }

        $page++;

        // Checagem da última página
        $hasMore = isset($data['meta']['current_page']) && isset($data['meta']['last_page'])
            ? $data['meta']['current_page'] < $data['meta']['last_page']
            : !empty($items);

    } while ($hasMore);
}

// Uso
foreach (fetchAllPagesGenerator('https://api.example.com/users') as $user) {
    // Processa um usuário por vez
    // Não precisa carregar todas as páginas de uma vez
    echo "Processando usuário: {$user['name']}\n";

    LocalUser::updateOrCreate(
        ['external_id' => $user['id']],
        ['name' => $user['name'], 'email' => $user['email']]
    );
}

// Com filtro
function fetchActiveUsersGenerator(string $url): Generator
{
    foreach (fetchAllPagesGenerator($url) as $user) {
        if ($user['is_active'] ?? false) {
            yield $user;
        }
    }
}

// Job Laravel para sincronizar
class SyncUsersFromApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        $count = 0;

        foreach (fetchAllPagesGenerator('https://api.example.com/users') as $userData) {
            User::updateOrCreate(
                ['external_id' => $userData['id']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                ]
            );

            $count++;

            // Para não estourar a memória, pausa a cada 1000 registros
            if ($count % 1000 === 0) {
                sleep(1);
            }
        }

        Log::info("Sincronizados {$count} usuários da API");
    }
}
```
</details>

### Exercício 3: Criar um gerador para percorrer a árvore de categorias

**Enunciado:** Implemente um gerador recursivo para percorrer a árvore de categorias (estruturas aninhadas).

<details>
<summary>Solução</summary>

```php
<?php

// Model
class Category extends Model
{
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
}

// Gerador para percorrer a árvore (depth-first)
function traverseCategoryTreeGenerator(Category $category, int $depth = 0): Generator
{
    // Entrega a categoria atual
    yield ['category' => $category, 'depth' => $depth];

    // Percorre os filhos em recursão
    foreach ($category->children as $child) {
        yield from traverseCategoryTreeGenerator($child, $depth + 1);
    }
}

// Uso
$rootCategory = Category::with('children.children.children')->find(1);

foreach (traverseCategoryTreeGenerator($rootCategory) as $item) {
    $indent = str_repeat('  ', $item['depth']);
    echo "{$indent}- {$item['category']->name}\n";
}

// Saída:
// - Electronics
//   - Phones
//     - iPhone
//     - Samsung
//   - Laptops
//     - MacBook
//     - Dell

// Gerador com filtro (só ativas)
function traverseActiveCategoriesGenerator(Category $category, int $depth = 0): Generator
{
    if (!$category->is_active) {
        return;  // Pula as inativas
    }

    yield ['category' => $category, 'depth' => $depth];

    foreach ($category->children as $child) {
        yield from traverseActiveCategoriesGenerator($child, $depth + 1);
    }
}

// Junta todos os IDs das categorias num array plano
function getAllCategoryIds(Category $category): array
{
    $ids = [];

    foreach (traverseCategoryTreeGenerator($category) as $item) {
        $ids[] = $item['category']->id;
    }

    return $ids;
}

// Uso: apagar com todos os filhos
$category = Category::find(1);
$ids = getAllCategoryIds($category);
Category::whereIn('id', $ids)->delete();

// Gerador de breadcrumbs (caminho até a categoria)
function getCategoryPathGenerator(Category $category): Generator
{
    $current = $category;

    while ($current !== null) {
        yield $current;
        $current = $current->parent;
    }
}

// Breadcrumbs
$category = Category::find(5);
$path = iterator_to_array(getCategoryPathGenerator($category));
$breadcrumbs = array_reverse($path);

foreach ($breadcrumbs as $item) {
    echo "{$item->name} > ";
}
// Electronics > Phones > iPhone >
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
