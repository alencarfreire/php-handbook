# 8.5 Autorização (Gates, Policies)

## Resumo

> **Autorização** — checagem de permissão (o que você pode fazer?).
>
> **Ferramentas:** Gates para checagem simples (closure), Policies para agrupar a lógica em volta do model (classe).
>
> **Importante:** `authorize()` no controller joga 403 se não tiver permissão. `@can` no Blade para mostrar/esconder. Spatie Permission para roles e permissões.

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
Autorização — checagem de permissão (o que você pode fazer?). No Laravel você usa Gates e Policies.

**A diferença:**
- **Gates** — checagem simples (closure)
- **Policies** — lógica agrupada por model (classe)

---

## Como funciona

**Gates (checagem simples):**

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    // Gate simples
    Gate::define('view-admin', function (User $user) {
        return $user->isAdmin();
    });

    // Com model
    Gate::define('update-post', function (User $user, Post $post) {
        return $user->id === $post->user_id;
    });
}

// Uso no controller
if (Gate::allows('view-admin')) {
    // Usuário é admin
}

if (Gate::denies('update-post', $post)) {
    abort(403);
}

// Via middleware
Route::middleware('can:view-admin')->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// Via authorize()
$this->authorize('update-post', $post);  // 403 se não tiver permissão
```

**Policies (para models):**

```bash
php artisan make:policy PostPolicy --model=Post
```

```php
// app/Policies/PostPolicy.php
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;  // Todo mundo pode ver a lista
    }

    public function view(User $user, Post $post): bool
    {
        return $post->published || $user->id === $post->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isVerified();
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->user_id || $user->isAdmin();
    }
}

// Registro no AuthServiceProvider
protected $policies = [
    Post::class => PostPolicy::class,
];

// Uso
class PostController extends Controller
{
    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);  // 403 se não tiver permissão

        $post->update($request->validated());

        return redirect()->route('posts.show', $post);
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('posts.index');
    }
}
```

---

## Quando usar

**Gates para:**
- Checagem simples (isAdmin)
- Sem amarrar a um model
- Permissões globais

**Policies para:**
- Permissão no model (CRUD)
- Agrupar a lógica
- Binding automático

---

## Exemplo prático

**Policy com roles diferentes:**

```php
class PostPolicy
{
    // Roda antes de todos os métodos
    public function before(User $user): ?bool
    {
        if ($user->isAdmin()) {
            return true;  // Admin pode tudo
        }

        return null;  // Segue a checagem
    }

    public function update(User $user, Post $post): bool
    {
        // Autor ou moderador
        return $user->id === $post->user_id || $user->isModerator();
    }

    public function delete(User $user, Post $post): bool
    {
        // Só o autor
        return $user->id === $post->user_id;
    }

    public function publish(User $user, Post $post): bool
    {
        // Autor ou moderador
        return $user->id === $post->user_id || $user->isModerator();
    }
}
```

**Checagem no Blade:**

```blade
@can('update', $post)
    <a href="{{ route('posts.edit', $post) }}">Editar</a>
@endcan

@cannot('delete', $post)
    <p>Você não pode excluir este post</p>
@endcannot

{{-- Sem model --}}
@can('view-admin')
    <a href="/admin">Painel admin</a>
@endcan
```

**API Resource com checagem de permissão:**

```php
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,

            // Campos condicionais conforme a permissão
            'can_edit' => $request->user()?->can('update', $this->resource),
            'can_delete' => $request->user()?->can('delete', $this->resource),
        ];
    }
}
```

**Middleware de autorização:**

```php
// Na rota
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('can:update,post');  // post = parâmetro da rota

// Ou no controller
public function __construct()
{
    $this->authorizeResource(Post::class, 'post');
}
// Checa permissão sozinho em todos os métodos CRUD
```

**Checagem de guest:**

```php
class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        // Guest só vê o que está publicado
        if (!$user) {
            return $post->published;
        }

        // Autenticado vê os próprios rascunhos
        return $post->published || $user->id === $post->user_id;
    }
}

// Uso
Gate::check('view', $post);  // Funciona para guest
```

**Response (no lugar de 403):**

```php
class PostPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Post $post): Response
    {
        if ($user->id !== $post->user_id) {
            return Response::deny('Você não é o dono deste post.');
        }

        return Response::allow();
    }
}

// No controller
try {
    $this->authorize('update', $post);
} catch (AuthorizationException $e) {
    // $e->getMessage() = 'Você não é o dono deste post.'
}
```

**Abilities (Sanctum para API):**

```php
// Criar token com abilities
$token = $user->createToken('token-name', ['post:create', 'post:update'])->plainTextToken;

