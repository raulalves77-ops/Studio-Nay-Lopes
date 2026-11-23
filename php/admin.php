<?php
/* admin.php — painel do studio.
   Acesso: administrador(a) e profissionais.
   O admin vê tudo; a profissional vê a própria agenda.
   Os formulários são tratados em admin/acoes.php e depois
   redirecionam (padrão POST/Redirect/GET). */
require 'conexao.php';
exigir_login(['admin', 'profissional']);

// Marca que o painel está no controle: os arquivos de admin/ só podem
// ser incluídos a partir daqui (nunca abertos direto pelo navegador).
define('PAINEL_STUDIO', true);

$usuario = usuario_logado();
$ehAdmin = eh_admin();

// ---- Perfil de profissional de quem está logado ----
$meuProfissionalId = null;
if (!$ehAdmin) {
    $st = $pdo->prepare('SELECT id FROM profissionais WHERE usuario_id = ? AND ativo = 1');
    $st->execute([(int) $usuario['id']]);
    $meuProfissionalId = (int) $st->fetchColumn() ?: null;
    $_SESSION['usuario']['profissional_id'] = $meuProfissionalId;

    if (!$meuProfissionalId) {
        $titulo = 'Painel';
        require 'topo.php';
        ?>
        <main class="page-card">
            <h1><i class="fa-solid fa-triangle-exclamation"></i> Perfil não vinculado</h1>
            <p>Sua conta ainda não está ligada a um perfil de profissional.
               Peça ao studio para vincular o acesso em <strong>Painel &rarr; Profissionais</strong>.</p>
            <p><a class="btn-outline" href="logout.php">Sair</a></p>
        </main>
        <?php
        require 'rodape.php';
        exit;
    }
}

// ---- Seções disponíveis ----
$todasAsSecoes = [
    'visao'         => ['Visão geral',   'fa-chart-pie'],
    'agenda'        => ['Agenda',        'fa-calendar-days'],
    'caixa'         => ['Caixa',         'fa-wallet'],
    'servicos'      => ['Serviços',      'fa-scissors'],
    'profissionais' => ['Profissionais', 'fa-user-tie'],
    'clientes'      => ['Clientes',      'fa-users'],
];

$secoesDisponiveis = $ehAdmin
    ? $todasAsSecoes
    : array_intersect_key($todasAsSecoes, ['visao' => true, 'agenda' => true]);

$secao = (string) ($_GET['secao'] ?? 'visao');
if (!isset($secoesDisponiveis[$secao])) {
    $secao = 'visao';
}

// ---- Formulários (só POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require '../admin/acoes.php';
}

$titulo = 'Painel — ' . $secoesDisponiveis[$secao][0];
require 'topo.php';
?>
        <main class="admin-dashboard">
            <div class="admin-header">
                <div>
                    <span class="admin-eyebrow"><i class="fa-solid fa-spa"></i> Painel do studio</span>
                    <h1><?= e($secoesDisponiveis[$secao][0]) ?></h1>
                    <p>Olá, <?= e(primeiro_nome($usuario['nome'])) ?>.
                        <?= $ehAdmin
                            ? 'Acompanhe a agenda, o caixa e a gestão do studio.'
                            : 'Acompanhe aqui a sua agenda de atendimentos.' ?>
                    </p>
                </div>
            </div>

            <?php exibir_flash(); ?>

            <?php require '../admin/_menu.php'; ?>

            <section class="admin-secao">
                <?php require '../admin/' . $secao . '.php'; ?>
            </section>
        </main>
<?php require 'rodape.php'; ?>
