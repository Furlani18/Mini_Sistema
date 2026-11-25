<?php
require_once __DIR__ . '/ui.php';

// Movimentação de estoque. TODA alteração de estoque passa por movimentar_estoque(),
// que atualiza produtos.ESTOQUE e grava uma linha em movimentos_estoque (histórico).
//
// Regras dos pedidos: só os status de venda (ABERTO, PAGO, ENVIADO — ver STATUS_VENDA
// em ui.php) seguram estoque. Entrar num deles (criar pedido, aprovar orçamento,
// reabrir cancelado) baixa o estoque; sair (cancelar, apagar) devolve; editar os itens
// de um pedido aberto movimenta só a diferença. Orçamentos não mexem no estoque.
// As funções devem ser chamadas dentro de uma transação ($pdo->beginTransaction()).

/** Tipos de movimento (valor no banco => rótulo na tela). */
function tipos_movimento(): array
{
    return [
        'CADASTRO'  => 'Cadastro',
        'ENTRADA'   => 'Entrada',
        'SAIDA'     => 'Saída',
        'VENDA'     => 'Venda',
        'DEVOLUCAO' => 'Devolução',
        'AJUSTE'    => 'Ajuste',
    ];
}

/**
 * Soma $quantidade (positiva = entrada, negativa = saída) ao estoque do produto e
 * registra no histórico. Retorna null se deu certo, ou a mensagem de erro quando
 * a saída deixaria o estoque negativo — nesse caso NADA é alterado.
 */
function movimentar_estoque(
    PDO $pdo, int $produtoId, int $quantidade, string $tipo, string $motivo,
    ?int $pedidoId = null, ?int $fornecedorId = null, ?float $custoUnitario = null
): ?string {
    if ($quantidade === 0) {
        return null;
    }

    // FOR UPDATE trava a linha do produto até o fim da transação (evita vender o mesmo item duas vezes)
    $stmt = $pdo->prepare('SELECT NOME, ESTOQUE, CUSTO FROM produtos WHERE ID = ? FOR UPDATE');
    $stmt->execute([$produtoId]);
    $produto = $stmt->fetch();
    if (!$produto) {
        return 'Produto #' . $produtoId . ' não existe mais';
    }

    $novoEstoque = (int)$produto['ESTOQUE'] + $quantidade;
    if ($novoEstoque < 0) {
        return "{$produto['NOME']} (disponível: {$produto['ESTOQUE']})";
    }

    // Entrada com custo informado: recalcula o custo médio do produto
    $novoCusto = $produto['CUSTO'] !== null ? (float)$produto['CUSTO'] : null;
    if ($quantidade > 0 && $custoUnitario !== null) {
        $novoCusto = custo_medio((int)$produto['ESTOQUE'], $novoCusto, $quantidade, $custoUnitario);
    }

    $pdo->prepare('UPDATE produtos SET ESTOQUE = ?, CUSTO = ? WHERE ID = ?')->execute([$novoEstoque, $novoCusto, $produtoId]);
    $pdo->prepare(
        'INSERT INTO movimentos_estoque
            (PRODUTO_ID, PEDIDO_ID, USUARIO_ID, FORNECEDOR_ID, TIPO, QUANTIDADE, CUSTO_UNITARIO, ESTOQUE_APOS, MOTIVO)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $produtoId, $pedidoId, $_SESSION['usuario_id'] ?? null, $fornecedorId, $tipo, $quantidade,
        $custoUnitario, $novoEstoque, mb_substr($motivo, 0, 255),
    ]);

    return null;
}

/** Quantidade de cada produto do pedido: [produto_id => quantidade]. */
function itens_do_pedido(PDO $pdo, int $pedidoId): array
{
    $stmt = $pdo->prepare('SELECT PRODUTO_ID, QUANTIDADE FROM itens_pedido WHERE PEDIDO_ID = ?');
    $stmt->execute([$pedidoId]);
    return array_map('intval', array_column($stmt->fetchAll(), 'QUANTIDADE', 'PRODUTO_ID'));
}