// Checar ability
if ($user->tokenCan('post:create')) {
    // Pode criar posts
}

// Middleware
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'store'])
        ->middleware('ability:post:create');

    Route::put('/posts/{post}', [PostController::class, 'update'])
        ->middleware('ability:post:update');
});
```

**Autorização por role:**

```php
// User model
class User extends Authenticatable
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }
}

// Gates
Gate::define('view-admin', fn(User $user) => $user->hasRole('admin'));
Gate::define('moderate-posts', fn(User $user) => $user->hasAnyRole(['admin', 'moderator']));

// Middleware
Route::middleware('can:view-admin')->group(function () {
    // Só para admin
});
```

**Spatie Permission (pacote popular):**

```bash
composer require spatie/laravel-permission
```

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Criar roles e permissões
$role = Role::create(['name' => 'admin']);
$permission = Permission::create(['name' => 'edit posts']);

$role->givePermissionTo($permission);
$user->assignRole('admin');

// Checagem
if ($user->can('edit posts')) {
    // Pode editar
}

if ($user->hasRole('admin')) {
    // Admin
}

// Middleware
Route::middleware(['role:admin'])->group(function () {
    // ...
});

Route::middleware(['permission:edit posts'])->group(function () {
    // ...
});
```

---

## Na entrevista

> "Autorização checa o que você pode fazer. Gates para checagem simples (Gate::define()), Policies para models (PostPolicy). authorize() no controller joga 403. @can no Blade. Middleware can:update,post. before() na Policy para admin (return true). Checagem de guest com ?User. Spatie Permission para roles e permissões. Sanctum abilities para token de API. authorizeResource() checa o CRUD sozinho."

---

## Exercícios práticos

### Exercício 1: Crie uma Policy de comentários

**Enunciado:** Implemente `CommentPolicy` com as regras: autor edita/exclui os próprios comentários, moderador exclui qualquer um, admin pode tudo.

<details>
<summary>Solução</summary>

```php
// 1. Criar a Policy
php artisan make:policy CommentPolicy --model=Comment

// app/Policies/CommentPolicy.php
class CommentPolicy
{
    /**
     * Roda antes de todos os métodos
     * Admin pode tudo
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null; // Segue a checagem
    }

    public function viewAny(User $user): bool
    {
        return true; // Todo mundo pode ver a lista
    }

    public function view(?User $user, Comment $comment): bool
    {
        return true; // Comentários são públicos
    }

    public function create(User $user): bool
    {
        return $user->email_verified_at !== null; // Só quem verificou o email
    }

    public function update(User $user, Comment $comment): bool
    {
        // Só o autor pode editar
        return $user->id === $comment->user_id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        // Autor ou moderador podem excluir
        return $user->id === $comment->user_id || $user->role === 'moderator';
    }
}

// 2. Registro no AuthServiceProvider
protected $policies = [
    Comment::class => CommentPolicy::class,
];

// 3. Uso no controller
class CommentController extends Controller
{
    public function update(Request $request, Comment $comment)
    {
        $this->authorize('update', $comment);

        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment->update($validated);

        return redirect()->back()->with('success', 'Comentário atualizado');
    }

    public function destroy(Comment $comment)
    {
        $this->authorize('delete', $comment);

        $comment->delete();

        return redirect()->back()->with('success', 'Comentário excluído');
    }
}

// 4. No Blade
@can('update', $comment)
    <a href="{{ route('comments.edit', $comment) }}">Editar</a>
@endcan

@can('delete', $comment)
    <form method="POST" action="{{ route('comments.destroy', $comment) }}">
        @csrf
        @method('DELETE')
        <button type="submit">Excluir</button>
    </form>
@endcan

// 5. User Model (roles)
class User extends Authenticatable
{
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'moderator';
    }
}
```
</details>

### Exercício 2: Implemente autorização por role

**Enunciado:** Crie um sistema de roles (admin, editor, viewer) com permissões diferentes nos posts.

<details>
<summary>Solução</summary>

