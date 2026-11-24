<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// A confirmação é feita antes, no modal (js/app.js).
// Regras: ninguém apaga o próprio usuário, e o sistema nunca fica sem usuários.
// Os pedidos que ele registrou continuam existindo: a chave estrangeira
// FK_PEDIDOS_USUARIO (ON DELETE SET NULL) só limpa o "registrado por".

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT NOME FROM usuarios WHERE ID = ?');
$stmt->execute([$id]);
$nome = $stmt->fetchColumn();

if ($nome === false) {
    flash('error', 'Usuário não encontrado.');
} elseif ($id === (int)$_SESSION['usuario_id']) {
    flash('error', 'Você não pode apagar o próprio usuário.');
} elseif ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() <= 1) {
    flash('error', 'O sistema precisa ter pelo menos um usuário.');
} else {
    $delete = $pdo->prepare('DELETE FROM usuarios WHERE ID = ?');
    $delete->execute([$id]);
    flash('success', "Usuário '$nome' removido. Os pedidos registrados por ele foram mantidos.");
}

header('Location: usuarios.php');
exit;
