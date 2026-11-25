<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/ui.php';

// Painel inicial: números de cada módulo, meta de vendas do mês, últimos pedidos,
// produtos com estoque baixo, orçamentos aguardando aprovação e aniversariantes do mês.


$numeros = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM clientes)                                   AS clientes,
        (SELECT COUNT(DISTINCT CLIENTE_ID) FROM pedidos)                  AS clientes_com_pedido,
        (SELECT COUNT(*) FROM produtos)                                   AS produtos,
        (SELECT COUNT(*) FROM produtos WHERE ESTOQUE <= " . ESTOQUE_BAIXO . ") AS estoque_baixo,
        (SELECT COUNT(*) FROM pedidos WHERE STATUS <> 'ORCAMENTO')          AS pedidos,
        (SELECT COUNT(*) FROM pedidos WHERE STATUS = 'ABERTO')            AS pedidos_abertos,
        (SELECT COALESCE(SUM(TOTAL), 0) FROM vw_pedidos
          WHERE STATUS IN ('ABERTO', 'PAGO', 'ENVIADO'))                                    AS faturamento"
)->fetch();

$ultimosPedidos = $pdo->query(
    'SELECT p.ID, p.STATUS, p.CRIADO_EM, c.NOME AS CLIENTE, p.TOTAL
       FROM vw_pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
      ORDER BY p.ID DESC
      LIMIT 5'
)->fetchAll();

$estoqueBaixo = $pdo->query(
    'SELECT ID, NOME, ESTOQUE FROM produtos WHERE ESTOQUE <= ' . ESTOQUE_BAIXO . ' ORDER BY ESTOQUE, NOME LIMIT 5'
)->fetchAll();

