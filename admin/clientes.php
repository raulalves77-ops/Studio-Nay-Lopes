<?php
/* admin/clientes.php — clientes, contato e histórico resumido. */

defined('PAINEL_STUDIO') or exit;

$busca = trim((string) ($_GET['busca'] ?? ''));

$sql = "SELECT u.id, u.nome, u.email, u.telefone, u.ativo, u.criado_em,
               COALESCE(x.qtd, 0) AS qtd,
               COALESCE(x.total, 0) AS total,
               x.ultimo
          FROM usuarios u
          LEFT JOIN (
               SELECT usuario_id,
                      COUNT(*) AS qtd,
                      SUM(CASE WHEN status = 'Concluido' THEN valor ELSE 0 END) AS total,
                      MAX(CASE WHEN status = 'Concluido' THEN data_hora END) AS ultimo
                 FROM agendamentos
                GROUP BY usuario_id
          ) x ON x.usuario_id = u.id
         WHERE u.tipo = 'cliente'";
$parametros = [];

if ($busca !== '') {
    $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ? OR u.telefone LIKE ?)';
    $termo = '%' . $busca . '%';
    $parametros = [$termo, $termo, $termo];
}
$sql .= ' ORDER BY u.nome';

$st = $pdo->prepare($sql);
$st->execute($parametros);
$clientes = $st->fetchAll();
?>

<div class="agenda-barra">
    <div class="agenda-dia">
        <div>
            <strong><?= count($clientes) ?> cliente<?= count($clientes) === 1 ? '' : 's' ?></strong>
            <span class="texto-min">cadastradas</span>
        </div>
    </div>
    <form method="get" action="admin.php" class="inline-search">
        <input type="hidden" name="secao" value="clientes">
        <input type="search" name="busca" value="<?= e($busca) ?>"
               placeholder="Buscar por nome, e-mail ou telefone" aria-label="Buscar cliente">
        <button type="submit" class="btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
    </form>
</div>

<div class="db-card">
    <h3><i class="fa-solid fa-users"></i> Clientes</h3>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Nome</th><th>Contato</th><th>Atendimentos</th><th>Total gasto</th>
                    <th>Último atendimento</th><th>Situação</th><th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                    <?php $digitos = preg_replace('/\D+/', '', (string) $c['telefone']); ?>
                    <tr>
                        <td>
                            <?= e($c['nome']) ?>
                            <span class="texto-min">desde <?= data_br_dia($c['criado_em']) ?></span>
                        </td>
                        <td>
                            <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a>
                            <?php if ($digitos !== ''): ?>
                                <span class="texto-min"><?= e($c['telefone']) ?></span>
                                <?php if (strlen($digitos) >= 10): ?>
                                    <a class="link-whats" target="_blank" rel="noopener"
                                       href="https://wa.me/55<?= e($digitos) ?>"
                                       title="Falar no WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $c['qtd'] ?></td>
                        <td><?= moeda($c['total']) ?></td>
                        <td><?= $c['ultimo'] ? data_br($c['ultimo']) : '—' ?></td>
                        <td>
                            <span class="<?= (int) $c['ativo'] === 1 ? 'badge-pago' : 'badge-cancelado' ?>">
                                <?= (int) $c['ativo'] === 1 ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="inline-form"
                                  onsubmit="return confirm('<?= (int) $c['ativo'] === 1 ? 'Desativar' : 'Reativar' ?> o acesso desta cliente?')">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="acao" value="cliente_status">
                                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                <button type="submit" class="btn-acao<?= (int) $c['ativo'] === 1 ? ' btn-perigo' : '' ?>">
                                    <?= (int) $c['ativo'] === 1 ? 'Desativar' : 'Reativar' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$clientes): ?>
                    <tr><td colspan="7"><?= $busca !== '' ? 'Nenhuma cliente encontrada.' : 'Nenhuma cliente cadastrada ainda.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
