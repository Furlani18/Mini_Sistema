<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';
require_once 'financeiro.php';

// Registro e edição de pedido (mesmo formulário):
//   pedido_novo.php              → novo pedido
//   pedido_novo.php?orcamento=1  → novo orçamento (não baixa estoque)
//   pedido_novo.php?cliente=ID   → novo pedido com o cliente já escolhido
//   pedido_novo.php?id=ID        → editar um orçamento ou pedido ABERTO (itens, cliente, desconto, pagamento)
//
// Grava em 2 tabelas dentro de UMA transação:
//   1) pedidos       → 1 linha (cliente, usuário logado, status, observação, desconto, pagamento)
//   2) itens_pedido  → 1 linha por produto (quantidade + preço do momento)
// e movimenta o estoque (com histórico). Se algo falhar, nada é gravado (rollBack).

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;
$itensAntes = [];      // [produto_id => quantidade] já gravados (edição)
$precosAntes = [];     // [produto_id => preço unitário] já gravados (edição)
$custosAntes = [];     // [produto_id => custo unitário] já gravados (edição)
$situacaoParcelas = ['TOTAL' => 0, 'RECEBIDAS' => 0];
$editaveis = ['ORCAMENTO', 'ABERTO'];

if ($editando) {
    $stmt = $pdo->prepare('SELECT CLIENTE_ID, STATUS, OBSERVACAO, DESCONTO, FORMA_PAGAMENTO FROM pedidos WHERE ID = ?');
    $stmt->execute([$id]);
    $pedido = $stmt->fetch();
    if (!$pedido) {
        flash('error', 'Pedido não encontrado.');
        header('Location: pedidos.php');
        exit;
    }
    if (!in_array($pedido['STATUS'], $editaveis, true)) {
        flash('error', 'Só orçamentos e pedidos com status "Aberto" podem ser editados.');
        header('Location: pedido_ver.php?id=' . $id);
        exit;
    }
    // Parcelas já recebidas "congelam" o valor do pedido
    $stmt = $pdo->prepare('SELECT COUNT(*) AS TOTAL, COUNT(PAGO_EM) AS RECEBIDAS FROM parcelas WHERE PEDIDO_ID = ?');
    $stmt->execute([$id]);
    $situacaoParcelas = $stmt->fetch();
    if ((int)$situacaoParcelas['RECEBIDAS'] > 0) {
        flash('error', 'Este pedido já tem parcelas recebidas e não pode mais ser editado. Estorne os recebimentos para editar.');
        header('Location: pedido_ver.php?id=' . $id);
        exit;
    }
    $stmt = $pdo->prepare('SELECT PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO, CUSTO_UNITARIO FROM itens_pedido WHERE PEDIDO_ID = ? ORDER BY ID');
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll() as $item) {
        $itensAntes[(int)$item['PRODUTO_ID']] = (int)$item['QUANTIDADE'];
        $precosAntes[(int)$item['PRODUTO_ID']] = (float)$item['PRECO_UNITARIO'];
        $custosAntes[(int)$item['PRODUTO_ID']] = $item['CUSTO_UNITARIO'];
    }
}

$clientes = $pdo->query('SELECT ID, NOME, EMAIL FROM clientes ORDER BY NOME')->fetchAll();
$produtos = $pdo->query('SELECT ID, NOME, PRECO, ESTOQUE, SKU, CODIGO_BARRAS FROM produtos ORDER BY NOME')->fetchAll();
$statusNovos = array_diff_key(status_pedidos(), ['CANCELADO' => true]);

