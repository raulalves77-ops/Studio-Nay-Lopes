<?php
/* ============================================================
   conexao.php — todas as páginas começam com:  require 'conexao.php';
   Ele faz 3 coisas: (1) conecta no banco, (2) liga a sessão
   (é ela que "lembra" quem está logado) e (3) define 4 funçõezinhas.
   ============================================================ */

// ---- (1) Dados do banco: ajuste para o seu computador ----
$servidor   = 'sql211.infinityfree.com';
$banco      = 'if0_43486335_studio_nay_lopes';
$usuario_db = 'if0_43486335';
$senha_db   = 'guilheermeRyu';

// ---- Horário de atendimento: segunda a sábado, das 9h às 19h ----
$DIAS_ATENDIMENTO = [1, 2, 3, 4, 5, 6];   // 0 = domingo, 1 = segunda ... 6 = sábado
$HORA_ABRE   = 9;
$HORA_FECHA  = 19;
$TEXTO_HORARIO = 'Segunda a Sábado, das 9h às 19h';

date_default_timezone_set('America/Sao_Paulo');

// ---- (2) Sessão: guarda o usuário logado em $_SESSION['usuario'] ----
if (php_sapi_name() !== 'cli') {
    session_start();
}

// ---- (1) Conexão com o banco usando PDO ----
// Todas as páginas usam a variável $pdo para falar com o MySQL.
try {
    $pdo = new PDO(
        "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
        $usuario_db,
        $senha_db,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // mostra erro de SQL
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // linhas viram arrays
        ]
    );
} catch (PDOException $erro) {
    die('Não foi possível conectar ao banco de dados. Confira o arquivo conexao.php.');
}

// ---- (3) Funções de ajuda ----

// Protege o texto antes de mostrar na tela (impede que alguém injete HTML/JavaScript).
// Use SEMPRE que imprimir algo que veio do banco ou de um formulário.
function e($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// 350 -> "R$ 350,00"
function moeda($valor) {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

// "2026-12-05 14:00:00" -> "05/12/2026 14:00"
function data_br($data) {
    return date('d/m/Y H:i', strtotime($data));
}

// Só deixa entrar quem está logado com o tipo certo ('cliente' ou 'admin').
// Se não estiver, manda para a tela de login e encerra a página.
function exigir_login($tipo) {
    if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['tipo'] !== $tipo) {
        header('Location: login.php');
        exit;
    }
}
