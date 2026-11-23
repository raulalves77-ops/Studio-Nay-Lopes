<?php
/* meus-agendamentos.php — agenda da cliente logada.
   Mostra os próximos atendimentos e o histórico, com opção de cancelar. */
require 'conexao.php';
exigir_login('cliente');

$usuario = usuario_logado();

// ---- Cancelamento ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $id = (int) ($_POST['id'] ?? 0);

    // O UPDATE repete as regras no WHERE: se o studio concluiu o atendimento
    // (ou o prazo passou) entre a consulta e a gravação, nada é alterado.
    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "UPDATE agendamentos
                SET status = 'Cancelado', pago = 0, forma_pagamento = NULL
              WHERE id = ? AND usuario_id = ?
                AND status IN ('Pendente', 'Confirmado')
                AND data_hora >= ?"
        );
        $st->execute([
            $id,
            (int) $usuario['id'],
            date('Y-m-d H:i:s', time() + 2 * 3600),
        ]);

        if ($st->rowCount() === 1) {
            caixa_remover_agendamento($pdo, $id);
            $pdo->commit();
            flash('ok', 'Agendamento cancelado. Esperamos você em outra data!');
        } else {
            $pdo->rollBack();
            flash('erro', 'Este agendamento não pode mais ser cancelado. O prazo é de 2 horas e o status não pode ter mudado.');
        }
    } catch (PDOException $excecao) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao cancelar agendamento: ' . $excecao->getMessage());
        flash('erro', 'Não foi possível cancelar agora. Tente novamente.');
    }

    redirecionar('meus-agendamentos.php');
}

$colunas = 'a.id, a.data_hora, a.valor, a.status, a.pago, a.forma_pagamento,
            s.nome AS servico, COALESCE(p.nome, \'A definir\') AS profissional';
$agora = date('Y-m-d H:i:s');

// Próximos: ainda vão acontecer e não foram cancelados.
$st = $pdo->prepare(
    "SELECT $colunas
       FROM agendamentos a
       JOIN servicos s ON s.id = a.servico_id
       LEFT JOIN profissionais p ON p.id = a.profissional_id
      WHERE a.usuario_id = ?
        AND a.data_hora >= ?
        AND a.status IN ('Pendente', 'Confirmado')
      ORDER BY a.data_hora ASC"
);
$st->execute([(int) $usuario['id'], $agora]);
$proximos = $st->fetchAll();

// Histórico: atendimentos passados ou encerrados.
$st = $pdo->prepare(
    "SELECT $colunas
       FROM agendamentos a
       JOIN servicos s ON s.id = a.servico_id
       LEFT JOIN profissionais p ON p.id = a.profissional_id
      WHERE a.usuario_id = ?
        AND (a.data_hora < ? OR a.status IN ('Concluido', 'Cancelado', 'Faltou'))
      ORDER BY a.data_hora DESC
      LIMIT 40"
);
$st->execute([(int) $usuario['id'], $agora]);
$historico = $st->fetchAll();

$titulo = 'Meus Agendamentos';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-calendar-day"></i> Meus Agendamentos</h1>
            <?php exibir_flash(); ?>

            <div class="db-card">
                <h3><i class="fa-solid fa-hourglass-half"></i> Próximos atendimentos</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Serviço</th><th>Profissional</th><th>Data/Horário</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($proximos as $a): ?>
                                <tr>
                                    <td><?= e($a['servico']) ?></td>
                                    <td><?= e($a['profissional']) ?></td>
                                    <td><?= data_br($a['data_hora']) ?></td>
                                    <td><?= moeda($a['valor']) ?></td>
                                    <td><span class="<?= classe_status($a['status']) ?>"><?= e(rotulo_status($a['status'])) ?></span></td>
                                    <td>
                                        <?php if (strtotime($a['data_hora']) - time() >= 2 * 3600): ?>
                                            <form method="post" class="inline-form" onsubmit="return confirm('Cancelar este agendamento?')">
                                                <?= campo_csrf() ?>
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button type="submit" class="btn-cancelar">Cancelar</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="texto-min">Fale com o studio</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$proximos): ?><tr><td colspan="6">Nenhum atendimento agendado. <a class="links" href="agendar.php">Agendar agora</a>.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Histórico</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>#</th><th>Serviço</th><th>Profissional</th><th>Data/Horário</th><th>Valor</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($historico as $a): ?>
                                <tr>
                                    <td>#<?= (int) $a['id'] ?></td>
                                    <td><?= e($a['servico']) ?></td>
                                    <td><?= e($a['profissional']) ?></td>
                                    <td><?= data_br($a['data_hora']) ?></td>
                                    <td><?= moeda($a['valor']) ?></td>
                                    <td>
                                        <span class="<?= classe_status($a['status']) ?>"><?= e(rotulo_status($a['status'])) ?></span>
                                        <?php if ((int) $a['pago'] === 1): ?>
                                            <span class="badge-pago">Pago</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$historico): ?><tr><td colspan="6">Nenhum atendimento no histórico ainda.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="form-hint">Cancelamento com no mínimo 2 horas de antecedência. O pagamento é feito no studio.</p>
        </main>
<?php require 'rodape.php'; ?>
