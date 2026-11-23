<?php
/* topo.php — cabeçalho e menu iguais em todas as páginas.
   Cada página define $titulo antes de incluir este arquivo. */
$logado         = (bool) usuario_logado();
$usuarioAtual   = usuario_logado();
$ehAdmin        = eh_admin();
$ehProfissional = eh_profissional();
$atual          = basename($_SERVER['SCRIPT_NAME'] ?? '');
$titulo         = $titulo ?? 'Studio Nay Lopes';

// Marca o link da página atual como "active" no menu
function ativo($arquivo)
{
    global $atual;
    return $arquivo === $atual ? ' class="active"' : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> - Studio Nay Lopes</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        /* Aplica o tema escuro salvo antes de desenhar a página (evita "piscar") */
        try { var t = localStorage.getItem('sbd-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
    </script>
</head>
<body>
    <div class="main-wrapper">
        <header class="navbar">
            <div class="logo"><i class="fa-solid fa-spa logo-icon"></i><div class="logo-text"><span>Studio</span><strong>Nay Lopes</strong></div></div>
            <div class="nav-actions">
                <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Ativar modo escuro"><i class="fa-solid fa-moon"></i></button>
                <button type="button" class="nav-toggle" aria-expanded="false" aria-label="Abrir menu"><i class="fa-solid fa-bars"></i></button>
            </div>
            <nav class="nav-links" id="site-navigation">
                <a href="/index.php"<?= ativo('index.php') ?>>Home</a>
                <a href="/php/servicos.php"<?= ativo('servicos.php') ?>>Serviços</a>
                <a href="/php/profissionais.php"<?= ativo('profissionais.php') ?>>Profissionais</a>
                <a href="/php/galeria.php"<?= ativo('galeria.php') ?>>Galeria</a>
                <a href="/php/contato.php"<?= ativo('contato.php') ?>>Contato</a>

                <?php if (!$logado || ($usuarioAtual['tipo'] ?? '') === 'cliente'): ?>
                    <a href="/php/agendar.php"<?= ativo('agendar.php') ?>>Agendar</a>
                <?php endif; ?>

                <?php if ($logado && ($usuarioAtual['tipo'] ?? '') === 'cliente'): ?>
                    <a href="/php/meus-agendamentos.php"<?= ativo('meus-agendamentos.php') ?>>Meus Agendamentos</a>
                    <a href="/php/minha-conta.php"<?= ativo('minha-conta.php') ?>>Minha Conta</a>
                <?php endif; ?>

                <?php if ($ehAdmin || $ehProfissional): ?>
                    <a href="/php/admin.php"<?= ativo('admin.php') ?>><i class="fa-solid fa-chart-line"></i> Painel</a>
                <?php endif; ?>

                <?php if ($logado): ?>
                    <span class="nav-user"><i class="fa-solid fa-user"></i> <?= e(primeiro_nome($usuarioAtual['nome'] ?? '')) ?></span>
                    <a href="/php/logout.php" class="btn-admin-nav"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
                <?php else: ?>
                    <a href="/php/cadastro.php"<?= ativo('cadastro.php') ?>>Cadastre-se</a>
                    <a href="/php/login.php" class="btn-admin-nav"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                <?php endif; ?>
            </nav>
        </header>
