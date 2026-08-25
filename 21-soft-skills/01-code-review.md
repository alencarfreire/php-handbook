# 15.1 Code Review

## Resumo

> **Code Review** — outros devs revisam o código antes do merge. Melhora qualidade, acha bug, espalha conhecimento.
>
> **O que checar:** funcionalidade, legibilidade, performance (N+1), segurança (SQL injection, XSS), arquitetura (SOLID).
>
> **Comentários:** construtivos, com explicação e solução. PR bom: 100-300 linhas, descrição, checklist, testes.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como fazer Code Review](#como-fazer-code-review)
- [Exemplos de comentários](#exemplos-de-comentários)
- [Checklist do reviewer](#checklist-do-reviewer)
- [Problemas típicos](#problemas-típicos)
- [Como receber Code Review](#como-receber-code-review)
- [Boas práticas de Pull Request](#boas-práticas-de-pull-request)
- [Automatizar o Code Review](#automatizar-o-code-review)
- [Ferramentas](#ferramentas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Code Review — outros devs revisam o código antes de entrar na branch principal.

**Objetivos:**
- Achar bugs e vulnerabilidades
- Melhorar a qualidade do código
- Trocar conhecimento no time
- Manter o padrão

---

## Como fazer Code Review

**O que checar:**

```
1. Funcionalidade
   ✓ O código faz o que o PR promete?
   ✓ Tem testes?
   ✓ Edge cases cobertos?

2. Legibilidade
   ✓ Nomes de variável/função claros?
   ✓ Sem duplicação?
   ✓ Comentário onde precisa?

3. Performance
   ✓ Sem N+1?
   ✓ Algoritmo ok?
   ✓ Cache onde precisa?

4. Segurança
   ✓ Proteção contra SQL injection?
   ✓ Proteção contra XSS?
   ✓ Sem secret hardcoded?

5. Arquitetura
   ✓ Segue os padrões do projeto?
   ✓ Princípios SOLID?
   ✓ Não quebra o design atual?
```

---

## Exemplos de comentários

**❌ Comentários ruins:**

```
"Isso está ruim"
"Não funciona"
"Refaz"
"Quem escreveu isso?"
```

**✅ Comentários bons:**

```php
// ❌ Achou o problema
// "Aqui vai ter N+1"

// ✅ Com explicação e solução
"Aqui nasce um N+1: cada post dispara
uma query extra pro user.
Sugestão:
Post::with('user')->get()"

// ❌ Crítica sem construtivo
// "Nome da função está ruim"

// ✅ Sugestão construtiva
"getUserData() está genérico demais. Sugiro
getUserProfileWithOrders(), porque a função
carrega o perfil com os pedidos"

// ✅ Pergunta em vez de afirmação
"Por que whereRaw em vez de where?
Isso pode não usar o índice"

// ✅ Elogio de código bom
"Early Return bem usado!
O código ficou bem mais legível"
```

---

## Checklist do reviewer

**Antes de começar:**

```
□ Entendi a tarefa?
□ Li a descrição do PR?
□ Olhei as issues ligadas?
□ Rodei o código local?
```

**Durante o review:**

```
□ Tem testes e eles passam?
□ Código legível e claro?
□ Sem duplicação (DRY)?
□ Sem N+1?
□ Migrations seguras (backward compatible)?
□ Sem SQL injection / XSS?
□ Sem credentials no código?
□ Code style do projeto ok?
□ Erros tratados?
□ Documentação atualizada (se precisar)?
```

---

## Problemas típicos

**1. N+1 Query:**

```php
// ❌ Problema
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;  // N+1
}

// ✅ Comentário
"N+1: cada post dispara uma query extra
pro user. Use:
Post::with('user')->get()"
```

**2. Sem validação:**

```php
// ❌ Problema
public function update(Request $request, User $user)
{
    $user->update($request->all());  // Qualquer campo!
}

// ✅ Comentário
"Falta validação e lista explícita de campos:
$request->validate(['name' => 'required|max:255']);
$user->update($request->only(['name', 'email']));"
```

**3. Hardcode:**

```php
// ❌ Problema
if ($user->role === 'admin') {
    // ...
}

// ✅ Comentário
"Sugiro tirar o role pra enum ou constante:
if ($user->role === UserRole::Admin->value)
ou
if ($user->hasRole('admin'))"
```

**4. Sem tratamento de erro:**

```php
// ❌ Problema
public function charge(User $user, int $amount)
{
    $this->stripeClient->charge($amount);
}

// ✅ Comentário
"Falta tratar erro do Stripe:
try {
    $this->stripeClient->charge($amount);
} catch (StripeException $e) {
    Log::error('Pagamento falhou', ['user' => $user->id]);
    throw new PaymentFailedException($e->getMessage());
}"
```

---

## Como receber Code Review

**✅ Boa reação:**

```
"Valeu pelo ponto! É N+1 mesmo.
Já corrijo"

"Boa pergunta. Usei whereRaw porque...
Mas você tem razão, dá pra ir de where(). Mudo"

"Não pensei nesse edge case. Vou adicionar a checagem"
```

**❌ Reação ruim:**

```
"Isso não é bug, é feature"
"É assim que funciona"
"Aqui sempre foi assim"
"Não é crítico"
"Depois eu corrijo"
```

**Se você não concorda:**

```
"Entendi o ponto, mas tem um detalhe...
[explicação]. Faz sentido manter
como está ou você ainda prefere mudar?"
```

---

## Boas práticas de Pull Request

**PR bom:**

```markdown
## Descrição
Adicionada autenticação em dois fatores para os usuários

## Mudanças
- Tabela `user_two_factor`
- Métodos novos `enableTwoFactor()` / `verifyTwoFactorCode()`
- Middleware `RequiresTwoFactor`
- Testes para todos os cenários

## Como testar
1. Registrar um usuário
2. Ativar 2FA no perfil
3. Sair e entrar de novo
4. Deve pedir o código 2FA

## Screenshots
[Capturas da UI]

## Checklist
- [x] Testes adicionados
- [x] Migrations backward compatible
- [x] Documentação atualizada
```

**Tamanho do PR:**

```
✅ Bom: 100-300 linhas
⚠️  Médio: 300-500 linhas
❌ Ruim: 1000+ linhas

PR grande → quebre em vários:
- PR #1: Models e migrations
- PR #2: Controllers e rotas
- PR #3: UI
```

---

## Automatizar o Code Review

**GitHub Actions:**

```yaml
# .github/workflows/code-quality.yml
name: Code Quality

on: [pull_request]

jobs:
  phpstan:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: PHPStan
        run: vendor/bin/phpstan analyse

  pint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Laravel Pint
        run: vendor/bin/pint --test

  tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run tests
        run: php artisan test
```

**Comentários automáticos:**

```yaml
# Comentar automaticamente quando falhar
- name: Comment PR
  if: failure()
  uses: actions/github-script@v6
  with:
    script: |
      github.rest.issues.createComment({
        issue_number: context.issue.number,
        owner: context.repo.owner,
        repo: context.repo.repo,
        body: '❌ Checagens de qualidade falharam. Corrija antes do merge.'
      })
```

---

## Ferramentas

**Code Review Tools:**

```
- GitHub / GitLab / Bitbucket (built-in)
- Gerrit (projetos grandes)
- Phabricator / Differential
- Review Board
```

**Code Analysis:**

```
- PHPStan (análise estática)
- Psalm (static analysis)
- Laravel Pint (code style)
- PHP CS Fixer (code style)
- SonarQube (análise completa)
```

---

## Na entrevista

> "Code Review checa funcionalidade, legibilidade, performance, segurança e arquitetura. Comentário tem que ser construtivo: explica o problema e sugere a solução. O reviewer olha teste, N+1, validação, SQL injection, code style. PR bom: 100-300 linhas, descrição, checklist. Automatizo com PHPStan, Pint e testes no CI/CD. Receber review é ouvir, não se defender."

---

## Exercícios práticos

### Exercício 1: Encontre os problemas no código

**Enunciado:** Faça o code review do código abaixo e liste todos os problemas:

```php
public function updateUser(Request $request, $id)
{
    $user = User::find($id);
    $user->name = $request->name;
    $user->email = $request->email;
    $user->password = $request->password;
    $user->save();

    return redirect('/users');
}
```

<details>
<summary>Solução</summary>

**Problemas:**

1. **Sem validação** — qualquer dado entra
2. **Sem checagem de existência** — se o user não existir, quebra
3. **Password sem hash** — grava em texto puro
4. **Mass assignment** — dá pra sobrescrever qualquer campo
5. **Sem autorização** — qualquer um altera qualquer usuário
6. **Sem tratamento de erro**

**Código corrigido:**

```php
public function updateUser(UpdateUserRequest $request, User $user)
{
    // Autorização
    $this->authorize('update', $user);

    // Dados validados
    $validated = $request->validated();

    // Hash da senha se veio no request
    if (isset($validated['password'])) {
        $validated['password'] = Hash::make($validated['password']);
    }

    // Atualiza só os campos permitidos
    $user->update($validated);

    return redirect('/users')->with('success', 'Usuário atualizado');
}

// UpdateUserRequest
public function rules()
{
    return [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $this->user->id,
        'password' => 'nullable|min:8|confirmed',
    ];
}
```
</details>

### Exercício 2: Escreva um comentário construtivo

**Enunciado:** Esse código chegou no seu review. Escreva um comentário construtivo:

```php
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->user->name;
    echo $post->category->name;
}
```

<details>
<summary>Solução</summary>

**Comentário ruim:**
```
"Isso é N+1, refaz"
```

**Comentário bom:**
```
"Aqui nasce um N+1: cada post dispara uma query
extra pra user e outra pra category. Com 100 posts
viram 1 + 100 + 100 = 201 queries no banco.

Sugestão: eager loading:

$posts = Post::with(['user', 'category'])->get();

Isso cai pra 3 queries:
1. SELECT * FROM posts
2. SELECT * FROM users WHERE id IN (...)
3. SELECT * FROM categories WHERE id IN (...)

A performance sobe várias vezes."
```

**Por que funciona:**
- Explica o problema
- Mostra o impacto (201 queries)
- Oferece solução concreta
- Explica como a solução funciona
</details>

### Exercício 3: Checklist do PR

**Enunciado:** Monte um checklist para um Pull Request de cadastro de usuário.

<details>
<summary>Solução</summary>

**Checklist do PR de User Registration:**

**Funcionalidade:**
- [ ] User consegue se registrar com email e password
- [ ] Password com no mínimo 8 caracteres
- [ ] Email único (validation + constraint no banco)
- [ ] Email de confirmação é enviado
- [ ] User não entra enquanto o email não for confirmado
- [ ] Testes cobrem todos os cenários

**Segurança:**
- [ ] Password com hash (Hash::make ou bcrypt)
- [ ] Sem SQL injection (usa Eloquent)
- [ ] Proteção CSRF (middleware)
- [ ] Email sanitizado (validation)
- [ ] Rate limiting em /register (throttle middleware)
- [ ] Sem credentials no código

**Performance:**
- [ ] Email vai pela queue (não trava o request)
- [ ] Sem N+1
- [ ] Índice em users.email

**Code Quality:**
- [ ] FormRequest para validação
- [ ] Service/Action para a regra de negócio
- [ ] Event/Listener para side effects (email, log)
- [ ] Code style (Laravel Pint)
- [ ] PHPDoc onde precisa
- [ ] Sem código duplicado

**Testing:**
- [ ] Feature test: registro com sucesso
- [ ] Feature test: validação (email required, unique, password min length)
- [ ] Feature test: email de confirmação enviado
- [ ] Unit test: UserService
- [ ] Edge cases cobertos

**Documentação:**
- [ ] README atualizado (se precisar)
- [ ] API docs atualizados (Swagger)
- [ ] Migration comentada

**Migrations:**
- [ ] Rollback funciona
- [ ] Indexes criados
- [ ] Foreign keys com onDelete cascade
- [ ] Backward compatible
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
