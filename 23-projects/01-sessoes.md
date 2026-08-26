# 23.1 Páginas com sessão

> **TL;DR**
> PHP puro. Home com form. POST grava nome e e-mail na `$_SESSION`. `/perfil.php` lê. Sem banco, sem Composer, sem framework. Sessão some quando o browser fecha o cookie (ou você chama `session_destroy()`).

**Gerado por IA. Não existe no handbook original da CodeMate.**

## Conteúdo

- [O que é](#o-que-é)
- [Como rodar](#como-rodar)
- [index.php — o form](#indexphp--o-form)
- [salvar.php — o POST](#salvarphp--o-post)
- [perfil.php — a página protegida](#perfilphp--a-página-protegida)
- [sair.php — destruir a sessão](#sairphp--destruir-a-sessão)
- [Quando usar](#quando-usar)
- [Na entrevista](#na-entrevista)
- [Recapitulando](#recapitulando)
- [Exercícios práticos](#exercícios-práticos)

---

## O que é

Sessão é estado do usuário no servidor, amarrado a um cookie (`PHPSESSID`). O browser manda o cookie. O PHP reabre o array `$_SESSION`.

`session_start()` no topo de **cada** script que lê ou grava. Sem isso, `$_SESSION` não existe.

Quatro arquivos. Nenhum é recorte — é o app inteiro. Os comentários no fonte dizem o *porquê*.

| Arquivo | Papel |
|---|---|
| `index.php` | Form. GET. Flash de erro. Saudação se já tem sessão. |
| `salvar.php` | POST. Valida. Grava `$_SESSION['usuario']`. Redirect. |
| `perfil.php` | GET. Sem sessão → home. Com sessão → mostra nome e e-mail. |
| `sair.php` | Destroi sessão e cookie. Redirect pra home. |

## Como rodar

Não precisa clonar o repo. [Baixe o zip](/downloads/01-sessoes.zip), extraia, suba o server.

```bash
unzip 01-sessoes.zip
cd 01-sessoes
php -S localhost:8000
```

Abre `http://localhost:8000`. Sem Apache. Sem nginx. O fonte completo também está [nesta página](#indexphp--o-form) e em [código completo](/23-projects/01-sessoes-codigo).

---

## index.php — o form

`session_start()` primeiro. Tira o flash `erro` (grava, redirect, lê, `unset`). Se já tem `usuario`, mostra o nome. O form manda POST para `/salvar.php`. Saída passa por `htmlspecialchars`.

```php
<?php
// Sem isto, $_SESSION não existe — mesmo com o cookie PHPSESSID no browser.
session_start();

// Flash: gravou no POST, redirecionou, leu aqui, apaga. Senão o erro gruda.
$erro = $_SESSION['erro'] ?? null;
unset($_SESSION['erro']);

$usuario = $_SESSION['usuario'] ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Home — sessão</title>
    <style>
        body { font-family: sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1rem; }
        label { display: block; margin: 0.75rem 0 0.25rem; }
        input { width: 100%; padding: 0.4rem; box-sizing: border-box; }
        button { margin-top: 1rem; padding: 0.5rem 1rem; }
        .erro { color: #b00020; }
        .ok { background: #f3f3f3; padding: 0.75rem 1rem; }
    </style>
</head>
<body>
    <h1>Home</h1>
    <p>PHP puro. O form grava na sessão. Não tem banco.</p>

    <?php if ($usuario): ?>
        <p class="ok">
            <?php // htmlspecialchars: o nome veio do form. Sem isso, XSS. ?>
            Olá, <?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?>.
            <a href="/perfil.php">Ver perfil</a>
        </p>
    <?php endif; ?>

    <?php if ($erro): ?>
        <p class="erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="/salvar.php">
        <label for="nome">Nome</label>
        <input id="nome" name="nome" type="text" required placeholder="João">

        <label for="email">E-mail</label>
        <input id="email" name="email" type="email" required placeholder="joao@email.com">

        <button type="submit">Guardar na sessão</button>
    </form>
</body>
</html>
```

## salvar.php — o POST

Só aceita POST (405 se não for). `trim` + e-mail válido. Erro vai na sessão e volta pra home (não imprime HTML aqui). Sucesso grava o array e redireciona pro perfil — **PRG**: F5 no perfil não reenvia o form.

```php
<?php
session_start();

// Este script só grava. GET aqui seria bug — 405 e para.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Só POST');
}

$nome  = trim((string) ($_POST['nome'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro'] = 'Nome e e-mail válidos, por favor.';
    header('Location: /index.php');
    exit;
}

// Isto some quando o processo morre. Não é banco.
$_SESSION['usuario'] = [
    'nome'  => $nome,
    'email' => $email,
];

// PRG: redirect depois do POST. F5 no perfil não reenvia o form.
header('Location: /perfil.php');
exit;
```

## perfil.php — a página protegida

Sem `$_SESSION['usuario']`, volta pra home. Com sessão, imprime os dois campos. XSS: `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.

```php
<?php
session_start();

// Página protegida: sem sessão, nem tenta renderizar. Volta pra home.
if (empty($_SESSION['usuario'])) {
    header('Location: /index.php');
    exit;
}

$usuario = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil — sessão</title>
    <style>
        body { font-family: sans-serif; max-width: 28rem; margin: 3rem auto; padding: 0 1rem; }
        dl { background: #f3f3f3; padding: 1rem; }
        dt { font-weight: 700; margin-top: 0.5rem; }
    </style>
</head>
<body>
    <h1>Perfil</h1>
    <p>Estes dados estão na <code>$_SESSION</code>. Recarregou a página? Continuam. Fechou o servidor? Sumiram.</p>

    <dl>
        <dt>Nome</dt>
        <dd><?= htmlspecialchars($usuario['nome'], ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>E-mail</dt>
        <dd><?= htmlspecialchars($usuario['email'], ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>

    <p>
        <a href="/index.php">Home</a>
        ·
        <a href="/sair.php">Sair</a>
    </p>
</body>
</html>
```

## sair.php — destruir a sessão

Esvazia o array, apaga o cookie de sessão e chama `session_destroy()`. O cookie sozinho no browser não reabre o que já morreu no servidor.

```php
<?php
session_start();

// Esvazia o array desta request.
$_SESSION = [];

// Sem apagar o cookie, o browser ainda manda o PHPSESSID velho.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Apaga o store no servidor. O próximo session_start() nasce vazio.
session_destroy();

header('Location: /index.php');
exit;
```

---

## Quando usar

- Login raso, carrinho, flash message, wizard de 2 passos.
- **Não** use sessão como banco. Não persiste entre máquinas. Não escala sozinha (arquivo em disco no servidor).
- API JSON de verdade costuma ir de token. Página HTML clássica vai de cookie de sessão.

## Na entrevista

> "Sessão no PHP é `session_start()` + cookie `PHPSESSID`. Eu guardo o usuário no `$_SESSION` depois do POST. A outra página só lê. Sem `session_start()`, o array não existe. Não é banco: se o processo/store some, os dados somem."

Se puxarem segurança: cookie `HttpOnly`, `Secure` em HTTPS, `SameSite`. Neste bolso o default do built-in server basta — mas você **fala** isso.

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

Já está no `index.php` deste projeto: `if ($usuario)` + link para `/perfil.php`. O exercício é **entender** o que já está lá, ou esconder o form depois do login se achar melhor.

</details>

### Exercício 3

**Enunciado:**
Explique o que acontece se você comentar `session_start()` só no `perfil.php`.

<details>
<summary>Solução</summary>

`$_SESSION['usuario']` não está lá. `empty` dá true. Redirect pra home. Ou notice, conforme a versão/config. O cookie até chega, mas o PHP não carregou o store. É o bug mais comum de quem “copia metade do tutorial”.

</details>

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
