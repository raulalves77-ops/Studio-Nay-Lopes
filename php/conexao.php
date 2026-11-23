<?php
/* ============================================================
   conexao.php — arquivo de inicialização usado por todas as páginas.

   Ele faz 4 coisas:
   1. conecta no banco de dados (PDO);
   2. liga a sessão com cookies seguros;
   3. define as configurações do studio (dias e horários);
   4. carrega as funções de apoio (texto, dinheiro, data, login,
      proteção CSRF, mensagens e regras de agenda/caixa).
   ============================================================ */

// ---- (0) Erros e cabeçalhos de segurança -------------------------------
// Erros vão para o log do PHP, nunca para a tela (evita vazar caminhos
// absolutos, trechos de SQL e stack traces para quem visita o site).
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Atrás de proxy/CDN o HTTPS costuma vir só no cabeçalho encaminhado.
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') === '443')
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

if (php_sapi_name() !== 'cli') {
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');       // o painel não pode ser embutido em iframe
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "script-src 'self' 'unsafe-inline'; "
        . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        . "font-src 'self' https://cdnjs.cloudflare.com; "
        . "img-src 'self' data:; media-src 'self'; connect-src 'self'; "
        . "object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'"
    );
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ---- (1) Banco de dados ------------------------------------------------
// As credenciais NÃO ficam mais neste arquivo.
// Precedência: variáveis de ambiente DB_* (usadas pelo rodar-local.bat) e,
// se existir, o config.php ao lado deste arquivo (não versionado).
// Marca para os arquivos de configuração que a conexão já está em curso
// (eles recusam ser abertos direto pelo navegador).
if (!defined('CONEXAO_EM_CURSO')) {
    define('CONEXAO_EM_CURSO', true);
}

$config = [];
$arquivoConfig = __DIR__ . '/config.php';
if (is_file($arquivoConfig)) {
    $carregado = require $arquivoConfig;
    if (is_array($carregado)) {
        $config = $carregado;
    }
}

// Ajustes locais (opcional): config.local.php com o banco do seu computador.
// Só é usado quando a requisição vem da própria máquina (127.0.0.1/::1) ou
// pelo terminal, então mesmo que o arquivo seja enviado por engano ao
// servidor, o site em produção continua usando o config.php.
$arquivoLocal = __DIR__ . '/config.local.php';
if (is_file($arquivoLocal)) {
    $da_propria_maquina = php_sapi_name() === 'cli'
        || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);

    if ($da_propria_maquina) {
        $local = require $arquivoLocal;
        if (is_array($local)) {
            $config = array_merge($config, $local);
        }
    }
}

// A variável de ambiente vale mesmo quando é vazia (ex.: root local sem senha).
$do_ambiente = static function (string $chave, string $padrao = '') use ($config): string {
    $valor = getenv($chave);
    if ($valor !== false) {
        return (string) $valor;
    }
    return array_key_exists($chave, $config) ? (string) $config[$chave] : $padrao;
};

$servidor   = $do_ambiente('DB_HOST');
$banco      = $do_ambiente('DB_NAME');
$usuario_db = $do_ambiente('DB_USER');
$senha_db   = $do_ambiente('DB_PASS');

if ($servidor === '' || $banco === '' || $usuario_db === '') {
    error_log('Banco de dados não configurado (DB_HOST/DB_NAME/DB_USER ou config.php).');
    http_response_code(500);
    die('Banco de dados não configurado. Veja o LEIA-ME.md.');
}

// ---- Configuração do studio --------------------------------------------
// 0 = domingo, 1 = segunda ... 6 = sábado
$DIAS_ATENDIMENTO  = [1, 2, 3, 4, 5, 6];
$HORA_ABRE         = 8;
$HORA_FECHA        = 18;          // o atendimento termina às 18h
$INTERVALO_MINUTOS = 30;          // a agenda anda de 30 em 30 minutos
$TEXTO_HORARIO     = 'Segunda a sábado, das 8h às 18h';

date_default_timezone_set('America/Sao_Paulo');

