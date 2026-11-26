<?php
declare(strict_types=1);

// Topo e rodapé das páginas da loja online.

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/loja_funcoes.php';

/** Abre a página: <head>, faixa da equipe (se logada e loja fechada) e topo com busca e carrinho. */
function loja_cabecalho(array $loja, string $titulo, bool $comBusca = true, string $descricao = ''): void
{
    $unidades = carrinho_unidades();
    $busca = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $logado = isset($_SESSION['usuario_id']);
    $tituloPagina = $titulo === '' ? $loja['nome'] : $titulo . ' · ' . $loja['nome'];
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($tituloPagina); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($descricao ?: ($loja['slogan'] ?: $loja['nome'])); ?>">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/loja.css">
    <script>
        (function () {
            var tema = localStorage.getItem('tema');
            if (!tema && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) tema = 'escuro';
            if (tema === 'escuro') document.documentElement.setAttribute('data-tema', 'escuro');
        })();
    </script>
</head>
<body class="loja">
    <a href="#conteudo" class="pular-conteudo">Pular para o conteúdo</a>
    <?php if ($logado && !$loja['ativa']): ?>
        <div class="loja-faixa-equipe" role="status">
            <?php echo icone('alerta'); ?> A loja está <strong>fechada ao público</strong>. Só a equipe logada consegue ver esta página.
            <a href="configuracoes.php">Abrir a loja</a>
        </div>
    <?php endif; ?>
    <header class="loja-topo">
        <div class="loja-topo-interno">
            <a href="loja.php" class="loja-marca">
                <span class="loja-marca-icone" aria-hidden="true"><?php echo htmlspecialchars(iniciais($loja['nome'])); ?></span>
                <span class="loja-marca-nome"><?php echo htmlspecialchars($loja['nome']); ?></span>
            </a>

            <?php if ($comBusca): ?>
                <form action="loja.php" method="get" class="loja-busca" role="search">
                    <label for="lojaBusca" class="visually-hidden">Buscar produtos</label>
                    <?php echo icone('busca'); ?>
                    <input type="search" id="lojaBusca" name="q" value="<?php echo htmlspecialchars($busca); ?>"
                           placeholder="O que você procura?" autocomplete="off" maxlength="80">
                </form>
            <?php endif; ?>

            <nav class="loja-acoes" aria-label="Atalhos">
                <button type="button" class="loja-acao" data-alternar-tema aria-label="Alternar modo escuro">
                    <?php echo icone('lua', 'icone-tema-escuro'); ?><?php echo icone('sol', 'icone-tema-claro'); ?>
                </button>
                <a href="<?php echo $logado ? 'index.php' : 'login.php'; ?>" class="loja-acao loja-acao-texto">
                    <?php echo icone($logado ? 'grafico' : 'usuario'); ?> <span><?php echo $logado ? 'Painel' : 'Área da equipe'; ?></span>
                </a>
                <a href="loja_carrinho.php" class="loja-acao loja-carrinho-botao"
                   aria-label="Carrinho: <?php echo $unidades; ?> <?php echo $unidades === 1 ? 'item' : 'itens'; ?>">
                    <?php echo icone('carrinho'); ?> <span class="loja-acao-rotulo">Carrinho</span>
                    <?php if ($unidades > 0): ?><span class="loja-contador"><?php echo $unidades; ?></span><?php endif; ?>
                </a>
            </nav>
        </div>
    </header>
    <?php
}

