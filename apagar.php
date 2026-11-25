<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lixeira_funcoes.php';

// A confirmação é feita antes, no modal (js/app.js), que envia um POST com token CSRF.
// Só administradores apagam. Uma cópia do cliente vai para a lixeira (lixeira.php),
// de onde pode ser restaurado. Depois, o usuário volta para a lista, onde a mensagem aparece como toast.

exigir_post('clientes.php');
exigir_admin('clientes.php');

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
    $pdo->beginTransaction();
    lixeira_guardar($pdo, 'cliente', $id, "Cliente '{$cliente['NOME']}'", lixeira_copia($pdo, 'cliente', $id));
    $pdo->prepare('DELETE FROM clientes WHERE ID = ?')->execute([$id]);
    $pdo->commit();
    registrar_atividade($pdo, 'APAGOU', "Apagou o cliente '{$cliente['NOME']}' (foi para a lixeira)", 'cliente', $id);
    flash('success', "Cliente '{$cliente['NOME']}' movido para a lixeira.");
} else {
    flash('error', 'Cliente não encontrado!');
}

header('Location: clientes.php');
exit;