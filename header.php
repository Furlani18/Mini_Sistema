<?php
// Cabeçalho compartilhado por todas as páginas internas.
//
// Antes de incluir, a página pode definir:
//   $breadcrumb = ['Clientes' => 'clientes.php', 'Atualizar' => null];
// O último item (link null) é a página atual. A "Home" é adicionada automaticamente.

require_once __DIR__ . '/ui.php';

$paginaAtual = basename($_SERVER['SCRIPT_NAME']);

// Páginas que pertencem a uma seção do menu (ex.: editar/apagar fazem parte de "Clientes").
$secoes = [
    'atualizar.php'      => 'clientes.php',
    'apagar.php'         => 'clientes.php',
    'produto_cadastro.php' => 'produtos.php',
    'produto_apagar.php' => 'produtos.php',
    'pedido_novo.php'    => 'pedidos.php',
    'pedido_ver.php'     => 'pedidos.php',
    'pedido_apagar.php'  => 'pedidos.php',
];
$secaoAtiva = $secoes[$paginaAtual] ?? $paginaAtual;

$menu = [
    'index.php'            => ['home', 'Home'],
    'cadastro.php'         => ['usuario-mais', 'Cadastrar Cliente'],
    'clientes.php'         => ['usuarios', 'Listar Clientes'],
    'produtos.php'         => ['pacote', 'Produtos'],
    'pedidos.php'          => ['carrinho', 'Pedidos'],
    'relacionamentos.php'  => ['banco', 'Relacionamentos'],
];

$breadcrumb = $breadcrumb ?? [];
?>
<header>
    <div class="header-top">
        <h1><a href="index.php" class="brand-link"><span class="brand-mark">CRM</span> Gestão de Clientes</a></h1>
        <div class="user-bar">
            <span class="user-name"><?php echo icone('usuario'); ?> <?php echo htmlspecialchars($_SESSION['usuario_nome'] ?? ''); ?></span>
            <a href="logout.php" class="btn btn-outline btn-sm"><?php echo icone('sair'); ?> Sair</a>
        </div>
    </div>
    <nav aria-label="Menu principal">
        <?php foreach ($menu as $href => [$iconeMenu, $rotulo]): ?>
            <?php $ativo = $href === $secaoAtiva; ?>
            <a href="<?php echo $href; ?>"
               class="nav-link<?php echo $ativo ? ' active' : ''; ?>"
               <?php echo $ativo ? 'aria-current="page"' : ''; ?>><?php echo icone($iconeMenu); ?> <?php echo $rotulo; ?></a>
        <?php endforeach; ?>
    </nav>
</header>

<?php if ($breadcrumb): ?>
<nav class="breadcrumb" aria-label="Você está em">
    <a href="index.php">Home</a>
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
