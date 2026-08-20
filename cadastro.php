<?php
require_once 'conexao.php';

// Processar formulário
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    if (empty($nome) || empty($email)) {
        $mensagem = 'Nome e Email são obrigatórios!';
        $tipo_mensagem = 'error';
    } else {
        $stmt = $pdo->prepare('INSERT INTO cliente (NOME, EMAIL, TELEFONE) VALUES (?, ?, ?)');
        $stmt->execute([$nome, $email, $telefone]);
        $mensagem = 'Cliente cadastrado com sucesso!';
        $tipo_mensagem = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Cliente</title>
    <link rel="stylesheet" href="css/styly.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 Sistema de Gerenciamento de Clientes</h1>
            <nav>
                <a href="index.html" class="nav-link">Home</a>
                <a href="cadastro.php" class="nav-link active">Cadastrar Cliente</a>
                <a href="clientes.php" class="nav-link">Listar Clientes</a>
            </nav>
        </header>

        <main>
            <div class="form-container">
                <h2>➕ Cadastrar Novo Cliente</h2>

                <?php if (!empty($mensagem)): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                        <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formCadastro">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input 
                            type="text" 
                            id="nome" 
                            name="nome" 
                            placeholder="Digite o nome completo" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Digite o email" 
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input 
                            type="tel" 
                            id="telefone" 
                            name="telefone" 
                            placeholder="Digite o telefone (opcional)"
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Cadastrar Cliente</button>
                        <a href="clientes.php" class="btn btn-secondary">📋 Ver Clientes</a>
                    </div>
                </form>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 Sistema de Gerenciamento de Clientes. Todos os direitos reservados.</p>
        </footer>
    </div>

    <script src="js/cadastro.js"></script>
</body>
</html>
