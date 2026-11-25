<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';
require_once 'lixeira_funcoes.php';

// Apaga um pedido. Os itens e as parcelas são apagados automaticamente pelo banco
// (ON DELETE CASCADE). Antes, devolve o estoque (só pedidos de venda seguram estoque:
// orçamentos nunca baixaram e cancelados já devolveram) e guarda uma cópia na lixeira.
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
    $doc = $status === 'ORCAMENTO' ? 'orçamento' : 'pedido';
    $pdo->beginTransaction();
    lixeira_guardar($pdo, 'pedido', $id, ucfirst($doc) . " #$id", lixeira_copia($pdo, 'pedido', $id));
    if (status_eh_venda($status)) {
        devolver_estoque($pdo, $id, "Pedido #$id apagado");
    }
    $pdo->prepare('DELETE FROM pedidos WHERE ID = ?')->execute([$id]);
    $pdo->commit();
    registrar_atividade($pdo, 'APAGOU', "Apagou o $doc #$id (foi para a lixeira)", 'pedido', $id);
    flash('success', ucfirst($doc) . " #$id movido para a lixeira. Itens e parcelas saíram junto (ON DELETE CASCADE).");
}

header('Location: pedidos.php');
exit;
