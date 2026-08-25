# 8.3 SQL Injection

## Resumo

> **SQL Injection** — injetar SQL pelo input do usuário para ler, alterar ou apagar dados no banco.
>
> **Proteção:** Laravel Query Builder e Eloquent parametrizam as queries sozinhos. Sempre use prepared statements com placeholders (?, :name).
>
> **Importante:** NUNCA concatene strings SQL. DB::raw() só com whitelist ou parâmetros.

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
SQL Injection — injetar SQL pelo input do usuário. O atacante consegue ler, alterar ou apagar dados no banco.

**Exemplo de ataque:**
```sql
-- Query com injeção
SELECT * FROM users WHERE email = 'user@example.com' OR '1'='1'

-- Sempre true → devolve todos os usuários
```

---

## Como funciona

**Código vulnerável:**

```php
// ❌ PERIGOSO: concatenação de SQL
Route::get('/users', function (Request $request) {
    $email = $request->input('email');

    $users = DB::select("SELECT * FROM users WHERE email = '{$email}'");
    // Ataque: ?email=' OR '1'='1
    // Devolve todos os usuários

    return $users;
});

// ❌ PERIGOSO: raw query sem parâmetros
$userId = $request->input('id');
DB::statement("DELETE FROM users WHERE id = {$userId}");
// Ataque: ?id=1 OR 1=1
// Apaga todos os usuários
```

**Código seguro (Parameter Binding):**

```php
// ✅ SEGURO: prepared statements
Route::get('/users', function (Request $request) {
    $email = $request->input('email');

    // Query Builder (parametriza automaticamente)
    $users = DB::table('users')
        ->where('email', $email)
        ->get();

    return $users;
});

// ✅ SEGURO: parâmetros no raw query
$users = DB::select('SELECT * FROM users WHERE email = ?', [$email]);

// ✅ SEGURO: parâmetros nomeados
$users = DB::select('SELECT * FROM users WHERE email = :email', [
    'email' => $email
]);
```

---

## Quando usar

**Sempre use Parameter Binding:**
- ✅ Query Builder (automático)
- ✅ Eloquent (automático)
- ✅ Prepared statements no raw SQL

**NUNCA faça:**
- ❌ Concatenação de SQL
- ❌ Raw query sem parâmetros
- ❌ DB::raw() com input do usuário

---

## Exemplo prático

**Eloquent protege automaticamente:**

```php
// ✅ SEGURO
$user = User::where('email', $request->input('email'))->first();

// ✅ SEGURO: insert em massa
User::create($request->validated());

// ✅ SEGURO: where in
User::whereIn('id', $request->input('ids'))->get();
```

**Query Builder protege automaticamente:**

```php
// ✅ SEGURO
DB::table('users')
    ->where('email', $email)
    ->where('active', true)
    ->get();

// ✅ SEGURO: condições complexas
DB::table('orders')
    ->where('status', $status)
    ->whereBetween('created_at', [$from, $to])
    ->orderBy($sortBy, $order)
    ->get();
```

**DB::raw() com cuidado:**

```php
// ❌ PERIGOSO: input do usuário em raw()
$column = $request->input('sort_by');
DB::table('users')->orderByRaw($column)->get();
// Ataque: ?sort_by=id; DELETE FROM users; --

// ✅ SEGURO: whitelist
$allowedColumns = ['id', 'name', 'created_at'];
$column = $request->input('sort_by', 'id');

if (!in_array($column, $allowedColumns)) {
    $column = 'id';
}

DB::table('users')->orderBy($column)->get();

// ✅ SEGURO: parâmetros no raw()
DB::table('orders')
    ->selectRaw('COUNT(*) as count, status')
    ->where('user_id', $userId)  // Parametrizado
    ->groupBy('status')
    ->get();
```

**Busca segura:**

```php
// ❌ PERIGOSO
$query = $request->input('q');
$users = DB::select("SELECT * FROM users WHERE name LIKE '%{$query}%'");

// ✅ SEGURO: parâmetros
$query = $request->input('q');
$users = DB::table('users')
    ->where('name', 'like', "%{$query}%")
    ->get();

// ✅ SEGURO: fulltext search
$users = DB::table('users')
    ->whereRaw('MATCH(name, email) AGAINST(? IN BOOLEAN MODE)', [$query])
    ->get();
```

