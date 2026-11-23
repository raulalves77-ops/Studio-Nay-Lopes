<?php
/* admin.php — painel da administração (só entra quem tem tipo = 'admin').
   Em cima: trata os botões/formulários. Embaixo: mostra as tabelas. */
require 'conexao.php';
exigir_login('admin');

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);

    try {
        if ($acao === 'cancelar') {
            // Admin cancela qualquer agendamento, sem regra de antecedência
            $pdo->prepare("UPDATE agendamentos SET status = 'Cancelado' WHERE id = ?")->execute([$id]);
            $mensagem = 'Agendamento cancelado.';

        } elseif ($acao === 'servico_novo') {
            $nome  = trim($_POST['nome'] ?? '');
            $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));   // aceita "45,50"
            if ($nome === '' || !is_numeric($valor) || $valor < 0) {
                $mensagem = 'Informe um nome e um valor válidos.';
            } else {
                $pdo->prepare('INSERT INTO servicos (nome, valor) VALUES (?, ?)')->execute([$nome, $valor]);
                $mensagem = 'Serviço cadastrado.';
            }

        } elseif ($acao === 'servico_preco') {
            $valor = str_replace(',', '.', trim($_POST['valor'] ?? ''));
            if (!is_numeric($valor) || $valor < 0) {
                $mensagem = 'Informe um valor válido.';
            } else {
                $pdo->prepare('UPDATE servicos SET valor = ? WHERE id = ?')->execute([$valor, $id]);
                $mensagem = 'Preço atualizado.';
            }

        } elseif ($acao === 'servico_excluir') {
            $pdo->prepare('DELETE FROM servicos WHERE id = ?')->execute([$id]);   // CASCADE: apaga os agendamentos dele
            $mensagem = 'Serviço excluído.';

        } elseif ($acao === 'prof_nova') {
            $nome = trim($_POST['nome'] ?? '');
            $esp  = trim($_POST['especialidade'] ?? '');
            if ($nome === '' || $esp === '') {
                $mensagem = 'Informe nome e especialidade.';
            } else {
                $pdo->prepare('INSERT INTO profissionais (nome, especialidade) VALUES (?, ?)')->execute([$nome, $esp]);
                $mensagem = 'Profissional cadastrada.';
            }

        } elseif ($acao === 'prof_excluir') {
            $pdo->prepare('DELETE FROM profissionais WHERE id = ?')->execute([$id]);   // CASCADE
            $mensagem = 'Profissional excluída.';
        }
    } catch (PDOException $erro) {
        $mensagem = 'Não foi possível salvar (já existe um serviço com esse nome?).';
    }
}

// ---- Dados para as tabelas ----
// Métricas: quantos agendamentos ativos e quanto vão render
$m = $pdo->query("SELECT COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS soma FROM agendamentos WHERE status <> 'Cancelado'")->fetch();
$ocupacao = min(100, $m['qtd'] * 10);   // mesma conta do painel original

$clientes      = $pdo->query("SELECT nome, email, telefone FROM usuarios WHERE tipo = 'cliente' ORDER BY nome")->fetchAll();
$profissionais = $pdo->query('SELECT id, nome, especialidade FROM profissionais ORDER BY id')->fetchAll();
$servicos      = $pdo->query('SELECT id, nome, valor FROM servicos ORDER BY id')->fetchAll();
$agendamentos  = $pdo->query(
    'SELECT a.id, u.nome AS cliente, s.nome AS servico, a.data_hora, a.valor, a.status
       FROM agendamentos a
       JOIN usuarios u ON u.id = a.usuario_id
       JOIN servicos s ON s.id = a.servico_id
      ORDER BY a.data_hora DESC'
)->fetchAll();

