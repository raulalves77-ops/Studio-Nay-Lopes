<?php
// Galeria: a lista de fotos está neste array. Para incluir uma foto nova,
// coloque o arquivo na pasta imagens/ e acrescente uma linha aqui.
require 'conexao.php';

$fotos = [
    'loiro perolado.png'      => 'Loiro perolado',
    'moreno iluminado.png'    => 'Moreno iluminado',
    'hidratação.png'          => 'Hidratação',
    'extensão de cílios.png'  => 'Extensão de cílios',
    'escova e hidratação.png' => 'Escova e hidratação',
    'agenda aberta.png'       => 'Agenda aberta',
    '4 dicas.png'             => 'Dicas de cuidados',
];

$titulo = 'Galeria';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-images"></i> Galeria</h1>
            <div class="gallery-grid">
                <?php foreach ($fotos as $arquivo => $legenda): ?>
                    <button type="button" class="gallery-item" data-caption="<?= e($legenda) ?>">
                        <img src="../imagens/<?= e($arquivo) ?>" alt="<?= e($legenda) ?>" loading="lazy">
                        <span class="gallery-zoom"><i class="fa-solid fa-magnifying-glass-plus"></i> Ampliar</span>
                    </button>
                <?php endforeach; ?>

                <button type="button" class="gallery-item gallery-video" data-video-src="../imagens/promoção.mp4" data-caption="Promoção do Studio Nay Lopes">
                    <video src="../imagens/promoção.mp4" muted preload="metadata" playsinline></video>
                    <span class="gallery-zoom"><i class="fa-solid fa-circle-play"></i> Assistir vídeo</span>
                </button>
            </div>
        </main>

        <!-- Janela de ampliação (controlada pelo ui.js) -->
        <div class="media-lightbox" id="media-lightbox" aria-hidden="true">
            <button type="button" class="media-lightbox-close" aria-label="Fechar">&times;</button>
            <div class="media-lightbox-content" id="media-lightbox-content"></div>
            <p class="media-lightbox-caption" id="media-lightbox-caption"></p>
        </div>
<?php require 'rodape.php'; ?>
