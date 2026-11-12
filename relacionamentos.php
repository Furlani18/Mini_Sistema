<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Página de apresentação do modelo de dados: diagrama das 5 tabelas,
// lista dos relacionamentos (cardinalidade + chave estrangeira + regra ON DELETE),
// quantidade de registros em cada tabela e uma consulta ao vivo juntando as 5.

// ---------- Diagrama (posições em unidades do viewBox do SVG) ----------
$larguraTabela = 210;
$alturaTitulo = 30;
$alturaLinha = 22;

$tabelas = [
    'usuarios' => ['x' => 20,  'y' => 20,  'colunas' => [['PK', 'ID'], ['', 'NOME'], ['UQ', 'EMAIL'], ['', 'SENHA']]],
    'clientes' => ['x' => 570, 'y' => 20,  'colunas' => [['PK', 'ID'], ['', 'NOME'], ['', 'EMAIL'], ['', 'TELEFONE']]],
    'pedidos'  => ['x' => 295, 'y' => 200, 'colunas' => [['PK', 'ID'], ['FK', 'CLIENTE_ID'], ['FK', 'USUARIO_ID'], ['', 'STATUS'], ['', 'CRIADO_EM']]],
    'itens_pedido' => ['x' => 295, 'y' => 420, 'colunas' => [['PK', 'ID'], ['FK', 'PEDIDO_ID'], ['FK', 'PRODUTO_ID'], ['', 'QUANTIDADE'], ['', 'PRECO_UNITARIO']]],
    'produtos' => ['x' => 570, 'y' => 420, 'colunas' => [['PK', 'ID'], ['', 'NOME'], ['', 'PRECO'], ['', 'ESTOQUE']]],
];
$novas = ['pedidos', 'itens_pedido', 'produtos'];

// Centro vertical da linha N (0 = ID) de uma tabela
$linhaY = fn(string $t, int $n) => $tabelas[$t]['y'] + $alturaTitulo + $alturaLinha * $n + $alturaLinha / 2;
$base = fn(string $t) => $tabelas[$t]['y'] + $alturaTitulo + $alturaLinha * count($tabelas[$t]['colunas']) + 6;

// Linhas: [caminho SVG, posição do "1", posição do "N"]
$ligacoes = [
    // usuarios.ID 1 ── N pedidos.USUARIO_ID
    ['M125 ' . $base('usuarios') . ' V' . $linhaY('pedidos', 2) . ' H295', [133, $base('usuarios') + 16], [280, $linhaY('pedidos', 2) - 6]],
    // clientes.ID 1 ── N pedidos.CLIENTE_ID
    ['M675 ' . $base('clientes') . ' V' . $linhaY('pedidos', 1) . ' H505', [683, $base('clientes') + 16], [511, $linhaY('pedidos', 1) - 6]],
    // pedidos.ID 1 ── N itens_pedido.PEDIDO_ID
    ['M400 ' . $base('pedidos') . ' V420', [408, $base('pedidos') + 16], [408, 414]],
    // produtos.ID 1 ── N itens_pedido.PRODUTO_ID
    ['M570 ' . $linhaY('produtos', 0) . ' H538 V' . $linhaY('itens_pedido', 2) . ' H505', [556, $linhaY('produtos', 0) - 6], [511, $linhaY('itens_pedido', 2) - 6]],
];
$alturaSvg = $base('itens_pedido') + 20;

// ---------- Relacionamentos ----------
$relacionamentos = [
    ['clientes', 'pedidos', '1 : N', 'pedidos.CLIENTE_ID → clientes.ID', 'RESTRICT',
        'Um cliente pode fazer vários pedidos; cada pedido pertence a um único cliente. Não é possível apagar um cliente que tem pedidos.'],
    ['usuarios', 'pedidos', '1 : N', 'pedidos.USUARIO_ID → usuarios.ID', 'SET NULL',
        'Um usuário do sistema registra vários pedidos. Se o usuário for apagado, os pedidos continuam, sem "registrado por".'],
    ['pedidos', 'itens_pedido', '1 : N', 'itens_pedido.PEDIDO_ID → pedidos.ID', 'CASCADE',
        'Um pedido tem vários itens. Ao apagar o pedido, os itens são apagados junto, automaticamente.'],
    ['produtos', 'itens_pedido', '1 : N', 'itens_pedido.PRODUTO_ID → produtos.ID', 'RESTRICT',
        'Um produto aparece em vários itens (de pedidos diferentes). Um produto já vendido não pode ser apagado.'],
    ['pedidos', 'produtos', 'N : N', 'através de itens_pedido', '—',
        'Um pedido tem vários produtos e um produto está em vários pedidos. A tabela associativa itens_pedido resolve esse N:N e guarda a quantidade e o preço da venda.'],
];

