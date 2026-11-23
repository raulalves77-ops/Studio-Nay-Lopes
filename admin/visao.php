<?php
/* admin/visao.php — visão geral do studio. */

defined('PAINEL_STUDIO') or exit;

$mes        = date('Y-m');
$filtroProf = $meuProfissionalId ? ' AND a.profissional_id = ' . (int) $meuProfissionalId : '';
$resumo     = caixa_resumo($pdo, $mes);

// Usa o horário do PHP (o fuso do MySQL pode ser diferente do fuso do site).
$agora = date('Y-m-d H:i:s');

// Atendimentos de hoje (ainda ativos)
$st = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos a
      WHERE DATE(a.data_hora) = ?
        AND a.status IN ('Pendente', 'Confirmado')" . $filtroProf
);
$st->execute([date('Y-m-d')]);
$hoje = (int) $st->fetchColumn();

// Concluídos no mês
$st = $pdo->prepare(
    "SELECT COUNT(*) AS qtd, COALESCE(SUM(a.valor), 0) AS total
       FROM agendamentos a
      WHERE a.status = 'Concluido' AND DATE_FORMAT(a.data_hora, '%Y-%m') = ?" . $filtroProf
);
$st->execute([$mes]);
$concluidos = $st->fetch();
$ticketMedio = (int) $concluidos['qtd'] > 0
    ? (float) $concluidos['total'] / (int) $concluidos['qtd']
    : 0.0;

// Agendamentos futuros ainda em aberto
$st = $pdo->prepare(
    "SELECT COUNT(*) FROM agendamentos a
      WHERE a.data_hora >= ?
        AND a.status IN ('Pendente', 'Confirmado')" . $filtroProf
);
$st->execute([$agora]);
$futuros = (int) $st->fetchColumn();

// Próximos atendimentos
$st = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.status, a.valor,
            u.nome AS cliente, s.nome AS servico,
            COALESCE(p.nome, 'A definir') AS profissional
       FROM agendamentos a
       JOIN usuarios u ON u.id = a.usuario_id
       JOIN servicos s ON s.id = a.servico_id
       LEFT JOIN profissionais p ON p.id = a.profissional_id
      WHERE a.data_hora >= ?
        AND a.status IN ('Pendente', 'Confirmado')" . $filtroProf . "
      ORDER BY a.data_hora ASC
      LIMIT 8"
);
$st->execute([$agora]);
$proximos = $st->fetchAll();
?>

<div class="metrics-grid">
    <?php if ($ehAdmin): ?>
        <div class="metric-card">
            <i class="fa-solid fa-arrow-trend-up metric-icon"></i>
            <div><h3>Recebido no mês</h3><p><?= moeda($resumo['entradas']) ?></p></div>
        </div>
        <div class="metric-card">
            <i class="fa-solid fa-arrow-trend-down metric-icon"></i>
            <div><h3>Despesas no mês</h3><p><?= moeda($resumo['saidas']) ?></p></div>
        </div>
        <div class="metric-card">
            <i class="fa-solid fa-scale-balanced metric-icon"></i>
            <div><h3>Saldo do mês</h3><p><?= moeda($resumo['saldo']) ?></p></div>
        </div>
    <?php else: ?>
        <?php
        $st = $pdo->prepare('SELECT comissao FROM profissionais WHERE id = ?');
        $st->execute([$meuProfissionalId]);
        $minhaComissao = (float) $st->fetchColumn();
        $minhaReceita  = (float) $concluidos['total'];
        ?>
        <div class="metric-card">
            <i class="fa-solid fa-sack-dollar metric-icon"></i>
            <div><h3>Receita do mês</h3><p><?= moeda($minhaReceita) ?></p></div>
        </div>
        <div class="metric-card">
            <i class="fa-solid fa-percent metric-icon"></i>
            <div>
                <h3>Comissão prevista</h3>
                <p><?= moeda($minhaReceita * $minhaComissao / 100) ?></p>
                <span class="metric-obs"><?= number_format($minhaComissao, 0, ',', '.') ?>% sobre o mês</span>
            </div>
        </div>
    <?php endif; ?>
    <div class="metric-card">
        <i class="fa-solid fa-calendar-check metric-icon"></i>
        <div><h3>Atendimentos hoje</h3><p><?= $hoje ?></p></div>
    </div>
    <div class="metric-card">
        <i class="fa-solid fa-receipt metric-icon"></i>
        <div><h3>Ticket médio (mês)</h3><p><?= moeda($ticketMedio) ?></p></div>
    </div>
    <div class="metric-card">
        <i class="fa-solid fa-calendar-plus metric-icon"></i>
        <div><h3>Agendamentos futuros</h3><p><?= $futuros ?></p></div>
    </div>
