<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';

// Apaga um pedido. Os itens são apagados automaticamente pelo banco
// (FK_ITENS_PEDIDO com ON DELETE CASCADE). Antes, devolve o estoque
// (pedidos cancelados já devolveram o estoque ao serem cancelados).
// Só por POST (botão do modal) e só administradores.

exigir_post('pedidos.php');
exigir_admin('pedidos.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT STATUS FROM pedidos WHERE ID = ?');
$stmt->execute([$id]);
$status = $stmt->fetchColumn();

if ($status === false) {
    flash('error', 'Pedido não encontrado!');
} else {
    $pdo->beginTransaction();
    if ($status !== 'CANCELADO') {
        devolver_estoque($pdo, $id, "Pedido #$id apagado");
    }
    $pdo->prepare('DELETE FROM pedidos WHERE ID = ?')->execute([$id]);
    $pdo->commit();
    registrar_atividade($pdo, 'APAGOU', "Apagou o pedido #$id", 'pedido', $id);
    flash('success', "Pedido #$id removido com sucesso! Os itens foram apagados junto (ON DELETE CASCADE).");
}

header('Location: pedidos.php');
exit;
