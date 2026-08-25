# 1.4 Estruturas de controle

> **TL;DR**
> Prefira guard clauses (return cedo) em vez de if aninhado. match em vez de switch (PHP 8.0). while quando você não sabe quantas iterações, for para contador, foreach para arrays. Depois de foreach com &$var, sempre unset($var). break sai do loop, continue pula a iteração. Evite exit/die em controllers. Sempre use declare(strict_types=1).

## Conteúdo

- [if, elseif, else](#if-elseif-else)
- [switch vs match](#switch-vs-match)
- [while, do-while](#while-do-while)
- [for](#for)
- [foreach](#foreach)
- [break, continue](#break-continue)
- [return, exit, die](#return-exit-die)
- [declare (strict_types)](#declare-strict_types)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## if, elseif, else

**O que é:**
Execução condicional de código.

**Como funciona:**
```php
$age = 25;

if ($age < 18) {
    echo 'Menor de idade';
} elseif ($age < 65) {
    echo 'Adulto';
} else {
    echo 'Aposentado';
}

// Sem chaves (não recomendado)
if ($isActive)
    echo 'Ativo';

// Com chaves (recomendado)
if ($isActive) {
    echo 'Ativo';
}

// Sintaxe alternativa (para templates)
<?php if ($user->isAdmin()): ?>
    <div>Painel do administrador</div>
<?php endif; ?>
```

**Quando usar:**
Qualquer checagem condicional.

**Exemplo prático:**
```php
// Checar permissão
public function update(Request $request, Post $post)
{
    if (!auth()->check()) {
        abort(401, 'Autenticação necessária');
    }

    if (!Gate::allows('update', $post)) {
        abort(403, 'Sem permissão para editar');
    }

    $post->update($request->validated());

    return response()->json($post);
}

// Guard clauses (saída antecipada)
public function process(?User $user)
{
    // BOM (guard clause)
    if ($user === null) {
        return;
    }

    if (!$user->isActive()) {
        throw new InactiveUserException();
    }

    // Lógica principal
    $this->doSomething($user);
}

// RUIM (aninhamento profundo)
public function processBad(?User $user)
{
    if ($user !== null) {
        if ($user->isActive()) {
            // Lógica principal no 3º nível de aninhamento
            $this->doSomething($user);
        }
    }
}
```

**Na entrevista:**
> "if-elseif-else para condições. Prefiro guard clauses (return cedo) em vez de if aninhado. Em template eu uso a sintaxe alternativa (if: ... endif;)."

---

## switch vs match

**O que é:**
Escolha múltipla com base em um valor.

**Como funciona:**
```php
// switch (jeito antigo)
$status = 'active';

switch ($status) {
    case 'active':
        $message = 'Ativo';
        break;
    case 'pending':
        $message = 'Pendente';
        break;
    case 'blocked':
        $message = 'Bloqueado';
        break;
    default:
        $message = 'Desconhecido';
}

// ⚠️ Sem break — executa todos os case abaixo (fall-through)
switch ($role) {
    case 'admin':
        $permissions[] = 'delete';
    case 'editor':
        $permissions[] = 'edit';
    case 'viewer':
        $permissions[] = 'view';
        break;
}
// Se $role = 'editor' → $permissions = ['edit', 'view']

// match (PHP 8.0+)
$message = match($status) {
    'active' => 'Ativo',
    'pending' => 'Pendente',
    'blocked' => 'Bloqueado',
    default => 'Desconhecido',
};

// Vários valores
$httpCategory = match($statusCode) {
    200, 201, 204 => 'success',
    400, 401, 403, 404 => 'client_error',
    500, 502, 503 => 'server_error',
    default => 'unknown',
};
```

**Quando usar:**
- `match` — sempre que der (PHP 8.0+)
- `switch` — código legado ou lógica pesada dentro do case

**Exemplo prático:**
```php
// Tratar status HTTP
$result = match($response->status()) {
    200 => $response->json(),
    201 => ['message' => 'Created', 'id' => $response->json('id')],
    400 => throw new BadRequestException($response->body()),
    401 => throw new UnauthorizedException(),
    404 => throw new NotFoundException(),
    default => throw new HttpException($response->status()),
};

// Permissões
$canEdit = match(true) {
    $user->isAdmin() => true,
    $user->owns($post) && !$post->isPublished() => true,
    default => false,
};

// Enum (PHP 8.1)
enum OrderStatus: string {
    case Pending = 'pending';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}

$badge = match($order->status) {
    OrderStatus::Pending => 'badge-warning',
    OrderStatus::Paid => 'badge-info',
    OrderStatus::Shipped => 'badge-primary',
    OrderStatus::Delivered => 'badge-success',
    OrderStatus::Cancelled => 'badge-danger',
};
```

**Na entrevista:**
> "switch é escolha múltipla com break. match (PHP 8.0) é o switch melhorado: comparação estrita, devolve valor, não precisa de break. Prefiro match quando vou devolver um valor."

---

## while, do-while

**O que é:**
Loops com condição.

**Como funciona:**
```php
// while — checa ANTES de executar
$i = 0;
while ($i < 5) {
    echo $i;
    $i++;
}
// Imprime: 01234

// do-while — checa DEPOIS de executar (roda pelo menos 1 vez)
$i = 10;
do {
    echo $i;
    $i++;
} while ($i < 5);
// Imprime: 10 (mesmo com condição false, rodou 1 vez)

// Loop infinito
while (true) {
    $job = $queue->pop();

    if ($job === null) {
        break;  // Sai do loop
    }

    $job->handle();
}
```

**Quando usar:**
- `while` — quando você não sabe quantas iterações vêm
- `do-while` — quando precisa rodar pelo menos 1 vez
- `for` / `foreach` — quando você já sabe a quantidade

**Exemplo prático:**
```php
// Ler arquivo grande linha a linha
$handle = fopen('large-file.csv', 'r');

while (($line = fgets($handle)) !== false) {
    $data = str_getcsv($line);
    // Processa a linha
    $this->processRow($data);
}

fclose($handle);

// Queue worker
while (true) {
    $job = $this->queue->pop();

    if ($job === null) {
        usleep(100000);  // 100ms
        continue;
    }

    try {
        $job->handle();
        $this->queue->acknowledge($job);
    } catch (\Exception $e) {
        $this->queue->reject($job);
        $this->logger->error($e);
    }
}

// Retry
$attempts = 0;
$maxAttempts = 3;

while ($attempts < $maxAttempts) {
    try {
        $result = $this->apiClient->request();
        break;  // Sucesso — sai
    } catch (ApiException $e) {
        $attempts++;
        if ($attempts >= $maxAttempts) {
            throw $e;
        }
        sleep(2);  // Espera antes de tentar de novo
    }
}
```

**Na entrevista:**
> "while checa a condição ANTES de executar, do-while DEPOIS (pelo menos 1 vez). Uso para retry, ler arquivo linha a linha, queue worker."

---

## for

**O que é:**
Loop com contador.

**Como funciona:**
```php
// for (inicialização; condição; incremento)
for ($i = 0; $i < 5; $i++) {
    echo $i;
}
// Imprime: 01234

// Ordem inversa
for ($i = 5; $i > 0; $i--) {
    echo $i;
}
// Imprime: 54321

// Passo 2
for ($i = 0; $i <= 10; $i += 2) {
    echo $i;
}
// Imprime: 0246810

// Várias variáveis
for ($i = 0, $j = 10; $i < $j; $i++, $j--) {
    echo "$i-$j ";
}
// Imprime: 0-10 1-9 2-8 3-7 4-6

// Loop infinito
for (;;) {
    // Roda para sempre
    if ($shouldStop) {
        break;
    }
}
```

**Quando usar:**
Quando você precisa de contador ou já sabe quantas iterações são.

**Exemplo prático:**
```php
// Gerar números de página
$totalPages = 10;
$currentPage = 5;

for ($i = 1; $i <= $totalPages; $i++) {
    if ($i === $currentPage) {
        echo "<span class='active'>$i</span> ";
    } else {
        echo "<a href='?page=$i'>$i</a> ";
    }
}

// Processamento em batch
$total = User::count();  // 100 000
$batchSize = 1000;

for ($offset = 0; $offset < $total; $offset += $batchSize) {
    $users = User::skip($offset)->take($batchSize)->get();

    foreach ($users as $user) {
        // Processa
        $this->processUser($user);
    }

    // Libera memória
    unset($users);
}

// Gerar intervalo de datas
$start = new DateTime('2024-01-01');
$end = new DateTime('2024-01-31');
$dates = [];

for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
    $dates[] = $date->format('Y-m-d');
}
```

**Na entrevista:**
> "for é loop com contador. Uso para batch, gerar intervalo, quando preciso do índice. Para array eu prefiro foreach."

---

## foreach

**O que é:**
Iteração em arrays e objetos.

**Como funciona:**
```php
$users = ['João', 'Pedro', 'Maria'];

// Só valores
foreach ($users as $user) {
    echo $user;
}

// Chave + valor
$ages = ['João' => 25, 'Pedro' => 30];

foreach ($ages as $name => $age) {
    echo "$name: $age anos";
}

// Alterar por referência
$numbers = [1, 2, 3];

foreach ($numbers as &$number) {
    $number *= 2;
}
unset($number);  // ⚠️ IMPORTANTE! Limpar a referência

var_dump($numbers);  // [2, 4, 6]

// Erro sem unset
foreach ($numbers as &$number) {
    $number *= 2;
}
// Esqueceu unset($number)

foreach ($numbers as $number) {
    // $number ainda é referência ao último elemento!
    // O último elemento será sobrescrito
}
```

**Quando usar:**
Iterar array, collection, objeto com Iterator.

**Exemplo prático:**
```php
// Eloquent Collection
$users = User::where('is_active', true)->get();

foreach ($users as $user) {
    echo $user->name;
}

// Alterar array por referência
$data = [
    ['name' => 'Produto 1', 'price' => 100],
    ['name' => 'Produto 2', 'price' => 200],
];

foreach ($data as &$item) {
    $item['price'] *= 1.1;  // Aumenta o preço em 10%
}
unset($item);

// Laravel Collection (melhor usar map)
$discounted = collect($data)->map(function ($item) {
    $item['price'] *= 0.9;  // Desconto de 10%
    return $item;
});

// Agrupar
$usersByDepartment = [];

foreach ($users as $user) {
    $usersByDepartment[$user->department_id][] = $user;
}

// Laravel Collection (melhor groupBy)
$grouped = $users->groupBy('department_id');
```

**Na entrevista:**
> "foreach itera array e collection. Para alterar elemento eu uso &$var, mas DEPOIS do loop é obrigatório unset(). No Laravel eu prefiro os métodos da Collection (map, filter, groupBy)."

---

## break, continue

**O que é:**
Controle da execução do loop.

**Como funciona:**
```php
// break — sai do loop
for ($i = 0; $i < 10; $i++) {
    if ($i === 5) {
        break;  // Sai quando i = 5
    }
    echo $i;
}
// Imprime: 01234

// continue — pula a iteração atual
for ($i = 0; $i < 10; $i++) {
    if ($i % 2 === 0) {
        continue;  // Pula os pares
    }
    echo $i;
}
// Imprime: 13579

// break com nível (sai de loops aninhados)
for ($i = 0; $i < 3; $i++) {
    for ($j = 0; $j < 3; $j++) {
        if ($i === 1 && $j === 1) {
            break 2;  // Sai dos DOIS loops
        }
        echo "$i-$j ";
    }
}
// Imprime: 0-0 0-1 0-2 1-0
```

**Quando usar:**
- `break` — sair quando a condição bate (achou o resultado, deu erro)
- `continue` — pular o item (filtro)

**Exemplo prático:**
```php
// Buscar usuário
$found = null;

foreach ($users as $user) {
    if ($user->email === $searchEmail) {
        $found = $user;
        break;  // Achou — sai
    }
}

// Melhor com Collection
$found = $users->firstWhere('email', $searchEmail);

// Validação com saída antecipada
public function validate(array $data): array
{
    $errors = [];

    foreach ($data as $field => $value) {
        if (empty($value)) {
            $errors[$field] = 'Campo obrigatório';
            continue;  // Pula as outras checagens deste campo
        }

        if ($field === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[$field] = 'Email inválido';
        }
    }

    return $errors;
}

// Processamento em batch com erros
foreach ($items as $item) {
    try {
        $this->process($item);
    } catch (ProcessException $e) {
        Log::error("Erro ao processar: {$e->getMessage()}");
        continue;  // Pula o item com erro, segue o processamento
    }
}
```

**Na entrevista:**
> "break sai do loop, continue pula a iteração. break 2 sai de loops aninhados. Uso break para sair cedo quando acho o resultado, continue para pular item com erro."

---

## return, exit, die

**O que é:**
Interrompe a função ou o script.

**Como funciona:**
```php
// return — sai da função e devolve valor
function findUser(int $id): ?User
{
    $user = User::find($id);

    if ($user === null) {
        return null;  // Saída antecipada
    }

    return $user;
}

// exit / die — para o script por completo
if (!auth()->check()) {
    exit('Autenticação necessária');  // Para a execução
}

// exit() = die() (aliases)
die('Erro fatal');

// Com código de saída (para CLI)
exit(0);   // Sucesso
exit(1);   // Erro
```

**Quando usar:**
- `return` — sempre para sair da função
- `exit` / `die` — só em erro crítico (não em produção!)

**Exemplo prático:**
```php
// RUIM (não use exit no controller)
public function show(int $id)
{
    $user = User::find($id);

    if ($user === null) {
        exit('User not found');  // ❌ Ruim
    }

    return view('user.show', compact('user'));
}

// BOM (use abort ou exceção)
public function show(int $id)
{
    $user = User::findOrFail($id);  // 404 se não achar

    return view('user.show', compact('user'));
}

// Guard clauses (return cedo)
public function update(Request $request, Post $post)
{
    if (!Gate::allows('update', $post)) {
        return response()->json(['error' => 'Forbidden'], 403);
    }

    $post->update($request->validated());

    return response()->json($post);
}

// Comando CLI
public function handle()
{
    if (!$this->confirm('Apagar todos os dados?')) {
        $this->info('Cancelado');
        return 0;  // Código de sucesso
    }

    try {
        DB::table('logs')->truncate();
        return 0;
    } catch (\Exception $e) {
        $this->error($e->getMessage());
        return 1;  // Código de erro
    }
}
```

**Na entrevista:**
> "return sai da função. exit/die param o script (não uso em produção, só em erro crítico). Prefiro guard clauses (return cedo) em vez de if aninhado."

---

## declare (strict_types)

**O que é:**
Declara diretivas para o PHP.

**Como funciona:**
```php
<?php
declare(strict_types=1);

// strict_types — tipagem estrita
function add(int $a, int $b): int
{
    return $a + $b;
}

add(5, 10);      // OK
add(5, '10');    // ❌ TypeError (sem strict_types converte '10' → 10)

// Sem strict_types (padrão)
add(5, '10');    // OK, '10' → 10 (conversão automática)
add(5, 'abc');   // ❌ TypeError ('abc' não dá para converter para int)
```

**Quando usar:**
**Sempre** use `declare(strict_types=1)` no começo do arquivo.

**Exemplo prático:**
```php
<?php
declare(strict_types=1);

namespace App\Services;

class OrderService
{
    public function create(int $userId, float $amount): Order
    {
        // $userId e $amount com tipo estrito
        return Order::create([
            'user_id' => $userId,
            'amount' => $amount,
        ]);
    }
}

// Sem strict_types
$service->create('5', '100.50');  // OK (converte para int e float)

// Com strict_types
$service->create('5', '100.50');  // ❌ TypeError

// Correto
$service->create(5, 100.50);  // ✅ OK
```

**Na entrevista:**
> "declare(strict_types=1) liga a tipagem estrita. Sem isso o PHP converte sozinho ('5' → 5). Sempre uso strict_types para o tipo não me surpreender."

---

## Recapitulando

**Condições:**
- `if-elseif-else` — qualquer condição
- `match` (PHP 8.0) — no lugar de switch
- Guard clauses (return cedo) — no lugar de if aninhado

**Loops:**
- `for` — quando precisa de contador
- `foreach` — array e collection
- `while` — quando você não sabe quantas iterações
- `do-while` — roda pelo menos 1 vez

**Controle:**
- `break` — sai do loop
- `continue` — pula a iteração
- `return` — sai da função
- `exit` / `die` — para o script (não use!)

**Importante na entrevista:**
- `match` vs `switch` (PHP 8.0)
- Guard clauses (saída antecipada)
- `&$var` no foreach + `unset()` obrigatório
- `declare(strict_types=1)` — use sempre
- Evite `exit` / `die` em controllers

---

## Exercícios práticos

### Exercício 1: Guard Clauses vs if aninhado
**Enunciado:** Refatore if aninhados para guard clauses.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ RUIM (aninhamento profundo)
function processOrder(?Order $order, ?User $user): void
{
    if ($order !== null) {
        if ($user !== null) {
            if ($user->isActive()) {
                if ($order->isPending()) {
                    if ($user->hasEnoughBalance($order->total)) {
                        // Lógica principal no 5º nível de aninhamento
                        $order->process();
                        $user->deductBalance($order->total);
                    } else {
                        throw new InsufficientBalanceException();
                    }
                } else {
                    throw new InvalidOrderStatusException();
                }
            } else {
                throw new InactiveUserException();
            }
        } else {
            throw new UserNotFoundException();
        }
    } else {
        throw new OrderNotFoundException();
    }
}

// ✅ BOM (guard clauses)
function processOrderRefactored(?Order $order, ?User $user): void
{
    // Saídas antecipadas
    if ($order === null) {
        throw new OrderNotFoundException();
    }

    if ($user === null) {
        throw new UserNotFoundException();
    }

    if (!$user->isActive()) {
        throw new InactiveUserException();
    }

    if (!$order->isPending()) {
        throw new InvalidOrderStatusException();
    }

    if (!$user->hasEnoughBalance($order->total)) {
        throw new InsufficientBalanceException();
    }

    // Lógica principal no 1º nível
    $order->process();
    $user->deductBalance($order->total);
}
```

**Pontos-chave:**
- Guard clauses diminuem o aninhamento
- Lógica principal fica no nível de cima
- Mais fácil de ler e manter
- Condição de erro fica explícita
</details>

### Exercício 2: Loop com break e continue
**Enunciado:** Processe um array de pedidos pulando itens e parando no limite.

<details>
<summary>Solução</summary>

```php
<?php

function processOrders(array $orders, int $maxAmount): array
{
    $processed = [];
    $totalAmount = 0;

    foreach ($orders as $order) {
        // Pula pedidos cancelados
        if ($order['status'] === 'cancelled') {
            continue;
        }

        // Pula pedidos com erro
        if (empty($order['items'])) {
            Log::warning("Pedido #{$order['id']} sem itens");
            continue;
        }

        // Para se atingir o limite
        if ($totalAmount + $order['total'] > $maxAmount) {
            Log::info("Atingiu o limite máximo");
            break;
        }

        // Processa o pedido
        try {
            $this->processOrder($order);
            $processed[] = $order['id'];
            $totalAmount += $order['total'];
        } catch (ProcessException $e) {
            Log::error("Falha ao processar pedido #{$order['id']}: {$e->getMessage()}");
            continue;  // Pula o pedido que falhou
        }
    }

    return [
        'processed' => $processed,
        'total_amount' => $totalAmount,
        'count' => count($processed),
    ];
}

// Exemplo de uso
$orders = [
    ['id' => 1, 'status' => 'pending', 'total' => 1000, 'items' => ['A', 'B']],
    ['id' => 2, 'status' => 'cancelled', 'total' => 500, 'items' => ['C']],
    ['id' => 3, 'status' => 'pending', 'total' => 1500, 'items' => []],
    ['id' => 4, 'status' => 'pending', 'total' => 2000, 'items' => ['D']],
];

$result = processOrders($orders, 5000);
// processed: [1, 4], total_amount: 3000
```

**Pontos-chave:**
- `continue` pula a iteração atual
- `break` sai do loop de vez
- Serve para processamento em batch com limite
- Loga o erro sem parar o processo inteiro
</details>

### Exercício 3: Generator para dados grandes
**Enunciado:** Leia um CSV grande linha a linha.

<details>
<summary>Solução</summary>

```php
<?php

// ❌ RUIM (carrega o arquivo inteiro na memória)
function readCsvBad(string $filePath): array
{
    $data = [];
    $handle = fopen($filePath, 'r');

    while (($line = fgets($handle)) !== false) {
        $data[] = str_getcsv($line);
    }

    fclose($handle);
    return $data;  // Arquivo inteiro na memória!
}

// ✅ BOM (generator, economiza memória)
function readCsv(string $filePath): Generator
{
    $handle = fopen($filePath, 'r');

    // Pula o cabeçalho
    fgets($handle);

    while (($line = fgets($handle)) !== false) {
        $row = str_getcsv($line);

        // Pula linhas vazias
        if (empty($row[0])) {
            continue;
        }

        yield $row;  // Devolve uma linha por vez
    }

    fclose($handle);
}

// Uso
function importUsers(string $csvPath): array
{
    $imported = [];
    $errors = [];
    $count = 0;

    foreach (readCsv($csvPath) as $index => $row) {
        try {
            [$name, $email, $age] = $row;

            // Validação
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Linha $index: email inválido";
                continue;
            }

            // Importa
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'age' => (int) $age,
            ]);

            $imported[] = $user->id;
            $count++;

            // Para depois de 1000 registros
            if ($count >= 1000) {
                Log::info('Atingiu o limite de importação');
                break;
            }

        } catch (\Exception $e) {
            $errors[] = "Linha $index: {$e->getMessage()}";
            continue;
        }
    }

    return [
        'imported' => $imported,
        'count' => $count,
        'errors' => $errors,
    ];
}
```

**Pontos-chave:**
- Generator (`yield`) não carrega o arquivo inteiro na memória
- `continue` pula linha inválida
- `break` limita quantos registros processar
- Serve para importar arquivo grande
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
