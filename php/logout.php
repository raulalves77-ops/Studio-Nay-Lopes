<?php
/* logout.php — encerra a sessão por completo e volta para o login. */
require 'conexao.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $parametros = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $parametros['path'],
            'domain'   => $parametros['domain'],
            'secure'   => (bool) $parametros['secure'],
            'httponly' => (bool) $parametros['httponly'],
            'samesite' => 'Lax',
        ]
    );
}

session_destroy();
header('Location: login.php');
exit;
