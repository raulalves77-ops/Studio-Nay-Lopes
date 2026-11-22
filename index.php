<?php
// Página inicial: só texto fixo e o botão para agendar.
require 'conexao.php';
$titulo = 'Home';
require 'topo.php';
?>
        <main class="hero-card">
            <div class="hero-content">
                <h1>Realce sua<br><span class="hero-highlight">Beleza Natural ✨</span></h1>
                <p class="hero-subtitle">Luzes, Progressiva, Escova &amp; Cílios com Cuidado Único</p>
                <a href="agendar.php" class="btn-primary">AGENDAR AGORA</a>
            </div>
            <div class="hero-image-wrapper">
                <img src="imagens/logo-studio-nay-lopes.png" alt="Studio Nay Lopes" class="hero-img logo-img">
            </div>
        </main>
<?php require 'rodape.php'; ?>
