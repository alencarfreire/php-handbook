# 9.2 GraphQL

## Resumo

> **GraphQL** — linguagem de query para API. O cliente pede só os campos que precisa.
>
> **O essencial:** Um endpoint. Query para ler, Mutation para alterar. Type define a estrutura, Resolve carrega os dados.
>
> **Laravel:** `rebing/graphql-laravel` ou Lighthouse. Schema-first vs Code-first.

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
GraphQL é uma linguagem de query para API. O cliente diz quais dados precisa. Um endpoint, queries flexíveis.

**REST vs GraphQL:**
- REST: vários endpoints, estrutura fixa
- GraphQL: um endpoint, estrutura flexível

---

## Como funciona

**Instalação (Laravel):**

```bash
composer require rebing/graphql-laravel
php artisan vendor:publish --provider="Rebing\GraphQL\GraphQLServiceProvider"
```

**Query GraphQL simples:**

```graphql
# Query
query {
  user(id: 1) {
    id
    name
    email
    posts {
      id
      title
    }
  }
}

# Response
{
  "data": {
    "user": {
      "id": 1,
      "name": "João",
      "email": "joao@email.com",
      "posts": [
        {"id": 1, "title": "Primeiro post"},
        {"id": 2, "title": "Segundo post"}
      ]
    }
  }
}
```

**Definir o Type:**

```php
// app/GraphQL/Types/UserType.php
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;

class UserType extends GraphQLType
{
    protected $attributes = [
        'name' => 'User',
        'description' => 'Um usuário',
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
            'name' => [
                'type' => Type::string(),
            ],
            'email' => [
                'type' => Type::string(),
            ],
            'posts' => [
                'type' => Type::listOf(GraphQL::type('Post')),
                'resolve' => fn($user) => $user->posts,
            ],
        ];
    }
}
```

**Definir a Query:**

```php
// app/GraphQL/Queries/UserQuery.php
use Rebing\GraphQL\Support\Query;
use GraphQL\Type\Definition\Type;

class UserQuery extends Query
{
    protected $attributes = [
        'name' => 'user',
    ];

    public function type(): Type
    {
        return GraphQL::type('User');
    }

    public function args(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        return User::find($args['id']);
    }
}
```

---

## Quando usar

**GraphQL para:**
- ✅ Dados aninhados complexos
- ✅ Apps mobile (menos requests)
- ✅ Flexibilidade na escolha dos campos

**REST para:**
- ✅ CRUD simples
- ✅ Cache (HTTP cache)
- ✅ Padronização

---

## Exemplo prático

**Mutation (criar/alterar):**

```php
// app/GraphQL/Mutations/CreatePostMutation.php
class CreatePostMutation extends Mutation
{
    protected $attributes = [
        'name' => 'createPost',
    ];

    public function type(): Type
    {
        return GraphQL::type('Post');
    }

    public function args(): array
    {
        return [
            'title' => [
                'type' => Type::nonNull(Type::string()),
            ],
            'body' => [
                'type' => Type::nonNull(Type::string()),
            ],
        ];
    }

    public function resolve($root, $args)
    {
        $post = Post::create([
            'user_id' => auth()->id(),
            'title' => $args['title'],
            'body' => $args['body'],
        ]);

        return $post;
    }
}
```

**Query GraphQL com mutation:**

```graphql
mutation {
  createPost(title: "Novo post", body: "Conteúdo") {
    id
    title
    author {
      name
    }
  }
}
```

**Lighthouse (alternativa):**

```bash
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-schema
```

```graphql
# graphql/schema.graphql
type Query {
  user(id: ID! @eq): User @find
  users: [User!]! @paginate
  posts: [Post!]! @all
}

type Mutation {
  createPost(title: String!, body: String!): Post @create
  updatePost(id: ID!, title: String, body: String): Post @update
  deletePost(id: ID!): Post @delete
}

type User {
  id: ID!
  name: String!
  email: String!
  posts: [Post!]! @hasMany
}

type Post {
  id: ID!
  title: String!
  body: String!
  user: User! @belongsTo
  comments: [Comment!]! @hasMany
}
```

---

## Na entrevista

> "GraphQL é linguagem de query, um endpoint. O cliente pede os campos que precisa. Query para ler, Mutation para alterar. Type define a estrutura. A função Resolve carrega os dados. Prós: flexibilidade, menos over-fetching. Contras: cache mais difícil, sem HTTP status codes. Laravel: rebing/graphql-laravel ou Lighthouse. Schema-first (Lighthouse) vs Code-first (rebing)."

---

## Exercícios práticos

### Exercício 1: Crie um GraphQL Type e uma Query

**Enunciado:** Crie um GraphQL Type para o model Post com os campos id, title, body. Adicione uma Query para buscar o post por ID.

<details>
<summary>Solução</summary>

