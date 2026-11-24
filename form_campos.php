<?php
// Renderiza campos de formulário com rótulo, ajuda e mensagem de erro.
// Usado pelos formulários de cliente, usuário e "Meu perfil".
//
// Variáveis esperadas:
//   $campos  = ['nome' => ['rotulo' => '', 'obrigatorio' => true, 'atributos' => 'type="text"', 'ajuda' => ''], ...]
//              (opcional por campo: 'sem_valor' => true para nunca reexibir o valor, como em senhas)
//   $valores = ['nome' => '', ...];
//   $erros   = ['campo' => 'mensagem'];  (vazio quando não há erros)

require_once __DIR__ . '/ui.php';
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
            value="<?php echo empty($c['sem_valor']) ? htmlspecialchars($valores[$campo] ?? '') : ''; ?>"
            aria-describedby="<?php echo $campo; ?>-ajuda <?php echo $campo; ?>-erro"
            <?php echo $c['atributos']; ?>
            <?php echo $c['obrigatorio'] ? 'required' : ''; ?>
            <?php echo $erro ? 'aria-invalid="true"' : ''; ?>
        >
        <small class="field-help" id="<?php echo $campo; ?>-ajuda"><?php echo $c['ajuda']; ?></small>
        <small class="field-error" id="<?php echo $campo; ?>-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erro); ?></span></small>
    </div>
<?php endforeach; ?>
