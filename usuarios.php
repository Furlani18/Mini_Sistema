<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Lista de usuários do sistema (quem faz login), com busca, ordenação e paginação.
// LEFT JOIN com pedidos para mostrar quantos pedidos cada usuário registrou.

$q = busca_da_url();
$where = '';
$parametros = [];
if ($q !== '') {
    $where = 'WHERE u.NOME LIKE ? OR u.EMAIL LIKE ?';
    $parametros = [like($q), like($q)];
}

$ordem = ordenacao([
    'nome'    => 'u.NOME',
    'email'   => 'u.EMAIL',
    'pedidos' => 'PEDIDOS',
    'criado'  => 'u.CRIADO_EM',
], 'nome');

$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios u $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT u.ID, u.NOME, u.EMAIL, u.TELEFONE, u.CRIADO_EM, COUNT(p.ID) AS PEDIDOS
       FROM usuarios u
       LEFT JOIN pedidos p ON p.USUARIO_ID = u.ID
       $where
      GROUP BY u.ID, u.NOME, u.EMAIL, u.TELEFONE, u.CRIADO_EM
      ORDER BY {$ordem['sql']}, u.ID
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$usuarios = $stmt->fetchAll();

$meuId = (int)$_SESSION['usuario_id'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Usuários' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('escudo'); ?> Usuários do Sistema</h2>
                    <a href="usuario_cadastro.php" class="btn btn-primary"><?php echo icone('usuario-mais'); ?> Novo Usuário</a>
                </div>

                <p class="intro">Usuários são as pessoas que acessam o sistema com email e senha. Cada pedido guarda quem o registrou.</p>

                <form method="get" class="filtros" role="search">
                    <?php echo campos_ocultos(['q', 'pagina']); ?>
                    <label for="buscaUsuarios" class="visually-hidden">Buscar usuário</label>
                    <div class="search-input">
                        <?php echo icone('busca'); ?>
                        <input type="search" id="buscaUsuarios" name="q" value="<?php echo htmlspecialchars($q); ?>"
                               placeholder="Buscar por nome ou email" autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Buscar</button>
                    <?php if ($q !== ''): ?>
                        <a href="usuarios.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                    <?php endif; ?>
                </form>

                <?php if (!$usuarios): ?>
                    <p class="no-results is-visible">Nenhum usuário encontrado para “<?php echo htmlspecialchars($q); ?>”.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead>
                                <tr>
                                    <?php echo th_ordenavel('nome', 'Usuário', $ordem); ?>
                                    <?php echo th_ordenavel('email', 'Email', $ordem); ?>
                                    <th>Telefone</th>
                                    <?php echo th_ordenavel('pedidos', 'Pedidos registrados', $ordem, 'num', 'desc'); ?>
                                    <?php echo th_ordenavel('criado', 'Criado em', $ordem, '', 'desc'); ?>
                                    <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <?php $nome = htmlspecialchars($u['NOME']); $souEu = (int)$u['ID'] === $meuId; ?>
                                    <tr>
                                        <td class="cell-client">
                                            <div class="client">
                                                <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($u['NOME'])); ?></span>
                                                <span>
                                                    <span class="client-name"><?php echo $nome; ?>
                                                        <?php if ($souEu): ?><span class="badge badge-tipo">Você</span><?php endif; ?></span>
                                                    <span class="client-id">#<?php echo $u['ID']; ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td data-label="Email"><?php echo htmlspecialchars($u['EMAIL']); ?></td>
                                        <td data-label="Telefone"><?php echo $u['TELEFONE'] ? htmlspecialchars($u['TELEFONE']) : '<span class="text-muted">—</span>'; ?></td>
                                        <td class="num" data-label="Pedidos registrados"><?php echo (int)$u['PEDIDOS']; ?></td>
                                        <td data-label="Criado em"><?php echo date('d/m/Y', strtotime($u['CRIADO_EM'])); ?></td>
                                        <td class="col-actions">
                                            <div class="action-buttons">
                                                <a href="<?php echo $souEu ? 'perfil.php' : 'usuario_cadastro.php?id=' . $u['ID']; ?>" class="btn-icon" title="Editar"
                                                   aria-label="Editar <?php echo $nome; ?>"><?php echo icone('editar'); ?></a>
                                                <?php if ($souEu): ?>
                                                    <span class="btn-icon is-disabled" title="Você não pode apagar o próprio usuário" aria-hidden="true"><?php echo icone('lixeira'); ?></span>
                                                <?php else: ?>
                                                    <a href="usuario_apagar.php?id=<?php echo $u['ID']; ?>" class="btn-icon btn-icon-danger" title="Apagar"
                                                       aria-label="Apagar <?php echo $nome; ?>"
                                                       data-confirmar-exclusao="o usuário <?php echo $nome; ?>"><?php echo icone('lixeira'); ?></a>
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
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
