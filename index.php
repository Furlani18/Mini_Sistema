<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/ui.php';

// Painel inicial: números de cada módulo, últimos pedidos e produtos com estoque baixo.

const ESTOQUE_BAIXO = 5;

$numeros = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM clientes)                                   AS clientes,
        (SELECT COUNT(DISTINCT CLIENTE_ID) FROM pedidos)                  AS clientes_com_pedido,
        (SELECT COUNT(*) FROM produtos)                                   AS produtos,
        (SELECT COUNT(*) FROM produtos WHERE ESTOQUE <= " . ESTOQUE_BAIXO . ") AS estoque_baixo,
        (SELECT COUNT(*) FROM pedidos)                                    AS pedidos,
        (SELECT COUNT(*) FROM pedidos WHERE STATUS = 'ABERTO')            AS pedidos_abertos,
        (SELECT COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0)
           FROM itens_pedido i JOIN pedidos p ON p.ID = i.PEDIDO_ID
          WHERE p.STATUS <> 'CANCELADO')                                  AS faturamento"
)->fetch();

$ultimosPedidos = $pdo->query(
    'SELECT p.ID, p.STATUS, p.CRIADO_EM, c.NOME AS CLIENTE,
            COALESCE(SUM(i.QUANTIDADE * i.PRECO_UNITARIO), 0) AS TOTAL
       FROM pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN itens_pedido i ON i.PEDIDO_ID = p.ID
      GROUP BY p.ID, p.STATUS, p.CRIADO_EM, c.NOME
      ORDER BY p.ID DESC
      LIMIT 5'
)->fetchAll();

$estoqueBaixo = $pdo->query(
    'SELECT ID, NOME, ESTOQUE FROM produtos WHERE ESTOQUE <= ' . ESTOQUE_BAIXO . ' ORDER BY ESTOQUE, NOME LIMIT 5'
)->fetchAll();

$plural = fn(int $n, string $um, string $varios) => $n . ' ' . ($n === 1 ? $um : $varios);
$primeiroNome = explode(' ', trim($_SESSION['usuario_nome'] ?? ''))[0];

// Data de hoje por extenso (ex.: "Quinta-feira, 12 de novembro de 2026")
$diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
$meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
$hoje = $diasSemana[(int)date('w')] . ', ' . date('j') . ' de ' . $meses[(int)date('n') - 1] . ' de ' . date('Y');

