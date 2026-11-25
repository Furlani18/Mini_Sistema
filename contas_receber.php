<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';
require_once 'financeiro.php';

// Contas a receber: parcelas dos pedidos de venda (parcelas ⨝ pedidos ⨝ clientes).
// Orçamentos e pedidos cancelados ficam de fora.
// Filtros: ?situacao=pendentes|vencida|a_vencer|paga|todas  ?de / ?ate (vencimento)  ?q=cliente ou nº do pedido
// O botão "Receber" registra o pagamento da parcela com a data de hoje.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'receber') {
    $stmt = $pdo->prepare(
        "SELECT pa.ID, pa.NUMERO, pa.VALOR, pa.PEDIDO_ID
           FROM parcelas pa INNER JOIN pedidos p ON p.ID = pa.PEDIDO_ID
          WHERE pa.ID = ? AND pa.PAGO_EM IS NULL AND p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO')"
    );
    $stmt->execute([(int)($_POST['parcela_id'] ?? 0)]);
    $parcela = $stmt->fetch();
    if (!$parcela) {
        flash('error', 'Parcela não encontrada ou já recebida.');
    } else {
        $pdo->prepare('UPDATE parcelas SET PAGO_EM = CURDATE() WHERE ID = ?')->execute([$parcela['ID']]);
        $quitou = marcar_pedido_pago_se_quitado($pdo, (int)$parcela['PEDIDO_ID']);
        registrar_atividade($pdo, 'PAGAMENTO', "Recebeu a parcela {$parcela['NUMERO']} do pedido #{$parcela['PEDIDO_ID']} (" . moeda((float)$parcela['VALOR']) . ')', 'pedido', (int)$parcela['PEDIDO_ID']);
        flash('success', 'Recebimento registrado: ' . moeda((float)$parcela['VALOR']) . '.' . ($quitou ? " Pedido #{$parcela['PEDIDO_ID']} quitado." : ''));
    }
    header('Location: contas_receber.php' . (($_SERVER['QUERY_STRING'] ?? '') ? '?' . ($_SERVER['QUERY_STRING'] ?? '') : ''));
    exit;
}

$situacoes = [
    'pendentes' => 'A receber (todas)',
    'vencida'   => 'Vencidas',
    'a_vencer'  => 'A vencer',
    'paga'      => 'Recebidas',
    'todas'     => 'Todas',
];
$situacao = is_string($_GET['situacao'] ?? null) && isset($situacoes[$_GET['situacao']]) ? $_GET['situacao'] : 'pendentes';
$q = busca_da_url();
$de = data_da_url('de');
$ate = data_da_url('ate');

$condicoes = ["p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO')"];
$parametros = [];
$condicoes[] = match ($situacao) {
    'pendentes' => 'pa.PAGO_EM IS NULL',
    'vencida'   => 'pa.PAGO_EM IS NULL AND pa.VENCIMENTO < CURDATE()',
    'a_vencer'  => 'pa.PAGO_EM IS NULL AND pa.VENCIMENTO >= CURDATE()',
    'paga'      => 'pa.PAGO_EM IS NOT NULL',
    default     => '1 = 1',
};
if ($q !== '') {
    $numero = ltrim($q, '#');
    $condicoes[] = ctype_digit($numero) ? '(p.ID = ? OR c.NOME LIKE ?)' : 'c.NOME LIKE ?';
    if (ctype_digit($numero)) {
        $parametros[] = (int)$numero;
    }
    $parametros[] = like($q);
}
if ($de) {
    $condicoes[] = 'pa.VENCIMENTO >= ?';
    $parametros[] = $de;
}
if ($ate) {
    $condicoes[] = 'pa.VENCIMENTO <= ?';
    $parametros[] = $ate;
}
$where = 'WHERE ' . implode(' AND ', $condicoes);
$from = 'FROM parcelas pa
        INNER JOIN pedidos p ON p.ID = pa.PEDIDO_ID
        INNER JOIN clientes c ON c.ID = p.CLIENTE_ID';

$ordem = ordenacao([
    'vencimento' => 'pa.VENCIMENTO',
    'cliente'    => 'c.NOME',
    'pedido'     => 'p.ID',
    'valor'      => 'pa.VALOR',
], 'vencimento', $situacao === 'paga' ? 'desc' : 'asc');

$stmt = $pdo->prepare("SELECT COUNT(*) AS QTD, COALESCE(SUM(pa.VALOR), 0) AS VALOR $from $where");
$stmt->execute($parametros);
$resumoFiltro = $stmt->fetch();
$pag = paginacao((int)$resumoFiltro['QTD']);