$titulo = 'Painel Administrativo';
require 'topo.php';
?>
        <main class="admin-dashboard">
            <?php if ($mensagem): ?><div class="alerta alerta-ok"><?= e($mensagem) ?></div><?php endif; ?>

            <!-- Métricas -->
            <div class="metrics-grid">
                <div class="metric-card"><i class="fa-solid fa-wallet metric-icon"></i>
                    <div><h3>Faturamento Previsto</h3><p><?= moeda($m['soma']) ?></p></div></div>
                <div class="metric-card"><i class="fa-solid fa-calendar-check metric-icon"></i>
                    <div><h3>Agendamentos</h3><p><?= (int) $m['qtd'] ?></p></div></div>
                <div class="metric-card"><i class="fa-solid fa-chart-pie metric-icon"></i>
                    <div><h3>Taxa de Ocupação</h3><p><?= $ocupacao ?>%</p></div></div>
            </div>

            <!-- Agendamentos -->
            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-table"></i> Agendamentos</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>ID</th><th>Cliente</th><th>Serviço</th><th>Data/Horário</th><th>Valor</th><th>Status</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($agendamentos as $a): ?>
                                <tr>
                                    <td>#<?= (int) $a['id'] ?></td>
                                    <td><?= e($a['cliente']) ?></td>
                                    <td><?= e($a['servico']) ?></td>
                                    <td><?= data_br($a['data_hora']) ?></td>
                                    <td><?= moeda($a['valor']) ?></td>
                                    <td><span class="<?= $a['status'] === 'Cancelado' ? 'badge-cancelado' : 'badge-pendente' ?>"><?= e($a['status']) ?></span></td>
                                    <td>
                                        <?php if ($a['status'] !== 'Cancelado'): ?>
                                            <form method="post" onsubmit="return confirm('Cancelar este agendamento?')">
                                                <input type="hidden" name="acao" value="cancelar">
                                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                                <button type="submit" class="btn-cancelar">Cancelar</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$agendamentos): ?><tr><td colspan="7">Nenhum agendamento ainda.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Serviços -->
            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-scissors"></i> Serviços</h3>
                <form method="post" class="admin-form-row">
                    <input type="hidden" name="acao" value="servico_novo">
                    <div class="form-group"><label>Nome</label><input type="text" name="nome" placeholder="Ex: Hidratação" required></div>
                    <div class="form-group"><label>Valor (R$)</label><input type="number" name="valor" min="0" step="0.01" required></div>
                    <button type="submit" class="btn-primary">CADASTRAR</button>
                </form>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Serviço</th><th>Preço</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($servicos as $s): ?>
                                <tr>
                                    <td><?= e($s['nome']) ?></td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="acao" value="servico_preco">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <input type="number" name="valor" value="<?= e($s['valor']) ?>" min="0" step="0.01" style="width:100px" required>
                                            <button type="submit" class="btn-cancelar">Salvar</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="post" class="inline-form" onsubmit="return confirm('Excluir este serviço? Os agendamentos dele também serão apagados.')">
                                            <input type="hidden" name="acao" value="servico_excluir">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="btn-cancelar">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Profissionais -->
            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-user-tie"></i> Profissionais</h3>
                <form method="post" class="admin-form-row">
                    <input type="hidden" name="acao" value="prof_nova">
                    <div class="form-group"><label>Nome</label><input type="text" name="nome" required></div>
                    <div class="form-group"><label>Especialidade</label><input type="text" name="especialidade" placeholder="Ex: Cabeleireira" required></div>
                    <button type="submit" class="btn-primary">CADASTRAR</button>
                </form>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Nome</th><th>Especialidade</th><th>Ação</th></tr></thead>
                        <tbody>
                            <?php foreach ($profissionais as $p): ?>
                                <tr>
                                    <td><?= e($p['nome']) ?></td>
                                    <td><?= e($p['especialidade']) ?></td>
                                    <td>
                                        <form method="post" onsubmit="return confirm('Excluir esta profissional? Os agendamentos dela também serão apagados.')">
                                            <input type="hidden" name="acao" value="prof_excluir">
                                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                            <button type="submit" class="btn-cancelar">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Clientes -->
            <div class="db-card" style="margin-top: 24px;">
                <h3><i class="fa-solid fa-users"></i> Clientes cadastradas</h3>
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Nome</th><th>E-mail</th><th>Telefone</th></tr></thead>
                        <tbody>
                            <?php foreach ($clientes as $c): ?>
                                <tr><td><?= e($c['nome']) ?></td><td><?= e($c['email']) ?></td><td><?= e($c['telefone']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if (!$clientes): ?><tr><td colspan="3">Nenhuma cliente ainda.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
<?php require 'rodape.php'; ?>
