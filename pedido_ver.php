<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'estoque.php';
require_once 'financeiro.php';

// Detalhes de um pedido (ou orçamento): dados do pedido + cliente + usuário (JOINs),
// itens com os produtos (itens_pedido ⨝ produtos), mudança de status e parcelas.
// Os totais vêm da visão vw_pedidos (SUBTOTAL - DESCONTO = TOTAL).
//
// Ações (POST, campo "acao"):
//   status          → muda o status (baixa/devolve estoque quando preciso)
//   gerar_parcelas  → divide o total em parcelas
//   refazer         → apaga as parcelas (só se nenhuma foi recebida)
//   receber         → registra o recebimento de uma parcela
//   estornar        → desfaz o recebimento de uma parcela

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$buscarPedido = $pdo->prepare(
    'SELECT p.ID, p.STATUS, p.ORIGEM, pe.ENTREGA, p.OBSERVACAO, p.CRIADO_EM, p.DESCONTO, p.FORMA_PAGAMENTO, p.SUBTOTAL, p.TOTAL,
            c.ID AS CLIENTE_ID, c.NOME AS CLIENTE, c.EMAIL AS CLIENTE_EMAIL, c.TELEFONE AS CLIENTE_TELEFONE,
            u.NOME AS USUARIO
       FROM vw_pedidos p
      INNER JOIN pedidos pe ON pe.ID = p.ID
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
      WHERE p.ID = ?'
);
$buscarPedido->execute([$id]);
$pedido = $buscarPedido->fetch();

if (!$pedido) {
    flash('error', 'Pedido não encontrado.');
    header('Location: pedidos.php');
    exit;
}

