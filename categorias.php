<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Categorias de produto (tabela categorias): criar, renomear e apagar.
//   categorias.php?editar=ID   → o formulário passa a renomear a categoria
//   POST categorias.php?apagar=ID (pelo modal) → apaga (só administradores);
//   os produtos dela ficam "sem categoria" (FK_PRODUTOS_CATEGORIA com ON DELETE SET NULL).

$erros = [];
$editarId = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$nome = '';

if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT NOME FROM categorias WHERE ID = ?');
    $stmt->execute([$editarId]);
    $nome = $stmt->fetchColumn();
    if ($nome === false) {
        flash('error', 'Categoria não encontrada.');
        header('Location: categorias.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['apagar'])) {
        exigir_admin('categorias.php');
        $stmt = $pdo->prepare('SELECT NOME FROM categorias WHERE ID = ?');
        $stmt->execute([(int)$_GET['apagar']]);
        $apagar = $stmt->fetchColumn();
        if ($apagar !== false) {
            $pdo->prepare('DELETE FROM categorias WHERE ID = ?')->execute([(int)$_GET['apagar']]);
            registrar_atividade($pdo, 'APAGOU', "Apagou a categoria '$apagar'", 'categoria', (int)$_GET['apagar']);
            flash('success', "Categoria '$apagar' apagada. Os produtos dela ficaram sem categoria.");
        }
        header('Location: categorias.php');
        exit;
    }

    $nome = trim((string)($_POST['nome'] ?? ''));
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 60) {
        $erros['nome'] = 'O nome deve ter entre 2 e 60 caracteres.';
    } else {
        try {
            if ($editarId > 0) {
                $pdo->prepare('UPDATE categorias SET NOME = ? WHERE ID = ?')->execute([$nome, $editarId]);
                registrar_atividade($pdo, 'ALTEROU', "Renomeou a categoria para '$nome'", 'categoria', $editarId);
                flash('success', 'Categoria renomeada.');
            } else {
                $pdo->prepare('INSERT INTO categorias (NOME) VALUES (?)')->execute([$nome]);
                registrar_atividade($pdo, 'CRIOU', "Criou a categoria '$nome'", 'categoria', (int)$pdo->lastInsertId());
                flash('success', "Categoria '$nome' criada.");
            }
            header('Location: categorias.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erros['nome'] = 'Já existe uma categoria com esse nome.';
        }
    }
}

$categorias = $pdo->query(
    'SELECT cat.ID, cat.NOME, COUNT(pr.ID) AS PRODUTOS, COALESCE(SUM(pr.ESTOQUE), 0) AS UNIDADES
       FROM categorias cat
       LEFT JOIN produtos pr ON pr.CATEGORIA_ID = cat.ID
      GROUP BY cat.ID, cat.NOME
      ORDER BY cat.NOME'
)->fetchAll();
$semCategoria = (int)$pdo->query('SELECT COUNT(*) FROM produtos WHERE CATEGORIA_ID IS NULL')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorias de Produto</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Produtos' => 'produtos.php', 'Categorias' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-header">
                <h2 class="page-title"><?php echo icone('etiqueta'); ?> Categorias de Produto</h2>
                <a href="produtos.php" class="btn btn-outline"><?php echo icone('pacote'); ?> Ver Produtos</a>
            </div>

            <div class="paineis paineis-estoque">
                <section class="form-container">
                    <h3 class="section-title"><?php echo icone($editarId ? 'editar' : 'plus'); ?> <?php echo $editarId ? 'Renomear categoria' : 'Nova categoria'; ?></h3>
                    <form method="POST" action="" novalidate>
                        <?php echo csrf_campo(); ?>
                        <div class="form-group<?php echo isset($erros['nome']) ? ' has-error' : ''; ?>">
                            <label for="nome">Nome <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="nome" name="nome" maxlength="60" required placeholder="Ex.: Periféricos"
                                   value="<?php echo htmlspecialchars((string)$nome); ?>" <?php echo $editarId ? 'autofocus' : ''; ?>>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['nome'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editarId ? 'Salvar' : 'Criar categoria'; ?></button>
                            <?php if ($editarId): ?>
                                <a href="categorias.php" class="btn btn-outline"><?php echo icone('fechar'); ?> Cancelar</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="list-container">
                    <?php if (!$categorias): ?>
                        <div class="empty-message">
                            <span class="empty-icon"><?php echo icone('etiqueta'); ?></span>
                            <p>Nenhuma categoria criada ainda.</p>
                            <p class="text-muted">Crie categorias como "Periféricos" ou "Monitores" e escolha a categoria no cadastro de cada produto.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="num">Produtos</th>
                                        <th class="num">Unidades em estoque</th>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categorias as $cat): ?>
                                        <?php $nomeCat = htmlspecialchars($cat['NOME']); ?>
                                        <tr>
                                            <td class="cell-client">
                                                <a href="produtos.php?categoria=<?php echo $cat['ID']; ?>" class="client-link">
                                                    <span class="client-name"><?php echo $nomeCat; ?></span>
                                                    <span class="client-id">Ver produtos da categoria</span>
                                                </a>
                                            </td>
                                            <td class="num" data-label="Produtos"><?php echo (int)$cat['PRODUTOS']; ?></td>
                                            <td class="num" data-label="Unidades"><?php echo (int)$cat['UNIDADES']; ?></td>
                                            <td class="col-actions">
                                                <div class="action-buttons">
                                                    <a href="categorias.php?editar=<?php echo $cat['ID']; ?>" class="btn-icon" title="Renomear" aria-label="Renomear <?php echo $nomeCat; ?>"><?php echo icone('editar'); ?></a>
                                                    <?php if (eh_admin()): ?>
                                                        <a href="categorias.php?apagar=<?php echo $cat['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar" aria-label="Apagar <?php echo $nomeCat; ?>"
                                                           data-confirmar-exclusao="a categoria <?php echo $nomeCat; ?>"><?php echo icone('lixeira'); ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($semCategoria > 0): ?>
                            <p class="text-muted list-total"><a href="produtos.php?categoria=sem" class="text-link"><?php echo $semCategoria === 1 ? '1 produto' : "$semCategoria produtos"; ?> sem categoria</a></p>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
