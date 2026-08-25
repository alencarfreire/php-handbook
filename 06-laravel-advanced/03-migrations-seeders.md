# 5.3 Migrations e Seeders

## Resumo

> **Migrations** — controle de versão do banco. `up()` cria, `down()` reverte as mudanças.
>
> **Seeders** — popular o banco com dados de teste. **Factories** — gerar models para testes e seeders.
>
> **Comandos:** `migrate`, `migrate:rollback`, `migrate:fresh --seed`.

---

## Conteúdo

- [O que é](#o-que-é)
- [Criar migrations](#como-funciona)
- [Tipos de colunas](#como-funciona)
- [Foreign Keys](#como-funciona)
- [Seeders](#seeders)
- [Factories](#factories)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Migrations — controle de versão do banco (criar/alterar tabelas). Seeders — popular o banco com dados de teste.

**O essencial:**
- Migrations — estrutura do banco no código
- Seeders — dados de teste
- Factories — gerar models

---

## Como funciona

**Criar a migration:**

```bash
# Criar a tabela
php artisan make:migration create_users_table

# Alterar a tabela
php artisan make:migration add_status_to_users_table

# Com flags
php artisan make:migration create_posts_table --create=posts
php artisan make:migration add_category_to_posts --table=posts
```

**Estrutura da migration:**

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();  // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();  // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

**Tipos de colunas:**

```php
Schema::create('products', function (Blueprint $table) {
    // Numérico
    $table->id();  // BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
    $table->bigInteger('views');
    $table->integer('stock');
    $table->tinyInteger('status');
    $table->decimal('price', 8, 2);  // 8 dígitos, 2 depois da vírgula
    $table->float('rating', 8, 2);

    // String
    $table->string('name', 255);  // VARCHAR(255)
    $table->text('description');  // TEXT
    $table->longText('content');  // LONGTEXT

    // Data/hora
    $table->date('birth_date');  // DATE
    $table->dateTime('published_at');  // DATETIME
    $table->timestamp('verified_at');  // TIMESTAMP
    $table->timestamps();  // created_at, updated_at
    $table->softDeletes();  // deleted_at

    // Boolean
    $table->boolean('is_active')->default(true);

    // JSON
    $table->json('metadata');

    // Foreign Key
    $table->foreignId('user_id')->constrained()->onDelete('cascade');

    // Enum
    $table->enum('status', ['pending', 'approved', 'rejected']);
});
```

**Modificadores de colunas:**

```php
$table->string('email')->nullable();  // NULL
$table->string('role')->default('user');  // DEFAULT
$table->string('name')->unique();  // UNIQUE
$table->string('description')->comment('Descrição do produto');  // COMMENT
$table->integer('order')->unsigned();  // UNSIGNED
$table->timestamp('created_at')->useCurrent();  // DEFAULT CURRENT_TIMESTAMP
$table->timestamp('updated_at')->useCurrentOnUpdate();  // ON UPDATE CURRENT_TIMESTAMP
```

**Índices:**

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('email')->unique();  // UNIQUE
    $table->index('email');  // INDEX
    $table->index(['user_id', 'created_at']);  // Índice composto

    // Índice nomeado
    $table->index('email', 'idx_users_email');

    // Remover o índice
    $table->dropIndex('idx_users_email');
    $table->dropUnique(['email']);
});
```

**Foreign Keys:**

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('title');
    $table->timestamps();
});

// Equivale a:
$table->unsignedBigInteger('user_id');
$table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade')
    ->onUpdate('cascade');

// Remover a FK
$table->dropForeign(['user_id']);
$table->dropForeign('posts_user_id_foreign');  // Pelo nome
```

**Alterar a tabela:**

```php
Schema::table('users', function (Blueprint $table) {
    // Adicionar coluna
    $table->string('phone')->nullable()->after('email');

    // Alterar coluna (precisa do doctrine/dbal)
    $table->string('name', 100)->change();

    // Renomear coluna
    $table->renameColumn('name', 'full_name');

    // Remover coluna
    $table->dropColumn('phone');
    $table->dropColumn(['phone', 'address']);
});

// Renomear tabela
Schema::rename('posts', 'articles');

// Remover tabela
Schema::drop('users');
Schema::dropIfExists('users');
```

**Rodar as migrations:**

```bash
# Rodar todas as migrations
php artisan migrate

# Reverter o último batch
php artisan migrate:rollback

# Reverter todas as migrations
php artisan migrate:reset

# Reverter e rodar de novo
php artisan migrate:refresh

# Reverter, rodar e popular (seed)
php artisan migrate:refresh --seed

# Dropar todas as tabelas e criar de novo
php artisan migrate:fresh

# Status das migrations
php artisan migrate:status
```

---

## Seeders

**Criar o Seeder:**

```bash
php artisan make:seeder UserSeeder
```

**Estrutura do Seeder:**

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Criar um usuário
        User::create([
            'name' => 'Admin',
            'email' => 'admin@email.com',
            'password' => Hash::make('password'),
        ]);

        // Criar vários
        User::insert([
            [
                'name' => 'João',
                'email' => 'joao@email.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maria',
                'email' => 'maria@email.com',
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
```

**DatabaseSeeder:**

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Chamar outros seeders
        $this->call([
            UserSeeder::class,
            PostSeeder::class,
            CategorySeeder::class,
        ]);
    }
}
```

**Rodar os Seeders:**

```bash
# Rodar todos os seeders
php artisan db:seed

# Rodar um seeder específico
php artisan db:seed --class=UserSeeder

# Migrations + seeders
php artisan migrate:fresh --seed
```

---

## Factories

**Criar a Factory:**

```bash
php artisan make:factory UserFactory
```

**Estrutura da Factory:**

```php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    // State (modificação)
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
```

**Usar as Factories:**

```php
// Criar um usuário
$user = User::factory()->create();

// Criar vários
$users = User::factory()->count(10)->create();

// Com atributos customizados
$user = User::factory()->create([
    'name' => 'João Silva',
    'email' => 'joao@email.com',
]);

// Usar o state
$admin = User::factory()->admin()->create();
$unverified = User::factory()->unverified()->create();

// Criar com relationships
$user = User::factory()
    ->has(Post::factory()->count(3))
    ->create();

// Equivale a:
$user = User::factory()
    ->hasPosts(3)
    ->create();
```

---

## Quando usar

**Migrations:**
- Qualquer mudança na estrutura do banco
- Versionar o banco
- CI/CD (deploy automático)

**Seeders:**
- Dados de teste no desenvolvimento
- Dados iniciais (roles, configs)
- Dados de demo

**Factories:**
- Testes unit/feature
- Gerar dados de teste
- Popular o banco rápido

---

## Exemplo prático

**Migrations de e-commerce:**

```php
// database/migrations/xxxx_create_products_table.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->integer('stock')->default(0);
    $table->foreignId('category_id')->constrained()->onDelete('cascade');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();

    $table->index(['category_id', 'is_active']);
    $table->index('slug');
});

// database/migrations/xxxx_create_orders_table.php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->enum('status', ['pending', 'paid', 'shipped', 'delivered', 'cancelled']);
    $table->decimal('total', 10, 2);
    $table->timestamps();

    $table->index(['user_id', 'status']);
    $table->index('created_at');
});

// database/migrations/xxxx_create_order_items_table.php
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->onDelete('cascade');
    $table->foreignId('product_id')->constrained()->onDelete('cascade');
    $table->integer('quantity');
    $table->decimal('price', 10, 2);
    $table->timestamps();
});
```

**Seeders com Factories:**

```php
// database/seeders/DatabaseSeeder.php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Criar categorias
        $categories = Category::factory()->count(5)->create();

        // Criar produtos para cada categoria
        $categories->each(function ($category) {
            Product::factory()
                ->count(10)
                ->for($category)
                ->create();
        });

        // Criar usuários com pedidos
        User::factory()
            ->count(20)
            ->has(
                Order::factory()
                    ->count(3)
                    ->has(OrderItem::factory()->count(2), 'items')
            )
            ->create();

        // Usuário admin
        User::factory()->admin()->create([
            'email' => 'admin@email.com',
        ]);
    }
}
```

**Factory com relationships:**

```php
// database/factories/OrderFactory.php
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'paid', 'shipped']),
            'total' => fake()->randomFloat(2, 10, 1000),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}

// Uso
$order = Order::factory()->paid()->create();
```

---

## Na entrevista

> "Migrations são o controle de versão do banco. up() cria, down() reverte. Schema::create() para tabela nova, Schema::table() para alteração. Foreign key com foreignId()->constrained()->onDelete('cascade'). Seeder popula o banco (DatabaseSeeder, db:seed). Factory gera model (User::factory()->create()) — uso em teste e seeder. migrate:fresh --seed recria o banco com dados. Factory com state para variação (admin(), unverified())."

---

## Exercícios práticos

### Exercício 1: Crie uma migration com índices

**Enunciado:** Crie uma migration para a tabela `posts` com os campos: title, slug (único), content, status (enum), published_at. Coloque os índices certos.

<details>
<summary>Solução</summary>

```php
// database/migrations/xxxx_create_posts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices para queries frequentes
            $table->index(['status', 'published_at']);  // Lista de publicados
            $table->index(['user_id', 'status']);       // Posts do usuário
            $table->index('created_at');                // Ordenar por data
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```
</details>

### Exercício 2: Escreva um Seeder com Factory

**Enunciado:** Crie um Seeder que gera 5 categorias, 10 produtos por categoria e 50 usuários.

<details>
<summary>Solução</summary>

```php
// database/factories/CategoryFactory.php
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fn(array $attributes) => Str::slug($attributes['name']),
            'description' => fake()->sentence(),
        ];
    }
}

// database/factories/ProductFactory.php
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'slug' => fn(array $attributes) => Str::slug($attributes['name']),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 1000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => fake()->boolean(80),  // 80% ativos
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
            'is_active' => false,
        ]);
    }
}

// database/seeders/ProductSeeder.php
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Criar 5 categorias
        $categories = Category::factory()->count(5)->create();

        // Para cada categoria, criar 10 produtos
        $categories->each(function ($category) {
            Product::factory()
                ->count(10)
                ->for($category)
                ->create();

            // Adicionar 2 produtos sem estoque
            Product::factory()
                ->count(2)
                ->outOfStock()
                ->for($category)
                ->create();
        });

        // Criar 50 usuários
        User::factory()->count(50)->create();
    }
}

// database/seeders/DatabaseSeeder.php
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProductSeeder::class,
        ]);
    }
}
```
</details>

### Exercício 3: Migration de alteração de tabela

**Enunciado:** Na tabela `users` já existente, adicione os campos: `phone` (nullable), `avatar` (nullable), `is_verified` (boolean, default false). Inclua um índice em phone.

<details>
<summary>Solução</summary>

```php
// database/migrations/xxxx_add_profile_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_verified')->default(false)->after('avatar');

            // Índice para busca por telefone
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->dropColumn(['phone', 'avatar', 'is_verified']);
        });
    }
};
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
