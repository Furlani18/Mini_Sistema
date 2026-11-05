<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

$valores = ['nome' => '', 'email' => '', 'telefone' => ''];
$erros = [];
$toasts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
    ];
    $erros = validar_cliente($valores['nome'], $valores['email'], $valores['telefone']);

    if (!$erros) {
        $stmt = $pdo->prepare('INSERT INTO clientes (NOME, EMAIL, TELEFONE) VALUES (?, ?, ?)');
        $stmt->execute([$valores['nome'], $valores['email'], $valores['telefone']]);
        $toasts[] = ['tipo' => 'success', 'mensagem' => "Cliente '{$valores['nome']}' cadastrado com sucesso!"];
        $valores = ['nome' => '', 'email' => '', 'telefone' => ''];
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Cliente</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Cadastrar Cliente' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone('usuario-mais'); ?> Cadastrar Novo Cliente</h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível cadastrar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formCadastro" data-validar novalidate>
                    <?php include __DIR__ . '/form_cliente.php'; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Cadastrar Cliente</button>
                        <a href="clientes.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Clientes</a>
                    </div>
                </form>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>

    <script src="js/formulario.js"></script>
</body>
</html>
