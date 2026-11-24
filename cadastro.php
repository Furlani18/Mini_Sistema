<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// Cadastro de cliente: dados principais, documento, endereço e observações.

$valores = cliente_vazio();
$erros = [];
$toasts = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = cliente_do_post();
    $erros = validar_cliente_completo($valores);

    if (!$erros) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO clientes (NOME, EMAIL, TELEFONE, CPF_CNPJ, CEP, LOGRADOURO, NUMERO, COMPLEMENTO, BAIRRO, CIDADE, UF, OBSERVACOES)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute(cliente_para_banco($valores));
            $novoId = (int)$pdo->lastInsertId();
            registrar_atividade($pdo, 'CRIOU', "Cadastrou o cliente '{$valores['nome']}'", 'cliente', $novoId);
            flash('success', "Cliente '{$valores['nome']}' cadastrado com sucesso!");
            header('Location: cliente_ver.php?id=' . $novoId);
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erros['cpf_cnpj'] = 'Já existe um cliente com esse CPF/CNPJ.';
        }
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
        <?php $breadcrumb = ['Clientes' => 'clientes.php', 'Cadastrar Cliente' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('usuario-mais'); ?> Cadastrar Novo Cliente</h2>
                    <a href="cliente_importar.php" class="btn btn-outline"><?php echo icone('upload'); ?> Importar planilha</a>
                </div>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível cadastrar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formCadastro" data-validar novalidate>
                    <?php echo csrf_campo(); ?>
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
    <script src="js/endereco.js"></script>
</body>
</html>
