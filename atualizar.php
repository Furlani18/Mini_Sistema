<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// Atualização de cliente: mesmos campos do cadastro (form_cliente.php).

$erros = [];
$toasts = [];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

// Se cliente não existe, volta para a lista com um aviso
if (!$cliente) {
    flash('error', 'Cliente não encontrado.');
    header('Location: clientes.php');
    exit;
}

$valores = cliente_do_banco($cliente);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = cliente_do_post();
    $erros = validar_cliente_completo($valores);

    if (!$erros) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE clientes
                    SET NOME = ?, EMAIL = ?, TELEFONE = ?, CPF_CNPJ = ?, CEP = ?, LOGRADOURO = ?, NUMERO = ?,
                        COMPLEMENTO = ?, BAIRRO = ?, CIDADE = ?, UF = ?, OBSERVACOES = ?
                  WHERE ID = ?'
            );
            $stmt->execute([...cliente_para_banco($valores), $id]);
            registrar_atividade($pdo, 'ALTEROU', "Alterou o cliente '{$valores['nome']}'", 'cliente', $id);
            $cliente['NOME'] = $valores['nome'];
            $toasts[] = ['tipo' => 'success', 'mensagem' => 'Cliente atualizado com sucesso!'];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erros['cpf_cnpj'] = 'Já existe outro cliente com esse CPF/CNPJ.';
        }
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
        <?php $breadcrumb = ['Clientes' => 'clientes.php', $cliente['NOME'] => 'cliente_ver.php?id=' . $id, 'Editar' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone('editar'); ?> Atualizar Cliente <span class="page-title-id">#<?php echo $cliente['ID']; ?></span></h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível salvar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="formAtualizar" data-validar novalidate>
                    <?php echo csrf_campo(); ?>
                    <?php include __DIR__ . '/form_cliente.php'; ?>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Salvar Alterações</button>
                        <a href="cliente_ver.php?id=<?php echo $cliente['ID']; ?>" class="btn btn-outline"><?php echo icone('olho'); ?> Ver Ficha</a>
                        <?php if (eh_admin()): ?>
                            <a href="apagar.php?id=<?php echo $cliente['ID']; ?>"
                               class="btn btn-danger-outline"
                               data-confirmar-exclusao="<?php echo htmlspecialchars($cliente['NOME']); ?>"><?php echo icone('lixeira'); ?> Apagar Cliente</a>
                        <?php endif; ?>
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
