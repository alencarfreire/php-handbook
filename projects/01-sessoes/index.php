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
