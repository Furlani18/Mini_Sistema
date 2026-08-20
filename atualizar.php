<?php
require_once 'conexao.php';

$mensagem = '';
$tipo_mensagem = '';
$cliente = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT ID AS id, NOME AS nome, EMAIL AS email, TELEFONE AS telefone FROM cliente WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

// Se cliente não existe, redirecionar
if (!$cliente) {
    header('Location: clientes.php');
    exit;
}

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    if (empty($nome) || empty($email)) {
        $mensagem = 'Nome e Email são obrigatórios!';
        $tipo_mensagem = 'error';
    } else {
        $stmt = $pdo->prepare('UPDATE cliente SET NOME = ?, EMAIL = ?, TELEFONE = ? WHERE ID = ?');
        $stmt->execute([$nome, $email, $telefone, $id]);
        $cliente['nome'] = $nome;
        $cliente['email'] = $email;
        $cliente['telefone'] = $telefone;
        
        $mensagem = 'Cliente atualizado com sucesso!';
        $tipo_mensagem = 'success';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Cliente</title>
    <link rel="stylesheet" href="css/styly.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 Sistema de Gerenciamento de Clientes</h1>
            <nav>
                <a href="index.html" class="nav-link">Home</a>
                <a href="cadastro.php" class="nav-link">Cadastrar Cliente</a>
                <a href="clientes.php" class="nav-link">Listar Clientes</a>
            </nav>
        </header>

        <main>
            <div class="form-container">
                <h2>✏️ Atualizar Cliente ID #<?php echo $cliente['id']; ?></h2>

                <?php if (!empty($mensagem)): ?>
                    <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                        <?php echo $mensagem; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formAtualizar">
                    <div class="form-group">
                        <label for="nome">Nome Completo *</label>
                        <input 
                            type="text" 
                            id="nome" 
                            name="nome" 
                            value="<?php echo htmlspecialchars($cliente['nome']); ?>"
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
                            value="<?php echo htmlspecialchars($cliente['email']); ?>"
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
                            value="<?php echo htmlspecialchars($cliente['telefone'] ?? ''); ?>"
                            placeholder="Digite o telefone (opcional)"
                        >
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Salvar Alterações</button>
                        <a href="clientes.php" class="btn btn-secondary">📋 Ver Clientes</a>
                        <a href="apagar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-danger" onclick="return confirm('Tem certeza que deseja deletar este cliente?');">🗑️ Deletar Cliente</a>
                    </div>
                </form>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 Sistema de Gerenciamento de Clientes. Todos os direitos reservados.</p>
        </footer>
    </div>

    <script src="js/atualizar.js"></script>
</body>
</html>
