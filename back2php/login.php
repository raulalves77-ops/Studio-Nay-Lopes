<?php
/* login.php — confere e-mail e senha e guarda o usuário na sessão. */
require 'conexao.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    // Procura o usuário pelo e-mail
    $st = $pdo->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $usuario = $st->fetch();

    // password_verify compara a senha digitada com o código guardado no banco
    if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
        session_regenerate_id(true);   // troca o código da sessão (segurança)
        $_SESSION['usuario'] = [
            'id'    => $usuario['id'],
            'nome'  => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo'  => $usuario['tipo'],
        ];
        // Admin vai para o painel; cliente vai para Minha Conta
        header('Location: ' . ($usuario['tipo'] === 'admin' ? 'admin.php' : 'minha-conta.php'));
        exit;
    }
    // A mesma mensagem para "e-mail não existe" e "senha errada" (não revela qual dos dois)
    $erro = 'E-mail ou senha incorretos.';
}

$titulo = 'Login';
require 'topo.php';
?>
        <main class="registration-container">
            <form class="form-card" method="post">
                <h2><i class="fa-solid fa-lock"></i> Acessar Conta</h2>
                <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit" class="btn-primary">ENTRAR</button>
                <p class="form-hint" style="margin-top:14px;">Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
            </form>
        </main>
<?php require 'rodape.php'; ?>
