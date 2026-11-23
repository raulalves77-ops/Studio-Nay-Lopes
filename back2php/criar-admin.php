<?php
/* criar-admin.php — cria a conta da administração. Rode UMA vez, no terminal:

       php criar-admin.php seu@email.com

   Ele pergunta a senha e grava no banco já em formato seguro (hash).
   Depois você pode apagar este arquivo. Pelo navegador ele não funciona. */

if (php_sapi_name() !== 'cli') {
    die('Execute este arquivo pelo terminal.');
}

require 'conexao.php';

$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Uso: php criar-admin.php seu@email.com\n");
}

echo 'Senha (mínimo 8 caracteres): ';
$senha = trim(fgets(STDIN));
if (strlen($senha) < 8) {
    die("Senha muito curta.\n");
}

// Se o e-mail já existir, vira admin e troca a senha
$pdo->prepare(
    "INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo) VALUES ('Administração', ?, '', ?, 'admin')
     ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash), tipo = 'admin'"
)->execute([$email, password_hash($senha, PASSWORD_DEFAULT)]);

echo "Pronto! Administrador $email salvo.\n";
