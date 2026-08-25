# 4.8 Request / Response

## Resumo

> **Request** — objeto do HTTP request: dados de formulário, arquivos, headers, cookies.
>
> **Response** — objeto da resposta: devolve view, json, redirect, download, stream.
>
> **Importante:** Validação com $request->validate(), arquivos com file(), macros para estender, JSON para API.

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
Request — objeto do HTTP request (dados, headers, arquivos). Response — objeto da resposta (conteúdo, status, headers).

**O essencial:**
- `Request` — dados de entrada ($request->input(), $request->file())
- `Response` — devolver dados (view, json, download)

---

## Como funciona

**Request (pegar os dados):**

```php
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function store(Request $request)
    {
        // Pegar o valor
        $name = $request->input('name');
        $email = $request->input('email', 'default@example.com');  // Com default

        // Todos os dados
        $all = $request->all();
        $only = $request->only(['name', 'email']);
        $except = $request->except(['password']);

        // Query params (?page=1)
        $page = $request->query('page', 1);

        // Route params (/users/{id})
        $id = $request->route('id');

        // Headers
        $token = $request->header('Authorization');
        $userAgent = $request->userAgent();

        // Método do request
        $method = $request->method();  // GET, POST, etc.
        $isPost = $request->isMethod('post');

        // URL
        $url = $request->url();        // http://example.com/users
        $fullUrl = $request->fullUrl();  // http://example.com/users?page=1
        $path = $request->path();      // users

        // IP
        $ip = $request->ip();

        // Request JSON
        if ($request->isJson()) {
            $data = $request->json()->all();
        }

        // Checar se existe
        if ($request->has('name')) {
            // name está presente
        }

        if ($request->filled('name')) {
            // name está presente e não está vazio
        }
    }
}
```

**Arquivos no Request:**

```php
public function upload(Request $request)
{
    // Pegar o arquivo
    $file = $request->file('photo');

    // Checar se fez upload
    if ($request->hasFile('photo')) {
        // Info do arquivo
        $extension = $file->extension();
        $size = $file->getSize();
        $originalName = $file->getClientOriginalName();

        // Salvar o arquivo
        $path = $file->store('photos');  // storage/app/photos/
        $path = $file->storeAs('photos', 'custom-name.jpg');

        // Storage público
        $path = $file->storePublicly('avatars', 's3');
    }
}
```

**Response (devolver dados):**

```php
use Illuminate\Http\Response;

class UserController extends Controller
{
    // View (HTML)
    public function index()
    {
        return view('users.index', ['users' => User::all()]);
    }

    // JSON
    public function apiIndex()
    {
        return response()->json([
            'data' => User::all(),
            'message' => 'Sucesso'
        ]);
    }

    // Status customizado
    public function show(User $user)
    {
        if (!$user->isActive()) {
            return response()->json(['error' => 'Usuário inativo'], 403);
        }

        return response()->json($user);
    }

    // Redirect
    public function store(Request $request)
    {
        $user = User::create($request->validated());

        return redirect()->route('users.show', $user);
    }

    // Download de arquivo
    public function download()
    {
        return response()->download(storage_path('app/file.pdf'));
    }

    // Stream de arquivo
    public function stream()
    {
        return response()->file(storage_path('app/video.mp4'));
    }

    // No content (204)
    public function destroy(User $user)
    {
        $user->delete();

        return response()->noContent();
    }
}
```

**Headers no Response:**

```php
return response()->json($data)
    ->header('X-Custom-Header', 'Valor')
    ->header('Content-Type', 'application/json')
    ->withHeaders([
        'X-Header-One' => 'Valor 1',
        'X-Header-Two' => 'Valor 2',
    ]);
```

**Cookies no Response:**

```php
return response('Conteúdo')
    ->cookie('nome', 'valor', $minutes, $path, $domain, $secure, $httpOnly);

// Ou
return response('Conteúdo')->withCookie(cookie('nome', 'valor', 60));
```

---

## Quando usar

**Request:**
- `$request->input()` — dados do formulário
- `$request->query()` — query params
- `$request->file()` — upload de arquivos
- `$request->header()` — headers

**Response:**
- `response()->json()` — respostas de API
- `view()` — páginas HTML
- `redirect()` — redirecionamentos
- `response()->download()` — download de arquivos

