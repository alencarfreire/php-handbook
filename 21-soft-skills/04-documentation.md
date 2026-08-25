# 15.4 Documentação

## O que é

**Tipos de documentação:**

```
1. Code comments (comentários no código)
2. API documentation (Swagger/OpenAPI)
3. README (visão geral do projeto)
4. Technical documentation (arquitetura)
5. User documentation (para usuários)
```

---

## Comentários no código

**Quando comentar:**

```php
✅ BOM: explicar o "porquê"

// Hash MD5 por compatibilidade com a API legacy
// TODO: migrar para bcrypt na v2.0
$hash = md5($password);

// Workaround para bug no PHP 8.0 (https://bugs.php.net/bug.php?id=12345)
if (version_compare(PHP_VERSION, '8.0', '>=')) {
    // código alternativo
}

// Regra de negócio: 10% de desconto para clientes VIP em pedidos > R$ 100
if ($user->isVip() && $order->total > 100) {
    $discount = 0.10;
}
```

```php
❌ RUIM: comentar o óbvio

// Buscar o usuário pelo ID
$user = User::find($id);

// Definir o nome
$user->name = $name;

// Salvar o usuário
$user->save();
```

```php
✅ MELHOR: código autoexplicativo

function applyVipDiscount(Order $order, User $user): void
{
    if ($user->isVip() && $order->exceedsMinimumForDiscount()) {
        $order->applyDiscount(self::VIP_DISCOUNT_PERCENTAGE);
    }
}
```

---

## PHPDoc

**Para classes:**

```php
/**
 * Service para processar pagamentos de usuários
 *
 * Processa pagamento em vários gateways
 * (Stripe, PayPal), com retry automático e detecção de fraude.
 *
 * @package App\Services
 * @author John Doe <john@example.com>
 */
class PaymentService
{
    // ...
}
```

**Para métodos:**

```php
/**
 * Processa o pagamento de um pedido
 *
 * Cobra o meio de pagamento do cliente e cria o registro
 * no banco. Se falhar, tenta de novo até 3 vezes com exponential backoff.
 *
 * @param Order $order Pedido a ser cobrado
 * @param PaymentMethod $method Meio de pagamento do cliente
 * @return Payment Registro do pagamento criado
 *
 * @throws PaymentFailedException Se o pagamento falhar depois de todas as tentativas
 * @throws InsufficientFundsException Se o cliente não tiver saldo
 *
 * @example
 * $payment = $paymentService->processPayment($order, $card);
 */
public function processPayment(Order $order, PaymentMethod $method): Payment
{
    // ...
}
```

**Para parâmetros complexos:**

```php
/**
 * Cria um usuário com dados extras
 *
 * @param array $data Dados do usuário
 * @param array $data['name'] string Nome completo
 * @param array $data['email'] string Email do usuário
 * @param array $data['roles'] array<string> Array opcional de nomes de role
 * @param array $data['profile'] array Dados opcionais de perfil
 * @param array $data['profile']['avatar'] string URL opcional do avatar
 *
 * @return User
 */
public function createUser(array $data): User
{
    // ...
}
```

---

## Documentação de API

**OpenAPI (Swagger):**

```php
/**
 * @OA\Get(
 *     path="/api/users/{id}",
 *     summary="Buscar usuário por ID",
 *     tags={"Users"},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Sucesso",
 *         @OA\JsonContent(ref="#/components/schemas/User")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Usuário não encontrado"
 *     )
 * )
 */
public function show(int $id)
{
    return User::findOrFail($id);
}
```

**Laravel API Resources (alternativa):**

```php
// app/Http/Resources/UserResource.php
/**
 * Representação do resource de usuário
 *
 * @property int $id ID do usuário
 * @property string $name Nome completo
 * @property string $email Email do usuário
 * @property Carbon $created_at Data de criação da conta
 */
class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
```

---

## README.md

**Estrutura:**

```markdown
# Nome do projeto

Descrição curta do que o projeto faz.

## Features

- Autenticação de usuários
- Processamento de pagamentos
- Notificações em tempo real
- Dashboard admin

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Redis 6.0+
- Node.js 18+

## Installation

```bash
# Clonar o repositório
git clone https://github.com/user/project.git
cd project

# Instalar dependências
composer install
npm install

# Configurar o ambiente
cp .env.example .env
php artisan key:generate

# Configurar o banco
php artisan migrate --seed

# Build dos assets
npm run build
```

## Configuration

### Database
Edite o `.env`:
```
DB_HOST=localhost
DB_DATABASE=myapp
DB_USERNAME=root
DB_PASSWORD=secret
```

### Queue
```bash
php artisan queue:work
```

## Usage

### Rodar local
```bash
php artisan serve
npm run dev
```

Acesse http://localhost:8000

### Rodar testes
```bash
php artisan test
```

## API Documentation

Docs da API em: http://localhost:8000/api/documentation

## Contributing

1. Faça fork do repositório
2. Crie a feature branch (`git checkout -b feature/amazing`)
3. Faça commit (`git commit -m 'Adiciona feature incrível'`)
4. Dê push na branch (`git push origin feature/amazing`)
5. Abra o Pull Request

## License

MIT License
```

---

## Documentação técnica

**Visão da arquitetura:**

