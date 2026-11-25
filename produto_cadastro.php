<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';
require_once 'validacao.php';

// Cadastro e edição de produto (mesmo formulário):
//   produto_cadastro.php         → novo produto (INSERT) com estoque inicial
//   produto_cadastro.php?id=ID   → editar nome, descrição, categoria, códigos, preço e custo (UPDATE)
// O custo é o custo médio de compra; as entradas de estoque com custo informado o recalculam.
// Na edição o estoque não é digitado: entradas, saídas e ajustes são feitos em
// produto_estoque.php, que registra o motivo no histórico (movimentos_estoque).

$erros = [];
$toasts = [];
$vazio = ['nome' => '', 'descricao' => '', 'categoria_id' => '', 'sku' => '', 'codigo_barras' => '', 'preco' => '', 'custo' => '', 'estoque' => '0'];
$valores = $vazio;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$categorias = $pdo->query('SELECT ID, NOME FROM categorias ORDER BY NOME')->fetchAll();
$formatarValor = fn($v) => $v === null ? '' : number_format((float)$v, 2, ',', '.');

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT NOME, DESCRICAO, CATEGORIA_ID, SKU, CODIGO_BARRAS, PRECO, CUSTO, ESTOQUE FROM produtos WHERE ID = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();
    if (!$produto) {
        flash('error', 'Produto não encontrado.');
        header('Location: produtos.php');
        exit;
    }
    $valores = [
        'nome'          => $produto['NOME'],
        'descricao'     => $produto['DESCRICAO'] ?? '',
        'categoria_id'  => (string)($produto['CATEGORIA_ID'] ?? ''),
        'sku'           => $produto['SKU'] ?? '',
        'codigo_barras' => $produto['CODIGO_BARRAS'] ?? '',
        'preco'         => $formatarValor($produto['PRECO']),
        'custo'         => $formatarValor($produto['CUSTO']),
        'estoque'       => (string)$produto['ESTOQUE'],
    ];
}
$editando = $id > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [];
    foreach (array_keys($vazio) as $campo) {
        $valores[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $valores['codigo_barras'] = preg_replace('/\D/', '', $valores['codigo_barras']);

    $preco = converter_moeda($valores['preco']);
    $custo = $valores['custo'] === '' ? null : converter_moeda($valores['custo']);
    $categoriaId = $valores['categoria_id'] === '' ? null : (int)$valores['categoria_id'];

    if (mb_strlen($valores['nome']) < 2) {
        $erros['nome'] = 'Informe o nome do produto (mínimo 2 caracteres).';
    }
    if ($preco === null) {
        $erros['preco'] = 'Informe um preço válido, por exemplo 1.499,90.';
    } elseif ($preco > VALOR_MAXIMO) {
        $erros['preco'] = 'O preço máximo é R$ 99.999.999,99.';
    }
    if ($valores['custo'] !== '' && ($custo === null || $custo > VALOR_MAXIMO)) {
        $erros['custo'] = 'Informe um custo válido, por exemplo 899,90, ou deixe em branco.';
    }
    if ($categoriaId !== null && !in_array($categoriaId, array_map('intval', array_column($categorias, 'ID')), true)) {
        $erros['categoria_id'] = 'Escolha uma categoria da lista.';
    }
    if ($valores['sku'] !== '' && !sku_valido($valores['sku'])) {
        $erros['sku'] = 'Use só letras, números, ponto, hífen ou sublinhado (até 40).';
    }
    if ($valores['codigo_barras'] !== '' && !gtin_valido($valores['codigo_barras'])) {
        $erros['codigo_barras'] = 'Código de barras inválido. Confira os 8, 12, 13 ou 14 dígitos.';
    }
    if (!$editando && (!ctype_digit($valores['estoque']) || (int)$valores['estoque'] > 1000000)) {
        $erros['estoque'] = 'O estoque deve ser um número inteiro entre 0 e 1.000.000.';
    }

    if (!$erros) {
        $dados = [
            $valores['nome'], $valores['descricao'] ?: null, $categoriaId,
            $valores['sku'] ?: null, $valores['codigo_barras'] ?: null, $preco, $custo,
        ];
        try {
            if ($editando) {
                $stmt = $pdo->prepare(
                    'UPDATE produtos SET NOME = ?, DESCRICAO = ?, CATEGORIA_ID = ?, SKU = ?, CODIGO_BARRAS = ?, PRECO = ?, CUSTO = ? WHERE ID = ?'
                );
                $stmt->execute([...$dados, $id]);
                registrar_atividade($pdo, 'ALTEROU', "Alterou o produto '{$valores['nome']}'", 'produto', $id);
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Produto atualizado com sucesso!'];
            } else {
                // Cadastra com estoque 0 e lança o estoque inicial como movimento (fica no histórico)
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO produtos (NOME, DESCRICAO, CATEGORIA_ID, SKU, CODIGO_BARRAS, PRECO, CUSTO, ESTOQUE) VALUES (?, ?, ?, ?, ?, ?, ?, 0)'
                );
                $stmt->execute($dados);
                $novoId = (int)$pdo->lastInsertId();
                movimentar_estoque($pdo, $novoId, (int)$valores['estoque'], 'CADASTRO', 'Estoque inicial no cadastro do produto', null, null, $custo);
                $pdo->commit();
                registrar_atividade($pdo, 'CRIOU', "Cadastrou o produto '{$valores['nome']}'", 'produto', $novoId);
                $toasts[] = ['tipo' => 'success', 'mensagem' => "Produto '{$valores['nome']}' cadastrado com sucesso!"];
                $valores = $vazio;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            // Descobre qual chave única foi violada pela mensagem do banco
            $mensagem = $e->getMessage();
            if (str_contains($mensagem, 'UQ_PRODUTOS_SKU')) {
                $erros['sku'] = 'Já existe um produto com esse SKU.';
            } elseif (str_contains($mensagem, 'UQ_PRODUTOS_CODIGO_BARRAS')) {
                $erros['codigo_barras'] = 'Já existe um produto com esse código de barras.';
            } else {
                $erros['nome'] = 'Já existe um produto com esse nome.';
            }
        }
    }
}

$campos = [
    'nome'      => ['Nome', true, 'type="text" maxlength="100" placeholder="Ex.: Mouse sem fio"', 'Nome único do produto.'],
    'descricao' => ['Descrição', false, 'type="text" maxlength="255" placeholder="Opcional"', 'Opcional. Detalhes do produto.'],
    'sku'       => ['SKU (código interno)', false, 'type="text" maxlength="40" placeholder="Ex.: MOU-001" autocomplete="off"',
                    'Opcional. Usado para achar o produto rápido no pedido.'],
    'codigo_barras' => ['Código de barras', false, 'type="text" inputmode="numeric" maxlength="14" placeholder="EAN-13, ex.: 7891234567895" autocomplete="off"',
                    'Opcional. Pode ser lido com um leitor de código de barras.'],
    'preco'     => ['Preço de venda (R$)', true, 'type="text" inputmode="numeric" placeholder="0,00" data-mascara-moeda autocomplete="off"',
                    'Digite só os números; os centavos entram automaticamente (ex.: 149990 → 1.499,90).'],
    'custo'     => ['Custo (R$)', false, 'type="text" inputmode="numeric" placeholder="0,00" data-mascara-moeda autocomplete="off"',
                    'Opcional. Quanto o produto custa para você; usado no cálculo do lucro.'],
    'estoque'   => ['Estoque inicial', true, 'type="number" min="0" max="1000000" step="1"', 'Quantidade disponível para venda.'],
];
if ($editando) {
    unset($campos['estoque']); // na edição, o estoque é movimentado em produto_estoque.php
}
$titulo = $editando ? 'Editar Produto' : 'Cadastrar Produto';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Produtos' => 'produtos.php', $titulo => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title">
                    <?php echo icone($editando ? 'editar' : 'pacote'); ?>
                    <?php echo $titulo; ?>
                    <?php if ($editando): ?><span class="page-title-id">#<?php echo $id; ?></span><?php endif; ?>
                </h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível salvar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?php echo csrf_campo(); ?>
                    <div class="form-grid">
                        <?php foreach ($campos as $campo => [$rotulo, $obrigatorio, $atributos, $ajuda]): ?>
                            <?php $erro = $erros[$campo] ?? ''; ?>
                            <div class="form-group<?php echo $erro ? ' has-error' : ''; ?>">
                                <label for="<?php echo $campo; ?>">
                                    <?php echo $rotulo; ?>
                                    <?php if ($obrigatorio): ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
                                </label>
                                <input id="<?php echo $campo; ?>" name="<?php echo $campo; ?>"
                                       value="<?php echo htmlspecialchars($valores[$campo]); ?>"
                                       aria-describedby="<?php echo $campo; ?>-ajuda <?php echo $campo; ?>-erro"
                                       <?php echo $atributos; ?>
                                       <?php echo $obrigatorio ? 'required' : ''; ?>
                                       <?php echo $erro ? 'aria-invalid="true"' : ''; ?>>
                                <small class="field-help" id="<?php echo $campo; ?>-ajuda"><?php echo $ajuda; ?></small>
                                <small class="field-error" id="<?php echo $campo; ?>-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erro); ?></span></small>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-group<?php echo isset($erros['categoria_id']) ? ' has-error' : ''; ?>">
                            <label for="categoria_id">Categoria</label>
                            <select id="categoria_id" name="categoria_id" aria-describedby="categoria_id-ajuda">
                                <option value="">Sem categoria</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?php echo $cat['ID']; ?>" <?php echo (string)$cat['ID'] === $valores['categoria_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['NOME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help" id="categoria_id-ajuda"><a href="categorias.php" class="text-link">Gerenciar categorias</a></small>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['categoria_id'] ?? ''); ?></span></small>
                        </div>
                        <?php
                        $precoAtual = converter_moeda($valores['preco']);
                        $custoAtual = $valores['custo'] !== '' ? converter_moeda($valores['custo']) : null;
                        ?>
                        <?php if ($precoAtual && $custoAtual !== null): ?>
                            <div class="form-group">
                                <span class="rotulo-campo">Margem</span>
                                <div class="estoque-atual">
                                    <strong><?php echo number_format(($precoAtual - $custoAtual) / $precoAtual * 100, 1, ',', '.'); ?>%</strong>
                                    <span class="text-muted">Lucro de <?php echo moeda($precoAtual - $custoAtual); ?> por unidade</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($editando): ?>
                            <div class="form-group">
                                <span class="rotulo-campo">Estoque atual</span>
                                <div class="estoque-atual">
                                    <strong><?php echo (int)$produto['ESTOQUE']; ?> un.</strong>
                                    <a href="produto_estoque.php?id=<?php echo $id; ?>" class="btn btn-outline btn-sm"><?php echo icone('movimento'); ?> Movimentar / histórico</a>
                                </div>
                                <small class="field-help">Entradas, saídas e ajustes ficam registrados no histórico.</small>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Produto'; ?></button>
                        <a href="produtos.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Produtos</a>
                        <?php if ($editando): ?>
                            <?php if (eh_admin()): ?>
                            <a href="produto_apagar.php?id=<?php echo $id; ?>" class="btn btn-danger-outline"
                               data-lixeira data-confirmar-exclusao="<?php echo htmlspecialchars($valores['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar Produto</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/moeda.js"></script>
</body>
</html>
