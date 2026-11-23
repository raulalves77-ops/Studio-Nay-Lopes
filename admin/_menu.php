<?php
/* admin/_menu.php — abas do painel. Recebe $secoesDisponiveis e $secao. */

defined('PAINEL_STUDIO') or exit;
?>
<nav class="admin-tabs" aria-label="Seções do painel">
    <?php foreach ($secoesDisponiveis as $chave => $info): ?>
        <a href="admin.php?secao=<?= e($chave) ?>"
           class="admin-tab<?= $chave === $secao ? ' active' : '' ?>"
           <?= $chave === $secao ? 'aria-current="page"' : '' ?>>
            <i class="fa-solid <?= e($info[1]) ?>"></i> <?= e($info[0]) ?>
        </a>
    <?php endforeach; ?>
</nav>