```markdown
# Architecture

## Overview

A app segue uma arquitetura em camadas:

```
┌─────────────────────────────────┐
│         Controllers             │
├─────────────────────────────────┤
│          Services               │
├─────────────────────────────────┤
│        Repositories             │
├─────────────────────────────────┤
│           Models                │
└─────────────────────────────────┘
```

## Layers

### Controllers
Recebem o HTTP request, validam o input, devolvem o response.
Fica em: `app/Http/Controllers`

### Services
Guardam a regra de negócio, orquestram as operações.
Fica em: `app/Services`

### Repositories
Abstraem o acesso ao banco, implementam as queries.
Fica em: `app/Repositories`

### Models
Models Eloquent que representam as tabelas.
Fica em: `app/Models`

## Key Components

### Payment Processing
Fica no `PaymentService`, que cobre:
- Integração Stripe
- Integração PayPal
- Retry (3 tentativas)
- Tratamento de webhook

### Notification System
Usa Laravel Notifications com os canais:
- Email (via queue)
- SMS (via Twilio)
- Push notifications (via FCM)

### Caching Strategy
- Dados do usuário: 1 hora
- Catálogo de produtos: 24 horas
- Configuração: até o deploy
```

---

## Schema do banco

**Documentando migrations:**

```php
/**
 * Cria a tabela users
 *
 * Guarda conta do usuário: credenciais de autenticação
 * e dados de perfil. Tabelas relacionadas: orders, posts, comments.
 */
class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();  // Usado no login
            $table->timestamp('email_verified_at')->nullable();  // Confirmação de email
            $table->string('password');  // Hash com bcrypt
            $table->enum('role', ['user', 'admin'])->default('user');  // Controle de acesso
            $table->rememberToken();  // Funcionalidade "lembrar de mim"
            $table->timestamps();
            $table->softDeletes();  // Suporte a soft delete

            // Indexes
            $table->index('email');  // Acelera as queries de login
            $table->index(['role', 'created_at']);  // Filtros do admin
        });
    }
}
```

---

## ADR (Architecture Decision Records)

**Formato:**

```markdown
# ADR-001: Usar Redis para session storage

## Status
Accepted

## Context
Precisamos escalar na horizontal com vários app servers.
Sessão em arquivo não funciona entre servers.

## Decision
Usar Redis para session storage.

## Consequences

### Positive
- Sessão compartilhada entre todos os servers
- Leitura e escrita rápidas
- Dá para persistir a sessão

### Negative
- Dependência a mais (Redis)
- Setup um pouco mais complexo
- Precisa monitorar se o Redis está no ar

## Alternatives Considered

1. **Database sessions**
   - Pros: o banco já existe
   - Cons: mais lento que Redis

2. **Sticky sessions**
   - Pros: não muda nada
   - Cons: carga desigual

## Implementation
```php
// config/session.php
'driver' => 'redis',
'connection' => 'session',
```

## Date
2024-01-15
```

---

## Changelog

**Formato:**

```markdown
# Changelog

Todas as mudanças relevantes deste projeto ficam neste arquivo.

## [1.2.0] - 2024-01-15

### Added
- Autenticação em dois fatores para usuários
- Exportar pedidos para CSV
- Tema dark mode

### Changed
- Pagamento passou a usar Stripe API v2
- Dashboard carrega 5x mais rápido

### Fixed
- Memory leak no queue worker
- XSS no sistema de comentários

### Security
- Dependências atualizadas com patches de segurança

## [1.1.0] - 2023-12-20

### Added
- Notificações por email quando o status do pedido muda

### Fixed
- Bug no fluxo de reset de senha
```

---

## Dicas práticas

**Para que NÃO serve comentário:**

```php
// ❌ Repetir o código
// Buscar todos os usuários
$users = User::all();

// ❌ Código comentado
// $oldImplementation = doSomething();
$newImplementation = doSomethingBetter();

// ❌ Óbvio
// Percorrer os usuários
foreach ($users as $user) {
    // ...
}
```

**Onde o comentário faz falta:**

```php
// ✅ Lógica não óbvia
// Imposto: 20% na UE, 0% fora da UE
$vat = $country->isEU() ? 0.20 : 0.00;

// ✅ TODO/FIXME
// TODO: refatorar para o pattern Strategy
// FIXME: memory leak ao processar arquivos grandes

// ✅ Workarounds
// Hack: o Safari não suporta essa propriedade CSS
// Usa polyfill no lugar

// ✅ Regex complexa
// Casa email: user@domain.com, user+tag@domain.co.uk
$pattern = '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i';
```

---

## Ferramentas

**Gerando a documentação:**

```bash
# PHPDoc
composer require --dev phpdocumentor/phpdocumentor
vendor/bin/phpdoc -d src -t docs

# API docs (Swagger)
composer require darkaonline/l5-swagger
php artisan l5-swagger:generate

# Database schema
composer require --dev beyondcode/laravel-er-diagram-generator
php artisan generate:erd
```

---

## Na entrevista

> "Documentação: code comments para o 'porquê', não para o 'o quê'. PHPDoc em classes e métodos com @param, @return, @throws. API documentation com OpenAPI/Swagger ou Laravel API Resources. README com instalação, configuração e exemplos. Documentação de arquitetura descreve camadas e componentes. ADR para decisões de arquitetura. Changelog para versões. Ferramentas: phpdocumentor, l5-swagger. Código autoexplicativo vale mais que comentário."

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
