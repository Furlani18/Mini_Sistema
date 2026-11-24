<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Ficha do cliente: dados de contato, indicadores de compra, produtos que mais compra
// e o histórico completo de pedidos.
//   cliente_ver.php?id=ID

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE ID = ?');
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    flash('error', 'Cliente não encontrado.');
    header('Location: clientes.php');
    exit;
}

// Histórico de pedidos (todos, inclusive cancelados) com o total de cada um
$stmt = $pdo->prepare(
    'SELECT p.ID, p.STATUS, p.CRIADO_EM, p.OBSERVACAO, p.ITENS, p.TOTAL
       FROM vw_pedidos p
      WHERE p.CLIENTE_ID = ?
      ORDER BY p.CRIADO_EM DESC, p.ID DESC'
);
$stmt->execute([$id]);
$pedidos = $stmt->fetchAll();

// Indicadores (cancelados não contam como compra)
$validos = array_filter($pedidos, fn($p) => $p['STATUS'] !== 'CANCELADO');
$totalGasto = array_sum(array_map(fn($p) => (float)$p['TOTAL'], $validos));
$qtdValidos = count($validos);
$ticketMedio = $qtdValidos ? $totalGasto / $qtdValidos : 0;
$ultimaCompra = $validos ? max(array_column($validos, 'CRIADO_EM')) : null;
$emAberto = count(array_filter($pedidos, fn($p) => $p['STATUS'] === 'ABERTO'));

// Produtos que o cliente mais compra
$stmt = $pdo->prepare(
    "SELECT pr.ID, pr.NOME,
            SUM(i.QUANTIDADE) AS QUANTIDADE,
            SUM(i.QUANTIDADE * i.PRECO_UNITARIO) AS VALOR
       FROM itens_pedido i
      INNER JOIN pedidos p ON p.ID = i.PEDIDO_ID
      INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
      WHERE p.CLIENTE_ID = ? AND p.STATUS <> 'CANCELADO'
      GROUP BY pr.ID, pr.NOME
      ORDER BY QUANTIDADE DESC, VALOR DESC
      LIMIT 5"
);
$stmt->execute([$id]);
$favoritos = $stmt->fetchAll();
$maiorQtd = $favoritos ? max(array_map(fn($f) => (int)$f['QUANTIDADE'], $favoritos)) : 0;

/** "há 3 dias", "hoje", "há 2 meses"… */
function tempo_desde(string $data): string
{
    $dias = (int)(new DateTime(date('Y-m-d', strtotime($data))))->diff(new DateTime('today'))->days;
    return match (true) {
        $dias === 0  => 'hoje',
        $dias === 1  => 'ontem',
        $dias < 30   => "há $dias dias",
        $dias < 60   => 'há 1 mês',
        $dias < 365  => 'há ' . intdiv($dias, 30) . ' meses',
        $dias < 730  => 'há 1 ano',
        default      => 'há ' . intdiv($dias, 365) . ' anos',
    };
}

