<?php
declare(strict_types=1);

// Início comum das páginas da loja online (abertas ao público, sem login):
//  - conexão, sessão (o carrinho fica nela) e funções da loja;
//  - cabeçalhos de segurança;
//  - todo POST precisa do token CSRF (o mesmo mecanismo do sistema interno);
//  - loja fechada em Configurações → o público vê um aviso; a equipe logada vê a loja
//    normalmente, com uma faixa avisando que ela está fechada.

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/seguranca.php';
require_once __DIR__ . '/loja_funcoes.php';
require_once __DIR__ . '/loja_layout.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$loja = loja_config($pdo);
$equipeLogada = isset($_SESSION['usuario_id']);

if (!$loja['ativa'] && !$equipeLogada) {
    http_response_code(503);
    loja_cabecalho($loja, 'Loja fechada', false);
    ?>
    <main class="loja-main">
        <section class="loja-vazio">
            <?php echo icone('loja'); ?>
            <h1>Estamos fechados no momento</h1>
            <p>A loja online volta em breve.<?php if (whatsapp_link($loja['whatsapp'])): ?> Enquanto isso, fale com a gente pelo WhatsApp.<?php endif; ?></p>
            <?php if ($zap = whatsapp_link($loja['whatsapp'], 'Olá! Gostaria de fazer um pedido.')): ?>
                <a href="<?php echo htmlspecialchars($zap); ?>" class="btn btn-whatsapp" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> Chamar no WhatsApp</a>
            <?php endif; ?>
        </section>
    </main>
    <?php
    loja_rodape($loja);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valido()) {
    flash('error', 'A página ficou aberta por muito tempo. Tente de novo.');
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}
