<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';

// Cadastro e edição de produto (mesmo formulário):
//   produto_cadastro.php         → novo produto (INSERT) com estoque inicial
//   produto_cadastro.php?id=ID   → editar nome, descrição e preço (UPDATE)
// Na edição o estoque não é digitado: entradas, saídas e ajustes são feitos em
// produto_estoque.php, que registra o motivo no histórico (movimentos_estoque).

$erros = [];
$toasts = [];
$vazio = ['nome' => '', 'descricao' => '', 'preco' => '', 'estoque' => '0'];
$valores = $vazio;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT NOME, DESCRICAO, PRECO, ESTOQUE FROM produtos WHERE ID = ?');
    $stmt->execute([$id]);
    $produto = $stmt->fetch();
    if (!$produto) {
        flash('error', 'Produto não encontrado.');
        header('Location: produtos.php');
        exit;
    }
    $valores = [
        'nome'      => $produto['NOME'],
        'descricao' => $produto['DESCRICAO'] ?? '',
        'preco'     => number_format((float)$produto['PRECO'], 2, ',', '.'),
        'estoque'   => (string)$produto['ESTOQUE'],
    ];
}
$editando = $id > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'nome'      => trim($_POST['nome'] ?? ''),
        'descricao' => trim($_POST['descricao'] ?? ''),
        'preco'     => trim($_POST['preco'] ?? ''),
        'estoque'   => trim($_POST['estoque'] ?? ''),
    ];

    $preco = converter_moeda($valores['preco']);

    if (mb_strlen($valores['nome']) < 2) {
        $erros['nome'] = 'Informe o nome do produto (mínimo 2 caracteres).';
    }
    if ($preco === null) {
        $erros['preco'] = 'Informe um preço válido, por exemplo 1.499,90.';
    } elseif ($preco > VALOR_MAXIMO) {
        $erros['preco'] = 'O preço máximo é R$ 99.999.999,99.';
    }
    if (!$editando && (!ctype_digit($valores['estoque']) || (int)$valores['estoque'] > 1000000)) {
        $erros['estoque'] = 'O estoque deve ser um número inteiro entre 0 e 1.000.000.';
    }

    if (!$erros) {
        $dados = [$valores['nome'], $valores['descricao'] ?: null, $preco];
        try {
            if ($editando) {
                $stmt = $pdo->prepare('UPDATE produtos SET NOME = ?, DESCRICAO = ?, PRECO = ? WHERE ID = ?');
                $stmt->execute([...$dados, $id]);
                registrar_atividade($pdo, 'ALTEROU', "Alterou o produto '{$valores['nome']}'", 'produto', $id);
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Produto atualizado com sucesso!'];
            } else {
                // Cadastra com estoque 0 e lança o estoque inicial como movimento (fica no histórico)
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO produtos (NOME, DESCRICAO, PRECO, ESTOQUE) VALUES (?, ?, ?, 0)');
                $stmt->execute($dados);
                $novoId = (int)$pdo->lastInsertId();
                movimentar_estoque($pdo, $novoId, (int)$valores['estoque'], 'CADASTRO', 'Estoque inicial no cadastro do produto');
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
            $erros['nome'] = 'Já existe um produto com esse nome.';
        }
    }
}

$campos = [
    'nome'      => ['Nome', true, 'type="text" maxlength="100" placeholder="Ex.: Mouse sem fio"', 'Nome único do produto.'],
    'descricao' => ['Descrição', false, 'type="text" maxlength="255" placeholder="Opcional"', 'Opcional. Detalhes do produto.'],
    'preco'     => ['Preço (R$)', true, 'type="text" inputmode="numeric" placeholder="0,00" data-mascara-moeda autocomplete="off"',
                    'Digite só os números; os centavos entram automaticamente (ex.: 149990 → 1.499,90).'],
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
                               data-confirmar-exclusao="<?php echo htmlspecialchars($valores['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar Produto</a>
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
