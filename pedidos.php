<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Lista de pedidos. Junta 4 tabelas:
//   pedidos  ⨝ clientes      (quem comprou)          INNER JOIN — todo pedido tem cliente
//            ⨝ usuarios      (quem registrou)        LEFT JOIN  — pode ser NULL
//            ⨝ itens_pedido  (para somar o total)    LEFT JOIN  — pedido pode estar sem itens
// Filtros (todos opcionais, combináveis):
//   ?q=texto  ?status=PAGO  ?de=2026-01-01  ?ate=2026-12-31  ?cliente=ID (link vindo da lista de clientes)
// Ordenação por coluna e paginação ficam no lista.php.

$q = busca_da_url();
$status = $_GET['status'] ?? '';
if (!is_string($status) || !isset(status_pedidos()[$status])) {
    $status = '';
}
$de = data_da_url('de');
$ate = data_da_url('ate');
$clienteFiltro = isset($_GET['cliente']) ? (int)$_GET['cliente'] : 0;
$nomeFiltro = null;

$condicoes = [];
$parametros = [];
if ($q !== '') {
    // Busca pelo número (#12 ou 12) ou pelo nome do cliente
    $numero = ltrim($q, '#');
    $condicoes[] = ctype_digit($numero) ? '(p.ID = ? OR c.NOME LIKE ?)' : 'c.NOME LIKE ?';
    if (ctype_digit($numero)) {
        $parametros[] = (int)$numero;
    }
    $parametros[] = like($q);
}
if ($status !== '') {
    $condicoes[] = 'p.STATUS = ?';
    $parametros[] = $status;
}
if ($de) {
    $condicoes[] = 'p.CRIADO_EM >= ?';
    $parametros[] = $de . ' 00:00:00';
}
if ($ate) {
    $condicoes[] = 'p.CRIADO_EM <= ?';
    $parametros[] = $ate . ' 23:59:59';
}
if ($clienteFiltro > 0) {
    $condicoes[] = 'p.CLIENTE_ID = ?';
    $parametros[] = $clienteFiltro;

    $stmt = $pdo->prepare('SELECT NOME FROM clientes WHERE ID = ?');
    $stmt->execute([$clienteFiltro]);
    $nomeFiltro = $stmt->fetchColumn() ?: null;
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$ordem = ordenacao([
    'id'      => 'p.ID',
    'data'    => 'p.CRIADO_EM',
    'cliente' => 'c.NOME',
    'status'  => 'p.STATUS',
    'itens'   => 'ITENS',
    'total'   => 'TOTAL',
], 'id', 'desc');

// Quantidade e total vendido de TODOS os pedidos filtrados (não só da página atual)
$stmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT p.ID) AS QTD,
            COALESCE(SUM(CASE WHEN p.STATUS <> 'CANCELADO' THEN i.QUANTIDADE * i.PRECO_UNITARIO END), 0) AS VENDIDO
       FROM pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
       $where"
);
$stmt->execute($parametros);
$resumo = $stmt->fetch();
$pag = paginacao((int)$resumo['QTD']);

$stmt = $pdo->prepare(
    "SELECT p.ID, p.STATUS, p.CRIADO_EM,
            c.ID AS CLIENTE_ID, c.NOME AS CLIENTE,
            u.NOME AS USUARIO,
            COUNT(i.ID) AS ITENS,
            COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) AS TOTAL
       FROM pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
       LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
       $where
      GROUP BY p.ID, p.STATUS, p.CRIADO_EM, c.ID, c.NOME, u.NOME
      ORDER BY {$ordem['sql']}, p.ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$pedidos = $stmt->fetchAll();

$filtrando = $q !== '' || $status !== '' || $de || $ate;
$totalCadastrados = (int)$pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
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
                        <span>Mostrando apenas os pedidos deste cliente.
                            <a href="cliente_ver.php?id=<?php echo $clienteFiltro; ?>" class="text-link">Ver ficha do cliente</a> ·
                            <a href="<?php echo link_lista(['cliente' => null, 'pagina' => null]); ?>" class="text-link">Ver todos os pedidos</a></span>
                    </div>
                <?php endif; ?>

                <?php if ($totalCadastrados === 0): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('carrinho'); ?></span>
                        <p>Nenhum pedido registrado ainda.</p>
                        <p><a href="<?php echo $linkNovo; ?>" class="text-link">Clique aqui para registrar um pedido</a></p>
                    </div>
                <?php else: ?>
                    <form method="get" class="filtros" role="search">
                        <?php echo campos_ocultos(['q', 'status', 'de', 'ate', 'pagina']); ?>
                        <label for="buscaPedidos" class="visually-hidden">Buscar pedido</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaPedidos" name="q" value="<?php echo htmlspecialchars($q); ?>"
                                   placeholder="Número do pedido ou cliente" autocomplete="off">
                        </div>
                        <label for="filtroStatus" class="visually-hidden">Status</label>
                        <select id="filtroStatus" name="status" class="filtro-select" data-auto-submit>
                            <option value="">Todos os status</option>
                            <?php foreach (status_pedidos() as $valor => $rotulo): ?>
                                <option value="<?php echo $valor; ?>"<?php echo $valor === $status ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="filtro-periodo">
                            <label for="filtroDe">De</label>
                            <input type="date" id="filtroDe" name="de" value="<?php echo $de; ?>">
                            <label for="filtroAte">até</label>
                            <input type="date" id="filtroAte" name="ate" value="<?php echo $ate; ?>">
                        </div>
                        <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Filtrar</button>
                        <?php if ($filtrando): ?>
                            <a href="<?php echo link_lista(['q' => null, 'status' => null, 'de' => null, 'ate' => null, 'pagina' => null]); ?>" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                        <?php endif; ?>
                    </form>

                    <?php if (!$pedidos): ?>
                        <p class="no-results is-visible">Nenhum pedido encontrado com esses filtros.</p>
                    <?php else: ?>
                        <p class="text-muted list-total">
                            Total vendido<?php echo $filtrando || $nomeFiltro ? ' nos pedidos filtrados' : ''; ?> (sem cancelados):
                            <strong><?php echo moeda((float)$resumo['VENDIDO']); ?></strong>
                        </p>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <?php echo th_ordenavel('id', 'Pedido', $ordem, '', 'desc'); ?>
                                        <?php echo th_ordenavel('cliente', 'Cliente', $ordem); ?>
                                        <th>Registrado por</th>
                                        <?php echo th_ordenavel('status', 'Status', $ordem); ?>
                                        <?php echo th_ordenavel('itens', 'Itens', $ordem, 'num', 'desc'); ?>
                                        <?php echo th_ordenavel('total', 'Total', $ordem, 'num', 'desc'); ?>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pedidos as $p): ?>
                                        <tr>
                                            <td class="cell-client">
                                                <a href="pedido_ver.php?id=<?php echo $p['ID']; ?>" class="client-link">
                                                    <span class="client-name">Pedido #<?php echo $p['ID']; ?></span>
                                                    <span class="client-id"><?php echo date('d/m/Y H:i', strtotime($p['CRIADO_EM'])); ?></span>
                                                </a>
                                            </td>
                                            <td data-label="Cliente">
                                                <a href="cliente_ver.php?id=<?php echo $p['CLIENTE_ID']; ?>" class="text-link"><?php echo htmlspecialchars($p['CLIENTE']); ?></a>
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