```php
// 1. Migration das roles
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique();
    $table->string('display_name');
    $table->timestamps();
});

Schema::create('role_user', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('role_id')->constrained()->onDelete('cascade');
    $table->timestamps();

    $table->primary(['user_id', 'role_id']);
});

// 2. Models
class Role extends Model
{
    protected $fillable = ['name', 'display_name'];

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

class User extends Authenticatable
{
    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()->whereIn('name', $roles)->exists();
    }

    public function assignRole(string $role): void
    {
        $roleModel = Role::where('name', $role)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
    }
}

// 3. Gates no AuthServiceProvider
public function boot(): void
{
    Gate::define('manage-posts', function (User $user) {
        return $user->hasAnyRole(['admin', 'editor']);
    });

    Gate::define('view-admin', function (User $user) {
        return $user->hasRole('admin');
    });

    Gate::define('edit-posts', function (User $user) {
        return $user->hasAnyRole(['admin', 'editor']);
    });

    Gate::define('delete-posts', function (User $user) {
        return $user->hasRole('admin');
    });
}

// 4. PostPolicy com roles
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Todo mundo pode ver
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'editor']);
    }

    public function update(User $user, Post $post): bool
    {
        // Autor ou editor
        return $user->id === $post->user_id || $user->hasRole('editor');
    }

    public function delete(User $user, Post $post): bool
    {
        // Só admin
        return $user->hasRole('admin');
    }
}

// 5. Middleware de roles
class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!$request->user() || !$request->user()->hasAnyRole($roles)) {
            abort(403, 'Sem acesso');
        }

        return $next($request);
    }
}

// Registro no Kernel.php
protected $middlewareAliases = [
    'role' => \App\Http\Middleware\CheckRole::class,
];

// 6. Uso nas rotas
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

Route::middleware(['auth', 'role:admin,editor'])->group(function () {
    Route::resource('posts', PostController::class);
});

// 7. Seeder das roles
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'admin', 'display_name' => 'Administrador']);
        Role::create(['name' => 'editor', 'display_name' => 'Editor']);
        Role::create(['name' => 'viewer', 'display_name' => 'Visualizador']);

        // Atribuir role ao primeiro usuário
        $admin = User::first();
        $admin->assignRole('admin');
    }
}
```
</details>

### Exercício 3: Use o Spatie Permission

**Enunciado:** Monte o sistema de permissões e roles com o pacote Spatie Permission.

<details>
<summary>Solução</summary>

```php
// 1. Instalação
composer require spatie/laravel-permission

// 2. Publicar migrations e config
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

// 3. User Model
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;

    // Resto do código...
}

// 4. Criar roles e permissões (Seeder)
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpar o cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Criar permissões
        Permission::create(['name' => 'view posts']);
        Permission::create(['name' => 'create posts']);
        Permission::create(['name' => 'edit posts']);
        Permission::create(['name' => 'delete posts']);
        Permission::create(['name' => 'publish posts']);

        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'edit users']);
        Permission::create(['name' => 'delete users']);

        // Criar roles e atribuir permissões
        $viewer = Role::create(['name' => 'viewer']);
        $viewer->givePermissionTo('view posts');

        $editor = Role::create(['name' => 'editor']);
        $editor->givePermissionTo(['view posts', 'create posts', 'edit posts']);

        $admin = Role::create(['name' => 'admin']);
        $admin->givePermissionTo(Permission::all());
    }
}

// 5. Atribuir roles aos usuários
$user = User::find(1);
$user->assignRole('admin');

$user->assignRole(['editor', 'viewer']); // Várias roles

// Permissão direta (sem role)
$user->givePermissionTo('edit posts');

// 6. Checar permissão
if ($user->can('edit posts')) {
    // Pode editar
}

if ($user->hasRole('admin')) {
    // Admin
}

if ($user->hasAnyRole(['admin', 'editor'])) {
    // Admin ou editor
}

// 7. Policy com Spatie
class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->can('edit posts') &&
               ($user->id === $post->user_id || $user->hasRole('admin'));
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('delete posts');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can('publish posts');
    }
}

// 8. Middleware
Route::middleware(['role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

Route::middleware(['permission:edit posts'])->group(function () {
    Route::resource('posts', PostController::class);
});

// Ou vários
Route::middleware(['role_or_permission:admin|edit posts'])->group(function () {
    // ...
});

// 9. Diretivas Blade
@role('admin')
    <a href="/admin">Painel admin</a>
@endrole

@hasrole('editor')
    <a href="/posts/create">Criar post</a>
@endhasrole

@can('edit posts')
    <a href="{{ route('posts.edit', $post) }}">Editar</a>
@endcan

// 10. API Resource com permissões
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'permissions' => [
                'can_edit' => $request->user()?->can('edit posts'),
                'can_delete' => $request->user()?->can('delete posts'),
                'can_publish' => $request->user()?->can('publish posts'),
            ],
        ];
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
