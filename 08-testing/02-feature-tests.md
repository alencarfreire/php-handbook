# 7.2 Feature tests

## Resumo

> **Feature tests** — testam o cenário inteiro via HTTP. Você cobre o user flow de verdade: request, banco, response.
>
> **Criar:** `php artisan make:test UserControllerTest`. RefreshDatabase desfaz as transações depois do teste.
>
> **Importante:** `actingAs($user)` para autenticar. Assertions: `assertStatus()`, `assertJson()`, `assertDatabaseHas()`. Fakes: `Storage::fake()`, `Queue::fake()`, `Mail::fake()`.

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
Feature tests testam a funcionalidade da app inteira (request HTTP, banco, autenticação). Você testa o cenário real do usuário.

**O essencial:**
- Testam HTTP endpoints
- Usam o banco (transactions)
- Checam o flow inteiro

---

## Como funciona

**Criar o teste:**

```bash
# Feature test (com banco)
php artisan make:test UserControllerTest

# Cria em tests/Feature/
```

**Feature test básico:**

```php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;  // Desfaz as transações depois do teste

    public function test_user_can_view_profile(): void
    {
        // Arrange: criar o usuário
        $user = User::factory()->create();

        // Act: enviar GET
        $response = $this->actingAs($user)->get('/profile');

        // Assert: checar a response
        $response->assertStatus(200);
        $response->assertSee($user->name);
    }

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->get('/profile');

        $response->assertStatus(302);  // Redirect
        $response->assertRedirect('/login');
    }
}
```

**HTTP Assertions:**

```php
// Status code
$response->assertStatus(200);
$response->assertOk();  // 200
$response->assertCreated();  // 201
$response->assertNoContent();  // 204
$response->assertNotFound();  // 404
$response->assertForbidden();  // 403
$response->assertUnauthorized();  // 401

// Redirect
$response->assertRedirect('/login');
$response->assertRedirectToRoute('login');

// View
$response->assertViewIs('users.index');
$response->assertViewHas('users');

// JSON
$response->assertJson(['success' => true]);
$response->assertJsonStructure(['data' => ['id', 'name']]);
$response->assertJsonPath('data.name', 'João');

// Headers
$response->assertHeader('Content-Type', 'application/json');

// Cookies
$response->assertCookie('name', 'value');

// Session
$response->assertSessionHas('status', 'success');
$response->assertSessionHasErrors(['email']);
```

**Database Assertions:**

```php
// Checar o registro no banco
$this->assertDatabaseHas('users', [
    'email' => 'joao@email.com',
]);

$this->assertDatabaseMissing('users', [
    'email' => 'excluido@email.com',
]);

// Quantidade de registros
$this->assertDatabaseCount('users', 10);

// Soft deletes
$this->assertSoftDeleted('users', ['id' => 1]);
```

---

## Quando usar

**Feature tests para:**
- API endpoints
- Operações CRUD
- Autenticação/autorização
- Validação de form
- User flows completos

**Unit tests para:**
- Lógica de negócio (Services)
- Cálculos
- Componentes isolados

---

## Exemplo prático

**Testar CRUD:**

```php
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_posts_list(): void
    {
        Post::factory()->count(5)->create();

        $response = $this->get('/posts');

        $response->assertStatus(200);
        $response->assertViewIs('posts.index');
        $response->assertViewHas('posts');
    }

    public function test_user_can_create_post(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/posts', [
            'title' => 'Post de teste',
            'body' => 'Conteúdo de teste',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/posts');

        $this->assertDatabaseHas('posts', [
            'title' => 'Post de teste',
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_create_post(): void
    {
        $response = $this->post('/posts', [
            'title' => 'Post de teste',
            'body' => 'Conteúdo de teste',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/posts', [
            'title' => '',  // Vazio
        ]);

        $response->assertSessionHasErrors(['title', 'body']);
    }

    public function test_user_can_update_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/posts/{$post->id}", [
            'title' => 'Título atualizado',
            'body' => 'Conteúdo atualizado',
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Título atualizado',
        ]);
    }

    public function test_user_cannot_update_others_post(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->put("/posts/{$post->id}", [
            'title' => 'Hackeado',
        ]);

        $response->assertStatus(403);  // Forbidden
    }

    public function test_user_can_delete_own_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/posts/{$post->id}");

        $response->assertStatus(302);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
```

