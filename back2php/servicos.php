<?php
// Lista os serviços que estão no banco (se o admin cadastrar um novo, ele aparece aqui).
require 'conexao.php';

// Ícone de cada serviço original; serviço novo usa uma tesoura.
$icones = [
    'Luzes' => 'luzes', 'Progressiva' => 'progressiva', 'Escova' => 'escova', 'Corte' => 'corte',
    'Sobrancelha' => 'sobrancelha', 'Cílios' => 'cilios',
    'Manutenção de cílios até 15 dias' => 'manutencao-cilios', 'Remoção de cílios' => 'remocao-cilios',
];

// SELECT simples: não tem dado vindo do usuário, então pode usar query() direto.
$servicos = $pdo->query('SELECT nome, valor, descricao FROM servicos ORDER BY id')->fetchAll();

$titulo = 'Serviços';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-scissors"></i> Nossos Serviços</h1>
            <div class="services-grid">
                <?php foreach ($servicos as $s): ?>
                    <div class="service-card service-card--icon">
                        <div class="service-icon">
                            <?php if (isset($icones[$s['nome']])): ?>
                                <img src="imagens/icones/<?= e($icones[$s['nome']]) ?>.png" alt="<?= e($s['nome']) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-scissors"></i>
                            <?php endif; ?>
                        </div>
                        <h3><?= e($s['nome']) ?></h3>
                        <?php if ($s['descricao']): ?><p><?= e($s['descricao']) ?></p><?php endif; ?>
                        <span class="price"><?= moeda($s['valor']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </main>
<?php require 'rodape.php'; ?>
