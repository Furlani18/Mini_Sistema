<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';

// Registro de um novo pedido. Grava em 2 tabelas dentro de UMA transação:
//   1) pedidos       → 1 linha (cliente, usuário logado, status, observação)
//   2) itens_pedido  → 1 linha por produto (quantidade + preço do momento)
// e baixa o estoque em produtos. Se algo falhar, nada é gravado (rollBack).

$clientes = $pdo->query('SELECT ID, NOME, EMAIL FROM clientes ORDER BY NOME')->fetchAll();
$produtos = $pdo->query('SELECT ID, NOME, PRECO, ESTOQUE FROM produtos ORDER BY NOME')->fetchAll();
$statusNovos = array_diff_key(status_pedidos(), ['CANCELADO' => true]);

$erros = [];
$valores = [
    'cliente_id' => isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0,
    'status'     => 'ABERTO',
    'observacao' => '',
];
$linhas = [['produto_id' => 0, 'quantidade' => 1]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
        'status'     => (string)($_POST['status'] ?? 'ABERTO'),
        'observacao' => trim($_POST['observacao'] ?? ''),
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
    if (!$itens && !isset($erros['itens'])) {
        $erros['itens'] = 'Adicione pelo menos um produto ao pedido.';
    }

    if (!$erros) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO pedidos (CLIENTE_ID, USUARIO_ID, STATUS, OBSERVACAO) VALUES (?, ?, ?, ?)');
            $stmt->execute([$valores['cliente_id'], $_SESSION['usuario_id'], $valores['status'], $valores['observacao'] ?: null]);
            $pedidoId = (int)$pdo->lastInsertId();

            // O preço unitário é copiado de produtos: se o preço mudar depois, o pedido não muda
            $item = $pdo->prepare(
                'INSERT INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO)
                 SELECT ?, ID, ?, PRECO FROM produtos WHERE ID = ?'
            );
            foreach ($itens as $produtoId => $qtd) {
                $item->execute([$pedidoId, $qtd, $produtoId]);
                if ($item->rowCount() === 0) {
                    throw new RuntimeException('Um dos produtos escolhidos não existe mais.');
                }
            }

            $faltando = baixar_estoque($pdo, $pedidoId);
            if ($faltando) {
                throw new RuntimeException('Estoque insuficiente para: ' . implode(', ', $faltando) . '.');
            }

            $pdo->commit();
            flash('success', "Pedido #$pedidoId registrado com sucesso!");
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Pedidos' => 'pedidos.php', 'Novo Pedido' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone('carrinho'); ?> Novo Pedido</h2>

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

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span><?php echo htmlspecialchars($erros['itens'] ?? 'Corrija os campos destacados abaixo.'); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="pedido_novo.php" id="formPedido" novalidate>
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

                        <div class="form-group<?php echo isset($erros['status']) ? ' has-error' : ''; ?>">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach ($statusNovos as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo $valor === $valores['status'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['status'] ?? ''); ?></span></small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observacao">Observação</label>
                        <input type="text" id="observacao" name="observacao" maxlength="255" placeholder="Opcional"
                               value="<?php echo htmlspecialchars($valores['observacao']); ?>">
                    </div>

                    <fieldset class="itens-pedido">
                        <legend>Itens do pedido <span class="required" aria-hidden="true">*</span></legend>

                        <div id="linhasItens">
                            <?php foreach ($linhas as $linha): ?>
                                <div class="item-linha" data-item-linha>
                                    <select name="produto_id[]" aria-label="Produto" data-produto>
                                        <option value="">Selecione o produto…</option>
                                        <?php foreach ($produtos as $p): ?>
                                            <option value="<?php echo $p['ID']; ?>" data-preco="<?php echo $p['PRECO']; ?>"
                                                <?php echo (int)$p['ID'] === $linha['produto_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($p['NOME']) . ' — ' . moeda((float)$p['PRECO']) . ' (estoque: ' . (int)$p['ESTOQUE'] . ')'; ?>
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
                            <p class="itens-total">Total: <strong id="totalPedido">R$ 0,00</strong></p>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Registrar Pedido</button>
                        <a href="pedidos.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Pedidos</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="js/pedido.js"></script>
</body>
</html>