---

## Exemplo prático

**API Controller com JSON Response:**

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Filtro
        if ($request->filled('category')) {
            $query->where('category_id', $request->input('category'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', "%{$request->input('search')}%");
        }

        // Ordenação
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginação
        $perPage = $request->input('per_page', 20);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    public function store(CreateProductRequest $request)
    {
        $product = Product::create($request->validated());

        // Upload da imagem
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $product->update(['image_path' => $path]);
        }

        return new ProductResource($product);
    }

    public function show(Product $product)
    {
        // Devolver com relationships
        return new ProductResource($product->load(['category', 'reviews']));
    }

    public function update(CreateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return new ProductResource($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }
}
```

**Upload de arquivos:**

```php
class AvatarController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',  // 2MB max
        ]);

        $user = $request->user();

        // Apagar o avatar antigo
        if ($user->avatar_path) {
            Storage::delete($user->avatar_path);
        }

        // Salvar o novo
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar enviado',
            'url' => Storage::url($path),
        ]);
    }
}
```

**Stream de arquivos grandes:**

```php
class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            // Headers do CSV
            fputcsv($handle, ['ID', 'Nome', 'Email', 'Criado em']);

            // Carrega um por um (economiza memória)
            User::cursor()->each(function ($user) use ($handle) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->created_at,
                ]);
            });

            fclose($handle);
        }, 'users.csv');
    }
}
```

**Classes de Response customizadas:**

```php
// app/Http/Responses/ApiResponse.php
class ApiResponse
{
    public static function success($data, string $message = 'Sucesso', int $status = 200)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = [])
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}

// Uso
class ProductController extends Controller
{
    public function store(Request $request)
    {
        $product = Product::create($request->validated());

        return ApiResponse::success($product, 'Produto criado', 201);
    }

    public function destroy(Product $product)
    {
        if ($product->orders()->exists()) {
            return ApiResponse::error('Não dá para apagar produto com pedidos', 422);
        }

        $product->delete();

        return ApiResponse::success(null, 'Produto apagado');
    }
}
```

**Request Macro (estender):**

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Http\Request;

public function boot(): void
{
    // Método customizado no Request
    Request::macro('isMobile', function () {
        return str_contains($this->userAgent(), 'Mobile');
    });

    Request::macro('ipInfo', function () {
        return [
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'is_mobile' => $this->isMobile(),
        ];
    });
}

// Uso
if ($request->isMobile()) {
    return view('mobile.index');
}

Log::info('Info do usuário', $request->ipInfo());
```

**Response Macro:**

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Response;

public function boot(): void
{
    Response::macro('success', function ($data, $message = 'Sucesso') {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    });

    Response::macro('error', function ($message, $status = 400) {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    });
}

// Uso
return response()->success($user, 'Usuário criado');
return response()->error('Credenciais inválidas', 401);
```

**Validação e erros no Request:**

```php
class UserController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);

        // Se a validação falhar:
        // - Web: redirect de volta com os erros
        // - API: JSON com os erros (422)
    }
}

// Tratamento customizado de erro de validação
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'errors' => $validator->errors()
        ], 422);
    }

    // Segue...
}
```

---

## Na entrevista

> "Request tem os dados do request: input() no formulário, query() nos query params, file() no arquivo, header() no header. Response devolve: json() na API, view() no HTML, redirect() para redirecionar, download() para arquivo. Arquivo grande eu uso streamDownload() — economiza memória. Macro em Request e Response para estender. validate() devolve 422 na API e redirect no web."

---

## Exercícios práticos

### Exercício 1: Implemente upload de avatar com validação e otimização

**Enunciado:** O usuário faz upload do avatar. Precisa: validação (image, max 2MB), redimensionar para 300x300, salvar no public storage, apagar o antigo.

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/AvatarController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class AvatarController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = $request->user();

        // Apagar o avatar antigo
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        // Pegar o arquivo
        $file = $request->file('avatar');

        // Otimizar a imagem
        $image = Image::make($file)
            ->fit(300, 300)
            ->encode('jpg', 85);

        // Gerar nome único
        $filename = 'avatars/' . $user->id . '_' . time() . '.jpg';

        // Salvar no public storage
        Storage::disk('public')->put($filename, $image->stream());

        // Atualizar o usuário
        $user->update(['avatar_path' => $filename]);

        return response()->json([
            'message' => 'Avatar enviado',
            'url' => Storage::url($filename),
        ]);
    }

    public function delete(Request $request)
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'message' => 'Avatar apagado',
        ]);
    }
}

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/avatar', [AvatarController::class, 'upload']);
    Route::delete('/avatar', [AvatarController::class, 'delete']);
});
```
</details>

