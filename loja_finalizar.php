<?php
declare(strict_types=1);
require_once __DIR__ . '/loja_inicio.php';

// Finalização do pedido na loja online: dados de contato, entrega (ou retirada) e
// forma de pagamento. Ao enviar, grava cliente + pedido ABERTO (ORIGEM = 'LOJA') e
// baixa o estoque (loja_registrar_pedido). O pagamento é combinado com a loja depois.
//
// Proteções: token CSRF, campo-isca invisível contra robôs ("site") e limite de
// LOJA_PEDIDOS_POR_HORA pedidos por IP por hora.

$carrinho = carrinho_montar($pdo);
if (!$carrinho['linhas']) {
    flash('warning', 'Seu carrinho está vazio.');
    header('Location: loja_carrinho.php');
    exit;
}

$erros = [];
$erroGeral = '';
// Dados do último pedido feito neste navegador, para não precisar digitar tudo de novo
$valores = array_merge(checkout_vazio(), array_intersect_key((array)($_SESSION['loja_cliente'] ?? []), checkout_vazio()));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys(checkout_vazio()) as $campo) {
        $valor = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valor) ? trim($valor) : '';
    }
    $valores['email'] = mb_strtolower($valores['email']);
    $valores['uf'] = strtoupper($valores['uf']);

    if (($_POST['site'] ?? '') !== '') {
        // Campo-isca preenchido: é um robô. Finge que deu certo sem gravar nada.
        carrinho_limpar();
        header('Location: loja.php');
        exit;
    }

    $erros = checkout_validar($valores);
    if (!$erros && loja_pedidos_recentes($pdo) >= LOJA_PEDIDOS_POR_HORA) {
        $erroGeral = 'Recebemos muitos pedidos deste endereço na última hora. Aguarde um pouco ou fale com a loja.';
    }
    // O carrinho pode ter mudado (estoque/preço) enquanto o visitante preenchia
    if (!$erros && !$erroGeral && $carrinho['avisos']) {
        $erroGeral = 'Alguns itens do carrinho mudaram. Confira o resumo antes de enviar.';
    }

    if (!$erros && !$erroGeral) {
        $itens = array_column($carrinho['linhas'], 'QUANTIDADE', 'ID');
        try {
            $pedidoId = loja_registrar_pedido($pdo, $valores, $itens);
            registrar_atividade($pdo, 'CRIOU', "Pedido #$pedidoId recebido pela loja online ({$valores['nome']})", 'pedido', $pedidoId, 'Loja online');

            $_SESSION['loja_pedidos'][] = $pedidoId;
            $_SESSION['loja_cliente'] = array_diff_key($valores, ['observacao' => true]);
            carrinho_limpar();
            header('Location: loja_pedido.php?id=' . $pedidoId);
            exit;
        } catch (RuntimeException $e) {
            $erroGeral = $e->getMessage();
            $carrinho = carrinho_montar($pdo);
        }
    }
}

$formas = loja_formas_pagamento();
$campo = function (string $nome, string $rotulo, string $atributos = '', bool $obrigatorio = true) use ($valores, $erros): string {
    $erro = $erros[$nome] ?? '';
    return '<div class="form-group' . ($erro ? ' has-error' : '') . '" data-campo="' . $nome . '">'
        . '<label for="' . $nome . '">' . $rotulo . ($obrigatorio ? ' <span class="required" aria-hidden="true">*</span>' : '') . '</label>'
        . '<input id="' . $nome . '" name="' . $nome . '" value="' . htmlspecialchars($valores[$nome]) . '" ' . $atributos
        . ($obrigatorio ? ' required' : '') . ($erro ? ' aria-invalid="true"' : '') . ' aria-describedby="' . $nome . '-erro">'
        . '<small class="field-error" id="' . $nome . '-erro">' . icone('erro') . '<span>' . htmlspecialchars($erro) . '</span></small></div>';
};

