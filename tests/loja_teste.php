<?php
// Loja online: carrinho, validação da finalização, gravação do pedido e fotos.
// Os testes de banco rodam numa transação desfeita no final (no_banco).

/** Produto de teste visível (ou não) na loja; devolve o ID. */
function produto_loja(PDO $pdo, int $estoque, float $preco = 50.0, bool $naLoja = true, ?float $custo = 20.0): int
{
    $pdo->prepare('INSERT INTO produtos (NOME, PRECO, CUSTO, ESTOQUE, NA_LOJA) VALUES (?, ?, ?, 0, ?)')
        ->execute(['Produto loja ' . bin2hex(random_bytes(4)), $preco, $custo, (int)$naLoja]);
    $id = (int)$pdo->lastInsertId();
    movimentar_estoque($pdo, $id, $estoque, 'CADASTRO', 'Teste');
    return $id;
}

/** Dados válidos de finalização (retirada na loja). */
function checkout_teste(array $mudancas = []): array
{
    return array_merge(checkout_vazio(), [
        'nome' => 'Cliente da Loja', 'email' => 'loja.' . bin2hex(random_bytes(5)) . '@exemplo.invalid',
        'telefone' => '(11) 91234-5678', 'entrega' => 'RETIRADA', 'forma_pagamento' => 'PIX',
    ], $mudancas);
}

/** PNG válido de $lado × $lado pixels, gerado sem a extensão GD. */
function png_teste(int $lado): string
{
    $pedaco = fn(string $tipo, string $dados) => pack('N', strlen($dados)) . $tipo . $dados . pack('N', crc32($tipo . $dados));
    $linhas = str_repeat("\0" . str_repeat("\xff\x00\x00", $lado), $lado);
    return "\x89PNG\r\n\x1a\n"
        . $pedaco('IHDR', pack('NNCCCCC', $lado, $lado, 8, 2, 0, 0, 0))
        . $pedaco('IDAT', gzcompress($linhas))
        . $pedaco('IEND', '');
}

/** Simula um item de $_FILES a partir de um conteúdo. */
function upload_teste(string $conteudo): array
{
    $caminho = tempnam(sys_get_temp_dir(), 'foto');
    file_put_contents($caminho, $conteudo);
    return ['name' => 'foto.png', 'tmp_name' => $caminho, 'size' => strlen($conteudo), 'error' => UPLOAD_ERR_OK, 'type' => 'image/png'];
}

/* ---------- Carrinho (sessão) ---------- */

teste('Carrinho soma quantidades, respeita o limite por produto e remove com zero', function () {
    $_SESSION['carrinho'] = [];
    carrinho_adicionar(7, 2);
    carrinho_adicionar(7, 3);
    igual([7 => 5], carrinho_ler());
    carrinho_adicionar(7, 500);
    igual(LOJA_QTD_MAXIMA, carrinho_ler()[7]);
    carrinho_definir(7, 0);
    igual([], carrinho_ler());
});

teste('Carrinho ignora ids e quantidades inválidos vindos da sessão', function () {
    $_SESSION['carrinho'] = ['abc' => 2, 3 => -1, 4 => '2', 0 => 5];
    igual([4 => 2], carrinho_ler());
    carrinho_limpar();
});

/* ---------- Validação da finalização ---------- */

teste('Retirada na loja não exige endereço; entrega exige CEP, endereço e UF', function () {
    igual([], checkout_validar(checkout_teste()));
    $erros = checkout_validar(checkout_teste(['entrega' => 'ENTREGA']));
    foreach (['cep', 'logradouro', 'numero', 'bairro', 'cidade', 'uf'] as $campo) {
        verdadeiro(isset($erros[$campo]), "faltou o erro de $campo");
    }
});

teste('Finalização exige telefone e forma de pagamento da loja; CPF é opcional mas conferido', function () {
    $erros = checkout_validar(checkout_teste(['telefone' => '', 'forma_pagamento' => 'CHEQUE', 'cpf_cnpj' => '111.111.111-11']));
    verdadeiro(isset($erros['telefone']));
    verdadeiro(isset($erros['forma_pagamento']));
    verdadeiro(isset($erros['cpf_cnpj']));
    igual([], checkout_validar(checkout_teste(['cpf_cnpj' => '529.982.247-25'])));
});

teste('Texto de entrega junta endereço e contato', function () {
    $texto = checkout_texto_entrega(checkout_teste([
        'entrega' => 'ENTREGA', 'cep' => '01310-100', 'logradouro' => 'Av. Paulista', 'numero' => '1000',
        'complemento' => 'Sala 5', 'bairro' => 'Bela Vista', 'cidade' => 'São Paulo', 'uf' => 'SP',
    ]));
    igual('Av. Paulista, 1000 - Sala 5 — Bela Vista — São Paulo/SP — CEP 01310-100 · Contato: Cliente da Loja, (11) 91234-5678', $texto);
    verdadeiro(str_starts_with(checkout_texto_entrega(checkout_teste()), 'Retirar na loja'));
});

/* ---------- Banco ---------- */

teste('Carrinho montado reduz ao estoque e retira produtos esgotados ou fora da loja', function () {
    no_banco(function (PDO $pdo) {
        $normal = produto_loja($pdo, 3, 10.0);
        $esgotado = produto_loja($pdo, 0);
        $fora = produto_loja($pdo, 5, 10.0, false);
        $r = carrinho_montar($pdo, [$normal => 5, $esgotado => 1, $fora => 1]);
        igual(1, count($r['linhas']));
        igual(3, $r['linhas'][0]['QUANTIDADE']);
        igual(30.0, $r['total']);
        igual(3, count($r['avisos']));
    });
});