// Cards de módulo: cada um com o seu número principal e um indicador próprio
$modulos = [
    [
        'cor' => 'azul', 'icone' => 'usuarios', 'titulo' => 'Clientes',
        'numero' => (int)$numeros['clientes'],
        'detalhe' => $numeros['clientes_com_pedido'] > 0
            ? $plural((int)$numeros['clientes_com_pedido'], 'já fez pedido', 'já fizeram pedidos')
            : 'Nenhum pedido feito ainda',
        'cadastrar' => ['cadastro.php', 'Cadastrar'], 'listar' => 'clientes.php',
    ],
    [
        'cor' => 'verde', 'icone' => 'pacote', 'titulo' => 'Produtos',
        'numero' => (int)$numeros['produtos'],
        'detalhe' => $numeros['estoque_baixo'] > 0
            ? $plural((int)$numeros['estoque_baixo'], 'com estoque baixo', 'com estoque baixo')
            : 'Estoque em dia',
        'alerta' => $numeros['estoque_baixo'] > 0,
        'cadastrar' => ['produto_cadastro.php', 'Cadastrar'], 'listar' => 'produtos.php',
    ],
    [
        'cor' => 'laranja', 'icone' => 'carrinho', 'titulo' => 'Pedidos',
        'numero' => (int)$numeros['pedidos'],
        'detalhe' => moeda((float)$numeros['faturamento']) . ' vendidos · ' . $plural((int)$numeros['pedidos_abertos'], 'aberto', 'abertos'),
        'cadastrar' => ['pedido_novo.php', 'Novo pedido'], 'listar' => 'pedidos.php',
    ],
];
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
        <div class="page-head">
          <img src="imagens/bem-vindo.jpeg" alt="Bem-vindo" class="page-head-image" />
          <div class="page-head-texto">
            <p class="page-head-data"><?php echo $hoje; ?></p>
            <h2>Olá, <?php echo htmlspecialchars($primeiroNome ?: 'bem-vindo'); ?>!</h2>
            <p>Resumo de clientes, produtos e pedidos do sistema.</p>
          </div>
          <div class="page-head-acoes">
            <a href="relacionamentos.php" class="btn btn-outline"><?php echo icone('banco'); ?> Relacionamentos</a>
            <a href="pedido_novo.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Pedido</a>
          </div>
        </div>

        <h3 class="secao-titulo">Visão geral</h3>

        <section class="modulos" aria-label="Módulos do sistema">
          <?php foreach ($modulos as $m): ?>
            <article class="modulo modulo-<?php echo $m['cor']; ?>">
              <div class="modulo-topo">
                <span class="modulo-icone"><?php echo icone($m['icone']); ?></span>
                <h3><?php echo $m['titulo']; ?></h3>
              </div>
              <p class="modulo-numero"><?php echo $m['numero']; ?></p>
              <p class="modulo-detalhe<?php echo !empty($m['alerta']) ? ' is-alerta' : ''; ?>">
                <?php if (!empty($m['alerta'])) echo icone('alerta'); ?>
                <?php echo htmlspecialchars($m['detalhe']); ?>
              </p>
              <div class="modulo-acoes">
                <a href="<?php echo $m['cadastrar'][0]; ?>" class="btn btn-outline btn-sm"><?php echo icone('plus'); ?> <?php echo $m['cadastrar'][1]; ?></a>
                <a href="<?php echo $m['listar']; ?>" class="modulo-link">Ver lista <span aria-hidden="true">→</span></a>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <h3 class="secao-titulo">Atividade recente</h3>
        <div class="paineis">
          <section class="painel">
            <div class="painel-cabecalho">
              <h3><?php echo icone('carrinho'); ?> Últimos pedidos</h3>
              <a href="pedidos.php" class="modulo-link">Ver todos <span aria-hidden="true">→</span></a>
            </div>
            <?php if (!$ultimosPedidos): ?>
              <p class="painel-vazio">Nenhum pedido ainda. <a href="pedido_novo.php" class="text-link">Registrar o primeiro</a></p>
            <?php else: ?>
              <ul class="painel-lista">
                <?php foreach ($ultimosPedidos as $p): ?>
                  <li>
                    <a href="pedido_ver.php?id=<?php echo $p['ID']; ?>" class="painel-item">
                      <span class="painel-principal">
                        <strong>#<?php echo $p['ID']; ?> · <?php echo htmlspecialchars($p['CLIENTE']); ?></strong>
                        <small><?php echo date('d/m/Y H:i', strtotime($p['CRIADO_EM'])); ?></small>
                      </span>
                      <?php echo badge_status($p['STATUS']); ?>
                      <span class="painel-valor"><?php echo moeda((float)$p['TOTAL']); ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>

          <section class="painel">
            <div class="painel-cabecalho">
              <h3><?php echo icone('alerta'); ?> Estoque baixo <small class="text-muted">(até <?php echo ESTOQUE_BAIXO; ?> un.)</small></h3>
              <a href="produtos.php" class="modulo-link">Ver produtos <span aria-hidden="true">→</span></a>
            </div>
            <?php if (!$estoqueBaixo): ?>
              <p class="painel-vazio"><?php echo $numeros['produtos'] > 0 ? 'Todos os produtos têm estoque suficiente.' : 'Nenhum produto cadastrado ainda. <a href="produto_cadastro.php" class="text-link">Cadastrar o primeiro</a>'; ?></p>
            <?php else: ?>
              <ul class="painel-lista">
                <?php foreach ($estoqueBaixo as $pr): ?>
                  <li>
                    <a href="produto_cadastro.php?id=<?php echo $pr['ID']; ?>" class="painel-item">
                      <span class="painel-principal"><strong><?php echo htmlspecialchars($pr['NOME']); ?></strong></span>
                      <span class="estoque-qtd<?php echo (int)$pr['ESTOQUE'] === 0 ? ' is-zerado' : ''; ?>">
                        <?php echo (int)$pr['ESTOQUE'] === 0 ? 'Esgotado' : $plural((int)$pr['ESTOQUE'], 'unidade', 'unidades'); ?>
                      </span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        </div>
      </main>

      <?php include __DIR__ . '/footer.php'; ?>
    </div>
  </body>
</html>
