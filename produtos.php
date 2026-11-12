<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Lista de produtos com busca instantânea (mesmo formato da lista de clientes).
// LEFT JOIN: mostra também os produtos que nunca foram vendidos (vendidos = 0).
$produtos = $pdo->query(
    'SELECT pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE,
            COALESCE(SUM(i.QUANTIDADE), 0) AS VENDIDOS
       FROM produtos pr
       LEFT JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
      GROUP BY pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE
      ORDER BY pr.ID DESC'
)->fetchAll();
$total = count($produtos);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Produtos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Produtos' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('pacote'); ?> Lista de Produtos</h2>
                    <a href="produto_cadastro.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Produto</a>
                </div>

                <?php if (!$produtos): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('pacote'); ?></span>
                        <p>Nenhum produto cadastrado ainda.</p>
                        <p><a href="produto_cadastro.php" class="text-link">Clique aqui para cadastrar o primeiro produto</a></p>
                    </div>
                <?php else: ?>
                    <div class="search-bar">
                        <label for="buscaProdutos" class="visually-hidden">Buscar produto</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaProdutos" data-busca placeholder="Buscar por nome ou descrição" autocomplete="off">
                        </div>
                        <p class="list-summary" id="resultadoBusca" data-singular="produto" data-plural="produtos" aria-live="polite">
                            <?php echo $total . ($total === 1 ? ' produto' : ' produtos'); ?>
                        </p>
                    </div>

                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="num">Preço</th>
                                    <th class="num">Estoque</th>
                                    <th class="num">Vendidos</th>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <?php $nome = htmlspecialchars($p['NOME']); ?>
                                    <tr data-search="<?php echo htmlspecialchars(mb_strtolower($p['NOME'] . ' ' . ($p['DESCRICAO'] ?? ''))); ?>">
                                        <td class="cell-client">
                                            <div class="client">
                                                <span class="avatar" aria-hidden="true"><?php echo icone('pacote'); ?></span>
                                                <span>
                                                    <span class="client-name"><?php echo $nome; ?></span>
                                                    <span class="client-id">#<?php echo $p['ID']; ?><?php echo $p['DESCRICAO'] ? ' · ' . htmlspecialchars($p['DESCRICAO']) : ''; ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="num" data-label="Preço"><?php echo moeda((float)$p['PRECO']); ?></td>
                                        <td class="num" data-label="Estoque"><?php echo (int)$p['ESTOQUE']; ?></td>
                                        <td class="num" data-label="Vendidos"><?php echo (int)$p['VENDIDOS']; ?></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <a href="produto_cadastro.php?id=<?php echo $p['ID']; ?>" class="btn-icon" title="Editar"
                                                   aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                <a href="produto_apagar.php?id=<?php echo $p['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar"
                                                   aria-label="Apagar <?php echo $nome; ?>"
                                                   data-confirmar-exclusao="<?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="no-results" id="semResultados">Nenhum produto encontrado para essa busca.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/busca.js"></script>
</body>
</html>
