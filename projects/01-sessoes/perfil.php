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