$stmt = $pdo->prepare(
    "SELECT pa.ID, pa.NUMERO, pa.VALOR, pa.VENCIMENTO, pa.PAGO_EM, pa.FORMA_PAGAMENTO, pa.PEDIDO_ID,
            (SELECT COUNT(*) FROM parcelas x WHERE x.PEDIDO_ID = pa.PEDIDO_ID) AS TOTAL_PARCELAS,
            c.ID AS CLIENTE_ID, c.NOME AS CLIENTE, c.TELEFONE
       $from $where
      ORDER BY {$ordem['sql']}, pa.ID
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$parcelas = $stmt->fetchAll();

// Indicadores gerais (sem filtros)
$indicadores = $pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN pa.PAGO_EM IS NULL THEN pa.VALOR END), 0)                                   AS A_RECEBER,
            COALESCE(SUM(CASE WHEN pa.PAGO_EM IS NULL AND pa.VENCIMENTO < CURDATE() THEN pa.VALOR END), 0)      AS VENCIDO,
            COUNT(CASE WHEN pa.PAGO_EM IS NULL AND pa.VENCIMENTO < CURDATE() THEN 1 END)                        AS QTD_VENCIDAS,
            COALESCE(SUM(CASE WHEN pa.PAGO_EM IS NULL AND pa.VENCIMENTO BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
                              THEN pa.VALOR END), 0)                                                            AS PROXIMOS_7,
            COALESCE(SUM(CASE WHEN pa.PAGO_EM >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN pa.VALOR END), 0)      AS RECEBIDO_MES
       FROM parcelas pa INNER JOIN pedidos p ON p.ID = pa.PEDIDO_ID
      WHERE p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO')"
)->fetch();

// Pedidos de venda que ainda não tiveram as parcelas definidas
$semParcelas = (int)$pdo->query(
    "SELECT COUNT(*) FROM vw_pedidos p
      WHERE p.STATUS = 'ABERTO' AND p.TOTAL > 0 AND NOT EXISTS (SELECT 1 FROM parcelas pa WHERE pa.PEDIDO_ID = p.ID)"
)->fetchColumn();

