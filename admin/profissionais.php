<?php
/* admin/profissionais.php — equipe, acessos e comissões. */

defined('PAINEL_STUDIO') or exit;

$st = $pdo->prepare(
    "SELECT p.id, p.usuario_id, p.nome, p.especialidade, p.telefone, p.comissao, p.ativo,
            u.email,
            COALESCE(x.qtd, 0) AS qtd_mes,
            COALESCE(x.total, 0) AS receita_mes
       FROM profissionais p
       LEFT JOIN usuarios u ON u.id = p.usuario_id
       LEFT JOIN (
            SELECT profissional_id, COUNT(*) AS qtd, SUM(valor) AS total
              FROM agendamentos
             WHERE status = 'Concluido' AND DATE_FORMAT(data_hora, '%Y-%m') = ?
             GROUP BY profissional_id
       ) x ON x.profissional_id = p.id
      ORDER BY p.ativo DESC, p.nome"
);
$st->execute([date('Y-m')]);
$profissionais = $st->fetchAll();
?>

<div class="db-card">
    <h3><i class="fa-solid fa-user-plus"></i> Nova profissional</h3>
    <p class="form-hint">
        A conta criada aqui dá acesso ao painel, mostrando apenas a agenda da própria profissional.
    </p>
    <form method="post">
        <?= campo_csrf() ?>
        <input type="hidden" name="acao" value="profissional_novo">
        <div class="form-grid-2">
            <div class="form-group">
                <label for="prof-nome">Nome</label>
                <input type="text" id="prof-nome" name="nome" maxlength="120" required>
            </div>
            <div class="form-group">
                <label for="prof-especialidade">Especialidade</label>
                <input type="text" id="prof-especialidade" name="especialidade" maxlength="255"
                       placeholder="Ex: Cabeleireira, designer de cílios">
            </div>
            <div class="form-group">
                <label for="prof-email">E-mail (login)</label>
                <input type="email" id="prof-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="prof-telefone">Telefone / WhatsApp</label>
                <input type="tel" id="prof-telefone" name="telefone" placeholder="(11) 99999-9999">
            </div>
            <div class="form-group">
                <label for="prof-comissao">Comissão (%)</label>
                <input type="text" id="prof-comissao" name="comissao" inputmode="decimal" value="0" placeholder="0">
            </div>
            <div class="form-group">
                <label for="prof-senha">Senha inicial (mín. 8 caracteres)</label>
                <input type="password" id="prof-senha" name="senha" minlength="8" autocomplete="new-password" required>
            </div>
        </div>
        <button type="submit" class="btn-primary">CADASTRAR PROFISSIONAL</button>
    </form>
</div>

<div class="prof-grid" style="margin-top: 24px;">
    <?php foreach ($profissionais as $p): ?>
        <?php $formId = 'form-prof-' . (int) $p['id']; ?>
        <div class="db-card prof-card<?= (int) $p['ativo'] === 0 ? ' prof-card--inativo' : '' ?>">
            <div class="prof-card-topo">
                <div>
                    <h3><?= e($p['nome']) ?></h3>
                    <p class="texto-min">
                        <?= e($p['email'] ?: 'Sem conta de acesso') ?>
                    </p>
                </div>
                <span class="<?= (int) $p['ativo'] === 1 ? 'badge-pago' : 'badge-cancelado' ?>">
                    <?= (int) $p['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                </span>
            </div>

            <div class="prof-stats">
                <span><strong><?= (int) $p['qtd_mes'] ?></strong> atend. no mês</span>
                <span><strong><?= moeda($p['receita_mes']) ?></strong> receita</span>
                <span><strong><?= moeda((float) $p['receita_mes'] * (float) $p['comissao'] / 100) ?></strong> repasse</span>
            </div>

            <form id="<?= e($formId) ?>" method="post">
                <?= campo_csrf() ?>
                <input type="hidden" name="acao" value="profissional_editar">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Nome</label>
                        <input type="text" name="nome" value="<?= e($p['nome']) ?>" maxlength="120" required>
                    </div>
                    <div class="form-group">
                        <label>Especialidade</label>
                        <input type="text" name="especialidade" value="<?= e($p['especialidade']) ?>" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="telefone" value="<?= e($p['telefone']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Comissão (%)</label>
                        <input type="text" name="comissao" inputmode="decimal"
                               value="<?= e(number_format((float) $p['comissao'], 2, ',', '')) ?>">
                    </div>
                    <div class="form-group">
                        <label>Situação</label>
                        <select name="ativo">
                            <option value="1"<?= (int) $p['ativo'] === 1 ? ' selected' : '' ?>>Ativa</option>
                            <option value="0"<?= (int) $p['ativo'] === 0 ? ' selected' : '' ?>>Inativa</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nova senha (opcional)</label>
                        <input type="password" name="nova_senha" minlength="8"
                               placeholder="Deixe em branco para manter" autocomplete="new-password">
                    </div>
                </div>
                <button type="submit" class="btn-outline">SALVAR ALTERAÇÕES</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$profissionais): ?><p class="form-hint">Nenhuma profissional cadastrada.</p><?php endif; ?>
</div>
