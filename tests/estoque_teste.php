<?php
// Estoque, orçamentos e lixeira no banco de verdade. Cada teste roda numa transação
// desfeita no final (no_banco), então o banco não é alterado.

/** Cria um produto de teste com estoque e custo; devolve o ID. */
function produto_teste(PDO $pdo, int $estoque, ?float $custo = null): int
{
    $pdo->prepare('INSERT INTO produtos (NOME, PRECO, CUSTO, ESTOQUE) VALUES (?, 100, ?, 0)')
        ->execute(['Produto de teste ' . bin2hex(random_bytes(4)), $custo]);
    $id = (int)$pdo->lastInsertId();
    movimentar_estoque($pdo, $id, $estoque, 'CADASTRO', 'Teste');
    return $id;
}

function estoque_de(PDO $pdo, int $produtoId): int
{
    $stmt = $pdo->prepare('SELECT ESTOQUE FROM produtos WHERE ID = ?');
    $stmt->execute([$produtoId]);
    return (int)$stmt->fetchColumn();
}

/** Cria cliente + pedido com 1 item do produto; devolve o ID do pedido. */
function pedido_teste(PDO $pdo, int $produtoId, int $quantidade, string $status): int
{
    $pdo->prepare('INSERT INTO clientes (NOME, EMAIL) VALUES (?, ?)')
        ->execute(['Cliente de teste', 'teste.' . bin2hex(random_bytes(6)) . '@exemplo.invalid']);
    $clienteId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO pedidos (CLIENTE_ID, STATUS) VALUES (?, ?)')->execute([$clienteId, $status]);
    $pedidoId = (int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO) VALUES (?, ?, ?, 100)')
        ->execute([$pedidoId, $produtoId, $quantidade]);
    return $pedidoId;
}

teste('Saída maior que o estoque é recusada e nada muda', function () {
    no_banco(function (PDO $pdo) {
        $id = produto_teste($pdo, 3);
        verdadeiro(movimentar_estoque($pdo, $id, -5, 'SAIDA', 'Teste') !== null, 'deveria retornar erro');
        igual(3, estoque_de($pdo, $id));
    });
});

teste('Entrada com custo recalcula o custo médio e fica no histórico', function () {
    no_banco(function (PDO $pdo) {
        $id = produto_teste($pdo, 10, 5.00);
        igual(null, movimentar_estoque($pdo, $id, 10, 'ENTRADA', 'NF 1', null, null, 7.00));
        $stmt = $pdo->prepare('SELECT CUSTO, ESTOQUE FROM produtos WHERE ID = ?');
        $stmt->execute([$id]);
        igual(['CUSTO' => '6.00', 'ESTOQUE' => 20], $stmt->fetch());
        $stmt = $pdo->prepare("SELECT CUSTO_UNITARIO FROM movimentos_estoque WHERE PRODUTO_ID = ? AND TIPO = 'ENTRADA'");
        $stmt->execute([$id]);
        igual('7.00', $stmt->fetchColumn());
    });
});

teste('Orçamento não baixa estoque; aprovar baixa; cancelar devolve', function () {
    no_banco(function (PDO $pdo) {
        $produto = produto_teste($pdo, 10);
        $pedido = pedido_teste($pdo, $produto, 4, 'ORCAMENTO');
        igual(10, estoque_de($pdo, $produto), 'orçamento criado');

        igual([], estoque_mudanca_status($pdo, $pedido, 'ORCAMENTO', 'ABERTO'));
        igual(6, estoque_de($pdo, $produto), 'orçamento aprovado');

        igual([], estoque_mudanca_status($pdo, $pedido, 'ABERTO', 'PAGO'));
        igual(6, estoque_de($pdo, $produto), 'aberto → pago não mexe');

        estoque_mudanca_status($pdo, $pedido, 'PAGO', 'CANCELADO');
        igual(10, estoque_de($pdo, $produto), 'cancelado devolve');
    });
});

