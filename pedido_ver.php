<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';

// Detalhes de um pedido: dados do pedido + cliente + usuário (JOINs) e a lista
// de itens com os produtos (itens_pedido ⨝ produtos). Também permite mudar o status.

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$buscarPedido = $pdo->prepare(
    'SELECT p.ID, p.STATUS, p.OBSERVACAO, p.CRIADO_EM,
            c.ID AS CLIENTE_ID, c.NOME AS CLIENTE, c.EMAIL AS CLIENTE_EMAIL, c.TELEFONE AS CLIENTE_TELEFONE,
            u.NOME AS USUARIO
       FROM pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
      WHERE p.ID = ?'
);
$buscarPedido->execute([$id]);
$pedido = $buscarPedido->fetch();

if (!$pedido) {
    flash('error', 'Pedido não encontrado.');
    header('Location: pedidos.php');
    exit;
}

// Mudança de status (cancelar devolve o estoque; sair de "cancelado" baixa de novo)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo = (string)($_POST['status'] ?? '');
    $atual = $pedido['STATUS'];

    if (!isset(status_pedidos()[$novo])) {
        flash('error', 'Status inválido.');
    } elseif ($novo !== $atual) {
        $pdo->beginTransaction();
        $faltando = [];
        if ($novo === 'CANCELADO') {
            devolver_estoque($pdo, $id);
        } elseif ($atual === 'CANCELADO') {
            $faltando = baixar_estoque($pdo, $id);
        }

        if ($faltando) {
            $pdo->rollBack();
            flash('error', 'Não foi possível reabrir o pedido. Estoque insuficiente para: ' . implode(', ', $faltando) . '.');
        } else {
            $pdo->prepare('UPDATE pedidos SET STATUS = ? WHERE ID = ?')->execute([$novo, $id]);
            $pdo->commit();
            flash('success', 'Status alterado para "' . status_pedidos()[$novo] . '".');
        }
    }
    header('Location: pedido_ver.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pr.ID AS PRODUTO_ID, pr.NOME AS PRODUTO, i.QUANTIDADE, i.PRECO_UNITARIO,
            i.QUANTIDADE * i.PRECO_UNITARIO AS SUBTOTAL
       FROM itens_pedido i
      INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
      WHERE i.PEDIDO_ID = ?
      ORDER BY pr.NOME'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();
$total = array_sum(array_column($itens, 'SUBTOTAL'));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido['ID']; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Pedidos' => 'pedidos.php', 'Pedido #' . $pedido['ID'] => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('carrinho'); ?> Pedido <span class="page-title-id">#<?php echo $pedido['ID']; ?></span> <?php echo badge_status($pedido['STATUS']); ?></h2>
                </div>

                <dl class="detalhes">
                    <div>
                        <dt>Cliente <small class="text-muted">(tabela clientes)</small></dt>
                        <dd>
                            <a href="pedidos.php?cliente=<?php echo $pedido['CLIENTE_ID']; ?>" class="text-link"><?php echo htmlspecialchars($pedido['CLIENTE']); ?></a><br>
                            <span class="text-muted"><?php echo htmlspecialchars($pedido['CLIENTE_EMAIL']); ?><?php echo $pedido['CLIENTE_TELEFONE'] ? ' · ' . htmlspecialchars($pedido['CLIENTE_TELEFONE']) : ''; ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt>Registrado por <small class="text-muted">(tabela usuarios)</small></dt>
                        <dd><?php echo $pedido['USUARIO'] ? htmlspecialchars($pedido['USUARIO']) : '<span class="text-muted">— (usuário removido)</span>'; ?></dd>
                    </div>
                    <div>
                        <dt>Data</dt>
                        <dd><?php echo date('d/m/Y H:i', strtotime($pedido['CRIADO_EM'])); ?></dd>
                    </div>
                    <div>
                        <dt>Observação</dt>
                        <dd><?php echo $pedido['OBSERVACAO'] ? htmlspecialchars($pedido['OBSERVACAO']) : '<span class="text-muted">—</span>'; ?></dd>
                    </div>
                </dl>

                <h3 class="section-title"><?php echo icone('pacote'); ?> Itens <small class="text-muted">(tabelas itens_pedido + produtos)</small></h3>
                <div class="table-responsive">
                    <table class="client-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="num">Quantidade</th>
                                <th class="num">Preço unitário</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td class="cell-client">
                                        <span class="client-name"><?php echo htmlspecialchars($item['PRODUTO']); ?></span>
                                        <span class="client-id">#<?php echo $item['PRODUTO_ID']; ?></span>
                                    </td>
                                    <td class="num" data-label="Quantidade"><?php echo (int)$item['QUANTIDADE']; ?></td>
                                    <td class="num" data-label="Preço unitário"><?php echo moeda((float)$item['PRECO_UNITARIO']); ?></td>
                                    <td class="num" data-label="Subtotal"><?php echo moeda((float)$item['SUBTOTAL']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$itens): ?>
                                <tr><td colspan="4" class="text-muted">Este pedido não tem itens.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="num">Total</th>
                                <th class="num total-valor"><?php echo moeda((float)$total); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="status-form">
                    <label for="status">Alterar status:</label>
                    <select id="status" name="status">
                        <?php foreach (status_pedidos() as $valor => $rotulo): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === $pedido['STATUS'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo icone('salvar'); ?> Salvar</button>
                </form>

                <div class="form-actions">
                    <a href="pedidos.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Pedidos</a>
                    <a href="pedido_apagar.php?id=<?php echo $pedido['ID']; ?>" class="btn btn-danger-outline"
                       data-confirmar-exclusao="o pedido #<?php echo $pedido['ID']; ?>"><?php echo icone('lixeira'); ?> Apagar Pedido</a>
                </div>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
