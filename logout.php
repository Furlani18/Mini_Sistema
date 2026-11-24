<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';

// Sair do sistema. Só por POST com token CSRF (botão "Sair" do menu): assim um
// link ou imagem em outro site não consegue desconectar o usuário.

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valido()) {
    header('Location: ' . (isset($_SESSION['usuario_id']) ? 'index.php' : 'login.php'));
    exit;
}

if (isset($_SESSION['usuario_id'])) {
    registrar_atividade($pdo, 'LOGOUT', 'Saiu do sistema', 'usuario', (int)$_SESSION['usuario_id']);
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

// Nova sessão só para mostrar o aviso na tela de login
session_start();
flash('success', 'Você saiu do sistema.');

header('Location: login.php');
exit;
