<?php
declare(strict_types=1);
require_once __DIR__ . '/loja_inicio.php';

// Carrinho da loja online (guardado na sessão do visitante).
//   POST acao=adicionar  id, qtd, voltar[, comprar=1 → vai direto para o carrinho]
//   POST acao=atualizar  qtd[ID] => quantidade (0 remove)
//   POST acao=remover    id
//   POST acao=limpar
// Preços e estoque são sempre os atuais do banco (carrinho_montar).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = (string)($_POST['acao'] ?? '');
    $destino = 'loja_carrinho.php';

    if ($acao === 'adicionar') {
        $id = (int)($_POST['id'] ?? 0);
        $qtd = max(1, min(LOJA_QTD_MAXIMA, (int)($_POST['qtd'] ?? 1)));
        $stmt = $pdo->prepare('SELECT NOME, ESTOQUE FROM produtos WHERE ID = ? AND NA_LOJA = 1');
        $stmt->execute([$id]);
        $produto = $stmt->fetch();
        $voltar = loja_voltar_seguro((string)($_POST['voltar'] ?? ''));

        if (!$produto) {
            flash('error', 'Este produto não está mais disponível.');
            $destino = $voltar;
        } else {
            $jaTem = carrinho_ler()[$id] ?? 0;
            $disponivel = min((int)$produto['ESTOQUE'], LOJA_QTD_MAXIMA) - $jaTem;
            if ($disponivel <= 0) {
                flash('warning', (int)$produto['ESTOQUE'] <= 0
                    ? "\"{$produto['NOME']}\" esgotou."
                    : "Você já tem no carrinho todas as unidades disponíveis de \"{$produto['NOME']}\".");
                $destino = $voltar;
            } elseif (carrinho_adicionar($id, min($qtd, $disponivel)) === 0) {
                flash('warning', 'Seu carrinho chegou ao limite de ' . LOJA_ITENS_MAXIMO . ' produtos diferentes.');
                $destino = 'loja_carrinho.php';
            } else {
                $msg = $qtd > $disponivel
                    ? "Adicionamos só $disponivel unidade(s) de \"{$produto['NOME']}\": é o que temos em estoque."
                    : "\"{$produto['NOME']}\" foi adicionado ao carrinho.";
                flash($qtd > $disponivel ? 'warning' : 'success', $msg);
                $destino = !empty($_POST['comprar']) ? 'loja_carrinho.php' : $voltar;
            }
        }
    } elseif ($acao === 'atualizar') {
        foreach ((array)($_POST['qtd'] ?? []) as $id => $qtd) {
            carrinho_definir((int)$id, (int)$qtd);
        }
        flash('success', 'Carrinho atualizado.');
    } elseif ($acao === 'remover') {
        carrinho_definir((int)($_POST['id'] ?? 0), 0);
        flash('success', 'Produto retirado do carrinho.');
    } elseif ($acao === 'limpar') {
        carrinho_limpar();
        flash('success', 'O carrinho foi esvaziado.');
    }

    header('Location: ' . $destino);
    exit;
}

