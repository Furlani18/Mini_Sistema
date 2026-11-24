<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';
require_once 'estoque.php';

// Estoque de um produto: lançar entrada (chegou mercadoria), saída (perda, uso interno…)
// ou ajuste para um valor exato (contagem/inventário), sempre com motivo,
// e ver o histórico completo de movimentações (tabela movimentos_estoque).
//   produto_estoque.php?id=ID

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$buscar = $pdo->prepare('SELECT ID, NOME, PRECO, ESTOQUE FROM produtos WHERE ID = ?');
$buscar->execute([$id]);
$produto = $buscar->fetch();
if (!$produto) {
    flash('error', 'Produto não encontrado.');
    header('Location: produtos.php');
    exit;
}

$operacoes = [
    'ENTRADA' => 'Entrada (somar ao estoque)',
    'SAIDA'   => 'Saída (tirar do estoque)',
    'AJUSTE'  => 'Ajuste (definir o valor exato contado)',
];
$valores = ['operacao' => 'ENTRADA', 'quantidade' => '', 'motivo' => ''];
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'operacao'   => (string)($_POST['operacao'] ?? ''),
        'quantidade' => trim((string)($_POST['quantidade'] ?? '')),
        'motivo'     => trim((string)($_POST['motivo'] ?? '')),
    ];

    if (!isset($operacoes[$valores['operacao']])) {
        $erros['operacao'] = 'Escolha o tipo de movimentação.';
    }
    $minimo = $valores['operacao'] === 'AJUSTE' ? 0 : 1;
    if (!ctype_digit($valores['quantidade']) || (int)$valores['quantidade'] < $minimo || (int)$valores['quantidade'] > 1000000) {
        $erros['quantidade'] = $valores['operacao'] === 'AJUSTE'
            ? 'Informe o estoque contado (0 ou mais).'
            : 'Informe uma quantidade inteira maior que zero.';
    }
    if (mb_strlen($valores['motivo']) < 3) {
        $erros['motivo'] = 'Descreva o motivo (ex.: "Nota fiscal 1234", "Produto avariado").';
    }

    if (!$erros) {
        $qtd = (int)$valores['quantidade'];
        $pdo->beginTransaction();
        if ($valores['operacao'] === 'AJUSTE') {
            // Lê o estoque travando a linha, para calcular a diferença com segurança
            $stmt = $pdo->prepare('SELECT ESTOQUE FROM produtos WHERE ID = ? FOR UPDATE');
            $stmt->execute([$id]);
            $variacao = $qtd - (int)$stmt->fetchColumn();
        } else {
            $variacao = $valores['operacao'] === 'ENTRADA' ? $qtd : -$qtd;
        }

        $erro = movimentar_estoque($pdo, $id, $variacao, $valores['operacao'], $valores['motivo']);
        if ($erro !== null) {
            $pdo->rollBack();
            $erros['quantidade'] = 'Estoque insuficiente: ' . $erro . '.';
        } elseif ($variacao === 0) {
            $pdo->rollBack();
            $erros['quantidade'] = 'O estoque já é ' . $qtd . '. Nada a ajustar.';
        } else {
            $pdo->commit();
            $sinal = $variacao > 0 ? '+' . $variacao : (string)$variacao;
            registrar_atividade($pdo, 'ESTOQUE', "Estoque de '{$produto['NOME']}': $sinal un. ({$valores['motivo']})", 'produto', $id);
            flash('success', "Estoque atualizado: $sinal un.");
            header('Location: produto_estoque.php?id=' . $id);
            exit;
        }
    }
}

// Histórico com filtro por tipo e paginação
$tipo = $_GET['tipo'] ?? '';
if (!is_string($tipo) || !isset(tipos_movimento()[$tipo])) {
    $tipo = '';
}
$where = 'WHERE m.PRODUTO_ID = ?' . ($tipo ? ' AND m.TIPO = ?' : '');
$parametros = $tipo ? [$id, $tipo] : [$id];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM movimentos_estoque m $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT m.TIPO, m.QUANTIDADE, m.ESTOQUE_APOS, m.MOTIVO, m.CRIADO_EM, m.PEDIDO_ID, u.NOME AS USUARIO
       FROM movimentos_estoque m
       LEFT JOIN usuarios u ON u.ID = m.USUARIO_ID
       $where
      ORDER BY m.CRIADO_EM DESC, m.ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$movimentos = $stmt->fetchAll();

