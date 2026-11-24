<?php
// Campos do formulário de cliente, usados no cadastro e na atualização.
// Três seções: dados principais, endereço (com busca automática pelo CEP) e observações.
//
// Variáveis esperadas:
//   $valores = cliente_vazio() ou cliente_do_banco(...)   (ver validacao.php)
//   $erros   = ['campo' => 'mensagem'];  (vazio quando não há erros)

require_once __DIR__ . '/validacao.php';

$secoesCliente = [
    'Dados principais' => [
        'nome' => [
            'rotulo' => 'Nome Completo', 'obrigatorio' => true, 'classe' => 'campo-duplo',
            'atributos' => 'type="text" maxlength="100" placeholder="Digite o nome completo ou razão social" autocomplete="name" minlength="3"',
            'ajuda' => 'Nome e sobrenome (ou razão social), com pelo menos 3 caracteres.',
        ],
        'email' => [
            'rotulo' => 'Email', 'obrigatorio' => true, 'classe' => 'campo-duplo',
            'atributos' => 'type="email" maxlength="100" placeholder="nome@empresa.com" autocomplete="email"',
            'ajuda' => 'Usaremos este email para identificar o cadastro.',
        ],
        'telefone' => [
            'rotulo' => 'Telefone', 'obrigatorio' => false,
            'atributos' => 'type="tel" placeholder="(11) 99999-9999" inputmode="numeric" autocomplete="tel"',
            'ajuda' => 'Opcional. A formatação é aplicada automaticamente.',
        ],
        'cpf_cnpj' => [
            'rotulo' => 'CPF ou CNPJ', 'obrigatorio' => false,
            'atributos' => 'type="text" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" maxlength="18" data-mascara-documento',
            'ajuda' => 'Opcional. Os dígitos verificadores são conferidos.',
        ],
    ],
    'Endereço' => [
        'cep' => [
            'rotulo' => 'CEP', 'obrigatorio' => false,
            'atributos' => 'type="text" inputmode="numeric" placeholder="00000-000" autocomplete="postal-code" maxlength="9" data-cep',
            'ajuda' => 'Digite o CEP para preencher o endereço automaticamente.',
        ],
        'logradouro' => [
            'rotulo' => 'Logradouro', 'obrigatorio' => false, 'classe' => 'campo-duplo',
            'atributos' => 'type="text" maxlength="150" placeholder="Rua, avenida…" autocomplete="address-line1" data-endereco="logradouro"',
            'ajuda' => '',
        ],
        'numero' => [
            'rotulo' => 'Número', 'obrigatorio' => false,
            'atributos' => 'type="text" maxlength="20" placeholder="123" autocomplete="off"',
            'ajuda' => '',
        ],
        'complemento' => [
            'rotulo' => 'Complemento', 'obrigatorio' => false,
            'atributos' => 'type="text" maxlength="100" placeholder="Apto, bloco, sala…" autocomplete="address-line2" data-endereco="complemento"',
            'ajuda' => '',
        ],
        'bairro' => [
            'rotulo' => 'Bairro', 'obrigatorio' => false,
            'atributos' => 'type="text" maxlength="100" autocomplete="off" data-endereco="bairro"',
            'ajuda' => '',
        ],
        'cidade' => [
            'rotulo' => 'Cidade', 'obrigatorio' => false,
            'atributos' => 'type="text" maxlength="100" autocomplete="address-level2" data-endereco="localidade"',
            'ajuda' => '',
        ],
        'uf' => [
            'rotulo' => 'Estado (UF)', 'obrigatorio' => false, 'tipo' => 'select',
            'opcoes' => array_combine(ufs(), ufs()),
            'atributos' => 'autocomplete="address-level1" data-endereco="uf"',
            'ajuda' => '',
        ],
    ],
    'Observações' => [
        'observacoes' => [
            'rotulo' => 'Anotações sobre o cliente', 'obrigatorio' => false, 'tipo' => 'textarea', 'classe' => 'campo-largo',
            'atributos' => 'rows="3" maxlength="2000" placeholder="Preferências, combinados, melhor horário para contato…"',
            'ajuda' => 'Opcional. Aparece na ficha do cliente.',
        ],
    ],
];
?>
<?php foreach ($secoesCliente as $tituloSecao => $campos): ?>
    <fieldset class="form-secao">
        <legend><?php echo $tituloSecao; ?></legend>
        <div class="form-grid form-grid-4">
            <?php include __DIR__ . '/form_campos.php'; ?>
        </div>
        <?php if ($tituloSecao === 'Endereço'): ?>
            <p class="cep-status" data-cep-status aria-live="polite"></p>
        <?php endif; ?>
    </fieldset>
<?php endforeach; ?>
