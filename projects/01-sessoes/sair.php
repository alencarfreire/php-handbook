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