/** Devolve ao estoque as quantidades de todos os itens do pedido. */
function devolver_estoque(PDO $pdo, int $pedidoId, string $motivo): void
{
    foreach (itens_do_pedido($pdo, $pedidoId) as $produtoId => $qtd) {
        movimentar_estoque($pdo, $produtoId, $qtd, 'DEVOLUCAO', $motivo, $pedidoId);
    }
}

/**
 * Baixa do estoque as quantidades dos itens do pedido.
 * Retorna a lista de produtos sem estoque suficiente (vazia = deu certo).
 * Se houver falta, a transação deve ser desfeita ($pdo->rollBack()).
 */
function baixar_estoque(PDO $pdo, int $pedidoId, string $motivo): array
{
    $faltando = [];
    foreach (itens_do_pedido($pdo, $pedidoId) as $produtoId => $qtd) {
        $erro = movimentar_estoque($pdo, $produtoId, -$qtd, 'VENDA', $motivo, $pedidoId);
        if ($erro !== null) {
            $faltando[] = $erro;
        }
    }
    return $faltando;
}

/**
 * Pedido editado: movimenta só a diferença entre os itens antigos e os novos
 * (ex.: de 3 para 5 unidades baixa mais 2; produto removido volta inteiro ao estoque).
 * Retorna a lista de produtos sem estoque suficiente (vazia = deu certo).
 */
function ajustar_estoque_pedido(PDO $pdo, int $pedidoId, array $antes, array $depois): array
{
    $faltando = [];
    foreach (array_keys($antes + $depois) as $produtoId) {
        $diferenca = ($depois[$produtoId] ?? 0) - ($antes[$produtoId] ?? 0);
        if ($diferenca === 0) {
            continue;
        }
        $erro = movimentar_estoque(
            $pdo, (int)$produtoId, -$diferenca,
            $diferenca > 0 ? 'VENDA' : 'DEVOLUCAO',
            "Pedido #$pedidoId editado", $pedidoId
        );
        if ($erro !== null) {
            $faltando[] = $erro;
        }
    }
    return $faltando;
}

/**
 * Custo médio ponderado depois de uma entrada.
 * Ex.: 10 un. a R$ 5,00 + entrada de 10 un. a R$ 7,00 → R$ 6,00.
 * Sem custo anterior (ou estoque zerado), vale o custo da entrada.
 */
function custo_medio(int $estoqueAtual, ?float $custoAtual, int $quantidadeEntrada, float $custoEntrada): float
{
    if ($custoAtual === null || $estoqueAtual <= 0) {
        return round($custoEntrada, 2);
    }
    $total = $estoqueAtual * $custoAtual + $quantidadeEntrada * $custoEntrada;
    return round($total / ($estoqueAtual + $quantidadeEntrada), 2);
}

/**
 * Mudança de status de um pedido: baixa ou devolve o estoque conforme o pedido
 * passa a segurar (ou deixa de segurar) mercadoria. Ex.: orçamento → aberto baixa;
 * aberto → cancelado devolve; pago → enviado não mexe.
 * Retorna a lista de produtos sem estoque suficiente (vazia = deu certo).
 */
function estoque_mudanca_status(PDO $pdo, int $pedidoId, string $de, string $para): array
{
    $antes = status_eh_venda($de);
    $depois = status_eh_venda($para);
    if ($antes === $depois) {
        return [];
    }
    $rotulo = status_pedidos()[$para] ?? $para;
    if ($depois) {
        $motivo = $de === 'ORCAMENTO' ? "Orçamento #$pedidoId aprovado" : "Pedido #$pedidoId reaberto ($rotulo)";
        return baixar_estoque($pdo, $pedidoId, $motivo);
    }
    devolver_estoque($pdo, $pedidoId, "Pedido #$pedidoId " . ($para === 'CANCELADO' ? 'cancelado' : "virou $rotulo"));
    return [];
}
