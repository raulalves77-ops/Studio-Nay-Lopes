<?php
// Lista as profissionais ativas cadastradas no painel.
require 'conexao.php';

$profissionais = $pdo->query(
    'SELECT nome, especialidade FROM profissionais WHERE ativo = 1 ORDER BY nome'
)->fetchAll();

$titulo = 'Profissionais';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-user-tie"></i> Nossa Equipe</h1>
            <div class="services-grid">
                <?php foreach ($profissionais as $p): ?>
                    <div class="service-card">
                        <i class="fa-solid fa-user-tie"></i>
                        <h3><?= e($p['nome']) ?></h3>
                        <p><?= e($p['especialidade']) ?></p>
                    </div>
                <?php endforeach; ?>
                <?php if (!$profissionais): ?><p class="form-hint">Nenhuma profissional cadastrada no momento.</p><?php endif; ?>
            </div>
        </main>
<?php require 'rodape.php'; ?>
