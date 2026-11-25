<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Apaga um fornecedor. As entradas de estoque dele continuam no histórico, sem o
// fornecedor (FK_MOVIMENTOS_FORNECEDOR com ON DELETE SET NULL).
// Só por POST (botão do modal) e só administradores.

exigir_post('fornecedores.php');
exigir_admin('fornecedores.php');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare('SELECT NOME FROM fornecedores WHERE ID = ?');
$stmt->execute([$id]);
$nome = $stmt->fetchColumn();

if ($nome === false) {
    flash('error', 'Fornecedor não encontrado!');
} else {
    $pdo->prepare('DELETE FROM fornecedores WHERE ID = ?')->execute([$id]);
    registrar_atividade($pdo, 'APAGOU', "Apagou o fornecedor '$nome'", 'fornecedor', $id);
    flash('success', "Fornecedor '$nome' removido. As compras dele continuam no histórico de estoque.");
}

header('Location: fornecedores.php');
exit;