$ehOrcamento = $pedido['STATUS'] === 'ORCAMENTO';
$nomeDoc = $ehOrcamento ? 'Orçamento' : 'Pedido';
$parcelas = parcelas_do_pedido($pdo, $id);
$algumaRecebida = (bool)array_filter($parcelas, fn($p) => $p['PAGO_EM'] !== null);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? 'status');

    if ($acao === 'status') {
        $novo = (string)($_POST['status'] ?? '');
        $atual = $pedido['STATUS'];

        if (!isset(status_pedidos()[$novo])) {
            flash('error', 'Status inválido.');
        } elseif ($novo === 'ORCAMENTO' && $atual !== 'ORCAMENTO') {
            flash('error', 'Um pedido não pode voltar a ser orçamento. Cancele-o e crie um novo orçamento.');
        } elseif ($novo !== $atual) {
            $pdo->beginTransaction();
            $faltando = estoque_mudanca_status($pdo, $id, $atual, $novo);
            if ($faltando) {
                $pdo->rollBack();
                flash('error', 'Não foi possível mudar o status. Estoque insuficiente para: ' . implode(', ', $faltando) . '.');
            } else {
                $pdo->prepare('UPDATE pedidos SET STATUS = ? WHERE ID = ?')->execute([$novo, $id]);
                $pdo->commit();
                if ($atual === 'ORCAMENTO' && status_eh_venda($novo)) {
                    registrar_atividade($pdo, 'ALTEROU', "Aprovou o orçamento #$id (virou pedido " . status_pedidos()[$novo] . ')', 'pedido', $id);
                    flash('success', "Orçamento aprovado! Agora é o pedido #$id e o estoque foi baixado.");
                } else {
                    registrar_atividade($pdo, 'ALTEROU', "$nomeDoc #$id: status de " . status_pedidos()[$atual] . ' para ' . status_pedidos()[$novo], 'pedido', $id);
                    flash('success', 'Status alterado para "' . status_pedidos()[$novo] . '".');
                }
            }
        }
    } elseif ($acao === 'gerar_parcelas') {
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $intervalo = (int)($_POST['intervalo'] ?? 30);
        $primeiro = (string)($_POST['primeiro_vencimento'] ?? '');
        $forma = (string)($_POST['forma_pagamento'] ?? '');
        $data = DateTime::createFromFormat('!Y-m-d', $primeiro);

        if (!status_eh_venda($pedido['STATUS'])) {
            flash('error', 'Só pedidos de venda (não orçamentos nem cancelados) recebem parcelas.');
        } elseif ($parcelas) {
            flash('error', 'Este pedido já tem parcelas.');
        } elseif ((float)$pedido['TOTAL'] <= 0) {
            flash('error', 'O pedido não tem valor a receber.');
        } elseif ($quantidade < 1 || $quantidade > PARCELAS_MAXIMO || !in_array($intervalo, [7, 15, 30], true)) {
            flash('error', 'Escolha de 1 a ' . PARCELAS_MAXIMO . ' parcelas e um intervalo válido.');
        } elseif (!$data || $data->format('Y-m-d') !== $primeiro) {
            flash('error', 'Informe a data do primeiro vencimento.');
        } elseif ($forma !== '' && !isset(formas_pagamento()[$forma])) {
            flash('error', 'Forma de pagamento inválida.');
        } elseif ((float)$pedido['TOTAL'] / $quantidade < 0.01) {
            flash('error', 'O valor de cada parcela ficaria menor que R$ 0,01.');
        } else {
            $inserir = $pdo->prepare('INSERT INTO parcelas (PEDIDO_ID, NUMERO, VALOR, VENCIMENTO, FORMA_PAGAMENTO) VALUES (?, ?, ?, ?, ?)');
            $pdo->beginTransaction();
            foreach (dividir_parcelas((float)$pedido['TOTAL'], $quantidade, $primeiro, $intervalo) as $parcela) {
                $inserir->execute([$id, $parcela['numero'], $parcela['valor'], $parcela['vencimento'], $forma ?: null]);
            }
            if ($forma !== '' && !$pedido['FORMA_PAGAMENTO']) {
                $pdo->prepare('UPDATE pedidos SET FORMA_PAGAMENTO = ? WHERE ID = ?')->execute([$forma, $id]);
            }
            $pdo->commit();
            registrar_atividade($pdo, 'PAGAMENTO', "Pedido #$id dividido em {$quantidade}x de " . moeda((float)$pedido['TOTAL'] / $quantidade), 'pedido', $id);
            flash('success', $quantidade === 1 ? 'Parcela única criada.' : "Pedido dividido em {$quantidade} parcelas.");
        }
    } elseif ($acao === 'refazer') {
        if ($algumaRecebida) {
            flash('error', 'Há parcelas já recebidas. Estorne os recebimentos antes de refazer as parcelas.');
        } else {
            $pdo->prepare('DELETE FROM parcelas WHERE PEDIDO_ID = ?')->execute([$id]);
            registrar_atividade($pdo, 'PAGAMENTO', "Apagou as parcelas do pedido #$id para refazer", 'pedido', $id);
            flash('success', 'Parcelas apagadas. Defina a nova divisão.');
        }
    } elseif ($acao === 'receber' || $acao === 'estornar') {
        $parcelaId = (int)($_POST['parcela_id'] ?? 0);
        $parcela = current(array_filter($parcelas, fn($p) => (int)$p['ID'] === $parcelaId)) ?: null;

        if (!$parcela) {
            flash('error', 'Parcela não encontrada.');
        } elseif ($acao === 'receber') {
            $pagoEm = (string)($_POST['pago_em'] ?? date('Y-m-d'));
            $forma = (string)($_POST['forma_pagamento'] ?? '');
            $data = DateTime::createFromFormat('!Y-m-d', $pagoEm);
            if (!$data || $data->format('Y-m-d') !== $pagoEm || $pagoEm > date('Y-m-d')) {
                flash('error', 'Informe a data do recebimento (hoje ou antes).');
            } elseif ($forma !== '' && !isset(formas_pagamento()[$forma])) {
                flash('error', 'Forma de pagamento inválida.');
            } else {
                $pdo->prepare('UPDATE parcelas SET PAGO_EM = ?, FORMA_PAGAMENTO = COALESCE(?, FORMA_PAGAMENTO) WHERE ID = ?')
                    ->execute([$pagoEm, $forma ?: null, $parcelaId]);
                $quitou = marcar_pedido_pago_se_quitado($pdo, $id);
                registrar_atividade($pdo, 'PAGAMENTO', "Recebeu a parcela {$parcela['NUMERO']} do pedido #$id (" . moeda((float)$parcela['VALOR']) . ')', 'pedido', $id);
                flash('success', 'Recebimento registrado.' . ($quitou ? ' Pedido quitado: status alterado para "Pago".' : ''));
            }
        } else {
            $pdo->prepare('UPDATE parcelas SET PAGO_EM = NULL WHERE ID = ?')->execute([$parcelaId]);
            registrar_atividade($pdo, 'PAGAMENTO', "Estornou o recebimento da parcela {$parcela['NUMERO']} do pedido #$id", 'pedido', $id);
            flash('success', 'Recebimento desfeito.');
        }
    }

    header('Location: pedido_ver.php?id=' . $id);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pr.ID AS PRODUTO_ID, pr.NOME AS PRODUTO, pr.SKU, i.QUANTIDADE, i.PRECO_UNITARIO,
            i.QUANTIDADE * i.PRECO_UNITARIO AS SUBTOTAL
       FROM itens_pedido i
      INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
      WHERE i.PEDIDO_ID = ?
      ORDER BY pr.NOME'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

