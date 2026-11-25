<?php
// Consultas e funções dos relatórios, usadas por relatorios.php (tela) e
// relatorio_exportar.php (arquivos CSV). Em todas, só contam como venda os pedidos
// ABERTO, PAGO e ENVIADO (orçamentos e cancelados ficam de fora).
// Valores por pedido vêm da visão vw_pedidos (já com o desconto). O ranking de produtos soma
// os itens vendidos (valor bruto, antes do desconto do pedido).

require_once __DIR__ . '/lista.php';

/** Atalhos de período: chave da URL => rótulo. */
function periodos_rapidos(): array
{
    return [
        '30d'  => 'Últimos 30 dias',
        '90d'  => 'Últimos 90 dias',
        '12m'  => 'Últimos 12 meses',
        'ano'  => 'Este ano',
        'tudo' => 'Todo o período',
    ];
}

/**
 * Período do relatório a partir da URL: ?periodo=30d (atalho) ou ?de=AAAA-MM-DD&ate=AAAA-MM-DD.
 * Retorna ['de' => 'Y-m-d', 'ate' => 'Y-m-d', 'periodo' => chave do atalho ou '' (personalizado)].
 */
function periodo_relatorio(PDO $pdo): array
{
    $de = data_da_url('de');
    $ate = data_da_url('ate');
    $hoje = date('Y-m-d');

    if ($de || $ate) {
        $de = $de ?? '2000-01-01';
        $ate = $ate ?? $hoje;
        if ($de > $ate) {
            [$de, $ate] = [$ate, $de];
        }
        return ['de' => $de, 'ate' => $ate, 'periodo' => ''];
    }

    $periodo = $_GET['periodo'] ?? '12m';
    if (!is_string($periodo) || !isset(periodos_rapidos()[$periodo])) {
        $periodo = '12m';
    }

    $de = match ($periodo) {
        '30d'  => date('Y-m-d', strtotime('-29 days')),
        '90d'  => date('Y-m-d', strtotime('-89 days')),
        'ano'  => date('Y-01-01'),
        'tudo' => substr((string)($pdo->query('SELECT MIN(CRIADO_EM) FROM pedidos')->fetchColumn() ?: $hoje), 0, 10),
        default => date('Y-m-01', strtotime('first day of -11 months')),
    };

    return ['de' => $de, 'ate' => $hoje, 'periodo' => $periodo];
}

/** Período imediatamente anterior, com a mesma quantidade de dias (para comparar). */
function periodo_anterior(array $p): array
{
    $dias = (int)(new DateTime($p['de']))->diff(new DateTime($p['ate']))->days + 1;
    $ate = (new DateTime($p['de']))->modify('-1 day');
    $de = (clone $ate)->modify('-' . ($dias - 1) . ' days');
    return ['de' => $de->format('Y-m-d'), 'ate' => $ate->format('Y-m-d'), 'periodo' => ''];
}

/** Parâmetros SQL (início e fim do dia) para um período. */
function limites(array $p): array
{
    return [$p['de'] . ' 00:00:00', $p['ate'] . ' 23:59:59'];
}

/** Indicadores gerais de vendas no período. */
function resumo_vendas(PDO $pdo, array $p): array
{
    $venda = "p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO')";
    $stmt = $pdo->prepare(
        "SELECT COUNT(CASE WHEN $venda THEN p.ID END)                         AS PEDIDOS,
                COUNT(CASE WHEN p.STATUS = 'CANCELADO' THEN p.ID END)          AS CANCELADOS,
                COUNT(CASE WHEN p.STATUS = 'ORCAMENTO' THEN p.ID END)          AS ORCAMENTOS,
                COUNT(DISTINCT CASE WHEN $venda THEN p.CLIENTE_ID END)         AS CLIENTES,
                COALESCE(SUM(CASE WHEN $venda THEN p.UNIDADES END), 0)         AS ITENS,
                COALESCE(SUM(CASE WHEN $venda THEN p.DESCONTO END), 0)         AS DESCONTOS,
                COALESCE(SUM(CASE WHEN $venda THEN p.TOTAL END), 0)            AS FATURAMENTO
           FROM vw_pedidos p
          WHERE p.CRIADO_EM BETWEEN ? AND ?"
    );
    $stmt->execute(limites($p));
    $r = $stmt->fetch();
    $r['TICKET'] = $r['PEDIDOS'] > 0 ? $r['FATURAMENTO'] / $r['PEDIDOS'] : 0;

    // Lucro bruto: só dos itens com custo conhecido (custo desconhecido não é tratado como zero).
    // Valor dos itens antes do desconto do pedido, como no ranking de produtos.
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE WHEN i.CUSTO_UNITARIO IS NOT NULL THEN i.QUANTIDADE * i.PRECO_UNITARIO END), 0) AS RECEITA_COM_CUSTO,
                COALESCE(SUM(i.QUANTIDADE * i.CUSTO_UNITARIO), 0)                                              AS CUSTO,
                COUNT(CASE WHEN i.CUSTO_UNITARIO IS NULL THEN 1 END)                                           AS ITENS_SEM_CUSTO
           FROM itens_pedido i
          INNER JOIN pedidos p ON p.ID = i.PEDIDO_ID
          WHERE $venda AND p.CRIADO_EM BETWEEN ? AND ?"
    );
    $stmt->execute(limites($p));
    $r += $stmt->fetch();
    $r['LUCRO'] = $r['RECEITA_COM_CUSTO'] - $r['CUSTO'];
    $r['MARGEM'] = $r['RECEITA_COM_CUSTO'] > 0 ? $r['LUCRO'] / $r['RECEITA_COM_CUSTO'] * 100 : 0;
    return $r;
}

