<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Apaga um produto. Se ele já aparece em algum pedido, a chave estrangeira
// FK_ITENS_PRODUTO (ON DELETE RESTRICT) impede a exclusão — avisamos o usuário.

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare(
    'SELECT pr.NOME, COUNT(i.ID) AS USOS
       FROM produtos pr
       LEFT JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
      WHERE pr.ID = ?
      GROUP BY pr.ID, pr.NOME'
);
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    flash('error', 'Produto não encontrado!');
} elseif ($produto['USOS'] > 0) {
    flash('error', "O produto '{$produto['NOME']}' não pode ser apagado: ele aparece em {$produto['USOS']} pedido(s).");
} else {
    $pdo->prepare('DELETE FROM produtos WHERE ID = ?')->execute([$id]);
    flash('success', "Produto '{$produto['NOME']}' removido com sucesso!");
}

header('Location: produtos.php');
exit;
