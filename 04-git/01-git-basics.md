# 11.1 O básico do Git

## Resumo

> **Git** — sistema de controle de versão distribuído. Rastreia mudanças no código.
>
> **Workflow básico:** clone → checkout -b → add → commit → push.
>
> **Importante:** staging area para commits seletivos, .gitignore para ignorar arquivos, reset/revert para desfazer.

---

## Conteúdo

- [O que é](#o-que-é)
- [Comandos principais](#comandos-principais)
- [Trabalhando com mudanças](#trabalhando-com-mudanças)
- [Trabalhando com arquivos](#trabalhando-com-arquivos)
- [Trabalhando com remotes](#trabalhando-com-remotes)
- [Exemplos práticos](#exemplos-práticos)
- [Na entrevista](#na-entrevista)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Git é um sistema de controle de versão distribuído. Rastreia mudanças no código e deixa o time trabalhar junto.

**Operações principais:**
- `clone` — baixar o repositório
- `pull` — trazer as mudanças
- `add` — colocar no staging
- `commit` — salvar as mudanças
- `push` — enviar para o servidor

---

## Comandos principais

**Init e clone:**

```bash
# Criar um repositório novo
git init

# Clonar um repositório
git clone https://github.com/user/repo.git

# Clonar uma branch específica
git clone -b develop https://github.com/user/repo.git
```

**Workflow básico:**

```bash
# 1. Ver o status
git status

# 2. Adicionar arquivos no staging
git add .                    # Todos os arquivos
git add src/Controller.php   # Um arquivo específico
git add *.php               # Por máscara

# 3. Fazer o commit
git commit -m "Adiciona autenticação de usuário"

# 4. Enviar para o servidor
git push origin main
```

**Trazer mudanças:**

```bash
# Trazer mudanças e fazer merge
git pull origin main

# Trazer mudanças sem merge
git fetch origin

# Ver o que mudou
git diff origin/main
```

---

## Trabalhando com mudanças

**Ver as mudanças:**

```bash
# O que mudou (unstaged)
git diff

# O que está no staging
git diff --staged

# Histórico de commits
git log
git log --oneline
git log --graph --oneline --all

# Mudanças em um arquivo específico
git log -p src/Controller.php
```

**Desfazer mudanças:**

```bash
# Desfazer mudanças no arquivo (antes do add)
git checkout -- src/Controller.php
git restore src/Controller.php

# Tirar do staging (depois do add, antes do commit)
git reset HEAD src/Controller.php
git restore --staged src/Controller.php

# Desfazer o último commit (manter as mudanças)
git reset --soft HEAD~1

# Desfazer o último commit (apagar as mudanças)
git reset --hard HEAD~1

# Desfazer o commit e criar um novo
git revert abc123
```

---

## Trabalhando com arquivos

**Adicionar e remover:**

```bash
# Renomear arquivo
git mv old.php new.php

# Remover arquivo
git rm file.php

# Remover do Git, mas deixar no disco
git rm --cached file.php

# Ignorar arquivos (.gitignore)
echo ".env" >> .gitignore
echo "vendor/" >> .gitignore
git add .gitignore
```

**.gitignore para Laravel:**

```gitignore
/vendor
/node_modules
/.env
/.env.backup
/storage/*.key
/public/hot
/public/storage
/storage/logs/*
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*
.phpunit.result.cache
.idea/
.vscode/
*.log
```

---

## Trabalhando com remotes

**Gerenciar repositórios remotos:**

```bash
# Ver os remotes
git remote -v

# Adicionar remote
git remote add origin https://github.com/user/repo.git

# Trocar a URL
git remote set-url origin https://github.com/user/new-repo.git

# Remover remote
git remote remove origin

# Ver informações do remote
git remote show origin
```

**Push e pull:**

```bash
# Push na branch
git push origin main

# Push de todas as branches
git push --all

# Push com tags
git push --tags

# Force push (cuidado!)
git push --force origin main

# Pull com rebase
git pull --rebase origin main
```

---

## Exemplos práticos

**Workflow típico:**

```bash
# 1. Começar o trabalho
git clone https://github.com/company/project.git
cd project

# 2. Criar a branch da tarefa
git checkout -b feature/user-auth

# 3. Fazer as mudanças
# ... edita os arquivos ...

# 4. Conferir o que mudou
git status
git diff

# 5. Adicionar e commitar
git add app/Controllers/AuthController.php
git add app/Models/User.php
git commit -m "Adiciona autenticação de usuário

- Adiciona métodos de login/register
- Adiciona geração de token JWT
- Adiciona validação de senha"

# 6. Enviar para o servidor
git push origin feature/user-auth
```

**Trabalhando com conflitos:**

```bash
# 1. Trazer as mudanças
git pull origin main
# Conflict em src/Controller.php

# 2. Abrir o arquivo, você vê:
<<<<<<< HEAD
// Seu código
public function index() {
    return view('home');
}
=======
// Código da main
public function index() {
    return view('dashboard');
}
>>>>>>> main

# 3. Corrigir na mão, deixar o que vale
public function index() {
    return view('dashboard');
}

# 4. Adicionar e commitar
git add src/Controller.php
git commit -m "Resolve conflito de merge"
```

**Ver o histórico:**

```bash
# Últimos 5 commits
git log -5 --oneline

# Commits da última semana
git log --since="1 week ago"

# Commits de um autor
git log --author="John"

# Mudanças no arquivo
git log --follow -- src/Controller.php

# Grafo das branches
git log --graph --oneline --decorate --all
```

**Aliases úteis:**

```bash
# Colocar no ~/.gitconfig
git config --global alias.st status
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.ci commit
git config --global alias.lg "log --graph --oneline --decorate --all"
git config --global alias.unstage "reset HEAD --"

# Uso
git st      # no lugar de git status
git lg      # grafo bonito
```

---

## Na entrevista

**Resposta estruturada:**

**O que é:**
- Git é um sistema de controle de versão distribuído
- Cada dev tem uma cópia completa do repositório
- Funciona offline

**Workflow básico:**
- `git clone` — baixar o repositório
- `git checkout -b` — criar a branch
- `git add` → `git commit` — salvar as mudanças
- `git push` — enviar para o servidor
- `git pull` — trazer as mudanças (fetch + merge)

**Staging area:**
- Zona entre o working directory e o commit
- Permite commitar arquivos seletivamente
- `git add` coloca no staging

**Desfazer mudanças:**
- `git reset --soft` — desfaz o commit, mantém as mudanças
- `git reset --hard` — apaga as mudanças de vez
- `git revert` — cria um commit novo de reversão

**Boas práticas:**
- `.gitignore` para ignorar arquivos (.env, vendor/)
- Atomic commits (um commit = uma tarefa)
- Commit messages com sentido

---

## Exercícios práticos

### Exercício 1: Desfaça um commit acidental

**Enunciado:** Você commitou o .env com secrets por engano. Desfaça o commit de forma que o arquivo fique no working directory, mas saia do histórico.

<details>
<summary>Solução</summary>

```bash
# 1. Desfazer o último commit (manter as mudanças)
git reset --soft HEAD~1

# 2. Tirar o .env do staging
git reset HEAD .env

# 3. Colocar o .env no .gitignore
echo ".env" >> .gitignore

# 4. Commitar o resto dos arquivos
git add .
git commit -m "Adiciona autenticação sem secrets"

# 5. Se já deu push — precisa de force push (PERIGOSO!)
# Melhor avisar o time e rotacionar os secrets

# Alternativa: apagar do histórico de vez
git filter-branch --index-filter 'git rm --cached --ignore-unmatch .env' HEAD

# Ou com BFG Repo-Cleaner (mais rápido)
# java -jar bfg.jar --delete-files .env
# git reflog expire --expire=now --all
# git gc --prune=now --aggressive
```
</details>

### Exercício 2: Escreva um commit message com sentido

**Enunciado:** Você tem mudanças em 3 arquivos: AuthController.php, User.php, CreateUsersTable.php. Crie um commit message estruturado.

<details>
<summary>Solução</summary>

```bash
# Ver o que mudou
git diff

# Adicionar os arquivos separados para atomic commits
git add app/Http/Controllers/AuthController.php
git add app/Models/User.php
git commit -m "Adiciona autenticação de usuário

- Implementa métodos de login/register no AuthController
- Adiciona geração de token JWT
- Adiciona hash de senha com bcrypt
- Adiciona validação de email

Refs: PROJ-123"

# Commit separado para a migration
git add database/migrations/2024_01_01_create_users_table.php
git commit -m "Adiciona migration da tabela users

- Adiciona campos email, password, name
- Adiciona unique constraint no email
- Adiciona timestamps

Refs: PROJ-123"

# Regras do commit message:
# 1. Primeira linha — resumo curto (até 50 caracteres)
# 2. Linha em branco
# 3. Descrição (o quê e por quê)
# 4. Link do issue/ticket
```
</details>

### Exercício 3: Restaure um arquivo apagado

**Enunciado:** Você apagou o CommandController.php por engano e já commitou. Restaure o arquivo pelo histórico.

<details>
<summary>Solução</summary>

```bash
# 1. Achar o commit em que o arquivo foi apagado
git log --oneline -- app/Http/Controllers/CommandController.php

# Vai mostrar:
# abc123 Remove controller antigo
# def456 Adiciona funcionalidade de command
# ...

# 2. Ver o conteúdo do arquivo antes da exclusão
git show def456:app/Http/Controllers/CommandController.php

# 3. Restaurar o arquivo do commit anterior
git checkout def456 -- app/Http/Controllers/CommandController.php

# 4. Commitar a restauração
git add app/Http/Controllers/CommandController.php
git commit -m "Restaura CommandController.php

Arquivo apagado por engano no commit abc123.
Restaurado do commit def456."

# Alternativa: restaurar do último estado
git checkout HEAD~1 -- app/Http/Controllers/CommandController.php

# Achar quando o arquivo foi apagado
git rev-list -n 1 HEAD -- app/Http/Controllers/CommandController.php
# Devolve o commit em que o arquivo existia pela última vez
```
</details>

---

*Parte do [PHP/Laravel Interview Handbook](/) | Feito com ❤️ pela equipe [CodeMate](https://codemate.team)*