/** Fecha a página: rodapé com contato, mensagens (toasts) e scripts. */
function loja_rodape(array $loja): void
{
    $toasts = flash_pegar();
    $iconesToast = ['success' => 'sucesso', 'error' => 'erro', 'warning' => 'alerta', 'info' => 'erro'];
    $zap = whatsapp_link($loja['whatsapp'], 'Olá! Vim pela loja online.');
    ?>
    <footer class="loja-rodape">
        <div class="loja-rodape-interno">
            <div>
                <strong><?php echo htmlspecialchars($loja['nome']); ?></strong>
                <?php if ($loja['slogan']): ?><p><?php echo htmlspecialchars($loja['slogan']); ?></p><?php endif; ?>
            </div>
            <div class="loja-rodape-contato">
                <?php if ($zap): ?>
                    <a href="<?php echo htmlspecialchars($zap); ?>" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Fale conosco pelo WhatsApp</a>
                <?php endif; ?>
                <span><?php echo icone('cartao'); ?> Pix, cartão, boleto ou dinheiro</span>
                <span><?php echo icone('caminhao'); ?> Entrega ou retirada na loja</span>
            </div>
        </div>
        <p class="loja-rodape-copy">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($loja['nome']); ?>. Todos os direitos reservados.</p>
    </footer>

    <div class="toast-area" aria-live="polite">
        <?php foreach ($toasts as $toast): ?>
            <div class="toast toast-<?php echo htmlspecialchars($toast['tipo']); ?>" role="status">
                <?php echo icone($iconesToast[$toast['tipo']] ?? 'erro', 'toast-icon'); ?>
                <span class="toast-text"><?php echo htmlspecialchars($toast['mensagem']); ?></span>
                <button type="button" class="toast-close" aria-label="Fechar mensagem"><?php echo icone('fechar'); ?></button>
            </div>
        <?php endforeach; ?>
    </div>

    <script src="js/app.js"></script>
    <script src="js/loja.js"></script>
</body>
</html>
    <?php
}

/** Card de produto da vitrine (com botão de adicionar ao carrinho). */
function loja_card_produto(array $p, string $voltar): string
{
    $id = (int)$p['ID'];
    $estoque = (int)$p['ESTOQUE'];
    $link = 'loja_produto.php?id=' . $id;
    $selo = '';
    if ($estoque <= 0) {
        $selo = '<span class="loja-selo loja-selo-esgotado">Esgotado</span>';
    } elseif ($estoque <= ESTOQUE_BAIXO) {
        $selo = '<span class="loja-selo loja-selo-ultimas">Últimas unidades</span>';
    } elseif (!empty($p['DESTAQUE'])) {
        $selo = '<span class="loja-selo">Destaque</span>';
    }

    $html = '<article class="loja-card' . ($estoque <= 0 ? ' is-esgotado' : '') . '">';
    $html .= '<a href="' . $link . '" class="loja-card-foto" tabindex="-1" aria-hidden="true">' . foto_produto($p) . $selo . '</a>';
    $html .= '<div class="loja-card-corpo">';
    if (!empty($p['CATEGORIA'])) {
        $html .= '<span class="loja-card-categoria">' . htmlspecialchars($p['CATEGORIA']) . '</span>';
    }
    $html .= '<h3 class="loja-card-nome"><a href="' . $link . '">' . htmlspecialchars($p['NOME']) . '</a></h3>';
    $html .= '<p class="loja-card-preco">' . moeda((float)$p['PRECO']) . '</p>';
    $html .= '</div>';
    if ($estoque > 0) {
        $html .= '<form method="POST" action="loja_carrinho.php" class="loja-card-acao">' . csrf_campo()
            . '<input type="hidden" name="acao" value="adicionar"><input type="hidden" name="id" value="' . $id . '">'
            . '<input type="hidden" name="voltar" value="' . htmlspecialchars($voltar) . '">'
            . '<button type="submit" class="btn btn-primary btn-bloco">' . icone('carrinho') . ' Adicionar</button></form>';
    } else {
        $html .= '<div class="loja-card-acao"><button type="button" class="btn btn-outline btn-bloco" disabled>Esgotado</button></div>';
    }
    return $html . '</article>';
}

/** Endereço de volta seguro (só páginas da vitrine) — evita redirecionar para outro site. */
function loja_voltar_seguro(string $voltar): string
{
    return preg_match('/^loja(_produto)?\.php(\?[A-Za-z0-9_=&%+\-.]*)?$/', $voltar) ? $voltar : 'loja.php';
}
