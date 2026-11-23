<?php
/* ============================================================
   lib/caixa.php — controle de fluxo de caixa.

   Regra principal: quando um atendimento é concluído e pago, a
   receita entra no caixa UMA única vez (a coluna agendamento_id
   é UNIQUE). Se o atendimento for cancelado ou reaberto, o
   lançamento automático é removido.
   ============================================================ */

/**
 * Lança (ou atualiza) a receita de um agendamento concluído.
 * A data do lançamento é o dia do atendimento.
 */
function caixa_registrar_agendamento(PDO $pdo, $agendamentoId)
{
    $st = $pdo->prepare(
        'SELECT a.id, a.valor, a.data_hora, a.forma_pagamento, s.nome AS servico
           FROM agendamentos a
           JOIN servicos s ON s.id = a.servico_id
          WHERE a.id = ?'
    );
    $st->execute([(int) $agendamentoId]);
    $agendamento = $st->fetch();

    if (!$agendamento) {
        return;
    }

    $descricao = 'Atendimento #' . (int) $agendamento['id'] . ' — ' . $agendamento['servico'];
    if (!empty($agendamento['forma_pagamento'])) {
        $descricao .= ' (' . $agendamento['forma_pagamento'] . ')';
    }

    $st = $pdo->prepare(
        "INSERT INTO movimentacoes (tipo, categoria, descricao, valor, data, agendamento_id)
         VALUES ('entrada', 'Serviços', ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             tipo      = 'entrada',
             categoria = 'Serviços',
             descricao = VALUES(descricao),
             valor     = VALUES(valor),
             data      = VALUES(data)"
    );
    $st->execute([
        $descricao,
        $agendamento['valor'],
        date('Y-m-d', strtotime($agendamento['data_hora'])),
        (int) $agendamento['id'],
    ]);
}

/** Remove o lançamento automático de um agendamento (cancelado/reaberto). */
function caixa_remover_agendamento(PDO $pdo, $agendamentoId)
{
    $st = $pdo->prepare('DELETE FROM movimentacoes WHERE agendamento_id = ?');
    $st->execute([(int) $agendamentoId]);
}

/**
 * Resumo de um mês (formato YYYY-MM).
 * Devolve entradas, saídas, saldo e quantidade de lançamentos.
 */
function caixa_resumo(PDO $pdo, $mes)
{
    $st = $pdo->prepare(
        "SELECT tipo, COALESCE(SUM(valor), 0) AS total, COUNT(*) AS qtd
           FROM movimentacoes
          WHERE DATE_FORMAT(data, '%Y-%m') = ?
          GROUP BY tipo"
    );
    $st->execute([$mes]);

    $resumo = ['entradas' => 0.0, 'saidas' => 0.0, 'saldo' => 0.0, 'qtd' => 0];
    foreach ($st->fetchAll() as $linha) {
        if ($linha['tipo'] === 'entrada') {
            $resumo['entradas'] = (float) $linha['total'];
        } else {
            $resumo['saidas'] = (float) $linha['total'];
        }
        $resumo['qtd'] += (int) $linha['qtd'];
    }
    $resumo['saldo'] = $resumo['entradas'] - $resumo['saidas'];

    return $resumo;
}

