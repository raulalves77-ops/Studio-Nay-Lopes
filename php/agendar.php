<?php
/* agendar.php — a cliente escolhe serviço, profissional e horário.
   O preço vem do banco; a disponibilidade é conferida com as mesmas
   regras usadas pelo painel (lib/agenda.php).

   A tela tem duas etapas numa página só:
   1. serviço, profissional e dia (formulário GET, que só recarrega a lista);
   2. escolha do horário livre (formulário POST, que agenda de verdade). */
require 'conexao.php';
exigir_login('cliente');

$usuario       = usuario_logado();
$erro          = '';
$servicos      = $pdo->query('SELECT id, nome, valor, duracao_minutos FROM servicos WHERE ativo = 1 ORDER BY nome')->fetchAll();
$profissionais = agenda_listar_profissionais($pdo);

$dataEscolhida  = trim((string) ($_POST['data'] ?? $_GET['data'] ?? ''));
$horaEscolhida  = trim((string) ($_POST['horario'] ?? ''));
$servicoId      = (int) ($_POST['servico'] ?? $_GET['servico'] ?? 0);
$profissionalId = (int) ($_POST['profissional'] ?? $_GET['profissional'] ?? 0);   // 0 = qualquer

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $st = $pdo->prepare('SELECT id, nome, valor, duracao_minutos FROM servicos WHERE id = ? AND ativo = 1');
    $st->execute([$servicoId]);
    $servico = $st->fetch();

    $duracao = $servico ? agenda_duracao_servico($servico) : 60;

    // Data e hora chegam separadas: "2026-12-05" + "14:30".
    $quando = null;
    $dataValida = DateTime::createFromFormat('!Y-m-d', $dataEscolhida);
    if ($dataValida && $dataValida->format('Y-m-d') === $dataEscolhida
        && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horaEscolhida)) {
        $quando = strtotime($dataEscolhida . ' ' . $horaEscolhida . ':00');
    }

    $erroHorario = agenda_validar_horario($quando, $duracao);

    if (!$servico) {
        $erro = 'Escolha um serviço disponível.';
    } elseif ($erroHorario) {
        $erro = $erroHorario;
    } else {
        $dataHora = date('Y-m-d H:i:00', $quando);

        if ($profissionalId) {
            $st = $pdo->prepare('SELECT id, nome FROM profissionais WHERE id = ? AND ativo = 1');
            $st->execute([$profissionalId]);
            $profissional = $st->fetch();

            if (!$profissional) {
                $erro = 'Profissional indisponível. Escolha outra opção.';
            } elseif (agenda_bloqueado($pdo, $profissionalId, $quando, $quando + $duracao * 60)) {
                $erro = 'A agenda está bloqueada nesse horário. Escolha outro dia ou profissional.';
            } elseif (agenda_conflito($pdo, $profissionalId, $quando, $duracao)) {
                $erro = 'Este horário já está reservado com ' . $profissional['nome'] . '. Escolha outro.';
            }
        } else {
            // "Qualquer profissional": escolhe automaticamente quem estiver livre.
            $livres = agenda_profissionais_livres($pdo, $quando, $duracao);
            if (!$livres) {
                $erro = 'Nenhuma profissional está livre nesse horário. Escolha outro.';
            } else {
                $profissionalId = (int) $livres[0]['id'];
            }
        }

        // Limite de reservas em aberto por cliente: evita que uma conta
        // ocupe todos os horários do studio.
        if ($erro === '') {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM agendamentos
                  WHERE usuario_id = ? AND status IN ('Pendente', 'Confirmado') AND data_hora >= ?"
            );
            $st->execute([(int) $usuario['id'], date('Y-m-d H:i:s')]);
            if ((int) $st->fetchColumn() >= 3) {
                $erro = 'Você já tem 3 agendamentos em aberto. Cancele um deles para marcar outro.';
            }
        }

        if ($erro === '') {
            try {
                $pdo->beginTransaction();

                // Trava a linha da profissional e confere de novo dentro da
                // transação: duas clientes ao mesmo tempo não reservam o
                // mesmo horário.
                $pdo->prepare('SELECT id FROM profissionais WHERE id = ? FOR UPDATE')
                    ->execute([$profissionalId]);
                if (agenda_conflito($pdo, $profissionalId, $quando, $duracao)) {
                    $pdo->rollBack();
                    $erro = 'Este horário acabou de ser reservado. Escolha outro.';
                } else {
                    $st = $pdo->prepare(
                        'INSERT INTO agendamentos
                            (usuario_id, servico_id, profissional_id, data_hora, valor, status)
                         VALUES (?, ?, ?, ?, ?, \'Pendente\')'
                    );
                    $st->execute([
                        (int) $usuario['id'],
                        (int) $servico['id'],
                        $profissionalId,
                        $dataHora,
                        $servico['valor'],
                    ]);
                    $pdo->commit();

                    flash('ok', 'Agendamento solicitado para ' . date('d/m/Y \à\s H:i', $quando)
                        . ' — ' . $servico['nome'] . ' (' . moeda($servico['valor'])
                        . '). Aguarde a confirmação do studio. O pagamento é feito no local.');
                    redirecionar('meus-agendamentos.php');
                }
            } catch (PDOException $excecao) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro ao agendar: ' . $excecao->getMessage());
                $erro = 'Não foi possível concluir o agendamento agora. Tente novamente.';
            }
        }
    }
}

