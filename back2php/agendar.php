<?php
/* agendar.php — a cliente escolhe serviço, profissional e horário.
   Só entra quem está logada (exigir_login). O nome, e-mail e telefone vêm da conta. */
require 'conexao.php';
exigir_login('cliente');
$usuario = $_SESSION['usuario'];

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servico_id = (int) ($_POST['servico'] ?? 0);
    $prof_id    = (int) ($_POST['profissional'] ?? 0);        // 0 = qualquer profissional
    $quando     = strtotime($_POST['data'] ?? '');            // texto -> número (false se inválido)

    // O preço vem do BANCO, nunca do formulário (ninguém consegue "mudar o preço" pelo navegador).
    $st = $pdo->prepare('SELECT * FROM servicos WHERE id = ?');
    $st->execute([$servico_id]);
    $servico = $st->fetch();

    if (!$servico) {
        $erro = 'Escolha um serviço.';
    } elseif ($quando === false) {
        $erro = 'Informe a data e o horário.';
    } elseif ($quando <= time()) {
        $erro = 'Escolha uma data futura.';
    } elseif (!in_array((int) date('w', $quando), $DIAS_ATENDIMENTO)
              || (int) date('G', $quando) < $HORA_ABRE
              || (int) date('G', $quando) >= $HORA_FECHA) {
        $erro = "Escolha um horário dentro do atendimento: $TEXTO_HORARIO.";
    } else {
        $data_hora = date('Y-m-d H:i:00', $quando);

        // Já existe agendamento (não cancelado) neste mesmo horário?
        $st = $pdo->prepare("SELECT id FROM agendamentos WHERE data_hora = ? AND status <> 'Cancelado'");
        $st->execute([$data_hora]);

        if ($st->fetch()) {
            $erro = 'Este horário já foi reservado. Escolha outro.';
        } else {
            // Grava o agendamento (profissional vazia = NULL)
            $st = $pdo->prepare(
                'INSERT INTO agendamentos (usuario_id, servico_id, profissional_id, data_hora, valor)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $st->execute([$usuario['id'], $servico['id'], $prof_id ?: null, $data_hora, $servico['valor']]);

            // Registra a "confirmação enviada" (aparece em Minha Conta)
            $pdo->prepare('INSERT INTO confirmacoes (agendamento_id) VALUES (?)')->execute([$pdo->lastInsertId()]);

            $sucesso = 'Agendamento confirmado para ' . date('d/m/Y H:i', $quando)
                     . ' — ' . $servico['nome'] . ' (' . moeda($servico['valor']) . '). Pagamento no studio.';
        }
    }
}

// Listas para preencher os <select> do formulário
$servicos      = $pdo->query('SELECT id, nome, valor FROM servicos ORDER BY id')->fetchAll();
$profissionais = $pdo->query('SELECT id, nome FROM profissionais ORDER BY id')->fetchAll();

$titulo = 'Agendar';
require 'topo.php';
?>
        <main class="registration-container">
            <form class="form-card" method="post">
                <h2><i class="fa-solid fa-calendar-check"></i> Novo Agendamento</h2>
                <p class="form-hint">Atendimento: <?= e($TEXTO_HORARIO) ?>.</p>
                <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>
                <?php if ($sucesso): ?>
                    <div class="alerta alerta-ok"><?= e($sucesso) ?> <a href="meus-agendamentos.php">Ver meus agendamentos</a></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="servico">Serviço</label>
                    <select id="servico" name="servico" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($servicos as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['nome']) ?> — <?= moeda($s['valor']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="profissional">Profissional (opcional)</label>
                    <select id="profissional" name="profissional">
                        <option value="">Qualquer profissional</option>
                        <?php foreach ($profissionais as $p): ?>
                            <option value="<?= (int) $p['id'] ?>"><?= e($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="data">Data e Horário</label>
                    <input type="datetime-local" id="data" name="data" min="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <button type="submit" class="btn-primary">CONFIRMAR AGENDAMENTO</button>
            </form>
        </main>
<?php require 'rodape.php'; ?>