### Exercício 2: Crie um API Response Helper com estrutura consistente

**Enunciado:** Implemente um helper para respostas de API no mesmo formato: success, error, validation error.

<details>
<summary>Solução</summary>

```php
// app/Http/Responses/ApiResponse.php
namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'Sucesso',
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    public static function validationError(
        array $errors,
        string $message = 'Falha na validação'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    public static function notFound(string $message = 'Recurso não encontrado'): JsonResponse
    {
        return self::error($message, 404);
    }

    public static function unauthorized(string $message = 'Não autenticado'): JsonResponse
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Acesso negado'): JsonResponse
    {
        return self::error($message, 403);
    }
}

// Uso no controller
namespace App\Http\Controllers\Api;

use App\Http\Responses\ApiResponse;
use App\Http\Requests\CreateProductRequest;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::paginate(20);

        return ApiResponse::success($products, 'Produtos listados');
    }

    public function store(CreateProductRequest $request)
    {
        $product = Product::create($request->validated());

        return ApiResponse::success($product, 'Produto criado', 201);
    }

    public function show(Product $product)
    {
        return ApiResponse::success($product);
    }

    public function destroy(Product $product)
    {
        if ($product->orders()->exists()) {
            return ApiResponse::error(
                'Não dá para apagar produto com pedidos',
                422
            );
        }

        $product->delete();

        return ApiResponse::success(null, 'Produto apagado');
    }
}

// app/Exceptions/Handler.php — tratamento de erros
public function render($request, Throwable $exception)
{
    if ($request->wantsJson()) {
        if ($exception instanceof ModelNotFoundException) {
            return ApiResponse::notFound();
        }

        if ($exception instanceof AuthenticationException) {
            return ApiResponse::unauthorized();
        }

        if ($exception instanceof AuthorizationException) {
            return ApiResponse::forbidden();
        }

        if ($exception instanceof ValidationException) {
            return ApiResponse::validationError($exception->errors());
        }
    }

    return parent::render($request, $exception);
}
```
</details>

### Exercício 3: Implemente Stream Export para volume grande

**Enunciado:** Crie um endpoint para exportar usuários em CSV com streaming (sem carregar tudo na memória).

<details>
<summary>Solução</summary>

```php
// app/Http/Controllers/ExportController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ExportController extends Controller
{
    public function exportUsers(Request $request)
    {
        $filters = $request->validate([
            'role' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
        ]);

        $filename = 'users_' . now()->format('Y-m-d_His') . '.csv';

        return Response::streamDownload(function () use ($filters) {
            $handle = fopen('php://output', 'w');

            // Headers do CSV
            fputcsv($handle, [
                'ID',
                'Nome',
                'Email',
                'Papel',
                'Criado em',
                'Último login',
            ]);

            // Montar a query
            $query = User::query();

            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['from_date'])) {
                $query->whereDate('created_at', '>=', $filters['from_date']);
            }

            if (!empty($filters['to_date'])) {
                $query->whereDate('created_at', '<=', $filters['to_date']);
            }

            // Cursor para economizar memória (um registro por vez)
            $query->cursor()->each(function ($user) use ($handle) {
                fputcsv($handle, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->last_login_at?->format('Y-m-d H:i:s') ?? 'Nunca',
                ]);
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    public function exportJson(Request $request)
    {
        return Response::streamDownload(function () {
            echo "[\n";

            $first = true;

            User::cursor()->each(function ($user) use (&$first) {
                if (!$first) {
                    echo ",\n";
                }

                echo json_encode([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]);

                $first = false;
            });

            echo "\n]";
        }, 'users.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}

// routes/api.php
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/export/users', [ExportController::class, 'exportUsers']);
    Route::get('/export/users/json', [ExportController::class, 'exportJson']);
});
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
