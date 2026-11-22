<?php
// Sair: apaga a sessão (esquece quem estava logado) e volta para o login.
require 'conexao.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
