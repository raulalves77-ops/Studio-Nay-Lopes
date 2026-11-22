<?php
/* meus-agendamentos.php — lista os agendamentos da cliente logada e permite cancelar. */
require 'conexao.php';
exigir_login('cliente');
$usuario = $_SESSION['usuario'];

$mensagem = '';

// Clicou em "Cancelar"?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);

    // "AND usuario_id = ?" garante que a cliente só cancela o PRÓPRIO agendamento
    $st = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ? AND usuario_id = ?');
    $st->execute([$id, $usuario['id']]);
    $ag = $st->fetch();

    if (!$ag) {
        $mensagem = 'Agendamento não encontrado.';
    } elseif ($ag['status'] === 'Cancelado') {
        $mensagem = 'Este agendamento já está cancelado.';
    } elseif (strtotime($ag['data_hora']) - time() < 2 * 3600) {      // menos de 2 horas
        $mensagem = 'O cancelamento deve ser feito com pelo menos 2 horas de antecedência.';
    } else {
        $pdo->prepare("UPDATE agendamentos SET status = 'Cancelado' WHERE id = ?")->execute([$id]);
        $mensagem = 'Agendamento cancelado.';
    }
}

// Busca só os agendamentos desta cliente (JOIN traz o nome do serviço junto)
$st = $pdo->prepare(
    'SELECT a.id, s.nome AS servico, a.data_hora, a.status
       FROM agendamentos a
       JOIN servicos s ON s.id = a.servico_id
      WHERE a.usuario_id = ?
      ORDER BY a.data_hora DESC'
);
$st->execute([$usuario['id']]);
$lista = $st->fetchAll();

$titulo = 'Meus Agendamentos';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-calendar-day"></i> Meus Agendamentos</h1>
            <?php if ($mensagem): ?><div class="alerta alerta-ok"><?= e($mensagem) ?></div><?php endif; ?>

            <div class="db-card">
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>ID</th><th>Serviço</th><th>Data/Horário</th><th>Status</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($lista as $a): ?>
                                <tr>
                                    <td>#<?= (int) $a['id'] ?></td>
                                    <td><?= e($a['servico']) ?></td>
                                    <td><?= data_br($a['data_hora']) ?></td>
                                    <td><span class="<?= $a['status'] === 'Cancelado' ? 'badge-cancelado' : 'badge-pendente' ?>"><?= e($a['status']) ?></span></td>
                                    <td>
                                        <?php if ($a['status'] !== 'Cancelado'): ?>
                                            <form method="post" onsubmit="return confirm('Cancelar este agendamento?')">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button type="submit" class="btn-cancelar">Cancelar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$lista): ?><tr><td colspan="5">Nenhum agendamento encontrado.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="form-hint">Cancelamento com no mínimo 2 horas de antecedência. O pagamento é feito no studio.</p>
        </main>
<?php require 'rodape.php'; ?>
