<?php
/* criar-admin.php — cria (ou atualiza) a conta do studio e a vincula
   ao perfil de profissional. Rode no terminal, uma única vez:

       php criar-admin.php "Nay Lopes" seu@email.com

   Também aceita o formato antigo (só o e-mail) e usa o nome do
   primeiro perfil de profissional sem conta. Pelo navegador não roda. */

if (php_sapi_name() !== 'cli') {
    die('Execute este arquivo pelo terminal.');
}

require 'conexao.php';

// ---- Argumentos ----
$argumentos = array_slice($argv, 1);

if (count($argumentos) >= 2) {
    $nome  = trim($argumentos[0]);
    $email = strtolower(trim($argumentos[1]));
} else {
    $nome  = '';
    $email = strtolower(trim($argumentos[0] ?? ''));
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Uso: php criar-admin.php \"Nome da Profissional\" seu@email.com\n");
}

// Sem nome informado: tenta reaproveitar o nome da profissional já cadastrada.
if ($nome === '') {
    $nome = (string) $pdo->query(
        'SELECT nome FROM profissionais WHERE usuario_id IS NULL ORDER BY id LIMIT 1'
    )->fetchColumn();
    if ($nome === '') {
        $nome = 'Administração';
    }
}

echo 'Senha (mínimo 8 caracteres): ';
$senha = trim((string) fgets(STDIN));
if (mb_strlen($senha) < 8) {
    die("Senha muito curta.\n");
}

echo 'Repita a senha: ';
if ($senha !== trim((string) fgets(STDIN))) {
    die("As senhas não coincidem.\n");
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    // ---- 1) Conta de acesso ----
    $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $usuarioId = (int) $st->fetchColumn();

    if ($usuarioId) {
        $pdo->prepare(
            "UPDATE usuarios
                SET nome = ?, senha_hash = ?, tipo = 'admin', ativo = 1
              WHERE id = ?"
        )->execute([$nome, $hash, $usuarioId]);
        echo "Conta existente atualizada: $email\n";
    } else {
        $pdo->prepare(
            "INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo, ativo)
             VALUES (?, ?, '', ?, 'admin', 1)"
        )->execute([$nome, $email, $hash]);
        $usuarioId = (int) $pdo->lastInsertId();
        echo "Conta criada: $email\n";
    }

    // ---- 2) Perfil de profissional vinculado à conta ----
    $st = $pdo->prepare('SELECT id FROM profissionais WHERE usuario_id = ?');
    $st->execute([$usuarioId]);
    $profissionalId = (int) $st->fetchColumn();

    if (!$profissionalId) {
        // Prefere um perfil sem conta com o mesmo nome (corrige cadastros antigos).
        $st = $pdo->prepare(
            'SELECT id FROM profissionais
              WHERE usuario_id IS NULL
              ORDER BY (nome = ?) DESC, id ASC
              LIMIT 1'
        );
        $st->execute([$nome]);
        $profissionalId = (int) $st->fetchColumn();

        if ($profissionalId) {
            $pdo->prepare(
                'UPDATE profissionais SET usuario_id = ?, nome = ?, ativo = 1 WHERE id = ?'
            )->execute([$usuarioId, $nome, $profissionalId]);
            echo "Perfil de profissional existente vinculado (id $profissionalId).\n";
        } else {
            $pdo->prepare(
                'INSERT INTO profissionais (usuario_id, nome, especialidade, comissao, ativo)
                 VALUES (?, ?, \'\', 0, 1)'
            )->execute([$usuarioId, $nome]);
            echo "Perfil de profissional criado.\n";
        }
    } else {
        $pdo->prepare('UPDATE profissionais SET nome = ?, ativo = 1 WHERE id = ?')
            ->execute([$nome, $profissionalId]);
        echo "Perfil de profissional já vinculado (id $profissionalId).\n";
    }

    $pdo->commit();
    echo "Pronto! Acesse login.php com $email\n";
} catch (PDOException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Detalhe do banco só no log — nunca na tela/terminal.
    error_log('Erro ao criar admin: ' . $erro->getMessage());
    die("Erro ao criar a conta. Veja o log do PHP para detalhes.\n");
}