**Ordenação dinâmica segura:**

```php
class UserController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'sometimes|in:id,name,email,created_at',
            'order' => 'sometimes|in:asc,desc',
        ]);

        $users = User::query()
            ->orderBy(
                $validated['sort_by'] ?? 'created_at',
                $validated['order'] ?? 'desc'
            )
            ->paginate(20);

        return view('users.index', compact('users'));
    }
}
```

**Uso seguro do IN:**

```php
// ✅ SEGURO: whereIn parametriza automaticamente
$ids = $request->input('ids');  // [1, 2, 3]
$users = User::whereIn('id', $ids)->get();

// ✅ SEGURO: subquery
$activeUserIds = User::where('active', true)->pluck('id');
$orders = Order::whereIn('user_id', $activeUserIds)->get();
```

**Second-order SQL Injection:**

```php
// Ataque em duas etapas
// 1. Salvar payload malicioso
User::create([
    'name' => "'; DROP TABLE users; --",  // Salvo no banco
]);

// 2. Usar em raw query
$user = User::find(1);
DB::statement("INSERT INTO logs (message) VALUES ('{$user->name}')");
// ❌ PERIGOSO

// ✅ SEGURO: sempre parametrize
DB::statement('INSERT INTO logs (message) VALUES (?)', [$user->name]);
```

**Teste de SQL Injection:**

```php
// tests/Feature/SqlInjectionTest.php
class SqlInjectionTest extends TestCase
{
    public function test_search_escapes_sql_injection(): void
    {
        $sqlPayload = "' OR '1'='1";

        $response = $this->get('/users?email=' . urlencode($sqlPayload));

        // Não deve devolver todos os usuários
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_sort_parameter_is_validated(): void
    {
        $response = $this->get('/users?sort_by=id; DROP TABLE users');

        // Deve devolver erro de validação
        $response->assertStatus(422);
    }
}
```

**WAF (Web Application Firewall):**

```php
// Middleware para proteção básica
class SqlInjectionDetection
{
    private array $patterns = [
        '/(\b(SELECT|UNION|INSERT|UPDATE|DELETE|DROP|CREATE)\b)/i',
        '/(\b(OR|AND)\b.*=)/i',
        '/(--|#|\/\*|\*\/)/i',
    ];

    public function handle($request, Closure $next)
    {
        $input = json_encode($request->all());

        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                Log::warning('Tentativa de SQL Injection detectada', [
                    'ip' => $request->ip(),
                    'input' => $input,
                ]);

                abort(403, 'Forbidden');
            }
        }

        return $next($request);
    }
}
```

---

## Na entrevista

> "SQL Injection é injetar SQL pelo input do usuário. O Laravel protege com Query Builder e Eloquent — parametrizam sozinhos. Prepared statements com placeholders (?, :name). NUNCA concatene SQL. DB::raw() só com whitelist ou parâmetros. Validação para coluna dinâmica (sort_by in:id,name). whereIn() é seguro. Second-order injection: dado que já está no banco e vai para raw query. WAF middleware como camada extra."

---

## Exercícios práticos

### Exercício 1: Encontre e corrija o SQL Injection

**Enunciado:** O que está errado neste código? Corrija as vulnerabilidades.

```php
class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        $category = $request->input('category');
        $sortBy = $request->input('sort', 'name');

        $products = DB::select("
            SELECT * FROM products
            WHERE name LIKE '%{$query}%'
            AND category = '{$category}'
            ORDER BY {$sortBy}
        ");

        return view('products.index', compact('products'));
    }
}
```

<details>
<summary>Solução</summary>

