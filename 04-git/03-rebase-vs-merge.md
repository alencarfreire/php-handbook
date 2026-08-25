# 11.3 Rebase vs Merge

## Resumo

> **Merge** — junta branches e cria um merge commit. Preserva o histórico.
>
> **Rebase** — move os commits para uma base nova. Histórico linear.
>
> **Regra:** NUNCA faça rebase de branch pública (main, develop). Só em branch pessoal.

---

## Conteúdo

- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [Merge](#merge)
- [Rebase](#rebase)
- [Interactive Rebase](#interactive-rebase)
- [Quando usar](#quando-usar)
- [Exemplos práticos](#exemplos-práticos)
- [Force push](#force-push)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**Merge:**
Junta as branches e cria um merge commit. Preserva o histórico inteiro.

**Rebase:**
Move os commits para uma base nova. Reescreve o histórico para ficar linear.

---

## Como funciona

**Merge:**

```bash
# Antes do merge:
# main:    A---B---C
# feature:      \---D---E

git checkout main
git merge feature

# Depois do merge:
# main:    A---B---C-------M
#               \         /
#                \---D---E
# M = merge commit
```

**Rebase:**

```bash
# Antes do rebase:
# main:    A---B---C
# feature:      \---D---E

git checkout feature
git rebase main

# Depois do rebase:
# main:    A---B---C
# feature:            \---D'---E'
# D', E' — commits novos (rebased)

# Depois o merge será fast-forward:
git checkout main
git merge feature

# Resultado:
# main:    A---B---C---D'---E' (linha reta)
```

---

## Merge

**Prós:**
- Preserva o histórico completo
- Seguro (não reescreve commits)
- Mostra quando houve merge

**Contras:**
- Muitos merge commits (histórico sujo)
- Grafo complicado

**Uso:**

```bash
# Merge comum
git checkout main
git merge feature

# Merge sem fast-forward (sempre cria merge commit)
git merge --no-ff feature

# Squash merge (todos os commits em um)
git merge --squash feature
git commit -m "Add feature X"
```

---

## Rebase

**Prós:**
- Histórico linear (limpo)
- git log fácil de ler
- Sem merge commit extra

**Contras:**
- Reescreve o histórico (perigoso em branch compartilhada)
- Pode perder contexto

**Uso:**

```bash
# Rebase em main
git checkout feature
git rebase main

# Interactive rebase (editar commits)
git rebase -i HEAD~3

# Continue depois de resolver os conflitos
git add .
git rebase --continue

# Cancelar o rebase
git rebase --abort

# Force push (precisa depois do rebase)
git push --force-with-lease origin feature
```

---

## Interactive Rebase

**Editar commits:**

```bash
git rebase -i HEAD~3

# Abre o editor:
pick abc123 Add login
pick def456 Fix typo
pick ghi789 Update tests

# Comandos:
# pick   — deixar como está
# reword — mudar a commit message
# edit   — alterar o commit
# squash — juntar com o anterior
# fixup  — squash sem guardar a message
# drop   — apagar o commit

# Exemplo: squash de 3 commits em um
pick abc123 Add login
squash def456 Fix typo
squash ghi789 Update tests

# Salvar e sair
# Abre o editor da nova commit message
```

**Exemplo prático:**

```bash
# Você tem 5 commits:
# - Add user model
# - Fix typo
# - Add tests
# - Fix test
# - Update readme

git rebase -i HEAD~5

# Compactar em 3 com sentido:
pick abc123 Add user model
fixup def456 Fix typo
pick ghi789 Add tests
fixup jkl012 Fix test
pick mno345 Update readme

# Resultado:
# - Add user model (com o typo corrigido)
# - Add tests (com a correção)
# - Update readme
```

---

## Quando usar

**Merge para:**
- Branch pública/compartilhada (main, develop)
- Preservar o histórico completo
- Feature branch no time

**Rebase para:**
- Branch pessoal (antes do push)
- Limpar o histórico antes do PR
- Atualizar a feature branch com o main

**Regra de ouro:**
❌ **NUNCA faça rebase de branch pública (main, develop)**
✅ Rebase só na sua branch local

---

## Exemplos práticos

**Atualizar a feature a partir do main (merge):**

```bash
git checkout feature
git merge main

# Prós: seguro, preserva o histórico
# Contras: merge commit na feature
```

**Atualizar a feature a partir do main (rebase):**

```bash
git checkout feature
git rebase main

# Prós: histórico linear
# Contras: precisa de force push se já deu push
```

**Limpar o histórico antes do PR:**

```bash
# Você tem 10 commits: "WIP", "fix", "typo", etc.

git rebase -i HEAD~10

# Squash em 2-3 commits com sentido:
pick abc123 Add user authentication
fixup def456 WIP
fixup ghi789 fix
fixup jkl012 typo
pick mno345 Add tests
fixup pqr678 fix tests

# Resultado: 2 commits limpos
git push --force-with-lease origin feature
```

**Rebase com conflitos:**

```bash
git rebase main

# CONFLICT em app/Controller.php
# ... resolver os conflitos ...

git add app/Controller.php
git rebase --continue

# Se ainda tiver conflito — repetir
# Se quiser cancelar
git rebase --abort
```

---

## Force push

**Depois do rebase precisa de force push:**

```bash
# ❌ Perigoso (pode sobrescrever mudança de outra pessoa)
git push --force origin feature

# ✅ Mais seguro (não sobrescreve se alguém já deu push)
git push --force-with-lease origin feature

# --force-with-lease checa se a branch remota não mudou
```

---

## Na entrevista

**Resposta estruturada:**

**Merge:**
- Junta as branches e cria um merge commit
- Preserva o histórico inteiro
- Seguro em branch compartilhada
- Pode deixar o grafo de commits complicado

**Rebase:**
- Move os commits para uma base nova
- Deixa o histórico linear
- Reescreve o histórico (muda os hashes dos commits)
- Perigoso em branch pública

**Quando usar:**
- **Merge** — branch compartilhada (main, develop), preservar histórico
- **Rebase** — branch pessoal, limpar histórico antes do PR

**Interactive rebase:**
- `git rebase -i HEAD~N` para editar commits
- **pick** — deixar, **squash** — juntar, **reword** — mudar a message
- **fixup** — squash sem guardar a message, **drop** — apagar

**Force push:**
- Depois do rebase precisa de force push
- `--force-with-lease` é mais seguro que `--force`
- Checa se o remote não mudou

**Regra de ouro:**
- NUNCA faça rebase de branch pública/compartilhada
- Só na branch pessoal, antes do merge em main

---

## Exercícios práticos

### Exercício 1: Rebase da feature branch no main

Você tem uma feature branch com 5 commits. O main andou. Faça rebase da feature no main para o histórico ficar linear.

<details>
<summary>Solução</summary>

```bash
# Estado atual:
# main:    A---B---C---D (origin mudou)
# feature:      \---E---F---G---H---I (seus commits)

# 1. Atualizar o main
git checkout main
git pull origin main

# 2. Mudar para feature
git checkout feature

# 3. Rebase em main
git rebase main

# Se não tiver conflito:
# feature:                D---E'---F'---G'---H'---I'
# (commits movidos para o main atual)

# 4. Force push (porque reescreveu o histórico)
git push --force-with-lease origin feature

# Se tiver conflito:
# CONFLICT em app/Controller.php

# Abra o arquivo e resolva os conflitos
# <<<<<<< HEAD
# código do main
# =======
# seu código
# >>>>>>>

# Resolver o conflito
git add app/Controller.php
git rebase --continue

# Se o próximo commit também tiver conflito — repetir
# Cancele o rebase se você se perder
git rebase --abort

# Depois do rebase ok
git push --force-with-lease origin feature

# Conferir o resultado
git log --graph --oneline --all
```
</details>

### Exercício 2: Squash de commits com interactive rebase

Você tem 7 commits na feature branch. Precisa fazer squash em 2 commits lógicos antes do PR.

<details>
<summary>Solução</summary>

```bash
# Commits atuais:
git log --oneline
# abc123 Update readme
# def456 Fix test typo
# ghi789 Add tests
# jkl012 Fix validation
# mno345 Add validation
# pqr678 Fix user model
# stu901 Add user model

# Meta: 2 commits
# 1. Add user model with validation
# 2. Add tests and update readme

# 1. Abrir o interactive rebase
git rebase -i HEAD~7

# 2. Abre o editor:
pick stu901 Add user model
fixup pqr678 Fix user model
pick mno345 Add validation
fixup jkl012 Fix validation
pick ghi789 Add tests
fixup def456 Fix test typo
pick abc123 Update readme

# 3. Mudar para:
pick stu901 Add user model
squash pqr678 Fix user model
squash mno345 Add validation
squash jkl012 Fix validation
pick ghi789 Add tests
squash def456 Fix test typo
squash abc123 Update readme

# Salvar e sair

# 4. Abre o editor da primeira commit message:
# Apague o texto e escreva:
Add user model with validation

- Implement User model
- Add email and password validation
- Add unique email constraint

Refs: FEAT-456

# Salvar e sair

# 5. Abre o editor da segunda commit message:
Add tests and documentation

- Add user model tests
- Add validation tests
- Update README with user model usage

Refs: FEAT-456

# 6. Resultado:
git log --oneline
# xyz789 Add tests and documentation
# abc456 Add user model with validation

# 7. Force push
git push --force-with-lease origin feature

# Alternativa: uma passada com reword
git rebase -i HEAD~7
# No editor:
pick stu901 Add user model
fixup pqr678 Fix user model
fixup mno345 Add validation
fixup jkl012 Fix validation
reword ghi789 Add tests
fixup def456 Fix test typo
fixup abc123 Update readme
```
</details>

### Exercício 3: Cancele o rebase e restaure a branch

Você fez rebase, se perdeu nos conflitos, quebrou tudo. Cancele o rebase e restaure a branch.

<details>
<summary>Solução</summary>

```bash
# Situação: rebase no meio, vários conflitos

# Opção 1: Cancelar o rebase atual
git rebase --abort

# A branch volta ao estado de antes do rebase
git log --oneline
# Tudo como estava

# Opção 2: Se o rebase e o push já foram, mas quebrou tudo
# (e você quer voltar ao estado antigo)

# 1. Achar o commit de antes do rebase no reflog
git reflog
# abc123 HEAD@{0}: rebase finished: ...
# def456 HEAD@{1}: rebase: ...
# ghi789 HEAD@{2}: checkout: moving from feature to main
# jkl012 HEAD@{3}: commit: My last good commit

# 2. Resetar a branch no último commit bom
git reset --hard HEAD@{3}
# ou
git reset --hard jkl012

# 3. Force push da branch restaurada
git push --force-with-lease origin feature

# Opção 3: Se a branch remota ainda está ok
git reset --hard origin/feature
git log --oneline
# Branch restaurada a partir do remote

# Comandos úteis para debug:
# Ver todas as mudanças no reflog
git reflog --date=relative

# Ver um commit específico do reflog
git show HEAD@{5}

# Criar uma branch de backup antes de operação perigosa
git branch backup-feature
# Agora pode experimentar à vontade, o backup está salvo

# Restaurar do backup
git checkout backup-feature
git branch -D feature
git checkout -b feature
git push --force-with-lease origin feature
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
