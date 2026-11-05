<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

$erros = [];
$toasts = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT ID AS id, NOME AS nome, EMAIL AS email, TELEFONE AS telefone FROM clientes WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

// Se cliente não existe, volta para a lista com um aviso
if (!$cliente) {
    flash('error', 'Cliente não encontrado.');
    header('Location: clientes.php');
    exit;
}

$valores = [
    'nome'     => $cliente['nome'],
    'email'    => $cliente['email'],
    'telefone' => $cliente['telefone'] ?? '',
];

// Processar atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
    ];
    $erros = validar_cliente($valores['nome'], $valores['email'], $valores['telefone']);

    if (!$erros) {
        $stmt = $pdo->prepare('UPDATE clientes SET NOME = ?, EMAIL = ?, TELEFONE = ? WHERE ID = ?');
        $stmt->execute([$valores['nome'], $valores['email'], $valores['telefone'], $id]);
        $cliente['nome'] = $valores['nome'];
        $toasts[] = ['tipo' => 'success', 'mensagem' => 'Cliente atualizado com sucesso!'];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualizar Cliente</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Clientes' => 'clientes.php', 'Atualizar Cliente' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone('editar'); ?> Atualizar Cliente <span class="page-title-id">#<?php echo $cliente['id']; ?></span></h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível salvar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formAtualizar" data-validar novalidate>
                    <?php include __DIR__ . '/form_cliente.php'; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Salvar Alterações</button>
                        <a href="clientes.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Clientes</a>
                        <a href="apagar.php?id=<?php echo $cliente['id']; ?>"
                           class="btn btn-danger-outline"
                           data-confirmar-exclusao="<?php echo htmlspecialchars($cliente['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar Cliente</a>
                    </div>
                </form>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="js/formulario.js"></script>
</body>
</html>
