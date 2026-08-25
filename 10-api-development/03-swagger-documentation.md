# 9.3 Swagger / Documentação de API

## Resumo

> **Swagger (OpenAPI)** — padrão de documentação de API com documentação interativa.
>
> **Laravel:** pacote l5-swagger, anotações `@OA\Get`/`@OA\Post` nos controllers, Schema para models.
>
> **Acesso:** `/api/documentation` para testar a API no navegador.

---

## Conteúdo

- [O que é](#o-que-é)
- [Instalação e configuração](#instalação-e-configuração)
- [Anotações](#anotações)
- [Quando usar](#quando-usar)
- [Exemplo prático](#exemplo-prático)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Swagger (OpenAPI) é o padrão de documentação de API. Documentação interativa, geração automática.

**Para que serve:**
- Documentação para desenvolvedores
- Testar a API
- Gerar clientes

---

## Instalação e configuração

**Instalação:**

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider="L5Swagger\L5SwaggerServiceProvider"
php artisan l5-swagger:generate
```

**Configuração (config/l5-swagger.php):**

```php
'api' => [
    'title' => 'Documentação da Minha API',
],
'routes' => [
    'api' => 'api/documentation',
],
'paths' => [
    'annotations' => [
        base_path('app/Http/Controllers'),
    ],
],
```

---

## Anotações

### Informações básicas

```php
/**
 * @OA\Info(
 *     title="Minha API",
 *     version="1.0.0",
 *     description="Documentação da API"
 * )
 */
```

### Request GET

```php
/**
 * @OA\Get(
 *     path="/api/posts",
 *     summary="Listar posts",
 *     tags={"Posts"},
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Número da página",
 *         required=false,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(ref="#/components/schemas/Post")
 *             )
 *         )
 *     )
 * )
 */
public function index() {}
```

### Request POST

```php
/**
 * @OA\Post(
 *     path="/api/posts",
 *     summary="Criar post",
 *     tags={"Posts"},
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"title","body"},
 *             @OA\Property(property="title", type="string"),
 *             @OA\Property(property="body", type="string")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Criado")
 * )
 */
public function store() {}
```

### Definições de Schema

```php
/**
 * @OA\Schema(
 *     schema="Post",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="body", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
```

### Security Scheme

```php
/**
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="sanctum",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
```

---

## Quando usar

**Documentação serve para:**
- API pública
- API para o frontend
- API para parceiros
- Arquitetura de microsserviços

---

## Exemplo prático

### Documentação completa do controller

```php
/**
 * @OA\SecurityScheme(
 *     type="http",
 *     securityScheme="sanctum",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */

/**
 * @OA\Tag(name="Posts", description="Gerenciar posts")
 * @OA\Tag(name="Auth", description="Autenticação")
 */
class Controller {}

class PostController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/posts",
     *     summary="Listar posts",
     *     tags={"Posts"},
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         @OA\Schema(type="string", enum={"draft", "published"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Post")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function index() {}
}
```

**Acesso à documentação:**

```
http://localhost/api/documentation
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Swagger (OpenAPI) — padrão de documentação de API
- Documentação interativa, dá para testar no navegador

**Instalação no Laravel:**
- `composer require darkaonline/l5-swagger`
- `php artisan l5-swagger:generate`

**Anotações principais:**
- `@OA\Info` — informação geral da API
- `@OA\Get`/`@OA\Post` — endpoints
- `@OA\Schema` — models
- `@OA\SecurityScheme` — autenticação

**Vantagens:**
- Documentação viva
- Testar a API no navegador
- Gerar clientes
- Sempre atual (gera a partir do código)

---

## Exercícios práticos

### Exercício 1: Documentar API CRUD

**Enunciado:** Crie a documentação Swagger da Article API com CRUD completo.

<details>
<summary>Solução</summary>

```php
/**
 * @OA\Schema(
 *     schema="Article",
 *     type="object",
 *     required={"title", "body"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Meu artigo"),
 *     @OA\Property(property="body", type="string", example="Conteúdo do artigo..."),
 *     @OA\Property(property="status", type="string", enum={"draft", "published"}),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class ArticleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/articles",
     *     summary="Listar artigos",
     *     tags={"Articles"},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Article"))
     *         )
     *     )
     * )
     */
    public function index() {}

    /**
     * @OA\Post(
     *     path="/api/articles",
     *     summary="Criar artigo",
     *     tags={"Articles"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title", "body"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="body", type="string"),
     *             @OA\Property(property="status", type="string", enum={"draft", "published"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Criado", @OA\JsonContent(ref="#/components/schemas/Article")),
     *     @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function store() {}

    /**
     * @OA\Get(
     *     path="/api/articles/{id}",
     *     summary="Exibir artigo",
     *     tags={"Articles"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sucesso", @OA\JsonContent(ref="#/components/schemas/Article")),
     *     @OA\Response(response=404, description="Não encontrado")
     * )
     */
    public function show() {}

    /**
     * @OA\Put(
     *     path="/api/articles/{id}",
     *     summary="Atualizar artigo",
     *     tags={"Articles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="body", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Atualizado"),
     *     @OA\Response(response=404, description="Não encontrado")
     * )
     */
    public function update() {}

    /**
     * @OA\Delete(
     *     path="/api/articles/{id}",
     *     summary="Excluir artigo",
     *     tags={"Articles"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Excluído"),
     *     @OA\Response(response=404, description="Não encontrado")
     * )
     */
    public function destroy() {}
}
```
</details>

### Exercício 2: Documentação de Nested Resource

**Enunciado:** Documente a API `/articles/{article}/comments`.

<details>
<summary>Solução</summary>

```php
/**
 * @OA\Schema(
 *     schema="Comment",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="article_id", type="integer"),
 *     @OA\Property(property="user_id", type="integer"),
 *     @OA\Property(property="body", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="user", ref="#/components/schemas/User")
 * )
 */

class CommentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/articles/{article}/comments",
     *     summary="Listar comentários do artigo",
     *     tags={"Comments"},
     *     @OA\Parameter(
     *         name="article",
     *         in="path",
     *         required=true,
     *         description="ID do artigo",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Sucesso",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Comment")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Artigo não encontrado")
     * )
     */
    public function index(Article $article) {}

    /**
     * @OA\Post(
     *     path="/api/articles/{article}/comments",
     *     summary="Criar comentário",
     *     tags={"Comments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="article", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"body"},
     *             @OA\Property(property="body", type="string", example="Ótimo artigo!")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Criado"),
     *     @OA\Response(response=401, description="Não autorizado"),
     *     @OA\Response(response=404, description="Artigo não encontrado")
     * )
     */
    public function store(Article $article) {}
}
```
</details>

### Exercício 3: Documentação com Enum e examples

**Enunciado:** Adicione documentação com valores de enum e examples.

<details>
<summary>Solução</summary>

```php
/**
 * @OA\Schema(
 *     schema="Order",
 *     type="object",
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"pending", "processing", "shipped", "delivered", "cancelled"},
 *         example="processing"
 *     ),
 *     @OA\Property(
 *         property="payment_method",
 *         type="string",
 *         enum={"credit_card", "paypal", "bank_transfer"},
 *         example="credit_card"
 *     ),
 *     @OA\Property(property="total", type="number", format="float", example=99.99),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="product_id", type="integer", example=1),
 *             @OA\Property(property="quantity", type="integer", example=2),
 *             @OA\Property(property="price", type="number", format="float", example=49.99)
 *         )
 *     )
 * )
 */

class OrderController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Criar pedido",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items", "payment_method"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="string",
     *                 enum={"credit_card", "paypal", "bank_transfer"},
     *                 example="credit_card"
     *             ),
     *             example={
     *                 "items": {
     *                     {"product_id": 1, "quantity": 2},
     *                     {"product_id": 3, "quantity": 1}
     *                 },
     *                 "payment_method": "credit_card",
     *                 "shipping_address": {
     *                     "street": "Av. Paulista, 1000",
     *                     "city": "São Paulo",
     *                     "country": "Brasil"
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Pedido criado",
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *     @OA\Response(response=422, description="Erro de validação")
     * )
     */
    public function store() {}
}
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
