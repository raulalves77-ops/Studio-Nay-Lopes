<?php
/* minha-conta.php — a cliente edita os dados, vê as confirmações e pode excluir a conta. */
require 'conexao.php';
exigir_login('cliente');
$id = $_SESSION['usuario']['id'];

$erro = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // ---- Atualizar dados ----
    if ($acao === 'atualizar') {
        $nome     = trim($_POST['nome'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $telefone = trim($_POST['telefone'] ?? '');
        $nova     = $_POST['nova_senha'] ?? '';

        // O e-mail novo já é de outra pessoa? (id <> ? = "que não seja eu")
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $st->execute([$email, $id]);

        if ($nome === '' || $telefone === '') {
            $erro = 'Preencha nome e telefone.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif ($st->fetch()) {
            $erro = 'Este e-mail já está em uso.';
        } elseif ($nova !== '' && strlen($nova) < 8) {
            $erro = 'A nova senha deve ter no mínimo 8 caracteres.';
        } else {
            $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?')
                ->execute([$nome, $email, $telefone, $id]);
            if ($nova !== '') {   // só troca a senha se ela foi preenchida
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
            }
            $_SESSION['usuario']['nome']  = $nome;
            $_SESSION['usuario']['email'] = $email;
            $ok = 'Dados atualizados.';
        }
    }

    // ---- Excluir a conta (o ON DELETE CASCADE apaga os agendamentos junto) ----
    if ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $conta = $st->fetch();

        if ($conta && password_verify($_POST['senha'] ?? '', $conta['senha_hash'])) {
            $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
            $_SESSION = [];
            session_destroy();
            header('Location: index.php');
            exit;
        }
        $erro = 'Senha incorreta. A conta não foi excluída.';
    }
}

// Dados atuais para preencher o formulário
$st = $pdo->prepare('SELECT nome, email, telefone FROM usuarios WHERE id = ?');
$st->execute([$id]);
$conta = $st->fetch();

// Confirmações dos agendamentos desta cliente
$st = $pdo->prepare(
    'SELECT c.id, s.nome AS servico, a.data_hora, c.enviada_em
       FROM confirmacoes c
       JOIN agendamentos a ON a.id = c.agendamento_id
       JOIN servicos s     ON s.id = a.servico_id
      WHERE a.usuario_id = ?
      ORDER BY c.enviada_em DESC'
);
$st->execute([$id]);
$confirmacoes = $st->fetchAll();

$titulo = 'Minha Conta';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-user-gear"></i> Minha Conta</h1>
            <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>
            <?php if ($ok): ?><div class="alerta alerta-ok"><?= e($ok) ?></div><?php endif; ?>

            <div class="db-card">
                <h3><i class="fa-solid fa-id-card"></i> Dados Pessoais</h3>
                <form method="post">
                    <input type="hidden" name="acao" value="atualizar">
                    <div class="form-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" value="<?= e($conta['nome']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">E-mail (login)</label>
                        <input type="email" id="email" name="email" value="<?= e($conta['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone / WhatsApp</label>
                        <input type="tel" id="telefone" name="telefone" value="<?= e($conta['telefone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nova_senha">Nova Senha (opcional, mín. 8 caracteres)</label>
                        <input type="password" id="nova_senha" name="nova_senha" placeholder="Deixe em branco para manter a atual">
                    </div>
                    <button type="submit" class="btn-primary">ATUALIZAR DADOS</button>
                </form>
            </div>

            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-envelope-circle-check"></i> Confirmações de Agendamento</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>ID</th><th>Serviço</th><th>Data/Horário</th><th>Registrada em</th></tr></thead>
                        <tbody>
                            <?php foreach ($confirmacoes as $c): ?>
                                <tr>
                                    <td>#<?= (int) $c['id'] ?></td>
                                    <td><?= e($c['servico']) ?></td>
                                    <td><?= data_br($c['data_hora']) ?></td>
                                    <td><?= data_br($c['enviada_em']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$confirmacoes): ?><tr><td colspan="4">Nenhuma confirmação registrada.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-user-slash"></i> Excluir minha conta</h3>
                <p class="form-hint">Apaga seus dados e todos os seus agendamentos. Não pode ser desfeito.</p>
                <form method="post" onsubmit="return confirm('Excluir a conta e todos os agendamentos?')">
                    <input type="hidden" name="acao" value="excluir">
                    <div class="form-group">
                        <label for="senha">Confirme sua senha</label>
                        <input type="password" id="senha" name="senha" required>
                    </div>
                    <button type="submit" class="btn-outline">EXCLUIR CONTA</button>
                </form>
            </div>
        </main>
<?php require 'rodape.php'; ?>