$erros = [];
if ($editando) {
    $valores = [
        'cliente_id'      => (int)$pedido['CLIENTE_ID'],
        'status'          => $pedido['STATUS'],
        'observacao'      => $pedido['OBSERVACAO'] ?? '',
        'desconto'        => (float)$pedido['DESCONTO'] > 0 ? number_format((float)$pedido['DESCONTO'], 2, ',', '.') : '',
        'forma_pagamento' => $pedido['FORMA_PAGAMENTO'] ?? '',
    ];
    $linhas = [];
    foreach ($itensAntes as $produtoId => $qtd) {
        $linhas[] = ['produto_id' => $produtoId, 'quantidade' => $qtd];
    }
} else {
    $valores = [
        'cliente_id'      => isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0,
        'status'          => isset($_GET['orcamento']) ? 'ORCAMENTO' : 'ABERTO',
        'observacao'      => '',
        'desconto'        => '',
        'forma_pagamento' => '',
    ];
    $linhas = [];
}
if (!$linhas) {
    $linhas = [['produto_id' => 0, 'quantidade' => 1]];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'cliente_id'      => (int)($_POST['cliente_id'] ?? 0),
        'status'          => $editando ? $pedido['STATUS'] : (string)($_POST['status'] ?? 'ABERTO'),
        'observacao'      => trim((string)($_POST['observacao'] ?? '')),
        'desconto'        => trim((string)($_POST['desconto'] ?? '')),
        'forma_pagamento' => (string)($_POST['forma_pagamento'] ?? ''),
    ];

    // Junta as linhas do formulário; o mesmo produto em 2 linhas vira 1 item com a soma
    $linhas = [];
    $itens = [];
    $produtoIds = (array)($_POST['produto_id'] ?? []);
    $quantidades = (array)($_POST['quantidade'] ?? []);
    foreach ($produtoIds as $n => $produtoId) {
        $produtoId = (int)$produtoId;
        $qtd = (int)($quantidades[$n] ?? 0);
        $linhas[] = ['produto_id' => $produtoId, 'quantidade' => $qtd];
        if ($produtoId <= 0) {
            continue;
        }
        if ($qtd <= 0) {
            $erros['itens'] = 'Todas as quantidades devem ser maiores que zero.';
        }
        $itens[$produtoId] = ($itens[$produtoId] ?? 0) + $qtd;
    }
    if (!$linhas) {
        $linhas = [['produto_id' => 0, 'quantidade' => 1]];
    }

    $idsClientes = array_map('intval', array_column($clientes, 'ID'));
    if (!in_array($valores['cliente_id'], $idsClientes, true)) {
        $erros['cliente_id'] = 'Selecione o cliente do pedido.';
    }
    if (!isset($statusNovos[$valores['status']])) {
        $erros['status'] = 'Status inválido.';
    }
    if ($valores['forma_pagamento'] !== '' && !isset(formas_pagamento()[$valores['forma_pagamento']])) {
        $erros['forma_pagamento'] = 'Forma de pagamento inválida.';
    }
    $desconto = $valores['desconto'] === '' ? 0.0 : converter_moeda($valores['desconto']);
    if ($desconto === null || $desconto > VALOR_MAXIMO) {
        $erros['desconto'] = 'Informe um desconto válido, por exemplo 50,00.';
    }
    if (!$itens && !isset($erros['itens'])) {
        $erros['itens'] = 'Adicione pelo menos um produto ao pedido.';
    }

    // O desconto não pode passar do valor dos itens (preço já gravado ou preço atual)
    if (!$erros) {
        $precosAtuais = array_map('floatval', array_column($produtos, 'PRECO', 'ID'));
        $subtotal = 0.0;
        foreach ($itens as $produtoId => $qtd) {
            $subtotal += $qtd * ($precosAntes[$produtoId] ?? $precosAtuais[$produtoId] ?? 0);
        }
        if ($desconto > $subtotal) {
            $erros['desconto'] = 'O desconto (' . moeda($desconto) . ') não pode ser maior que o valor dos itens (' . moeda($subtotal) . ').';
        }
    }

    if (!$erros) {
        $pdo->beginTransaction();
        try {
            $dadosPedido = [$valores['cliente_id'], $valores['observacao'] ?: null, $desconto, $valores['forma_pagamento'] ?: null];

            if ($editando) {
                // Trava o pedido e confere de novo: alguém pode ter mudado o status nesse meio-tempo
                $stmt = $pdo->prepare('SELECT STATUS FROM pedidos WHERE ID = ? FOR UPDATE');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() !== $pedido['STATUS']) {
                    throw new RuntimeException('O status deste pedido mudou enquanto você editava. Abra-o de novo.');
                }
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM parcelas WHERE PEDIDO_ID = ? AND PAGO_EM IS NOT NULL');
                $stmt->execute([$id]);
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException('Uma parcela deste pedido foi recebida enquanto você editava. Estorne o recebimento para editar.');
                }
                // O valor pode mudar: as parcelas (nenhuma recebida) são apagadas para serem refeitas
                $pdo->prepare('DELETE FROM parcelas WHERE PEDIDO_ID = ?')->execute([$id]);
                $pdo->prepare('UPDATE pedidos SET CLIENTE_ID = ?, OBSERVACAO = ?, DESCONTO = ?, FORMA_PAGAMENTO = ? WHERE ID = ?')
                    ->execute([...$dadosPedido, $id]);
                $pdo->prepare('DELETE FROM itens_pedido WHERE PEDIDO_ID = ?')->execute([$id]);
                $pedidoId = $id;
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO pedidos (CLIENTE_ID, OBSERVACAO, DESCONTO, FORMA_PAGAMENTO, USUARIO_ID, STATUS) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([...$dadosPedido, $_SESSION['usuario_id'], $valores['status']]);
                $pedidoId = (int)$pdo->lastInsertId();
            }

            // Produto que já estava no pedido mantém o preço (e o custo) da venda original;
            // produto novo usa o preço e o custo atuais (copiados de produtos — se mudarem depois, o pedido não muda)
            $itemComPreco = $pdo->prepare(
                'INSERT INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO, CUSTO_UNITARIO) VALUES (?, ?, ?, ?, ?)'
            );
            $itemPrecoAtual = $pdo->prepare(
                'INSERT INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO, CUSTO_UNITARIO)
                 SELECT ?, ID, ?, PRECO, CUSTO FROM produtos WHERE ID = ?'
            );
            foreach ($itens as $produtoId => $qtd) {
                if (isset($precosAntes[$produtoId])) {
                    $itemComPreco->execute([$pedidoId, $produtoId, $qtd, $precosAntes[$produtoId], $custosAntes[$produtoId] ?? null]);
                } else {
                    $itemPrecoAtual->execute([$pedidoId, $qtd, $produtoId]);
                    if ($itemPrecoAtual->rowCount() === 0) {
                        throw new RuntimeException('Um dos produtos escolhidos não existe mais.');
                    }
                }
            }

            // Orçamento não mexe no estoque; pedido de venda baixa (ou ajusta a diferença na edição)
            $faltando = [];
            if (status_eh_venda($valores['status'])) {
                $faltando = $editando
                    ? ajustar_estoque_pedido($pdo, $pedidoId, $itensAntes, $itens)
                    : baixar_estoque($pdo, $pedidoId, "Pedido #$pedidoId");
            }
            if ($faltando) {
                throw new RuntimeException('Estoque insuficiente para: ' . implode(', ', $faltando) . '.');
            }

            $pdo->commit();
            $doc = $valores['status'] === 'ORCAMENTO' ? 'orçamento' : 'pedido';
            registrar_atividade($pdo, $editando ? 'ALTEROU' : 'CRIOU',
                ($editando ? 'Editou' : 'Registrou') . " o $doc #$pedidoId", 'pedido', $pedidoId);
            $aviso = (int)$situacaoParcelas['TOTAL'] > 0 ? ' As parcelas foram apagadas: gere-as de novo com o valor atualizado.' : '';
            $Doc = $doc === 'orçamento' ? 'Orçamento' : 'Pedido';
            flash('success', ($editando ? "$Doc #$pedidoId atualizado!" : "$Doc #$pedidoId registrado com sucesso!") . $aviso);
            header('Location: pedido_ver.php?id=' . $pedidoId);
            exit;
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $erros['itens'] = $e->getMessage();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

