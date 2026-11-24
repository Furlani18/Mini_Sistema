<?php
// Rodapé compartilhado: rodapé visual, toasts e modal de confirmação de exclusão.
//
// Antes de incluir, a página pode definir:
//   $toasts = [['tipo' => 'success', 'mensagem' => '...']];  // mensagens desta página
// Mensagens guardadas com flash() (ex.: depois de um redirecionamento) também aparecem.

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/seguranca.php';

$toasts = array_merge(flash_pegar(), $toasts ?? []);
$iconesToast = ['success' => 'sucesso', 'error' => 'erro', 'warning' => 'alerta', 'info' => 'erro'];
?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> Sistema de Gerenciamento de Clientes. Todos os direitos reservados.</p>
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

<dialog class="modal" id="modalConfirmar" aria-labelledby="modalTitulo" aria-describedby="modalTexto">
    <div class="modal-box">
        <div class="modal-icon"><?php echo icone('alerta'); ?></div>
        <h2 id="modalTitulo">Confirmar exclusão</h2>
        <p id="modalTexto">
            Tem certeza que deseja apagar <strong data-modal-nome>este registro</strong>?
            Esta ação não pode ser desfeita.
        </p>
        <div class="modal-actions">
            <button type="button" class="btn btn-outline" data-modal-cancelar>Cancelar</button>
            <?php // A exclusão é feita por POST com token CSRF; o js/app.js define o "action" com o link clicado ?>
            <form method="POST" action="" data-modal-form>
                <?php echo csrf_campo(); ?>
                <button type="submit" class="btn btn-danger"><?php echo icone('lixeira'); ?> Apagar</button>
            </form>
        </div>
    </div>
</dialog>

<script src="js/app.js"></script>
