<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'financeiro.php';

// Comprovante do pedido (ou do orçamento) para imprimir ou salvar em PDF ("Salvar como PDF"
// na janela de impressão do navegador — não precisa de biblioteca extra).
// Se o pedido tiver parcelas, elas aparecem no comprovante.
//   pedido_imprimir.php?id=ID

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare(
    'SELECT p.ID, p.STATUS, p.OBSERVACAO, p.CRIADO_EM, p.DESCONTO, p.FORMA_PAGAMENTO, p.SUBTOTAL, p.TOTAL,
            c.NOME, c.EMAIL, c.TELEFONE, c.CPF_CNPJ, c.CEP, c.LOGRADOURO, c.NUMERO, c.COMPLEMENTO, c.BAIRRO, c.CIDADE, c.UF,
            u.NOME AS USUARIO
       FROM vw_pedidos p
      INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
       LEFT JOIN usuarios u ON u.ID = p.USUARIO_ID
      WHERE p.ID = ?'
);
$stmt->execute([$id]);
$pedido = $stmt->fetch();

if (!$pedido) {
    flash('error', 'Pedido não encontrado.');
    header('Location: pedidos.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT pr.NOME, i.QUANTIDADE, i.PRECO_UNITARIO, i.QUANTIDADE * i.PRECO_UNITARIO AS SUBTOTAL
       FROM itens_pedido i
      INNER JOIN produtos pr ON pr.ID = i.PRODUTO_ID
      WHERE i.PEDIDO_ID = ?
      ORDER BY pr.NOME'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();
$endereco = endereco_cliente($pedido);
$parcelas = parcelas_do_pedido($pdo, $id);
$ehOrcamento = $pedido['STATUS'] === 'ORCAMENTO';
$nomeDoc = $ehOrcamento ? 'Orçamento' : 'Pedido';
$validadeOrcamento = date('d/m/Y', strtotime($pedido['CRIADO_EM'] . ' +7 days'));

$primeiroNome = explode(' ', trim($pedido['NOME']))[0];
$whatsapp = whatsapp_link($pedido['TELEFONE'],
    "Olá, $primeiroNome! " . ($ehOrcamento ? "Segue o orçamento #$id" : "Segue o comprovante do pedido #$id")
    . ', no valor de ' . moeda((float)$pedido['TOTAL']) . '. O PDF vai em anexo.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nomeDoc; ?> #<?php echo $pedido['ID']; ?> - Comprovante</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="impressao-body">
    <div class="impressao-barra">
        <a href="pedido_ver.php?id=<?php echo $pedido['ID']; ?>" class="btn btn-outline btn-sm"><?php echo icone('fechar'); ?> Voltar</a>
        <span class="text-muted">Dica: na janela de impressão, escolha “Salvar como PDF” para gerar o arquivo.</span>
        <?php if ($whatsapp): ?>
            <a href="<?php echo htmlspecialchars($whatsapp); ?>" class="btn btn-outline btn-sm btn-whatsapp" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Enviar por WhatsApp</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><?php echo icone('impressora'); ?> Imprimir / Salvar PDF</button>
    </div>

    <article class="comprovante">
        <header class="comprovante-topo">
            <div class="comprovante-marca">
                <span class="brand-mark">CRM</span>
                <div>
                    <strong>Gestão de Clientes</strong>
                    <small><?php echo $ehOrcamento ? 'Orçamento' : 'Comprovante de pedido'; ?></small>
                </div>
            </div>
            <div class="comprovante-numero">
                <span><?php echo $nomeDoc; ?></span>
                <strong>#<?php echo str_pad((string)$pedido['ID'], 5, '0', STR_PAD_LEFT); ?></strong>
                <small><?php echo date('d/m/Y \à\s H:i', strtotime($pedido['CRIADO_EM'])); ?></small>
            </div>
        </header>

        <section class="comprovante-blocos">
            <div>
                <h3>Cliente</h3>
                <p><strong><?php echo htmlspecialchars($pedido['NOME']); ?></strong></p>
                <?php if ($pedido['CPF_CNPJ']): ?>
                    <p><?php echo strlen($pedido['CPF_CNPJ']) === 14 ? 'CNPJ' : 'CPF'; ?>: <?php echo formatar_documento($pedido['CPF_CNPJ']); ?></p>
                <?php endif; ?>
                <p><?php echo htmlspecialchars($pedido['EMAIL']); ?><?php echo $pedido['TELEFONE'] ? ' · ' . htmlspecialchars($pedido['TELEFONE']) : ''; ?></p>
                <?php if ($endereco): ?><p><?php echo htmlspecialchars($endereco); ?></p><?php endif; ?>
            </div>
            <div>
                <h3><?php echo $nomeDoc; ?></h3>
                <?php if ($ehOrcamento): ?>
                    <p>Válido até: <strong><?php echo $validadeOrcamento; ?></strong></p>
                <?php else: ?>
                    <p>Status: <strong><?php echo status_pedidos()[$pedido['STATUS']]; ?></strong></p>
                <?php endif; ?>
                <p>Pagamento: <?php echo $pedido['FORMA_PAGAMENTO'] ? formas_pagamento()[$pedido['FORMA_PAGAMENTO']] : 'não informado'; ?></p>
                <p>Atendente: <?php echo $pedido['USUARIO'] ? htmlspecialchars($pedido['USUARIO']) : '—'; ?></p>
            </div>
        </section>

        <table class="comprovante-itens">
            <thead>
                <tr><th>Produto</th><th class="num">Qtd.</th><th class="num">Preço unit.</th><th class="num">Subtotal</th></tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['NOME']); ?></td>
                        <td class="num"><?php echo (int)$item['QUANTIDADE']; ?></td>
                        <td class="num"><?php echo moeda((float)$item['PRECO_UNITARIO']); ?></td>
                        <td class="num"><?php echo moeda((float)$item['SUBTOTAL']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="3" class="num">Subtotal</th><td class="num"><?php echo moeda((float)$pedido['SUBTOTAL']); ?></td></tr>
                <?php if ((float)$pedido['DESCONTO'] > 0): ?>
                    <tr><th colspan="3" class="num">Desconto</th><td class="num">− <?php echo moeda((float)$pedido['DESCONTO']); ?></td></tr>
                <?php endif; ?>
                <tr class="comprovante-total"><th colspan="3" class="num">Total</th><td class="num"><?php echo moeda((float)$pedido['TOTAL']); ?></td></tr>
            </tfoot>
        </table>

        <?php if ($parcelas): ?>
            <table class="comprovante-itens comprovante-parcelas">
                <thead>
                    <tr><th>Parcela</th><th>Vencimento</th><th>Situação</th><th class="num">Valor</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($parcelas as $parc): ?>
                        <tr>
                            <td><?php echo (int)$parc['NUMERO']; ?>/<?php echo count($parcelas); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($parc['VENCIMENTO'])); ?></td>
                            <td><?php echo $parc['PAGO_EM'] ? 'Recebida em ' . date('d/m/Y', strtotime($parc['PAGO_EM'])) : 'A pagar'; ?></td>
                            <td class="num"><?php echo moeda((float)$parc['VALOR']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($pedido['OBSERVACAO']): ?>
            <p class="comprovante-obs"><strong>Observação:</strong> <?php echo htmlspecialchars($pedido['OBSERVACAO']); ?></p>
        <?php endif; ?>

        <footer class="comprovante-rodape">
            Emitido em <?php echo date('d/m/Y H:i'); ?> · Este documento não tem valor fiscal.
            <?php if ($ehOrcamento): ?><br>Orçamento sujeito à disponibilidade de estoque. Preços válidos até <?php echo $validadeOrcamento; ?>.<?php endif; ?>
        </footer>
    </article>
</body>
</html>