/**
 * Faturamento e nº de pedidos agrupados por dia (períodos de até 31 dias) ou por mês.
 * Dias/meses sem vendas entram com zero, para o gráfico não "pular" datas.
 */
function vendas_por_periodo(PDO $pdo, array $p): array
{
    $dias = (int)(new DateTime($p['de']))->diff(new DateTime($p['ate']))->days + 1;
    $porDia = $dias <= 31;
    $formatoSql = $porDia ? '%Y-%m-%d' : '%Y-%m';

    $stmt = $pdo->prepare(
        "SELECT DATE_FORMAT(p.CRIADO_EM, '$formatoSql') AS CHAVE,
                COUNT(*) AS PEDIDOS,
                COALESCE(SUM(p.TOTAL), 0) AS VALOR
           FROM vw_pedidos p
          WHERE p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO') AND p.CRIADO_EM BETWEEN ? AND ?
          GROUP BY CHAVE"
    );
    $stmt->execute(limites($p));
    $vendas = [];
    foreach ($stmt->fetchAll() as $linha) {
        $vendas[$linha['CHAVE']] = $linha;
    }

    $meses = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $serie = [];
    $atual = new DateTime($porDia ? $p['de'] : substr($p['de'], 0, 7) . '-01');
    $fim = new DateTime($p['ate']);
    while ($atual <= $fim) {
        $chave = $atual->format($porDia ? 'Y-m-d' : 'Y-m');
        $mes = $meses[(int)$atual->format('n') - 1];
        $serie[] = [
            'chave'   => $chave,
            'curto'   => $porDia ? $atual->format('d') : $mes,
            'longo'   => $porDia ? $atual->format('d/m/Y') : $mes . '/' . $atual->format('Y'),
            'valor'   => (float)($vendas[$chave]['VALOR'] ?? 0),
            'pedidos' => (int)($vendas[$chave]['PEDIDOS'] ?? 0),
        ];
        $atual->modify($porDia ? '+1 day' : '+1 month');
    }

    return ['porDia' => $porDia, 'serie' => $serie];
}

/** Quantidade e valor de pedidos por status no período (inclui cancelados). */
function pedidos_por_status(PDO $pdo, array $p): array
{
    $stmt = $pdo->prepare(
        'SELECT p.STATUS, COUNT(*) AS PEDIDOS, COALESCE(SUM(p.TOTAL), 0) AS VALOR
           FROM vw_pedidos p
          WHERE p.CRIADO_EM BETWEEN ? AND ?
          GROUP BY p.STATUS'
    );
    $stmt->execute(limites($p));
    $porStatus = [];
    foreach ($stmt->fetchAll() as $linha) {
        $porStatus[$linha['STATUS']] = $linha;
    }

    // Sempre na mesma ordem, com zero para os status sem pedidos
    $resultado = [];
    foreach (status_pedidos() as $status => $rotulo) {
        $resultado[$status] = [
            'pedidos' => (int)($porStatus[$status]['PEDIDOS'] ?? 0),
            'valor'   => (float)($porStatus[$status]['VALOR'] ?? 0),
        ];
    }
    return $resultado;
}