teste('Aprovar orçamento sem estoque suficiente informa o produto', function () {
    no_banco(function (PDO $pdo) {
        $produto = produto_teste($pdo, 2);
        $pedido = pedido_teste($pdo, $produto, 5, 'ORCAMENTO');
        $faltando = estoque_mudanca_status($pdo, $pedido, 'ORCAMENTO', 'ABERTO');
        igual(1, count($faltando));
        verdadeiro(str_contains($faltando[0], 'disponível: 2'));
    });
});

teste('Pedido quitado muda de Aberto para Pago', function () {
    no_banco(function (PDO $pdo) {
        $pedido = pedido_teste($pdo, produto_teste($pdo, 5), 1, 'ABERTO');
        $inserir = $pdo->prepare('INSERT INTO parcelas (PEDIDO_ID, NUMERO, VALOR, VENCIMENTO, PAGO_EM) VALUES (?, ?, 50, CURDATE(), ?)');
        $inserir->execute([$pedido, 1, date('Y-m-d')]);
        $inserir->execute([$pedido, 2, null]);
        falso(marcar_pedido_pago_se_quitado($pdo, $pedido), 'ainda falta uma parcela');
        $pdo->prepare('UPDATE parcelas SET PAGO_EM = CURDATE() WHERE PEDIDO_ID = ?')->execute([$pedido]);
        verdadeiro(marcar_pedido_pago_se_quitado($pdo, $pedido));
        $stmt = $pdo->prepare('SELECT STATUS FROM pedidos WHERE ID = ?');
        $stmt->execute([$pedido]);
        igual('PAGO', $stmt->fetchColumn());
    });
});

teste('Lixeira: pedido apagado volta com itens, parcelas e estoque baixado', function () {
    no_banco(function (PDO $pdo) {
        $produto = produto_teste($pdo, 10);
        $pedido = pedido_teste($pdo, $produto, 3, 'ABERTO');
        movimentar_estoque($pdo, $produto, -3, 'VENDA', 'Teste', $pedido);
        $pdo->prepare('INSERT INTO parcelas (PEDIDO_ID, NUMERO, VALOR, VENCIMENTO) VALUES (?, 1, 300, CURDATE())')->execute([$pedido]);

        // Apaga como o pedido_apagar.php faz
        lixeira_guardar($pdo, 'pedido', $pedido, "Pedido #$pedido", lixeira_copia($pdo, 'pedido', $pedido));
        devolver_estoque($pdo, $pedido, 'Teste apagado');
        $pdo->prepare('DELETE FROM pedidos WHERE ID = ?')->execute([$pedido]);
        igual(10, estoque_de($pdo, $produto), 'estoque devolvido ao apagar');

        $item = $pdo->query('SELECT * FROM lixeira ORDER BY ID DESC LIMIT 1')->fetch();
        igual(null, lixeira_restaurar($pdo, $item));
        igual(7, estoque_de($pdo, $produto), 'estoque baixado de novo ao restaurar');
        $stmt = $pdo->prepare('SELECT (SELECT COUNT(*) FROM itens_pedido WHERE PEDIDO_ID = ?) + (SELECT COUNT(*) FROM parcelas WHERE PEDIDO_ID = ?)');
        $stmt->execute([$pedido, $pedido]);
        igual(2, (int)$stmt->fetchColumn(), '1 item + 1 parcela');
    });
});

teste('Lixeira: cliente não volta se o email já foi usado por outro', function () {
    no_banco(function (PDO $pdo) {
        $email = 'teste.' . bin2hex(random_bytes(6)) . '@exemplo.invalid';
        $pdo->prepare('INSERT INTO clientes (NOME, EMAIL) VALUES (?, ?)')->execute(['Original', $email]);
        $id = (int)$pdo->lastInsertId();
        lixeira_guardar($pdo, 'cliente', $id, 'Cliente', lixeira_copia($pdo, 'cliente', $id));
        $pdo->prepare('DELETE FROM clientes WHERE ID = ?')->execute([$id]);
        $pdo->prepare('INSERT INTO clientes (NOME, EMAIL) VALUES (?, ?)')->execute(['Outro com o mesmo email', $email]);

        $item = $pdo->query('SELECT * FROM lixeira ORDER BY ID DESC LIMIT 1')->fetch();
        verdadeiro(str_contains((string)lixeira_restaurar($pdo, $item), 'Já existe'));
    });
});
