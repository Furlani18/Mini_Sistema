<?php
declare(strict_types=1);

// Loja online (vitrine pública): configurações, carrinho, fotos dos produtos e
// fechamento do pedido. Usado pelas páginas loja*.php (abertas, sem login) e pelo
// cadastro de produtos (upload de foto).
//
// Regras:
//  - o carrinho fica na sessão do visitante: [produto_id => quantidade];
//  - preço e estoque SEMPRE vêm do banco na hora (o carrinho guarda só ids e quantidades);
//  - o pedido da loja nasce com status ABERTO e ORIGEM = 'LOJA': baixa o estoque como
//    qualquer venda e aparece para a equipe em Pedidos e no Painel;
//  - o cliente é reaproveitado pelo email (sem alterar o cadastro existente) ou criado.

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/validacao.php';
require_once __DIR__ . '/estoque.php';

const LOJA_POR_PAGINA = 12;
const LOJA_QTD_MAXIMA = 99;            // unidades de um mesmo produto por pedido
const LOJA_ITENS_MAXIMO = 30;          // produtos diferentes no carrinho
const LOJA_PEDIDOS_POR_HORA = 5;       // pedidos por IP por hora (contra abuso)

const FOTO_PASTA = __DIR__ . '/uploads/produtos';
const FOTO_URL = 'uploads/produtos';
const FOTO_TAMANHO_MAXIMO = 3 * 1024 * 1024;   // 3 MB (o navegador já reduz antes de enviar)
const FOTO_TIPOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/* ---------- Configurações ---------- */

/** Configurações da loja com valores padrão. */
function loja_config(PDO $pdo): array
{
    $valores = $pdo->query("SELECT CHAVE, VALOR FROM configuracoes WHERE CHAVE LIKE 'loja\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    return [
        'ativa'    => ($valores['loja_ativa'] ?? '1') === '1',
        'nome'     => trim((string)($valores['loja_nome'] ?? '')) ?: 'Minha Loja',
        'slogan'   => trim((string)($valores['loja_slogan'] ?? '')),
        'whatsapp' => trim((string)($valores['loja_whatsapp'] ?? '')),
    ];
}

/* ---------- Carrinho (sessão) ---------- */

/** Carrinho atual: [produto_id => quantidade], só com valores válidos. */
function carrinho_ler(): array
{
    $carrinho = [];
    foreach ((array)($_SESSION['carrinho'] ?? []) as $id => $qtd) {
        $id = (int)$id;
        $qtd = (int)$qtd;
        if ($id > 0 && $qtd > 0) {
            $carrinho[$id] = min($qtd, LOJA_QTD_MAXIMA);
        }
    }
    return $carrinho;
}

function carrinho_gravar(array $carrinho): void
{
    $_SESSION['carrinho'] = $carrinho;
}

/** Soma $qtd unidades do produto (respeitando os limites). Retorna a nova quantidade. */
function carrinho_adicionar(int $produtoId, int $qtd): int
{
    $carrinho = carrinho_ler();
    if (!isset($carrinho[$produtoId]) && count($carrinho) >= LOJA_ITENS_MAXIMO) {
        return 0;
    }
    $carrinho[$produtoId] = min(LOJA_QTD_MAXIMA, ($carrinho[$produtoId] ?? 0) + max(1, $qtd));
    carrinho_gravar($carrinho);
    return $carrinho[$produtoId];
}

/** Define a quantidade (0 remove o produto). */
function carrinho_definir(int $produtoId, int $qtd): void
{
    $carrinho = carrinho_ler();
    if ($qtd <= 0) {
        unset($carrinho[$produtoId]);
    } elseif (isset($carrinho[$produtoId])) {
        $carrinho[$produtoId] = min($qtd, LOJA_QTD_MAXIMA);
    }
    carrinho_gravar($carrinho);
}

function carrinho_limpar(): void
{
    unset($_SESSION['carrinho']);
}

/** Total de unidades no carrinho (para o contador do topo). */
function carrinho_unidades(): int
{
    return array_sum(carrinho_ler());
}

/**
 * Monta o carrinho com os dados atuais do banco.
 * Retorna ['linhas' => [...], 'total' => float, 'avisos' => [...]].
 * Produtos que saíram da loja ou esgotaram são retirados; quantidades acima do
 * estoque são reduzidas — e o visitante é avisado. O carrinho da sessão é corrigido.
 */
