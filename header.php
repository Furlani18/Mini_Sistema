<?php
// Menu lateral (sidebar) compartilhado por todas as páginas internas.
//
// Antes de incluir, a página pode definir:
//   $breadcrumb = ['Clientes' => 'clientes.php', 'Atualizar' => null];
// O último item (link null) é a página atual. A "Home" é adicionada automaticamente.

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/seguranca.php';

$paginaAtual = basename($_SERVER['SCRIPT_NAME']);

// Páginas que pertencem a um item do menu (ex.: cadastrar/editar/apagar fazem parte de "Clientes").
$secoes = [
    'cadastro.php'         => 'clientes.php',
    'atualizar.php'        => 'clientes.php',
    'apagar.php'           => 'clientes.php',
    'cliente_ver.php'      => 'clientes.php',
    'usuario_cadastro.php' => 'usuarios.php',
    'usuario_apagar.php'   => 'usuarios.php',
    'cliente_importar.php' => 'clientes.php',
    'produto_cadastro.php' => 'produtos.php',
    'categorias.php'       => 'produtos.php',
    'fornecedor_cadastro.php' => 'fornecedores.php',
    'fornecedor_apagar.php'   => 'fornecedores.php',
    'produto_apagar.php'   => 'produtos.php',
    'produto_estoque.php'  => 'produtos.php',
    'pedido_novo.php'      => 'pedidos.php',
    'pedido_ver.php'       => 'pedidos.php',
    'pedido_apagar.php'    => 'pedidos.php',
];
$secaoAtiva = $secoes[$paginaAtual] ?? $paginaAtual;

// Menu agrupado: título do grupo => [arquivo => [ícone, rótulo]]
$menu = [
    'Geral' => [
        'index.php'      => ['home', 'Painel'],
        'relatorios.php' => ['grafico', 'Relatórios'],
    ],
    'Vendas' => [
        'pedidos.php'        => ['carrinho', 'Pedidos'],
        'contas_receber.php' => ['dinheiro', 'Contas a receber'],
    ],
    'Cadastros' => [
        'clientes.php'     => ['usuarios', 'Clientes'],
        'produtos.php'     => ['pacote', 'Produtos'],
        'fornecedores.php' => ['caminhao', 'Fornecedores'],
    ],
    'Administração' => [
        'usuarios.php'        => ['escudo', 'Usuários'],
        'atividades.php'      => ['historico', 'Atividades'],
        'lixeira.php'         => ['lixeira', 'Lixeira'],
        'configuracoes.php'   => ['engrenagem', 'Configurações'],
        'relacionamentos.php' => ['banco', 'Banco de dados'],
    ],
];
// Operadores não veem as funções exclusivas de administrador
if (!eh_admin()) {
    unset($menu['Administração']['usuarios.php'], $menu['Administração']['atividades.php'],
          $menu['Administração']['lixeira.php'], $menu['Administração']['configuracoes.php']);
}

$nomeUsuario = $_SESSION['usuario_nome'] ?? '';
$breadcrumb = $breadcrumb ?? [];
?>
<script>
    // Aplica o tema salvo (ou o do sistema operacional) antes de desenhar a página, para não "piscar"
    (function () {
        var tema = localStorage.getItem('tema');
        if (!tema && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) tema = 'escuro';
        if (tema === 'escuro') document.documentElement.setAttribute('data-tema', 'escuro');
    })();
</script>
<header class="sidebar">
    <a href="index.php" class="sidebar-brand">
        <span class="brand-mark">CRM</span>
        <span class="brand-text">Gestão de Clientes</span>
    </a>

    <button type="button" class="busca-atalho" data-abrir-busca aria-keyshortcuts="Control+K">
        <?php echo icone('busca'); ?> <span>Buscar…</span> <kbd>Ctrl K</kbd>
    </button>

    <nav class="sidebar-nav" aria-label="Menu principal">
        <?php // Nomes com prefixo "menu" para não sobrescrever variáveis da página que inclui este arquivo ?>
        <?php foreach ($menu as $menuGrupo => $menuLinks): ?>
            <p class="nav-grupo"><?php echo $menuGrupo; ?></p>
            <?php foreach ($menuLinks as $menuHref => [$menuIcone, $menuRotulo]): ?>
                <?php $menuAtivo = $menuHref === $secaoAtiva; ?>
                <a href="<?php echo $menuHref; ?>"
                   class="nav-link<?php echo $menuAtivo ? ' active' : ''; ?>"
                   <?php echo $menuAtivo ? 'aria-current="page"' : ''; ?>><?php echo icone($menuIcone); ?> <?php echo $menuRotulo; ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <button type="button" class="nav-link nav-tema" data-alternar-tema>
        <?php echo icone('lua', 'icone-tema-escuro'); ?><?php echo icone('sol', 'icone-tema-claro'); ?>
        <span class="icone-tema-escuro">Modo escuro</span><span class="icone-tema-claro">Modo claro</span>
    </button>

    <div class="sidebar-user<?php echo $paginaAtual === 'perfil.php' ? ' active' : ''; ?>">
        <a href="perfil.php" class="sidebar-user-link" title="Meu perfil"
           <?php echo $paginaAtual === 'perfil.php' ? 'aria-current="page"' : ''; ?>>
            <span class="sidebar-avatar" aria-hidden="true"><?php echo htmlspecialchars(iniciais($nomeUsuario ?: '?')); ?></span>
            <span class="sidebar-user-info">
                <strong><?php echo htmlspecialchars($nomeUsuario); ?></strong>
                <small><?php echo perfis()[$_SESSION['usuario_perfil'] ?? ''] ?? 'Usuário'; ?> · Meu perfil</small>
            </span>
        </a>
        <form method="POST" action="logout.php" class="sidebar-sair-form">
            <?php echo csrf_campo(); ?>
            <button type="submit" class="sidebar-sair" title="Sair" aria-label="Sair do sistema"><?php echo icone('sair'); ?></button>
        </form>
    </div>
</header>

<?php if ($breadcrumb): ?>
<nav class="breadcrumb" aria-label="Você está em">
    <a href="index.php">Painel</a>
    <?php foreach ($breadcrumb as $menuRotulo => $menuHref): ?>
        <span class="breadcrumb-sep" aria-hidden="true">›</span>
        <?php if ($menuHref): ?>
            <a href="<?php echo htmlspecialchars($menuHref); ?>"><?php echo htmlspecialchars($menuRotulo); ?></a>
        <?php else: ?>
            <span aria-current="page"><?php echo htmlspecialchars($menuRotulo); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
