<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/seguranca.php';

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

// Mensagem guardada antes de um redirecionamento (ex.: "Você saiu do sistema")
foreach (flash_pegar() as $aviso) {
    $mensagem = $aviso['mensagem'];
    $tipo_mensagem = $aviso['tipo'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $senha = is_string($_POST['senha'] ?? null) ? trim($_POST['senha']) : '';
    $email_preenchido = $email;
    $minutos = $email !== '' ? login_bloqueado($pdo, $email) : 0;

    if (!csrf_valido()) {
        $mensagem = 'A página de login expirou. Tente entrar novamente.';
        $tipo_mensagem = 'error';
    } elseif (empty($email) || empty($senha)) {
        $mensagem = 'Informe email e senha!';
        $tipo_mensagem = 'error';
    } elseif ($minutos > 0) {
        // Bloqueio temporário: nem confere a senha, para não ajudar quem está "chutando"
        $mensagem = "Muitas tentativas erradas. Aguarde $minutos minuto(s) e tente novamente.";
        $tipo_mensagem = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT ID, NOME, EMAIL, SENHA, PERFIL FROM usuarios WHERE EMAIL = ?');
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['SENHA'])) {
            session_regenerate_id(true);
            unset($_SESSION['csrf']); // novo token para a sessão autenticada
            $_SESSION['usuario_id'] = $usuario['ID'];
            $_SESSION['usuario_nome'] = $usuario['NOME'];
            $_SESSION['usuario_email'] = $usuario['EMAIL'];
            $_SESSION['usuario_perfil'] = $usuario['PERFIL'];

            limpar_tentativas($pdo, $email);
            registrar_atividade($pdo, 'LOGIN', 'Entrou no sistema', 'usuario', (int)$usuario['ID']);

            header('Location: ' . $redirect);
            exit;
        }

        registrar_tentativa_falha($pdo, $email);
        $restantes = LOGIN_MAX_POR_EMAIL - (int)$pdo->query(
            'SELECT COUNT(*) FROM tentativas_login WHERE EMAIL = ' . $pdo->quote($email)
            . ' AND CRIADO_EM > NOW() - INTERVAL ' . LOGIN_JANELA_MINUTOS . ' MINUTE'
        )->fetchColumn();
        $mensagem = match (true) {
            $restantes <= 0 => 'Email ou senha inválidos! Por segurança, o login foi bloqueado por ' . LOGIN_JANELA_MINUTOS . ' minutos.',
            $restantes <= 2 => "Email ou senha inválidos! Restam $restantes tentativa(s) antes do bloqueio temporário.",
            default         => 'Email ou senha inválidos!',
        };
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
    <link rel="stylesheet" href="css/style.css">
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
                <div class="alert alert-<?php echo $tipo_mensagem; ?>" role="alert">
                    <?php echo icone($tipo_mensagem === 'success' ? 'sucesso' : 'erro'); ?>
                    <span><?php echo htmlspecialchars($mensagem); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="formLogin">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <?php echo csrf_campo(); ?>

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
                    <button type="submit" class="btn btn-primary"><?php echo icone('entrar'); ?> Entrar</button>
                </div>
            </form>

            <p class="login-hint">
                Esqueceu a senha? Peça a um administrador do sistema para redefini-la.
            </p>
        </div>
    </div>
</body>
</html>