/** Produtos mais vendidos no período (por quantidade). $limite null = todos. */
function produtos_mais_vendidos(PDO $pdo, array $p, ?int $limite = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT pr.ID, pr.NOME,
                SUM(i.QUANTIDADE) AS QUANTIDADE,
                SUM(i.QUANTIDADE * i.PRECO_UNITARIO) AS VALOR,
                SUM(i.QUANTIDADE * (i.PRECO_UNITARIO - i.CUSTO_UNITARIO)) AS LUCRO,
                COUNT(DISTINCT p.ID) AS PEDIDOS
           FROM itens_pedido i
          INNER JOIN pedidos p ON p.ID = i.PEDIDO_ID
          INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
          WHERE p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO') AND p.CRIADO_EM BETWEEN ? AND ?
          GROUP BY pr.ID, pr.NOME
          ORDER BY QUANTIDADE DESC, VALOR DESC"
        . ($limite ? ' LIMIT ' . (int)$limite : '')
    );
    $stmt->execute(limites($p));
    return $stmt->fetchAll();
}

/**
 * Vendas por categoria de produto no período (valor bruto dos itens).
 * Produtos sem categoria aparecem juntos em "Sem categoria".
 */
function vendas_por_categoria(PDO $pdo, array $p): array
{
    $stmt = $pdo->prepare(
        "SELECT COALESCE(cat.NOME, 'Sem categoria') AS NOME,
                SUM(i.QUANTIDADE) AS QUANTIDADE,
                SUM(i.QUANTIDADE * i.PRECO_UNITARIO) AS VALOR
           FROM itens_pedido i
          INNER JOIN pedidos p ON p.ID = i.PEDIDO_ID
          INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
           LEFT JOIN categorias cat ON cat.ID = pr.CATEGORIA_ID
          WHERE p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO') AND p.CRIADO_EM BETWEEN ? AND ?
          GROUP BY cat.ID, cat.NOME
          ORDER BY VALOR DESC"
    );
    $stmt->execute(limites($p));
    return $stmt->fetchAll();
}

/** Clientes que mais compraram no período (por valor). $limite null = todos. */
function melhores_clientes(PDO $pdo, array $p, ?int $limite = 5): array
{
    $stmt = $pdo->prepare(
        "SELECT c.ID, c.NOME, c.EMAIL,
                COUNT(*) AS PEDIDOS,
                COALESCE(SUM(p.TOTAL), 0) AS VALOR,
                MAX(p.CRIADO_EM) AS ULTIMA
           FROM vw_pedidos p
          INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
          WHERE p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO') AND p.CRIADO_EM BETWEEN ? AND ?
          GROUP BY c.ID, c.NOME, c.EMAIL
          ORDER BY VALOR DESC, PEDIDOS DESC"
        . ($limite ? ' LIMIT ' . (int)$limite : '')
    );
    $stmt->execute(limites($p));
    return $stmt->fetchAll();
}

/**
 * Escala "redonda" para o eixo Y: devolve o topo e o passo das linhas de grade
 * (ex.: máximo 4.206 → topo 5.000, passo 1.000).
 */
function escala_eixo(float $maximo, int $linhas = 4): array
{
    if ($maximo <= 0) {
        return ['topo' => 100.0, 'passo' => 25.0];
    }
    $bruto = $maximo / $linhas;
    $magnitude = 10 ** floor(log10($bruto));
    $normalizado = $bruto / $magnitude;
    $bonito = match (true) {
        $normalizado <= 1   => 1,
        $normalizado <= 2   => 2,
        $normalizado <= 2.5 => 2.5,
        $normalizado <= 5   => 5,
        default             => 10,
    };
    $passo = $bonito * $magnitude;
    return ['topo' => $passo * ceil($maximo / $passo), 'passo' => $passo];
}

/** Valor curto para o eixo: "R$ 2.000" abaixo de 10 mil; "R$ 20 mil", "R$ 1,5 mi" acima. */
function moeda_eixo(float $valor, bool $compacto): string
{
    if (!$compacto) {
        return 'R$ ' . number_format($valor, 0, ',', '.');
    }
    if ($valor >= 1_000_000) {
        return 'R$ ' . rtrim(rtrim(number_format($valor / 1_000_000, 1, ',', '.'), '0'), ',') . ' mi';
    }
    return 'R$ ' . rtrim(rtrim(number_format($valor / 1000, 1, ',', '.'), '0'), ',') . ' mil';
}