// ---------- Contagem de registros ----------
$contagem = [];
foreach (array_keys($tabelas) as $tabela) {
    // Nomes vêm da lista fixa acima (nunca do usuário), então é seguro interpolar
    $contagem[$tabela] = (int)$pdo->query("SELECT COUNT(*) FROM `$tabela`")->fetchColumn();
}

// ---------- Consulta ao vivo com as 5 tabelas ----------
$sqlJoin = <<<SQL
SELECT p.ID            AS pedido,
       c.NOME          AS cliente,
       u.NOME          AS registrado_por,
       pr.NOME         AS produto,
       i.QUANTIDADE    AS qtd,
       i.QUANTIDADE * i.PRECO_UNITARIO AS subtotal
  FROM pedidos p
 INNER JOIN clientes     c  ON c.ID  = p.CLIENTE_ID
  LEFT JOIN usuarios     u  ON u.ID  = p.USUARIO_ID
 INNER JOIN itens_pedido i  ON i.PEDIDO_ID  = p.ID
 INNER JOIN produtos     pr ON pr.ID = i.PRODUTO_ID
 ORDER BY p.ID DESC, pr.NOME
 LIMIT 15;
SQL;
$linhasJoin = $pdo->query($sqlJoin)->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relacionamentos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Relacionamentos' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <h2 class="page-title"><?php echo icone('banco'); ?> Modelo de dados — 5 tabelas relacionadas</h2>
                <p class="intro">
                    O banco <code>mini_sistema</code> tem 5 tabelas. <strong>usuarios</strong> e <strong>clientes</strong> já existiam;
                    <strong>produtos</strong>, <strong>pedidos</strong> e <strong>itens_pedido</strong> formam o módulo de vendas e ligam tudo
                    por meio de <strong>chaves estrangeiras (FK)</strong>.
                </p>

                <div class="diagrama">
                    <svg viewBox="0 0 800 <?php echo $alturaSvg; ?>" role="img" aria-labelledby="diagramaTitulo">
                        <title id="diagramaTitulo">Diagrama entidade-relacionamento das tabelas usuarios, clientes, pedidos, itens_pedido e produtos</title>

                        <?php foreach ($ligacoes as [$caminho, $um, $muitos]): ?>
                            <path d="<?php echo $caminho; ?>" class="er-linha"/>
                            <text x="<?php echo $um[0]; ?>" y="<?php echo $um[1]; ?>" class="er-card">1</text>
                            <text x="<?php echo $muitos[0]; ?>" y="<?php echo $muitos[1]; ?>" class="er-card">N</text>
                        <?php endforeach; ?>

                        <?php foreach ($tabelas as $nome => $t): ?>
                            <?php $altura = $alturaTitulo + $alturaLinha * count($t['colunas']) + 6; ?>
                            <g class="er-tabela<?php echo in_array($nome, $novas, true) ? ' er-nova' : ''; ?>">
                                <rect x="<?php echo $t['x']; ?>" y="<?php echo $t['y']; ?>" width="<?php echo $larguraTabela; ?>" height="<?php echo $altura; ?>" rx="8" class="er-caixa"/>
                                <path d="M<?php echo $t['x']; ?> <?php echo $t['y'] + 8; ?> a8 8 0 0 1 8 -8 h<?php echo $larguraTabela - 16; ?> a8 8 0 0 1 8 8 v<?php echo $alturaTitulo - 8; ?> h-<?php echo $larguraTabela; ?> z" class="er-cabecalho"/>
                                <text x="<?php echo $t['x'] + 12; ?>" y="<?php echo $t['y'] + 20; ?>" class="er-nome"><?php echo $nome; ?></text>
                                <text x="<?php echo $t['x'] + $larguraTabela - 12; ?>" y="<?php echo $t['y'] + 20; ?>" class="er-qtd" text-anchor="end"><?php echo $contagem[$nome]; ?> reg.</text>
                                <?php foreach ($t['colunas'] as $n => [$chave, $coluna]): ?>
                                    <?php $y = $t['y'] + $alturaTitulo + $alturaLinha * $n + 16; ?>
                                    <?php if ($chave): ?>
                                        <text x="<?php echo $t['x'] + 12; ?>" y="<?php echo $y; ?>" class="er-chave er-chave-<?php echo strtolower($chave); ?>"><?php echo $chave; ?></text>
                                    <?php endif; ?>
                                    <text x="<?php echo $t['x'] + 44; ?>" y="<?php echo $y; ?>" class="er-coluna"><?php echo $coluna; ?></text>
                                <?php endforeach; ?>
                            </g>
                        <?php endforeach; ?>
                    </svg>
                    <p class="legenda">
                        <span><strong class="er-chave-pk">PK</strong> chave primária</span>
                        <span><strong class="er-chave-fk">FK</strong> chave estrangeira</span>
                        <span><strong class="er-chave-uq">UQ</strong> valor único</span>
                        <span><span class="legenda-nova"></span> tabela nova</span>
                        <span><strong>1 — N</strong> um para muitos</span>
                    </p>
                </div>
            </div>

            <div class="list-container">
                <h2 class="page-title"><?php echo icone('lista'); ?> Relacionamentos</h2>
                <div class="table-responsive">
                    <table class="client-table">
                        <thead>
                            <tr>
                                <th>Tabelas</th>
                                <th>Tipo</th>
                                <th>Chave estrangeira</th>
                                <th>Ao apagar o "pai"</th>
                                <th>Significado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relacionamentos as [$a, $b, $tipo, $fk, $regra, $descricao]): ?>
                                <tr>
                                    <td class="cell-client"><span class="client-name"><code><?php echo $a; ?></code> → <code><?php echo $b; ?></code></span></td>
                                    <td data-label="Tipo"><span class="badge badge-tipo"><?php echo $tipo; ?></span></td>
                                    <td data-label="Chave estrangeira"><code><?php echo $fk; ?></code></td>
                                    <td data-label="Ao apagar"><code><?php echo $regra; ?></code></td>
                                    <td data-label="Significado"><?php echo $descricao; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="list-container">
                <h2 class="page-title"><?php echo icone('busca'); ?> Consulta ao vivo juntando as 5 tabelas</h2>
                <pre class="sql"><code><?php echo htmlspecialchars($sqlJoin); ?></code></pre>

                <?php if (!$linhasJoin): ?>
                    <div class="empty-message">
                        <p>Nenhum pedido com itens ainda. <a href="pedido_novo.php" class="text-link">Registre um pedido</a> para ver o resultado.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <th>Pedido <small>(pedidos)</small></th>
                                    <th>Cliente <small>(clientes)</small></th>
                                    <th>Registrado por <small>(usuarios)</small></th>
                                    <th>Produto <small>(produtos)</small></th>
                                    <th class="num">Qtd <small>(itens_pedido)</small></th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($linhasJoin as $l): ?>
                                    <tr>
                                        <td class="cell-client"><a href="pedido_ver.php?id=<?php echo $l['pedido']; ?>" class="text-link">#<?php echo $l['pedido']; ?></a></td>
                                        <td data-label="Cliente"><?php echo htmlspecialchars($l['cliente']); ?></td>
                                        <td data-label="Registrado por"><?php echo $l['registrado_por'] ? htmlspecialchars($l['registrado_por']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td data-label="Produto"><?php echo htmlspecialchars($l['produto']); ?></td>
                                        <td class="num" data-label="Qtd"><?php echo (int)$l['qtd']; ?></td>
                                        <td class="num" data-label="Subtotal"><?php echo moeda((float)$l['subtotal']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
