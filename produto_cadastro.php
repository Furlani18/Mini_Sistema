<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Cadastro e edição de produto (mesmo formulário):
//   produto_cadastro.php         → novo produto (INSERT)
//   produto_cadastro.php?id=ID   → editar produto existente (UPDATE)

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
        'preco'     => number_format((float)$produto['PRECO'], 2, ',', ''),
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

    // Aceita "1.234,50", "1234,50" e "1234.50"
    $precoTexto = str_contains($valores['preco'], ',')
        ? str_replace(['.', ','], ['', '.'], $valores['preco'])
        : $valores['preco'];

    if (mb_strlen($valores['nome']) < 2) {
        $erros['nome'] = 'Informe o nome do produto (mínimo 2 caracteres).';
    }
    if (!is_numeric($precoTexto) || (float)$precoTexto < 0) {
        $erros['preco'] = 'Informe um preço válido, por exemplo 199,90.';
    }
    if (!ctype_digit($valores['estoque'])) {
        $erros['estoque'] = 'O estoque deve ser um número inteiro maior ou igual a zero.';
    }

    if (!$erros) {
        $dados = [$valores['nome'], $valores['descricao'] ?: null, round((float)$precoTexto, 2), (int)$valores['estoque']];
        try {
            if ($editando) {
                $stmt = $pdo->prepare('UPDATE produtos SET NOME = ?, DESCRICAO = ?, PRECO = ?, ESTOQUE = ? WHERE ID = ?');
                $stmt->execute([...$dados, $id]);
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Produto atualizado com sucesso!'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO produtos (NOME, DESCRICAO, PRECO, ESTOQUE) VALUES (?, ?, ?, ?)');
                $stmt->execute($dados);
                $toasts[] = ['tipo' => 'success', 'mensagem' => "Produto '{$valores['nome']}' cadastrado com sucesso!"];
                $valores = $vazio;
            }
        } catch (PDOException $e) {
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
    'preco'     => ['Preço (R$)', true, 'type="text" inputmode="decimal" placeholder="0,00"', 'Use vírgula para os centavos, ex.: 199,90.'],
    'estoque'   => ['Estoque', true, 'type="number" min="0" step="1"', 'Quantidade disponível para venda.'],
];
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
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Produto'; ?></button>
                        <a href="produtos.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Produtos</a>
                        <?php if ($editando): ?>
                            <a href="produto_apagar.php?id=<?php echo $id; ?>" class="btn btn-danger-outline"
                               data-confirmar-exclusao="<?php echo htmlspecialchars($valores['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar Produto</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
