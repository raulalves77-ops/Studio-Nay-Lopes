<?php
// Página de contato: texto fixo. O horário vem de conexao.php ($TEXTO_HORARIO).
require 'conexao.php';
$titulo = 'Contato';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-envelope"></i> Fale Conosco</h1>
            <div class="form-group"><label><i class="fa-solid fa-location-dot"></i> Endereço</label><p>Rua Erva de Touro, 159 — São Paulo</p></div>
            <div class="form-group"><label><i class="fa-solid fa-phone"></i> Telefone / WhatsApp</label><p ><a  href="https://wa.me/5511986377706" target="_blank" rel="noopener" class="links">(11) 98637-7706</a></p></div>
            <div class="form-group"><label><i class="fa-brands fa-instagram"></i> Instagram</label><p><a class="links" href="https://instagram.com.....
                /studio.nay.lopes" target="_blank" rel="noopener">@studio.nay.lopes</a></p></div>
            <div class="form-group"><label><i class="fa-solid fa-clock"></i> Horário</label><p><?= e($TEXTO_HORARIO) ?></p></div>
        </main>
<?php require 'rodape.php'; ?>