**Testar API:**

```php
class ApiPostControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_posts(): void
    {
        Post::factory()->count(25)->create();

        $response = $this->getJson('/api/posts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'body', 'created_at'],
            ],
            'meta' => ['current_page', 'total'],
        ]);
        $response->assertJsonCount(20, 'data');  // Paginação padrão
    }

    public function test_creates_post_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/posts', [
                'title' => 'Post da API',
                'body' => 'Criado via API',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'title' => 'Post da API',
            ],
        ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Post da API',
            'user_id' => $user->id,
        ]);
    }

    public function test_returns_422_for_invalid_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/posts', [
            'title' => '',  // Inválido
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'body']);
    }

    public function test_filters_posts_by_status(): void
    {
        Post::factory()->count(5)->create(['published' => true]);
        Post::factory()->count(3)->create(['published' => false]);

        $response = $this->getJson('/api/posts?status=published');

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }
}
```

**Testar autenticação:**

```php
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertStatus(302);
        $response->assertRedirect('/');
        $this->assertGuest();
    }
}
```

**Testar File Upload:**

```php
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user)->post('/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(302);

        // Checar se o arquivo foi salvo
        Storage::disk('public')->assertExists('avatars/' . $file->hashName());

        // Checar o registro no banco
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_path' => 'avatars/' . $file->hashName(),
        ]);
    }

    public function test_validates_file_type(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf');

        $response = $this->actingAs($user)->post('/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }
}
```

**Testar Queue/Job:**

```php
use Illuminate\Support\Facades\Queue;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_creation_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post('/orders', [
            'product_id' => 1,
            'quantity' => 2,
        ]);

        Queue::assertPushed(ProcessOrder::class, function ($job) use ($user) {
            return $job->order->user_id === $user->id;
        });
    }
}
```

**Testar Notification:**

```php
use Illuminate\Support\Facades\Notification;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_receives_welcome_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        event(new UserRegistered($user));

        Notification::assertSentTo($user, WelcomeNotification::class);
    }
}
```

---

## Na entrevista

> "Feature tests checam o cenário inteiro via HTTP. RefreshDatabase desfaz as transações depois do teste. actingAs($user) para request autenticado. Assertions: assertStatus, assertJson, assertDatabaseHas. Eu testo CRUD, autenticação, validation, file upload. Storage::fake() para arquivo, Queue::fake() para job, Notification::fake() para notificação. Factory para dado de teste. API eu testo com getJson/postJson."

---

## Exercícios práticos

### Exercício 1: CRUD tests para Article

**Enunciado:** Escreva o conjunto completo de Feature tests para ArticleController: index, create, update, delete. Cheque autorização e validação.

<details>
<summary>Solução</summary>

```php
// tests/Feature/ArticleControllerTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{User, Article};
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_articles_list(): void
    {
        Article::factory()->count(5)->create(['published' => true]);

        $response = $this->get('/articles');

        $response->assertStatus(200);
        $response->assertViewIs('articles.index');
        $response->assertViewHas('articles');
    }

    public function test_user_can_create_article(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/articles', [
            'title' => 'Artigo de teste',
            'content' => 'Este é um conteúdo de teste',
            'published' => true,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/articles');

        $this->assertDatabaseHas('articles', [
            'title' => 'Artigo de teste',
            'user_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_create_article(): void
    {
        $response = $this->post('/articles', [
            'title' => 'Artigo de teste',
            'content' => 'Conteúdo',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/articles', [
            'title' => '',  // Vazio
            'content' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'content']);
    }

    public function test_user_can_update_own_article(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->put("/articles/{$article->id}", [
            'title' => 'Título atualizado',
            'content' => 'Conteúdo atualizado',
            'published' => true,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'title' => 'Título atualizado',
        ]);
    }

    public function test_user_cannot_update_others_article(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $article = Article::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)->put("/articles/{$article->id}", [
            'title' => 'Título hackeado',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('articles', [
            'id' => $article->id,
            'title' => 'Título hackeado',
        ]);
    }

    public function test_user_can_delete_own_article(): void
    {
        $user = User::factory()->create();
        $article = Article::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete("/articles/{$article->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }
}
```
</details>

