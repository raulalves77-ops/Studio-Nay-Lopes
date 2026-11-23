<?php
/* login.php — confere e-mail e senha e guarda o usuário na sessão. */
require 'conexao.php';

// Quem já está logado não precisa ver o formulário.
if (usuario_logado()) {
    redirecionar(eh_admin() || eh_profissional() ? 'admin.php' : 'minha-conta.php');
}

/**
 * Garante a tabela de tentativas de login. Se o banco não permitir a
 * criação (privilégio), o login continua funcionando e o problema fica
 * registrado no log — nunca derruba a página.
 */
function login_tabela_disponivel(PDO $pdo): bool
{
    static $disponivel = null;
    if ($disponivel !== null) {
        return $disponivel;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS tentativas_login (
                id        INT AUTO_INCREMENT PRIMARY KEY,
                email     VARCHAR(150) NOT NULL DEFAULT '',
                ip        VARCHAR(45)  NOT NULL DEFAULT '',
                criado_em DATETIME     NOT NULL,
                KEY idx_tentativas_email (email, criado_em),
                KEY idx_tentativas_ip (ip, criado_em)
            ) ENGINE=InnoDB"
        );
        $disponivel = true;
    } catch (PDOException $excecao) {
        error_log('Tabela tentativas_login indisponível: ' . $excecao->getMessage());
        $disponivel = false;
    }

    return $disponivel;
}

/** Já passou do limite de erros para este e-mail ou este IP? */
function login_tentativas_excedidas(PDO $pdo, string $email, string $ip): bool
{
    if (!login_tabela_disponivel($pdo)) {
        return false;
    }

    try {
        $st = $pdo->prepare(
            'SELECT SUM(email = ?) AS por_email, SUM(ip = ?) AS por_ip
               FROM tentativas_login
              WHERE criado_em >= ?'
        );
        $st->execute([$email, $ip, date('Y-m-d H:i:s', time() - 900)]);   // 15 min
        $linha = $st->fetch() ?: [];

        return (int) ($linha['por_email'] ?? 0) >= 5
            || (int) ($linha['por_ip'] ?? 0) >= 20;
    } catch (PDOException $excecao) {
        error_log('Falha ao consultar tentativas de login: ' . $excecao->getMessage());
        return false;
    }
}

/** Anota mais um erro (usado só quando o login falha). */
function login_tentativas_registrar(PDO $pdo, string $email, string $ip): void
{
    if (!login_tabela_disponivel($pdo)) {
        return;
    }

    try {
        $pdo->prepare('INSERT INTO tentativas_login (email, ip, criado_em) VALUES (?, ?, ?)')
            ->execute([$email, $ip, date('Y-m-d H:i:s')]);

        // Limpeza de vez em quando, para a tabela não crescer sem limite.
        if (random_int(1, 20) === 1) {
            $pdo->prepare('DELETE FROM tentativas_login WHERE criado_em < ?')
                ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        }
    } catch (PDOException $excecao) {
        error_log('Falha ao registrar tentativa de login: ' . $excecao->getMessage());
    }
}

/** Login deu certo: zera o histórico deste e-mail e deste IP. */
function login_tentativas_limpar(PDO $pdo, string $email, string $ip): void
{
    if (!login_tabela_disponivel($pdo)) {
        return;
    }

    try {
        $pdo->prepare('DELETE FROM tentativas_login WHERE email = ? OR ip = ?')
            ->execute([$email, $ip]);
    } catch (PDOException $excecao) {
        error_log('Falha ao limpar tentativas de login: ' . $excecao->getMessage());
    }
}

$erro  = !empty($_GET['expirada']) ? 'Sua sessão expirou por inatividade. Entre novamente.' : '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $senha = (string) ($_POST['senha'] ?? '');

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '0.0.0.0';
    }

    // Limite guardado no banco (por e-mail e por IP): não desaparece
    // quando o atacante troca de cookie.
    if (login_tentativas_excedidas($pdo, $email, $ip)) {
        $erro = 'Muitas tentativas seguidas. Aguarde alguns minutos e tente novamente.';
    } else {
        $st = $pdo->prepare('SELECT id, nome, email, senha_hash, tipo, ativo FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        $usuario = $st->fetch();

        if ($usuario && (int) $usuario['ativo'] === 1
            && password_verify($senha, $usuario['senha_hash'])) {

            // Atualiza o hash caso o algoritmo padrão do PHP tenha mudado.
            if (password_needs_rehash($usuario['senha_hash'], PASSWORD_DEFAULT)) {
                $pdo->prepare('UPDATE usuarios SET senha_hash = ? WHERE id = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $usuario['id']]);
            }

            login_tentativas_limpar($pdo, $email, $ip);

            session_regenerate_id(true);
            csrf_renovar();   // o token de antes do login não vale mais

            $_SESSION['usuario'] = [
                'id'    => (int) $usuario['id'],
                'nome'  => $usuario['nome'],
                'email' => $usuario['email'],
                'tipo'  => $usuario['tipo'],
            ];

            // Profissional precisa do id do perfil para ver a própria agenda.
            if ($usuario['tipo'] === 'profissional') {
                $st = $pdo->prepare('SELECT id FROM profissionais WHERE usuario_id = ?');
                $st->execute([$usuario['id']]);
                $_SESSION['usuario']['profissional_id'] = (int) $st->fetchColumn() ?: null;
            }

            redirecionar($usuario['tipo'] === 'cliente' ? 'minha-conta.php' : 'admin.php');
        }

        login_tentativas_registrar($pdo, $email, $ip);

        // Atraso pequeno e variável: encarece tentativa e erro em massa.
        usleep(random_int(150000, 450000));

        // A mesma mensagem para e-mail inexistente e senha errada.
        $erro = 'E-mail ou senha incorretos.';
    }
}

$titulo = 'Login';
require 'topo.php';
?>
        <main class="registration-container">
            <form class="form-card" method="post" autocomplete="on">
                <?= campo_csrf() ?>
                <h2><i class="fa-solid fa-lock"></i> Acessar Conta</h2>
                <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>"
                           autocomplete="email" required autofocus>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn-primary">ENTRAR</button>
                <p class="form-hint" style="margin-top:14px;">Ainda não tem conta? <a href="cadastro.php">Cadastre-se</a></p>
            </form>
        </main>
<?php require 'rodape.php'; ?>
