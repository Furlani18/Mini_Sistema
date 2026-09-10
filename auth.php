<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';

// Protege a página atual: se não houver usuário autenticado na sessão,
// redireciona para a tela de login, guardando a página de destino.
if (!isset($_SESSION['usuario_id'])) {
    $destino = basename($_SERVER['SCRIPT_NAME']);
    header('Location: login.php?redirect=' . urlencode($destino));
    exit;
}
