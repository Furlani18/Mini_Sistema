<?php
// Ferramentas compartilhadas pelas listas (clientes, produtos, pedidos e usuários):
// ordenação por coluna, paginação e links que preservam os filtros da URL.
//
// Uso típico:
//   $ordem = ordenacao(['nome' => 'c.NOME', 'id' => 'c.ID'], 'id', 'desc');
//   $pag   = paginacao($totalDeRegistros);
//   ... ORDER BY {$ordem['sql']} LIMIT {$pag['limite']} OFFSET {$pag['offset']}
//   echo th_ordenavel('nome', 'Cliente', $ordem);   (no <thead>)
//   echo seletor_ordenacao(['nome' => 'Cliente'], $ordem);  (celular: as colunas viram cartões)
//   echo controles_paginacao($pag);                 (abaixo da tabela)

require_once __DIR__ . '/ui.php';

/** Quantidades de registros por página que o usuário pode escolher. */
const POR_PAGINA_OPCOES = [10, 25, 50];

/**
 * Monta um link para a página atual mantendo os parâmetros da URL e trocando
 * só os informados em $mudancas (valor null remove o parâmetro).
 */
function link_lista(array $mudancas = []): string
{
    $params = array_merge($_GET, $mudancas);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    $pagina = basename($_SERVER['SCRIPT_NAME']);
    return htmlspecialchars($pagina . ($params ? '?' . http_build_query($params) : ''));
}

/**
 * Lê ?ordem=coluna&dir=asc|desc da URL, aceitando só as colunas permitidas
 * (as chaves de $colunas). Os valores de $colunas são as expressões SQL — nunca
 * o texto vindo da URL — o que evita SQL injection no ORDER BY.
 */
function ordenacao(array $colunas, string $padrao, string $dirPadrao = 'asc'): array
{
    $coluna = $_GET['ordem'] ?? $padrao;
    $dir = $_GET['dir'] ?? $dirPadrao;
    // O seletor do celular envia coluna e direção juntas: ?ordenar=nome:desc
    $combinado = $_GET['ordenar'] ?? null;
    if (is_string($combinado) && str_contains($combinado, ':')) {
        [$coluna, $dir] = explode(':', $combinado, 2);
    }
    if (!is_string($coluna) || !isset($colunas[$coluna])) {
        $coluna = $padrao;
    }
    $dir = is_string($dir) && strtolower($dir) === 'desc' ? 'desc' : 'asc';

    return [
        'coluna' => $coluna,
        'dir'    => $dir,
        'padrao' => $padrao,
        'sql'    => $colunas[$coluna] . ' ' . strtoupper($dir),
    ];
}

/** Cabeçalho de tabela clicável que alterna a ordenação (crescente/decrescente). */
function th_ordenavel(string $coluna, string $rotulo, array $ordem, string $classe = '', string $primeiraDir = 'asc'): string
{
    $ativa = $ordem['coluna'] === $coluna;
    // $primeiraDir: direção do primeiro clique (para valores e datas, 'desc' — do maior para o menor)
    $proximaDir = $ativa ? ($ordem['dir'] === 'asc' ? 'desc' : 'asc') : $primeiraDir;
    $href = link_lista(['ordem' => $coluna, 'dir' => $proximaDir, 'ordenar' => null, 'pagina' => null]);

    $ariaSort = $ativa ? ($ordem['dir'] === 'asc' ? 'ascending' : 'descending') : 'none';
    $seta = $ativa ? ($ordem['dir'] === 'asc' ? '↑' : '↓') : '↕';
    $classes = trim($classe . ($ativa ? ' is-sorted' : ''));

    return '<th class="th-ordenavel ' . $classes . '" aria-sort="' . $ariaSort . '">'
        . '<a href="' . $href . '">' . htmlspecialchars($rotulo)
        . ' <span class="sort-seta" aria-hidden="true">' . $seta . '</span></a></th>';
}

/**
 * Seletor "Ordenar por" para telas pequenas, onde a tabela vira uma lista de cartões
 * e os cabeçalhos clicáveis somem. $rotulos = ['coluna' => 'Rótulo', ...] (as mesmas
 * colunas de ordenacao()). Cada coluna aparece nas duas direções.
 */
function seletor_ordenacao(array $rotulos, array $ordem): string
{
    $atual = $ordem['coluna'] . ':' . $ordem['dir'];
    $html = '<form method="get" class="ordenar-celular">' . campos_ocultos(['ordem', 'dir', 'ordenar', 'pagina'])
        . '<label for="ordenarCelular">Ordenar por</label><select id="ordenarCelular" name="ordenar" class="filtro-select" data-auto-submit>';
    foreach ($rotulos as $coluna => $rotulo) {
        foreach (['asc' => '↑ crescente', 'desc' => '↓ decrescente'] as $dir => $texto) {
            $valor = $coluna . ':' . $dir;
            $html .= '<option value="' . htmlspecialchars($valor) . '"' . ($valor === $atual ? ' selected' : '') . '>'
                . htmlspecialchars($rotulo) . ' ' . $texto . '</option>';
        }
    }
    return $html . '</select><noscript><button type="submit" class="btn btn-outline btn-sm">OK</button></noscript></form>';
}

