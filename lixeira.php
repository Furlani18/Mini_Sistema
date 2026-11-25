<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';
require_once 'lixeira_funcoes.php';

// Lixeira: clientes, produtos e pedidos apagados (tabela lixeira).
// O administrador pode restaurar (volta com o mesmo número) ou excluir de vez.
//   POST acao=restaurar&id=ID       → restaura
//   POST lixeira.php?excluir=ID     → exclui de vez (enviado pelo modal de confirmação)
//   POST lixeira.php?esvaziar=1     → exclui tudo de vez
// Somente administradores.

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['acao'] ?? '') === 'restaurar') {
        $stmt = $pdo->prepare('SELECT * FROM lixeira WHERE ID = ?');
        $stmt->execute([(int)($_POST['id'] ?? 0)]);
        $item = $stmt->fetch();
        if (!$item) {
            flash('error', 'Item não encontrado na lixeira.');
        } else {
            $pdo->beginTransaction();
            $erro = lixeira_restaurar($pdo, $item);
            if ($erro !== null) {
                $pdo->rollBack();
                flash('error', "Não foi possível restaurar: $erro");
            } else {
                $pdo->prepare('DELETE FROM lixeira WHERE ID = ?')->execute([$item['ID']]);
                $pdo->commit();
                registrar_atividade($pdo, 'RESTAUROU', "Restaurou da lixeira: {$item['DESCRICAO']}", $item['ENTIDADE'], (int)$item['ENTIDADE_ID']);
                flash('success', "{$item['DESCRICAO']} foi restaurado.");
            }
        }
    } elseif (isset($_GET['excluir'])) {
        $stmt = $pdo->prepare('SELECT DESCRICAO FROM lixeira WHERE ID = ?');
        $stmt->execute([(int)$_GET['excluir']]);
        $descricao = $stmt->fetchColumn();
        if ($descricao !== false) {
            $pdo->prepare('DELETE FROM lixeira WHERE ID = ?')->execute([(int)$_GET['excluir']]);
            registrar_atividade($pdo, 'APAGOU', "Excluiu de vez da lixeira: $descricao", 'sistema');
            flash('success', "$descricao foi excluído de vez.");
        }
    } elseif (isset($_GET['esvaziar'])) {
        $quantos = $pdo->exec('DELETE FROM lixeira');
        if ($quantos > 0) {
            registrar_atividade($pdo, 'APAGOU', "Esvaziou a lixeira ($quantos itens)", 'sistema');
        }
        flash('success', 'Lixeira esvaziada.');
    }
    header('Location: lixeira.php');
    exit;
}

