# Código completo — 23.1 Páginas com sessão

> Fonte que **roda**. Gerado por IA. Não existe no handbook original da CodeMate.

Pasta no GitHub: [`projects/01-sessoes/`](https://github.com/alencarfreire/php-handbook/tree/translate-pt-br/projects/01-sessoes)

Walkthrough: [23.1](/23-projects/01-sessoes)

## Como rodar

```bash
cd projects/01-sessoes
php -S localhost:8000
```

## `README.md`

```md
# 01 — Páginas com sessão

PHP puro. Sem Composer, sem banco, sem framework.

**Gerado por IA.** Não faz parte do handbook original da CodeMate.

## O que você treina

- `session_start()` / `$_SESSION`
- Form HTML + POST
- Redirect depois do POST (PRG)
- Página que exige sessão
- `htmlspecialchars` na saída

## Como rodar

```bash
cd projects/01-sessoes
php -S localhost:8000
```

Abre http://localhost:8000

1. Preenche nome e e-mail na home
2. Envia → vai para `/perfil.php` com os dados
3. `Sair` destroi a sessão

## O que não entra (de propósito)

- Banco
- Senha / login de verdade
- CSRF token
- Framework
```

## `index.php`

```php
<?php
session_start();

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

## `perfil.php`

```php
<?php
session_start();

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

## `sair.php`

```php
<?php
session_start();

$_SESSION = [];

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

session_destroy();

header('Location: /index.php');
exit;
```

## `salvar.php`

```php
<?php
session_start();

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

$_SESSION['usuario'] = [
    'nome'  => $nome,
    'email' => $email,
];

header('Location: /perfil.php');
exit;
```

*Parte do [PHP/Laravel Interview Handbook](/) — seção gerada por IA, só neste fork.*