loja_cabecalho($loja, 'Finalizar pedido', false);
?>
    <main class="loja-main" id="conteudo">
        <nav class="loja-passos" aria-label="Etapas da compra">
            <a href="loja_carrinho.php"><span>1</span> Carrinho</a>
            <span class="is-atual" aria-current="step"><span>2</span> Seus dados</span>
            <span><span>3</span> Confirmação</span>
        </nav>

        <h1 class="loja-pagina-titulo">Finalizar pedido</h1>

        <?php if ($erroGeral || $erros): ?>
            <div class="alert alert-error" role="alert">
                <?php echo icone('erro'); ?>
                <span><?php echo htmlspecialchars($erroGeral ?: 'Confira os campos destacados abaixo.'); ?></span>
            </div>
        <?php endif; ?>
        <?php foreach ($carrinho['avisos'] as $aviso): ?>
            <div class="alert alert-warning" role="alert"><?php echo icone('alerta'); ?> <span><?php echo htmlspecialchars($aviso); ?></span></div>
        <?php endforeach; ?>

        <div class="loja-checkout">
            <form method="POST" action="loja_finalizar.php" class="loja-form" novalidate id="formFinalizar">
                <?php echo csrf_campo(); ?>
                <div class="loja-isca" aria-hidden="true">
                    <label for="site">Não preencha este campo</label>
                    <input type="text" id="site" name="site" tabindex="-1" autocomplete="off">
                </div>

                <fieldset class="loja-bloco">
                    <legend><?php echo icone('usuario'); ?> Seus dados</legend>
                    <div class="form-grid">
                        <?php echo $campo('nome', 'Nome completo', 'type="text" maxlength="100" autocomplete="name"'); ?>
                        <?php echo $campo('email', 'Email', 'type="email" maxlength="100" autocomplete="email" inputmode="email"'); ?>
                        <?php echo $campo('telefone', 'Telefone / WhatsApp', 'type="tel" maxlength="15" autocomplete="tel" placeholder="(11) 91234-5678" data-mascara-telefone'); ?>
                        <?php echo $campo('cpf_cnpj', 'CPF ou CNPJ', 'type="text" maxlength="18" inputmode="numeric" data-mascara-documento placeholder="Opcional"', false); ?>
                    </div>
                    <p class="field-help">Já comprou com a gente? Use o mesmo email: o pedido fica junto do seu cadastro.</p>
                </fieldset>

                <fieldset class="loja-bloco">
                    <legend><?php echo icone('caminhao'); ?> Entrega</legend>
                    <div class="loja-opcoes" role="radiogroup" aria-label="Como você quer receber">
                        <label class="loja-opcao">
                            <input type="radio" name="entrega" value="ENTREGA" <?php echo $valores['entrega'] !== 'RETIRADA' ? 'checked' : ''; ?> data-entrega>
                            <span><strong>Receber em casa</strong><small>A loja combina o frete e o prazo com você</small></span>
                        </label>
                        <label class="loja-opcao">
                            <input type="radio" name="entrega" value="RETIRADA" <?php echo $valores['entrega'] === 'RETIRADA' ? 'checked' : ''; ?> data-entrega>
                            <span><strong>Retirar na loja</strong><small>Sem custo de entrega</small></span>
                        </label>
                    </div>
                    <div class="form-grid" data-endereco-entrega<?php echo $valores['entrega'] === 'RETIRADA' ? ' hidden' : ''; ?>>
                        <?php // A mensagem da busca do CEP fica dentro do próprio campo, para não ocupar uma célula da grade
                        echo preg_replace('#</div>$#', '<p class="cep-status" data-cep-status aria-live="polite"></p></div>',
                            $campo('cep', 'CEP', 'type="text" maxlength="9" inputmode="numeric" autocomplete="postal-code" placeholder="00000-000" data-cep')); ?>
                        <?php echo $campo('logradouro', 'Endereço', 'type="text" maxlength="150" autocomplete="address-line1" data-endereco="logradouro"'); ?>
                        <?php echo $campo('numero', 'Número', 'type="text" maxlength="20"'); ?>
                        <?php echo $campo('complemento', 'Complemento', 'type="text" maxlength="100" placeholder="Apto, bloco… (opcional)" data-endereco="complemento"', false); ?>
                        <?php echo $campo('bairro', 'Bairro', 'type="text" maxlength="100" data-endereco="bairro"'); ?>
                        <?php echo $campo('cidade', 'Cidade', 'type="text" maxlength="100" autocomplete="address-level2" data-endereco="localidade"'); ?>
                        <div class="form-group<?php echo isset($erros['uf']) ? ' has-error' : ''; ?>">
                            <label for="uf">Estado <span class="required" aria-hidden="true">*</span></label>
                            <select id="uf" name="uf" data-endereco="uf" autocomplete="address-level1">
                                <option value="">UF</option>
                                <?php foreach (ufs() as $uf): ?>
                                    <option value="<?php echo $uf; ?>"<?php echo $uf === $valores['uf'] ? ' selected' : ''; ?>><?php echo $uf; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['uf'] ?? ''); ?></span></small>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="loja-bloco">
                    <legend><?php echo icone('cartao'); ?> Pagamento</legend>
                    <div class="loja-opcoes loja-opcoes-pagamento<?php echo isset($erros['forma_pagamento']) ? ' has-error' : ''; ?>" role="radiogroup" aria-label="Forma de pagamento">
                        <?php foreach ($formas as $valor => $rotulo): ?>
                            <label class="loja-opcao loja-opcao-compacta">
                                <input type="radio" name="forma_pagamento" value="<?php echo $valor; ?>" <?php echo $valor === $valores['forma_pagamento'] ? 'checked' : ''; ?>>
                                <span><strong><?php echo $rotulo; ?></strong></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($erros['forma_pagamento'])): ?>
                        <p class="loja-erro-texto"><?php echo icone('erro'); ?> <?php echo htmlspecialchars($erros['forma_pagamento']); ?></p>
                    <?php endif; ?>
                    <p class="field-help">Nada é cobrado agora. A loja confirma o pedido e envia as instruções de pagamento.</p>
                </fieldset>

                <div class="form-group">
                    <label for="observacao">Observações</label>
                    <textarea id="observacao" name="observacao" maxlength="255" rows="2" placeholder="Algum recado para a loja? (opcional)"><?php echo htmlspecialchars($valores['observacao']); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-bloco" data-enviar-pedido><?php echo icone('check'); ?> Enviar pedido · <?php echo moeda($carrinho['total']); ?></button>
            </form>

            <aside class="loja-resumo" aria-labelledby="tituloResumo">
                <h2 id="tituloResumo">Seu pedido</h2>
                <ul class="loja-resumo-itens">
                    <?php foreach ($carrinho['linhas'] as $linha): ?>
                        <li>
                            <?php echo foto_produto($linha, 'foto-mini'); ?>
                            <span><?php echo htmlspecialchars($linha['NOME']); ?><small><?php echo (int)$linha['QUANTIDADE']; ?> × <?php echo moeda((float)$linha['PRECO']); ?></small></span>
                            <strong><?php echo moeda((float)$linha['SUBTOTAL']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl>
                    <div class="loja-resumo-total"><dt>Total</dt><dd><?php echo moeda($carrinho['total']); ?></dd></div>
                </dl>
                <a href="loja_carrinho.php" class="text-link">Alterar carrinho</a>
            </aside>
        </div>
    </main>
    <script src="js/endereco.js"></script>
<?php loja_rodape($loja); ?>
