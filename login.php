<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';

// Se já estiver logado, vai direto para o sistema
if (isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';
$email_preenchido = '';

// Página para onde o usuário será enviado após o login
$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $redirect)) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $email_preenchido = $email;

    if (empty($email) || empty($senha)) {
        $mensagem = 'Informe email e senha!';
        $tipo_mensagem = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT ID, NOME, EMAIL, SENHA FROM usuarios WHERE EMAIL = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['SENHA'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = $usuario['ID'];
            $_SESSION['usuario_nome'] = $usuario['NOME'];
            $_SESSION['usuario_email'] = $usuario['EMAIL'];

            header('Location: ' . $redirect);
            exit;
        }

        $mensagem = 'Email ou senha inválidos!';
        $tipo_mensagem = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Gerenciamento de Clientes</title>
    <link rel="stylesheet" href="css/styly.css">
</head>
<body class="login-body">
    <div class="login-wrapper">
        <div class="login-card">
            <div class="login-brand">
                <span class="brand-mark">CRM</span>
                <h1>Gestão de Clientes</h1>
            </div>
            <p class="login-subtitle">Entre com suas credenciais para acessar o sistema</p>

            <?php if (!empty($mensagem)): ?>
                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <?php echo htmlspecialchars($mensagem); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="formLogin">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Digite seu email"
                        value="<?php echo htmlspecialchars($email_preenchido); ?>"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        placeholder="Digite sua senha"
                        required
                    >
                </div>

                <div class="form-actions login-actions">
                    <button type="submit" class="btn btn-primary">🔐 Entrar</button>
                </div>
            </form>

            <p class="login-hint">
                Acesso padrão: <strong>admin@sistema.com</strong> / <strong>admin123</strong>
            </p>
        </div>
    </div>
</body>
</html>
