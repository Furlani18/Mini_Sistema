<?php
// Movimentação de estoque ligada aos pedidos.
// Regras: criar pedido baixa o estoque; cancelar ou apagar pedido devolve o estoque;
// reabrir um pedido cancelado baixa de novo (se houver estoque).
// Todas as funções devem ser chamadas dentro de uma transação ($pdo->beginTransaction()).

/** Devolve ao estoque as quantidades de todos os itens do pedido. */
function devolver_estoque(PDO $pdo, int $pedidoId): void
{
    $stmt = $pdo->prepare(
        'UPDATE produtos pr
           JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
            SET pr.ESTOQUE = pr.ESTOQUE + i.QUANTIDADE
          WHERE i.PEDIDO_ID = ?'
    );
    $stmt->execute([$pedidoId]);
}

/**
 * Baixa do estoque as quantidades dos itens do pedido.
 * Retorna a lista de produtos sem estoque suficiente (vazia = deu certo).
 */
function baixar_estoque(PDO $pdo, int $pedidoId): array
{
    // FOR UPDATE trava as linhas dos produtos até o fim da transação
    $stmt = $pdo->prepare(
        'SELECT pr.NOME, pr.ESTOQUE, i.QUANTIDADE
           FROM itens_pedido i
           JOIN produtos pr ON pr.ID = i.PRODUTO_ID
          WHERE i.PEDIDO_ID = ?
            FOR UPDATE'
    );
    $stmt->execute([$pedidoId]);

    $faltando = [];
    foreach ($stmt->fetchAll() as $item) {
        if ($item['QUANTIDADE'] > $item['ESTOQUE']) {
            $faltando[] = "{$item['NOME']} (disponível: {$item['ESTOQUE']})";
        }
    }
    if ($faltando) {
        return $faltando;
    }

    $pdo->prepare(
        'UPDATE produtos pr
           JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
            SET pr.ESTOQUE = pr.ESTOQUE - i.QUANTIDADE
          WHERE i.PEDIDO_ID = ?'
    )->execute([$pedidoId]);

    return [];
}