```php
// Problemas:
// 1. Concatenação de $query — SQL Injection via LIKE
// 2. Concatenação de $category — SQL Injection
// 3. Concatenação de $sortBy — dá para injetar via ORDER BY

// Solução
class ProductController extends Controller
{
    public function search(Request $request)
    {
        // 1. Validar o input
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:50',
            'sort' => 'nullable|in:name,price,created_at',
        ]);

        // 2. Usar Query Builder (parametriza automaticamente)
        $query = DB::table('products');

        // LIKE seguro
        if (!empty($validated['q'])) {
            $query->where('name', 'like', '%' . $validated['q'] . '%');
        }

        // WHERE seguro
        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        // ORDER BY seguro (whitelist via validação)
        $sortBy = $validated['sort'] ?? 'name';
        $query->orderBy($sortBy);

        $products = $query->get();

        return view('products.index', compact('products'));
    }
}

// Alternativa: Eloquent
class ProductController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:50',
            'sort' => 'nullable|in:name,price,created_at',
        ]);

        $products = Product::query()
            ->when($validated['q'] ?? null, fn($query, $search) =>
                $query->where('name', 'like', "%{$search}%")
            )
            ->when($validated['category'] ?? null, fn($query, $cat) =>
                $query->where('category', $cat)
            )
            ->orderBy($validated['sort'] ?? 'name')
            ->get();

        return view('products.index', compact('products'));
    }
}
```
</details>

### Exercício 2: Ordenação dinâmica segura

**Enunciado:** Implemente um endpoint para listar usuários com ordenação por campos diferentes.

<details>
<summary>Solução</summary>

```php
// UserController
class UserController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
    ];

    private const SORT_DIRECTIONS = ['asc', 'desc'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:' . implode(',', self::SORTABLE_COLUMNS),
            'direction' => 'nullable|in:' . implode(',', self::SORT_DIRECTIONS),
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $sortBy = $validated['sort_by'] ?? 'created_at';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 15;

        // Whitelist garante a segurança
        if (!in_array($sortBy, self::SORTABLE_COLUMNS)) {
            $sortBy = 'created_at';
        }

        if (!in_array($direction, self::SORT_DIRECTIONS)) {
            $direction = 'desc';
        }

        $users = User::orderBy($sortBy, $direction)
            ->paginate($perPage);

        return UserResource::collection($users);
    }
}

// Alternativa: usar o pacote spatie/laravel-query-builder
composer require spatie/laravel-query-builder

use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedSort;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = QueryBuilder::for(User::class)
            ->allowedSorts([
                'id',
                'name',
                'email',
                'created_at',
            ])
            ->allowedFilters([
                'name',
                'email',
            ])
            ->paginate($request->input('per_page', 15));

        return UserResource::collection($users);
    }
}

// Uso:
// GET /users?sort=name
// GET /users?sort=-created_at (desc)
// GET /users?filter[name]=João&sort=email
```
</details>

### Exercício 3: Proteja contra Second-Order SQL Injection

**Enunciado:** Implemente log seguro das ações do usuário.

<details>
<summary>Solução</summary>

```php
// ❌ código VULNERÁVEL
class ActivityLogger
{
    public function log(User $user, string $action, array $data)
    {
        // Second-order injection: $user->name do banco pode conter SQL
        $message = "Usuário {$user->name} executou {$action}";

        DB::statement("INSERT INTO activity_log (message, data) VALUES ('{$message}', '{$data}')");
    }
}

// ✅ código SEGURO
class ActivityLogger
{
    public function log(User $user, string $action, array $data)
    {
        // Solução 1: usar Query Builder
        DB::table('activity_log')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'data' => json_encode($data),
            'created_at' => now(),
        ]);
    }
}

// Solução 2: Eloquent Model
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

class ActivityLogger
{
    public function log(User $user, string $action, array $data): void
    {
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'data' => $data,
        ]);
    }
}

// Solução 3: prepared statements para raw SQL
class ActivityLogger
{
    public function log(User $user, string $action, array $data): void
    {
        $message = "Usuário {$user->name} executou {$action}";

        DB::statement(
            'INSERT INTO activity_log (message, data, created_at) VALUES (?, ?, ?)',
            [$message, json_encode($data), now()]
        );
    }
}

// Uso
$logger = new ActivityLogger();
$logger->log(
    auth()->user(),
    'updated_profile',
    ['field' => 'email', 'old' => 'antigo@email.com', 'new' => 'novo@email.com']
);
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
