# 5.1 Eloquent Relationships

## Resumo

> **Eloquent Relationships** — jeito de ligar models sem escrever SQL JOIN.
>
> **Tipos:** hasOne/belongsTo (1:1), hasMany/belongsTo (1:N), belongsToMany (N:N), hasManyThrough (via model intermediário), morphTo/morphMany (polimórficas).
>
> **Importante:** eager loading com `with()` resolve o N+1.

---

## Conteúdo

- [O que é](#o-que-é)
- [Tipos de relacionamento](#tipos-de-relacionamento)
- [Quando usar](#quando-usar)
- [Eager Loading](#eager-loading-problema-n1)
- [Has/WhereHas](#exists-queries-checar-existência)
- [WithCount](#contar-models-relacionados)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Relationships no Eloquent ligam models entre si. Substituem SQL JOIN e simplificam o trabalho com dados relacionados.

**Tipos de relacionamento:**
- One to One (1:1) — hasOne / belongsTo
- One to Many (1:N) — hasMany / belongsTo
- Many to Many (N:N) — belongsToMany
- Has Many Through — hasManyThrough
- Polymorphic — morphTo / morphMany

---

## Tipos de relacionamento

### One to One (hasOne / belongsTo)

```php
// User tem um Profile
class User extends Model
{
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}

// Profile pertence a User
class Profile extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Uso
$user = User::find(1);
$profile = $user->profile;  // SELECT * FROM profiles WHERE user_id = 1

$profile = Profile::find(1);
$user = $profile->user;  // SELECT * FROM users WHERE id = $profile->user_id
```

### One to Many (hasMany / belongsTo)

```php
// User tem muitos Posts
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}

// Post pertence a User
class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

// Uso
$user = User::find(1);
$posts = $user->posts;  // SELECT * FROM posts WHERE user_id = 1

foreach ($user->posts as $post) {
    echo $post->title;
}

// Criar post para o usuário
$user->posts()->create([
    'title' => 'Novo Post',
    'body' => 'Conteúdo',
]);
```

### Many to Many (belongsToMany)

```php
// User tem muitas Roles, Role tem muitos Users
// Tabela pivot: role_user (user_id, role_id)

class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }
}

class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}

// Uso
$user = User::find(1);
$roles = $user->roles;  // JOIN via role_user

// Anexar role
$user->roles()->attach($roleId);
$user->roles()->attach([1, 2, 3]);

// Remover role
$user->roles()->detach($roleId);
$user->roles()->detach();  // Remover todas

// Sincronizar (remove as antigas, adiciona as novas)
$user->roles()->sync([1, 2, 3]);

// Toggle (anexa se não tiver, remove se tiver)
$user->roles()->toggle([1, 2]);
```

### Tabela pivot com campos extras

```php
class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('expires_at', 'is_active')  // Campos extras
            ->withTimestamps();  // created_at, updated_at no pivot
    }
}

// Uso
foreach ($user->roles as $role) {
    echo $role->pivot->expires_at;
    echo $role->pivot->is_active;
}

// Criar com campos do pivot
$user->roles()->attach($roleId, [
    'expires_at' => now()->addYear(),
    'is_active' => true,
]);
```

### Has Many Through

```php
// Country -> User -> Post
// Pegar todos os posts do país via users

class Country extends Model
{
    public function posts()
    {
        return $this->hasManyThrough(
            Post::class,      // Model final
            User::class,      // Model intermediário
            'country_id',     // FK de countries em users
            'user_id',        // FK de users em posts
            'id',             // PK countries
            'id'              // PK users
        );
    }
}

// Uso
$country = Country::find(1);
$posts = $country->posts;  // Todos os posts dos users desse país
```

### Polymorphic Relations (polimórficas)

```php
// Comments para Post e Video
// Tabela comments: id, commentable_id, commentable_type, body

class Comment extends Model
{
    public function commentable()
    {
        return $this->morphTo();
    }
}

class Post extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Video extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

// Uso
$post = Post::find(1);
$comments = $post->comments;

$post->comments()->create([
    'body' => 'Ótimo post!',
]);

$comment = Comment::find(1);
$commentable = $comment->commentable;  // Post ou Video
```

---

## Quando usar

| Relacionamento | Exemplo | Quando usar |
|-------|--------|-------------------|
| **hasOne / belongsTo** | User → Profile | Um para um |
| **hasMany / belongsTo** | User → Posts | Um para muitos |
| **belongsToMany** | User ↔ Roles | Muitos para muitos |
| **hasManyThrough** | Country → Posts (via Users) | Via model intermediário |
| **Polymorphic** | Comments para models diferentes | Relacionamento genérico |

---

## Eager Loading (problema N+1)

### O problema N+1

```php
// ❌ RUIM: N+1 queries
$users = User::all();  // 1 query

foreach ($users as $user) {
    echo $user->profile->bio;  // N queries
}

// ✅ BOM: 2 queries
$users = User::with('profile')->get();  // 2 queries (users + profiles)

foreach ($users as $user) {
    echo $user->profile->bio;  // Sem query extra
}
```

### Eager Loading aninhado

```php
// Eager loading aninhado
$users = User::with(['posts.comments.user'])->get();

// Eager loading condicional
$users = User::with(['posts' => function ($query) {
    $query->where('published', true)->orderBy('created_at', 'desc');
}])->get();
```

### Lazy Eager Loading

```php
$users = User::all();

// Carregar relationships depois
$users->load('posts');
$users->load(['posts.comments']);

// Carregar só se ainda não estiver carregado
$users->loadMissing('posts');
```

---

## Exists Queries (checar existência)

```php
// Users com posts
$users = User::has('posts')->get();

// Users com mais de 3 posts
$users = User::has('posts', '>', 3)->get();

// Users com posts publicados
$users = User::whereHas('posts', function ($query) {
    $query->where('published', true);
})->get();

// Users SEM posts
$users = User::doesntHave('posts')->get();
```

---

## Contar models relacionados

```php
// Conta posts de cada user (1 query)
$users = User::withCount('posts')->get();

foreach ($users as $user) {
    echo $user->posts_count;  // Sem query extra
}

// Com condição
$users = User::withCount(['posts' => function ($query) {
    $query->where('published', true);
}])->get();

// Vários contadores
$users = User::withCount(['posts', 'comments', 'likes'])->get();
```

---

## Exemplo prático

### Relacionamentos de e-commerce

```php
// User hasMany Orders
class User extends Model
{
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrder()
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }

    public function oldestOrder()
    {
        return $this->hasOne(Order::class)->oldestOfMany();
    }
}

// Order belongsTo User, hasMany OrderItems
class Order extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            OrderItem::class,
            'order_id',
            'id',
            'id',
            'product_id'
        );
    }
}

// OrderItem belongsTo Order, belongsTo Product
class OrderItem extends Model
{
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

// Product belongsToMany Categories
class Product extends Model
{
    public function categories()
    {
        return $this->belongsToMany(Category::class)
            ->withTimestamps();
    }
}

// Uso
$user = User::with(['orders.items.product'])->find(1);

foreach ($user->orders as $order) {
    foreach ($order->items as $item) {
        echo "{$item->product->name}: {$item->quantity}";
    }
}
```

### Polymorphic Comments

```php
// Comment para Post, Video, Product
class Comment extends Model
{
    public function commentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

trait HasComments
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Post extends Model
{
    use HasComments;
}

class Video extends Model
{
    use HasComments;
}

// Uso
$post = Post::find(1);
$post->comments()->create([
    'user_id' => auth()->id(),
    'body' => 'Ótimo post!',
]);

// Pegar todos os comments com o user
$comments = $post->comments()->with('user')->get();
```

---

## Na entrevista

**Resposta estruturada:**

**Tipos de relacionamento:**
- hasOne/belongsTo (1:1) — User → Profile
- hasMany/belongsTo (1:N) — User → Posts
- belongsToMany (N:N) — User ↔ Roles (via tabela pivot)
- hasManyThrough — Country → Posts via Users
- Polymorphic — relationship genérica (Comments para models diferentes)

**Eager Loading:**
- `with()` carrega as relationships de antemão (resolve o N+1)
- `load()` carrega depois que você já tem o model
- Aninhado: `with(['posts.comments.user'])`

**Many to Many:**
- `attach()` — anexa
- `detach()` — remove
- `sync()` — sincroniza
- `withPivot()` — campos extras no pivot

**Queries:**
- `has()` / `whereHas()` — filtra pela existência da relationship
- `withCount()` — conta sem carregar
- `doesntHave()` — ausência de relationship

---

## Exercícios práticos

### Exercício 1: Configure belongsToMany com pivot

**Enunciado:** Você tem `User` e `Project`. O usuário pode estar em vários projects, com role (`role`) e data de entrada (`joined_at`). Configure a relationship.

<details>
<summary>Solução</summary>

```php
// Migration: create_project_user_table
Schema::create('project_user', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->foreignId('project_id')->constrained()->onDelete('cascade');
    $table->string('role')->default('member');
    $table->timestamp('joined_at')->useCurrent();
    $table->timestamps();

    $table->primary(['user_id', 'project_id']);
});

// User Model
class User extends Model
{
    public function projects()
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }
}

// Project Model
class Project extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }
}

// Uso
$user->projects()->attach($projectId, [
    'role' => 'admin',
    'joined_at' => now(),
]);

foreach ($user->projects as $project) {
    echo "{$project->name}: {$project->pivot->role}";
}
```
</details>

### Exercício 2: Corrija o N+1

**Enunciado:** O que está errado neste código? Corrija.

```php
$users = User::all();

return response()->json([
    'data' => $users->map(fn($user) => [
        'name' => $user->name,
        'posts_count' => $user->posts->count(),
        'latest_post' => $user->posts->sortByDesc('created_at')->first()?->title,
    ]),
]);
```

<details>
<summary>Solução</summary>

```php
// Problema: N+1 queries para posts

// Solução
$users = User::withCount('posts')
    ->with(['posts' => function ($query) {
        $query->latest()->limit(1);
    }])
    ->get();

return response()->json([
    'data' => $users->map(fn($user) => [
        'name' => $user->name,
        'posts_count' => $user->posts_count,  // Do withCount
        'latest_post' => $user->posts->first()?->title,  // Do with
    ]),
]);
```
</details>

### Exercício 3: Implemente relationship polimórfica

**Enunciado:** Crie o model `Image` que pode ser anexado a `Post`, `User` e `Product`. Implemente a relationship.

<details>
<summary>Solução</summary>

```php
// Migration: create_images_table
Schema::create('images', function (Blueprint $table) {
    $table->id();
    $table->string('url');
    $table->morphs('imageable');  // imageable_id, imageable_type
    $table->timestamps();
});

// Image Model
class Image extends Model
{
    protected $fillable = ['url'];

    public function imageable()
    {
        return $this->morphTo();
    }
}

// Trait para reutilizar
trait HasImages
{
    public function images()
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function image()
    {
        return $this->morphOne(Image::class, 'imageable');
    }
}

// Models
class Post extends Model
{
    use HasImages;
}

class User extends Model
{
    use HasImages;
}

class Product extends Model
{
    use HasImages;
}

// Uso
$post = Post::find(1);
$post->images()->create(['url' => 'https://example.com/image.jpg']);

$images = $post->images;  // Todas as imagens do post

// Pegar todas as imagens com os donos
$images = Image::with('imageable')->get();

foreach ($images as $image) {
    $owner = $image->imageable;  // Post, User ou Product
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