// ---- (2) Sessão --------------------------------------------------------
if (php_sapi_name() !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    // Recusa IDs de sessão que o PHP não emitiu (proteção contra fixação).
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,       // o JavaScript não consegue ler o cookie
        'samesite' => 'Lax',      // dificulta ataques CSRF
        'secure'   => $https,     // só trafega por HTTPS quando existe HTTPS
    ]);
    session_start();

    $agoraSessao   = time();
    $limiteInativo = 1800;   // 30 minutos sem uso encerram a sessão

    if (!empty($_SESSION['ultimo_acesso'])
        && $agoraSessao - (int) $_SESSION['ultimo_acesso'] > $limiteInativo) {
        $_SESSION = [];
        session_destroy();
        redirecionar('login.php?expirada=1');
    }

    // Renova o ID de tempos em tempos, mesmo durante o uso.
    if (empty($_SESSION['id_emitido']) || $agoraSessao - (int) $_SESSION['id_emitido'] > 900) {
        session_regenerate_id(true);
        $_SESSION['id_emitido'] = $agoraSessao;
    }

    $_SESSION['ultimo_acesso'] = $agoraSessao;
}

// ---- Conexão PDO -------------------------------------------------------
// $pdo é a variável usada em todas as páginas para falar com o MySQL.
try {
    $pdo = new PDO(
        "mysql:host=$servidor;dbname=$banco;charset=utf8mb4",
        $usuario_db,
        $senha_db,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // usa prepared statements de verdade
        ]
    );
} catch (PDOException $erro) {
    error_log('Falha ao conectar no banco: ' . $erro->getMessage());
    http_response_code(500);
    die('Não foi possível conectar ao banco de dados. Confira as credenciais em conexao.php.');
}

// ---- (3 e 4) Funções de apoio ------------------------------------------
require_once __DIR__ . '/../lib/agenda.php';
require_once __DIR__ . '/../lib/caixa.php';

// ============================================================
// Texto, dinheiro e datas
// ============================================================

