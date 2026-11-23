<?php
/* ============================================================
   lib/agenda.php — regras de horário do studio.

   Todas as funções recebem $pdo como parâmetro. São usadas tanto
   pela cliente (agendar.php) quanto pelo painel (encaixes, agenda
   e bloqueios), garantindo a MESMA regra nos dois lugares.
   ============================================================ */

/** Duração de um serviço em minutos (padrão: 60). */
function agenda_duracao_servico($servico)
{
    $duracao = (int) ($servico['duracao_minutos'] ?? 0);
    return $duracao > 0 ? $duracao : 60;
}

/**
 * Confere se o horário está dentro do atendimento e se o serviço
 * (quando a duração é informada) termina antes do fechamento.
 * Devolve a mensagem de erro ou null quando está tudo certo.
 */
function agenda_validar_horario($timestamp, $duracaoMinutos = 0)
{
    global $DIAS_ATENDIMENTO, $HORA_ABRE, $HORA_FECHA, $INTERVALO_MINUTOS, $TEXTO_HORARIO;

    if (!$timestamp) {
        return 'Informe uma data e um horário válidos.';
    }
    if ($timestamp <= time()) {
        return 'Escolha uma data e um horário futuros.';
    }
    if (!in_array((int) date('w', $timestamp), $DIAS_ATENDIMENTO, true)) {
        return 'O studio não atende neste dia. ' . $TEXTO_HORARIO . '.';
    }

    $hora   = (int) date('G', $timestamp);
    $minuto = (int) date('i', $timestamp);
    if ($hora < $HORA_ABRE || $hora >= $HORA_FECHA) {
        return 'Escolha um horário dentro do atendimento: ' . $TEXTO_HORARIO . '.';
    }
    if ($minuto % $INTERVALO_MINUTOS !== 0) {
        return 'Os horários são de ' . $INTERVALO_MINUTOS . ' em ' . $INTERVALO_MINUTOS . ' minutos.';
    }

    if ((int) $duracaoMinutos > 0) {
        $fechamento = strtotime(
            date('Y-m-d', $timestamp) . ' ' . sprintf('%02d:00:00', $HORA_FECHA)
        );
        if ($timestamp + (int) $duracaoMinutos * 60 > $fechamento) {
            return 'O serviço não termina dentro do horário de atendimento. Escolha um horário mais cedo.';
        }
    }

    return null;
}

/** Existe bloqueio do studio (profissional_id NULL) ou da profissional nesse intervalo? */
function agenda_bloqueado(PDO $pdo, $profissionalId, $inicio, $fim)
{
    $st = $pdo->prepare(
        'SELECT id FROM bloqueios
          WHERE (profissional_id IS NULL OR profissional_id = ?)
            AND inicio < ? AND fim > ?
          LIMIT 1'
    );
    $st->execute([
        (int) $profissionalId,
        date('Y-m-d H:i:s', $fim),
        date('Y-m-d H:i:s', $inicio),
    ]);
    return (bool) $st->fetch();
}

/**
 * A profissional já tem atendimento que se sobrepõe a este intervalo?
 * Considera a duração real de cada serviço. $ignorarId serve para
 * remarcar um agendamento existente.
 */
function agenda_conflito(PDO $pdo, $profissionalId, $inicio, $duracaoMinutos, $ignorarId = null)
{
    $fim = $inicio + ((int) $duracaoMinutos * 60);

    // Busca desde o começo do dia do início até o fim do dia do término
    // (cobre atendimentos longos e virada de meia-noite).
    $sql = "SELECT a.id, a.data_hora, COALESCE(s.duracao_minutos, 60) AS duracao
              FROM agendamentos a
              JOIN servicos s ON s.id = a.servico_id
             WHERE a.profissional_id = ?
               AND a.data_hora >= ? AND a.data_hora <= ?
               AND a.status IN ('Pendente', 'Confirmado', 'Concluido')";
    $parametros = [
        (int) $profissionalId,
        date('Y-m-d 00:00:00', $inicio),
        date('Y-m-d 23:59:59', $fim),
    ];

    if ($ignorarId) {
        $sql .= ' AND a.id <> ?';
        $parametros[] = (int) $ignorarId;
    }

    $st = $pdo->prepare($sql);
    $st->execute($parametros);

    foreach ($st->fetchAll() as $linha) {
        $inicioExistente = strtotime($linha['data_hora']);
        $fimExistente    = $inicioExistente + ((int) $linha['duracao'] * 60);
        if ($inicioExistente < $fim && $fimExistente > $inicio) {
            return true;
        }
    }
    return false;
}

