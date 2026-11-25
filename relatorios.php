<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'relatorios_funcoes.php';

// Relatórios de vendas: indicadores do período (com comparação ao período anterior),
// faturamento ao longo do tempo, pedidos por status, produtos mais vendidos e melhores clientes.
// Filtro: ?periodo=30d|90d|12m|ano|tudo  ou  ?de=AAAA-MM-DD&ate=AAAA-MM-DD
// Os gráficos são HTML/CSS simples (sem biblioteca externa); o js/relatorios.js cuida das dicas ao passar o mouse.

$periodo = periodo_relatorio($pdo);
$resumo = resumo_vendas($pdo, $periodo);
$comparar = $periodo['periodo'] !== 'tudo';
$anterior = $comparar ? resumo_vendas($pdo, periodo_anterior($periodo)) : null;

$grafico = vendas_por_periodo($pdo, $periodo);
$serie = $grafico['serie'];
$maiorValor = max(array_column($serie, 'valor') ?: [0]);
$escala = escala_eixo($maiorValor);
$compacto = $escala['topo'] >= 10000;
$indiceMaior = $maiorValor > 0 ? array_search($maiorValor, array_column($serie, 'valor'), true) : null;
// Muitas colunas: mostra só alguns rótulos do eixo X para não embolar
$passoRotulo = max(1, (int)ceil(count($serie) / 12));

$porStatus = pedidos_por_status($pdo, $periodo);
$totalStatus = array_sum(array_column($porStatus, 'pedidos'));
$produtos = produtos_mais_vendidos($pdo, $periodo);
$maiorQtd = $produtos ? max(array_map(fn($p) => (int)$p['QUANTIDADE'], $produtos)) : 0;
$clientes = melhores_clientes($pdo, $periodo);
$maiorCliente = $clientes ? max(array_map(fn($c) => (float)$c['VALOR'], $clientes)) : 0;
$categorias = vendas_por_categoria($pdo, $periodo);
$maiorCategoria = $categorias ? max(array_map(fn($c) => (float)$c['VALOR'], $categorias)) : 0;

/** Variação percentual em relação ao período anterior (null quando não dá para comparar). */
function variacao(?array $anterior, array $atual, string $campo): ?array
{
    if ($anterior === null || (float)$anterior[$campo] == 0.0) {
        return null;
    }
    $pct = ((float)$atual[$campo] - (float)$anterior[$campo]) / (float)$anterior[$campo] * 100;
    return [
        'pct'    => $pct,
        'texto'  => ($pct >= 0 ? '+' : '−') . number_format(abs($pct), 1, ',', '.') . '%',
        'classe' => abs($pct) < 0.05 ? 'neutro' : ($pct > 0 ? 'positivo' : 'negativo'),
        'seta'   => abs($pct) < 0.05 ? '→' : ($pct > 0 ? '↑' : '↓'),
    ];
}

$plural = fn(int $n, string $um, string $varios) => $n . ' ' . ($n === 1 ? $um : $varios);
$semCusto = (int)$resumo['ITENS_SEM_CUSTO'];
$indicadores = [
    ['Faturamento', moeda((float)$resumo['FATURAMENTO']), variacao($anterior, $resumo, 'FATURAMENTO'),
        (float)$resumo['DESCONTOS'] > 0 ? 'Já com ' . moeda((float)$resumo['DESCONTOS']) . ' de descontos' : 'Sem cancelados e descontos'],
    ['Pedidos', (string)(int)$resumo['PEDIDOS'], variacao($anterior, $resumo, 'PEDIDOS'),
        $plural((int)$resumo['CANCELADOS'], 'cancelado', 'cancelados') . ' e ' . $plural((int)$resumo['ORCAMENTOS'], 'orçamento', 'orçamentos') . ' à parte'],
    ['Ticket médio', moeda((float)$resumo['TICKET']), variacao($anterior, $resumo, 'TICKET'), 'Valor médio por pedido'],
    ['Itens vendidos', (string)(int)$resumo['ITENS'], variacao($anterior, $resumo, 'ITENS'),
        (int)$resumo['CLIENTES'] === 1 ? '1 cliente comprou' : (int)$resumo['CLIENTES'] . ' clientes compraram'],
    (float)$resumo['RECEITA_COM_CUSTO'] > 0
        ? ['Lucro bruto', moeda((float)$resumo['LUCRO']), variacao($anterior, $resumo, 'LUCRO'),
            'Margem de ' . number_format((float)$resumo['MARGEM'], 1, ',', '.') . '% antes dos descontos'
            . ($semCusto ? ' · ' . $plural($semCusto, 'item sem custo ficou', 'itens sem custo ficaram') . ' de fora' : '')]
        : ['Lucro bruto', '—', null, $semCusto
            ? 'Nenhum item vendido tem custo. Informe o custo no cadastro do produto ou nas entradas de estoque.'
            : 'Sem vendas no período'],
];