/** Calcula página atual, total de páginas, LIMIT e OFFSET a partir da URL. */
function paginacao(int $total): array
{
    $porPagina = (int)($_GET['por'] ?? POR_PAGINA_OPCOES[0]);
    if (!in_array($porPagina, POR_PAGINA_OPCOES, true)) {
        $porPagina = POR_PAGINA_OPCOES[0];
    }
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = min(max(1, (int)($_GET['pagina'] ?? 1)), $totalPaginas);

    return [
        'total'        => $total,
        'por'          => $porPagina,
        'pagina'       => $pagina,
        'totalPaginas' => $totalPaginas,
        'limite'       => $porPagina,
        'offset'       => ($pagina - 1) * $porPagina,
        'de'           => $total ? ($pagina - 1) * $porPagina + 1 : 0,
        'ate'          => min($pagina * $porPagina, $total),
    ];
}

/**
 * Barra de paginação: "Mostrando 11–20 de 57", seletor de itens por página
 * e botões de página (com reticências quando há muitas páginas).
 */
function controles_paginacao(array $pag): string
{
    if ($pag['total'] === 0) {
        return '';
    }

    // Páginas visíveis: primeira, última e duas vizinhas da atual
    $visiveis = [];
    foreach (range(1, $pag['totalPaginas']) as $n) {
        if ($n === 1 || $n === $pag['totalPaginas'] || abs($n - $pag['pagina']) <= 1) {
            $visiveis[] = $n;
        }
    }

    $html = '<div class="paginacao">';
    $html .= '<p class="paginacao-info">Mostrando <strong>' . $pag['de'] . '–' . $pag['ate']
        . '</strong> de <strong>' . $pag['total'] . '</strong></p>';

    // Seletor "por página" (formulário GET que preserva os outros filtros)
    $html .= '<form method="get" class="paginacao-por">' . campos_ocultos(['por', 'pagina']);
    $html .= '<label for="porPagina">Por página</label><select id="porPagina" name="por" data-auto-submit>';
    foreach (POR_PAGINA_OPCOES as $opcao) {
        $html .= '<option value="' . $opcao . '"' . ($opcao === $pag['por'] ? ' selected' : '') . '>' . $opcao . '</option>';
    }
    $html .= '</select><noscript><button type="submit" class="btn btn-outline btn-sm">OK</button></noscript></form>';

    if ($pag['totalPaginas'] > 1) {
        $html .= '<nav class="paginacao-paginas" aria-label="Paginação">';
        $anterior = $pag['pagina'] - 1;
        $html .= $anterior >= 1
            ? '<a href="' . link_lista(['pagina' => $anterior]) . '" class="pag-btn" aria-label="Página anterior">‹</a>'
            : '<span class="pag-btn is-disabled" aria-hidden="true">‹</span>';

        $ultima = 0;
        foreach ($visiveis as $n) {
            if ($n - $ultima > 1) {
                $html .= '<span class="pag-reticencias" aria-hidden="true">…</span>';
            }
            $html .= $n === $pag['pagina']
                ? '<span class="pag-btn is-atual" aria-current="page">' . $n . '</span>'
                : '<a href="' . link_lista(['pagina' => $n]) . '" class="pag-btn" aria-label="Página ' . $n . '">' . $n . '</a>';
            $ultima = $n;
        }

        $proxima = $pag['pagina'] + 1;
        $html .= $proxima <= $pag['totalPaginas']
            ? '<a href="' . link_lista(['pagina' => $proxima]) . '" class="pag-btn" aria-label="Próxima página">›</a>'
            : '<span class="pag-btn is-disabled" aria-hidden="true">›</span>';
        $html .= '</nav>';
    }

    return $html . '</div>';
}

/**
 * Campos <input type="hidden"> com os parâmetros atuais da URL, exceto os listados.
 * Usado nos formulários de filtro para não perder ordenação, itens por página etc.
 */
function campos_ocultos(array $exceto): string
{
    $html = '';
    foreach ($_GET as $chave => $valor) {
        if (!in_array($chave, $exceto, true) && is_string($valor) && $valor !== '') {
            $html .= '<input type="hidden" name="' . htmlspecialchars($chave) . '" value="' . htmlspecialchars($valor) . '">';
        }
    }
    return $html;
}

/** Lê uma data AAAA-MM-DD da URL; retorna null se estiver vazia ou inválida. */
function data_da_url(string $parametro): ?string
{
    $valor = $_GET[$parametro] ?? '';
    $data = DateTime::createFromFormat('!Y-m-d', is_string($valor) ? $valor : '');
    return $data && $data->format('Y-m-d') === $valor ? $valor : null;
}

/** Texto de busca da URL (?q=), já sem espaços nas pontas. */
function busca_da_url(): string
{
    $q = $_GET['q'] ?? '';
    return is_string($q) ? trim($q) : '';
}

/** Escapa % e _ para usar o texto do usuário dentro de um LIKE. */
function like(string $texto): string
{
    return '%' . addcslashes($texto, '%_\\') . '%';
}
