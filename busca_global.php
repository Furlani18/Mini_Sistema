<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Busca global (atalho Ctrl+K em qualquer tela): procura clientes, produtos, pedidos
// e fornecedores de uma vez e responde em JSON para o js/busca.js.
//   busca_global.php?q=texto
// Cada grupo traz até 5 resultados: [titulo, detalhe, url].

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$q = busca_da_url();
if (mb_strlen($q) < 2) {
    echo json_encode(['grupos' => []]);
    exit;
}
$termo = like($q);
$digitos = preg_replace('/\D/', '', $q);
$numero = ltrim($q, '#');

$buscar = function (string $sql, array $parametros) use ($pdo): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    return $stmt->fetchAll();
};

$grupos = [];

// Clientes: nome, email, telefone ou CPF/CNPJ
$clientes = $buscar(
    'SELECT ID, NOME, EMAIL, TELEFONE FROM clientes
      WHERE NOME LIKE ? OR EMAIL LIKE ? OR TELEFONE LIKE ?' . (strlen($digitos) >= 3 ? ' OR CPF_CNPJ LIKE ?' : '') . '
      ORDER BY NOME LIMIT 5',
    strlen($digitos) >= 3 ? [$termo, $termo, $termo, like($digitos)] : [$termo, $termo, $termo]
);
if ($clientes) {
    $grupos[] = ['titulo' => 'Clientes', 'icone' => 'usuarios', 'itens' => array_map(fn($c) => [
        'titulo'  => $c['NOME'],
        'detalhe' => $c['EMAIL'] . ($c['TELEFONE'] ? ' · ' . $c['TELEFONE'] : ''),
        'url'     => 'cliente_ver.php?id=' . $c['ID'],
    ], $clientes)];
}

// Produtos: nome, SKU ou código de barras
$produtos = $buscar(
    'SELECT ID, NOME, SKU, PRECO, ESTOQUE FROM produtos
      WHERE NOME LIKE ? OR SKU LIKE ? OR CODIGO_BARRAS = ?
      ORDER BY NOME LIMIT 5',
    [$termo, $termo, $q]
);
if ($produtos) {
    $grupos[] = ['titulo' => 'Produtos', 'icone' => 'pacote', 'itens' => array_map(fn($p) => [
        'titulo'  => $p['NOME'],
        'detalhe' => ($p['SKU'] ? 'SKU ' . $p['SKU'] . ' · ' : '') . moeda((float)$p['PRECO']) . ' · ' . (int)$p['ESTOQUE'] . ' em estoque',
        'url'     => 'produto_estoque.php?id=' . $p['ID'],
    ], $produtos)];
}

// Pedidos: número (#12) ou nome do cliente
$pedidos = $buscar(
    'SELECT p.ID, p.STATUS, p.TOTAL, p.CRIADO_EM, c.NOME AS CLIENTE
       FROM vw_pedidos p INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
      WHERE ' . (ctype_digit($numero) ? 'p.ID = ? OR ' : '') . 'c.NOME LIKE ?
      ORDER BY p.ID DESC LIMIT 5',
    ctype_digit($numero) ? [(int)$numero, $termo] : [$termo]
);
if ($pedidos) {
    $grupos[] = ['titulo' => 'Pedidos', 'icone' => 'carrinho', 'itens' => array_map(fn($p) => [
        'titulo'  => ($p['STATUS'] === 'ORCAMENTO' ? 'Orçamento' : 'Pedido') . ' #' . $p['ID'] . ' · ' . $p['CLIENTE'],
        'detalhe' => status_pedidos()[$p['STATUS']] . ' · ' . moeda((float)$p['TOTAL']) . ' · ' . date('d/m/Y', strtotime($p['CRIADO_EM'])),
        'url'     => 'pedido_ver.php?id=' . $p['ID'],
    ], $pedidos)];
}

// Fornecedores
$fornecedores = $buscar('SELECT ID, NOME, CONTATO FROM fornecedores WHERE NOME LIKE ? OR CONTATO LIKE ? ORDER BY NOME LIMIT 5', [$termo, $termo]);
if ($fornecedores) {
    $grupos[] = ['titulo' => 'Fornecedores', 'icone' => 'caminhao', 'itens' => array_map(fn($f) => [
        'titulo'  => $f['NOME'],
        'detalhe' => $f['CONTATO'] ?: 'Fornecedor',
        'url'     => 'fornecedor_cadastro.php?id=' . $f['ID'],
    ], $fornecedores)];
}

// O ícone vai pronto (SVG) para o JavaScript não precisar conhecer o conjunto de ícones
foreach ($grupos as &$grupo) {
    $grupo['icone'] = icone($grupo['icone']);
}
unset($grupo);

echo json_encode(['grupos' => $grupos], JSON_UNESCAPED_UNICODE);