$textoPeriodo = date('d/m/Y', strtotime($periodo['de'])) . ' a ' . date('d/m/Y', strtotime($periodo['ate']));
$queryPeriodo = http_build_query(['de' => $periodo['de'], 'ate' => $periodo['ate']]);
$coresStatus = ['ORCAMENTO' => 'orcamento', 'ABERTO' => 'aberto', 'PAGO' => 'pago', 'ENVIADO' => 'enviado', 'CANCELADO' => 'cancelado'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Relatórios' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-header relatorio-topo">
                <div>
                    <h2 class="page-title"><?php echo icone('grafico'); ?> Relatórios de Vendas</h2>
                    <p class="text-muted relatorio-periodo"><?php echo icone('calendario'); ?> <?php echo $textoPeriodo; ?>
                        <?php if ($comparar): ?><span class="relatorio-comparacao">· comparado ao período anterior de mesma duração</span><?php endif; ?></p>
                </div>
                <div class="exportar">
                    <span class="exportar-rotulo"><?php echo icone('download'); ?> Exportar CSV:</span>
                    <a href="relatorio_exportar.php?tipo=pedidos&amp;<?php echo htmlspecialchars($queryPeriodo); ?>" class="btn btn-outline btn-sm">Pedidos</a>
                    <a href="relatorio_exportar.php?tipo=produtos&amp;<?php echo htmlspecialchars($queryPeriodo); ?>" class="btn btn-outline btn-sm">Produtos</a>
                    <a href="relatorio_exportar.php?tipo=clientes&amp;<?php echo htmlspecialchars($queryPeriodo); ?>" class="btn btn-outline btn-sm">Clientes</a>
                </div>
            </div>

            <form method="get" class="filtros filtros-relatorio" aria-label="Período do relatório">
                <div class="segmentado" role="group" aria-label="Períodos rápidos">
                    <?php foreach (periodos_rapidos() as $chave => $rotulo): ?>
                        <a href="relatorios.php?periodo=<?php echo $chave; ?>"
                           class="segmentado-item<?php echo $periodo['periodo'] === $chave ? ' is-ativo' : ''; ?>"
                           <?php echo $periodo['periodo'] === $chave ? 'aria-current="true"' : ''; ?>><?php echo $rotulo; ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="filtro-periodo">
                    <label for="relDe">De</label>
                    <input type="date" id="relDe" name="de" value="<?php echo $periodo['de']; ?>" required>
                    <label for="relAte">até</label>
                    <input type="date" id="relAte" name="ate" value="<?php echo $periodo['ate']; ?>" required>
                    <button type="submit" class="btn btn-outline btn-sm">Aplicar</button>
                </div>
            </form>

            <section class="indicadores" aria-label="Indicadores do período">
                <?php foreach ($indicadores as [$rotulo, $valor, $delta, $detalhe]): ?>
                    <div class="indicador">
                        <p class="indicador-rotulo"><?php echo $rotulo; ?></p>
                        <p class="indicador-valor"><?php echo $valor; ?></p>
                        <p class="indicador-detalhe">
                            <?php if ($delta): ?>
                                <span class="delta delta-<?php echo $delta['classe']; ?>"><span aria-hidden="true"><?php echo $delta['seta']; ?></span> <?php echo $delta['texto']; ?></span>
                                <span class="visually-hidden">em relação ao período anterior.</span>
                            <?php endif; ?>
                            <?php echo $detalhe; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </section>

            <section class="painel painel-grafico">
                <div class="painel-cabecalho">
                    <h3><?php echo icone('grafico'); ?> Faturamento por <?php echo $grafico['porDia'] ? 'dia' : 'mês'; ?></h3>
                    <span class="text-muted painel-nota">Orçamentos e cancelados não entram</span>
                </div>

                <?php if ($maiorValor <= 0): ?>
                    <p class="painel-vazio">Nenhuma venda neste período.</p>
                <?php else: ?>
                    <div class="grafico" data-grafico>
                        <div class="grafico-eixo-y" aria-hidden="true">
                            <?php for ($k = (int)round($escala['topo'] / $escala['passo']); $k >= 0; $k--): $v = $k * $escala['passo']; ?>
                                <span style="bottom: <?php echo $v / $escala['topo'] * 100; ?>%"><?php echo moeda_eixo($v, $compacto); ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="grafico-corpo">
                            <div class="grafico-grade" aria-hidden="true">
                                <?php for ($k = (int)round($escala['topo'] / $escala['passo']); $k >= 0; $k--): $v = $k * $escala['passo']; ?>
                                    <span style="bottom: <?php echo $v / $escala['topo'] * 100; ?>%"></span>
                                <?php endfor; ?>
                            </div>
                            <div class="grafico-colunas" role="list" aria-label="Faturamento por <?php echo $grafico['porDia'] ? 'dia' : 'mês'; ?>">
                                <?php foreach ($serie as $i => $ponto): ?>
                                    <?php
                                    $textoPedidos = $ponto['pedidos'] === 1 ? '1 pedido' : $ponto['pedidos'] . ' pedidos';
                                    $altura = $ponto['valor'] / $escala['topo'] * 100;
                                    ?>
                                    <div class="coluna" role="listitem" tabindex="0"
                                         data-titulo="<?php echo $ponto['longo']; ?>"
                                         data-valor="<?php echo moeda($ponto['valor']); ?>"
                                         data-extra="<?php echo $textoPedidos; ?>"
                                         aria-label="<?php echo $ponto['longo'] . ': ' . moeda($ponto['valor']) . ', ' . $textoPedidos; ?>">
                                        <span class="coluna-trilho">
                                            <?php if ($i === $indiceMaior): ?>
                                                <span class="coluna-valor" style="bottom: <?php echo $altura; ?>%" aria-hidden="true"><?php echo moeda_eixo($ponto['valor'], $ponto['valor'] >= 10000); ?></span>
                                            <?php endif; ?>
                                            <span class="coluna-barra<?php echo $ponto['valor'] > 0 ? '' : ' is-zero'; ?>" style="height: <?php echo $altura; ?>%"></span>
                                        </span>
                                        <span class="coluna-rotulo<?php echo $i % $passoRotulo === 0 ? '' : ' is-oculto'; ?>" aria-hidden="true"><?php echo $ponto['curto']; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="grafico-dica" role="tooltip" hidden></div>
                    </div>

                    <details class="tabela-dados">
                        <summary>Ver dados em tabela</summary>
                        <div class="table-responsive">
                            <table>
                                <thead><tr><th><?php echo $grafico['porDia'] ? 'Dia' : 'Mês'; ?></th><th class="num">Pedidos</th><th class="num">Faturamento</th></tr></thead>
                                <tbody>
                                    <?php foreach ($serie as $ponto): ?>
                                        <tr><td><?php echo $ponto['longo']; ?></td><td class="num"><?php echo $ponto['pedidos']; ?></td><td class="num"><?php echo moeda($ponto['valor']); ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endif; ?>
            </section>

            <div class="paineis paineis-tres">
                <section class="painel">
                    <div class="painel-cabecalho">
                        <h3><?php echo icone('lista'); ?> Pedidos por status</h3>
                        <a href="pedidos.php?<?php echo htmlspecialchars($queryPeriodo); ?>" class="modulo-link">Ver pedidos <span aria-hidden="true">→</span></a>
                    </div>
                    <?php if ($totalStatus === 0): ?>
                        <p class="painel-vazio">Nenhum pedido neste período.</p>
                    <?php else: ?>
                        <ul class="ranking">
                            <?php foreach ($porStatus as $status => $s): ?>
                                <?php $pct = $s['pedidos'] / $totalStatus * 100; ?>
                                <li class="ranking-item">
                                    <div class="ranking-linha">
                                        <a href="pedidos.php?<?php echo htmlspecialchars($queryPeriodo); ?>&amp;status=<?php echo $status; ?>" class="ranking-nome"><?php echo badge_status($status); ?></a>
                                        <span class="ranking-valor"><strong><?php echo $s['pedidos']; ?></strong> · <?php echo number_format($pct, 0); ?>% · <?php echo moeda($s['valor']); ?></span>
                                    </div>
                                    <span class="ranking-barra ranking-<?php echo $coresStatus[$status]; ?>" aria-hidden="true"><?php if ($pct > 0): ?><span style="width: <?php echo round($pct, 1); ?>%"></span><?php endif; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="painel">
                    <div class="painel-cabecalho">
                        <h3><?php echo icone('pacote'); ?> Produtos mais vendidos</h3>
                    </div>
                    <?php if (!$produtos): ?>
                        <p class="painel-vazio">Nenhum produto vendido neste período.</p>
                    <?php else: ?>
                        <ol class="ranking">
                            <?php foreach ($produtos as $p): ?>
                                <li class="ranking-item">
                                    <div class="ranking-linha">
                                        <a href="produto_cadastro.php?id=<?php echo $p['ID']; ?>" class="ranking-nome"><?php echo htmlspecialchars($p['NOME']); ?></a>
                                        <span class="ranking-valor"><strong><?php echo (int)$p['QUANTIDADE']; ?> un.</strong> · <?php echo moeda((float)$p['VALOR']); ?><?php if ($p['LUCRO'] !== null): ?> · <span title="Lucro bruto (preço − custo)">lucro <?php echo moeda((float)$p['LUCRO']); ?></span><?php endif; ?></span>
                                    </div>
                                    <span class="ranking-barra" aria-hidden="true"><span style="width: <?php echo round((int)$p['QUANTIDADE'] / $maiorQtd * 100, 1); ?>%"></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>

                <section class="painel">
                    <div class="painel-cabecalho">
                        <h3><?php echo icone('usuarios'); ?> Melhores clientes</h3>
                    </div>
                    <?php if (!$clientes): ?>
                        <p class="painel-vazio">Nenhum cliente comprou neste período.</p>
                    <?php else: ?>
                        <ol class="ranking">
                            <?php foreach ($clientes as $c): ?>
                                <li class="ranking-item">
                                    <div class="ranking-linha">
                                        <a href="cliente_ver.php?id=<?php echo $c['ID']; ?>" class="ranking-nome"><?php echo htmlspecialchars($c['NOME']); ?></a>
                                        <span class="ranking-valor"><strong><?php echo moeda((float)$c['VALOR']); ?></strong> · <?php echo (int)$c['PEDIDOS'] === 1 ? '1 pedido' : (int)$c['PEDIDOS'] . ' pedidos'; ?></span>
                                    </div>
                                    <span class="ranking-barra" aria-hidden="true"><span style="width: <?php echo $maiorCliente > 0 ? round((float)$c['VALOR'] / $maiorCliente * 100, 1) : 0; ?>%"></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </section>
            </div>

            <section class="painel">
                <div class="painel-cabecalho">
                    <h3><?php echo icone('etiqueta'); ?> Vendas por categoria</h3>
                    <a href="categorias.php" class="modulo-link">Categorias <span aria-hidden="true">→</span></a>
                </div>
                <?php if (!$categorias): ?>
                    <p class="painel-vazio">Nenhuma venda neste período.</p>
                <?php else: ?>
                    <ul class="ranking ranking-colunas">
                        <?php foreach ($categorias as $cat): ?>
                            <li class="ranking-item">
                                <div class="ranking-linha">
                                    <span class="ranking-nome"><?php echo htmlspecialchars($cat['NOME']); ?></span>
                                    <span class="ranking-valor"><strong><?php echo moeda((float)$cat['VALOR']); ?></strong> · <?php echo (int)$cat['QUANTIDADE']; ?> un.</span>
                                </div>
                                <span class="ranking-barra" aria-hidden="true"><span style="width: <?php echo $maiorCategoria > 0 ? round((float)$cat['VALOR'] / $maiorCategoria * 100, 1) : 0; ?>%"></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/relatorios.js"></script>
</body>
</html>
