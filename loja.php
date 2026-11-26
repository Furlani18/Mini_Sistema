<?php
declare(strict_types=1);
require_once __DIR__ . '/loja_inicio.php';

// Vitrine da loja online (página principal pública).
//   loja.php                       → destaques, categorias e todos os produtos
//   ?q=texto                       → busca por nome, descrição ou SKU
//   ?categoria=ID                  → só uma categoria
//   ?ordem=menor|maior|nome|novos  → ordenação (padrão: disponíveis primeiro, mais vendidos)
//   ?pagina=N                      → paginação (LOJA_POR_PAGINA por página)
// Só aparecem produtos com "Mostrar na loja" marcado. Esgotados vão para o fim.

$q = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$categoriaId = (int)($_GET['categoria'] ?? 0);
$ordens = [
    'relevancia' => ['Mais vendidos', 'VENDIDOS DESC, p.NOME'],
    'menor'      => ['Menor preço', 'p.PRECO ASC, p.NOME'],
    'maior'      => ['Maior preço', 'p.PRECO DESC, p.NOME'],
    'nome'       => ['Nome (A–Z)', 'p.NOME'],
    'novos'      => ['Novidades', 'p.ID DESC'],
];
$ordem = is_string($_GET['ordem'] ?? null) && isset($ordens[$_GET['ordem']]) ? $_GET['ordem'] : 'relevancia';

// Categorias com pelo menos um produto visível
$categorias = $pdo->query(
    'SELECT c.ID, c.NOME, COUNT(p.ID) AS QTD
       FROM categorias c INNER JOIN produtos p ON p.CATEGORIA_ID = c.ID AND p.NA_LOJA = 1
      GROUP BY c.ID, c.NOME ORDER BY c.NOME'
)->fetchAll();
$nomeCategoria = null;
foreach ($categorias as $c) {
    if ((int)$c['ID'] === $categoriaId) {
        $nomeCategoria = $c['NOME'];
    }
}
if ($nomeCategoria === null) {
    $categoriaId = 0;
}

$condicoes = ['p.NA_LOJA = 1'];
$parametros = [];
if ($q !== '') {
    $condicoes[] = '(p.NOME LIKE ? OR p.DESCRICAO LIKE ? OR p.SKU = ?)';
    $termo = '%' . addcslashes($q, '%_\\') . '%';
    array_push($parametros, $termo, $termo, $q);
}
if ($categoriaId > 0) {
    $condicoes[] = 'p.CATEGORIA_ID = ?';
    $parametros[] = $categoriaId;
}
$where = 'WHERE ' . implode(' AND ', $condicoes);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM produtos p $where");
$stmt->execute($parametros);
$total = (int)$stmt->fetchColumn();
$totalPaginas = max(1, (int)ceil($total / LOJA_POR_PAGINA));
$pagina = min(max(1, (int)($_GET['pagina'] ?? 1)), $totalPaginas);
$offset = ($pagina - 1) * LOJA_POR_PAGINA;

// Unidades vendidas (pedidos de venda) para "Mais vendidos"
$vendidos = "(SELECT COALESCE(SUM(i.QUANTIDADE), 0) FROM itens_pedido i INNER JOIN pedidos pe ON pe.ID = i.PEDIDO_ID
              WHERE i.PRODUTO_ID = p.ID AND pe.STATUS IN ('" . implode("', '", STATUS_VENDA) . "'))";
$colunas = "p.ID, p.NOME, p.PRECO, p.ESTOQUE, p.FOTO, p.DESTAQUE, p.CATEGORIA_ID, c.NOME AS CATEGORIA, $vendidos AS VENDIDOS";

$stmt = $pdo->prepare(
    "SELECT $colunas
       FROM produtos p LEFT JOIN categorias c ON c.ID = p.CATEGORIA_ID
     $where
      ORDER BY (p.ESTOQUE > 0) DESC, {$ordens[$ordem][1]}
      LIMIT " . LOJA_POR_PAGINA . " OFFSET $offset"
);
$stmt->execute($parametros);
$produtos = $stmt->fetchAll();

// Página inicial "limpa" (sem busca/filtro): mostra a vitrine de destaques
$inicio = $q === '' && $categoriaId === 0 && $pagina === 1 && $ordem === 'relevancia';
$destaques = [];
if ($inicio) {
    $destaques = $pdo->query(
        "SELECT $colunas FROM produtos p LEFT JOIN categorias c ON c.ID = p.CATEGORIA_ID
          WHERE p.NA_LOJA = 1 AND p.DESTAQUE = 1 AND p.ESTOQUE > 0
          ORDER BY p.ID DESC LIMIT 4"
    )->fetchAll();
}

/** Link da vitrine mantendo os filtros atuais. */
$link = function (array $mudancas) use ($q, $categoriaId, $ordem, $pagina): string {
    $params = array_merge(
        ['q' => $q, 'categoria' => $categoriaId ?: null, 'ordem' => $ordem === 'relevancia' ? null : $ordem, 'pagina' => $pagina > 1 ? $pagina : null],
        $mudancas
    );
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    return 'loja.php' . ($params ? '?' . http_build_query($params) : '');
};
$voltar = $link([]);

if ($q !== '') {
    $tituloLista = 'Resultados para "' . $q . '"';
} elseif ($nomeCategoria !== null) {
    $tituloLista = $nomeCategoria;
} else {
    $tituloLista = $inicio && $destaques ? 'Todos os produtos' : 'Produtos';
}

