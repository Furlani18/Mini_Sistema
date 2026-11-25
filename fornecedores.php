<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Lista de fornecedores com o resumo das compras (entradas de estoque ligadas a cada um).
// LEFT JOIN: mostra também fornecedores sem nenhuma compra registrada.

$q = busca_da_url();
$where = '';
$parametros = [];
if ($q !== '') {
    $where = 'WHERE f.NOME LIKE ? OR f.CONTATO LIKE ? OR f.EMAIL LIKE ?';
    $parametros = [like($q), like($q), like($q)];
    $digitos = preg_replace('/\D/', '', $q);
    if (strlen($digitos) >= 3) {
        $where .= ' OR f.CPF_CNPJ LIKE ?';
        $parametros[] = like($digitos);
    }
}

$ordem = ordenacao([
    'nome'    => 'f.NOME',
    'compras' => 'COMPRAS',
    'total'   => 'TOTAL',
    'ultima'  => 'ULTIMA',
], 'nome');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM fornecedores f $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT f.ID, f.NOME, f.CPF_CNPJ, f.CONTATO, f.EMAIL, f.TELEFONE,
            COUNT(m.ID) AS COMPRAS,
            COALESCE(SUM(m.QUANTIDADE * m.CUSTO_UNITARIO), 0) AS TOTAL,
            MAX(m.CRIADO_EM) AS ULTIMA
       FROM fornecedores f
       LEFT JOIN movimentos_estoque m ON m.FORNECEDOR_ID = f.ID
       $where
      GROUP BY f.ID, f.NOME, f.CPF_CNPJ, f.CONTATO, f.EMAIL, f.TELEFONE
      ORDER BY {$ordem['sql']}, f.NOME
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$fornecedores = $stmt->fetchAll();
$totalCadastrados = $q === '' ? $pag['total'] : (int)$pdo->query('SELECT COUNT(*) FROM fornecedores')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fornecedores</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Fornecedores' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('caminhao'); ?> Fornecedores</h2>
                    <a href="fornecedor_cadastro.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Fornecedor</a>
                </div>

                <?php if ($totalCadastrados === 0): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('caminhao'); ?></span>
                        <p>Nenhum fornecedor cadastrado ainda.</p>
                        <p class="text-muted">Com fornecedores cadastrados, cada entrada de estoque registra de quem foi a compra e quanto custou.</p>
                        <p><a href="fornecedor_cadastro.php" class="text-link">Cadastrar o primeiro fornecedor</a></p>
                    </div>
                <?php else: ?>
                    <form method="get" class="filtros" role="search">
                        <?php echo campos_ocultos(['q', 'pagina']); ?>
                        <label for="buscaFornecedores" class="visually-hidden">Buscar fornecedor</label>
                        <div class="search-input">
                            <?php echo icone('busca'); ?>
                            <input type="search" id="buscaFornecedores" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Nome, contato, email ou CNPJ" autocomplete="off">
                        </div>
                        <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Buscar</button>
                        <?php if ($q !== ''): ?>
                            <a href="fornecedores.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                        <?php endif; ?>
                    </form>

                    <?php if (!$fornecedores): ?>
                        <p class="no-results is-visible">Nenhum fornecedor encontrado.</p>
                    <?php else: ?>
                        <?php echo seletor_ordenacao(['nome' => 'Nome', 'compras' => 'Compras', 'total' => 'Total comprado', 'ultima' => 'Última compra'], $ordem); ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <?php echo th_ordenavel('nome', 'Fornecedor', $ordem); ?>
                                        <th>Contato</th>
                                        <?php echo th_ordenavel('compras', 'Entradas', $ordem, 'num', 'desc'); ?>
                                        <?php echo th_ordenavel('total', 'Total comprado', $ordem, 'num', 'desc'); ?>
                                        <?php echo th_ordenavel('ultima', 'Última compra', $ordem, '', 'desc'); ?>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <?php $nome = htmlspecialchars($f['NOME']); ?>
                                        <tr>
                                            <td class="cell-client">
                                                <a href="fornecedor_cadastro.php?id=<?php echo $f['ID']; ?>" class="client-link">
                                                    <div class="client">
                                                        <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($f['NOME'])); ?></span>
                                                        <span>
                                                            <span class="client-name"><?php echo $nome; ?></span>
                                                            <span class="client-id"><?php echo $f['CPF_CNPJ'] ? (strlen($f['CPF_CNPJ']) === 14 ? 'CNPJ ' : 'CPF ') . formatar_documento($f['CPF_CNPJ']) : '#' . $f['ID']; ?></span>
                                                        </span>
                                                    </div>
                                                </a>
                                            </td>
                                            <td data-label="Contato">
                                                <?php echo $f['CONTATO'] ? htmlspecialchars($f['CONTATO']) : ''; ?>
                                                <?php if ($f['TELEFONE'] || $f['EMAIL']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(implode(' · ', array_filter([$f['TELEFONE'], $f['EMAIL']]))); ?></small>
                                                <?php elseif (!$f['CONTATO']): ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num" data-label="Entradas"><?php echo (int)$f['COMPRAS']; ?></td>
                                            <td class="num" data-label="Total comprado"><?php echo moeda((float)$f['TOTAL']); ?></td>
                                            <td data-label="Última compra"><?php echo $f['ULTIMA'] ? date('d/m/Y', strtotime($f['ULTIMA'])) : '<span class="text-muted">—</span>'; ?></td>
                                            <td class="col-actions">
                                                <div class="action-buttons">
                                                    <a href="fornecedor_cadastro.php?id=<?php echo $f['ID']; ?>" class="btn-icon" title="Editar" aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                    <?php if (eh_admin()): ?>
                                                        <a href="fornecedor_apagar.php?id=<?php echo $f['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar" aria-label="Apagar <?php echo $nome; ?>"
                                                           data-confirmar-exclusao="o fornecedor <?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
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
