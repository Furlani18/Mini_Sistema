<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Cadastro, edição e listagem de produtos na mesma página.
//   GET  produtos.php            → formulário vazio (novo produto) + lista
//   GET  produtos.php?editar=ID  → formulário preenchido para edição
//   POST produtos.php            → INSERT (sem id) ou UPDATE (com id)

$erros = [];
$toasts = [];
$vazio = ['id' => 0, 'nome' => '', 'descricao' => '', 'preco' => '', 'estoque' => '0'];
$valores = $vazio;

if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT ID, NOME, DESCRICAO, PRECO, ESTOQUE FROM produtos WHERE ID = ?');
    $stmt->execute([(int)$_GET['editar']]);
    if ($p = $stmt->fetch()) {
        $valores = [
            'id'        => (int)$p['ID'],
            'nome'      => $p['NOME'],
            'descricao' => $p['DESCRICAO'] ?? '',
            'preco'     => number_format((float)$p['PRECO'], 2, ',', ''),
            'estoque'   => (string)$p['ESTOQUE'],
        ];
    } else {
        $toasts[] = ['tipo' => 'error', 'mensagem' => 'Produto não encontrado.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'id'        => (int)($_POST['id'] ?? 0),
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
            if ($valores['id'] > 0) {
                $stmt = $pdo->prepare('UPDATE produtos SET NOME = ?, DESCRICAO = ?, PRECO = ?, ESTOQUE = ? WHERE ID = ?');
                $stmt->execute([...$dados, $valores['id']]);
                flash('success', "Produto '{$valores['nome']}' atualizado com sucesso!");
            } else {
                $stmt = $pdo->prepare('INSERT INTO produtos (NOME, DESCRICAO, PRECO, ESTOQUE) VALUES (?, ?, ?, ?)');
                $stmt->execute($dados);
                flash('success', "Produto '{$valores['nome']}' cadastrado com sucesso!");
            }
            header('Location: produtos.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erros['nome'] = 'Já existe um produto com esse nome.';
        }
    }
}

// LEFT JOIN: lista também os produtos que nunca foram vendidos (vendidos = 0)
$produtos = $pdo->query(
    'SELECT pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE,
            COALESCE(SUM(i.QUANTIDADE), 0) AS VENDIDOS
       FROM produtos pr
       LEFT JOIN itens_pedido i ON i.PRODUTO_ID = pr.ID
      GROUP BY pr.ID, pr.NOME, pr.DESCRICAO, pr.PRECO, pr.ESTOQUE
      ORDER BY pr.NOME'
)->fetchAll();

$editando = $valores['id'] > 0;

$campos = [
    'nome'      => ['Nome', true, 'type="text" maxlength="100" placeholder="Ex.: Mouse sem fio"'],
    'descricao' => ['Descrição', false, 'type="text" maxlength="255" placeholder="Opcional"'],
    'preco'     => ['Preço (R$)', true, 'type="text" inputmode="decimal" placeholder="0,00"'],
    'estoque'   => ['Estoque', true, 'type="number" min="0" step="1"'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Produtos' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title">
                    <?php echo icone($editando ? 'editar' : 'pacote'); ?>
                    <?php echo $editando ? 'Editar Produto <span class="page-title-id">#' . $valores['id'] . '</span>' : 'Cadastrar Produto'; ?>
                </h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível salvar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="produtos.php" novalidate>
                    <input type="hidden" name="id" value="<?php echo $valores['id']; ?>">
                    <div class="form-grid">
                        <?php foreach ($campos as $campo => [$rotulo, $obrigatorio, $atributos]): ?>
                            <?php $erro = $erros[$campo] ?? ''; ?>
                            <div class="form-group<?php echo $erro ? ' has-error' : ''; ?>">
                                <label for="<?php echo $campo; ?>">
                                    <?php echo $rotulo; ?>
                                    <?php if ($obrigatorio): ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
                                </label>
                                <input id="<?php echo $campo; ?>" name="<?php echo $campo; ?>"
                                       value="<?php echo htmlspecialchars($valores[$campo]); ?>"
                                       aria-describedby="<?php echo $campo; ?>-erro"
                                       <?php echo $atributos; ?>
                                       <?php echo $obrigatorio ? 'required' : ''; ?>
                                       <?php echo $erro ? 'aria-invalid="true"' : ''; ?>>
                                <small class="field-error" id="<?php echo $campo; ?>-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erro); ?></span></small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Produto'; ?></button>
                        <?php if ($editando): ?>
                            <a href="produtos.php" class="btn btn-outline"><?php echo icone('fechar'); ?> Cancelar edição</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('lista'); ?> Produtos cadastrados</h2>
                    <p class="list-summary"><?php echo count($produtos) . (count($produtos) === 1 ? ' produto' : ' produtos'); ?></p>
                </div>

                <?php if (!$produtos): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('pacote'); ?></span>
                        <p>Nenhum produto cadastrado ainda. Use o formulário acima.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th class="num">Preço</th>
                                    <th class="num">Estoque</th>
                                    <th class="num">Vendidos</th>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                    <?php $nome = htmlspecialchars($p['NOME']); ?>
                                    <tr>
                                        <td class="cell-client">
                                            <span class="client-name"><?php echo $nome; ?></span>
                                            <span class="client-id">#<?php echo $p['ID']; ?><?php echo $p['DESCRICAO'] ? ' · ' . htmlspecialchars($p['DESCRICAO']) : ''; ?></span>
                                        </td>
                                        <td class="num" data-label="Preço"><?php echo moeda((float)$p['PRECO']); ?></td>
                                        <td class="num" data-label="Estoque"><?php echo (int)$p['ESTOQUE']; ?></td>
                                        <td class="num" data-label="Vendidos"><?php echo (int)$p['VENDIDOS']; ?></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <a href="produtos.php?editar=<?php echo $p['ID']; ?>" class="btn-icon" title="Editar"
                                                   aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                <a href="produto_apagar.php?id=<?php echo $p['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar"
                                                   aria-label="Apagar <?php echo $nome; ?>"
                                                   data-confirmar-exclusao="<?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
                                            </div>
                                        </td>
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
