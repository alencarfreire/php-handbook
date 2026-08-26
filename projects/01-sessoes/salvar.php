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