// Resumo das parcelas
$recebido = array_sum(array_map(fn($p) => $p['PAGO_EM'] !== null ? (float)$p['VALOR'] : 0, $parcelas));
$aReceber = array_sum(array_map(fn($p) => $p['PAGO_EM'] === null ? (float)$p['VALOR'] : 0, $parcelas));

// Mensagem pronta para o WhatsApp do cliente
$primeiroNome = explode(' ', trim($pedido['CLIENTE']))[0];
$linhasItens = array_map(fn($i) => '• ' . (int)$i['QUANTIDADE'] . 'x ' . $i['PRODUTO'] . ' — ' . moeda((float)$i['SUBTOTAL']), $itens);
$mensagemWhats = "Olá, $primeiroNome! "
    . ($ehOrcamento ? "Segue o seu orçamento #$id:" : "Aqui está o resumo do seu pedido #$id:") . "\n\n"
    . implode("\n", $linhasItens)
    . ((float)$pedido['DESCONTO'] > 0 ? "\nDesconto: − " . moeda((float)$pedido['DESCONTO']) : '')
    . "\n*Total: " . moeda((float)$pedido['TOTAL']) . '*'
    . ($ehOrcamento ? "\n\nQualquer dúvida, é só responder esta mensagem." : "\nStatus: " . status_pedidos()[$pedido['STATUS']]);
$linkWhats = whatsapp_link($pedido['CLIENTE_TELEFONE'], $mensagemWhats);

