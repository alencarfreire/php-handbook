# 11.2 Estratégias de branch e merge

## Resumo

> **Branching** — criar branches isoladas para desenvolver features sem mexer na main.
>
> **Merge strategies:** Fast-forward (linha reta), Three-way merge (merge commit), Squash (junta os commits).
>
> **Naming:** feature/, bugfix/, hotfix/, release/ para cada tipo de branch.

---

## Conteúdo

- [O que é](#o-que-é)
- [Trabalhando com branches](#trabalhando-com-branches)
- [Estratégias de merge](#estratégias-de-merge)
- [Convenções de nome](#convenções-de-nome)
- [Exemplos práticos](#exemplos-práticos)
- [Comparando as estratégias](#comparando-as-estratégias)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Branching é criar branches separadas para desenvolver features. Merge strategies são as formas de juntar as branches.

**Tipos de merge:**
- Fast-forward (merge simples)
- Three-way merge (merge com commit)
- Squash merge (junta os commits)

---

## Trabalhando com branches

**Criar e trocar:**

```bash
# Criar a branch
git branch feature/new-api

# Trocar para a branch
git checkout feature/new-api

# Criar e trocar no mesmo comando
git checkout -b feature/new-api

# Ver todas as branches
git branch -a

# Apagar a branch (local)
git branch -d feature/new-api

# Apagar a branch (force)
git branch -D feature/new-api

# Apagar a branch no servidor
git push origin --delete feature/new-api
```

**Renomear a branch:**

```bash
# Renomear a branch atual
git branch -m new-name

# Renomear outra branch
git branch -m old-name new-name
```

---

## Estratégias de merge

**Fast-forward (padrão):**

```bash
# main: A---B
# feature:     C---D

git checkout main
git merge feature/new-api

# Resultado: A---B---C---D (linha reta)
```

Fast-forward acontece quando a main não ganhou commits novos.

**Three-way merge:**

```bash
# main: A---B---E
# feature:     C---D

git checkout main
git merge feature/new-api

# Resultado:
# A---B---E---M
#      \     /
#       C---D
# M = merge commit
```

**Squash merge (junta os commits):**

```bash
# main: A---B
# feature:     C---D---E

git checkout main
git merge --squash feature/new-api
git commit -m "Add new API (squashed)"

# Resultado: A---B---F
# F tem todas as mudanças de C, D, E
```

**No fast-forward:**

```bash
# Sempre criar merge commit
git merge --no-ff feature/new-api

# Útil na história: dá para ver onde a branch começou e terminou
```

---

## Convenções de nome

**Prefixos das branches:**

```bash
# Feature nova
feature/user-authentication
feature/payment-integration

# Correção de bug
bugfix/login-error
fix/memory-leak

# Hotfix (correção urgente em production)
hotfix/security-patch
hotfix/critical-bug

# Release
release/v1.2.0
release/2024-01-15

# Experimento
experiment/new-architecture
spike/performance-test

# Refatoração
refactor/database-queries
```

---

## Exemplos práticos

**Feature branch workflow:**

```bash
# 1. Criar a branch a partir da main
git checkout main
git pull origin main
git checkout -b feature/add-comments

# 2. Trabalhar na branch
git add .
git commit -m "Add comment model"
git commit -m "Add comment controller"
git commit -m "Add comment views"

# 3. Enviar para o servidor
git push origin feature/add-comments

# 4. Atualizar a partir da main (se a main mudou)
git checkout main
git pull origin main
git checkout feature/add-comments
git merge main  # ou git rebase main

# 5. Abrir Pull Request no GitHub/GitLab

# 6. Depois do review — merge na main
git checkout main
git merge --no-ff feature/add-comments
git push origin main

# 7. Apagar a branch
git branch -d feature/add-comments
git push origin --delete feature/add-comments
```

**Hotfix workflow:**

```bash
# 1. Criar o hotfix a partir da main
git checkout main
git checkout -b hotfix/security-fix

# 2. Corrigir o problema
git add .
git commit -m "Fix security vulnerability"

# 3. Merge na main
git checkout main
git merge --no-ff hotfix/security-fix
git tag v1.2.1
git push origin main --tags

# 4. Merge na develop (se existir)
git checkout develop
git merge --no-ff hotfix/security-fix
git push origin develop

# 5. Apagar a branch
git branch -d hotfix/security-fix
```

**Conflitos no merge:**

```bash
git checkout main
git merge feature/new-api

# CONFLICT em app/Controllers/ApiController.php
# Auto-merging app/Controllers/ApiController.php
# CONFLICT (content): Merge conflict in app/Controllers/ApiController.php

# 1. Ver os arquivos em conflito
git status

# 2. Abrir o arquivo, corrigir
# <<<<<<< HEAD
# código da main
# =======
# código da feature
# >>>>>>> feature/new-api

# 3. Adicionar o arquivo corrigido
git add app/Controllers/ApiController.php

# 4. Terminar o merge
git commit -m "Merge feature/new-api into main"

# Abortar o merge (se algo deu errado)
git merge --abort
```

**Cherry-pick (pegar um commit específico):**

```bash
# Pegar o commit abc123 de outra branch
git cherry-pick abc123

# Pegar vários commits
git cherry-pick abc123 def456

# Cherry-pick com conflitos
git cherry-pick abc123
# ... corrigir os conflitos ...
git add .
git cherry-pick --continue
```

---

## Comparando as estratégias

**Fast-forward:**
- História limpa (linha reta)
- Não dá para ver onde estava a branch
- Serve para mudança simples

**No fast-forward:**
- O merge commit guarda a história da branch
- Dá para ver onde a feature começou e terminou
- Serve para feature branches

**Squash:**
- Um commit no lugar de vários
- História limpa na main
- Perde o detalhe da feature
- Serve para PR pequeno

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Branching — isola as mudanças em branches separadas
- Permite trabalhar em paralelo em tarefas diferentes
- Feature branch workflow — cria a branch, trabalha, faz merge de volta

**Naming conventions:**
- `feature/` — feature nova
- `bugfix/` — correção de bug
- `hotfix/` — correção urgente em production
- `release/` — preparação do release

**Merge strategies:**
- **Fast-forward** — linha reta (quando a main não mudou)
- **Three-way merge** — cria merge commit
- **Squash** — junta todos os commits em um
- `--no-ff` — sempre cria merge commit (para a história)

**Conflitos:**
- Você resolve na mão, editando o arquivo
- `git add` + `git commit` para terminar
- `git merge --abort` para cancelar

**Cherry-pick:**
- Copia commits específicos entre branches
- `git cherry-pick <commit-hash>`

---

## Exercícios práticos

### Exercício 1: Hotfix em production

**Enunciado:** Tem um bug crítico em production. Crie o hotfix, corrija, faça merge na main e na develop.

<details>
<summary>Solução</summary>

```bash
# 1. Conferir que está na main
git checkout main
git pull origin main

# 2. Criar a branch de hotfix
git checkout -b hotfix/critical-payment-bug

# 3. Corrigir o bug
# Editamos app/Services/PaymentService.php
git add app/Services/PaymentService.php
git commit -m "Fix critical payment processing bug

Issue: Payment was not being processed for amounts > 1000
Solution: Fix decimal precision in PaymentService

Refs: BUG-456"

# 4. Fazer push do hotfix
git push origin hotfix/critical-payment-bug

# 5. Merge na main
git checkout main
git merge --no-ff hotfix/critical-payment-bug

# 6. Criar a tag do release
git tag -a v1.2.1 -m "Hotfix: Critical payment bug"
git push origin main --tags

# 7. Merge na develop (para o bug não voltar)
git checkout develop
git pull origin develop
git merge --no-ff hotfix/critical-payment-bug
git push origin develop

# 8. Apagar a branch de hotfix
git branch -d hotfix/critical-payment-bug
git push origin --delete hotfix/critical-payment-bug

# 9. Deploy em production
# (via CI/CD ou na mão)
```
</details>

### Exercício 2: Squash de vários commits WIP

**Enunciado:** Você tem uma feature branch com 10 commits: "WIP", "fix", "typo". Faça squash antes do PR.

<details>
<summary>Solução</summary>

```bash
# Commits atuais:
git log --oneline
# abc123 fix typo
# def456 WIP
# ghi789 add tests
# jkl012 fix
# mno345 Add user profile feature
# ... (mais 5 commits)

# Opção 1: Interactive rebase (recomendado)
git rebase -i HEAD~10

# Abre o editor:
pick mno345 Add user profile feature
fixup jkl012 fix
fixup ghi789 add tests
fixup def456 WIP
fixup abc123 fix typo
# ... o resto em fixup

# Salvar e sair
# Resultado: 1 commit limpo

# Force push (porque reescreveu a história)
git push --force-with-lease origin feature/user-profile

# Opção 2: Squash merge (mais simples)
git checkout main
git merge --squash feature/user-profile
git commit -m "Add user profile feature

- Add profile page
- Add avatar upload
- Add profile edit form
- Add validation
- Add tests

Refs: FEAT-123"

# Opção 3: Soft reset + commit novo
git reset --soft HEAD~10
git commit -m "Add user profile feature

Complete implementation with tests and validation.

Refs: FEAT-123"
git push --force-with-lease origin feature/user-profile
```
</details>

### Exercício 3: Cherry-pick de um commit na branch de release

**Enunciado:** Na develop tem um bugfix importante (commit abc123). Inclua só ele na release/v1.2.

<details>
<summary>Solução</summary>

```bash
# 1. Achar o commit na develop
git checkout develop
git log --oneline --grep="bugfix"

# Achou: abc123 Fix validation bug in LoginController

# 2. Trocar para a branch de release
git checkout release/v1.2

# 3. Cherry-pick do commit
git cherry-pick abc123

# Se não tiver conflito — pronto
git push origin release/v1.2

# 4. Se tiver conflitos
# CONFLICT em app/Http/Controllers/LoginController.php

# Abrir o arquivo, corrigir os conflitos
# <<<<<<< HEAD
# código da release
# =======
# código do cherry-pick
# >>>>>>>

# Resolver o conflito
git add app/Http/Controllers/LoginController.php
git cherry-pick --continue

# 5. Fazer push
git push origin release/v1.2

# Alternativa: cherry-pick de vários commits
git cherry-pick abc123 def456 ghi789

# Ou um intervalo de commits
git cherry-pick abc123..ghi789

# Abortar o cherry-pick se algo deu errado
git cherry-pick --abort
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