/**
 * Profissionais ativas e livres no intervalo indicado.
 * Devolve uma lista de ['id' => ..., 'nome' => ...].
 */
function agenda_profissionais_livres(PDO $pdo, $inicio, $duracaoMinutos, $profissionalId = null)
{
    $sql = 'SELECT id, nome FROM profissionais WHERE ativo = 1';
    $parametros = [];

    if ($profissionalId) {
        $sql .= ' AND id = ?';
        $parametros[] = (int) $profissionalId;
    }
    $sql .= ' ORDER BY id';

    $st = $pdo->prepare($sql);
    $st->execute($parametros);

    $fim = $inicio + ((int) $duracaoMinutos * 60);
    $livres = [];

    foreach ($st->fetchAll() as $profissional) {
        $id = (int) $profissional['id'];
        if (!agenda_conflito($pdo, $id, $inicio, $duracaoMinutos)
            && !agenda_bloqueado($pdo, $id, $inicio, $fim)) {
            $livres[] = $profissional;
        }
    }
    return $livres;
}

/**
 * Todos os horários livres de um dia, de $HORA_ABRE até $HORA_FECHA, de
 * $INTERVALO_MINUTOS em $INTERVALO_MINUTOS. Respeita a duração do serviço
 * (o atendimento precisa terminar até o fechamento), os bloqueios e a agenda
 * de cada profissional.
 *
 * $profissionalId null = qualquer profissional disponível.
 * Devolve [['hora' => '08:00', 'timestamp' => 123, 'profissionais' => 1], ...].
 */
function agenda_horarios_disponiveis(PDO $pdo, $dia, $duracaoMinutos, $profissionalId = null)
{
    global $DIAS_ATENDIMENTO, $HORA_ABRE, $HORA_FECHA, $INTERVALO_MINUTOS;

    $dia  = trim((string) $dia);
    $data = DateTime::createFromFormat('!Y-m-d', $dia);
    if (!$data || $data->format('Y-m-d') !== $dia) {
        return [];
    }
    if (!in_array((int) $data->format('w'), $DIAS_ATENDIMENTO, true)) {
        return [];
    }

    $duracao    = max(1, (int) $duracaoMinutos);
    $agora      = time();
    $inicioDia  = strtotime($dia . ' 00:00:00');
    $fechamento = $inicioDia + ((int) $HORA_FECHA * 3600);

    $horarios = [];
    for ($ts = $inicioDia + ((int) $HORA_ABRE * 3600); $ts < $fechamento; $ts += $INTERVALO_MINUTOS * 60) {
        if ($ts <= $agora) {
            continue;                       // horário que já passou
        }
        if ($ts + ($duracao * 60) > $fechamento) {
            continue;                       // não terminaria antes de fechar
        }

        $livres = agenda_profissionais_livres($pdo, $ts, $duracao, $profissionalId ? (int) $profissionalId : null);
        if (!$livres) {
            continue;                       // ocupado ou bloqueado
        }

        $horarios[] = [
            'hora'          => date('H:i', $ts),
            'timestamp'     => $ts,
            'profissionais' => count($livres),
        ];
    }

    return $horarios;
}

/** Lista enxuta das profissionais ativas (para os <select>). */
function agenda_listar_profissionais(PDO $pdo, $somenteAtivas = true)
{
    $sql = 'SELECT id, nome, especialidade, ativo FROM profissionais';
    if ($somenteAtivas) {
        $sql .= ' WHERE ativo = 1';
    }
    $sql .= ' ORDER BY nome';
    return $pdo->query($sql)->fetchAll();
}
