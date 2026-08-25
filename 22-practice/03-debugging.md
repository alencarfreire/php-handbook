# 16.3 Cenários de debug

## Cenários de debug na entrevista

### Cenário 1: N+1 Query Problem

**Problema:**

```php
// Usuário reclama: "A página da lista de posts demora 5 segundos"

class PostController extends Controller
{
    public function index()
    {
        $posts = Post::all();  // 1 query

        return view('posts.index', compact('posts'));
    }
}

// posts/index.blade.php
@foreach ($posts as $post)
    <div>
        <h2>{{ $post->title }}</h2>
        <p>Por: {{ $post->user->name }}</p>  // N queries!
        <p>Comentários: {{ $post->comments->count() }}</p>  // N queries!
    </div>
@endforeach

// Total: 1 + 100 + 100 = 201 queries para 100 posts
```

**Debugging:**

```php
// 1. Ligar o Query Log
DB::enableQueryLog();

// Carregar a página

// 2. Ver as queries
dd(DB::getQueryLog());

// Vamos ver:
// SELECT * FROM posts
// SELECT * FROM users WHERE id = 1
// SELECT * FROM users WHERE id = 2
// SELECT * FROM users WHERE id = 3
// ...
```

**Solução:**

```php
class PostController extends Controller
{
    public function index()
    {
        // Eager loading
        $posts = Post::with(['user', 'comments'])->get();  // 3 queries

        return view('posts.index', compact('posts'));
    }
}

// Agora: 3 queries no lugar de 201
```

---

### Cenário 2: Memory Limit Exceeded

**Problema:**

```php
// Fatal error: Allowed memory size of 134217728 bytes exhausted

class ExportController extends Controller
{
    public function exportUsers()
    {
        $users = User::all();  // 1 milhão de usuários na memória!

        $csv = '';
        foreach ($users as $user) {
            $csv .= "{$user->id},{$user->name},{$user->email}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv');
    }
}
```

**Debugging:**

```php
// Ver o uso atual de memória
echo memory_get_usage() / 1024 / 1024 . ' MB';

// Checar o limit
echo ini_get('memory_limit'); // 128M
```

**Solução:**

```php
class ExportController extends Controller
{
    public function exportUsers()
    {
        $fileName = 'users_' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Cabeçalho
            fputcsv($handle, ['ID', 'Nome', 'Email']);

            // chunk() no lugar de all()
            User::chunk(1000, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email
                    ]);
                }
            });

            fclose($handle);
        }, $fileName);
    }
}

// Agora: memória estável ~5 MB
```

---

### Cenário 3: Query lenta no banco

**Problema:**

```php
// A query leva 10+ segundos

$orders = Order::where('status', 'pending')
    ->where('created_at', '>=', now()->subDays(30))
    ->orderBy('total', 'desc')
    ->get();
```

**Debugging:**

```sql
-- 1. EXPLAIN
EXPLAIN SELECT * FROM orders
WHERE status = 'pending'
AND created_at >= '2024-01-01'
ORDER BY total DESC;

-- Resultado:
-- type: ALL (full table scan — ruim!)
-- rows: 1000000 (varre a tabela inteira)
-- Extra: Using where; Using filesort (sem índice)
```

**Solução:**

```php
// Migration: adicionar índices
Schema::table('orders', function (Blueprint $table) {
    $table->index(['status', 'created_at', 'total']);
    // Composite index na ordem do WHERE, ORDER BY
});

// Agora o EXPLAIN mostra:
// type: range (usa o índice)
// rows: 1000 (só as linhas necessárias)
// Extra: Using index condition (rápido!)
```

---

### Cenário 4: Race Condition

**Problema:**

```php
// Dois usuários compram o último item ao mesmo tempo

class CheckoutController extends Controller
{
    public function checkout(Request $request, Product $product)
    {
        // User A e User B chegam aqui ao mesmo tempo
        if ($product->stock > 0) {
            // Os dois veem stock = 1

            // Criar o pedido
            Order::create([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
            ]);

            // Decrementar o stock
            $product->decrement('stock');
            // Agora stock = -1 (oversold!)
        }
    }
}
```

**Debugging:**

```bash
# Reproduzir a race condition
ab -n 100 -c 10 http://localhost/checkout/1

# Depois: stock = -5 (oversold em 5 unidades)
```

**Solução 1: Database Lock**

```php
DB::transaction(function () use ($product, $request) {
    // Lock na leitura
    $product = Product::where('id', $product->id)
        ->lockForUpdate()
        ->first();

    if ($product->stock > 0) {
        Order::create([...]);
        $product->decrement('stock');
    } else {
        throw new OutOfStockException();
    }
});
```

**Solução 2: Optimistic Locking**

```php
class Product extends Model
{
    protected $casts = [
        'version' => 'integer',
    ];
}

// Checkout
$product = Product::find($id);
$originalVersion = $product->version;

if ($product->stock > 0) {
    Order::create([...]);

    // Update só se a version não mudou
    $updated = Product::where('id', $product->id)
        ->where('version', $originalVersion)
        ->update([
            'stock' => DB::raw('stock - 1'),
            'version' => DB::raw('version + 1'),
        ]);

    if (!$updated) {
        throw new ConcurrentUpdateException('Produto foi atualizado por outro usuário');
    }
}
```

