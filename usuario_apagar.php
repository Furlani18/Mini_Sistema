<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// A confirmação é feita antes, no modal (js/app.js), que envia um POST com token CSRF.
// Regras: só administradores; ninguém apaga o próprio usuário; o sistema nunca fica sem administrador.
// Os pedidos que ele registrou continuam existindo: a chave estrangeira
// FK_PEDIDOS_USUARIO (ON DELETE SET NULL) só limpa o "registrado por".

exigir_post('usuarios.php');
exigir_admin('usuarios.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT NOME, PERFIL FROM usuarios WHERE ID = ?');
$stmt->execute([$id]);
$alvo = $stmt->fetch();
$nome = $alvo ? $alvo['NOME'] : false;

if ($nome === false) {
    flash('error', 'Usuário não encontrado.');
} elseif ($id === (int)$_SESSION['usuario_id']) {
    flash('error', 'Você não pode apagar o próprio usuário.');
} elseif ($alvo['PERFIL'] === 'ADMIN' && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE PERFIL = 'ADMIN'")->fetchColumn() <= 1) {
    flash('error', 'O sistema precisa ter pelo menos um administrador.');
} else {
    $delete = $pdo->prepare('DELETE FROM usuarios WHERE ID = ?');
    $delete->execute([$id]);
    registrar_atividade($pdo, 'APAGOU', "Apagou o usuário '$nome'", 'usuario', $id);
    flash('success', "Usuário '$nome' removido. Os pedidos registrados por ele foram mantidos.");
}

header('Location: usuarios.php');
exit;
