<?php
require_once 'conexao.php';

$clientes = $pdo->query('SELECT ID AS id, NOME AS nome, EMAIL AS email, TELEFONE AS telefone FROM cliente ORDER BY ID DESC')->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listar Clientes</title>
    <link rel="stylesheet" href="css/styly.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><span class="brand-mark">CRM</span> Gestão de Clientes</h1>
            <nav>
                <a href="index.html" class="nav-link">Home</a>
                <a href="cadastro.php" class="nav-link">Cadastrar Cliente</a>
                <a href="clientes.php" class="nav-link active">Listar Clientes</a>
            </nav>
        </header>

        <main>
            <div class="list-container">
                <h2>👥 Lista de Clientes</h2>

                <div class="actions" style="margin-bottom: 20px;">
                    <a href="cadastro.php" class="btn btn-primary">➕ Novo Cliente</a>
                </div>

                <div class="search-bar">
                    <div>
                        <label for="buscaClientes">Buscar cliente</label>
                        <input type="search" id="buscaClientes" placeholder="Nome, email ou telefone" autocomplete="off">
                    </div>
                </div>

                <?php if (empty($clientes)): ?>
                    <div class="empty-message">
                        <p>Nenhum cliente cadastrado ainda.</p>
                        <p><a href="cadastro.php" style="color: var(--primary-color); text-decoration: underline;">Clique aqui para cadastrar o primeiro cliente</a></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>#ID</th>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Telefone</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr data-cliente-row data-search="<?php echo htmlspecialchars(strtolower($cliente['nome'] . ' ' . $cliente['email'] . ' ' . ($cliente['telefone'] ?? ''))); ?>">
                                        <td><strong><?php echo $cliente['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($cliente['nome']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefone'] ?? '-'); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="atualizar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-warning">✏️ Editar</a>
                                                <a href="apagar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja deletar este cliente?');">🗑️ Deletar</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="list-summary" id="resultadoBusca">
                        <strong>Total de clientes: <?php echo count($clientes); ?></strong>
                    </p>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 Sistema de Gerenciamento de Clientes. Todos os direitos reservados.</p>
        </footer>
    </div>
    <script src="js/clientes.js"></script>
</body>
</html>
