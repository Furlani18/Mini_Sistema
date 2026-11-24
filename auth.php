<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';

// Protege a página atual:
// 1) sem usuário autenticado na sessão → vai para o login, guardando a página de destino;
// 2) recarrega nome/email/perfil do banco a cada página — se um administrador mudar
//    o perfil ou apagar o usuário, isso vale na hora (usuário apagado é desconectado);
// 3) todo formulário POST precisa do token CSRF (ver seguranca.php).

if (!isset($_SESSION['usuario_id'])) {
    $destino = basename($_SERVER['SCRIPT_NAME']);
    header('Location: login.php?redirect=' . urlencode($destino));
    exit;
}

$stmtUsuarioLogado = $pdo->prepare('SELECT NOME, EMAIL, PERFIL FROM usuarios WHERE ID = ?');
$stmtUsuarioLogado->execute([$_SESSION['usuario_id']]);
$usuarioLogado = $stmtUsuarioLogado->fetch();

if (!$usuarioLogado) {
    $_SESSION = [];
    session_destroy();
    header('Location: login.php');
    exit;
}
$_SESSION['usuario_nome'] = $usuarioLogado['NOME'];
$_SESSION['usuario_email'] = $usuarioLogado['EMAIL'];
$_SESSION['usuario_perfil'] = $usuarioLogado['PERFIL'];
unset($stmtUsuarioLogado, $usuarioLogado);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    flash('error', 'O formulário expirou ou é inválido. Confira os dados e envie novamente.');
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}