$nome = htmlspecialchars($cliente['NOME']);
$indicadores = [
    ['Total gasto', moeda($totalGasto), $qtdValidos ? 'Sem contar cancelados' : 'Nenhuma compra ainda'],
    ['Pedidos', (string)count($pedidos), $emAberto ? ($emAberto === 1 ? '1 em aberto' : "$emAberto em aberto") : 'Nenhum em aberto'],
    ['Ticket médio', moeda($ticketMedio), 'Valor médio por pedido'],
    ['Última compra', $ultimaCompra ? date('d/m/Y', strtotime($ultimaCompra)) : '—', $ultimaCompra ? tempo_desde($ultimaCompra) : 'Nunca comprou'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nome; ?> - Ficha do Cliente</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Clientes' => 'clientes.php', $cliente['NOME'] => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <section class="perfil-cabecalho">
                <span class="avatar avatar-lg" aria-hidden="true"><?php echo htmlspecialchars(iniciais($cliente['NOME'])); ?></span>
                <div class="perfil-info">
                    <h2><?php echo $nome; ?> <span class="page-title-id">#<?php echo $cliente['ID']; ?></span></h2>
                    <ul class="perfil-contatos">
                        <li><?php echo icone('email'); ?> <a href="mailto:<?php echo htmlspecialchars($cliente['EMAIL']); ?>"><?php echo htmlspecialchars($cliente['EMAIL']); ?></a></li>
                        <?php if ($cliente['TELEFONE']): ?>
                            <li><?php echo icone('telefone'); ?> <?php echo htmlspecialchars($cliente['TELEFONE']); ?></li>
                        <?php endif; ?>
                        <?php if ($cliente['CPF_CNPJ']): ?>
                            <li><?php echo icone('documento'); ?> <?php echo strlen($cliente['CPF_CNPJ']) === 14 ? 'CNPJ' : 'CPF'; ?> <?php echo formatar_documento($cliente['CPF_CNPJ']); ?></li>
                        <?php endif; ?>
                        <li><?php echo icone('calendario'); ?> Cliente desde <?php echo date('d/m/Y', strtotime($cliente['CRIADO_EM'])); ?></li>
                    </ul>
                    <?php $endereco = endereco_cliente($cliente); ?>
                    <?php if ($endereco): ?>
                        <p class="perfil-endereco"><?php echo icone('mapa'); ?> <?php echo htmlspecialchars($endereco); ?></p>
                    <?php endif; ?>
                </div>
                <div class="page-head-acoes">
                    <a href="atualizar.php?id=<?php echo $cliente['ID']; ?>" class="btn btn-outline"><?php echo icone('editar'); ?> Editar</a>
                    <a href="pedido_novo.php?cliente=<?php echo $cliente['ID']; ?>" class="btn btn-primary"><?php echo icone('plus'); ?> Novo Pedido</a>
                </div>
            </section>

            <?php if ($cliente['OBSERVACOES']): ?>
                <div class="nota-cliente">
                    <?php echo icone('nota'); ?>
                    <div>
                        <strong>Observações</strong>
                        <p><?php echo nl2br(htmlspecialchars($cliente['OBSERVACOES'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <section class="indicadores" aria-label="Indicadores do cliente">
                <?php foreach ($indicadores as [$rotulo, $valor, $detalhe]): ?>
                    <div class="indicador">
                        <p class="indicador-rotulo"><?php echo $rotulo; ?></p>
                        <p class="indicador-valor"><?php echo $valor; ?></p>
                        <p class="indicador-detalhe"><?php echo $detalhe; ?></p>
                    </div>
                <?php endforeach; ?>
            </section>

            <div class="paineis">
                <section class="painel">
                    <div class="painel-cabecalho">
                        <h3><?php echo icone('carrinho'); ?> Histórico de pedidos</h3>
                        <?php if ($pedidos): ?>
                            <a href="pedidos.php?cliente=<?php echo $cliente['ID']; ?>" class="modulo-link">Filtrar na lista <span aria-hidden="true">→</span></a>
                        <?php endif; ?>
                    </div>
                    <?php if (!$pedidos): ?>
                        <p class="painel-vazio">Este cliente ainda não fez pedidos.
                            <a href="pedido_novo.php?cliente=<?php echo $cliente['ID']; ?>" class="text-link">Registrar o primeiro</a></p>
                    <?php else: ?>
                        <ul class="painel-lista">
                            <?php foreach ($pedidos as $p): ?>
                                <li>
                                    <a href="pedido_ver.php?id=<?php echo $p['ID']; ?>" class="painel-item">
                                        <span class="painel-principal">
                                            <strong>Pedido #<?php echo $p['ID']; ?></strong>
                                            <small>
                                                <?php echo date('d/m/Y H:i', strtotime($p['CRIADO_EM'])); ?> ·
                                                <?php echo (int)$p['ITENS'] === 1 ? '1 item' : (int)$p['ITENS'] . ' itens'; ?>
                                                <?php echo $p['OBSERVACAO'] ? ' · ' . htmlspecialchars($p['OBSERVACAO']) : ''; ?>
                                            </small>
                                        </span>
                                        <?php echo badge_status($p['STATUS']); ?>
                                        <span class="painel-valor<?php echo $p['STATUS'] === 'CANCELADO' ? ' is-riscado' : ''; ?>"><?php echo moeda((float)$p['TOTAL']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="painel">
                    <div class="painel-cabecalho">
                        <h3><?php echo icone('pacote'); ?> Produtos mais comprados</h3>
                    </div>
                    <?php if (!$favoritos): ?>
                        <p class="painel-vazio">Nenhuma compra registrada ainda.</p>
                    <?php else: ?>
                        <ul class="ranking">
                            <?php foreach ($favoritos as $f): ?>
                                <li class="ranking-item">
                                    <div class="ranking-linha">
                                        <span class="ranking-nome"><?php echo htmlspecialchars($f['NOME']); ?></span>
                                        <span class="ranking-valor"><?php echo (int)$f['QUANTIDADE']; ?> un. · <?php echo moeda((float)$f['VALOR']); ?></span>
                                    </div>
                                    <span class="ranking-barra" aria-hidden="true"><span style="width: <?php echo round((int)$f['QUANTIDADE'] / $maiorQtd * 100, 1); ?>%"></span></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>

            <div class="form-actions form-actions-solta">
                <a href="clientes.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Clientes</a>
                <?php if (eh_admin()): ?>
                <a href="apagar.php?id=<?php echo $cliente['ID']; ?>" class="btn btn-danger-outline"
                   data-confirmar-exclusao="<?php echo $nome; ?>"><?php echo icone('lixeira'); ?> Apagar Cliente</a>
                <?php endif; ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
