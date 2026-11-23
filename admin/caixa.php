<?php
/* admin/caixa.php — fluxo de caixa do studio. */

defined('PAINEL_STUDIO') or exit;

// Mês exibido (formato YYYY-MM)
$mes = (string) ($_GET['mes'] ?? date('Y-m'));
if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $partes)
    || !checkdate((int) $partes[2], 1, (int) $partes[1])) {
    $mes = date('Y-m');
}

$resumo = caixa_resumo($pdo, $mes);

$st = $pdo->prepare(
    "SELECT id, tipo, categoria, descricao, valor, data, agendamento_id
       FROM movimentacoes
      WHERE DATE_FORMAT(data, '%Y-%m') = ?
      ORDER BY data DESC, id DESC"
);
$st->execute([$mes]);
$movimentacoes = $st->fetchAll();

// Receita que entrou automaticamente pela agenda
$st = $pdo->prepare(
    "SELECT COALESCE(SUM(valor), 0)
       FROM movimentacoes
      WHERE tipo = 'entrada' AND categoria = 'Serviços'
        AND DATE_FORMAT(data, '%Y-%m') = ?"
);
$st->execute([$mes]);
$receitaServicos = (float) $st->fetchColumn();

$categorias = ['Serviços', 'Produtos', 'Aluguel', 'Materiais', 'Marketing', 'Impostos', 'Comissão', 'Outros'];
$mesAnterior = date('Y-m', strtotime($mes . '-01 -1 month'));
$mesSeguinte = date('Y-m', strtotime($mes . '-01 +1 month'));
$ehMesAtual  = $mes === date('Y-m');
?>

<div class="metrics-grid">
    <div class="metric-card">
        <i class="fa-solid fa-arrow-trend-up metric-icon"></i>
        <div><h3>Entradas</h3><p><?= moeda($resumo['entradas']) ?></p></div>
    </div>
    <div class="metric-card">
        <i class="fa-solid fa-arrow-trend-down metric-icon"></i>
        <div><h3>Saídas</h3><p><?= moeda($resumo['saidas']) ?></p></div>
    </div>
    <div class="metric-card">
        <i class="fa-solid fa-scale-balanced metric-icon"></i>
        <div><h3>Saldo do mês</h3><p class="<?= $resumo['saldo'] < 0 ? 'valor-negativo' : 'valor-positivo' ?>"><?= moeda($resumo['saldo']) ?></p></div>
    </div>
    <div class="metric-card">
        <i class="fa-solid fa-scissors metric-icon"></i>
        <div><h3>Receita de serviços</h3><p><?= moeda($receitaServicos) ?></p></div>
    </div>
</div>

<div class="agenda-barra">
    <div class="agenda-dia">
        <a class="btn-icone" href="admin.php?secao=caixa&mes=<?= e($mesAnterior) ?>"
           aria-label="Mês anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <div>
            <strong><?= e(mes_br($mes)) ?></strong>
            <?php if (!$ehMesAtual): ?>
                <a href="admin.php?secao=caixa" class="link-min">Mês atual</a>
            <?php endif; ?>
        </div>
        <a class="btn-icone" href="admin.php?secao=caixa&mes=<?= e($mesSeguinte) ?>"
           aria-label="Próximo mês"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
    <div class="agenda-totais">
        <span><i class="fa-solid fa-list"></i> <?= (int) $resumo['qtd'] ?> lançamentos</span>
    </div>
</div>

<div class="db-card">
    <h3><i class="fa-solid fa-money-bill-transfer"></i> Lançamentos</h3>
    <div class="table-responsive">
        <table>
            <thead><tr><th>Data</th><th>Tipo</th><th>Categoria</th><th>Descrição</th><th>Valor</th><th>Origem</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($movimentacoes as $m): ?>
                    <tr>
                        <td><?= data_br_dia($m['data']) ?></td>
                        <td>
                            <span class="<?= $m['tipo'] === 'entrada' ? 'badge-entrada' : 'badge-saida' ?>">
                                <?= $m['tipo'] === 'entrada' ? 'Entrada' : 'Saída' ?>
                            </span>
                        </td>
                        <td><?= e($m['categoria']) ?></td>
                        <td><?= e($m['descricao']) ?></td>
                        <td class="<?= $m['tipo'] === 'entrada' ? 'valor-positivo' : 'valor-negativo' ?>">
                            <?= $m['tipo'] === 'entrada' ? '+' : '−' ?> <?= moeda($m['valor']) ?>
                        </td>
                        <td class="texto-min">
                            <?php if ($m['agendamento_id'] !== null): ?>
                                Agenda #<?= (int) $m['agendamento_id'] ?>
                            <?php elseif (strpos($m['descricao'], 'Atendimento #') === 0): ?>
                                Histórico
                            <?php else: ?>
                                Manual
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($m['agendamento_id'] === null): ?>
                                <form method="post" class="inline-form" onsubmit="return confirm('Excluir este lançamento?')">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="acao" value="caixa_excluir">
                                    <input type="hidden" name="mes" value="<?= e($mes) ?>">
                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="btn-perigo">Excluir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movimentacoes): ?><tr><td colspan="7">Nenhum lançamento neste mês.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="form-hint">
        Receitas de atendimento entram automaticamente quando o atendimento é concluído na agenda
        e saem do caixa se ele for reaberto ou cancelado.
    </p>
</div>

<div class="db-card" style="margin-top: 24px;">
    <h3><i class="fa-solid fa-plus"></i> Novo lançamento manual</h3>
    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="caixa_nova">
        <input type="hidden" name="mes" value="<?= e($mes) ?>">
        <div class="form-grid-2">
            <div class="form-group">
                <label for="caixa-tipo">Tipo</label>
                <select id="caixa-tipo" name="tipo" required>
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </select>
            </div>
            <div class="form-group">
                <label for="caixa-categoria">Categoria</label>
                <select id="caixa-categoria" name="categoria">
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= e($categoria) ?>"><?= e($categoria) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="caixa-valor">Valor (R$)</label>
                <input type="text" id="caixa-valor" name="valor" inputmode="decimal" placeholder="0,00" required>
            </div>
            <div class="form-group">
                <label for="caixa-data">Data</label>
                <input type="date" id="caixa-data" name="data"
                       value="<?= $mes === date('Y-m') ? date('Y-m-d') : e($mes . '-01') ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="caixa-descricao">Descrição</label>
            <input type="text" id="caixa-descricao" name="descricao" maxlength="255"
                   placeholder="Ex: compra de produtos, aluguel do espaço" required>
        </div>
        <button type="submit" class="btn-primary">REGISTRAR LANÇAMENTO</button>
    </form>
</div>
