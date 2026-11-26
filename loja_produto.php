<?php
declare(strict_types=1);
require_once __DIR__ . '/loja_inicio.php';

// Página de um produto na loja online: foto, preço, descrição, disponibilidade,
// quantidade + "Adicionar ao carrinho" / "Comprar agora" e produtos da mesma categoria.

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT p.ID, p.NOME, p.DESCRICAO, p.PRECO, p.ESTOQUE, p.FOTO, p.SKU, p.DESTAQUE, p.CATEGORIA_ID, c.NOME AS CATEGORIA
       FROM produtos p LEFT JOIN categorias c ON c.ID = p.CATEGORIA_ID
      WHERE p.ID = ? AND p.NA_LOJA = 1'
);
$stmt->execute([$id]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    loja_cabecalho($loja, 'Produto não encontrado');
    ?>
    <main class="loja-main" id="conteudo">
        <section class="loja-vazio">
            <?php echo icone('pacote'); ?>
            <h1>Produto não encontrado</h1>
            <p>Ele pode ter saído da loja. Veja os outros produtos disponíveis.</p>
            <a href="loja.php" class="btn btn-primary">Ir para a loja</a>
        </section>
    </main>
    <?php
    loja_rodape($loja);
    exit;
}

$estoque = (int)$produto['ESTOQUE'];
$noCarrinho = carrinho_ler()[$id] ?? 0;
$maximo = max(0, min($estoque, LOJA_QTD_MAXIMA) - $noCarrinho);

$relacionados = [];
if ($produto['CATEGORIA_ID']) {
    $stmt = $pdo->prepare(
        'SELECT p.ID, p.NOME, p.PRECO, p.ESTOQUE, p.FOTO, p.DESTAQUE, p.CATEGORIA_ID, c.NOME AS CATEGORIA
           FROM produtos p LEFT JOIN categorias c ON c.ID = p.CATEGORIA_ID
          WHERE p.NA_LOJA = 1 AND p.CATEGORIA_ID = ? AND p.ID <> ?
          ORDER BY (p.ESTOQUE > 0) DESC, p.ID DESC LIMIT 4'
    );
    $stmt->execute([$produto['CATEGORIA_ID'], $id]);
    $relacionados = $stmt->fetchAll();
}

$voltar = 'loja_produto.php?id=' . $id;
$zap = whatsapp_link($loja['whatsapp'], "Olá! Tenho uma dúvida sobre o produto \"{$produto['NOME']}\".");

loja_cabecalho($loja, $produto['NOME'], true, mb_substr((string)$produto['DESCRICAO'], 0, 160));
?>
    <main class="loja-main" id="conteudo">
        <nav class="loja-trilha" aria-label="Você está em">
            <a href="loja.php">Início</a>
            <?php if ($produto['CATEGORIA']): ?>
                <span aria-hidden="true">›</span>
                <a href="loja.php?categoria=<?php echo (int)$produto['CATEGORIA_ID']; ?>"><?php echo htmlspecialchars($produto['CATEGORIA']); ?></a>
            <?php endif; ?>
            <span aria-hidden="true">›</span>
            <span aria-current="page"><?php echo htmlspecialchars($produto['NOME']); ?></span>
        </nav>

        <article class="loja-produto">
            <div class="loja-produto-foto"><?php echo foto_produto($produto, 'foto-grande'); ?></div>

            <div class="loja-produto-info">
                <?php if ($produto['CATEGORIA']): ?><span class="loja-card-categoria"><?php echo htmlspecialchars($produto['CATEGORIA']); ?></span><?php endif; ?>
                <h1><?php echo htmlspecialchars($produto['NOME']); ?></h1>
                <?php if ($produto['SKU']): ?><p class="loja-produto-sku">Código: <?php echo htmlspecialchars($produto['SKU']); ?></p><?php endif; ?>

                <p class="loja-produto-preco"><?php echo moeda((float)$produto['PRECO']); ?></p>

                <p class="loja-disponibilidade <?php echo $estoque <= 0 ? 'is-esgotado' : ($estoque <= ESTOQUE_BAIXO ? 'is-ultimas' : 'is-disponivel'); ?>">
                    <?php if ($estoque <= 0): ?>
                        <?php echo icone('erro'); ?> Esgotado no momento
                    <?php elseif ($estoque <= ESTOQUE_BAIXO): ?>
                        <?php echo icone('alerta'); ?> Últimas <?php echo $estoque; ?> unidade<?php echo $estoque === 1 ? '' : 's'; ?>
                    <?php else: ?>
                        <?php echo icone('sucesso'); ?> Em estoque
                    <?php endif; ?>
                </p>

                <?php if ($estoque > 0 && $maximo > 0): ?>
                    <form method="POST" action="loja_carrinho.php" class="loja-comprar">
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="adicionar">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="voltar" value="<?php echo $voltar; ?>">
                        <div class="loja-qtd" data-qtd>
                            <label for="qtd" class="visually-hidden">Quantidade</label>
                            <button type="button" class="loja-qtd-btn" data-qtd-menos aria-label="Diminuir quantidade"><?php echo icone('menos'); ?></button>
                            <input type="number" id="qtd" name="qtd" value="1" min="1" max="<?php echo $maximo; ?>" step="1" inputmode="numeric">
                            <button type="button" class="loja-qtd-btn" data-qtd-mais aria-label="Aumentar quantidade"><?php echo icone('plus'); ?></button>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg"><?php echo icone('carrinho'); ?> Adicionar ao carrinho</button>
                        <button type="submit" name="comprar" value="1" class="btn btn-outline btn-lg">Comprar agora</button>
                    </form>
                    <?php if ($noCarrinho > 0): ?>
                        <p class="text-muted"><?php echo icone('check'); ?> Você já tem <?php echo $noCarrinho; ?> no <a href="loja_carrinho.php" class="text-link">carrinho</a>.</p>
                    <?php endif; ?>
                <?php elseif ($estoque > 0): ?>
                    <p class="text-muted">Você já colocou no <a href="loja_carrinho.php" class="text-link">carrinho</a> todas as unidades disponíveis.</p>
                <?php endif; ?>

                <?php if ($zap): ?>
                    <a href="<?php echo htmlspecialchars($zap); ?>" class="loja-duvida" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Ficou com dúvida? Pergunte pelo WhatsApp</a>
                <?php endif; ?>

                <?php if ($produto['DESCRICAO']): ?>
                    <section class="loja-descricao">
                        <h2>Descrição</h2>
                        <p><?php echo nl2br(htmlspecialchars($produto['DESCRICAO'])); ?></p>
                    </section>
                <?php endif; ?>
            </div>
        </article>

        <?php if ($relacionados): ?>
            <section class="loja-secao" aria-labelledby="tituloRelacionados">
                <h2 class="loja-secao-titulo" id="tituloRelacionados">Você também pode gostar</h2>
                <div class="loja-grade">
                    <?php foreach ($relacionados as $p) { echo loja_card_produto($p, $voltar); } ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
<?php loja_rodape($loja); ?>