loja_cabecalho($loja, $q !== '' ? "Busca: $q" : ($nomeCategoria ?? ''));
?>
    <main class="loja-main" id="conteudo">
        <?php if ($inicio): ?>
            <section class="loja-hero">
                <div class="loja-hero-texto">
                    <p class="loja-hero-sobre">Bem-vindo à</p>
                    <h1><?php echo htmlspecialchars($loja['nome']); ?></h1>
                    <?php if ($loja['slogan']): ?><p class="loja-hero-slogan"><?php echo htmlspecialchars($loja['slogan']); ?></p><?php endif; ?>
                    <div class="loja-hero-acoes">
                        <a href="#produtos" class="btn btn-primary btn-lg"><?php echo icone('pacote'); ?> Ver produtos</a>
                        <?php if ($zap = whatsapp_link($loja['whatsapp'], 'Olá! Vim pela loja online e tenho uma dúvida.')): ?>
                            <a href="<?php echo htmlspecialchars($zap); ?>" class="btn btn-hero-outline btn-lg" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Fale com a gente</a>
                        <?php endif; ?>
                    </div>
                </div>
                <ul class="loja-hero-vantagens">
                    <li><?php echo icone('caminhao'); ?> <span><strong>Entrega ou retirada</strong> Você escolhe ao finalizar</span></li>
                    <li><?php echo icone('cartao'); ?> <span><strong>Pague como preferir</strong> Pix, cartão, boleto ou dinheiro</span></li>
                    <li><?php echo icone('escudo'); ?> <span><strong>Compra segura</strong> A loja confirma tudo com você</span></li>
                </ul>
            </section>

            <?php if ($destaques): ?>
                <section class="loja-secao" aria-labelledby="tituloDestaques">
                    <h2 class="loja-secao-titulo" id="tituloDestaques"><?php echo icone('estrela'); ?> Destaques</h2>
                    <div class="loja-grade">
                        <?php foreach ($destaques as $p) { echo loja_card_produto($p, $voltar); } ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <section class="loja-secao" id="produtos" aria-labelledby="tituloProdutos">
            <div class="loja-lista-topo">
                <div>
                    <h2 class="loja-secao-titulo" id="tituloProdutos"><?php echo htmlspecialchars($tituloLista); ?></h2>
                    <p class="text-muted"><?php echo $total === 1 ? '1 produto' : "$total produtos"; ?></p>
                </div>
                <form method="get" class="loja-ordenar">
                    <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>"><?php endif; ?>
                    <?php if ($categoriaId): ?><input type="hidden" name="categoria" value="<?php echo $categoriaId; ?>"><?php endif; ?>
                    <label for="ordem">Ordenar por</label>
                    <select id="ordem" name="ordem" data-auto-submit>
                        <?php foreach ($ordens as $valor => [$rotulo]): ?>
                            <option value="<?php echo $valor; ?>"<?php echo $valor === $ordem ? ' selected' : ''; ?>><?php echo $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <noscript><button type="submit" class="btn btn-outline btn-sm">OK</button></noscript>
                </form>
            </div>

            <?php if ($categorias): ?>
                <nav class="loja-categorias" aria-label="Categorias">
                    <a href="<?php echo $link(['categoria' => null, 'pagina' => null]); ?>" class="loja-chip<?php echo $categoriaId === 0 ? ' is-ativo' : ''; ?>"
                       <?php echo $categoriaId === 0 ? 'aria-current="page"' : ''; ?>>Todas</a>
                    <?php foreach ($categorias as $c): ?>
                        <?php $ativa = (int)$c['ID'] === $categoriaId; ?>
                        <a href="<?php echo $link(['categoria' => (int)$c['ID'], 'pagina' => null]); ?>" class="loja-chip<?php echo $ativa ? ' is-ativo' : ''; ?>"
                           <?php echo $ativa ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($c['NOME']); ?> <span><?php echo (int)$c['QTD']; ?></span></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if (!$produtos): ?>
                <div class="loja-vazio">
                    <?php echo icone('busca'); ?>
                    <h3><?php echo $q !== '' || $categoriaId ? 'Nenhum produto encontrado' : 'Em breve, novidades por aqui'; ?></h3>
                    <p><?php echo $q !== '' ? 'Tente outra palavra ou veja todos os produtos.' : 'Ainda não há produtos à venda.'; ?></p>
                    <?php if ($q !== '' || $categoriaId): ?><a href="loja.php" class="btn btn-outline">Ver todos os produtos</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="loja-grade">
                    <?php foreach ($produtos as $p) { echo loja_card_produto($p, $voltar); } ?>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav class="loja-paginacao" aria-label="Páginas">
                        <?php if ($pagina > 1): ?><a href="<?php echo $link(['pagina' => $pagina - 1]); ?>#produtos" class="pag-btn" aria-label="Página anterior">‹</a><?php endif; ?>
                        <?php for ($n = 1; $n <= $totalPaginas; $n++): ?>
                            <?php if ($n === $pagina): ?>
                                <span class="pag-btn is-atual" aria-current="page"><?php echo $n; ?></span>
                            <?php elseif ($n === 1 || $n === $totalPaginas || abs($n - $pagina) <= 1): ?>
                                <a href="<?php echo $link(['pagina' => $n]); ?>#produtos" class="pag-btn" aria-label="Página <?php echo $n; ?>"><?php echo $n; ?></a>
                            <?php elseif (abs($n - $pagina) === 2): ?>
                                <span class="pag-reticencias" aria-hidden="true">…</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($pagina < $totalPaginas): ?><a href="<?php echo $link(['pagina' => $pagina + 1]); ?>#produtos" class="pag-btn" aria-label="Próxima página">›</a><?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>
<?php loja_rodape($loja); ?>
