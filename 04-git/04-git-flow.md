# 11.4 Git Flow

## Resumo

> **Git Flow** — branching model para gerenciar releases em projetos grandes.
>
> **Estrutura:** main (production), develop (desenvolvimento), feature/ (features), release/ (preparar o release), hotfix/ (correções urgentes).
>
> **Workflow:** feature → develop → release → main. Hotfix → main + develop.

---

## Conteúdo

- [O que é](#o-que-é)
- [Estrutura das branches](#estrutura-das-branches)
- [Workflow](#workflow)
- [Exemplo de projeto](#exemplo-de-projeto)
- [Alternativas](#alternativas)
- [Quando usar](#quando-usar)
- [Dicas práticas](#dicas-práticas)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Git Flow é um branching model para gerenciar releases. Estrutura de branches para projetos grandes.

**Branches principais:**
- `main` (production)
- `develop` (desenvolvimento)
- `feature/*` (features novas)
- `release/*` (preparar o release)
- `hotfix/*` (correções urgentes)

---

## Estrutura das branches

**Branches permanentes:**

```
main     — código de production, só releases estáveis
develop  — integração de features novas, próximo release
```

**Branches temporárias:**

```
feature/*  — features novas (a partir de develop)
release/*  — preparar o release (a partir de develop)
hotfix/*   — correções urgentes (a partir de main)
```

**Esquema:**

```
main:     v1.0 --------- v1.1 ----------- v1.2
            \            /  \            /
develop:     \--A--B--C----D--E--F--G--H---
                  \     /      \    /
feature/x:         F1--F2        F3-F4
```

---

## Workflow

**1. Feature (feature nova):**

```bash
# Criar feature a partir de develop
git checkout develop
git pull origin develop
git checkout -b feature/user-profile

# Trabalhar
git add .
git commit -m "Add profile page"
git commit -m "Add avatar upload"

# Merge de volta em develop
git checkout develop
git merge --no-ff feature/user-profile
git push origin develop

# Apagar a branch
git branch -d feature/user-profile
```

**2. Release (preparar o release):**

```bash
# Criar release a partir de develop
git checkout develop
git checkout -b release/1.2.0

# Corrigir bugs, atualizar a versão
git commit -m "Bump version to 1.2.0"
git commit -m "Fix minor bugs"

# Merge em main (production)
git checkout main
git merge --no-ff release/1.2.0
git tag -a v1.2.0 -m "Release 1.2.0"
git push origin main --tags

# Merge de volta em develop
git checkout develop
git merge --no-ff release/1.2.0
git push origin develop

# Apagar a branch
git branch -d release/1.2.0
```

**3. Hotfix (correção urgente):**

```bash
# Criar hotfix a partir de main
git checkout main
git checkout -b hotfix/security-fix

# Corrigir
git commit -m "Fix security vulnerability"

# Merge em main
git checkout main
git merge --no-ff hotfix/security-fix
git tag -a v1.2.1 -m "Hotfix 1.2.1"
git push origin main --tags

# Merge em develop
git checkout develop
git merge --no-ff hotfix/security-fix
git push origin develop

# Apagar a branch
git branch -d hotfix/security-fix
```

---

## Exemplo de projeto

**Inicializar o Git Flow:**

```bash
# Instalar git-flow (macOS)
brew install git-flow

# Inicialização
git flow init

# Perguntas (pode deixar o padrão):
# - Production branch: main
# - Development branch: develop
# - Feature prefix: feature/
# - Release prefix: release/
# - Hotfix prefix: hotfix/
```

**Trabalhar com feature:**

```bash
# Criar feature
git flow feature start user-auth

# Trabalhar
git add .
git commit -m "Add authentication"

# Finalizar a feature (merge em develop)
git flow feature finish user-auth
```

**Trabalhar com release:**

```bash
# Criar release
git flow release start 1.2.0

# Atualizar a versão, corrigir bugs
git commit -m "Bump version"

# Finalizar o release (merge em main e develop, criar tag)
git flow release finish 1.2.0
```

**Trabalhar com hotfix:**

```bash
# Criar hotfix
git flow hotfix start 1.2.1

# Corrigir o bug
git commit -m "Fix critical bug"

# Finalizar o hotfix (merge em main e develop)
git flow hotfix finish 1.2.1
```

---

## Alternativas

**GitHub Flow (simplificado):**

```
main  — production
       \
feature — sempre a partir de main, merge via PR
```

Mais simples que Git Flow. Serve para continuous deployment.

**GitLab Flow:**

```
main → production → stable
```

Branches extras de environment (staging, production).

**Trunk-Based Development:**

```
main  — todo mundo trabalha em main
       \
feature — feature branches curtas (1-2 dias)
```

Para times com CI/CD e feature flags.

---

## Quando usar

**Git Flow para:**
- Releases planejados
- Várias versões em production
- Time grande

**NÃO para:**
- Continuous deployment
- Projetos pequenos
- Desenvolvimento solo

---

## Dicas práticas

**Naming conventions:**

```bash
# Feature
feature/add-user-authentication
feature/update-payment-gateway

# Release
release/1.2.0
release/2024-Q1

# Hotfix
hotfix/fix-login-bug
hotfix/security-patch-1.2.1

# Bugfix (em develop)
bugfix/fix-email-validation
```

**Commit messages:**

```bash
# Feature
"Add user authentication feature"
"Implement JWT token generation"

# Release
"Bump version to 1.2.0"
"Update changelog for 1.2.0"

# Hotfix
"Fix critical security vulnerability in auth"
"Hotfix: Resolve memory leak in cache"
```

**Proteção de branches (GitHub/GitLab):**

```bash
# Settings → Branches → Branch protection rules

main:
✅ Require pull request reviews (1+)
✅ Require status checks to pass
✅ Require branches to be up to date
✅ No force push
✅ No deletion

develop:
✅ Require pull request reviews
⬜ Allow force push (para rebase)
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Git Flow é um branching model para gerenciar releases
- Abordagem estruturada de versionamento
- Serve para releases planejados

**Estrutura das branches:**
- **main** — código de production, releases estáveis
- **develop** — integração de features, próximo release
- **feature/** — features novas (a partir de develop)
- **release/** — preparar o release (a partir de develop)
- **hotfix/** — correções urgentes (a partir de main)

**Workflow:**
- Feature → develop (features novas)
- Release → main + develop (release)
- Hotfix → main + develop (correção urgente)
- Tags para versões (v1.2.0)

**Alternativas:**
- **GitHub Flow** — mais simples, uma branch main, PR para features
- **Trunk-Based** — branches curtas, feature flags
- A escolha depende do processo de release

**Quando usar:**
- Releases planejados (não continuous deployment)
- Time grande (precisa de estrutura)
- Várias versões em production

---

## Exercícios práticos

### Exercício 1: Ciclo completo de release com Git Flow

**Enunciado:** Crie uma feature, faça merge em develop, crie um release, faça merge em main e develop, adicione a tag.

<details>
<summary>Solução</summary>

```bash
# Passo 1: Inicializar o Git Flow (se ainda não fez)
git flow init
# Deixar tudo no padrão

# Passo 2: Criar feature
git flow feature start payment-integration
# Cria automaticamente a branch feature/payment-integration a partir de develop

# Passo 3: Trabalhar na feature
echo "Payment integration code" > app/Services/PaymentService.php
git add app/Services/PaymentService.php
git commit -m "Add PaymentService with Stripe integration"

git add app/Controllers/PaymentController.php
git commit -m "Add payment controller"

git add tests/Feature/PaymentTest.php
git commit -m "Add payment integration tests"

# Passo 4: Finalizar a feature
git flow feature finish payment-integration
# Automaticamente:
# - merge em develop
# - apaga a branch feature/payment-integration
# - muda para develop

# Passo 5: Criar release
git flow release start 1.3.0
# Cria automaticamente a branch release/1.3.0 a partir de develop

# Passo 6: Preparar o release
# Atualizar a versão
echo "1.3.0" > version.txt
git add version.txt
git commit -m "Bump version to 1.3.0"

# Atualizar o CHANGELOG
echo "## 1.3.0\n- Add payment integration" >> CHANGELOG.md
git add CHANGELOG.md
git commit -m "Update CHANGELOG for 1.3.0"

# Corrigir os últimos bugs
git commit -m "Fix minor UI bugs before release"

# Passo 7: Finalizar o release
git flow release finish 1.3.0
# Automaticamente:
# - merge em main
# - cria a tag v1.3.0
# - merge de volta em develop
# - apaga a branch release/1.3.0

# Abre o editor da tag message. Escreva:
Release version 1.3.0

- Payment integration with Stripe
- Bug fixes
- UI improvements

# Passo 8: Push de tudo
git checkout main
git push origin main --tags

git checkout develop
git push origin develop

# Conferir o resultado
git log --graph --oneline --all --decorate
```
</details>

### Exercício 2: Hotfix de bug crítico

**Enunciado:** Bug crítico em production. Crie um hotfix, corrija, faça merge em main e develop.

<details>
<summary>Solução</summary>

```bash
# Situação: production em v1.3.0, bug crítico no PaymentService

# Passo 1: Criar hotfix a partir de main
git checkout main
git pull origin main

git flow hotfix start 1.3.1
# Cria automaticamente a branch hotfix/1.3.1 a partir de main

# Passo 2: Corrigir o bug
# Edite app/Services/PaymentService.php
git add app/Services/PaymentService.php
git commit -m "Fix critical bug in payment validation

Issue: Payment validation was failing for amounts > 1000
Cause: Incorrect decimal handling
Solution: Use bcmath for precise calculations

Refs: BUG-789"

# Adicionar teste de regressão
git add tests/Feature/PaymentBugTest.php
git commit -m "Add regression test for payment bug"

# Atualizar a versão
echo "1.3.1" > version.txt
git add version.txt
git commit -m "Bump version to 1.3.1"

# Atualizar o CHANGELOG
echo "## 1.3.1 (Hotfix)\n- Fix payment validation bug" >> CHANGELOG.md
git add CHANGELOG.md
git commit -m "Update CHANGELOG for hotfix 1.3.1"

# Passo 3: Finalizar o hotfix
git flow hotfix finish 1.3.1
# Automaticamente:
# - merge em main
# - cria a tag v1.3.1
# - merge em develop (para o bug não voltar)
# - apaga a branch hotfix/1.3.1

# Abre o editor da tag message:
Hotfix 1.3.1: Critical payment bug fix

Critical bug fix for payment validation failing on amounts > 1000.

IMPORTANT: Deploy immediately to production.

# Passo 4: Push de tudo
git checkout main
git push origin main --tags

git checkout develop
git push origin develop

# Passo 5: Deploy em production (via CI/CD ou na mão)
# Avisar o time sobre o hotfix

# Alternativa sem git-flow:
git checkout main
git checkout -b hotfix/1.3.1
# ... correções ...
git checkout main
git merge --no-ff hotfix/1.3.1
git tag -a v1.3.1 -m "Hotfix message"
git checkout develop
git merge --no-ff hotfix/1.3.1
git push origin main develop --tags
```
</details>

### Exercício 3: Várias features ao mesmo tempo

**Enunciado:** Você tem 2 desenvolvedores em features diferentes. Gerencie o merge em develop sem conflitos.

<details>
<summary>Solução</summary>

```bash
# Situação:
# - Developer 1 trabalha em feature/user-profile
# - Developer 2 trabalha em feature/notifications

# === Developer 1 ===
# Passo 1: Criar feature de perfil
git checkout develop
git pull origin develop
git flow feature start user-profile

# Trabalhar
git add app/Controllers/ProfileController.php
git commit -m "Add profile controller"

git add app/Models/Profile.php
git commit -m "Add profile model"

git add resources/views/profile.blade.php
git commit -m "Add profile view"

# Finalizar a feature
git flow feature finish user-profile
# merge em develop

git push origin develop

# === Developer 2 (ao mesmo tempo) ===
# Passo 2: Criar feature de notificações
git checkout develop
git pull origin develop
git flow feature start notifications

# Trabalhar
git add app/Notifications/UserNotification.php
git commit -m "Add user notification"

git add app/Controllers/NotificationController.php
git commit -m "Add notification controller"

# Passo 3: Developer 1 já fez push em develop
# Atualizar a branch da feature a partir de develop antes do finish
git checkout develop
git pull origin develop  # Pegar as mudanças do Developer 1

git checkout feature/notifications
git rebase develop  # Rebase no develop atual

# Se tiver conflito — resolva:
# git add .
# git rebase --continue

# Finalizar a feature
git flow feature finish notifications
# merge em develop (já com as mudanças do Developer 1)

git push origin develop

# === Conferir o resultado ===
git checkout develop
git log --graph --oneline --all

# Deve ficar assim:
# * merge feature/notifications
# |\
# | * Add notification controller
# | * Add user notification
# * | merge feature/user-profile
# |\|
# | * Add profile view
# | * Add profile model
# | * Add profile controller
# |/
# * (older develop commits)

# === Boas práticas para trabalho em paralelo ===

# 1. Pull frequente de develop
git checkout feature/my-feature
git fetch origin develop
git rebase origin/develop

# 2. PRs pequenos (1-2 dias de trabalho)
# Menos chance de conflito

# 3. Comunicação
# "Estou mexendo em UserController.php" — no Slack/Discord

# 4. Separar as áreas de responsabilidade
# Developer 1: User module
# Developer 2: Notification module
# Menos sobreposição = menos conflito

# 5. Code review antes do merge
# Conferir se as mudanças não conflitam

# 6. CI/CD
# Testes automáticos no merge em develop
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
