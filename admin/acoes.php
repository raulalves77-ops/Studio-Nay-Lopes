<?php
/* admin/acoes.php — processa TODOS os formulários do painel.
   Incluído por admin.php somente em requisições POST.
   Cada ação valida os dados, grava e redireciona (PRG). */

// Só roda quando incluído pelo admin.php (nunca por acesso direto).
defined('PAINEL_STUDIO') or exit;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirecionar('admin.php');
}

verificar_csrf();

$acao = (string) ($_POST['acao'] ?? '');
$id   = (int) ($_POST['id'] ?? 0);

/** Busca um agendamento que o usuário logado pode alterar. */
function acao_agendamento_permitido(PDO $pdo, $agendamentoId, $meuProfissionalId)
{
    $st = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ?');
    $st->execute([(int) $agendamentoId]);
    $agendamento = $st->fetch();

    if (!$agendamento) {
        flash('erro', 'Agendamento não encontrado.');
        redirecionar('admin.php?secao=agenda');
    }
    if ($meuProfissionalId && (int) $agendamento['profissional_id'] !== (int) $meuProfissionalId) {
        http_response_code(403);
        die('Você não tem permissão para alterar este agendamento.');
    }
    return $agendamento;
}

/** Volta para a agenda, mantendo o dia do atendimento. */
function acao_voltar_agenda($dataHora = null)
{
    $url = 'admin.php?secao=agenda';
    if ($dataHora) {
        $url .= '&data=' . urlencode(date('Y-m-d', strtotime($dataHora)));
    }
    redirecionar($url);
}