$entidade = is_string($_GET['entidade'] ?? null) && isset(entidades_lixeira()[$_GET['entidade']]) ? $_GET['entidade'] : '';
$where = $entidade ? 'WHERE ENTIDADE = ?' : '';
$parametros = $entidade ? [$entidade] : [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM lixeira $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT ID, ENTIDADE, ENTIDADE_ID, DESCRICAO, DADOS, USUARIO_NOME, APAGADO_EM
       FROM lixeira $where
      ORDER BY APAGADO_EM DESC, ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$itens = $stmt->fetchAll();
$totalLixeira = (int)$pdo->query('SELECT COUNT(*) FROM lixeira')->fetchColumn();

/** Uma linha de detalhe sobre o item apagado, lida da cópia em JSON. */
function detalhe_lixeira(array $item): string
{
    $d = json_decode($item['DADOS'], true) ?: [];
    switch ($item['ENTIDADE']) {
        case 'cliente':
            $c = $d['clientes'] ?? [];
            return trim(($c['EMAIL'] ?? '') . (!empty($c['TELEFONE']) ? ' · ' . $c['TELEFONE'] : ''));
        case 'produto':
            $p = $d['produtos'] ?? [];
            return moeda((float)($p['PRECO'] ?? 0)) . ' · ' . (int)($p['ESTOQUE'] ?? 0) . ' un. em estoque';
        case 'pedido':
            $p = $d['pedidos'] ?? [];
            $itens = $d['itens_pedido'] ?? [];
            $total = array_sum(array_map(fn($i) => $i['QUANTIDADE'] * $i['PRECO_UNITARIO'], $itens)) - (float)($p['DESCONTO'] ?? 0);
            return (status_pedidos()[$p['STATUS'] ?? ''] ?? '') . ' · ' . count($itens) . ' ' . (count($itens) === 1 ? 'item' : 'itens') . ' · ' . moeda(max(0, $total));
    }
    return '';
}
$iconesLixeira = ['cliente' => 'usuario', 'produto' => 'pacote', 'pedido' => 'carrinho'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lixeira</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Lixeira' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('lixeira'); ?> Lixeira</h2>
                    <?php if ($totalLixeira > 0): ?>
                        <a href="lixeira.php?esvaziar=1" class="btn btn-danger-outline"
                           data-confirmar-exclusao="todos os <?php echo $totalLixeira; ?> itens da lixeira de vez"><?php echo icone('lixeira'); ?> Esvaziar lixeira</a>
                    <?php endif; ?>
                </div>
                <p class="intro">Clientes, produtos e pedidos apagados ficam aqui até serem excluídos de vez. Ao restaurar, o registro volta com o mesmo número; um pedido de venda restaurado baixa o estoque de novo.</p>

                <?php if ($totalLixeira === 0): ?>
                    <div class="empty-message">
                        <span class="empty-icon"><?php echo icone('lixeira'); ?></span>
                        <p>A lixeira está vazia.</p>
                    </div>
                <?php else: ?>
                    <form method="get" class="filtros filtros-inline">
                        <label for="filtroEntidade" class="visually-hidden">Tipo de registro</label>
                        <select id="filtroEntidade" name="entidade" class="filtro-select" data-auto-submit>
                            <option value="">Todos os tipos</option>
                            <?php foreach (entidades_lixeira() as $valor => $rotulo): ?>
                                <option value="<?php echo $valor; ?>" <?php echo $valor === $entidade ? 'selected' : ''; ?>><?php echo $rotulo; ?>s</option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn btn-outline btn-sm">Filtrar</button></noscript>
                    </form>

                    <?php if (!$itens): ?>
                        <p class="no-results is-visible">Nenhum item desse tipo na lixeira.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr>
                                        <th>Registro</th>
                                        <th>Tipo</th>
                                        <th>Apagado</th>
                                        <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $item): ?>
                                        <tr>
                                            <td class="cell-client">
                                                <div class="client">
                                                    <span class="avatar" aria-hidden="true"><?php echo icone($iconesLixeira[$item['ENTIDADE']] ?? 'lixeira'); ?></span>
                                                    <span>
                                                        <span class="client-name"><?php echo htmlspecialchars($item['DESCRICAO']); ?></span>
                                                        <span class="client-id">#<?php echo $item['ENTIDADE_ID']; ?> · <?php echo htmlspecialchars(detalhe_lixeira($item)); ?></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td data-label="Tipo"><span class="badge badge-tipo"><?php echo entidades_lixeira()[$item['ENTIDADE']] ?? $item['ENTIDADE']; ?></span></td>
                                            <td data-label="Apagado">
                                                <?php echo date('d/m/Y H:i', strtotime($item['APAGADO_EM'])); ?><br>
                                                <small class="text-muted">por <?php echo $item['USUARIO_NOME'] ? htmlspecialchars($item['USUARIO_NOME']) : '—'; ?></small>
                                            </td>
                                            <td class="col-actions">
                                                <div class="action-buttons">
                                                    <form method="POST" action="lixeira.php" class="form-inline">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="acao" value="restaurar">
                                                        <input type="hidden" name="id" value="<?php echo $item['ID']; ?>">
                                                        <button type="submit" class="btn btn-outline btn-sm"><?php echo icone('restaurar'); ?> Restaurar</button>
                                                    </form>
                                                    <a href="lixeira.php?excluir=<?php echo $item['ID']; ?>" class="btn-icon btn-icon-danger" title="Excluir de vez"
                                                       aria-label="Excluir de vez <?php echo htmlspecialchars($item['DESCRICAO']); ?>"
                                                       data-confirmar-exclusao="<?php echo htmlspecialchars($item['DESCRICAO']); ?> de vez"><?php echo icone('lixeira'); ?></a>
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
