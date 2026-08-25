# 11.5 Resolução de conflitos

## Resumo

> **Conflito** — quando o Git não consegue juntar automaticamente as mudanças de branches diferentes.
>
> **Formato:** `<<<HEAD` (seu código), `===` (separador), `>>>branch` (o código deles).
>
> **Resolução:** Abra o arquivo, tire os marcadores, deixe o código certo, `git add`, `git commit`.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como o conflito aparece](#como-o-conflito-aparece)
- [Resolução de conflitos](#resolução-de-conflitos)
- [Ferramentas para resolver](#ferramentas-para-resolver)
- [Tipos de conflito](#tipos-de-conflito)
- [Exemplos práticos](#exemplos-práticos)
- [Como evitar conflitos](#como-evitar-conflitos)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Conflito é quando o Git não consegue juntar sozinho as mudanças no mesmo arquivo, vindas de branches diferentes.

**Quando acontece:**
- Merge de branches que mexeram nas mesmas linhas
- Rebase com commits que conflitam
- Cherry-pick de um commit com conflito
- Pull com mudanças remotas

---

## Como o conflito aparece

**Formato:**

```php
<<<<<<< HEAD
// Seu código (branch atual)
public function index()
{
    return view('dashboard');
}
=======
// Código da outra branch
public function index()
{
    return view('home');
}
>>>>>>> feature/new-design
```

**Marcadores:**
- `<<<<<<< HEAD` — início das suas mudanças
- `=======` — separador
- `>>>>>>> branch` — fim das mudanças da outra branch

---

## Resolução de conflitos

**No merge:**

```bash
git checkout main
git merge feature/new-api

# Auto-merging app/Controllers/ApiController.php
# CONFLICT (content): Merge conflict in app/Controllers/ApiController.php
# Automatic merge failed; fix conflicts and then commit the result.

# 1. Ver os arquivos em conflito
git status

# On branch main
# You have unmerged paths.
# Unmerged paths:
#   both modified:   app/Controllers/ApiController.php

# 2. Abrir o arquivo e corrigir
vim app/Controllers/ApiController.php

# 3. Remover os marcadores, deixar o código certo
# Antes:
<<<<<<< HEAD
return response()->json(['status' => 'ok']);
=======
return response()->json(['success' => true]);
>>>>>>> feature/new-api

# Depois (exemplo):
return response()->json(['success' => true, 'status' => 'ok']);

# 4. Adicionar o arquivo corrigido
git add app/Controllers/ApiController.php

# 5. Finalizar o merge
git commit -m "Merge feature/new-api into main"

# Ou abortar o merge
git merge --abort
```

**No rebase:**

```bash
git rebase main

# CONFLICT (content): Merge conflict in app/Models/User.php

# 1. Corrigir os conflitos
vim app/Models/User.php

# 2. Adicionar o arquivo
git add app/Models/User.php

# 3. Continuar o rebase
git rebase --continue

# Se ainda tiver conflito — repetir os passos 1-3
# Ou abortar o rebase
git rebase --abort
```

---

## Ferramentas para resolver

**VS Code:**

```bash
# Abrir no VS Code
code app/Controllers/ApiController.php

# O VS Code mostra:
# ✅ Accept Current Change (HEAD)
# ✅ Accept Incoming Change (branch)
# ✅ Accept Both Changes
# ✅ Compare Changes
```

**Merge tool:**

```bash
# Configurar o merge tool (ex.: vimdiff)
git config --global merge.tool vimdiff

# Ou p4merge
git config --global merge.tool p4merge

# Abrir o merge tool
git mergetool

# Depois de resolver:
git add .
git commit
```

**PhpStorm/WebStorm:**

```
VCS → Git → Resolve Conflicts

Mostra 3 painéis:
- Left: sua versão
- Center: resultado
- Right: a versão deles
```

---

## Tipos de conflito

**1. Content conflict (conteúdo alterado):**

```bash
# As duas branches mudaram a mesma linha
git merge feature

# CONFLICT in file.php
<<<<<<< HEAD
$price = 100;
=======
$price = 150;
>>>>>>> feature
```

**2. Delete-modify conflict (exclusão vs alteração):**

```bash
# Você apagou o arquivo, eles alteraram
git merge feature

# CONFLICT (modify/delete): file.php deleted in HEAD and modified in feature

# Resolução:
# Manter a exclusão
git rm file.php

# Ou restaurar
git add file.php

git commit
```

**3. Rename conflict (renomeação):**

```bash
# As duas branches renomearam o arquivo de jeitos diferentes
# Escolher um nome
git mv old-name.php new-name.php
git add .
git commit
```

---

## Exemplos práticos

**Conflito simples:**

```php
// Antes do merge
// main:
public function store(Request $request)
{
    $user = User::create($request->all());
    return redirect()->route('users.index');
}

// feature:
public function store(Request $request)
{
    $user = User::create($request->validated());
    return response()->json($user, 201);
}

// Conflito:
<<<<<<< HEAD
public function store(Request $request)
{
    $user = User::create($request->all());
    return redirect()->route('users.index');
}
=======
public function store(Request $request)
{
    $user = User::create($request->validated());
    return response()->json($user, 201);
}
>>>>>>> feature/api

// Resolução (ficar com o melhor dos dois):
public function store(Request $request)
{
    $user = User::create($request->validated());

    if ($request->wantsJson()) {
        return response()->json($user, 201);
    }

    return redirect()->route('users.index');
}
```

**Vários conflitos:**

```bash
git merge feature

# CONFLICT in:
# - app/Controllers/UserController.php
# - app/Models/User.php
# - routes/web.php

# Resolver um conflito de cada vez
git status  # Ver o que falta

# Unmerged paths:
#   both modified:   app/Controllers/UserController.php
#   both modified:   app/Models/User.php
#   both modified:   routes/web.php

# Depois de corrigir todos os arquivos
git add app/Controllers/UserController.php
git add app/Models/User.php
git add routes/web.php
git commit
```

**Conflito no composer.lock:**

```bash
# Costuma conflitar no merge
git merge develop

# CONFLICT in composer.lock

# Resolução: recriar
git checkout --theirs composer.json
git checkout --theirs composer.lock
composer install
git add composer.json composer.lock
git commit
```

---

## Como evitar conflitos

**Pull frequente:**

```bash
# Atualizar de main/develop com frequência
git pull origin main
```

**PR pequeno:**

```bash
# Não acumular muita mudança
# Abrir PR a cada 1-2 dias
```

**Comunicação:**

```bash
# Avisar o time se você for mexer nos mesmos arquivos
```

**Rebase em vez de merge (em feature branch):**

```bash
# Rebase deixa a história linear, menos conflito
git rebase main
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Conflito aparece quando o Git não consegue juntar as mudanças sozinho
- As duas branches mudaram as mesmas linhas de jeitos diferentes
- Precisa resolver na mão

**Formato do conflito:**
- `<<<<<<< HEAD` — início das suas mudanças
- `=======` — separador
- `>>>>>>> branch` — fim das mudanças da outra branch

**Resolução:**
1. Abrir o arquivo no editor
2. Remover os marcadores
3. Deixar o código certo (ou juntar os dois)
4. `git add <arquivo>`
5. `git commit` (no merge) ou `git rebase --continue` (no rebase)

**Abortar:**
- No merge: `git merge --abort`
- No rebase: `git rebase --abort`

**Ferramentas:**
- VS Code com botões visuais
- PhpStorm com view de 3 painéis
- `git mergetool` com vimdiff/p4merge

**Tipos de conflito:**
- **Content** — conteúdo alterado
- **Delete-modify** — exclusão vs alteração
- **Rename** — renomeação

**Como evitar:**
- Pull frequente de main
- PR pequeno (1-2 dias)
- Comunicação no time
- Rebase em feature branch

---

## Exercícios práticos

### Exercício 1: Resolva um content conflict

Duas branches mudaram o mesmo método de jeitos diferentes. Resolva o conflito juntando a lógica.

<details>
<summary>Solução</summary>

```bash
# Situação:
# main: o método devolve redirect
# feature: o método devolve JSON

# Merge
git checkout main
git merge feature/api

# CONFLICT in app/Controllers/UserController.php

# Abrir o arquivo
vim app/Controllers/UserController.php

# Você vai ver:
<<<<<<< HEAD
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required',
        'email' => 'required|email',
    ]);

    $user = User::create($validated);

    return redirect()->route('users.index')
        ->with('success', 'User created');
}
=======
public function store(Request $request)
{
    $user = User::create($request->all());

    return response()->json([
        'data' => $user,
        'message' => 'User created successfully'
    ], 201);
}
>>>>>>> feature/api

# Resolução: juntar a lógica (ficar com a validation do main, adicionar o JSON response)
public function store(Request $request)
{
    // Validation do main (melhor)
    $validated = $request->validate([
        'name' => 'required',
        'email' => 'required|email',
    ]);

    $user = User::create($validated);

    // Suporte aos dois casos
    if ($request->wantsJson()) {
        return response()->json([
            'data' => $user,
            'message' => 'User created successfully'
        ], 201);
    }

    return redirect()->route('users.index')
        ->with('success', 'User created');
}

# Salvar o arquivo

# Adicionar e commitar
git add app/Controllers/UserController.php
git commit -m "Merge feature/api into main

Resolved conflict in UserController:
- Keep validation from main
- Add JSON response support from feature
- Support both web and API requests"

# Conferir se tudo funciona
php artisan test
```
</details>

### Exercício 2: Resolva um delete-modify conflict

Você apagou um arquivo antigo, mas o colega alterou. Decida o que fazer.

<details>
<summary>Solução</summary>

```bash
# Situação:
# main: apagou LegacyController.php (legado)
# feature: alterou LegacyController.php (adicionou funcionalidade)

# Merge
git checkout main
git merge feature/legacy-update

# CONFLICT (modify/delete): app/Controllers/LegacyController.php
# deleted in HEAD and modified in feature/legacy-update

# Analisar a situação
# 1. Ver o que mudou na feature
git show feature/legacy-update:app/Controllers/LegacyController.php

# 2. Ver por que foi apagado no main
git log --oneline -- app/Controllers/LegacyController.php
# Achar o commit da exclusão
git show abc123

# Opção A: Manter a exclusão (se a funcionalidade não for necessária)
git rm app/Controllers/LegacyController.php

git commit -m "Merge feature/legacy-update into main

Resolved delete-modify conflict:
- Keep file deleted (replaced by NewController)
- Legacy functionality moved to NewController"

# Opção B: Restaurar o arquivo (se as mudanças forem importantes)
git add app/Controllers/LegacyController.php

git commit -m "Merge feature/legacy-update into main

Resolved delete-modify conflict:
- Restore LegacyController with new changes
- TODO: Refactor to NewController later"

# Opção C: Extrair as mudanças e levar para o controller novo
# 1. Ver as mudanças
git show feature/legacy-update:app/Controllers/LegacyController.php > /tmp/legacy.php

# 2. Levar a lógica necessária para o NewController na mão
vim app/Controllers/NewController.php
# ... adicionar a lógica do legacy ...

# 3. Manter a exclusão
git rm app/Controllers/LegacyController.php

# 4. Adicionar as mudanças no arquivo novo
git add app/Controllers/NewController.php

git commit -m "Merge feature/legacy-update into main

Resolved delete-modify conflict:
- Keep LegacyController deleted
- Migrated new functionality to NewController
- Maintains backward compatibility"
```
</details>

### Exercício 3: Vários conflitos no rebase

Você tem uma feature branch com 5 commits. No rebase em cima de main, 3 commits deram conflito. Resolva todos.

<details>
<summary>Solução</summary>

```bash
# Situação:
# feature: 5 commits na semana
# main: avançou e mexeu nos mesmos arquivos

# Tentativa de rebase
git checkout feature/user-dashboard
git rebase main

# CONFLICT (commit 1/5): Add dashboard route
# CONFLICT in routes/web.php

# === Conflito 1 ===
# 1. Ver o conflito
git status
# both modified: routes/web.php

# 2. Abrir o arquivo
vim routes/web.php

<<<<<<< HEAD
Route::get('/home', [HomeController::class, 'index']);
Route::get('/profile', [ProfileController::class, 'show']);
=======
Route::get('/dashboard', [DashboardController::class, 'index']);
>>>>>>> Add dashboard route

# 3. Resolver (ficar com as duas rotas)
Route::get('/home', [HomeController::class, 'index']);
Route::get('/profile', [ProfileController::class, 'show']);
Route::get('/dashboard', [DashboardController::class, 'index']);

# 4. Continuar
git add routes/web.php
git rebase --continue

# === Conflito 2 ===
# CONFLICT (commit 2/5): Add dashboard controller
# CONFLICT in app/Controllers/DashboardController.php

vim app/Controllers/DashboardController.php

<<<<<<< HEAD
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('home.index');
    }
}
=======
namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }
}
>>>>>>> Add dashboard controller

# Resolver (ficar com a view certa)
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard.index');
    }
}

git add app/Controllers/DashboardController.php
git rebase --continue

# === Conflito 3 ===
# CONFLICT (commit 3/5): Add dashboard tests
# CONFLICT in tests/Feature/DashboardTest.php

vim tests/Feature/DashboardTest.php
# ... resolver o conflito ...

git add tests/Feature/DashboardTest.php
git rebase --continue

# === Commits 4-5 sem conflito ===
# Successfully rebased and updated refs/heads/feature/user-dashboard

# Force push (porque reescreveu a história)
git push --force-with-lease origin feature/user-dashboard

# Conferir o resultado
git log --oneline
# 5 commits agora em cima do main atual

# Se você se enrolar nos conflitos — abortar o rebase:
git rebase --abort
# A branch volta ao estado de antes do rebase

# Alternativa: usar rerere (reuse recorded resolution)
# Aplica sozinho conflitos que você já resolveu
git config --global rerere.enabled true

# No próximo rebase o Git lembra como você resolveu
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
