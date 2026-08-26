# 23.1 Páginas com sessão

> **TL;DR**
> PHP puro. Home com form. POST grava nome e e-mail na `$_SESSION`. `/perfil.php` lê. Sem banco, sem Composer, sem framework. Sessão some quando o browser fecha o cookie (ou você chama `session_destroy()`).

## Conteúdo

- [Código completo](/23-projects/01-sessoes-codigo)
- [O que é](#o-que-é)
- [Como funciona](#como-funciona)
- [O form e o POST](#o-form-e-o-post)
- [A página que lê a sessão](#a-página-que-lê-a-sessão)
- [Sair](#sair)
- [Quando usar](#quando-usar)
- [Na entrevista](#na-entrevista)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

**O que é:**
Sessão é estado do usuário no servidor, amarrado a um cookie (`PHPSESSID`). O browser manda o cookie. O PHP reabre o array `$_SESSION`.

**Como funciona:**
`session_start()` no topo de **cada** script que lê ou grava. Sem isso, `$_SESSION` não existe.

**Código completo no site:** [todos os arquivos](/23-projects/01-sessoes-codigo). Pasta: `projects/01-sessoes/`.

## Como funciona

Três arquivos de verdade:

| Arquivo | Papel |
|---|---|
| `index.php` | Form. GET. Mostra erro da sessão, se tiver. |
| `salvar.php` | POST. Valida. Grava `$_SESSION['usuario']`. Redirect. |
| `perfil.php` | GET. Se não tem sessão, volta pra home. Senão mostra os dados. |
| `sair.php` | Destroi a sessão. Redirect pra home. |

Subir:

```bash
cd projects/01-sessoes
php -S localhost:8000
```

Abre `http://localhost:8000`. Sem Apache. Sem nginx.

## O form e o POST

**O que é:**
HTML manda `POST` para `salvar.php`. PHP lê `$_POST`. Não confie no client.

**Como funciona:**

```php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Só POST');
}

$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = 'Nome e e-mail válidos, por favor.';
    header('Location: /index.php');
    exit;
}

$_SESSION['usuario'] = [
    'nome'  => $nome,
    'email' => $email,
];

header('Location: /perfil.php');
exit;
```

**Importante na entrevista:**
Redirect depois do POST (PRG). Se o usuário der F5 no perfil, não reenvia o form.

Flash de erro também vai na sessão: grava, redirect, lê, `unset`.

## A página que lê a sessão

```php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: /index.php');
    exit;
}

$usuario = $_SESSION['usuario'];
```

Aí você imprime `$usuario['nome']` e `$usuario['email']`. Escape com `htmlspecialchars`. XSS de form cai nesta frase.

## Sair

```php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /index.php');
exit;
```

Cookie ainda pode existir no browser. `session_destroy()` apaga o arquivo/store no servidor. O próximo `session_start()` cria outra sessão vazia.

## Quando usar

- Login raso, carrinho, flash message, wizard de 2 passos.
- **Não** use sessão como banco. Não persiste entre máquinas. Não escala sozinha (arquivo em disco no servidor).
- API JSON de verdade costuma ir de token. Página HTML clássica vai de cookie de sessão.

## Na entrevista

> "Sessão no PHP é `session_start()` + cookie `PHPSESSID`. Eu guardo o usuário no `$_SESSION` depois do POST. A outra página só lê. Sem `session_start()`, o array não existe. Não é banco: se o processo/store some, os dados somem."

Se puxarem segurança: cookie `HttpOnly`, `Secure` em HTTPS, `SameSite`. Neste projeto de bolso o default do built-in server basta — mas você **fala** isso.

## Recapitulando

- `session_start()` em todo script que toca sessão
- POST → valida → grava → redirect (PRG)
- Página protegida: se não tem `$_SESSION['usuario']`, volta
- `htmlspecialchars` na saída
- Sem framework, sem PDO, sem Composer — de propósito

## Exercícios práticos

### Exercício 1

**Enunciado:**
Adicione um campo `telefone` no form. Grave na sessão. Mostre no perfil.

<details>
<summary>Solução</summary>

No form, um `input name="telefone"`. Em `salvar.php`, `$telefone = trim($_POST['telefone'] ?? '');` e entra no array `$_SESSION['usuario']`. No perfil, imprime com `htmlspecialchars`. Validar formato é extra — o mínimo é não gravar vazio se a regra pedir.

</details>

### Exercício 2

**Enunciado:**
Se o usuário já tem sessão e abre a home, mostre “Olá, João” e um link para o perfil. Sem isso, a home finge que ninguém entrou.

<details>
<summary>Solução</summary>

Depois do `session_start()` no `index.php`, se `!empty($_SESSION['usuario'])`, echo do nome e `<a href="/perfil.php">`. O form pode continuar ali — ou você esconde. Os dois são válidos; na entrevista diga o que escolheu e por quê.

</details>

### Exercício 3

**Enunciado:**
Explique o que acontece se você comentar `session_start()` só no `perfil.php`.

<details>
<summary>Solução</summary>

`$_SESSION['usuario']` não está lá. `empty` dá true. Redirect pra home. Ou notice, conforme a versão/config. O cookie até chega, mas o PHP não carregou o store. É o bug mais comum de quem “copia metade do tutorial”.

</details>

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