// Opções do seletor de status: "Orçamento" só aparece enquanto o pedido ainda é um orçamento
$opcoesStatus = status_pedidos();
if (!$ehOrcamento) {
    unset($opcoesStatus['ORCAMENTO']);
}
$podeEditar = in_array($pedido['STATUS'], ['ORCAMENTO', 'ABERTO'], true) && !$algumaRecebida; // parcela recebida congela o pedido
$hoje = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomeDoc; ?> #<?php echo $pedido['ID']; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Pedidos' => 'pedidos.php', $nomeDoc . ' #' . $pedido['ID'] => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone($ehOrcamento ? 'documento-texto' : 'carrinho'); ?> <?php echo $nomeDoc; ?> <span class="page-title-id">#<?php echo $pedido['ID']; ?></span> <?php echo badge_status($pedido['STATUS']); ?></h2>
                    <div class="page-head-acoes">
                        <?php if ($linkWhats): ?>
                            <a href="<?php echo htmlspecialchars($linkWhats); ?>" class="btn btn-outline btn-whatsapp" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> WhatsApp</a>
                        <?php endif; ?>
                        <a href="pedido_imprimir.php?id=<?php echo $pedido['ID']; ?>" class="btn btn-outline" target="_blank" rel="noopener"><?php echo icone('impressora'); ?> Imprimir / PDF</a>
                        <?php if ($podeEditar): ?>
                            <a href="pedido_novo.php?id=<?php echo $pedido['ID']; ?>" class="btn btn-outline"><?php echo icone('editar'); ?> Editar</a>
                        <?php endif; ?>
                        <?php if ($ehOrcamento): ?>
                            <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="form-inline">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="status">
                                <input type="hidden" name="status" value="ABERTO">
                                <button type="submit" class="btn btn-primary"><?php echo icone('check'); ?> Aprovar orçamento</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($pedido['ORIGEM'] === 'LOJA' && $pedido['STATUS'] === 'ABERTO'): ?>
                    <div class="alert alert-info">
                        <?php echo icone('loja'); ?>
                        <span>Pedido feito pelo cliente na <strong>loja online</strong>. O estoque já foi reservado.
                            Confirme com o cliente o pagamento e a entrega e depois atualize o status.</span>
                    </div>
                <?php endif; ?>

                <?php if ($ehOrcamento): ?>
                    <div class="alert alert-info">
                        <?php echo icone('documento-texto'); ?>
                        <span>Este é um <strong>orçamento</strong>: não reserva estoque e não conta como venda.
                            Ao aprovar, ele vira um pedido "Aberto" e o estoque é baixado.</span>
                    </div>
                <?php endif; ?>

                <dl class="detalhes">
                    <div>
                        <dt>Cliente <small class="text-muted">(tabela clientes)</small></dt>
                        <dd>
                            <a href="cliente_ver.php?id=<?php echo $pedido['CLIENTE_ID']; ?>" class="text-link"><?php echo htmlspecialchars($pedido['CLIENTE']); ?></a><br>
                            <span class="text-muted"><?php echo htmlspecialchars($pedido['CLIENTE_EMAIL']); ?><?php echo $pedido['CLIENTE_TELEFONE'] ? ' · ' . htmlspecialchars($pedido['CLIENTE_TELEFONE']) : ''; ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt>Registrado por <small class="text-muted">(tabela usuarios)</small></dt>
                        <dd><?php if ($pedido['ORIGEM'] === 'LOJA'): ?>
                            <span class="badge badge-loja"><?php echo icone('loja'); ?> Loja online</span> <span class="text-muted">feito pelo cliente</span>
                        <?php else: ?>
                            <?php echo $pedido['USUARIO'] ? htmlspecialchars($pedido['USUARIO']) : '<span class="text-muted">— (usuário removido)</span>'; ?>
                        <?php endif; ?></dd>
                    </div>
                    <div>
                        <dt>Data</dt>
                        <dd><?php echo date('d/m/Y H:i', strtotime($pedido['CRIADO_EM'])); ?></dd>
                    </div>
                    <div>
                        <dt>Pagamento</dt>
                        <dd><?php echo $pedido['FORMA_PAGAMENTO'] ? formas_pagamento()[$pedido['FORMA_PAGAMENTO']] : '<span class="text-muted">Não informado</span>'; ?></dd>
                    </div>
                    <div>
                        <dt>Observação</dt>
                        <dd><?php echo $pedido['OBSERVACAO'] ? htmlspecialchars($pedido['OBSERVACAO']) : '<span class="text-muted">—</span>'; ?></dd>
                    </div>
                    <?php if ($pedido['ENTREGA']): ?>
                        <div class="detalhes-largo">
                            <dt>Entrega</dt>
                            <dd><?php echo htmlspecialchars($pedido['ENTREGA']); ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <h3 class="section-title"><?php echo icone('pacote'); ?> Itens <small class="text-muted">(tabelas itens_pedido + produtos)</small></h3>
                <div class="table-responsive">
                    <table class="client-table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th class="num">Quantidade</th>
                                <th class="num">Preço unitário</th>
                                <th class="num">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($itens as $item): ?>
                                <tr>
                                    <td class="cell-client">
                                        <span class="client-name"><?php echo htmlspecialchars($item['PRODUTO']); ?></span>
                                        <span class="client-id">#<?php echo $item['PRODUTO_ID']; ?><?php echo $item['SKU'] ? ' · SKU ' . htmlspecialchars($item['SKU']) : ''; ?></span>
                                    </td>
                                    <td class="num" data-label="Quantidade"><?php echo (int)$item['QUANTIDADE']; ?></td>
                                    <td class="num" data-label="Preço unitário"><?php echo moeda((float)$item['PRECO_UNITARIO']); ?></td>
                                    <td class="num" data-label="Subtotal"><?php echo moeda((float)$item['SUBTOTAL']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$itens): ?>
                                <tr><td colspan="4" class="text-muted">Este pedido não tem itens.</td></tr>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <?php if ((float)$pedido['DESCONTO'] > 0): ?>
                                <tr class="tfoot-secundario">
                                    <th colspan="3" class="num">Subtotal</th>
                                    <th class="num"><?php echo moeda((float)$pedido['SUBTOTAL']); ?></th>
                                </tr>
                                <tr class="tfoot-secundario">
                                    <th colspan="3" class="num">Desconto</th>
                                    <th class="num">− <?php echo moeda((float)$pedido['DESCONTO']); ?></th>
                                </tr>
                            <?php endif; ?>
                            <tr>
                                <th colspan="3" class="num">Total</th>
                                <th class="num total-valor"><?php echo moeda((float)$pedido['TOTAL']); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (status_eh_venda($pedido['STATUS']) || $parcelas): ?>
                    <section class="parcelas" aria-labelledby="tituloParcelas">
                        <div class="list-header">
                            <h3 class="section-title" id="tituloParcelas"><?php echo icone('dinheiro'); ?> Pagamento e parcelas <small class="text-muted">(tabela parcelas)</small></h3>
                            <?php if ($parcelas): ?>
                                <p class="parcelas-resumo">
                                    <span>Recebido <strong><?php echo moeda($recebido); ?></strong></span>
                                    <span>A receber <strong><?php echo moeda($aReceber); ?></strong></span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <?php if (!$parcelas): ?>
                            <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="parcelas-gerar">
                                <?php echo csrf_campo(); ?>
                                <input type="hidden" name="acao" value="gerar_parcelas">
                                <p class="text-muted">Defina como o cliente vai pagar os <?php echo moeda((float)$pedido['TOTAL']); ?>. As parcelas aparecem em <a href="contas_receber.php" class="text-link">Contas a receber</a>.</p>
                                <div class="parcelas-campos">
                                    <div class="form-group">
                                        <label for="quantidadeParcelas">Parcelas</label>
                                        <select id="quantidadeParcelas" name="quantidade">
                                            <?php for ($n = 1; $n <= PARCELAS_MAXIMO; $n++): ?>
                                                <option value="<?php echo $n; ?>"><?php echo $n === 1 ? 'À vista (1x)' : $n . 'x de ' . moeda((float)$pedido['TOTAL'] / $n); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="primeiroVencimento">1º vencimento</label>
                                        <input type="date" id="primeiroVencimento" name="primeiro_vencimento" value="<?php echo $hoje; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="intervaloParcelas">Intervalo</label>
                                        <select id="intervaloParcelas" name="intervalo">
                                            <option value="30">Mensal (30 dias)</option>
                                            <option value="15">Quinzenal (15 dias)</option>
                                            <option value="7">Semanal (7 dias)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="formaParcelas">Forma</label>
                                        <select id="formaParcelas" name="forma_pagamento">
                                            <option value="">Não informada</option>
                                            <?php foreach (formas_pagamento() as $valor => $rotulo): ?>
                                                <option value="<?php echo $valor; ?>" <?php echo $valor === $pedido['FORMA_PAGAMENTO'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary"><?php echo icone('check'); ?> Gerar parcelas</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="client-table tabela-parcelas">
                                    <thead>
                                        <tr>
                                            <th>Parcela</th>
                                            <th>Vencimento</th>
                                            <th>Situação</th>
                                            <th class="num">Valor</th>
                                            <th class="col-actions"><span class="visually-hidden">Ações</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($parcelas as $parc): ?>
                                            <?php $situacao = situacao_parcela($parc); ?>
                                            <tr>
                                                <td class="cell-client">
                                                    <span class="client-name"><?php echo (int)$parc['NUMERO']; ?>/<?php echo count($parcelas); ?></span>
                                                    <span class="client-id"><?php echo $parc['FORMA_PAGAMENTO'] ? formas_pagamento()[$parc['FORMA_PAGAMENTO']] : 'Forma não informada'; ?></span>
                                                </td>
                                                <td data-label="Vencimento"><?php echo date('d/m/Y', strtotime($parc['VENCIMENTO'])); ?></td>
                                                <td data-label="Situação">
                                                    <?php echo badge_parcela($situacao); ?>
                                                    <?php if ($parc['PAGO_EM']): ?><small class="text-muted">em <?php echo date('d/m/Y', strtotime($parc['PAGO_EM'])); ?></small><?php endif; ?>
                                                </td>
                                                <td class="num" data-label="Valor"><?php echo moeda((float)$parc['VALOR']); ?></td>
                                                <td class="col-actions">
                                                    <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="parcela-acao">
                                                        <?php echo csrf_campo(); ?>
                                                        <input type="hidden" name="parcela_id" value="<?php echo $parc['ID']; ?>">
                                                        <?php if ($parc['PAGO_EM']): ?>
                                                            <input type="hidden" name="acao" value="estornar">
                                                            <button type="submit" class="btn btn-outline btn-sm"><?php echo icone('restaurar'); ?> Estornar</button>
                                                        <?php else: ?>
                                                            <input type="hidden" name="acao" value="receber">
                                                            <label class="visually-hidden" for="pagoEm<?php echo $parc['ID']; ?>">Data do recebimento</label>
                                                            <input type="date" id="pagoEm<?php echo $parc['ID']; ?>" name="pago_em" value="<?php echo $hoje; ?>" max="<?php echo $hoje; ?>" class="input-sm">
                                                            <button type="submit" class="btn btn-primary btn-sm"><?php echo icone('check'); ?> Receber</button>
                                                        <?php endif; ?>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!$algumaRecebida): ?>
                                <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="parcelas-refazer">
                                    <?php echo csrf_campo(); ?>
                                    <input type="hidden" name="acao" value="refazer">
                                    <button type="submit" class="btn-limpar"><?php echo icone('restaurar'); ?> Refazer parcelas</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <form method="POST" action="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="status-form">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="status">
                    <label for="status">Alterar status:</label>
                    <select id="status" name="status">
                        <?php foreach ($opcoesStatus as $valor => $rotulo): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === $pedido['STATUS'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><?php echo icone('salvar'); ?> Salvar</button>
                </form>

                <div class="form-actions">
                    <a href="pedidos.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Pedidos</a>
                    <?php if (eh_admin()): ?>
                        <a href="pedido_apagar.php?id=<?php echo $pedido['ID']; ?>" class="btn btn-danger-outline" data-lixeira
                           data-confirmar-exclusao="o <?php echo mb_strtolower($nomeDoc); ?> #<?php echo $pedido['ID']; ?>"><?php echo icone('lixeira'); ?> Apagar <?php echo $nomeDoc; ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