// Resumo dos últimos 30 dias
$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(CASE WHEN QUANTIDADE > 0 THEN QUANTIDADE END), 0) AS ENTRADAS,
            COALESCE(-SUM(CASE WHEN QUANTIDADE < 0 THEN QUANTIDADE END), 0) AS SAIDAS
       FROM movimentos_estoque
      WHERE PRODUTO_ID = ? AND CRIADO_EM >= NOW() - INTERVAL 30 DAY'
);
$stmt->execute([$id]);
$resumo = $stmt->fetch();
$nome = htmlspecialchars($produto['NOME']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque - <?php echo $nome; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Produtos' => 'produtos.php', $produto['NOME'] => 'produto_cadastro.php?id=' . $id, 'Estoque' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-header">
                <h2 class="page-title"><?php echo icone('movimento'); ?> Estoque de <?php echo $nome; ?></h2>
                <a href="produto_cadastro.php?id=<?php echo $id; ?>" class="btn btn-outline"><?php echo icone('editar'); ?> Editar Produto</a>
            </div>

            <section class="indicadores" aria-label="Resumo do estoque">
                <div class="indicador">
                    <p class="indicador-rotulo">Estoque atual</p>
                    <p class="indicador-valor"><?php echo (int)$produto['ESTOQUE']; ?> un.</p>
                    <p class="indicador-detalhe">
                        <?php if ((int)$produto['ESTOQUE'] === 0): ?><span class="estoque-qtd is-zerado">Esgotado</span>
                        <?php elseif ((int)$produto['ESTOQUE'] <= ESTOQUE_BAIXO): ?><span class="estoque-qtd">Estoque baixo</span>
                        <?php else: ?>Disponível para venda<?php endif; ?>
                    </p>
                </div>
                <div class="indicador">
                    <p class="indicador-rotulo">Valor em estoque</p>
                    <p class="indicador-valor"><?php echo moeda((int)$produto['ESTOQUE'] * (float)$produto['PRECO']); ?></p>
                    <p class="indicador-detalhe">Pelo preço atual (<?php echo moeda((float)$produto['PRECO']); ?>)</p>
                </div>
                <div class="indicador">
                    <p class="indicador-rotulo">Entradas (30 dias)</p>
                    <p class="indicador-valor">+<?php echo (int)$resumo['ENTRADAS']; ?></p>
                    <p class="indicador-detalhe">Compras, devoluções e ajustes</p>
                </div>
                <div class="indicador">
                    <p class="indicador-rotulo">Saídas (30 dias)</p>
                    <p class="indicador-valor">−<?php echo (int)$resumo['SAIDAS']; ?></p>
                    <p class="indicador-detalhe">Vendas, perdas e ajustes</p>
                </div>
            </section>

            <div class="paineis paineis-estoque">
                <section class="form-container">
                    <h3 class="section-title"><?php echo icone('plus'); ?> Nova movimentação</h3>
                    <?php if ($erros): ?>
                        <div class="alert alert-error" role="alert"><?php echo icone('erro'); ?><span>Não foi possível movimentar. Confira os campos.</span></div>
                    <?php endif; ?>
                    <form method="POST" action="" novalidate>
                        <?php echo csrf_campo(); ?>
                        <div class="form-group<?php echo isset($erros['operacao']) ? ' has-error' : ''; ?>">
                            <label for="operacao">Tipo <span class="required" aria-hidden="true">*</span></label>
                            <select id="operacao" name="operacao">
                                <?php foreach ($operacoes as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo $valor === $valores['operacao'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['operacao'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-group<?php echo isset($erros['quantidade']) ? ' has-error' : ''; ?>">
                            <label for="quantidade">Quantidade <span class="required" aria-hidden="true">*</span></label>
                            <input type="number" id="quantidade" name="quantidade" min="0" max="1000000" step="1" required
                                   value="<?php echo htmlspecialchars($valores['quantidade']); ?>" aria-describedby="quantidade-ajuda">
                            <small class="field-help" id="quantidade-ajuda">No ajuste, informe o total contado na prateleira.</small>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['quantidade'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-group<?php echo isset($erros['motivo']) ? ' has-error' : ''; ?>">
                            <label for="motivo">Motivo <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="motivo" name="motivo" maxlength="255" required placeholder="Ex.: Nota fiscal 1234"
                                   value="<?php echo htmlspecialchars($valores['motivo']); ?>">
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['motivo'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Lançar</button>
                        </div>
                    </form>
                </section>

                <section class="list-container">
                    <div class="list-header">
                        <h3 class="section-title"><?php echo icone('historico'); ?> Histórico</h3>
                        <form method="get" class="filtros filtros-inline">
                            <?php echo campos_ocultos(['tipo', 'pagina']); ?>
                            <label for="filtroTipo" class="visually-hidden">Tipo de movimento</label>
                            <select id="filtroTipo" name="tipo" class="filtro-select" data-auto-submit>
                                <option value="">Todos os tipos</option>
                                <?php foreach (tipos_movimento() as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo $valor === $tipo ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="btn btn-outline btn-sm">Filtrar</button></noscript>
                        </form>
                    </div>

                    <?php if (!$movimentos): ?>
                        <p class="no-results is-visible">Nenhuma movimentação<?php echo $tipo ? ' desse tipo' : ''; ?>.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table tabela-movimentos">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Motivo</th>
                                        <th class="num">Qtd.</th>
                                        <th class="num">Saldo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimentos as $m): ?>
                                        <tr>
                                            <td class="cell-client">
                                                <span class="client-name"><?php echo date('d/m/Y H:i', strtotime($m['CRIADO_EM'])); ?></span>
                                                <span class="client-id"><?php echo $m['USUARIO'] ? htmlspecialchars($m['USUARIO']) : 'Sistema'; ?></span>
                                            </td>
                                            <td data-label="Tipo"><span class="badge badge-mov-<?php echo strtolower($m['TIPO']); ?>"><?php echo tipos_movimento()[$m['TIPO']]; ?></span></td>
                                            <td data-label="Motivo">
                                                <?php if ($m['PEDIDO_ID']): ?>
                                                    <a href="pedido_ver.php?id=<?php echo $m['PEDIDO_ID']; ?>" class="text-link"><?php echo htmlspecialchars($m['MOTIVO']); ?></a>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars($m['MOTIVO']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num" data-label="Qtd."><strong class="mov-qtd <?php echo (int)$m['QUANTIDADE'] > 0 ? 'is-entrada' : 'is-saida'; ?>"><?php echo (int)$m['QUANTIDADE'] > 0 ? '+' . (int)$m['QUANTIDADE'] : '−' . abs((int)$m['QUANTIDADE']); ?></strong></td>
                                            <td class="num" data-label="Saldo"><?php echo (int)$m['ESTOQUE_APOS']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php echo controles_paginacao($pag); ?>
                </section>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