// ---- Dados para desenhar a tela ----------------------------------------
// Funciona no primeiro acesso (GET) e também quando o POST volta com erro.
$dataSelecionada = '';
if ($dataEscolhida !== '') {
    $testeData = DateTime::createFromFormat('!Y-m-d', $dataEscolhida);
    if ($testeData && $testeData->format('Y-m-d') === $dataEscolhida) {
        $dataSelecionada = $dataEscolhida;
    }
}

$servicoSelecionado = null;
foreach ($servicos as $s) {
    if ((int) $s['id'] === $servicoId) {
        $servicoSelecionado = $s;
        break;
    }
}

$profissionalSelecionado = 0;
foreach ($profissionais as $p) {
    if ((int) $p['id'] === $profissionalId) {
        $profissionalSelecionado = (int) $p['id'];
        break;
    }
}

$slots = ($servicoSelecionado && $dataSelecionada !== '')
    ? agenda_horarios_disponiveis(
        $pdo,
        $dataSelecionada,
        agenda_duracao_servico($servicoSelecionado),
        $profissionalSelecionado ?: null
    )
    : [];

$diaAberto = $dataSelecionada !== ''
    && in_array((int) date('w', strtotime($dataSelecionada)), $DIAS_ATENDIMENTO, true);

$titulo = 'Agendar';
require 'topo.php';
?>
<head>
    <link rel="stylesheet" href="style.css">
</head>
        <main class="agendar-container">
            <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

            <form class="form-card" method="get" action="agendar.php">
                <h2><i class="fa-solid fa-calendar-check"></i> Novo Agendamento</h2>
                <p class="form-hint">Atendimento: <?= e($TEXTO_HORARIO) ?>, de <?= (int) $INTERVALO_MINUTOS ?> em <?= (int) $INTERVALO_MINUTOS ?> minutos.</p>

                <div class="form-group">
                    <label for="servico">Serviço</label>
                    <select id="servico" name="servico" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"<?= (int) $s['id'] === $servicoId ? ' selected' : '' ?>>
                                <?= e($s['nome']) ?> — <?= moeda($s['valor']) ?>
                                (<?= duracao_texto(agenda_duracao_servico($s)) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="profissional">Profissional (opcional)</label>
                    <select id="profissional" name="profissional">
                        <option value="">Qualquer profissional disponível</option>
                        <?php foreach ($profissionais as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === $profissionalSelecionado ? ' selected' : '' ?>><?= e($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="data">Dia</label>
                    <input type="date" id="data" name="data"
                           min="<?= date('Y-m-d') ?>"
                           max="<?= date('Y-m-d', strtotime('+120 days')) ?>"
                           value="<?= e($dataSelecionada) ?>" required>
                </div>

                <button type="submit" class="btn-primary">VER HORÁRIOS LIVRES</button>
            </form>

            <?php if ($dataSelecionada !== '' && $servicoSelecionado): ?>
                <form class="form-card" method="post" action="agendar.php">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="servico" value="<?= (int) $servicoSelecionado['id'] ?>">
                    <input type="hidden" name="profissional" value="<?= (int) $profissionalSelecionado ?>">
                    <input type="hidden" name="data" value="<?= e($dataSelecionada) ?>">

                    <h2><i class="fa-regular fa-clock"></i> Horários de <?= e(data_br_dia($dataSelecionada)) ?></h2>
                    <p class="form-hint">
                        <?= e($servicoSelecionado['nome']) ?> ·
                        <?= moeda($servicoSelecionado['valor']) ?> ·
                        <?= duracao_texto(agenda_duracao_servico($servicoSelecionado)) ?>
                    </p>

                    <?php if (!$diaAberto): ?>
                        <div class="alerta alerta-erro">O studio não atende nesse dia. <?= e($TEXTO_HORARIO) ?>.</div>
                    <?php elseif (!$slots): ?>
                        <div class="alerta alerta-erro">Nenhum horário livre nesse dia. Tente outra data ou profissional.</div>
                    <?php else: ?>
                        <div class="slots-grid" role="radiogroup" aria-label="Horários livres">
                            <?php foreach ($slots as $slot): ?>
                                <label class="slot">
                                    <input type="radio" name="horario" value="<?= e($slot['hora']) ?>"
                                        <?= $slot['hora'] === $horaEscolhida ? 'checked' : '' ?> required>
                                    <span><?= e($slot['hora']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn-primary">CONFIRMAR AGENDAMENTO</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </main>
<?php require 'rodape.php'; ?>
