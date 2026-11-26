<?php
declare(strict_types=1);
require_once __DIR__ . '/loja_inicio.php';

// Confirmação do pedido feito na loja online. Só abre pedidos feitos NESTE navegador
// (os números ficam na sessão), para ninguém ver o pedido dos outros trocando o número.

$id = (int)($_GET['id'] ?? 0);
$meus = array_map('intval', (array)($_SESSION['loja_pedidos'] ?? []));

$pedido = null;
if (in_array($id, $meus, true)) {
    $stmt = $pdo->prepare(
        "SELECT p.ID, p.STATUS, p.CRIADO_EM, p.FORMA_PAGAMENTO, p.ENTREGA, p.OBSERVACAO, v.TOTAL, c.NOME, c.EMAIL
           FROM pedidos p INNER JOIN vw_pedidos v ON v.ID = p.ID INNER JOIN clientes c ON c.ID = p.CLIENTE_ID
          WHERE p.ID = ? AND p.ORIGEM = 'LOJA'"
    );
    $stmt->execute([$id]);
    $pedido = $stmt->fetch() ?: null;
}

if (!$pedido) {
    http_response_code(404);
    loja_cabecalho($loja, 'Pedido não encontrado');
    ?>
    <main class="loja-main" id="conteudo">
        <section class="loja-vazio">
            <?php echo icone('nota'); ?>
            <h1>Pedido não encontrado</h1>
            <p>Por segurança, o resumo só aparece no navegador em que o pedido foi feito.<?php echo whatsapp_link($loja['whatsapp']) ? ' Para saber dele, fale com a loja.' : ''; ?></p>
            <a href="loja.php" class="btn btn-primary">Voltar para a loja</a>
        </section>
    </main>
    <?php
    loja_rodape($loja);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT i.QUANTIDADE, i.PRECO_UNITARIO, p.ID, p.NOME, p.FOTO, p.CATEGORIA_ID
       FROM itens_pedido i INNER JOIN produtos p ON p.ID = i.PRODUTO_ID
      WHERE i.PEDIDO_ID = ? ORDER BY i.ID'
);
$stmt->execute([$id]);
$itens = $stmt->fetchAll();

$primeiroNome = explode(' ', trim($pedido['NOME']))[0];
$zap = whatsapp_link($loja['whatsapp'], "Olá! Acabei de fazer o pedido #$id na loja online ({$pedido['NOME']}).");
$cancelado = $pedido['STATUS'] === 'CANCELADO';

loja_cabecalho($loja, "Pedido #$id");
?>
    <main class="loja-main" id="conteudo">
        <nav class="loja-passos" aria-label="Etapas da compra">
            <span class="is-feito"><span><?php echo icone('check'); ?></span> Carrinho</span>
            <span class="is-feito"><span><?php echo icone('check'); ?></span> Seus dados</span>
            <span class="is-atual" aria-current="step"><span>3</span> Confirmação</span>
        </nav>

        <section class="loja-confirmacao">
            <div class="loja-confirmacao-icone<?php echo $cancelado ? ' is-cancelado' : ''; ?>"><?php echo icone($cancelado ? 'erro' : 'sucesso'); ?></div>
            <h1><?php echo $cancelado ? "O pedido #$id foi cancelado" : "Pedido #$id recebido!"; ?></h1>
            <p class="loja-confirmacao-texto">
                <?php if ($cancelado): ?>
                    Fale com a loja se tiver alguma dúvida.
                <?php else: ?>
                    Obrigado, <?php echo htmlspecialchars($primeiroNome); ?>! A loja vai conferir o pedido e falar com você
                    para combinar o pagamento<?php echo str_starts_with((string)$pedido['ENTREGA'], 'Retirar') ? ' e a retirada' : ' e a entrega'; ?>.
                <?php endif; ?>
            </p>
            <?php if ($zap && !$cancelado): ?>
                <a href="<?php echo htmlspecialchars($zap); ?>" class="btn btn-whatsapp btn-lg" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Avisar a loja pelo WhatsApp</a>
            <?php endif; ?>
        </section>

        <div class="loja-checkout">
            <section class="loja-bloco loja-bloco-solto" aria-labelledby="tituloItens">
                <h2 id="tituloItens">Itens</h2>
                <ul class="loja-resumo-itens">
                    <?php foreach ($itens as $item): ?>
                        <li>
                            <?php echo foto_produto($item, 'foto-mini'); ?>
                            <span><?php echo htmlspecialchars($item['NOME']); ?><small><?php echo (int)$item['QUANTIDADE']; ?> × <?php echo moeda((float)$item['PRECO_UNITARIO']); ?></small></span>
                            <strong><?php echo moeda((int)$item['QUANTIDADE'] * (float)$item['PRECO_UNITARIO']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl><div class="loja-resumo-total"><dt>Total</dt><dd><?php echo moeda((float)$pedido['TOTAL']); ?></dd></div></dl>
            </section>

            <aside class="loja-resumo" aria-labelledby="tituloDetalhes">
                <h2 id="tituloDetalhes">Detalhes</h2>
                <dl class="loja-detalhes">
                    <div><dt>Data</dt><dd><?php echo date('d/m/Y \à\s H:i', strtotime($pedido['CRIADO_EM'])); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo htmlspecialchars($pedido['EMAIL']); ?></dd></div>
                    <div><dt>Pagamento</dt><dd><?php echo htmlspecialchars(formas_pagamento()[$pedido['FORMA_PAGAMENTO']] ?? 'A combinar'); ?></dd></div>
                    <div><dt>Entrega</dt><dd><?php echo htmlspecialchars(explode(' · Contato:', (string)$pedido['ENTREGA'])[0]); ?></dd></div>
                    <?php if ($pedido['OBSERVACAO']): ?><div><dt>Observações</dt><dd><?php echo htmlspecialchars($pedido['OBSERVACAO']); ?></dd></div><?php endif; ?>
                </dl>
                <a href="loja.php" class="btn btn-outline btn-bloco"><?php echo icone('pacote'); ?> Continuar comprando</a>
            </aside>
        </div>
    </main>
<?php loja_rodape($loja); ?>
