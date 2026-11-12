<?php
// Menu lateral (sidebar) compartilhado por todas as páginas internas.
//
// Antes de incluir, a página pode definir:
//   $breadcrumb = ['Clientes' => 'clientes.php', 'Atualizar' => null];
// O último item (link null) é a página atual. A "Home" é adicionada automaticamente.

require_once __DIR__ . '/ui.php';

$paginaAtual = basename($_SERVER['SCRIPT_NAME']);

// Páginas que pertencem a um item do menu (ex.: cadastrar/editar/apagar fazem parte de "Clientes").
$secoes = [
    'cadastro.php'         => 'clientes.php',
    'atualizar.php'        => 'clientes.php',
    'apagar.php'           => 'clientes.php',
    'produto_cadastro.php' => 'produtos.php',
    'produto_apagar.php'   => 'produtos.php',
    'pedido_novo.php'      => 'pedidos.php',
    'pedido_ver.php'       => 'pedidos.php',
    'pedido_apagar.php'    => 'pedidos.php',
];
$secaoAtiva = $secoes[$paginaAtual] ?? $paginaAtual;

// Menu agrupado: título do grupo => [arquivo => [ícone, rótulo]]
$menu = [
    'Geral' => [
        'index.php' => ['home', 'Painel'],
    ],
    'Cadastros' => [
        'clientes.php' => ['usuarios', 'Clientes'],
        'produtos.php' => ['pacote', 'Produtos'],
        'pedidos.php'  => ['carrinho', 'Pedidos'],
    ],
    'Banco de dados' => [
        'relacionamentos.php' => ['banco', 'Relacionamentos'],
    ],
];

$nomeUsuario = $_SESSION['usuario_nome'] ?? '';
$breadcrumb = $breadcrumb ?? [];
?>
<header class="sidebar">
    <a href="index.php" class="sidebar-brand">
        <span class="brand-mark">CRM</span>
        <span class="brand-text">Gestão de Clientes</span>
    </a>

    <nav class="sidebar-nav" aria-label="Menu principal">
        <?php foreach ($menu as $grupo => $itens): ?>
            <p class="nav-grupo"><?php echo $grupo; ?></p>
            <?php foreach ($itens as $href => [$iconeMenu, $rotulo]): ?>
                <?php $ativo = $href === $secaoAtiva; ?>
                <a href="<?php echo $href; ?>"
                   class="nav-link<?php echo $ativo ? ' active' : ''; ?>"
                   <?php echo $ativo ? 'aria-current="page"' : ''; ?>><?php echo icone($iconeMenu); ?> <?php echo $rotulo; ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-user">
        <span class="sidebar-avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($nomeUsuario ?: '?')); ?></span>
        <span class="sidebar-user-info">
            <strong><?php echo htmlspecialchars($nomeUsuario); ?></strong>
            <small>Usuário logado</small>
        </span>
        <a href="logout.php" class="sidebar-sair" title="Sair" aria-label="Sair do sistema"><?php echo icone('sair'); ?></a>
    </div>
</header>

<?php if ($breadcrumb): ?>
<nav class="breadcrumb" aria-label="Você está em">
    <a href="index.php">Painel</a>
    <?php foreach ($breadcrumb as $rotulo => $href): ?>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
        <?php if ($href): ?>
            <a href="<?php echo htmlspecialchars($href); ?>"><?php echo htmlspecialchars($rotulo); ?></a>
        <?php else: ?>
            <span aria-current="page"><?php echo htmlspecialchars($rotulo); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
