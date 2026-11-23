<?php
/* minha-conta.php — a cliente edita os dados, vê o histórico e pode excluir a conta. */
require 'conexao.php';
exigir_login('cliente');

$id = (int) usuario_logado()['id'];

/** A senha informada confere com a senha atual da conta? */
function conta_senha_confere(PDO $pdo, int $usuarioId, string $senha): bool
{
    if ($senha === '') {
        return false;
    }

    $st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
    $st->execute([$usuarioId]);
    $hash = $st->fetchColumn();

    return $hash !== false && password_verify($senha, (string) $hash);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $acao = $_POST['acao'] ?? '';

    // ---- Atualizar dados ----
    if ($acao === 'atualizar') {
        $nome     = trim($_POST['nome'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $telefone = trim($_POST['telefone'] ?? '');
        $nova     = (string) ($_POST['nova_senha'] ?? '');
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');

        // Trocar e-mail de login ou senha exige a senha atual.
        $mudaAcesso = $nova !== ''
            || $email !== (string) ($_SESSION['usuario']['email'] ?? '');

        $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND id <> ?');
        $st->execute([$email, $id]);
        $emailEmUso = (bool) $st->fetch();

        if ($nome === '' || $telefone === '') {
            flash('erro', 'Preencha nome e telefone.');
        } elseif (mb_strlen($nome) < 3) {
            flash('erro', 'Informe seu nome completo.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('erro', 'Informe um e-mail válido.');
        } elseif (!telefone_valido($telefone)) {
            flash('erro', 'Informe um telefone com DDD (10 ou 11 dígitos).');
        } elseif ($mudaAcesso && !conta_senha_confere($pdo, $id, $senhaAtual)) {
            flash('erro', 'Informe a senha atual para alterar o e-mail ou a senha.');
        } elseif ($emailEmUso) {
            flash('erro', 'Este e-mail já está em uso por outra conta.');
        } elseif ($nova !== '' && mb_strlen($nova) < 8) {
            flash('erro', 'A nova senha deve ter no mínimo 8 caracteres.');
        } else {
            try {
                $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?')
                    ->execute([$nome, $email, $telefone, $id]);

                if ($nova !== '') {
                    $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                        ->execute([password_hash($nova, PASSWORD_DEFAULT), $id]);
                }

                $_SESSION['usuario']['nome']  = $nome;
                $_SESSION['usuario']['email'] = $email;
                flash('ok', 'Dados atualizados.');
            } catch (PDOException $excecao) {
                error_log('Erro ao atualizar conta: ' . $excecao->getMessage());
                flash('erro', 'Não foi possível atualizar agora. Tente novamente.');
            }
        }

        redirecionar('minha-conta.php');
    }

    // ---- Excluir a conta ----
    if ($acao === 'excluir') {
        $st = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = ?');
        $st->execute([$id]);
        $conta = $st->fetch();

        if ($conta && password_verify((string) ($_POST['senha'] ?? ''), $conta['senha_hash'])) {
            try {
                $pdo->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
                $_SESSION = [];
                session_destroy();
                header('Location: index.php');
                exit;
            } catch (PDOException $excecao) {
                error_log('Erro ao excluir conta: ' . $excecao->getMessage());
                flash('erro', 'Não foi possível excluir a conta agora. Fale com o studio.');
            }
        } else {
            flash('erro', 'Senha incorreta. A conta não foi excluída.');
        }

        redirecionar('minha-conta.php');
    }
}

// Dados atuais para preencher o formulário
$st = $pdo->prepare('SELECT nome, email, telefone, criado_em FROM usuarios WHERE id = ?');
$st->execute([$id]);
$conta = $st->fetch();

if (!$conta) {
    redirecionar('logout.php');
}

// Histórico de atendimentos da cliente
$st = $pdo->prepare(
    "SELECT a.id, a.data_hora, a.valor, a.status, a.pago,
            s.nome AS servico, COALESCE(p.nome, 'A definir') AS profissional
       FROM agendamentos a
       JOIN servicos s ON s.id = a.servico_id
       LEFT JOIN profissionais p ON p.id = a.profissional_id
      WHERE a.usuario_id = ?
      ORDER BY a.data_hora DESC
      LIMIT 20"
);
$st->execute([$id]);
$atendimentos = $st->fetchAll();

$titulo = 'Minha Conta';
require 'topo.php';
?>
        <main class="page-card">
            <h1><i class="fa-solid fa-user-gear"></i> Minha Conta</h1>
            <?php exibir_flash(); ?>

            <div class="db-card">
                <h3><i class="fa-solid fa-id-card"></i> Dados Pessoais</h3>
                <p class="form-hint">Cliente desde <?= data_br_dia($conta['criado_em']) ?>.</p>
                <form method="post">
                    <?= campo_csrf() ?>
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
                    <div class="form-group">
                        <label for="senha_atual">Senha atual (para alterar e-mail ou senha)</label>
                        <input type="password" id="senha_atual" name="senha_atual"
                               placeholder="Deixe em branco se não for trocar" autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn-primary">ATUALIZAR DADOS</button>
                </form>
            </div>

            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Meus Atendimentos</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>#</th><th>Serviço</th><th>Profissional</th><th>Data/Horário</th><th>Valor</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($atendimentos as $a): ?>
                                <tr>
                                    <td>#<?= (int) $a['id'] ?></td>
                                    <td><?= e($a['servico']) ?></td>
                                    <td><?= e($a['profissional']) ?></td>
                                    <td><?= data_br($a['data_hora']) ?></td>
                                    <td><?= moeda($a['valor']) ?></td>
                                    <td><span class="<?= classe_status($a['status']) ?>"><?= e(rotulo_status($a['status'])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$atendimentos): ?><tr><td colspan="6">Nenhum atendimento registrado ainda.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-user-slash"></i> Excluir minha conta</h3>
                <p class="form-hint">Apaga seus dados e todos os seus agendamentos. Não pode ser desfeito.</p>
                <form method="post" onsubmit="return confirm('Excluir a conta e todos os agendamentos?')">
                    <?= campo_csrf() ?>
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
