<?php
require_once 'conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mensagem = '';
$tipo_mensagem = '';
$cliente_encontrado = false;

$stmt = $pdo->prepare('SELECT NOME FROM cliente WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if ($cliente) {
    $delete = $pdo->prepare('DELETE FROM cliente WHERE ID = ?');
    $delete->execute([$id]);
    $cliente_encontrado = true;
    $nome_cliente = $cliente['NOME'];
}

if ($cliente_encontrado) {
    $mensagem = "Cliente '$nome_cliente' removido com sucesso!";
    $tipo_mensagem = 'success';
} else {
    $mensagem = 'Cliente não encontrado!';
    $tipo_mensagem = 'error';
}

// Aguardar 2 segundos e redirecionar
header("Refresh: 2; url=clientes.php");
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletar Cliente</title>
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
            <div class="list-container">
                <h2>🗑️ Deletar Cliente</h2>

                <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                    <p style="margin-top: 10px;">
                        Redirecionando para a lista de clientes em 2 segundos...
                        <br>
                        <a href="clientes.php" class="btn btn-primary" style="margin-top: 10px; display: inline-block;">📋 Ir para Lista de Clientes</a>
                    </p>
                </div>
            </div>
        </main>

        <footer>
            <p>&copy; 2024 Sistema de Gerenciamento de Clientes. Todos os direitos reservados.</p>
        </footer>
    </div>
</body>
</html>