teste('Pedido da loja cria cliente, nasce ABERTO com origem LOJA e baixa o estoque', function () {
    no_banco(function (PDO $pdo) {
        $a = produto_loja($pdo, 10, 25.0, true, 10.0);
        $b = produto_loja($pdo, 4, 100.0, true, null);
        $dados = checkout_teste(['observacao' => 'Embrulhar para presente']);
        $pedidoId = loja_registrar_pedido($pdo, $dados, [$a => 2, $b => 1]);

        $stmt = $pdo->prepare('SELECT p.STATUS, p.ORIGEM, p.USUARIO_ID, p.FORMA_PAGAMENTO, p.OBSERVACAO, p.ENTREGA, v.TOTAL, v.CUSTO, c.EMAIL
                                 FROM pedidos p INNER JOIN vw_pedidos v ON v.ID = p.ID INNER JOIN clientes c ON c.ID = p.CLIENTE_ID WHERE p.ID = ?');
        $stmt->execute([$pedidoId]);
        $p = $stmt->fetch();
        igual('ABERTO', $p['STATUS']);
        igual('LOJA', $p['ORIGEM']);
        igual(null, $p['USUARIO_ID']);
        igual('PIX', $p['FORMA_PAGAMENTO']);
        igual('Embrulhar para presente', $p['OBSERVACAO']);
        igual($dados['email'], $p['EMAIL']);
        igual(150.0, (float)$p['TOTAL']);
        igual(20.0, (float)$p['CUSTO'], 'custo copiado só do produto que tem custo');
        verdadeiro(str_starts_with($p['ENTREGA'], 'Retirar na loja'));
        igual(8, estoque_de($pdo, $a));
        igual(3, estoque_de($pdo, $b));
    });
});

teste('Pedido da loja reaproveita o cliente pelo email sem alterar o cadastro', function () {
    no_banco(function (PDO $pdo) {
        $email = 'existente.' . bin2hex(random_bytes(5)) . '@exemplo.invalid';
        $pdo->prepare('INSERT INTO clientes (NOME, EMAIL, TELEFONE) VALUES (?, ?, ?)')->execute(['Nome Original', $email, '(11) 3333-4444']);
        $clienteId = (int)$pdo->lastInsertId();
        $produto = produto_loja($pdo, 5);

        $pedidoId = loja_registrar_pedido($pdo, checkout_teste(['email' => $email, 'nome' => 'Outro Nome', 'telefone' => '(21) 99999-0000']), [$produto => 1]);

        $stmt = $pdo->prepare('SELECT p.CLIENTE_ID, c.NOME, c.TELEFONE FROM pedidos p INNER JOIN clientes c ON c.ID = p.CLIENTE_ID WHERE p.ID = ?');
        $stmt->execute([$pedidoId]);
        $r = $stmt->fetch();
        igual($clienteId, (int)$r['CLIENTE_ID']);
        igual('Nome Original', $r['NOME']);
        igual('(11) 3333-4444', $r['TELEFONE']);
    });
});

teste('Pedido da loja usa o preço do banco e recusa estoque insuficiente ou produto fora da loja', function () {
    no_banco(function (PDO $pdo) {
        $produto = produto_loja($pdo, 2, 80.0);
        $erro = null;
        try {
            loja_registrar_pedido($pdo, checkout_teste(), [$produto => 3]);
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
        verdadeiro($erro !== null && str_contains($erro, 'estoque'), 'deveria recusar por estoque');

        $fora = produto_loja($pdo, 5, 10.0, false);
        $erro = null;
        try {
            loja_registrar_pedido($pdo, checkout_teste(), [$fora => 1]);
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        }
        verdadeiro($erro !== null, 'deveria recusar produto fora da loja');
    });
});

/* ---------- Fotos ---------- */

teste('Foto: aceita PNG de verdade e recusa texto disfarçado, imagem minúscula e erro de envio', function () {
    $png = upload_teste(png_teste(60));
    igual(['ext' => 'png'], foto_validar($png));

    $falso = upload_teste("<?php echo 'oi'; ?>");
    verdadeiro(isset(foto_validar($falso)['erro']), 'texto com nome .png deveria ser recusado');

    $pequena = upload_teste(png_teste(10));
    verdadeiro(isset(foto_validar($pequena)['erro']), 'imagem de 10 px deveria ser recusada');

    verdadeiro(isset(foto_validar(['error' => UPLOAD_ERR_INI_SIZE])['erro']));
    foreach ([$png, $falso, $pequena] as $f) {
        @unlink($f['tmp_name']);
    }
});

teste('Foto: só nomes gerados pelo sistema viram endereço (sem caminhos)', function () {
    igual('uploads/produtos/12-a1b2c3.jpg', foto_url('12-a1b2c3.jpg'));
    igual(null, foto_url('../config.php'));
    igual(null, foto_url('12-a1b2c3.php'));
    igual(null, foto_url(null));
});

teste('Foto: gravar troca o arquivo do produto e apagar remove do disco', function () {
    no_banco(function (PDO $pdo) {
        $produto = produto_loja($pdo, 1);
        $arquivo = upload_teste(png_teste(60));
        $primeira = produto_trocar_foto($pdo, $produto, null, $arquivo, 'png', false, false);
        verdadeiro(is_file(FOTO_PASTA . '/' . $primeira));
        $segunda = produto_trocar_foto($pdo, $produto, $primeira, $arquivo, 'png', false, false);
        falso(is_file(FOTO_PASTA . '/' . $primeira), 'a foto antiga deveria ser apagada');
        igual(null, produto_trocar_foto($pdo, $produto, $segunda, null, null, true));
        falso(is_file(FOTO_PASTA . '/' . $segunda), 'remover deveria apagar o arquivo');
        @unlink($arquivo['tmp_name']);
    });
});
