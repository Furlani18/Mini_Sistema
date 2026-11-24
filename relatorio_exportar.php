<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'relatorios_funcoes.php';

// Exporta dados do período em CSV, pronto para abrir no Excel (padrão brasileiro):
// separador ";", vírgula decimal e UTF-8 com BOM (para os acentos aparecerem certo).
//   relatorio_exportar.php?tipo=pedidos|produtos|clientes&de=AAAA-MM-DD&ate=AAAA-MM-DD

$periodo = periodo_relatorio($pdo);
$tipo = $_GET['tipo'] ?? '';

/** Número no formato brasileiro, sem separador de milhar (o Excel entende como número). */
function numero_csv(float $valor): string
{
    return number_format($valor, 2, ',', '');
}

switch ($tipo) {
    case 'pedidos':
        $stmt = $pdo->prepare(
            'SELECT p.ID, p.CRIADO_EM, p.STATUS, c.NOME AS CLIENTE, c.EMAIL, c.CPF_CNPJ, u.NOME AS USUARIO, p.OBSERVACAO,
                    p.FORMA_PAGAMENTO, p.UNIDADES AS ITENS, p.SUBTOTAL, p.DESCONTO, p.TOTAL
               FROM vw_pedidos p
              INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
               LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
              WHERE p.CRIADO_EM BETWEEN ? AND ?
              ORDER BY p.CRIADO_EM, p.ID'
        );
        $stmt->execute(limites($periodo));
        $cabecalho = ['Pedido', 'Data', 'Status', 'Cliente', 'CPF/CNPJ', 'Email do cliente', 'Registrado por', 'Pagamento',
                      'Observação', 'Itens', 'Subtotal (R$)', 'Desconto (R$)', 'Total (R$)'];
        $linhas = array_map(fn($p) => [
            $p['ID'], date('d/m/Y H:i', strtotime($p['CRIADO_EM'])), status_pedidos()[$p['STATUS']] ?? $p['STATUS'],
            $p['CLIENTE'], formatar_documento($p['CPF_CNPJ']), $p['EMAIL'], $p['USUARIO'] ?? '',
            $p['FORMA_PAGAMENTO'] ? formas_pagamento()[$p['FORMA_PAGAMENTO']] : '', $p['OBSERVACAO'] ?? '', (int)$p['ITENS'],
            numero_csv((float)$p['SUBTOTAL']), numero_csv((float)$p['DESCONTO']), numero_csv((float)$p['TOTAL']),
        ], $stmt->fetchAll());
        break;

    case 'produtos':
        $cabecalho = ['Produto', 'Quantidade vendida', 'Pedidos', 'Faturamento (R$)'];
        $linhas = array_map(fn($p) => [
            $p['NOME'], (int)$p['QUANTIDADE'], (int)$p['PEDIDOS'], numero_csv((float)$p['VALOR']),
        ], produtos_mais_vendidos($pdo, $periodo, null));
        break;

    case 'clientes':
        $cabecalho = ['Cliente', 'Email', 'Pedidos', 'Total comprado (R$)', 'Última compra'];
        $linhas = array_map(fn($c) => [
            $c['NOME'], $c['EMAIL'], (int)$c['PEDIDOS'], numero_csv((float)$c['VALOR']), date('d/m/Y', strtotime($c['ULTIMA'])),
        ], melhores_clientes($pdo, $periodo, null));
        break;

    default:
        flash('error', 'Tipo de exportação inválido.');
        header('Location: relatorios.php');
        exit;
}

$arquivo = sprintf('%s_%s_a_%s.csv', $tipo, $periodo['de'], $periodo['ate']);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: no-store');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM: faz o Excel reconhecer UTF-8
fputcsv($saida, $cabecalho, ';');
foreach ($linhas as $linha) {
    // Evita "injeção de fórmula": texto começando com = + - @ seria executado pelo Excel
    $linha = array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $linha);
    fputcsv($saida, $linha, ';');
}
fclose($saida);
