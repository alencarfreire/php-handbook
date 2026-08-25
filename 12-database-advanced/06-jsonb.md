# 9.6 JSONB no PostgreSQL

> **TL;DR:** JSONB é o tipo JSON binário do PostgreSQL para schema flexível. Operadores: -> (get JSON), ->> (get text), @> (contains), ? (has key). GIN indexes para queries rápidas. Laravel: where('attributes->brand', 'Dell'), whereJsonContains. Casos de uso: atributos dinâmicos, preferences do usuário, audit logs.

## Conteúdo

- [O que é](#o-que-é)
- [Criar coluna JSONB](#criar-coluna-jsonb)
- [Gravar dados](#gravar-dados)
- [Ler dados](#ler-dados)
- [Operadores JSONB](#operadores-jsonb)
- [Queries no Laravel](#queries-no-laravel)
- [Índices em JSONB](#índices-em-jsonb)
- [Exemplos práticos](#exemplos-práticos)
- [Funções JSONB](#funções-jsonb)
- [Dicas de performance](#dicas-de-performance)
- [JSONB vs relacional](#jsonb-vs-relacional)
- [Exercícios práticos](#exercícios-práticos)
- [Na entrevista](#na-entrevista)

## O que é

**JSONB:**
Tipo JSON binário no PostgreSQL. Guarda o JSON já parseado. Dá para indexar e consultar com eficiência.

**JSON vs JSONB:**
- **JSON**: texto, guarda como está, queries lentas
- **JSONB**: binary, parsed, queries rápidas, suporta índices

**Para quê:**
- Schema flexível (campos dinâmicos)
- Estruturas aninhadas
- Query dentro do JSON
- Migração de NoSQL

---

## Criar coluna JSONB

**Migration:**

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->jsonb('attributes');  // Coluna JSONB
    $table->timestamps();
});
```

**Model:**

```php
class Product extends Model
{
    protected $casts = [
        'attributes' => 'array',  // Encode/decode JSON automático
    ];
}
```

---

## Gravar dados

```php
Product::create([
    'name' => 'Laptop',
    'attributes' => [
        'brand' => 'Dell',
        'specs' => [
            'cpu' => 'Intel i7',
            'ram' => '16GB',
            'storage' => '512GB SSD',
        ],
        'tags' => ['electronics', 'computers'],
    ],
]);
```

---

## Ler dados

**Leitura básica:**

```php
$product = Product::find(1);

// O JSON inteiro
$attributes = $product->attributes;

// Acesso aos campos
$brand = $product->attributes['brand'];  // 'Dell'
$cpu = $product->attributes['specs']['cpu'];  // 'Intel i7'
```

**Caminho JSON no Eloquent:**

```php
// WHERE em campo JSON
$products = Product::where('attributes->brand', 'Dell')->get();

// Campos aninhados
$products = Product::where('attributes->specs->cpu', 'Intel i7')->get();

// SELECT de campo JSON
$brands = Product::select('attributes->brand as brand')->get();
```

---

## Operadores JSONB

### 1. `->` Pegar objeto JSON

```sql
SELECT attributes->'brand' FROM products;
-- Resultado: JSON ("Dell")
```

### 2. `->>` Pegar text

```sql
SELECT attributes->>'brand' FROM products;
-- Resultado: text (Dell)
```

### 3. `@>` Contém JSON

```sql
-- Encontrar produtos com brand = 'Dell'
SELECT * FROM products
WHERE attributes @> '{"brand": "Dell"}';
```

**Laravel:**

```php
Product::whereRaw("attributes @> ?", ['{"brand": "Dell"}'])->get();
```

### 4. `?` Tem a chave

```sql
-- Encontrar produtos que têm o campo 'warranty'
SELECT * FROM products
WHERE attributes ? 'warranty';
```

### 5. `?|` Tem qualquer uma das chaves

```sql
-- Tem 'color' ou 'size'
SELECT * FROM products
WHERE attributes ?| array['color', 'size'];
```

### 6. `?&` Tem todas as chaves

```sql
-- Tem 'color' e 'size'
SELECT * FROM products
WHERE attributes ?& array['color', 'size'];
```

---

## Queries no Laravel

**WHERE em campo JSON:**

```php
// Condição simples
Product::where('attributes->brand', 'Dell')->get();

// Aninhado
Product::where('attributes->specs->ram', '16GB')->get();

// Contains
Product::whereJsonContains('attributes->tags', 'electronics')->get();

// Tamanho do array
Product::whereJsonLength('attributes->tags', 2)->get();
```

**UPDATE em campo JSON:**

```php
// Atualizar o JSON inteiro
$product->update([
    'attributes' => ['brand' => 'HP'],
]);

// Atualizar um campo específico via SQL
Product::where('id', 1)->update([
    'attributes->brand' => 'HP',
]);
```

**Incrementar número no JSON:**

```php
DB::table('products')
    ->where('id', 1)
    ->update([
        'attributes->views' => DB::raw("(attributes->>'views')::int + 1"),
    ]);
```

---

## Índices em JSONB

### 1. GIN Index (General Inverted Index)

**Para os operadores `@>` e `?`:**

```php
Schema::table('products', function (Blueprint $table) {
    $table->index('attributes', 'idx_products_attributes', 'gin');
});

// SQL:
// CREATE INDEX idx_products_attributes ON products USING gin (attributes);
```

**Uso:**

```sql
-- Rápido (usa GIN index)
SELECT * FROM products
WHERE attributes @> '{"brand": "Dell"}';
```

---

### 2. GIN Index em um caminho específico

```php
// Raw SQL na migration
DB::statement("
    CREATE INDEX idx_products_brand
    ON products USING gin ((attributes->'brand'))
");
```

**Uso:**

```sql
-- Rápido
SELECT * FROM products
WHERE attributes->'brand' = '"Dell"';
```

---

### 3. B-Tree Index em campo JSON

```php
// Para ORDER BY e WHERE com operadores de comparação
DB::statement("
    CREATE INDEX idx_products_price
    ON products ((attributes->>'price')::numeric)
");
```

**Uso:**

```sql
-- Rápido
SELECT * FROM products
WHERE (attributes->>'price')::numeric > 1000
ORDER BY (attributes->>'price')::numeric;
```

**Laravel:**

```php
Product::whereRaw("(attributes->>'price')::numeric > ?", [1000])
    ->orderByRaw("(attributes->>'price')::numeric")
    ->get();
```

---

## Exemplos práticos

### 1. Atributos dinâmicos (e-commerce)

```php
// Product com atributos diferentes por categoria
Product::create([
    'name' => 'T-Shirt',
    'category' => 'clothing',
    'attributes' => [
        'size' => 'L',
        'color' => 'blue',
        'material' => 'cotton',
    ],
]);

Product::create([
    'name' => 'Laptop',
    'category' => 'electronics',
    'attributes' => [
        'brand' => 'Dell',
        'cpu' => 'Intel i7',
        'ram' => '16GB',
    ],
]);

// Filtros
Product::where('category', 'clothing')
    ->where('attributes->color', 'blue')
    ->get();

Product::where('category', 'electronics')
    ->where('attributes->brand', 'Dell')
    ->get();
```

---

### 2. Preferences do usuário

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email');
    $table->jsonb('preferences');
});

User::create([
    'email' => 'joao@email.com',
    'preferences' => [
        'theme' => 'dark',
        'language' => 'pt',
        'notifications' => [
            'email' => true,
            'push' => false,
        ],
    ],
]);

// Usuários com tema dark
User::where('preferences->theme', 'dark')->get();

// Notificações por email ativas
User::where('preferences->notifications->email', true)->get();
```

---

### 3. Audit log

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('model_type');
    $table->unsignedBigInteger('model_id');
    $table->string('action');  // created, updated, deleted
    $table->jsonb('old_values')->nullable();
    $table->jsonb('new_values')->nullable();
    $table->timestamps();
});

// No update
AuditLog::create([
    'model_type' => 'Product',
    'model_id' => 1,
    'action' => 'updated',
    'old_values' => [
        'price' => 1000,
        'stock' => 10,
    ],
    'new_values' => [
        'price' => 1200,
        'stock' => 5,
    ],
]);

// Todas as mudanças de preço
AuditLog::whereRaw("old_values->>'price' IS DISTINCT FROM new_values->>'price'")
    ->get();
```

---

### 4. Settings/configuração

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->jsonb('value');
});

// Guardar configurações complexas
Setting::create([
    'key' => 'payment_gateways',
    'value' => [
        'stripe' => [
            'enabled' => true,
            'api_key' => 'sk_test_...',
            'webhook_secret' => 'whsec_...',
        ],
        'paypal' => [
            'enabled' => false,
            'client_id' => 'xxx',
        ],
    ],
]);

// Pegar a configuração
$stripeEnabled = Setting::where('key', 'payment_gateways')
    ->value('value->stripe->enabled');
```

---

## Funções JSONB

### 1. jsonb_array_elements

```sql
-- Expandir o JSON array em linhas
SELECT jsonb_array_elements(attributes->'tags') as tag
FROM products
WHERE id = 1;

-- tag
-- "electronics"
-- "computers"
```

**Laravel:**

```php
DB::table('products')
    ->selectRaw("jsonb_array_elements(attributes->'tags') as tag")
    ->where('id', 1)
    ->get();
```

---

### 2. jsonb_build_object

```sql
-- Criar objeto JSON
SELECT jsonb_build_object(
    'name', name,
    'brand', attributes->>'brand'
) FROM products;
```

---

### 3. jsonb_set

```sql
-- Atualizar valor no JSON
UPDATE products
SET attributes = jsonb_set(
    attributes,
    '{specs, ram}',
    '"32GB"'
)
WHERE id = 1;
```

**Laravel:**

```php
DB::table('products')
    ->where('id', 1)
    ->update([
        'attributes' => DB::raw("jsonb_set(attributes, '{specs, ram}', '\"32GB\"')"),
    ]);
```

---

## Dicas de performance

```
✓ Use JSONB em vez de JSON (mais rápido)
✓ Crie GIN indexes para queries frequentes
✓ Para ORDER BY, use B-Tree indexes em (field->>'key')::type
✓ Guarde só campos dinâmicos no JSONB (não tudo)
✓ JSONB é bom para read-heavy (não para write-heavy)
✓ Normalize se você faz JOIN frequente nesses campos
```

---

## JSONB vs relacional

**JSONB é bom para:**
- ✅ Schema dinâmica/flexível
- ✅ Dados aninhados
- ✅ Protótipo/MVP
- ✅ Settings, preferences

**Relacional é bom para:**
- ✅ Schema rígida
- ✅ JOIN com outras tabelas
- ✅ Integridade referencial
- ✅ Queries complexas

**Abordagem híbrida:**

```php
Schema::create('products', function (Blueprint $table) {
    // Relacional (para JOIN e WHERE)
    $table->id();
    $table->string('name');
    $table->decimal('price');
    $table->unsignedBigInteger('category_id');

    // JSONB (para campos dinâmicos)
    $table->jsonb('attributes')->nullable();

    $table->foreign('category_id')->references('id')->on('categories');
});
```

---

## Exercícios práticos

### Exercício 1: E-commerce com atributos dinâmicos

**Enunciado:** Implemente um sistema de produtos com atributos flexíveis por categoria.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedBigInteger('category_id');
    $table->decimal('price', 10, 2);
    $table->jsonb('attributes');
    $table->timestamps();

    $table->index('category_id');
    // GIN index para JSONB
    $table->index('attributes', 'idx_products_attributes', 'gin');
});

// Também índice em campos específicos
DB::statement("CREATE INDEX idx_products_brand ON products USING gin ((attributes->'brand'))");
DB::statement("CREATE INDEX idx_products_price_numeric ON products ((attributes->>'price')::numeric)");

// app/Models/Product.php
class Product extends Model
{
    protected $casts = [
        'attributes' => 'array',
    ];

    // Scope para filtrar por campos JSONB
    public function scopeWithAttribute($query, string $key, $value)
    {
        return $query->where("attributes->{$key}", $value);
    }

    public function scopeHasAttribute($query, string $key)
    {
        return $query->whereRaw("attributes ? ?", [$key]);
    }

    public function scopeAttributeContains($query, array $data)
    {
        return $query->whereRaw("attributes @> ?", [json_encode($data)]);
    }
}

// app/Http/Controllers/ProductController.php
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Filtros dos query params
        if ($brand = $request->input('brand')) {
            $query->withAttribute('brand', $brand);
        }

        if ($color = $request->input('color')) {
            $query->withAttribute('color', $color);
        }

        if ($minPrice = $request->input('min_price')) {
            $query->whereRaw("(attributes->>'price')::numeric >= ?", [$minPrice]);
        }

        // Filtro por existência do atributo
        if ($request->boolean('has_warranty')) {
            $query->hasAttribute('warranty');
        }

        // Filtro composto (contains)
        if ($specs = $request->input('specs')) {
            $query->attributeContains($specs);
        }

        return ProductResource::collection($query->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric',
            'attributes' => 'required|array',
        ]);

        // Atributos diferentes por categoria
        $product = Product::create($validated);

        return new ProductResource($product);
    }

    // Update em massa de campo JSONB
    public function bulkUpdateAttribute(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'attribute_key' => 'required|string',
            'attribute_value' => 'required',
        ]);

        // Atualizar um campo específico no JSONB
        $affected = Product::where('category_id', $validated['category_id'])
            ->update([
                "attributes->{$validated['attribute_key']}" => $validated['attribute_value']
            ]);

        return response()->json([
            'message' => "Atualizados {$affected} produtos",
            'affected' => $affected
        ]);
    }
}

// Exemplos de dados por categoria
// Electronics:
Product::create([
    'name' => 'Laptop Dell XPS',
    'category_id' => 1,
    'price' => 1500,
    'attributes' => [
        'brand' => 'Dell',
        'model' => 'XPS 15',
        'specs' => [
            'cpu' => 'Intel i7',
            'ram' => '16GB',
            'storage' => '512GB SSD',
        ],
        'warranty' => '2 years',
    ],
]);

// Clothing:
Product::create([
    'name' => 'T-Shirt Nike',
    'category_id' => 2,
    'price' => 25,
    'attributes' => [
        'brand' => 'Nike',
        'size' => 'L',
        'color' => 'blue',
        'material' => 'cotton',
    ],
]);
```

</details>

### Exercício 2: Preferences e settings do usuário

**Enunciado:** Sistema de configurações do usuário com JSONB.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::table('users', function (Blueprint $table) {
    $table->jsonb('preferences')->nullable();
    $table->jsonb('metadata')->nullable();
});

// app/Models/User.php
class User extends Model
{
    protected $casts = [
        'preferences' => 'array',
        'metadata' => 'array',
    ];

    // Helpers para preferences
    public function getPreference(string $key, $default = null)
    {
        return data_get($this->preferences, $key, $default);
    }

    public function setPreference(string $key, $value): void
    {
        $preferences = $this->preferences ?? [];
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        $this->save();
    }

    public function updatePreferences(array $updates): void
    {
        $preferences = $this->preferences ?? [];

        foreach ($updates as $key => $value) {
            data_set($preferences, $key, $value);
        }

        $this->preferences = $preferences;
        $this->save();
    }

    // Scopes
    public function scopeWithPreference($query, string $key, $value)
    {
        return $query->where("preferences->{$key}", $value);
    }

    public function scopeNotificationEnabled($query, string $type)
    {
        return $query->where("preferences->notifications->{$type}", true);
    }
}

// app/Http/Controllers/UserPreferencesController.php
class UserPreferencesController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'theme' => 'sometimes|in:light,dark,auto',
            'language' => 'sometimes|string|size:2',
            'notifications.email' => 'sometimes|boolean',
            'notifications.push' => 'sometimes|boolean',
            'notifications.sms' => 'sometimes|boolean',
            'privacy.profile_visible' => 'sometimes|boolean',
            'privacy.show_email' => 'sometimes|boolean',
        ]);

        $user->updatePreferences($validated);

        return response()->json([
            'message' => 'Preferências atualizadas',
            'preferences' => $user->preferences
        ]);
    }

    public function get(Request $request)
    {
        $user = $request->user();

        return response()->json($user->preferences ?? []);
    }

    // Usuários com determinadas configurações
    public function getUsersWithEmailNotifications()
    {
        $users = User::notificationEnabled('email')->get();

        return response()->json($users);
    }
}

// Preferences padrão no cadastro
class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = User::create([
            ...$validated,
            'preferences' => [
                'theme' => 'light',
                'language' => 'pt',
                'notifications' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                ],
                'privacy' => [
                    'profile_visible' => true,
                    'show_email' => false,
                ],
            ],
        ]);

        return response()->json($user);
    }
}
```

</details>

### Exercício 3: Audit log com JSONB

**Enunciado:** Sistema de auditoria de mudanças, com o diff salvo em JSONB.

<details>
<summary>Solução</summary>

```php
// Migration
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('model_type');
    $table->unsignedBigInteger('model_id');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->string('action'); // created, updated, deleted
    $table->jsonb('old_values')->nullable();
    $table->jsonb('new_values')->nullable();
    $table->jsonb('metadata')->nullable();
    $table->timestamp('created_at');

    $table->index(['model_type', 'model_id']);
    $table->index('user_id');
    $table->index('action');
    $table->index('created_at');
});

DB::statement("CREATE INDEX idx_audit_old_values ON audit_logs USING gin (old_values)");
DB::statement("CREATE INDEX idx_audit_new_values ON audit_logs USING gin (new_values)");

// app/Models/AuditLog.php
class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getDiff(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        $changes = [];

        foreach ($new as $key => $value) {
            $oldValue = $old[$key] ?? null;

            if ($oldValue !== $value) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }
}

// app/Observers/AuditObserver.php
class AuditObserver
{
    public function created($model)
    {
        AuditLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => auth()->id(),
            'action' => 'created',
            'new_values' => $model->getAttributes(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    public function updated($model)
    {
        $changes = $model->getDirty();

        if (empty($changes)) {
            return;
        }

        $original = $model->getOriginal();

        AuditLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => auth()->id(),
            'action' => 'updated',
            'old_values' => array_intersect_key($original, $changes),
            'new_values' => $changes,
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }

    public function deleted($model)
    {
        AuditLog::create([
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'old_values' => $model->getAttributes(),
            'metadata' => [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ]);
    }
}

// Registrar o observer
// app/Providers/EventServiceProvider.php
public function boot()
{
    Product::observe(AuditObserver::class);
    User::observe(AuditObserver::class);
    Order::observe(AuditObserver::class);
}

// app/Http/Controllers/AuditLogController.php
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user');

        if ($modelType = $request->input('model_type')) {
            $query->where('model_type', $modelType);
        }

        if ($modelId = $request->input('model_id')) {
            $query->where('model_id', $modelId);
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        // Busca por mudanças de um campo específico
        if ($field = $request->input('field')) {
            $query->whereRaw("new_values ? ?", [$field]);
        }

        // Busca por um valor específico
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw("old_values::text ILIKE ?", ["%{$search}%"])
                  ->orWhereRaw("new_values::text ILIKE ?", ["%{$search}%"]);
            });
        }

        $logs = $query->latest()->paginate(50);

        return response()->json($logs);
    }

    public function show(AuditLog $auditLog)
    {
        return response()->json([
            'audit_log' => $auditLog->load('user'),
            'diff' => $auditLog->getDiff(),
        ]);
    }
}
```

</details>

---

## Na entrevista

> "JSONB é o tipo JSON binário do PostgreSQL. Diferença para JSON: parsed, tem índice, query rápida. Operadores: -> (get JSON), ->> (get text), @> (contains), ? (has key). No Laravel: where('attributes->brand', 'Dell'), whereJsonContains. GIN index para @> e ?, B-Tree para ORDER BY. Casos de uso: atributos dinâmicos, preferences do usuário, audit log, settings. Trade-off: flexibilidade vs schema rígida. Na prática: JSONB para campos dinâmicos read-heavy, relacional para JOIN e schema rígida. O híbrido costuma ser o melhor."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
