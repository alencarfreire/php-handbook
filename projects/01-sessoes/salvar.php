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