// Meta de vendas do mês (definida em Configurações): faturamento do mês e projeção
$meta = (float)config_ler($pdo, 'meta_mensal', '0');
$vendidoMes = (float)$pdo->query(
    "SELECT COALESCE(SUM(TOTAL), 0) FROM vw_pedidos
      WHERE STATUS IN ('ABERTO', 'PAGO', 'ENVIADO') AND CRIADO_EM >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
$diasNoMes = (int)date('t');
$diaHoje = (int)date('j');
$projecao = $vendidoMes / $diaHoje * $diasNoMes;
$pctMeta = $meta > 0 ? $vendidoMes / $meta * 100 : 0;
$faltaMeta = max(0, $meta - $vendidoMes);
$diasRestantes = $diasNoMes - $diaHoje + 1; // inclui hoje

// Parcelas vencidas (contas a receber)
$vencidas = $pdo->query(
    "SELECT COUNT(*) AS QTD, COALESCE(SUM(pa.VALOR), 0) AS VALOR
       FROM parcelas pa INNER JOIN pedidos p ON p.ID = pa.PEDIDO_ID
      WHERE pa.PAGO_EM IS NULL AND pa.VENCIMENTO < CURDATE() AND p.STATUS IN ('ABERTO', 'PAGO', 'ENVIADO')"
)->fetch();

// Orçamentos aguardando aprovação
$orcamentos = $pdo->query(
    "SELECT p.ID, p.CRIADO_EM, p.TOTAL, c.NOME AS CLIENTE
       FROM vw_pedidos p INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
      WHERE p.STATUS = 'ORCAMENTO'
      ORDER BY p.CRIADO_EM DESC
      LIMIT 5"
)->fetchAll();

// Aniversariantes do mês (ordem do dia do aniversário)
$aniversariantes = $pdo->query(
    'SELECT ID, NOME, TELEFONE, NASCIMENTO, DAY(NASCIMENTO) AS DIA
       FROM clientes
      WHERE MONTH(NASCIMENTO) = MONTH(CURDATE())
      ORDER BY DAY(NASCIMENTO), NOME
      LIMIT 8'
)->fetchAll();

// Aviso de segurança: o usuário logado ainda usa a senha padrão de instalação?
$stmt = $pdo->prepare('SELECT SENHA FROM usuarios WHERE ID = ?');
$stmt->execute([$_SESSION['usuario_id']]);
$usaSenhaPadrao = password_verify('admin123', (string)$stmt->fetchColumn());

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
            <a href="relatorios.php" class="btn btn-outline"><?php echo icone('grafico'); ?> Relatórios</a>
            <a href="pedido_novo.php" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Pedido</a>
          </div>
        </div>

        <?php if ($usaSenhaPadrao): ?>
          <div class="alert alert-warning" role="alert">
            <?php echo icone('cadeado'); ?>
            <span>Você ainda está usando a <strong>senha padrão de instalação</strong>, que qualquer pessoa pode adivinhar.
              <a href="perfil.php" class="text-link">Trocar minha senha agora</a></span>
          </div>
        <?php endif; ?>

        <?php if ((int)$vencidas['QTD'] > 0): ?>
          <div class="alert alert-warning" role="alert">
            <?php echo icone('dinheiro'); ?>
            <span><strong><?php echo (int)$vencidas['QTD'] === 1 ? '1 parcela vencida' : (int)$vencidas['QTD'] . ' parcelas vencidas'; ?></strong>
              somando <?php echo moeda((float)$vencidas['VALOR']); ?>.
              <a href="contas_receber.php?situacao=vencida" class="text-link">Ver contas a receber</a></span>
          </div>
        <?php endif; ?>

        <?php if ($meta > 0): ?>
          <section class="meta-mes" aria-label="Meta de vendas do mês">
            <div class="meta-cabecalho">
              <h3><?php echo icone('alvo'); ?> Meta de <?php echo nome_mes((int)date('n')); ?></h3>
              <p class="meta-numeros">
                <strong><?php echo moeda($vendidoMes); ?></strong> de <?php echo moeda($meta); ?>
                <span class="meta-pct<?php echo $pctMeta >= 100 ? ' is-batida' : ''; ?>"><?php echo number_format($pctMeta, 0, ',', '.'); ?>%</span>
              </p>
            </div>
            <div class="meta-barra" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)min(100, $pctMeta); ?>"
                 aria-label="<?php echo number_format($pctMeta, 0); ?>% da meta">
              <span class="meta-progresso<?php echo $pctMeta >= 100 ? ' is-batida' : ''; ?>" style="width: <?php echo min(100, round($pctMeta, 1)); ?>%"></span>
              <span class="meta-esperado" style="left: <?php echo round($diaHoje / $diasNoMes * 100, 1); ?>%" title="Onde deveria estar hoje, num ritmo constante"></span>
            </div>
            <p class="meta-detalhe">
              <?php if ($pctMeta >= 100): ?>
                <?php echo icone('sucesso'); ?> Meta batida! <?php echo moeda($vendidoMes - $meta); ?> acima do objetivo.
              <?php else: ?>
                Faltam <strong><?php echo moeda($faltaMeta); ?></strong> em <?php echo $diasRestantes === 1 ? '1 dia' : "$diasRestantes dias"; ?>
                (<?php echo moeda($faltaMeta / $diasRestantes); ?> por dia).
                No ritmo atual, o mês fecha em <strong><?php echo moeda($projecao); ?></strong>
                <?php echo $projecao >= $meta ? '— dentro da meta.' : '— abaixo da meta.'; ?>
              <?php endif; ?>
              <span class="text-muted">A marca na barra mostra onde a venda deveria estar hoje.</span>
            </p>
          </section>
        <?php elseif (eh_admin()): ?>
          <p class="meta-vazia"><?php echo icone('alvo'); ?> Defina uma meta de vendas para acompanhar o mês aqui no painel. <a href="configuracoes.php" class="text-link">Definir meta</a></p>
        <?php endif; ?>

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
                    <a href="produto_estoque.php?id=<?php echo $pr['ID']; ?>&amp;entrada=1#movimentar" class="painel-item">
                      <span class="painel-principal"><strong><?php echo htmlspecialchars($pr['NOME']); ?></strong></span>
                      <span class="estoque-qtd<?php echo (int)$pr['ESTOQUE'] === 0 ? ' is-zerado' : ''; ?>">
                        <?php echo (int)$pr['ESTOQUE'] === 0 ? 'Esgotado' : $plural((int)$pr['ESTOQUE'], 'unidade', 'unidades'); ?>
                      </span>
                      <span class="painel-acao"><?php echo icone('plus'); ?> Dar entrada</span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
        </div>

        <div class="paineis paineis-iguais">
          <section class="painel">
            <div class="painel-cabecalho">
              <h3><?php echo icone('documento-texto'); ?> Orçamentos aguardando</h3>
              <a href="pedidos.php?status=ORCAMENTO" class="modulo-link">Ver todos <span aria-hidden="true">→</span></a>
            </div>
            <?php if (!$orcamentos): ?>
              <p class="painel-vazio">Nenhum orçamento pendente. <a href="pedido_novo.php?orcamento=1" class="text-link">Criar orçamento</a></p>
            <?php else: ?>
              <ul class="painel-lista">
                <?php foreach ($orcamentos as $o): ?>
                  <li>
                    <a href="pedido_ver.php?id=<?php echo $o['ID']; ?>" class="painel-item">
                      <span class="painel-principal">
                        <strong>#<?php echo $o['ID']; ?> · <?php echo htmlspecialchars($o['CLIENTE']); ?></strong>
                        <small><?php echo date('d/m/Y', strtotime($o['CRIADO_EM'])); ?></small>
                      </span>
                      <span class="painel-valor"><?php echo moeda((float)$o['TOTAL']); ?></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>

          <section class="painel">
            <div class="painel-cabecalho">
              <h3><?php echo icone('bolo'); ?> Aniversariantes de <?php echo nome_mes((int)date('n')); ?></h3>
            </div>
            <?php if (!$aniversariantes): ?>
              <p class="painel-vazio">Nenhum cliente faz aniversário este mês<?php echo (int)$pdo->query('SELECT COUNT(*) FROM clientes WHERE NASCIMENTO IS NOT NULL')->fetchColumn() === 0 ? ' (cadastre a data de nascimento na ficha do cliente)' : ''; ?>.</p>
            <?php else: ?>
              <ul class="painel-lista">
                <?php foreach ($aniversariantes as $a): ?>
                  <?php
                  $ehHoje = (int)$a['DIA'] === $diaHoje;
                  $whats = whatsapp_link($a['TELEFONE'], 'Feliz aniversário, ' . explode(' ', trim($a['NOME']))[0] . '! 🎉');
                  ?>
                  <li class="aniversariante<?php echo $ehHoje ? ' is-hoje' : ''; ?>">
                    <a href="cliente_ver.php?id=<?php echo $a['ID']; ?>" class="painel-item">
                      <span class="aniversario-dia" aria-hidden="true"><?php echo str_pad((string)$a['DIA'], 2, '0', STR_PAD_LEFT); ?></span>
                      <span class="painel-principal">
                        <strong><?php echo htmlspecialchars($a['NOME']); ?></strong>
                        <small><?php echo $ehHoje ? 'Hoje!' : 'Dia ' . (int)$a['DIA']; ?> · <?php echo (new DateTime($a['NASCIMENTO']))->diff(new DateTime(date('Y') . '-' . date('m') . '-' . str_pad((string)$a['DIA'], 2, '0', STR_PAD_LEFT)))->y; ?> anos</small>
                      </span>
                    </a>
                    <?php if ($whats): ?>
                      <a href="<?php echo htmlspecialchars($whats); ?>" class="btn-icon btn-icon-whatsapp" target="_blank" rel="noopener"
                         title="Dar parabéns pelo WhatsApp" aria-label="Dar parabéns a <?php echo htmlspecialchars($a['NOME']); ?> pelo WhatsApp"><?php echo icone('whatsapp'); ?></a>
                    <?php endif; ?>
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