```php
// app/GraphQL/Types/PostType.php
namespace App\GraphQL\Types;

use App\Models\Post;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Type as GraphQLType;

class PostType extends GraphQLType
{
    protected $attributes = [
        'name' => 'Post',
        'description' => 'Um post',
        'model' => Post::class,
    ];

    public function fields(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID do post',
            ],
            'title' => [
                'type' => Type::string(),
                'description' => 'Título do post',
            ],
            'body' => [
                'type' => Type::string(),
                'description' => 'Conteúdo do post',
            ],
            'author' => [
                'type' => GraphQL::type('User'),
                'description' => 'Autor do post',
                'resolve' => function ($post) {
                    return $post->user;
                },
            ],
            'created_at' => [
                'type' => Type::string(),
                'description' => 'Data de criação',
                'resolve' => function ($post) {
                    return $post->created_at->toISOString();
                },
            ],
        ];
    }
}

// app/GraphQL/Queries/PostQuery.php
namespace App\GraphQL\Queries;

use App\Models\Post;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Query;

class PostQuery extends Query
{
    protected $attributes = [
        'name' => 'post',
        'description' => 'Busca um post pelo ID',
    ];

    public function type(): Type
    {
        return GraphQL::type('Post');
    }

    public function args(): array
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::int()),
                'description' => 'ID do post',
            ],
        ];
    }

    public function resolve($root, $args)
    {
        return Post::with('user')->findOrFail($args['id']);
    }
}

// config/graphql.php
'schemas' => [
    'default' => [
        'query' => [
            'post' => \App\GraphQL\Queries\PostQuery::class,
        ],
    ],
],

'types' => [
    'Post' => \App\GraphQL\Types\PostType::class,
    'User' => \App\GraphQL\Types\UserType::class,
],

// Query GraphQL
/*
query {
  post(id: 1) {
    id
    title
    body
    author {
      name
      email
    }
    created_at
  }
}
*/
```

</details>

### Exercício 2: Adicione Mutation com validação

**Enunciado:** Crie uma Mutation para criar post com validação de title (mín. 3 caracteres) e body (campo obrigatório).

<details>
<summary>Solução</summary>

```php
// app/GraphQL/Mutations/CreatePostMutation.php
namespace App\GraphQL\Mutations;

use App\Models\Post;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Facades\GraphQL;
use Rebing\GraphQL\Support\Mutation;
use Illuminate\Support\Facades\Validator;

class CreatePostMutation extends Mutation
{
    protected $attributes = [
        'name' => 'createPost',
        'description' => 'Cria um post novo',
    ];

    public function type(): Type
    {
        return GraphQL::type('Post');
    }

    public function args(): array
    {
        return [
            'title' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Título do post (mín. 3 caracteres)',
            ],
            'body' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Conteúdo do post',
            ],
        ];
    }

    protected function rules(array $args = []): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:10'],
        ];
    }

    public function resolve($root, $args)
    {
        $user = auth()->user();

        if (!$user) {
            throw new \GraphQL\Error\Error('Não autenticado');
        }

        $post = Post::create([
            'user_id' => $user->id,
            'title' => $args['title'],
            'body' => $args['body'],
        ]);

        return $post->load('user');
    }
}

// config/graphql.php
'schemas' => [
    'default' => [
        'mutation' => [
            'createPost' => \App\GraphQL\Mutations\CreatePostMutation::class,
        ],
    ],
],

// Query GraphQL
/*
mutation {
  createPost(
    title: "Meu post GraphQL"
    body: "Este é o conteúdo criado via mutation GraphQL"
  ) {
    id
    title
    body
    author {
      name
    }
  }
}
*/
```

</details>

### Exercício 3: Configure o Lighthouse para um start rápido

**Enunciado:** Use o Lighthouse para criar uma API GraphQL sem escrever classes. Crie o schema para User e Post.

<details>
<summary>Solução</summary>

```bash
# Instalação
composer require nuwave/lighthouse
php artisan vendor:publish --tag=lighthouse-schema
php artisan vendor:publish --tag=lighthouse-config
```

```graphql
# graphql/schema.graphql
"String datetime no formato `Y-m-d H:i:s`"
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

type Query {
    users(
        first: Int = 15
        page: Int
    ): UserPaginator! @paginate(defaultCount: 15)

    user(id: ID @eq): User @find

    posts(
        first: Int = 20
        page: Int
    ): PostPaginator! @paginate(defaultCount: 20)

    post(id: ID @eq): Post @find
}

type Mutation {
    createPost(
        title: String! @rules(apply: ["required", "min:3", "max:255"])
        body: String! @rules(apply: ["required", "min:10"])
    ): Post @create @inject(context: "user.id", name: "user_id")

    updatePost(
        id: ID! @eq
        title: String @rules(apply: ["min:3", "max:255"])
        body: String @rules(apply: ["min:10"])
    ): Post @update

    deletePost(id: ID! @eq): Post @delete @can(ability: "delete")
}

type User {
    id: ID!
    name: String!
    email: String!
    created_at: DateTime!
    updated_at: DateTime!
    posts: [Post!]! @hasMany
    posts_count: Int! @count(relation: "posts")
}

type Post {
    id: ID!
    title: String!
    body: String!
    created_at: DateTime!
    updated_at: DateTime!
    user: User! @belongsTo
}

type UserPaginator {
    data: [User!]!
    paginatorInfo: PaginatorInfo!
}

type PostPaginator {
    data: [Post!]!
    paginatorInfo: PaginatorInfo!
}

type PaginatorInfo {
    count: Int!
    currentPage: Int!
    firstItem: Int
    hasMorePages: Boolean!
    lastItem: Int
    lastPage: Int!
    perPage: Int!
    total: Int!
}
```

```php
// Exemplos de queries

// Buscar usuários com os posts
/*
query {
  users(first: 5) {
    data {
      id
      name
      email
      posts_count
      posts {
        id
        title
      }
    }
    paginatorInfo {
      currentPage
      lastPage
      total
    }
  }
}
*/

// Buscar post com o autor
/*
query {
  post(id: 1) {
    id
    title
    body
    created_at
    user {
      id
      name
      email
    }
  }
}
*/

// Criar post (precisa de autenticação)
/*
mutation {
  createPost(
    title: "Novo post via Lighthouse"
    body: "Bem mais fácil que o code-first!"
  ) {
    id
    title
    body
    created_at
    user {
      name
    }
  }
}
*/

// Atualizar post
/*
mutation {
  updatePost(
    id: 1
    title: "Título atualizado"
    body: "Conteúdo atualizado"
  ) {
    id
    title
    body
  }
}
*/
```

</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
