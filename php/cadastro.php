<?php
/* cadastro.php — cria a conta de uma cliente e já a deixa logada. */
require 'conexao.php';

if (usuario_logado()) {
    redirecionar(eh_admin() || eh_profissional() ? 'admin.php' : 'minha-conta.php');
}

$erro     = '';
$nome     = '';
$email    = '';
$telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $nome      = trim($_POST['nome'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $telefone  = trim($_POST['telefone'] ?? '');
    $senha     = (string) ($_POST['senha'] ?? '');
    $confirmar = (string) ($_POST['confirmar_senha'] ?? '');

    if ($nome === '' || $telefone === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) < 3) {
        $erro = 'Informe seu nome completo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (!telefone_valido($telefone)) {
        $erro = 'Informe um telefone com DDD (10 ou 11 dígitos).';
    } elseif (mb_strlen($senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As duas senhas não são iguais.';
    } else {
        $st = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
        $st->execute([$email]);

        if ($st->fetch()) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            $st = $pdo->prepare(
                'INSERT INTO usuarios (nome, email, telefone, senha_hash, tipo)
                 VALUES (?, ?, ?, ?, \'cliente\')'
            );
            $st->execute([$nome, $email, $telefone, password_hash($senha, PASSWORD_DEFAULT)]);

            session_regenerate_id(true);
            csrf_renovar();   // o token de antes do cadastro não vale mais
            $_SESSION['usuario'] = [
                'id'    => (int) $pdo->lastInsertId(),
                'nome'  => $nome,
                'email' => $email,
                'tipo'  => 'cliente',
            ];

            flash('ok', 'Conta criada com sucesso. Bem-vinda, ' . primeiro_nome($nome) . '!');
            redirecionar('minha-conta.php');
        }
    }
}

$titulo = 'Cadastro';
require 'topo.php';
?>
        <main class="registration-container">
            <form class="form-card" method="post" autocomplete="on">
                <?= campo_csrf() ?>
                <h2>Novo Cliente</h2>
                <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="nome">Nome Completo</label>
                    <input type="text" id="nome" name="nome" value="<?= e($nome) ?>"
                           autocomplete="name" required>
                </div>
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="email" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone / WhatsApp</label>
                    <input type="tel" id="telefone" name="telefone" value="<?= e($telefone) ?>"
                           placeholder="(11) 99999-9999" autocomplete="tel" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha (mín. 8 caracteres)</label>
                    <input type="password" id="senha" name="senha" minlength="8"
                           autocomplete="new-password" required>
                </div>
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar senha</label>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8"
                           autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn-primary">CADASTRAR CLIENTE</button>
                <p class="form-hint" style="margin-top:14px;">Já tem conta? <a href="login.php">Entrar</a></p>
            </form>
        </main>
<?php require 'rodape.php'; ?>
