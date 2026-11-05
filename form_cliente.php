<?php
// Campos do formulário de cliente, usados no cadastro e na atualização.
//
// Variáveis esperadas:
//   $valores = ['nome' => '', 'email' => '', 'telefone' => ''];
//   $erros   = ['campo' => 'mensagem'];  (vazio quando não há erros)

$campos = [
    'nome' => [
        'rotulo'      => 'Nome Completo',
        'obrigatorio' => true,
        'atributos'   => 'type="text" placeholder="Digite o nome completo" autocomplete="name" minlength="3"',
        'ajuda'       => 'Informe nome e sobrenome, com pelo menos 3 caracteres.',
    ],
    'email' => [
        'rotulo'      => 'Email',
        'obrigatorio' => true,
        'atributos'   => 'type="email" placeholder="nome@empresa.com" autocomplete="email"',
        'ajuda'       => 'Usaremos este email para identificar o cadastro.',
    ],
    'telefone' => [
        'rotulo'      => 'Telefone',
        'obrigatorio' => false,
        'atributos'   => 'type="tel" placeholder="(11) 99999-9999" inputmode="numeric" autocomplete="tel"',
        'ajuda'       => 'Opcional. Digite apenas números; a formatação é aplicada automaticamente.',
    ],
];
?>
<?php foreach ($campos as $campo => $c): ?>
    <?php $erro = $erros[$campo] ?? ''; ?>
    <div class="form-group<?php echo $erro ? ' has-error' : ''; ?>">
        <label for="<?php echo $campo; ?>">
            <?php echo $c['rotulo']; ?>
            <?php if ($c['obrigatorio']): ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <input
            id="<?php echo $campo; ?>"
            name="<?php echo $campo; ?>"
            value="<?php echo htmlspecialchars($valores[$campo] ?? ''); ?>"
            aria-describedby="<?php echo $campo; ?>-ajuda <?php echo $campo; ?>-erro"
            <?php echo $c['atributos']; ?>
            <?php echo $c['obrigatorio'] ? 'required' : ''; ?>
            <?php echo $erro ? 'aria-invalid="true"' : ''; ?>
        >
        <small class="field-help" id="<?php echo $campo; ?>-ajuda"><?php echo $c['ajuda']; ?></small>
        <small class="field-error" id="<?php echo $campo; ?>-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erro); ?></span></small>
    </div>
<?php endforeach; ?>
