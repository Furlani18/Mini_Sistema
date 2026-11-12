<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
?>
<!doctype html>
<html lang="pt-BR">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sistema de Gerenciamento de Clientes</title>
    <link rel="stylesheet" href="css/style.css" />
  </head>
  <body>
    <div class="container">
      <?php include __DIR__ . '/header.php'; ?>

      <main>
        <section class="welcome">
          <div class="welcome-content">
            <img
              src="imagens/bem-vindo.jpeg"
              alt="Bem-vindo"
              class="welcome-image"
            />
            <h2>Bem-vindo ao Sistema</h2>
            <p>
              Gerencie facilmente seus clientes, produtos e pedidos com nosso sistema intuitivo.
            </p>
            <div class="actions">
              <a href="cadastro.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Cliente</a>
              <a href="pedido_novo.php" class="btn btn-primary"><?php echo icone('carrinho'); ?> Novo Pedido</a>
              <a href="relacionamentos.php" class="btn btn-secondary"><?php echo icone('banco'); ?> Ver Relacionamentos</a>
            </div>
          </div>
        </section>

        <?php
        // Cards de funcionalidades: [link, ícone, classe extra do ícone, título, descrição, dica]
        $grupos = [
            ['usuarios', 'Clientes', [
                ['cadastro.php', 'plus', '', 'Cadastrar Cliente', 'Adicione novos clientes ao sistema', ''],
                ['clientes.php', 'lista', '', 'Listar Clientes', 'Visualize e busque os clientes cadastrados', ''],
                ['clientes.php', 'editar', 'feature-icon-warning', 'Atualizar Cliente', 'Modifique informações dos clientes', 'Escolha um cliente na lista para atualizar'],
                ['clientes.php', 'lixeira', 'feature-icon-danger', 'Apagar Cliente', 'Remova clientes do sistema', 'Escolha um cliente na lista para apagar'],
            ]],
            ['pacote', 'Produtos', [
                ['produto_cadastro.php', 'plus', '', 'Cadastrar Produto', 'Adicione produtos com preço e estoque', ''],
                ['produtos.php', 'lista', '', 'Listar Produtos', 'Visualize e busque os produtos cadastrados', ''],
                ['produtos.php', 'editar', 'feature-icon-warning', 'Atualizar Produto', 'Altere preço, estoque e descrição', 'Escolha um produto na lista para atualizar'],
                ['produtos.php', 'lixeira', 'feature-icon-danger', 'Apagar Produto', 'Remova produtos que nunca foram vendidos', 'Escolha um produto na lista para apagar'],
            ]],
            ['carrinho', 'Pedidos', [
                ['pedido_novo.php', 'plus', '', 'Cadastrar Pedido', 'Registre um pedido com vários produtos', ''],
                ['pedidos.php', 'lista', '', 'Listar Pedidos', 'Visualize e busque os pedidos registrados', ''],
                ['pedidos.php', 'editar', 'feature-icon-warning', 'Atualizar Pedido', 'Altere o status do pedido (pago, enviado...)', 'Abra um pedido na lista para mudar o status'],
                ['pedidos.php', 'lixeira', 'feature-icon-danger', 'Apagar Pedido', 'Remova pedidos e seus itens', 'Escolha um pedido na lista para apagar'],
            ]],
        ];
        ?>
        <section class="features">
          <h3>Funcionalidades</h3>
          <?php foreach ($grupos as [$iconeGrupo, $tituloGrupo, $cards]): ?>
            <div class="features-group">
              <h4 class="features-subtitle"><?php echo icone($iconeGrupo); ?> <?php echo $tituloGrupo; ?></h4>
              <div class="features-grid">
                <?php foreach ($cards as [$href, $iconeCard, $classeIcone, $titulo, $descricao, $dica]): ?>
                  <a href="<?php echo $href; ?>" class="feature-card"<?php echo $dica ? ' title="' . $dica . '"' : ''; ?>>
                    <span class="feature-icon <?php echo $classeIcone; ?>"><?php echo icone($iconeCard); ?></span>
                    <h4><?php echo $titulo; ?></h4>
                    <p><?php echo $descricao; ?></p>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      </main>

      <?php include __DIR__ . '/footer.php'; ?>
    </div>
  </body>
</html>