---

### Cenário 5: Problemas de session depois do deploy

**Problema:**

```
Depois do deploy em production os usuários reclamam:
"Fica deslogando o tempo todo"
```

**Debugging:**

```php
// 1. Checar o driver
// config/session.php
'driver' => env('SESSION_DRIVER', 'file'),

// 2. Checar onde os arquivos ficam
'files' => storage_path('framework/sessions'),

// 3. Checar as permissões
ls -la storage/framework/sessions
# drwxr-xr-x  www-data www-data
```

**Problema:**

```
Load Balancer
├─ Server 1 (sessions em /var/www/storage)
├─ Server 2 (sessions em /var/www/storage)
└─ Server 3 (sessions em /var/www/storage)

Request 1 → Server 1 (criou a session)
Request 2 → Server 2 (sem session — logout)
```

**Solução:**

```env
# .env
SESSION_DRIVER=redis
REDIS_HOST=redis-cluster.example.com

# Agora todos os servidores usam o mesmo Redis
```

---

### Cenário 6: Memory leak no queue worker

**Problema:**

```php
// O queue worker usa cada vez mais memória
// Depois de 1000 jobs: 2GB de memória

class ProcessImageJob implements ShouldQueue
{
    public function handle()
    {
        $image = Image::find($this->imageId);

        // Processar a imagem
        $processed = $this->processImage($image->path);

        // Salvar
        $image->update(['processed_path' => $processed]);

        // ❌ NÃO sai da memória!
    }

    private function processImage($path)
    {
        $img = imagecreatefromjpeg($path);  // Objeto grande na memória
        // ... processamento ...
        return $newPath;
        // $img NÃO foi liberado!
    }
}
```

**Debugging:**

```bash
# Monitorar a memória do worker
watch -n 1 'ps aux | grep "queue:work"'

# A memória sobe: 50MB → 100MB → 500MB → 2GB
```

**Solução:**

```php
class ProcessImageJob implements ShouldQueue
{
    public function handle()
    {
        $image = Image::find($this->imageId);
        $processed = $this->processImage($image->path);
        $image->update(['processed_path' => $processed]);

        // Limpar a memória
        unset($image);
        gc_collect_cycles();
    }

    private function processImage($path)
    {
        $img = imagecreatefromjpeg($path);
        // ... processamento ...
        $newPath = $this->save($img);

        // ✅ Liberar o resource
        imagedestroy($img);

        return $newPath;
    }
}

// Ou reiniciar o worker depois de N jobs
php artisan queue:work --max-jobs=1000
```

---

### Cenário 7: Erro de CORS

**Problema:**

```javascript
// Frontend
fetch('http://api.example.com/users')
    .then(res => res.json())
    .then(data => console.log(data));

// Console:
// Access to fetch at 'http://api.example.com/users' from origin
// 'http://frontend.example.com' has been blocked by CORS policy
```

**Debugging:**

```bash
# Checar os headers
curl -H "Origin: http://frontend.example.com" \
     -H "Access-Control-Request-Method: POST" \
     -H "Access-Control-Request-Headers: Content-Type" \
     -X OPTIONS \
     http://api.example.com/users -v

# Response headers:
# (vazio — CORS não está configurado)
```

**Solução:**

```php
// config/cors.php
return [
    'paths' => ['api/*'],
    'allowed_origins' => ['http://frontend.example.com'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'supports_credentials' => true,
];

// Ou middleware
class CorsMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request)
            ->header('Access-Control-Allow-Origin', 'http://frontend.example.com')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
    }
}
```

---

## Técnicas gerais de debug

**1. Logs:**

```php
// Adicionar logs
Log::info('User checkout', [
    'user_id' => auth()->id(),
    'product_id' => $product->id,
    'stock' => $product->stock,
]);

// Tail dos logs em tempo real
tail -f storage/logs/laravel.log
```

**2. dd() e dump():**

```php
// Para a execução
dd($variable);

// Continua a execução
dump($variable);

// Ray (ferramenta paga)
ray($variable);
```

**3. Laravel Telescope:**

```bash
composer require laravel/telescope --dev
php artisan telescope:install

# http://localhost/telescope
# Queries, Jobs, Exceptions, Logs
```

**4. Xdebug:**

```ini
; php.ini
xdebug.mode=debug
xdebug.start_with_request=yes

# Breakpoint no PhpStorm
# Passar o código passo a passo
```

---

## Na entrevista

> "Debug: N+1 se resolve com eager loading (with). Memory limit: chunk no lugar de all(), stream no export. Query lenta: EXPLAIN para analisar, adicionar índices. Race condition: lockForUpdate ou optimistic locking. Session em vários servidores: Redis no lugar de file. Memory leak: unset(), imagedestroy(), --max-jobs. CORS: config/cors.php ou middleware. Ferramentas: Query Log, Telescope, Xdebug, logs (tail -f)."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
