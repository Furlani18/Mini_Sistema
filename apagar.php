<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// A confirmação é feita antes, no modal (js/app.js). Aqui o cliente é removido
// e o usuário volta para a lista, onde a mensagem aparece como toast.

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare(
    'SELECT c.NOME, COUNT(p.ID) AS PEDIDOS
       FROM clientes c
       LEFT JOIN pedidos p ON p.CLIENTE_ID = c.ID
      WHERE c.ID = ?
      GROUP BY c.ID, c.NOME'
);
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if ($cliente && $cliente['PEDIDOS'] > 0) {
    // A chave estrangeira FK_PEDIDOS_CLIENTE (ON DELETE RESTRICT) também bloquearia no banco
    flash('error', "O cliente '{$cliente['NOME']}' não pode ser apagado: ele possui {$cliente['PEDIDOS']} pedido(s). Apague os pedidos primeiro.");
} elseif ($cliente) {
    $delete = $pdo->prepare('DELETE FROM clientes WHERE ID = ?');
    $delete->execute([$id]);
    flash('success', "Cliente '{$cliente['NOME']}' removido com sucesso!");
} else {
    flash('error', 'Cliente não encontrado!');
}

header('Location: clientes.php');
exit;