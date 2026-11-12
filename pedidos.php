<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Lista de pedidos. Junta 4 tabelas:
//   pedidos  ⨝ clientes      (quem comprou)          INNER JOIN — todo pedido tem cliente
//            ⨝ usuarios      (quem registrou)        LEFT JOIN  — pode ser NULL
//            ⨝ itens_pedido  (para somar o total)    LEFT JOIN  — pedido pode estar sem itens
// Filtro opcional: pedidos.php?cliente=ID (link vindo da lista de clientes).

$clienteFiltro = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;
$nomeFiltro = null;

$sql = 'SELECT p.ID, p.STATUS, p.CRIADO_EM,
               c.ID AS CLIENTE_ID, c.NOME AS CLIENTE,
               u.NOME AS USUARIO,
               COUNT(i.ID) AS ITENS,
               COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) AS TOTAL
          FROM pedidos p
         INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
          LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
          LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID';
$parametros = [];

if ($clienteFiltro > 0) {
    $sql .= ' WHERE p.CLIENTE_ID = ?';
    $parametros[] = $clienteFiltro;

    $stmt = $pdo->prepare('SELECT NOME FROM clientes WHERE ID = ?');
    $stmt->execute([$clienteFiltro]);
    $nomeFiltro = $stmt->fetchColumn() ?: null;
}

$sql .= ' GROUP BY p.ID, p.STATUS, p.CRIADO_EM, c.ID, c.NOME, u.NOME
          ORDER BY p.ID DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$pedidos = $stmt->fetchAll();

$totalGeral = array_sum(array_map(fn($p) => $p['STATUS'] === 'CANCELADO' ? 0 : (float)$p['TOTAL'], $pedidos));
$linkNovo = 'pedido_novo.php' . ($clienteFiltro ? '?cliente=' . $clienteFiltro : '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php
        $breadcrumb = $nomeFiltro ? ['Pedidos' => 'pedidos.php', $nomeFiltro => null] : ['Pedidos' => null];
        include __DIR__ . '/header.php';
        ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('carrinho'); ?> <?php echo $nomeFiltro ? 'Pedidos de ' . htmlspecialchars($nomeFiltro) : 'Pedidos'; ?></h2>
                    <a href="<?php echo $linkNovo; ?>" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Pedido</a>
                </div>

                <?php if ($nomeFiltro): ?>
                    <div class="alert alert-info">
                        <?php echo icone('busca'); ?>
                        <span>Mostrando apenas os pedidos deste cliente. <a href="pedidos.php" class="text-link">Ver todos os pedidos</a></span>
                    </div>
                <?php endif; ?>

                <?php if (!$pedidos): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('carrinho'); ?></span>
                        <p>Nenhum pedido encontrado.</p>
                        <p><a href="<?php echo $linkNovo; ?>" class="text-link">Clique aqui para registrar um pedido</a></p>
                    </div>
                <?php else: ?>
                    <div class="search-bar">
                        <label for="buscaPedidos" class="visually-hidden">Buscar pedido</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaPedidos" data-busca placeholder="Buscar por número, cliente ou status" autocomplete="off">
                        </div>
                        <p class="list-summary" id="resultadoBusca" data-singular="pedido" data-plural="pedidos" aria-live="polite">
                            <?php echo count($pedidos) . (count($pedidos) === 1 ? ' pedido' : ' pedidos'); ?>
                        </p>
                    </div>
                    <p class="text-muted list-total">Total vendido (sem cancelados): <strong><?php echo moeda($totalGeral); ?></strong></p>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Registrado por</th>
                                    <th>Status</th>
                                    <th class="num">Itens</th>
                                    <th class="num">Total</th>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos as $p): ?>
                                    <?php $textoBusca = '#' . $p['ID'] . ' pedido ' . $p['ID'] . ' ' . $p['CLIENTE'] . ' ' . ($p['USUARIO'] ?? '') . ' ' . (status_pedidos()[$p['STATUS']] ?? ''); ?>
                                    <tr data-search="<?php echo htmlspecialchars(mb_strtolower($textoBusca)); ?>">
                                        <td class="cell-client">
                                            <span class="client-name">Pedido #<?php echo $p['ID']; ?></span>
                                            <span class="client-id"><?php echo date('d/m/Y H:i', strtotime($p['CRIADO_EM'])); ?></span>
                                        </td>
                                        <td data-label="Cliente">
                                            <a href="pedidos.php?cliente=<?php echo $p['CLIENTE_ID']; ?>" class="text-link"><?php echo htmlspecialchars($p['CLIENTE']); ?></a>
                                        </td>
                                        <td data-label="Registrado por"><?php echo $p['USUARIO'] ? htmlspecialchars($p['USUARIO']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td data-label="Status"><?php echo badge_status($p['STATUS']); ?></td>
                                        <td class="num" data-label="Itens"><?php echo (int)$p['ITENS']; ?></td>
                                        <td class="num" data-label="Total"><strong><?php echo moeda((float)$p['TOTAL']); ?></strong></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <a href="pedido_ver.php?id=<?php echo $p['ID']; ?>" class="btn-icon" title="Ver detalhes"
                                                   aria-label="Ver pedido #<?php echo $p['ID']; ?>"><?php echo icone('olho'); ?></a>
                                                <a href="pedido_apagar.php?id=<?php echo $p['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar"
                                                   aria-label="Apagar pedido #<?php echo $p['ID']; ?>"
                                                   data-confirmar-exclusao="o pedido #<?php echo $p['ID']; ?>"><?php echo icone('lixeira'); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="no-results" id="semResultados">Nenhum pedido encontrado para essa busca.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/busca.js"></script>
</body>
</html>
