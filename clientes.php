<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

$clientes = $pdo->query('SELECT ID AS id, NOME AS nome, EMAIL AS email, TELEFONE AS telefone FROM clientes ORDER BY ID DESC')->fetchAll();
$total = count($clientes);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Clientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Clientes' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('usuarios'); ?> Lista de Clientes</h2>
                    <a href="cadastro.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Cliente</a>
                </div>

                <?php if (empty($clientes)): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('usuarios'); ?></span>
                        <p>Nenhum cliente cadastrado ainda.</p>
                        <p><a href="cadastro.php" class="text-link">Clique aqui para cadastrar o primeiro cliente</a></p>
                    </div>
                <?php else: ?>
                    <div class="search-bar">
                        <label for="buscaClientes" class="visually-hidden">Buscar cliente</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaClientes" placeholder="Buscar por nome, email ou telefone" autocomplete="off">
                        </div>
                        <p class="list-summary" id="resultadoBusca" data-total="<?php echo $total; ?>" aria-live="polite">
                            <?php echo $total . ($total === 1 ? ' cliente' : ' clientes'); ?>
                        </p>
                    </div>

                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <?php $nome = htmlspecialchars($cliente['nome']); ?>
                                    <tr data-cliente-row data-search="<?php echo htmlspecialchars(mb_strtolower($cliente['nome'] . ' ' . $cliente['email'] . ' ' . ($cliente['telefone'] ?? ''))); ?>">
                                        <td class="cell-client">
                                            <div class="client">
                                                <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($cliente['nome'])); ?></span>
                                                <span>
                                                    <span class="client-name"><?php echo $nome; ?></span>
                                                    <span class="client-id">#<?php echo $cliente['id']; ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="Email"><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td data-label="Telefone"><?php echo $cliente['telefone'] ? htmlspecialchars($cliente['telefone']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <a href="atualizar.php?id=<?php echo $cliente['id']; ?>"
                                                   class="btn-icon"
                                                   title="Editar"
                                                   aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                <a href="apagar.php?id=<?php echo $cliente['id']; ?>"
                                                   class="btn-icon btn-icon-danger"
                                                   title="Apagar"
                                                   aria-label="Apagar <?php echo $nome; ?>"
                                                   data-confirmar-exclusao="<?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="no-results" id="semResultados">Nenhum cliente encontrado para essa busca.</p>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/clientes.js"></script>
</body>
</html>
