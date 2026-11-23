<?php
/* admin/agenda.php — agenda do dia, encaixes e bloqueios. */

defined('PAINEL_STUDIO') or exit;

$data = (string) ($_GET['data'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !strtotime($data)) {
    $data = date('Y-m-d');
}

$statusValidos = ['Pendente', 'Confirmado', 'Concluido', 'Cancelado', 'Faltou'];
$statusFiltro  = (string) ($_GET['status'] ?? '');
if (!in_array($statusFiltro, $statusValidos, true)) {
    $statusFiltro = '';
}

// Profissional só enxerga a própria coluna da agenda.
$profFiltro = $meuProfissionalId ?: (int) ($_GET['profissional'] ?? 0);

$sql = "SELECT a.id, a.profissional_id, a.data_hora, a.status, a.valor, a.pago,
               a.forma_pagamento, a.observacoes,
               u.nome AS cliente, u.telefone,
               s.nome AS servico, COALESCE(s.duracao_minutos, 60) AS duracao,
               COALESCE(p.nome, '—') AS profissional
          FROM agendamentos a
          JOIN usuarios u ON u.id = a.usuario_id
          JOIN servicos s ON s.id = a.servico_id
          LEFT JOIN profissionais p ON p.id = a.profissional_id
         WHERE a.data_hora BETWEEN ? AND ?";
$parametros = [$data . ' 00:00:00', $data . ' 23:59:59'];

if ($statusFiltro !== '') {
    $sql .= ' AND a.status = ?';
    $parametros[] = $statusFiltro;
}
if ($profFiltro) {
    $sql .= ' AND a.profissional_id = ?';
    $parametros[] = $profFiltro;
}
$sql .= ' ORDER BY a.data_hora ASC';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$agendamentos = $st->fetchAll();

// Totais do dia
$totalDia    = 0.0;
$previstoDia = 0.0;
$concluidosDia = 0;
foreach ($agendamentos as $a) {
    if (!in_array($a['status'], ['Cancelado', 'Faltou'], true)) {
        $previstoDia += (float) $a['valor'];
    }
    if ($a['status'] === 'Concluido') {
        $totalDia += (float) $a['valor'];
        $concluidosDia++;
    }
}

// Bloqueios que passam por este dia
$st = $pdo->prepare(
    "SELECT b.id, b.inicio, b.fim, b.motivo, COALESCE(p.nome, 'Todo o studio') AS profissional
       FROM bloqueios b
       LEFT JOIN profissionais p ON p.id = b.profissional_id
      WHERE b.inicio <= ? AND b.fim >= ?
      ORDER BY b.inicio"
);
$st->execute([$data . ' 23:59:59', $data . ' 00:00:00']);
$bloqueios = $st->fetchAll();

$clientes      = $pdo->query("SELECT id, nome FROM usuarios WHERE tipo = 'cliente' AND ativo = 1 ORDER BY nome")->fetchAll();
$servicos      = $pdo->query('SELECT id, nome, valor, duracao_minutos FROM servicos WHERE ativo = 1 ORDER BY nome')->fetchAll();
$profissionais = agenda_listar_profissionais($pdo);

$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$rotuloDia  = $diasSemana[(int) date('w', strtotime($data))] . ', ' . data_br_dia($data);
$anterior   = date('Y-m-d', strtotime($data . ' -1 day'));
$proximo    = date('Y-m-d', strtotime($data . ' +1 day'));

/** Monta um link da agenda preservando os filtros atuais. */
function agenda_link_painel($data, $status, $prof)
{
    $query = ['secao' => 'agenda', 'data' => $data];
    if ($status !== '') {
        $query['status'] = $status;
    }
    if ($prof) {
        $query['profissional'] = $prof;
    }
    return 'admin.php?' . http_build_query($query);
}
?>

<div class="agenda-barra">
    <div class="agenda-dia">
        <a class="btn-icone" href="<?= e(agenda_link_painel($anterior, $statusFiltro, $profFiltro)) ?>"
           aria-label="Dia anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <div>
            <strong><?= e($rotuloDia) ?></strong>
            <a href="<?= e(agenda_link_painel(date('Y-m-d'), $statusFiltro, $profFiltro)) ?>" class="link-min">Hoje</a>
        </div>
        <a class="btn-icone" href="<?= e(agenda_link_painel($proximo, $statusFiltro, $profFiltro)) ?>"
           aria-label="Próximo dia"><i class="fa-solid fa-chevron-right"></i></a>
    </div>

    <div class="agenda-totais">
        <span><i class="fa-solid fa-list-check"></i> <?= count($agendamentos) ?> agendados</span>
        <span><i class="fa-solid fa-circle-check"></i> <?= $concluidosDia ?> concluídos</span>
        <span><i class="fa-solid fa-coins"></i> <?= moeda($previstoDia) ?> previstos</span>
        <span><i class="fa-solid fa-sack-dollar"></i> <?= moeda($totalDia) ?> recebidos</span>
    </div>
</div>

<form method="get" action="admin.php" class="filtros-barra">
    <input type="hidden" name="secao" value="agenda">
    <input type="hidden" name="data" value="<?= e($data) ?>">
    <div class="form-group">
        <label for="filtro-status">Status</label>
        <select id="filtro-status" name="status" onchange="this.form.submit()">
            <option value="">Todos</option>
            <?php foreach ($statusValidos as $s): ?>
                <option value="<?= e($s) ?>"<?= $statusFiltro === $s ? ' selected' : '' ?>><?= e(rotulo_status($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($ehAdmin): ?>
        <div class="form-group">
            <label for="filtro-prof">Profissional</label>
            <select id="filtro-prof" name="profissional" onchange="this.form.submit()">
                <option value="0">Todas</option>
                <?php foreach ($profissionais as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"<?= $profFiltro === (int) $p['id'] ? ' selected' : '' ?>>
                        <?= e($p['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
</form>

<div class="db-card">
    <h3><i class="fa-solid fa-calendar-day"></i> Atendimentos do dia</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr><th>Hora</th><th>Cliente</th><th>Serviço</th><th>Profissional</th><th>Valor</th><th>Status</th><th>Ações</th></tr>
            </thead>
            <tbody>
                <?php foreach ($agendamentos as $a): ?>
                    <?php
                    $digitos = preg_replace('/\D+/', '', (string) $a['telefone']);
                    $podeAlterar = $ehAdmin || (int) $a['profissional_id'] === (int) $meuProfissionalId;
                    ?>
                    <tr>
                        <td class="celula-hora"><?= hora_br($a['data_hora']) ?></td>
                        <td>
                            <?= e($a['cliente']) ?>
                            <?php if (strlen($digitos) >= 10): ?>
                                <a class="link-whats" target="_blank" rel="noopener"
                                   href="https://wa.me/55<?= e($digitos) ?>"
                                   title="Falar no WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                            <?php endif; ?>
                            <?php if (!empty($a['observacoes'])): ?>
                                <span class="obs" title="<?= e($a['observacoes']) ?>"><i class="fa-regular fa-note-sticky"></i></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($a['servico']) ?><span class="texto-min"><?= duracao_texto($a['duracao']) ?></span></td>
                        <td><?= e($a['profissional']) ?></td>
                        <td><?= moeda($a['valor']) ?></td>
                        <td>
                            <span class="<?= classe_status($a['status']) ?>"><?= e(rotulo_status($a['status'])) ?></span>
                            <?php if ((int) $a['pago'] === 1 && !empty($a['forma_pagamento'])): ?>
                                <span class="texto-min"><?= e($a['forma_pagamento']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($podeAlterar): ?>
                                <div class="acoes-linha">
                                    <?php if ($a['status'] === 'Pendente'): ?>
                                        <form method="post" class="inline-form">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Confirmado">
                                            <button type="submit" class="btn-acao btn-confirmar">Confirmar</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($a['status'], ['Pendente', 'Confirmado'], true)): ?>
                                        <form method="post" class="inline-form acao-concluir">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Concluido">
                                            <select name="forma_pagamento" class="select-mini" required aria-label="Forma de pagamento">
                                                <option value="">Pagamento...</option>
                                                <?php foreach (formas_pagamento() as $forma): ?>
                                                    <option value="<?= e($forma) ?>"><?= e($forma) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-acao btn-concluir">Concluir</button>
                                        </form>

                                        <form method="post" class="inline-form" onsubmit="return confirm('Marcar que a cliente não compareceu?')">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Faltou">
                                            <button type="submit" class="btn-acao">Faltou</button>
                                        </form>

                                        <form method="post" class="inline-form" onsubmit="return confirm('Cancelar este agendamento?')">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Cancelado">
                                            <button type="submit" class="btn-acao btn-perigo">Cancelar</button>
                                        </form>
                                    <?php elseif ($a['status'] === 'Concluido'): ?>
                                        <form method="post" class="inline-form"
                                              onsubmit="return confirm('Reabrir o atendimento? A receita sairá do caixa.')">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Confirmado">
                                            <button type="submit" class="btn-acao">Reabrir</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" class="inline-form">
                                            <?= campo_csrf() ?>
                                            <input type="hidden" name="acao" value="agendamento_status">
                                            <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                            <input type="hidden" name="novo_status" value="Pendente">
                                            <button type="submit" class="btn-acao">Reabrir</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$agendamentos): ?><tr><td colspan="7">Nenhum agendamento neste dia.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-grid-2" style="margin-top: 24px;">
    <?php if ($ehAdmin): ?>
        <div class="db-card">
            <h3><i class="fa-solid fa-calendar-plus"></i> Novo encaixe</h3>
            <p class="form-hint">Agende uma cliente que chegou sem reserva ou por telefone.</p>
            <form method="post">
                <?= campo_csrf() ?>
                <input type="hidden" name="acao" value="agendamento_novo">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="encaixe-cliente">Cliente</label>
                        <select id="encaixe-cliente" name="cliente" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"><?= e($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="encaixe-servico">Serviço</label>
                        <select id="encaixe-servico" name="servico" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($servicos as $s): ?>
                                <option value="<?= (int) $s['id'] ?>">
                                    <?= e($s['nome']) ?> — <?= moeda($s['valor']) ?> (<?= duracao_texto(agenda_duracao_servico($s)) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="encaixe-prof">Profissional</label>
                        <select id="encaixe-prof" name="profissional" required>
                            <?php foreach ($profissionais as $p): ?>
                                <option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="encaixe-data">Data e horário</label>
                        <input type="datetime-local" id="encaixe-data" name="data" step="1800"
                               value="<?= e($data) ?>T09:00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="encaixe-obs">Observações (opcional)</label>
                    <input type="text" id="encaixe-obs" name="observacoes" maxlength="255">
                </div>
                <button type="submit" class="btn-primary">AGENDAR ENCAIXE</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="db-card<?= $ehAdmin ? '' : ' db-card--full' ?>">
        <h3><i class="fa-solid fa-ban"></i> Bloqueios de agenda</h3>

        <?php if ($bloqueios): ?>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>Período</th><th>Profissional</th><th>Motivo</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($bloqueios as $b): ?>
                            <tr>
                                <td><?= data_br($b['inicio']) ?><br><span class="texto-min">até <?= data_br($b['fim']) ?></span></td>
                                <td><?= e($b['profissional']) ?></td>
                                <td><?= e($b['motivo'] ?: '—') ?></td>
                                <td>
                                    <form method="post" class="inline-form" onsubmit="return confirm('Remover este bloqueio?')">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="acao" value="bloqueio_excluir">
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn-perigo">Remover</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="form-hint">Nenhum bloqueio neste dia.</p>
        <?php endif; ?>

        <form method="post" style="margin-top: 16px;">
            <?= campo_csrf() ?>
            <input type="hidden" name="acao" value="bloqueio_novo">
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="bloqueio-inicio">Início</label>
                    <input type="datetime-local" id="bloqueio-inicio" name="inicio" step="1800" required>
                </div>
                <div class="form-group">
                    <label for="bloqueio-fim">Fim</label>
                    <input type="datetime-local" id="bloqueio-fim" name="fim" step="1800" required>
                </div>
            </div>
            <?php if ($ehAdmin): ?>
                <div class="form-group">
                    <label for="bloqueio-prof">Profissional</label>
                    <select id="bloqueio-prof" name="profissional">
                        <option value="0">Todo o studio</option>
                        <?php foreach ($profissionais as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="form-group">
                <label for="bloqueio-motivo">Motivo (opcional)</label>
                <input type="text" id="bloqueio-motivo" name="motivo" maxlength="255" placeholder="Ex: feriado, folga, curso">
            </div>
            <button type="submit" class="btn-outline">BLOQUEAR</button>
        </form>
    </div>
</div>