</div>

<div class="db-card" style="margin-top: 24px;">
    <h3><i class="fa-solid fa-hourglass-half"></i> Próximos atendimentos</h3>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Quando</th><th>Cliente</th><th>Serviço</th><th>Profissional</th><th>Valor</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($proximos as $a): ?>
                    <tr>
                        <td><?= data_br($a['data_hora']) ?></td>
                        <td><?= e($a['cliente']) ?></td>
                        <td><?= e($a['servico']) ?></td>
                        <td><?= e($a['profissional']) ?></td>
                        <td><?= moeda($a['valor']) ?></td>
                        <td><span class="<?= classe_status($a['status']) ?>"><?= e(rotulo_status($a['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$proximos): ?><tr><td colspan="6">Nenhum atendimento futuro agendado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($ehAdmin): ?>
    <?php
    // Repasses do mês por profissional (receita concluída × comissão)
    $st = $pdo->prepare(
        "SELECT p.nome, p.comissao, COALESCE(x.qtd, 0) AS qtd, COALESCE(x.total, 0) AS total
           FROM profissionais p
           LEFT JOIN (
                SELECT profissional_id, COUNT(*) AS qtd, SUM(valor) AS total
                  FROM agendamentos
                 WHERE status = 'Concluido' AND DATE_FORMAT(data_hora, '%Y-%m') = ?
                 GROUP BY profissional_id
           ) x ON x.profissional_id = p.id
          ORDER BY p.nome"
    );
    $st->execute([$mes]);
    $repasses = $st->fetchAll();

    // Serviços mais vendidos no mês
    $st = $pdo->prepare(
        "SELECT s.nome, COUNT(*) AS qtd, COALESCE(SUM(a.valor), 0) AS total
           FROM agendamentos a
           JOIN servicos s ON s.id = a.servico_id
          WHERE a.status = 'Concluido' AND DATE_FORMAT(a.data_hora, '%Y-%m') = ?
          GROUP BY s.id, s.nome
          ORDER BY qtd DESC, total DESC
          LIMIT 5"
    );
    $st->execute([$mes]);
    $maisVendidos = $st->fetchAll();
    ?>

    <div class="admin-grid-2" style="margin-top: 24px;">
        <div class="db-card">
            <h3><i class="fa-solid fa-hand-holding-dollar"></i> Repasses do mês</h3>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Profissional</th><th>Atend.</th><th>Receita</th><th>Comissão</th><th>Repasse</th></tr></thead>
                    <tbody>
                        <?php foreach ($repasses as $r): ?>
                            <tr>
                                <td><?= e($r['nome']) ?></td>
                                <td><?= (int) $r['qtd'] ?></td>
                                <td><?= moeda($r['total']) ?></td>
                                <td><?= number_format((float) $r['comissao'], 0, ',', '.') ?>%</td>
                                <td><?= moeda((float) $r['total'] * (float) $r['comissao'] / 100) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$repasses): ?><tr><td colspan="5">Nenhuma profissional cadastrada.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="db-card">
            <h3><i class="fa-solid fa-ranking-star"></i> Serviços mais vendidos</h3>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Serviço</th><th>Atend.</th><th>Receita</th></tr></thead>
                    <tbody>
                        <?php foreach ($maisVendidos as $s): ?>
                            <tr>
                                <td><?= e($s['nome']) ?></td>
                                <td><?= (int) $s['qtd'] ?></td>
                                <td><?= moeda($s['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$maisVendidos): ?><tr><td colspan="3">Nenhum atendimento concluído neste mês.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>