$filtrando = $situacao !== 'pendentes' || $q !== '' || $de || $ate;
$cards = [
    ['A receber', moeda((float)$indicadores['A_RECEBER']), 'Todas as parcelas em aberto', 'contas_receber.php'],
    ['Vencido', moeda((float)$indicadores['VENCIDO']),
        (int)$indicadores['QTD_VENCIDAS'] === 1 ? '1 parcela atrasada' : (int)$indicadores['QTD_VENCIDAS'] . ' parcelas atrasadas', 'contas_receber.php?situacao=vencida'],
    ['Próximos 7 dias', moeda((float)$indicadores['PROXIMOS_7']), 'Vencem até ' . date('d/m', strtotime('+7 days')),
        'contas_receber.php?situacao=a_vencer&amp;ate=' . date('Y-m-d', strtotime('+7 days'))],
    ['Recebido em ' . nome_mes((int)date('n')), moeda((float)$indicadores['RECEBIDO_MES']), 'Pagamentos registrados no mês', 'contas_receber.php?situacao=paga&amp;de=' . date('Y-m-01')],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Receber</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Contas a receber' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-header">
                <h2 class="page-title"><?php echo icone('dinheiro'); ?> Contas a Receber</h2>
            </div>

            <section class="indicadores" aria-label="Resumo financeiro">
                <?php foreach ($cards as [$rotulo, $valor, $detalhe, $link]): ?>
                    <a class="indicador indicador-link<?php echo $rotulo === 'Vencido' && (float)$indicadores['VENCIDO'] > 0 ? ' is-alerta' : ''; ?>" href="<?php echo $link; ?>">
                        <p class="indicador-rotulo"><?php echo $rotulo; ?></p>
                        <p class="indicador-valor"><?php echo $valor; ?></p>
                        <p class="indicador-detalhe"><?php echo $detalhe; ?></p>
                    </a>
                <?php endforeach; ?>
            </section>

            <?php if ($semParcelas > 0): ?>
                <div class="alert alert-info">
                    <?php echo icone('alerta'); ?>
                    <span><?php echo $semParcelas === 1 ? '1 pedido aberto ainda não tem' : "$semParcelas pedidos abertos ainda não têm"; ?> as parcelas definidas e não aparece<?php echo $semParcelas === 1 ? '' : 'm'; ?> aqui.
                        <a href="pedidos.php?status=ABERTO" class="text-link">Ver pedidos abertos</a></span>
                </div>
            <?php endif; ?>

            <div class="list-container">
                <form method="get" class="filtros" role="search">
                    <?php echo campos_ocultos(['q', 'situacao', 'de', 'ate', 'pagina']); ?>
                    <label for="buscaContas" class="visually-hidden">Buscar</label>
                    <div class="search-input">
                        <?php echo icone('busca'); ?>
                        <input type="search" id="buscaContas" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Cliente ou nº do pedido" autocomplete="off">
                    </div>
                    <label for="filtroSituacao" class="visually-hidden">Situação</label>
                    <select id="filtroSituacao" name="situacao" class="filtro-select" data-auto-submit>
                        <?php foreach ($situacoes as $valor => $rotulo): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === $situacao ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="filtro-periodo">
                        <label for="filtroDe">Vence de</label>
                        <input type="date" id="filtroDe" name="de" value="<?php echo $de; ?>">
                        <label for="filtroAte">até</label>
                        <input type="date" id="filtroAte" name="ate" value="<?php echo $ate; ?>">
                    </div>
                    <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Filtrar</button>
                    <?php if ($filtrando): ?>
                        <a href="contas_receber.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                    <?php endif; ?>
                </form>

                <?php if (!$parcelas): ?>
                    <p class="no-results is-visible"><?php echo $filtrando ? 'Nenhuma parcela encontrada com esses filtros.' : 'Nenhuma parcela a receber. Tudo em dia!'; ?></p>
                <?php else: ?>
                    <p class="text-muted list-total"><?php echo $situacoes[$situacao]; ?>: <strong><?php echo moeda((float)$resumoFiltro['VALOR']); ?></strong>
                        em <?php echo (int)$resumoFiltro['QTD'] === 1 ? '1 parcela' : (int)$resumoFiltro['QTD'] . ' parcelas'; ?></p>
                    <?php echo seletor_ordenacao(['vencimento' => 'Vencimento', 'cliente' => 'Cliente', 'valor' => 'Valor'], $ordem); ?>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <?php echo th_ordenavel('cliente', 'Cliente', $ordem); ?>
                                    <?php echo th_ordenavel('pedido', 'Pedido', $ordem, '', 'desc'); ?>
                                    <?php echo th_ordenavel('vencimento', 'Vencimento', $ordem); ?>
                                    <th>Situação</th>
                                    <?php echo th_ordenavel('valor', 'Valor', $ordem, 'num', 'desc'); ?>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parcelas as $pa): ?>
                                    <?php
                                    $sit = situacao_parcela($pa);
                                    $diasAtraso = $sit === 'vencida' ? (int)(new DateTime($pa['VENCIMENTO']))->diff(new DateTime('today'))->days : 0;
                                    $cobranca = $sit === 'vencida'
                                        ? whatsapp_link($pa['TELEFONE'], 'Olá, ' . explode(' ', trim($pa['CLIENTE']))[0] . '! Lembrete: a parcela ' . $pa['NUMERO'] . '/' . $pa['TOTAL_PARCELAS']
                                            . ' do pedido #' . $pa['PEDIDO_ID'] . ', no valor de ' . moeda((float)$pa['VALOR']) . ', venceu em ' . date('d/m/Y', strtotime($pa['VENCIMENTO'])) . '.')
                                        : null;
                                    ?>
                                    <tr>
                                        <td class="cell-client">
                                            <a href="cliente_ver.php?id=<?php echo $pa['CLIENTE_ID']; ?>" class="client-link">
                                                <span class="client-name"><?php echo htmlspecialchars($pa['CLIENTE']); ?></span>
                                                <span class="client-id">Parcela <?php echo (int)$pa['NUMERO']; ?>/<?php echo (int)$pa['TOTAL_PARCELAS']; ?><?php echo $pa['FORMA_PAGAMENTO'] ? ' · ' . formas_pagamento()[$pa['FORMA_PAGAMENTO']] : ''; ?></span>
                                            </a>
                                        </td>
                                        <td data-label="Pedido"><a href="pedido_ver.php?id=<?php echo $pa['PEDIDO_ID']; ?>" class="text-link">#<?php echo $pa['PEDIDO_ID']; ?></a></td>
                                        <td data-label="Vencimento">
                                            <?php echo date('d/m/Y', strtotime($pa['VENCIMENTO'])); ?>
                                            <?php if ($diasAtraso): ?><br><small class="texto-perigo"><?php echo $diasAtraso === 1 ? '1 dia de atraso' : "$diasAtraso dias de atraso"; ?></small><?php endif; ?>
                                        </td>
                                        <td data-label="Situação">
                                            <?php echo badge_parcela($sit); ?>
                                            <?php if ($pa['PAGO_EM']): ?><small class="text-muted">em <?php echo date('d/m/Y', strtotime($pa['PAGO_EM'])); ?></small><?php endif; ?>
                                        </td>
                                        <td class="num" data-label="Valor"><strong><?php echo moeda((float)$pa['VALOR']); ?></strong></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <?php if ($cobranca): ?>
                                                    <a href="<?php echo htmlspecialchars($cobranca); ?>" class="btn-icon btn-icon-whatsapp" target="_blank" rel="noopener"
                                                       title="Cobrar pelo WhatsApp" aria-label="Cobrar <?php echo htmlspecialchars($pa['CLIENTE']); ?> pelo WhatsApp"><?php echo icone('whatsapp'); ?></a>
                                                <?php endif; ?>
                                                <?php if (!$pa['PAGO_EM']): ?>
                                                    <form method="POST" action="contas_receber.php<?php echo ($_SERVER['QUERY_STRING'] ?? '') ? '?' . htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') : ''; ?>" class="form-inline">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="acao" value="receber">
                                                        <input type="hidden" name="parcela_id" value="<?php echo $pa['ID']; ?>">
                                                        <button type="submit" class="btn btn-primary btn-sm" title="Registrar o recebimento com a data de hoje"><?php echo icone('check'); ?> Receber</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php echo controles_paginacao($pag); ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
