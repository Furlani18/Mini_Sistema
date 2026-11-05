<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// A confirmação é feita antes, no modal (js/app.js). Aqui o cliente é removido
// e o usuário volta para a lista, onde a mensagem aparece como toast.

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT NOME FROM clientes WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if ($cliente) {
    $delete = $pdo->prepare('DELETE FROM clientes WHERE ID = ?');
    $delete->execute([$id]);
    flash('success', "Cliente '{$cliente['NOME']}' removido com sucesso!");
} else {
    flash('error', 'Cliente não encontrado!');
}

header('Location: clientes.php');
exit;
