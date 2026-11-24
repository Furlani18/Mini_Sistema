<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Lista de produtos com busca, filtro de estoque, ordenação e paginação.
// LEFT JOIN: mostra também os produtos que nunca foram vendidos (vendidos = 0).


$q = busca_da_url();
$filtrosEstoque = [
    ''        => 'Todo o estoque',
    'baixo'   => 'Estoque baixo (até ' . ESTOQUE_BAIXO . ')',
    'zerado'  => 'Esgotados',
    'normal'  => 'Estoque normal',
];
$estoque = $_GET['estoque'] ?? '';
if (!is_string($estoque) || !isset($filtrosEstoque[$estoque])) {
    $estoque = '';
}

$condicoes = [];
$parametros = [];
if ($q !== '') {
    $condicoes[] = '(pr.NOME LIKE ? OR pr.DESCRICAO LIKE ?)';
    array_push($parametros, like($q), like($q));
}
$condicoes[] = match ($estoque) {
    'baixo'  => 'pr.ESTOQUE BETWEEN 1 AND ' . ESTOQUE_BAIXO,
    'zerado' => 'pr.ESTOQUE = 0',
    'normal' => 'pr.ESTOQUE > ' . ESTOQUE_BAIXO,
    default  => '1 = 1',
};
$where = 'WHERE ' . implode(' AND ', $condicoes);

$ordem = ordenacao([
    'id'       => 'pr.ID',
    'nome'     => 'pr.NOME',
    'preco'    => 'pr.PRECO',
    'estoque'  => 'pr.ESTOQUE',
    'vendidos' => 'VENDIDOS',
], 'id', 'desc');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos pr $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE,
            COALESCE(SUM(CASE WHEN p.STATUS <> 'CANCELADO' THEN i.QUANTIDADE END), 0) AS VENDIDOS
       FROM produtos pr
       LEFT JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
       LEFT JOIN pedidos p ON p.ID = i.PEDIDO_ID
       $where
      GROUP BY pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE
      ORDER BY {$ordem['sql']}, pr.ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll();

$filtrando = $q !== '' || $estoque !== '';
$totalCadastrados = $filtrando ? (int)$pdo->query('SELECT COUNT(*) FROM produtos')->fetchColumn() : $pag['total'];
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

                <?php if ($totalCadastrados === 0): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('pacote'); ?></span>
                        <p>Nenhum produto cadastrado ainda.</p>
                        <p><a href="produto_cadastro.php" class="text-link">Clique aqui para cadastrar o primeiro produto</a></p>
                    </div>
                <?php else: ?>
                    <form method="get" class="filtros" role="search">
                        <?php echo campos_ocultos(['q', 'estoque', 'pagina']); ?>
                        <label for="buscaProdutos" class="visually-hidden">Buscar produto</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaProdutos" name="q" value="<?php echo htmlspecialchars($q); ?>"
                                   placeholder="Buscar por nome ou descrição" autocomplete="off">
                        </div>
                        <label for="filtroEstoque" class="visually-hidden">Situação do estoque</label>
                        <select id="filtroEstoque" name="estoque" class="filtro-select" data-auto-submit>
                            <?php foreach ($filtrosEstoque as $valor => $rotulo): ?>
                                <option value="<?php echo $valor; ?>"<?php echo $valor === $estoque ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Buscar</button>
                        <?php if ($filtrando): ?>
                            <a href="produtos.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                        <?php endif; ?>
                    </form>

                    <?php if (!$produtos): ?>
                        <p class="no-results is-visible">Nenhum produto encontrado com esses filtros.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <?php echo th_ordenavel('nome', 'Produto', $ordem); ?>
                                        <?php echo th_ordenavel('preco', 'Preço', $ordem, 'num', 'desc'); ?>
                                        <?php echo th_ordenavel('estoque', 'Estoque', $ordem, 'num'); ?>
                                        <?php echo th_ordenavel('vendidos', 'Vendidos', $ordem, 'num', 'desc'); ?>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $p): ?>
                                        <?php $nome = htmlspecialchars($p['NOME']); $qtd = (int)$p['ESTOQUE']; ?>
                                        <tr>
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
                                            <td class="num" data-label="Estoque">
                                                <?php if ($qtd === 0): ?>
                                                    <span class="estoque-qtd is-zerado">Esgotado</span>
                                                <?php elseif ($qtd <= ESTOQUE_BAIXO): ?>
                                                    <span class="estoque-qtd" title="Estoque baixo"><?php echo $qtd; ?></span>
                                                <?php else: ?>
                                                    <?php echo $qtd; ?>
                                                <?php endif; ?>
                                            </td>
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
                        </div>
                    <?php endif; ?>

                    <?php echo controles_paginacao($pag); ?>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
