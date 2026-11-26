<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';
require_once 'validacao.php';
require_once 'loja_funcoes.php';

// Cadastro e edição de produto (mesmo formulário):
//   produto_cadastro.php         → novo produto (INSERT) com estoque inicial
//   produto_cadastro.php?id=ID   → editar nome, descrição, categoria, códigos, preço e custo (UPDATE)
// O custo é o custo médio de compra; as entradas de estoque com custo informado o recalculam.
// Loja online: foto (enviada já reduzida pelo navegador — js/foto.js — e conferida em
// loja_funcoes.php), "Mostrar na loja" e "Destaque" (aparece no topo da vitrine).
// Na edição o estoque não é digitado: entradas, saídas e ajustes são feitos em
// produto_estoque.php, que registra o motivo no histórico (movimentos_estoque).

$erros = [];
$toasts = [];
$vazio = ['nome' => '', 'descricao' => '', 'categoria_id' => '', 'sku' => '', 'codigo_barras' => '', 'preco' => '', 'custo' => '', 'estoque' => '0',
          'na_loja' => '1', 'destaque' => ''];
$fotoAtual = null;
$valores = $vazio;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$categorias = $pdo->query('SELECT ID, NOME FROM categorias ORDER BY NOME')->fetchAll();
$formatarValor = fn($v) => $v === null ? '' : number_format((float)$v, 2, ',', '.');

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT NOME, DESCRICAO, CATEGORIA_ID, SKU, CODIGO_BARRAS, PRECO, CUSTO, ESTOQUE, FOTO, NA_LOJA, DESTAQUE FROM produtos WHERE ID = ?');
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
        'na_loja'       => $produto['NA_LOJA'] ? '1' : '',
        'destaque'      => $produto['DESTAQUE'] ? '1' : '',
    ];
    $fotoAtual = $produto['FOTO'];
}
$editando = $id > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [];
    foreach (array_keys($vazio) as $campo) {
        $valores[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $valores['codigo_barras'] = preg_replace('/\D/', '', $valores['codigo_barras']);
    $valores['na_loja'] = isset($_POST['na_loja']) ? '1' : '';
    $valores['destaque'] = isset($_POST['destaque']) ? '1' : '';
    $removerFoto = isset($_POST['remover_foto']);

    // Foto nova (opcional): confere o arquivo antes de gravar qualquer coisa
    $fotoEnviada = $_FILES['foto'] ?? null;
    $extFoto = null;
    if (is_array($fotoEnviada) && (int)($fotoEnviada['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $conferencia = foto_validar($fotoEnviada);
        if (isset($conferencia['erro'])) {
            $erros['foto'] = $conferencia['erro'];
        } else {
            $extFoto = $conferencia['ext'];
        }
    }

    $preco = converter_moeda($valores['preco']);
    $custo = $valores['custo'] === '' ? null : converter_moeda($valores['custo']);
    $categoriaId = $valores['categoria_id'] === '' ? null : (int)$valores['categoria_id'];

    if (mb_strlen($valores['descricao']) > 1000) {
        $erros['descricao'] = 'A descrição pode ter até 1000 caracteres.';
    }
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
            (int)($valores['na_loja'] === '1'), (int)($valores['destaque'] === '1'),
        ];
        try {
            if ($editando) {
                $stmt = $pdo->prepare(
                    'UPDATE produtos SET NOME = ?, DESCRICAO = ?, CATEGORIA_ID = ?, SKU = ?, CODIGO_BARRAS = ?, PRECO = ?, CUSTO = ?,
                            NA_LOJA = ?, DESTAQUE = ? WHERE ID = ?'
                );
                $stmt->execute([...$dados, $id]);
                $fotoAtual = produto_trocar_foto($pdo, $id, $fotoAtual, $extFoto ? $fotoEnviada : null, $extFoto, $removerFoto);
                registrar_atividade($pdo, 'ALTEROU', "Alterou o produto '{$valores['nome']}'", 'produto', $id);
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Produto atualizado com sucesso!'];
            } else {
                // Cadastra com estoque 0 e lança o estoque inicial como movimento (fica no histórico)
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO produtos (NOME, DESCRICAO, CATEGORIA_ID, SKU, CODIGO_BARRAS, PRECO, CUSTO, NA_LOJA, DESTAQUE, ESTOQUE)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
                );
                $stmt->execute($dados);
                $novoId = (int)$pdo->lastInsertId();
                movimentar_estoque($pdo, $novoId, (int)$valores['estoque'], 'CADASTRO', 'Estoque inicial no cadastro do produto', null, null, $custo);
                $pdo->commit();
                produto_trocar_foto($pdo, $novoId, null, $extFoto ? $fotoEnviada : null, $extFoto, false);
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

if ($erros && isset($extFoto) && !isset($erros['foto'])) {
    $erros['foto'] = 'Por segurança, selecione a foto de novo antes de salvar.';
}

$campos = [
    'nome'      => ['Nome', true, 'type="text" maxlength="100" placeholder="Ex.: Mouse sem fio"', 'Nome único do produto.'],
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

                <form method="POST" action="" enctype="multipart/form-data" novalidate>
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo FOTO_TAMANHO_MAXIMO; ?>">
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

                    <div class="form-group<?php echo isset($erros['descricao']) ? ' has-error' : ''; ?>">
                        <label for="descricao">Descrição</label>
                        <textarea id="descricao" name="descricao" maxlength="1000" rows="4" placeholder="Opcional. Aparece na página do produto na loja online."
                                  aria-describedby="descricao-erro"><?php echo htmlspecialchars($valores['descricao']); ?></textarea>
                        <small class="field-error" id="descricao-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['descricao'] ?? ''); ?></span></small>
                    </div>

                    <fieldset class="produto-loja">
                        <legend><?php echo icone('loja'); ?> Loja online</legend>
                        <div class="produto-loja-grade">
                            <div class="produto-foto-campo<?php echo isset($erros['foto']) ? ' has-error' : ''; ?>" data-foto>
                                <div class="produto-foto-previa" data-foto-previa>
                                    <?php if ($url = foto_url($fotoAtual)): ?>
                                        <img src="<?php echo htmlspecialchars($url); ?>" alt="Foto atual do produto">
                                    <?php else: ?>
                                        <span class="produto-foto-vazia"><?php echo icone('imagem'); ?><small>Sem foto</small></span>
                                    <?php endif; ?>
                                </div>
                                <div class="produto-foto-acoes">
                                    <label for="foto" class="btn btn-outline btn-sm"><?php echo icone('upload'); ?> <?php echo $fotoAtual ? 'Trocar foto' : 'Escolher foto'; ?></label>
                                    <input type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" class="visually-hidden"
                                           aria-describedby="foto-ajuda foto-erro" data-foto-campo>
                                    <?php if ($fotoAtual): ?>
                                        <label class="checkbox-linha"><input type="checkbox" name="remover_foto" value="1" data-foto-remover> Remover foto</label>
                                    <?php endif; ?>
                                    <small class="field-help" id="foto-ajuda" data-foto-status>JPG, PNG ou WebP. Fotos grandes são reduzidas automaticamente.</small>
                                    <small class="field-error" id="foto-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['foto'] ?? ''); ?></span></small>
                                </div>
                            </div>
                            <div class="produto-loja-opcoes">
                                <label class="checkbox-linha">
                                    <input type="checkbox" name="na_loja" value="1" <?php echo $valores['na_loja'] === '1' ? 'checked' : ''; ?>>
                                    <span><strong>Mostrar na loja</strong><small>Desmarque para vender só pelo sistema.</small></span>
                                </label>
                                <label class="checkbox-linha">
                                    <input type="checkbox" name="destaque" value="1" <?php echo $valores['destaque'] === '1' ? 'checked' : ''; ?>>
                                    <span><strong>Destaque</strong><small>Aparece no topo da página inicial da loja.</small></span>
                                </label>
                                <?php if ($editando && $valores['na_loja'] === '1'): ?>
                                    <a href="loja_produto.php?id=<?php echo $id; ?>" class="text-link" target="_blank" rel="noopener"><?php echo icone('olho'); ?> Ver na loja</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </fieldset>

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
    <script src="js/foto.js"></script>
</body>
</html>