/** Escapa qualquer texto antes de imprimir na tela (evita HTML/JS injetado). */
function e($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** 350 -> "R$ 350,00" */
function moeda($valor)
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/** "2026-12-05 14:00:00" -> "05/12/2026 14:00" */
function data_br($data)
{
    $ts = $data ? strtotime((string) $data) : false;
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

/** "2026-12-05" -> "05/12/2026" */
function data_br_dia($data)
{
    $ts = $data ? strtotime((string) $data) : false;
    return $ts ? date('d/m/Y', $ts) : '—';
}

/** "2026-12-05 14:00:00" -> "14:00" */
function hora_br($data)
{
    $ts = $data ? strtotime((string) $data) : false;
    return $ts ? date('H:i', $ts) : '—';
}

/** 60 -> "1h", 90 -> "1h30", 45 -> "45min" */
function duracao_texto($minutos)
{
    $minutos = max(0, (int) $minutos);
    $horas   = intdiv($minutos, 60);
    $resto   = $minutos % 60;

    if ($horas && $resto) {
        return $horas . 'h' . str_pad((string) $resto, 2, '0', STR_PAD_LEFT);
    }
    if ($horas) {
        return $horas . 'h';
    }
    return $resto . 'min';
}

/** "2026-11" -> "Novembro/2026". Usado no filtro do caixa. */
function mes_br($mes)
{
    $nomes = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];
    $partes = explode('-', (string) $mes);
    if (count($partes) !== 2) {
        return (string) $mes;
    }
    $numero = (int) $partes[1];
    return ($nomes[$numero] ?? $mes) . '/' . $partes[0];
}

/** Primeiro nome, para a saudação do menu. */
function primeiro_nome($nome)
{
    $partes = preg_split('/\s+/', trim((string) $nome));
    return $partes[0] ?? '';
}

/** Telefone brasileiro: 10 (fixo) ou 11 (celular) dígitos. */
function telefone_valido($telefone)
{
    $digitos = preg_replace('/\D+/', '', (string) $telefone);
    return strlen($digitos) >= 10 && strlen($digitos) <= 11;
}

/** Converte "45,50" / "R$ 45.50" em 45.5. Devolve null se for inválido. */
function valor_decimal($texto)
{
    $texto = trim((string) $texto);
    if ($texto === '') {
        return null;
    }
    $texto = str_replace(['R$', ' '], '', $texto);
    if (strpos($texto, ',') !== false) {          // formato brasileiro
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    }
    if (!is_numeric($texto)) {
        return null;
    }
    $valor = round((float) $texto, 2);

    // Rejeita infinito/NaN (ex.: "1e309") e valores fora da faixa do caixa.
    if (!is_finite($valor) || $valor < 0 || $valor > 1000000) {
        return null;
    }
    return $valor;
}

// ============================================================
// Status dos agendamentos
// ============================================================

/** Status aceitos no banco e o texto mostrado na tela. */
function rotulo_status($status)
{
    $rotulos = [
        'Pendente'   => 'Pendente',
        'Confirmado' => 'Confirmado',
        'Concluido'  => 'Concluído',
        'Cancelado'  => 'Cancelado',
        'Faltou'     => 'Não compareceu',
    ];
    return $rotulos[$status] ?? (string) $status;
}

/** Classe CSS do selo colorido de cada status. */
function classe_status($status)
{
    $classes = [
        'Pendente'   => 'badge-pendente',
        'Confirmado' => 'badge-confirmado',
        'Concluido'  => 'badge-pago',
        'Cancelado'  => 'badge-cancelado',
        'Faltou'     => 'badge-faltou',
    ];
    return $classes[$status] ?? 'badge-pendente';
}

/** Formas de pagamento aceitas no caixa. */
function formas_pagamento()
{
    return ['Dinheiro', 'Pix', 'Cartão de débito', 'Cartão de crédito', 'Outro'];
}

// ============================================================
// Login, sessão e permissões
// ============================================================

/** O usuário logado (array) ou null. */
function usuario_logado()
{
    return $_SESSION['usuario'] ?? null;
}

/** O usuário logado é administrador(a)/dono(a) do studio? */
function eh_admin()
{
    return (usuario_logado()['tipo'] ?? '') === 'admin';
}

/** O usuário logado é uma profissional? */
function eh_profissional()
{
    return (usuario_logado()['tipo'] ?? '') === 'profissional';
}

/** Redireciona e encerra a página. */
function redirecionar($url)
{
    header('Location: ' . $url);
    exit;
}

/**
 * Exige login. $tipos pode ser 'cliente' ou uma lista como ['admin', 'profissional'].
 * Sem login -> tela de login. Com o tipo errado -> área correta do usuário.
 */
function exigir_login($tipos = 'cliente')
{
    global $pdo;

    $tipos = (array) $tipos;
    $usuario = usuario_logado();

    if (!$usuario) {
        redirecionar('login.php');
    }

    // Conta desativada perde o acesso na hora, mesmo com sessão aberta.
    $st = $pdo->prepare('SELECT ativo FROM usuarios WHERE id = ?');
    $st->execute([(int) ($usuario['id'] ?? 0)]);
    $ativo = $st->fetchColumn();
    if ($ativo === false || (int) $ativo !== 1) {
        $_SESSION = [];
        session_destroy();
        redirecionar('login.php');
    }

    if (!in_array($usuario['tipo'] ?? '', $tipos, true)) {
        redirecionar(($usuario['tipo'] ?? '') === 'cliente' ? 'minha-conta.php' : 'admin.php');
    }
}

// ============================================================
// CSRF (proteção dos formulários)
// ============================================================

/** Cria (uma vez por sessão) o token que vai dentro dos formulários. */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Troca o token CSRF. Chamado depois do login/cadastro para o token criado
 * antes da autenticação não continuar valendo.
 */
function csrf_renovar()
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/** Campo escondido pronto para colar dentro de <form>. */
function campo_csrf()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Confere o token em toda requisição POST. Interrompe se for inválido. */
function verificar_csrf()
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || empty($_SESSION['csrf_token'])
        || !hash_equals($_SESSION['csrf_token'], $enviado)) {
        http_response_code(419);
        die('Sua sessão expirou. Volte, recarregue a página e tente novamente.');
    }
}

// ============================================================
// Mensagens entre páginas (flash)
// ============================================================

/** Guarda uma mensagem para a próxima página. $tipo: 'ok' ou 'erro'. */
function flash($tipo, $mensagem)
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/** Imprime e apaga as mensagens guardadas. */
function exibir_flash()
{
    foreach ($_SESSION['flash'] ?? [] as $item) {
        $tipo = ($item['tipo'] ?? '') === 'erro' ? 'erro' : 'ok';
        echo '<div class="alerta alerta-' . $tipo . '">' . e($item['mensagem'] ?? '') . '</div>';
    }
    unset($_SESSION['flash']);
}
