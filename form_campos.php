<?php
// Renderiza campos de formulário com rótulo, ajuda e mensagem de erro.
// Usado pelos formulários de cliente, usuário e "Meu perfil".
//
// Variáveis esperadas:
//   $campos  = ['nome' => ['rotulo' => '', 'obrigatorio' => true, 'atributos' => 'type="text"', 'ajuda' => ''], ...]
//              Opcionais por campo:
//                'tipo'      => 'textarea' | 'select'   (padrão: <input>)
//                'opcoes'    => ['SP' => 'SP', ...]      (para select; inclui uma opção vazia)
//                'classe'    => 'campo-largo'            (classe extra no .form-group, ex.: ocupar a linha toda)
//                'sem_valor' => true                     (nunca reexibir o valor, como em senhas)
//   $valores = ['nome' => '', ...];
//   $erros   = ['campo' => 'mensagem'];  (vazio quando não há erros)

require_once __DIR__ . '/ui.php';
?>
<?php foreach ($campos as $campo => $c): ?>
    <?php
    $erro = $erros[$campo] ?? '';
    $valor = empty($c['sem_valor']) ? (string)($valores[$campo] ?? '') : '';
    $tipo = $c['tipo'] ?? 'input';
    $comuns = 'id="' . $campo . '" name="' . $campo . '" aria-describedby="' . $campo . '-ajuda ' . $campo . '-erro" '
        . ($c['atributos'] ?? '') . ($c['obrigatorio'] ? ' required' : '') . ($erro ? ' aria-invalid="true"' : '');
    ?>
    <div class="form-group<?php echo $erro ? ' has-error' : ''; ?><?php echo isset($c['classe']) ? ' ' . $c['classe'] : ''; ?>">
        <label for="<?php echo $campo; ?>">
            <?php echo $c['rotulo']; ?>
            <?php if ($c['obrigatorio']): ?><span class="required" aria-hidden="true">*</span><?php endif; ?>
        </label>
        <?php if ($tipo === 'textarea'): ?>
            <textarea <?php echo $comuns; ?>><?php echo htmlspecialchars($valor); ?></textarea>
        <?php elseif ($tipo === 'select'): ?>
            <select <?php echo $comuns; ?>>
                <option value="">—</option>
                <?php foreach ($c['opcoes'] as $opcao => $rotuloOpcao): ?>
                    <option value="<?php echo htmlspecialchars((string)$opcao); ?>" <?php echo (string)$opcao === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($rotuloOpcao); ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input <?php echo $comuns; ?> value="<?php echo htmlspecialchars($valor); ?>">
        <?php endif; ?>
        <small class="field-help" id="<?php echo $campo; ?>-ajuda"><?php echo $c['ajuda']; ?></small>
        <small class="field-error" id="<?php echo $campo; ?>-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erro); ?></span></small>
    </div>
<?php endforeach; ?>
