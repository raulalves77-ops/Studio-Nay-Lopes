<?php
/* admin/servicos.php — cadastro e edição dos serviços. */

defined('PAINEL_STUDIO') or exit;

$st = $pdo->prepare(
    "SELECT s.id, s.nome, s.valor, s.duracao_minutos, s.descricao, s.ativo,
            (SELECT COUNT(*) FROM agendamentos a
              WHERE a.servico_id = s.id
                AND a.status = 'Concluido'
                AND DATE_FORMAT(a.data_hora, '%Y-%m') = ?) AS qtd_mes
       FROM servicos s
      ORDER BY s.ativo DESC, s.nome"
);
$st->execute([date('Y-m')]);
$servicos = $st->fetchAll();
?>

<div class="db-card">
    <h3><i class="fa-solid fa-plus"></i> Novo serviço</h3>
    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="servico_novo">
        <div class="admin-form-row">
            <div class="form-group">
                <label for="novo-nome">Nome</label>
                <input type="text" id="novo-nome" name="nome" maxlength="120" placeholder="Ex: Hidratação" required>
            </div>
            <div class="form-group">
                <label for="novo-valor">Valor (R$)</label>
                <input type="text" id="novo-valor" name="valor" inputmode="decimal" placeholder="0,00" required>
            </div>
            <div class="form-group">
                <label for="nova-duracao">Duração (min)</label>
                <input type="number" id="nova-duracao" name="duracao" value="60" min="15" max="480" step="15" required>
            </div>
            <div class="form-group">
                <label for="nova-descricao">Descrição (opcional)</label>
                <input type="text" id="nova-descricao" name="descricao" maxlength="255">
            </div>
            <button type="submit" class="btn-primary">CADASTRAR</button>
        </div>
    </form>
</div>

<div class="db-card" style="margin-top: 24px;">
    <h3><i class="fa-solid fa-scissors"></i> Serviços cadastrados</h3>
    <p class="form-hint">Desative um serviço para escondê-lo do site sem apagar o histórico.</p>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nome</th><th>Valor</th><th>Duração</th><th>Descrição</th>
                    <th>Situação</th><th>No mês</th><th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicos as $s): ?>
                    <?php $formId = 'form-servico-' . (int) $s['id']; ?>
                    <tr>
                        <td><input form="<?= e($formId) ?>" type="text" name="nome" value="<?= e($s['nome']) ?>"
                                   maxlength="120" required></td>
                        <td><input form="<?= e($formId) ?>" type="text" name="valor" inputmode="decimal"
                                   value="<?= e(number_format((float) $s['valor'], 2, ',', '')) ?>" required></td>
                        <td><input form="<?= e($formId) ?>" type="number" name="duracao"
                                   value="<?= (int) $s['duracao_minutos'] ?>" min="15" max="480" step="15" required></td>
                        <td><input form="<?= e($formId) ?>" type="text" name="descricao"
                                   value="<?= e($s['descricao']) ?>" maxlength="255"></td>
                        <td>
                            <select form="<?= e($formId) ?>" name="ativo">
                                <option value="1"<?= (int) $s['ativo'] === 1 ? ' selected' : '' ?>>Ativo</option>
                                <option value="0"<?= (int) $s['ativo'] === 0 ? ' selected' : '' ?>>Inativo</option>
                            </select>
                        </td>
                        <td><?= (int) $s['qtd_mes'] ?></td>
                        <td>
                            <form id="<?= e($formId) ?>" method="post" class="inline-form">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="acao" value="servico_editar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            </form>
                            <button type="submit" form="<?= e($formId) ?>" class="btn-acao btn-concluir">Salvar</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$servicos): ?><tr><td colspan="7">Nenhum serviço cadastrado.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
