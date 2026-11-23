<?php
/* cadastro.php — cria a conta de uma cliente.
   Fluxo: o formulário (embaixo) envia os dados por POST para a própria página;
   o PHP (em cima) valida, grava no banco e já deixa a cliente logada. */
require 'conexao.php';

$erro = '';
$nome = $email = $telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {          // o formulário foi enviado?
    $nome     = trim($_POST['nome'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $telefone = trim($_POST['telefone'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    // 1) Validar os campos
    if ($nome === '' || $telefone === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } else {
        // 2) O e-mail já existe? (o "?" é preenchido com segurança pelo execute: evita SQL injection)
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $st->execute([$email]);

        if ($st->fetch()) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            // 3) Gravar. password_hash() transforma a senha num código irreversível.
            $st = $pdo->prepare('INSERT INTO usuarios (nome, email, telefone, senha_hash) VALUES (?, ?, ?, ?)');
            $st->execute([$nome, $email, $telefone, password_hash($senha, PASSWORD_DEFAULT)]);

            // 4) Já deixa logada: guarda os dados na sessão e vai para Minha Conta
            session_regenerate_id(true);
            $_SESSION['usuario'] = ['id' => $pdo->lastInsertId(), 'nome' => $nome, 'email' => $email, 'tipo' => 'cliente'];
            header('Location: minha-conta.php');
            exit;
        }
    }
}

$titulo = 'Cadastro';
require 'topo.php';
?>
        <main class="registration-container">
            <form class="form-card" method="post">
                <h2>Novo Cliente</h2>
                <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" value="<?= e($nome) ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= e($telefone) ?>" placeholder="(11) 99999-9999" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha (mín. 8 caracteres)</label>
                    <input type="password" id="senha" name="senha" minlength="8" required>
                </div>
                <button type="submit" class="btn-primary">CADASTRAR CLIENTE</button>
            </form>
        </main>
<?php require 'rodape.php'; ?>