$ehOrcamento = $valores['status'] === 'ORCAMENTO';
$titulo = $editando
    ? ($ehOrcamento ? "Editar Orçamento #$id" : "Editar Pedido #$id")
    : ($ehOrcamento ? 'Novo Orçamento' : 'Novo Pedido');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php
        $breadcrumb = $editando
            ? ['Pedidos' => 'pedidos.php', ($ehOrcamento ? 'Orçamento' : 'Pedido') . " #$id" => "pedido_ver.php?id=$id", 'Editar' => null]
            : ['Pedidos' => 'pedidos.php', $titulo => null];
        include __DIR__ . '/header.php';
        ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone($editando ? 'editar' : 'carrinho'); ?> <?php echo $titulo; ?></h2>

                <?php if (!$clientes || !$produtos): ?>
                    <div class="alert alert-warning" role="alert">
                        <?php echo icone('alerta'); ?>
                        <span>
                            Para registrar um pedido é preciso ter pelo menos um
                            <?php if (!$clientes): ?><a href="cadastro.php" class="text-link">cliente cadastrado</a><?php endif; ?>
                            <?php if (!$clientes && !$produtos): ?> e um <?php endif; ?>
                            <?php if (!$produtos): ?><a href="produto_cadastro.php" class="text-link">produto cadastrado</a><?php endif; ?>.
                        </span>
                    </div>
                <?php else: ?>

                <?php if ($editando): ?>
                    <div class="alert alert-info">
                        <?php echo icone('alerta'); ?>
                        <span><?php echo $ehOrcamento
                            ? 'Orçamentos não reservam estoque. Produtos que já estavam no orçamento mantêm o preço original.'
                            : 'Produtos que já estavam no pedido mantêm o preço da venda original. O estoque é ajustado só pela diferença.'; ?>
                            <?php if ((int)$situacaoParcelas['TOTAL'] > 0): ?> As parcelas atuais serão apagadas ao salvar, para serem refeitas com o novo valor.<?php endif; ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span><?php echo htmlspecialchars($erros['itens'] ?? 'Corrija os campos destacados abaixo.'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formPedido" novalidate>
                    <?php echo csrf_campo(); ?>
                    <div class="form-grid">
                        <div class="form-group<?php echo isset($erros['cliente_id']) ? ' has-error' : ''; ?>">
                            <label for="cliente_id">Cliente <span class="required" aria-hidden="true">*</span></label>
                            <select id="cliente_id" name="cliente_id" required>
                                <option value="">Selecione…</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo $c['ID']; ?>" <?php echo (int)$c['ID'] === $valores['cliente_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['NOME'] . ' — ' . $c['EMAIL']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['cliente_id'] ?? ''); ?></span></small>
                        </div>

                        <?php if (!$editando): ?>
                            <div class="form-group<?php echo isset($erros['status']) ? ' has-error' : ''; ?>">
                                <label for="status">Status</label>
                                <select id="status" name="status" aria-describedby="status-ajuda" data-status-pedido>
                                    <?php foreach ($statusNovos as $valor => $rotulo): ?>
                                        <option value="<?php echo $valor; ?>" <?php echo $valor === $valores['status'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="field-help" id="status-ajuda">"Orçamento" não baixa o estoque nem conta como venda.</small>
                                <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['status'] ?? ''); ?></span></small>
                            </div>
                        <?php endif; ?>

                        <div class="form-group<?php echo isset($erros['forma_pagamento']) ? ' has-error' : ''; ?>">
                            <label for="forma_pagamento">Forma de pagamento</label>
                            <select id="forma_pagamento" name="forma_pagamento">
                                <option value="">Não informada</option>
                                <?php foreach (formas_pagamento() as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo $valor === $valores['forma_pagamento'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['forma_pagamento'] ?? ''); ?></span></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observacao">Observação</label>
                        <input type="text" id="observacao" name="observacao" maxlength="255" placeholder="Opcional"
                               value="<?php echo htmlspecialchars($valores['observacao']); ?>">
                    </div>

                    <fieldset class="itens-pedido">
                        <legend>Itens do pedido <span class="required" aria-hidden="true">*</span></legend>

                        <div class="leitor-codigo">
                            <label for="buscaCodigo"><?php echo icone('codigo-barras'); ?> Adicionar por código</label>
                            <input type="text" id="buscaCodigo" placeholder="SKU ou código de barras + Enter" autocomplete="off"
                                   aria-describedby="buscaCodigoStatus" data-busca-codigo>
                            <small id="buscaCodigoStatus" class="leitor-status" aria-live="polite"></small>
                        </div>

                        <div id="linhasItens">
                            <?php foreach ($linhas as $linha): ?>
                                <div class="item-linha" data-item-linha>
                                    <select name="produto_id[]" aria-label="Produto" data-produto>
                                        <option value="">Selecione o produto…</option>
                                        <?php foreach ($produtos as $p): ?>
                                            <?php
                                            $pid = (int)$p['ID'];
                                            // Na edição: preço original e estoque somando o que já está reservado neste pedido
                                            $preco = $precosAntes[$pid] ?? (float)$p['PRECO'];
                                            $disponivel = (int)$p['ESTOQUE'] + ($itensAntes[$pid] ?? 0);
                                            ?>
                                            <option value="<?php echo $pid; ?>" data-preco="<?php echo $preco; ?>"
                                                data-codigos="<?php echo htmlspecialchars(trim(mb_strtolower((string)$p['SKU']) . ' ' . (string)$p['CODIGO_BARRAS'])); ?>"
                                                <?php echo $pid === $linha['produto_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['NOME']) . ($p['SKU'] ? ' [' . htmlspecialchars($p['SKU']) . ']' : '') . ' — ' . moeda($preco) . ' (disponível: ' . $disponivel . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" name="quantidade[]" min="1" step="1" value="<?php echo max(1, $linha['quantidade']); ?>"
                                           aria-label="Quantidade" data-quantidade>
                                    <span class="item-subtotal" data-subtotal>—</span>
                                    <button type="button" class="btn-icon btn-icon-danger" title="Remover item"
                                            aria-label="Remover item" data-remover-item><?php echo icone('lixeira'); ?></button>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="itens-rodape">
                            <button type="button" class="btn btn-outline btn-sm" id="adicionarItem"><?php echo icone('plus'); ?> Adicionar produto</button>
                            <div class="itens-resumo">
                                <div class="form-group desconto-campo<?php echo isset($erros['desconto']) ? ' has-error' : ''; ?>">
                                    <label for="desconto">Desconto (R$)</label>
                                    <input type="text" id="desconto" name="desconto" inputmode="numeric" placeholder="0,00"
                                           data-mascara-moeda autocomplete="off" value="<?php echo htmlspecialchars($valores['desconto']); ?>">
                                    <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['desconto'] ?? ''); ?></span></small>
                                </div>
                                <dl class="itens-totais">
                                    <div><dt>Subtotal</dt><dd id="subtotalPedido">R$ 0,00</dd></div>
                                    <div><dt>Desconto</dt><dd id="descontoPedido">− R$ 0,00</dd></div>
                                    <div class="itens-total"><dt>Total</dt><dd><strong id="totalPedido">R$ 0,00</strong></dd></div>
                                </dl>
                            </div>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <span data-rotulo-salvar><?php echo $editando ? 'Salvar Alterações' : ($ehOrcamento ? 'Salvar Orçamento' : 'Registrar Pedido'); ?></span></button>
                        <a href="<?php echo $editando ? 'pedido_ver.php?id=' . $id : 'pedidos.php'; ?>" class="btn btn-outline">
                            <?php echo $editando ? icone('fechar') . ' Cancelar' : icone('lista') . ' Ver Pedidos'; ?></a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="js/moeda.js"></script>
    <script src="js/pedido.js"></script>
</body>
</html>