### Exercício 2: API tests com Pagination e Filtering

**Enunciado:** Crie o API endpoint `/api/products` com paginação e filtro por categoria. Escreva testes para todos os cenários.

<details>
<summary>Solução</summary>

```php
// tests/Feature/Api/ProductControllerTest.php
namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\{Product, Category, User};
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_products(): void
    {
        Product::factory()->count(30)->create();

        $response = $this->getJson('/api/products');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'price', 'created_at'],
            ],
            'meta' => [
                'current_page',
                'total',
                'per_page',
            ],
            'links',
        ]);

        $response->assertJsonCount(15, 'data');  // Padrão: 15 por página
    }

    public function test_filters_products_by_category(): void
    {
        $category1 = Category::factory()->create(['name' => 'Eletrônicos']);
        $category2 = Category::factory()->create(['name' => 'Livros']);

        Product::factory()->count(5)->create(['category_id' => $category1->id]);
        Product::factory()->count(3)->create(['category_id' => $category2->id]);

        $response = $this->getJson("/api/products?category={$category1->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data');
    }

    public function test_filters_products_by_price_range(): void
    {
        Product::factory()->create(['price' => 50]);
        Product::factory()->create(['price' => 150]);
        Product::factory()->create(['price' => 250]);

        $response = $this->getJson('/api/products?min_price=100&max_price=200');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.price', 150);
    }

    public function test_creates_product_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/products', [
                'name' => 'Produto novo',
                'price' => 99.99,
                'description' => 'Descrição de teste',
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'name' => 'Produto novo',
                'price' => 99.99,
            ],
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Produto novo',
        ]);
    }

    public function test_returns_401_without_token(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Produto',
            'price' => 50,
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_product_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/products', [
            'name' => '',  // Obrigatório
            'price' => 'invalid',  // Tem que ser numérico
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'price']);
    }

    public function test_shows_single_product(): void
    {
        $product = Product::factory()->create([
            'name' => 'Produto de teste',
            'price' => 123.45,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $product->id,
                'name' => 'Produto de teste',
                'price' => 123.45,
            ],
        ]);
    }

    public function test_returns_404_for_nonexistent_product(): void
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404);
    }
}
```
</details>

### Exercício 3: File Upload com Storage fake

**Enunciado:** Escreva o teste de upload de avatar do usuário. Cheque validação de tipo e tamanho do arquivo.

<details>
<summary>Solução</summary>

```php
// tests/Feature/AvatarUploadTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AvatarUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_upload_avatar(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        // Checar se o arquivo foi salvo
        $avatarPath = 'avatars/' . $file->hashName();
        Storage::disk('public')->assertExists($avatarPath);

        // Checar o registro no banco
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar_path' => $avatarPath,
        ]);
    }

    public function test_validates_file_is_image(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
        Storage::disk('public')->assertMissing('avatars/' . $file->hashName());
    }

    public function test_validates_file_size(): void
    {
        $user = User::factory()->create();
        // 5MB (máximo 2MB)
        $file = UploadedFile::fake()->image('large.jpg')->size(5000);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_validates_image_dimensions(): void
    {
        $user = User::factory()->create();
        // Imagem pequena demais
        $file = UploadedFile::fake()->image('tiny.jpg', 50, 50);

        $response = $this->actingAs($user)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_deletes_old_avatar_when_uploading_new(): void
    {
        $user = User::factory()->create();

        // Upload do primeiro avatar
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $oldFile]);
        $oldPath = 'avatars/' . $oldFile->hashName();

        // Upload do novo avatar
        $newFile = UploadedFile::fake()->image('new.jpg');
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => $newFile]);
        $newPath = 'avatars/' . $newFile->hashName();

        // Arquivo antigo removido
        Storage::disk('public')->assertMissing($oldPath);
        // Arquivo novo existe
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_guest_cannot_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post('/profile/avatar', ['avatar' => $file]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
