<?php
/* topo.php — cabeçalho e menu iguais em todas as páginas.
   Cada página define $titulo antes de incluir este arquivo. */
$logado = isset($_SESSION['usuario']);
$ehAdmin = $logado && $_SESSION['usuario']['tipo'] === 'admin';
$atual = basename($_SERVER['SCRIPT_NAME']);   // ex.: "servicos.php"

// Marca o link da página atual como "active" no menu
function ativo($arquivo) {
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
    <link rel="stylesheet" href="style.css">
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
                <a href="index.php"<?= ativo('index.php') ?>>Home</a>
                <a href="servicos.php"<?= ativo('servicos.php') ?>>Serviços</a>
                <a href="profissionais.php"<?= ativo('profissionais.php') ?>>Profissionais</a>
                <a href="galeria.php"<?= ativo('galeria.php') ?>>Galeria</a>
                <a href="contato.php"<?= ativo('contato.php') ?>>Contato</a>
                <a href="agendar.php"<?= ativo('agendar.php') ?>>Agendar</a>
                <a href="meus-agendamentos.php"<?= ativo('meus-agendamentos.php') ?>>Meus Agendamentos</a>
                <a href="minha-conta.php"<?= ativo('minha-conta.php') ?>>Minha Conta</a>
                <?php if ($ehAdmin): ?><a href="admin.php"<?= ativo('admin.php') ?>>Painel</a><?php endif; ?>
                <?php if ($logado): ?>
                    <a href="logout.php" class="btn-admin-nav"><i class="fa-solid fa-right-from-bracket"></i> Sair</a>
                <?php else: ?>
                    <a href="cadastro.php"<?= ativo('cadastro.php') ?>>Cadastre-se</a>
                    <a href="login.php" class="btn-admin-nav"><i class="fa-solid fa-right-to-bracket"></i> Login</a>
                <?php endif; ?>
            </nav>
        </header>