function carrinho_montar(PDO $pdo, ?array $carrinho = null): array
{
    $carrinho ??= carrinho_ler();
    $resultado = ['linhas' => [], 'total' => 0.0, 'unidades' => 0, 'avisos' => []];
    if (!$carrinho) {
        return $resultado;
    }

    $marcadores = implode(', ', array_fill(0, count($carrinho), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.ID, p.NOME, p.PRECO, p.ESTOQUE, p.FOTO, p.CATEGORIA_ID, c.NOME AS CATEGORIA
           FROM produtos p LEFT JOIN categorias c ON c.ID = p.CATEGORIA_ID
          WHERE p.NA_LOJA = 1 AND p.ID IN ($marcadores)"
    );
    $stmt->execute(array_keys($carrinho));
    $produtos = [];
    foreach ($stmt->fetchAll() as $p) {
        $produtos[(int)$p['ID']] = $p;
    }

    $corrigido = [];
    foreach ($carrinho as $id => $qtd) {
        $p = $produtos[$id] ?? null;
        if (!$p) {
            $resultado['avisos'][] = 'Um produto do seu carrinho não está mais disponível e foi retirado.';
            continue;
        }
        $estoque = (int)$p['ESTOQUE'];
        if ($estoque <= 0) {
            $resultado['avisos'][] = "\"{$p['NOME']}\" esgotou e foi retirado do carrinho.";
            continue;
        }
        if ($qtd > $estoque) {
            $resultado['avisos'][] = "Só temos $estoque unidade(s) de \"{$p['NOME']}\"; ajustamos a quantidade.";
            $qtd = $estoque;
        }
        $corrigido[$id] = $qtd;
        $subtotal = round($qtd * (float)$p['PRECO'], 2);
        $resultado['linhas'][] = $p + ['QUANTIDADE' => $qtd, 'SUBTOTAL' => $subtotal];
        $resultado['total'] += $subtotal;
        $resultado['unidades'] += $qtd;
    }
    $resultado['total'] = round($resultado['total'], 2);
    $resultado['avisos'] = array_values(array_unique($resultado['avisos']));

    if ($corrigido !== $carrinho && $carrinho === carrinho_ler()) {
        carrinho_gravar($corrigido);
    }
    return $resultado;
}

/* ---------- Fotos dos produtos ---------- */

/** Endereço público da foto (ou null quando o produto não tem foto). */
function foto_url(?string $arquivo): ?string
{
    return $arquivo && preg_match('/^[a-z0-9\-]+\.(jpg|png|webp)$/', $arquivo) ? FOTO_URL . '/' . $arquivo : null;
}

/**
 * Confere um arquivo enviado ($_FILES['foto']). Retorna ['ext' => 'jpg'] ou ['erro' => '...'].
 * O tipo é descoberto pelo CONTEÚDO do arquivo (não pelo nome nem pelo que o navegador diz).
 */
function foto_validar(array $arquivo): array
{
    $codigo = (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
        return ['erro' => 'A foto é grande demais (máximo de 3 MB).'];
    }
    if ($codigo !== UPLOAD_ERR_OK) {
        return ['erro' => 'Não foi possível receber a foto. Tente de novo.'];
    }
    $caminho = (string)($arquivo['tmp_name'] ?? '');
    $tamanho = (int)($arquivo['size'] ?? 0);
    if ($tamanho <= 0 || $tamanho > FOTO_TAMANHO_MAXIMO || !is_file($caminho)) {
        return ['erro' => 'A foto é grande demais (máximo de 3 MB).'];
    }
    $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($caminho);
    if (!isset(FOTO_TIPOS[$tipo])) {
        return ['erro' => 'Envie uma imagem JPG, PNG ou WebP.'];
    }
    $dimensoes = @getimagesize($caminho);
    if (!$dimensoes || $dimensoes[0] < 50 || $dimensoes[1] < 50 || $dimensoes[0] > 6000 || $dimensoes[1] > 6000) {
        return ['erro' => 'Imagem inválida ou com tamanho fora do normal (de 50 a 6000 pixels).'];
    }
    return ['ext' => FOTO_TIPOS[$tipo]];
}

/**
 * Grava a foto enviada com um nome novo e aleatório (o nome original é descartado).
 * Retorna o nome do arquivo gravado. $mover = false copia (usado nos testes).
 */
function foto_gravar(array $arquivo, int $produtoId, string $ext, bool $mover = true): string
{
    if (!is_dir(FOTO_PASTA)) {
        mkdir(FOTO_PASTA, 0755, true);
    }
    $nome = $produtoId . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destino = FOTO_PASTA . '/' . $nome;
    $ok = $mover ? move_uploaded_file($arquivo['tmp_name'], $destino) : copy($arquivo['tmp_name'], $destino);
    if (!$ok) {
        throw new RuntimeException('Não foi possível gravar a foto no servidor.');
    }
    return $nome;
}

/** Apaga o arquivo de uma foto (ignora nomes inválidos ou arquivos que já não existem). */
function foto_apagar(?string $arquivo): void
{
    if ($arquivo && foto_url($arquivo) !== null && is_file(FOTO_PASTA . '/' . $arquivo)) {
        @unlink(FOTO_PASTA . '/' . $arquivo);
    }
}

/**
 * Grava a foto nova do produto (apagando a antiga) ou remove a foto atual.
 * Retorna o nome da foto que ficou no produto (ou null).
 */
function produto_trocar_foto(PDO $pdo, int $produtoId, ?string $fotoAtual, ?array $arquivo, ?string $ext, bool $remover, bool $mover = true): ?string
{
    if ($arquivo !== null && $ext !== null) {
        $nova = foto_gravar($arquivo, $produtoId, $ext, $mover);
        $pdo->prepare('UPDATE produtos SET FOTO = ? WHERE ID = ?')->execute([$nova, $produtoId]);
        foto_apagar($fotoAtual);
        return $nova;
    }
    if ($remover && $fotoAtual !== null) {
        $pdo->prepare('UPDATE produtos SET FOTO = NULL WHERE ID = ?')->execute([$produtoId]);
        foto_apagar($fotoAtual);
        return null;
    }
    return $fotoAtual;
}

/**
 * Imagem do produto: a foto, ou um quadro colorido com a inicial do nome
 * (a cor vem da categoria, para produtos da mesma categoria combinarem).
 */
function foto_produto(array $p, string $classe = ''): string
{
    $url = foto_url($p['FOTO'] ?? null);
    $nome = htmlspecialchars((string)$p['NOME']);
    if ($url) {
        return '<img src="' . htmlspecialchars($url) . '" alt="' . $nome . '" class="foto-produto ' . $classe . '" loading="lazy">';
    }
    $tom = ((int)($p['CATEGORIA_ID'] ?? 0) ?: crc32((string)$p['NOME'])) % 6;
    return '<span class="foto-produto foto-vazia tom-' . $tom . ' ' . $classe . '" role="img" aria-label="' . $nome . ' (sem foto)">'
        . htmlspecialchars(mb_strtoupper(mb_substr(trim((string)$p['NOME']), 0, 1))) . '</span>';
}

/* ---------- Fechamento do pedido ---------- */

/** Campos do formulário de finalização, vazios. */
function checkout_vazio(): array
{
    return array_merge(array_fill_keys([
        'nome', 'email', 'telefone', 'cpf_cnpj', 'entrega', 'cep', 'logradouro', 'numero',
        'complemento', 'bairro', 'cidade', 'uf', 'forma_pagamento', 'observacao',
    ], ''), ['entrega' => 'ENTREGA']);
}

/** Formas de pagamento aceitas na loja (pagas na entrega/retirada ou combinadas com a loja). */
function loja_formas_pagamento(): array
{
    return array_intersect_key(formas_pagamento(), array_flip(['PIX', 'CREDITO', 'DEBITO', 'DINHEIRO', 'BOLETO']));
}

/** Valida o formulário de finalização. Retorna campo => mensagem (vazio = tudo certo). */
function checkout_validar(array $v): array
{
    $erros = validar_cliente($v['nome'], $v['email'], $v['telefone']);
    if ($v['telefone'] === '' && !isset($erros['telefone'])) {
        $erros['telefone'] = 'Informe um telefone para a loja falar com você.';
    }
    $doc = preg_replace('/\D/', '', $v['cpf_cnpj']);
    if ($doc !== '' && !(strlen($doc) === 11 ? cpf_valido($doc) : (strlen($doc) === 14 && cnpj_valido($doc)))) {
        $erros['cpf_cnpj'] = 'CPF ou CNPJ inválido. Confira os números ou deixe em branco.';
    }
    if (!in_array($v['entrega'], ['ENTREGA', 'RETIRADA'], true)) {
        $erros['entrega'] = 'Escolha entrega ou retirada.';
    } elseif ($v['entrega'] === 'ENTREGA') {
        if (strlen(preg_replace('/\D/', '', $v['cep'])) !== 8) {
            $erros['cep'] = 'Informe o CEP com 8 dígitos.';
        }
        foreach (['logradouro' => 'o endereço', 'numero' => 'o número', 'bairro' => 'o bairro', 'cidade' => 'a cidade'] as $campo => $nome) {
            if ($v[$campo] === '') {
                $erros[$campo] = "Informe $nome.";
            }
        }
        if (!in_array($v['uf'], ufs(), true)) {
            $erros['uf'] = 'Escolha o estado.';
        }
    }
    if (!isset(loja_formas_pagamento()[$v['forma_pagamento']])) {
        $erros['forma_pagamento'] = 'Escolha como prefere pagar.';
    }
    $limites = ['nome' => 100, 'email' => 100, 'logradouro' => 150, 'numero' => 20, 'complemento' => 100,
                'bairro' => 100, 'cidade' => 100, 'observacao' => 255];
    foreach ($limites as $campo => $max) {
        if (mb_strlen($v[$campo]) > $max && !isset($erros[$campo])) {
            $erros[$campo] = "Use no máximo $max caracteres.";
        }
    }
    return $erros;
}

/** Texto de entrega gravado no pedido (endereço ou retirada + telefone de contato). */
function checkout_texto_entrega(array $v): string
{
    if ($v['entrega'] === 'RETIRADA') {
        $texto = 'Retirar na loja';
    } else {
        $texto = $v['logradouro'] . ', ' . $v['numero'] . ($v['complemento'] !== '' ? ' - ' . $v['complemento'] : '')
            . ' — ' . $v['bairro'] . ' — ' . $v['cidade'] . '/' . $v['uf'] . ' — CEP ' . formatar_cep(preg_replace('/\D/', '', $v['cep']));
    }
    return mb_substr($texto . ' · Contato: ' . $v['nome'] . ', ' . $v['telefone'], 0, 500);
}

/**
 * Grava o pedido da loja numa transação: cliente (reaproveitado pelo email ou criado),
 * pedido ABERTO com ORIGEM = 'LOJA', itens com o preço e o custo atuais, e baixa do estoque.
 * Lança RuntimeException com uma mensagem para o visitante se algo impedir o pedido.
 * Retorna o ID do pedido.
 */
function loja_registrar_pedido(PDO $pdo, array $v, array $carrinho): int
{
    if (!$carrinho) {
        throw new RuntimeException('Seu carrinho está vazio.');
    }
    $propria = !$pdo->inTransaction();
    if ($propria) {
        $pdo->beginTransaction();
    }
    try {
        // Cliente: o email identifica. Cadastro existente não é alterado pela loja
        // (outra pessoa poderia digitar o email de alguém); o endereço fica no pedido.
        $stmt = $pdo->prepare('SELECT ID FROM clientes WHERE EMAIL = ?');
        $stmt->execute([$v['email']]);
        $clienteId = (int)$stmt->fetchColumn();
        if ($clienteId === 0) {
            $novo = cliente_vazio();
            foreach ($novo as $campo => $_) {
                $novo[$campo] = $v[$campo] ?? '';
            }
            if ($v['entrega'] !== 'ENTREGA') {
                foreach (['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
                    $novo[$campo] = '';
                }
            }
            $novo['observacoes'] = 'Cadastrado pela loja online.';
            try {
                $pdo->prepare(sql_inserir_cliente())->execute(cliente_para_banco($novo));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    throw new RuntimeException('Esse CPF/CNPJ já está cadastrado com outro email. Use o email do seu cadastro ou deixe o CPF em branco.');
                }
                throw $e;
            }
            $clienteId = (int)$pdo->lastInsertId();
        }

        $pdo->prepare(
            "INSERT INTO pedidos (CLIENTE_ID, USUARIO_ID, STATUS, ORIGEM, OBSERVACAO, FORMA_PAGAMENTO, ENTREGA)
             VALUES (?, NULL, 'ABERTO', 'LOJA', ?, ?, ?)"
        )->execute([$clienteId, $v['observacao'] !== '' ? $v['observacao'] : null, $v['forma_pagamento'], checkout_texto_entrega($v)]);
        $pedidoId = (int)$pdo->lastInsertId();

        // Preço e custo vêm do banco; só entram produtos visíveis na loja
        $item = $pdo->prepare(
            'INSERT INTO itens_pedido (PEDIDO_ID, PRODUTO_ID, QUANTIDADE, PRECO_UNITARIO, CUSTO_UNITARIO)
             SELECT ?, ID, ?, PRECO, CUSTO FROM produtos WHERE ID = ? AND NA_LOJA = 1'
        );
        foreach ($carrinho as $produtoId => $qtd) {
            $item->execute([$pedidoId, min((int)$qtd, LOJA_QTD_MAXIMA), (int)$produtoId]);
            if ($item->rowCount() === 0) {
                throw new RuntimeException('Um produto do carrinho não está mais disponível. Confira o carrinho.');
            }
        }

        $faltando = baixar_estoque($pdo, $pedidoId, "Pedido #$pedidoId (loja online)");
        if ($faltando) {
            throw new RuntimeException('Acabou o estoque de: ' . implode(', ', $faltando) . '. Ajuste o carrinho e tente de novo.');
        }

        if ($propria) {
            $pdo->commit();
        }
        return $pedidoId;
    } catch (Throwable $e) {
        if ($propria && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Quantos pedidos este IP fez pela loja na última hora (registro de atividades). */
function loja_pedidos_recentes(PDO $pdo): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM atividades
          WHERE ACAO = 'CRIOU' AND ENTIDADE = 'pedido' AND USUARIO_ID IS NULL
            AND IP = ? AND CRIADO_EM > NOW() - INTERVAL 1 HOUR"
    );
    $stmt->execute([ip_cliente()]);
    return (int)$stmt->fetchColumn();
}
