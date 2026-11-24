<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Lista de clientes com busca, ordenação por coluna e paginação (tudo feito no banco).
// LEFT JOIN com pedidos/itens para mostrar quantos pedidos cada cliente tem e quanto já gastou.

$q = busca_da_url();
$where = '';
$parametros = [];
if ($q !== '') {
    $where = 'WHERE c.NOME LIKE ? OR c.EMAIL LIKE ? OR c.TELEFONE LIKE ? OR c.CIDADE LIKE ?';
    $parametros = [like($q), like($q), like($q), like($q)];
    // CPF/CNPJ é gravado só com dígitos: "123.456" também encontra "123456…"
    $digitos = preg_replace('/\D/', '', $q);
    if (strlen($digitos) >= 3) {
        $where .= ' OR c.CPF_CNPJ LIKE ?';
        $parametros[] = like($digitos);
    }
}

$ordem = ordenacao([
    'id'      => 'c.ID',
    'nome'    => 'c.NOME',
    'email'   => 'c.EMAIL',
    'pedidos' => 'pedidos',
    'gasto'   => 'gasto',
], 'id', 'desc');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes c $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT c.ID AS id, c.NOME AS nome, c.EMAIL AS email, c.TELEFONE AS telefone,
            COUNT(p.ID) AS pedidos,
            COALESCE(SUM(CASE WHEN p.STATUS <> 'CANCELADO' THEN p.TOTAL END), 0) AS gasto
       FROM clientes c
       LEFT JOIN vw_pedidos p ON p.CLIENTE_ID = c.ID
       $where
      GROUP BY c.ID, c.NOME, c.EMAIL, c.TELEFONE
      ORDER BY {$ordem['sql']}, c.ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$clientes = $stmt->fetchAll();

$totalCadastrados = $q === '' ? $pag['total'] : (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
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
                    <div class="page-head-acoes">
                        <a href="cliente_importar.php" class="btn btn-outline"><?php echo icone('upload'); ?> Importar</a>
                        <a href="cadastro.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Cliente</a>
                    </div>
                </div>

                <?php if ($totalCadastrados === 0): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('usuarios'); ?></span>
                        <p>Nenhum cliente cadastrado ainda.</p>
                        <p><a href="cadastro.php" class="text-link">Clique aqui para cadastrar o primeiro cliente</a></p>
                    </div>
                <?php else: ?>
                    <form method="get" class="filtros" role="search">
                        <?php echo campos_ocultos(['q', 'pagina']); ?>
                        <label for="buscaClientes" class="visually-hidden">Buscar cliente</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaClientes" name="q" value="<?php echo htmlspecialchars($q); ?>"
                                   placeholder="Buscar por nome, email, telefone, CPF/CNPJ ou cidade" autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Buscar</button>
                        <?php if ($q !== ''): ?>
                            <a href="clientes.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                        <?php endif; ?>
                    </form>

                    <?php if (!$clientes): ?>
                        <p class="no-results is-visible">Nenhum cliente encontrado para “<?php echo htmlspecialchars($q); ?>”.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <?php echo th_ordenavel('nome', 'Cliente', $ordem); ?>
                                        <?php echo th_ordenavel('email', 'Email', $ordem); ?>
                                        <th>Telefone</th>
                                        <?php echo th_ordenavel('pedidos', 'Pedidos', $ordem, 'num', 'desc'); ?>
                                        <?php echo th_ordenavel('gasto', 'Total gasto', $ordem, 'num', 'desc'); ?>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes as $cliente): ?>
                                        <?php $nome = htmlspecialchars($cliente['nome']); ?>
                                        <tr>
                                            <td class="cell-client">
                                                <a href="cliente_ver.php?id=<?php echo $cliente['id']; ?>" class="client client-link">
                                                    <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($cliente['nome'])); ?></span>
                                                    <span>
                                                        <span class="client-name"><?php echo $nome; ?></span>
                                                        <span class="client-id">#<?php echo $cliente['id']; ?></span>
                                                    </span>
                                                </a>
                                            </td>
                                            <td data-label="Email"><?php echo htmlspecialchars($cliente['email']); ?></td>
                                            <td data-label="Telefone"><?php echo $cliente['telefone'] ? htmlspecialchars($cliente['telefone']) : '<span class="text-muted">—</span>'; ?></td>
                                            <td class="num" data-label="Pedidos">
                                                <a href="pedidos.php?cliente=<?php echo $cliente['id']; ?>" class="text-link"
                                                   title="Ver pedidos de <?php echo $nome; ?>"><?php echo (int)$cliente['pedidos']; ?></a>
                                            </td>
                                            <td class="num" data-label="Total gasto"><?php echo moeda((float)$cliente['gasto']); ?></td>
                                            <td class="col-actions">
                                                <div class="action-buttons">
                                                    <a href="cliente_ver.php?id=<?php echo $cliente['id']; ?>"
                                                       class="btn-icon" title="Ver ficha"
                                                       aria-label="Ver ficha de <?php echo $nome; ?>"><?php echo icone('olho'); ?></a>
                                                    <a href="atualizar.php?id=<?php echo $cliente['id']; ?>"
                                                       class="btn-icon"
                                                       title="Editar"
                                                       aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                    <?php if (eh_admin()): ?>
                                                    <a href="apagar.php?id=<?php echo $cliente['id']; ?>"
                                                       class="btn-icon btn-icon-danger"
                                                       title="Apagar"
                                                       aria-label="Apagar <?php echo $nome; ?>"
                                                       data-confirmar-exclusao="<?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php echo controles_paginacao($pag); ?>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