/** Volta para o caixa, mantendo o mês aberto. */
function acao_voltar_caixa()
{
    $mes = (string) ($_POST['mes'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }
    redirecionar('admin.php?secao=caixa&mes=' . urlencode($mes));
}

/** Ações que só o administrador pode executar. */
$acoesDoAdmin = [
    'agendamento_novo',
    'servico_novo', 'servico_editar',
    'profissional_novo', 'profissional_editar',
    'caixa_nova', 'caixa_excluir',
    'cliente_status',
];
if (!$ehAdmin && in_array($acao, $acoesDoAdmin, true)) {
    http_response_code(403);
    die('Ação restrita à administração do studio.');
}

try {
    switch ($acao) {

        // ====================================================
        // AGENDA
        // ====================================================
        case 'agendamento_status':
            $agendamento = acao_agendamento_permitido($pdo, $id, $meuProfissionalId);
            $novo = (string) ($_POST['novo_status'] ?? '');

            if (!in_array($novo, ['Pendente', 'Confirmado', 'Concluido', 'Cancelado', 'Faltou'], true)) {
                flash('erro', 'Status inválido.');
                acao_voltar_agenda($agendamento['data_hora']);
            }

            // Só transições coerentes com o fluxo do atendimento.
            $transicoes = [
                'Pendente'   => ['Confirmado', 'Concluido', 'Cancelado', 'Faltou'],
                'Confirmado' => ['Concluido', 'Cancelado', 'Faltou'],
                'Concluido'  => ['Pendente', 'Confirmado'],
                'Cancelado'  => ['Pendente'],
                'Faltou'     => ['Pendente'],
            ];
            if (!in_array($novo, $transicoes[$agendamento['status']] ?? [], true)) {
                flash('erro', 'Não é possível mudar de "' . rotulo_status($agendamento['status'])
                    . '" para "' . rotulo_status($novo) . '".');
                acao_voltar_agenda($agendamento['data_hora']);
            }

            // Desfazer uma receita já lançada é decisão da administração.
            if ($agendamento['status'] === 'Concluido' && !$ehAdmin) {
                http_response_code(403);
                die('Somente a administração do studio pode reabrir um atendimento concluído.');
            }

            if ($novo === 'Concluido') {
                $forma = (string) ($_POST['forma_pagamento'] ?? '');
                if (!in_array($forma, formas_pagamento(), true)) {
                    flash('erro', 'Escolha a forma de pagamento para concluir o atendimento.');
                    acao_voltar_agenda($agendamento['data_hora']);
                }

                $pdo->beginTransaction();

                $pdo->prepare(
                    "UPDATE agendamentos
                        SET status = 'Concluido', pago = 1, forma_pagamento = ?
                      WHERE id = ?"
                )->execute([$forma, $id]);

                caixa_registrar_agendamento($pdo, $id);
                $pdo->commit();
                flash('ok', 'Atendimento concluído e receita lançada no caixa.');
            } else {
                $pdo->beginTransaction();

                // Cancelar, marcar falta ou reabrir desfaz o pagamento: a
                // receita sai do caixa e o atendimento volta a ficar em aberto.
                $pdo->prepare(
                    "UPDATE agendamentos
                        SET status = ?, pago = 0, forma_pagamento = NULL
                      WHERE id = ?"
                )->execute([$novo, $id]);

                // Se o atendimento tinha receita lançada, ela sai do caixa.
                caixa_remover_agendamento($pdo, $id);
                $pdo->commit();
                flash('ok', 'Status atualizado para ' . rotulo_status($novo) . '.');
            }

            acao_voltar_agenda($agendamento['data_hora']);
            break;

        case 'agendamento_novo':   // encaixe feito pelo studio
            $clienteId = (int) ($_POST['cliente'] ?? 0);
            $servicoId = (int) ($_POST['servico'] ?? 0);
            $profId    = $meuProfissionalId ?: (int) ($_POST['profissional'] ?? 0);
            $quando    = strtotime((string) ($_POST['data'] ?? ''));
            $obs       = trim((string) ($_POST['observacoes'] ?? ''));

            $st = $pdo->prepare("SELECT id FROM usuarios WHERE id = ? AND tipo = 'cliente' AND ativo = 1");
            $st->execute([$clienteId]);
            $clienteOk = (bool) $st->fetch();

            $st = $pdo->prepare('SELECT id, nome, valor, duracao_minutos FROM servicos WHERE id = ? AND ativo = 1');
            $st->execute([$servicoId]);
            $servico = $st->fetch();

            $st = $pdo->prepare('SELECT id FROM profissionais WHERE id = ? AND ativo = 1');
            $st->execute([$profId]);
            $profOk = (bool) $st->fetch();

            $duracao     = $servico ? agenda_duracao_servico($servico) : 60;
            $erroHorario = agenda_validar_horario($quando, $duracao);

            if (!$clienteOk) {
                flash('erro', 'Escolha uma cliente ativa.');
            } elseif (!$servico) {
                flash('erro', 'Escolha um serviço ativo.');
            } elseif (!$profOk) {
                flash('erro', 'Escolha uma profissional ativa.');
            } elseif ($erroHorario) {
                flash('erro', $erroHorario);
            } else {
                $duracao = agenda_duracao_servico($servico);

                // Trava a linha da profissional: dois encaixes simultâneos não
                // podem passar juntos pela checagem de conflito.
                $pdo->beginTransaction();
                $pdo->prepare('SELECT id FROM profissionais WHERE id = ? FOR UPDATE')
                    ->execute([$profId]);

                if (agenda_conflito($pdo, $profId, $quando, $duracao)) {
                    $pdo->rollBack();
                    flash('erro', 'A profissional já tem atendimento nesse horário.');
                } elseif (agenda_bloqueado($pdo, $profId, $quando, $quando + $duracao * 60)) {
                    $pdo->rollBack();
                    flash('erro', 'A agenda está bloqueada nesse horário.');
                } else {
                    $pdo->prepare(
                        'INSERT INTO agendamentos
                            (usuario_id, servico_id, profissional_id, data_hora, valor, status, observacoes)
                         VALUES (?, ?, ?, ?, ?, \'Confirmado\', ?)'
                    )->execute([
                        $clienteId,
                        $servicoId,
                        $profId,
                        date('Y-m-d H:i:00', $quando),
                        $servico['valor'],
                        $obs !== '' ? mb_substr($obs, 0, 255) : null,
                    ]);
                    $pdo->commit();
                    flash('ok', 'Encaixe agendado para ' . date('d/m/Y H:i', $quando) . '.');
                }
            }

            acao_voltar_agenda($quando ? date('Y-m-d H:i:s', $quando) : null);
            break;

        case 'bloqueio_novo':
            $profId = $meuProfissionalId ?: (int) ($_POST['profissional'] ?? 0);
            $inicio = strtotime((string) ($_POST['inicio'] ?? ''));
            $fim    = strtotime((string) ($_POST['fim'] ?? ''));
            $motivo = trim((string) ($_POST['motivo'] ?? ''));

            if ($meuProfissionalId) {
                $profId = $meuProfissionalId;   // profissional bloqueia a própria agenda
            }

            if (!$inicio || !$fim) {
                flash('erro', 'Informe o início e o fim do bloqueio.');
            } elseif ($fim <= $inicio) {
                flash('erro', 'O fim do bloqueio deve ser depois do início.');
            } else {
                if ($profId) {
                    $st = $pdo->prepare('SELECT id FROM profissionais WHERE id = ?');
                    $st->execute([$profId]);
                    if (!$st->fetch()) {
                        flash('erro', 'Profissional inválida para o bloqueio.');
                        redirecionar('admin.php?secao=agenda');
                    }
                }

                $pdo->prepare(
                    'INSERT INTO bloqueios (profissional_id, inicio, fim, motivo) VALUES (?, ?, ?, ?)'
                )->execute([
                    $profId ?: null,
                    date('Y-m-d H:i:s', $inicio),
                    date('Y-m-d H:i:s', $fim),
                    mb_substr($motivo, 0, 255),
                ]);
                flash('ok', 'Agenda bloqueada.');
            }

            acao_voltar_agenda($inicio ? date('Y-m-d H:i:s', $inicio) : null);
            break;

        case 'bloqueio_excluir':
            $st = $pdo->prepare('SELECT * FROM bloqueios WHERE id = ?');
            $st->execute([$id]);
            $bloqueio = $st->fetch();

            if (!$bloqueio) {
                flash('erro', 'Bloqueio não encontrado.');
            } elseif ($meuProfissionalId && (int) $bloqueio['profissional_id'] !== (int) $meuProfissionalId) {
                http_response_code(403);
                die('Você não pode remover este bloqueio.');
            } else {
                $pdo->prepare('DELETE FROM bloqueios WHERE id = ?')->execute([$id]);
                flash('ok', 'Bloqueio removido.');
            }

            redirecionar('admin.php?secao=agenda' . (!empty($bloqueio['inicio'])
                ? '&data=' . urlencode(date('Y-m-d', strtotime($bloqueio['inicio'])))
                : ''));
            break;

        // ====================================================
        // CAIXA
        // ====================================================
        case 'caixa_nova':
            $tipo      = ($_POST['tipo'] ?? '') === 'saida' ? 'saida' : 'entrada';
            $categoria = trim((string) ($_POST['categoria'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $valor     = valor_decimal($_POST['valor'] ?? '');
            $data      = (string) ($_POST['data'] ?? '');
            $dataObj   = date_create_from_format('Y-m-d', $data);

            if (!$dataObj || $dataObj->format('Y-m-d') !== $data) {
                flash('erro', 'Informe uma data válida para o lançamento.');
            } elseif ($valor === null || $valor <= 0) {
                flash('erro', 'Informe um valor maior que zero.');
            } elseif ($descricao === '') {
                flash('erro', 'Descreva o lançamento.');
            } else {
                $pdo->prepare(
                    'INSERT INTO movimentacoes (tipo, categoria, descricao, valor, data)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([
                    $tipo,
                    $categoria !== '' ? mb_substr($categoria, 0, 60) : 'Outros',
                    mb_substr($descricao, 0, 255),
                    $valor,
                    $data,
                ]);
                flash('ok', 'Lançamento registrado no caixa.');
            }

            acao_voltar_caixa();
            break;

        case 'caixa_excluir':
            $st = $pdo->prepare('SELECT id, agendamento_id FROM movimentacoes WHERE id = ?');
            $st->execute([$id]);
            $movimentacao = $st->fetch();

            if (!$movimentacao) {
                flash('erro', 'Lançamento não encontrado.');
            } elseif ($movimentacao['agendamento_id'] !== null) {
                flash('erro', 'Receitas de atendimento são controladas pela agenda. Reabra o atendimento para removê-las.');
            } else {
                $pdo->prepare('DELETE FROM movimentacoes WHERE id = ?')->execute([$id]);
                flash('ok', 'Lançamento excluído.');
            }

            acao_voltar_caixa();
            break;

        // ====================================================
        // SERVIÇOS
        // ====================================================
        case 'servico_novo':
            $nome      = trim((string) ($_POST['nome'] ?? ''));
            $valor     = valor_decimal($_POST['valor'] ?? '');
            $duracao   = (int) ($_POST['duracao'] ?? 60);
            $descricao = trim((string) ($_POST['descricao'] ?? ''));

            if ($nome === '') {
                flash('erro', 'Informe o nome do serviço.');
            } elseif ($valor === null) {
                flash('erro', 'Informe um valor válido.');
            } elseif ($duracao < 15 || $duracao > 480) {
                flash('erro', 'A duração deve ficar entre 15 e 480 minutos.');
            } else {
                $pdo->prepare(
                    'INSERT INTO servicos (nome, valor, duracao_minutos, descricao)
                     VALUES (?, ?, ?, ?)'
                )->execute([
                    mb_substr($nome, 0, 120),
                    $valor,
                    $duracao,
                    $descricao !== '' ? mb_substr($descricao, 0, 255) : null,
                ]);
                flash('ok', 'Serviço cadastrado.');
            }

            redirecionar('admin.php?secao=servicos');
            break;

        case 'servico_editar':
            $nome      = trim((string) ($_POST['nome'] ?? ''));
            $valor     = valor_decimal($_POST['valor'] ?? '');
            $duracao   = (int) ($_POST['duracao'] ?? 60);
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $ativo     = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;

            $st = $pdo->prepare('SELECT id FROM servicos WHERE id = ?');
            $st->execute([$id]);
            $existe = (bool) $st->fetch();

            if (!$existe) {
                flash('erro', 'Serviço não encontrado.');
            } elseif ($nome === '') {
                flash('erro', 'Informe o nome do serviço.');
            } elseif ($valor === null) {
                flash('erro', 'Informe um valor válido.');
            } elseif ($duracao < 15 || $duracao > 480) {
                flash('erro', 'A duração deve ficar entre 15 e 480 minutos.');
            } else {
                $pdo->prepare(
                    'UPDATE servicos
                        SET nome = ?, valor = ?, duracao_minutos = ?, descricao = ?, ativo = ?
                      WHERE id = ?'
                )->execute([
                    mb_substr($nome, 0, 120),
                    $valor,
                    $duracao,
                    $descricao !== '' ? mb_substr($descricao, 0, 255) : null,
                    $ativo,
                    $id,
                ]);
                flash('ok', 'Serviço atualizado.');
            }

            redirecionar('admin.php?secao=servicos');
            break;

        // ====================================================
        // PROFISSIONAIS
        // ====================================================
        case 'profissional_novo':
            $nome       = trim((string) ($_POST['nome'] ?? ''));
            $email      = strtolower(trim((string) ($_POST['email'] ?? '')));
            $telefone   = trim((string) ($_POST['telefone'] ?? ''));
            $senha      = (string) ($_POST['senha'] ?? '');
            $especial   = trim((string) ($_POST['especialidade'] ?? ''));
            $comissao   = valor_decimal($_POST['comissao'] ?? '') ?? 0.0;

            $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
            $st->execute([$email]);

            if ($nome === '' || mb_strlen($nome) < 3) {
                flash('erro', 'Informe o nome da profissional.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('erro', 'Informe um e-mail válido para o acesso.');
            } elseif ($st->fetch()) {
                flash('erro', 'Este e-mail já está cadastrado.');
            } elseif (mb_strlen($senha) < 8) {
                flash('erro', 'A senha precisa ter no mínimo 8 caracteres.');
            } elseif ($telefone !== '' && !telefone_valido($telefone)) {
                flash('erro', 'Telefone inválido.');
            } elseif ($comissao > 100) {
                flash('erro', 'A comissão deve ficar entre 0 e 100%.');
            } else {
                $pdo->beginTransaction();

                $pdo->prepare(
                    "INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo, ativo)
                     VALUES (?, ?, ?, ?, 'profissional', 1)"
                )->execute([$nome, $email, $telefone, password_hash($senha, PASSWORD_DEFAULT)]);

                $pdo->prepare(
                    'INSERT INTO profissionais (usuario_id, nome, especialidade, telefone, comissao, ativo)
                     VALUES (?, ?, ?, ?, ?, 1)'
                )->execute([
                    (int) $pdo->lastInsertId(),
                    $nome,
                    mb_substr($especial, 0, 255),
                    $telefone,
                    $comissao,
                ]);

                $pdo->commit();
                flash('ok', 'Profissional cadastrada com acesso ao painel.');
            }

            redirecionar('admin.php?secao=profissionais');
            break;

        case 'profissional_editar':
            $st = $pdo->prepare('SELECT * FROM profissionais WHERE id = ?');
            $st->execute([$id]);
            $profissional = $st->fetch();

            $nome      = trim((string) ($_POST['nome'] ?? ''));
            $especial  = trim((string) ($_POST['especialidade'] ?? ''));
            $telefone  = trim((string) ($_POST['telefone'] ?? ''));
            $comissao  = valor_decimal($_POST['comissao'] ?? '') ?? 0.0;
            $ativo     = (int) ($_POST['ativo'] ?? 1) === 1 ? 1 : 0;
            $novaSenha = (string) ($_POST['nova_senha'] ?? '');

            if (!$profissional) {
                flash('erro', 'Profissional não encontrada.');
            } elseif ($nome === '' || mb_strlen($nome) < 3) {
                flash('erro', 'Informe o nome da profissional.');
            } elseif ($telefone !== '' && !telefone_valido($telefone)) {
                flash('erro', 'Telefone inválido.');
            } elseif ($comissao > 100) {
                flash('erro', 'A comissão deve ficar entre 0 e 100%.');
            } elseif ($novaSenha !== '' && mb_strlen($novaSenha) < 8) {
                flash('erro', 'A nova senha precisa ter no mínimo 8 caracteres.');
            } else {
                // O próprio admin não pode desativar o seu perfil de acesso.
                if ($profissional['usuario_id'] !== null
                    && (int) $profissional['usuario_id'] === (int) $usuario['id']) {
                    $ativo = 1;
                }

                $pdo->beginTransaction();

                $pdo->prepare(
                    'UPDATE profissionais
                        SET nome = ?, especialidade = ?, telefone = ?, comissao = ?, ativo = ?
                      WHERE id = ?'
                )->execute([
                    mb_substr($nome, 0, 120),
                    mb_substr($especial, 0, 255),
                    $telefone,
                    $comissao,
                    $ativo,
                    $id,
                ]);

                if ($profissional['usuario_id'] !== null) {
                    $pdo->prepare(
                        'UPDATE usuarios SET nome = ?, telefone = ?, ativo = ? WHERE id = ?'
                    )->execute([$nome, $telefone, $ativo, (int) $profissional['usuario_id']]);

                    if ($novaSenha !== '') {
                        $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                            ->execute([
                                password_hash($novaSenha, PASSWORD_DEFAULT),
                                (int) $profissional['usuario_id'],
                            ]);
                    }
                }

                $pdo->commit();
                flash('ok', 'Profissional atualizada.');
            }

            redirecionar('admin.php?secao=profissionais');
            break;

        // ====================================================
        // CLIENTES
        // ====================================================
        case 'cliente_status':
            $st = $pdo->prepare("SELECT id, nome, ativo FROM usuarios WHERE id = ? AND tipo = 'cliente'");
            $st->execute([$id]);
            $cliente = $st->fetch();

            if (!$cliente) {
                flash('erro', 'Cliente não encontrada.');
            } else {
                $novoAtivo = (int) $cliente['ativo'] === 1 ? 0 : 1;
                $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?')
                    ->execute([$novoAtivo, $id]);
                flash('ok', $novoAtivo
                    ? 'Acesso da cliente reativado.'
                    : 'Acesso da cliente desativado.');
            }

            redirecionar('admin.php?secao=clientes');
            break;

        default:
            flash('erro', 'Ação não reconhecida.');
            redirecionar('admin.php');
    }
} catch (PDOException $excecao) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Erro no painel (' . $acao . '): ' . $excecao->getMessage());

    // Nome de serviço duplicado é o erro mais comum.
    if ($excecao->getCode() === '23000') {
        flash('erro', 'Já existe um registro com esse nome/e-mail.');
    } else {
        flash('erro', 'Não foi possível salvar agora. Tente novamente.');
    }

    redirecionar('admin.php?secao=' . urlencode($secao));
}