$carrinho = carrinho_montar($pdo);
loja_cabecalho($loja, 'Carrinho');
?>
    <main class="loja-main" id="conteudo">
        <h1 class="loja-pagina-titulo"><?php echo icone('carrinho'); ?> Seu carrinho</h1>

        <?php foreach ($carrinho['avisos'] as $aviso): ?>
            <div class="alert alert-warning" role="alert"><?php echo icone('alerta'); ?> <span><?php echo htmlspecialchars($aviso); ?></span></div>
        <?php endforeach; ?>

        <?php if (!$carrinho['linhas']): ?>
            <section class="loja-vazio">
                <?php echo icone('carrinho'); ?>
                <h2>Seu carrinho está vazio</h2>
                <p>Que tal dar uma olhada nos nossos produtos?</p>
                <a href="loja.php" class="btn btn-primary"><?php echo icone('pacote'); ?> Ver produtos</a>
            </section>
        <?php else: ?>
            <div class="loja-checkout">
                <form method="POST" action="loja_carrinho.php" class="loja-carrinho" id="formCarrinho">
                    <?php echo csrf_campo(); ?>
                    <input type="hidden" name="acao" value="atualizar">
                    <ul class="loja-carrinho-lista">
                        <?php foreach ($carrinho['linhas'] as $linha): ?>
                            <?php $pid = (int)$linha['ID']; $max = min((int)$linha['ESTOQUE'], LOJA_QTD_MAXIMA); ?>
                            <li class="loja-carrinho-item">
                                <a href="loja_produto.php?id=<?php echo $pid; ?>" class="loja-carrinho-foto" tabindex="-1" aria-hidden="true"><?php echo foto_produto($linha); ?></a>
                                <div class="loja-carrinho-info">
                                    <a href="loja_produto.php?id=<?php echo $pid; ?>" class="loja-carrinho-nome"><?php echo htmlspecialchars($linha['NOME']); ?></a>
                                    <span class="text-muted"><?php echo moeda((float)$linha['PRECO']); ?> cada</span>
                                </div>
                                <div class="loja-qtd loja-qtd-sm" data-qtd data-qtd-enviar>
                                    <label for="qtd<?php echo $pid; ?>" class="visually-hidden">Quantidade de <?php echo htmlspecialchars($linha['NOME']); ?></label>
                                    <button type="button" class="loja-qtd-btn" data-qtd-menos aria-label="Diminuir"><?php echo icone('menos'); ?></button>
                                    <input type="number" id="qtd<?php echo $pid; ?>" name="qtd[<?php echo $pid; ?>]" value="<?php echo (int)$linha['QUANTIDADE']; ?>"
                                           min="0" max="<?php echo $max; ?>" step="1" inputmode="numeric">
                                    <button type="button" class="loja-qtd-btn" data-qtd-mais aria-label="Aumentar"><?php echo icone('plus'); ?></button>
                                </div>
                                <strong class="loja-carrinho-subtotal"><?php echo moeda((float)$linha['SUBTOTAL']); ?></strong>
                                <button type="submit" form="remover<?php echo $pid; ?>" class="btn-icon btn-icon-danger" title="Remover"
                                        aria-label="Remover <?php echo htmlspecialchars($linha['NOME']); ?>"><?php echo icone('lixeira'); ?></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="loja-carrinho-rodape">
                        <a href="loja.php" class="text-link">‹ Continuar comprando</a>
                        <div class="loja-carrinho-botoes">
                            <button type="submit" form="limparCarrinho" class="btn btn-ghost btn-sm">Esvaziar carrinho</button>
                            <button type="submit" class="btn btn-outline btn-sm" data-atualizar-carrinho><?php echo icone('restaurar'); ?> Atualizar</button>
                        </div>
                    </div>
                </form>

                <?php // Formulários separados (um <form> não pode ficar dentro de outro) ?>
                <?php foreach ($carrinho['linhas'] as $linha): ?>
                    <form method="POST" action="loja_carrinho.php" id="remover<?php echo (int)$linha['ID']; ?>" hidden>
                        <?php echo csrf_campo(); ?><input type="hidden" name="acao" value="remover"><input type="hidden" name="id" value="<?php echo (int)$linha['ID']; ?>">
                    </form>
                <?php endforeach; ?>
                <form method="POST" action="loja_carrinho.php" id="limparCarrinho" hidden>
                    <?php echo csrf_campo(); ?><input type="hidden" name="acao" value="limpar">
                </form>

                <aside class="loja-resumo" aria-labelledby="tituloResumo">
                    <h2 id="tituloResumo">Resumo</h2>
                    <dl>
                        <div><dt><?php echo $carrinho['unidades']; ?> <?php echo $carrinho['unidades'] === 1 ? 'item' : 'itens'; ?></dt><dd><?php echo moeda($carrinho['total']); ?></dd></div>
                        <div><dt>Entrega</dt><dd class="text-muted">combinada com a loja</dd></div>
                        <div class="loja-resumo-total"><dt>Total</dt><dd><?php echo moeda($carrinho['total']); ?></dd></div>
                    </dl>
                    <a href="loja_finalizar.php" class="btn btn-primary btn-lg btn-bloco">Finalizar pedido</a>
                    <p class="loja-resumo-nota"><?php echo icone('escudo'); ?> Você não paga nada agora: a loja confirma o pedido e combina o pagamento com você.</p>
                </aside>
            </div>
        <?php endif; ?>
    </main>
<?php loja_rodape($loja); ?>
