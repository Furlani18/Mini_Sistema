<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Configurações do sistema (tabela configuracoes) e backup do banco.
// Somente administradores.

exigir_admin();

$erros = [];
$toasts = [];
$meta = config_ler($pdo, 'meta_mensal');
$valorMeta = $meta !== null && (float)$meta > 0 ? number_format((float)$meta, 2, ',', '.') : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valorMeta = trim((string)($_POST['meta_mensal'] ?? ''));
    $nova = $valorMeta === '' ? null : converter_moeda($valorMeta);
    if ($valorMeta !== '' && ($nova === null || $nova > VALOR_MAXIMO)) {
        $erros['meta_mensal'] = 'Informe um valor válido, por exemplo 50.000,00, ou deixe em branco para não usar meta.';
    } else {
        config_gravar($pdo, 'meta_mensal', $nova === null || $nova <= 0 ? null : (string)$nova);
        registrar_atividade($pdo, 'ALTEROU', $nova ? 'Definiu a meta mensal de vendas em ' . moeda($nova) : 'Removeu a meta mensal de vendas', 'sistema');
        flash('success', $nova ? 'Meta mensal salva: ' . moeda($nova) . '.' : 'Meta mensal removida.');
        header('Location: configuracoes.php');
        exit;
    }
}

// Tamanho aproximado do banco, para o card do backup
$tamanho = (float)$pdo->query(
    'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
)->fetchColumn();
$ultimoBackup = $pdo->query("SELECT CRIADO_EM, USUARIO_NOME FROM atividades WHERE ACAO = 'BACKUP' ORDER BY ID DESC LIMIT 1")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Configurações' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <h2 class="page-title"><?php echo icone('engrenagem'); ?> Configurações</h2>

            <div class="paineis paineis-iguais">
                <section class="form-container">
                    <h3 class="section-title"><?php echo icone('alvo'); ?> Meta de vendas</h3>
                    <p class="text-muted">O painel mostra quanto já foi vendido no mês em relação à meta e a projeção de fechamento.</p>
                    <form method="POST" action="" novalidate>
                        <?php echo csrf_campo(); ?>
                        <div class="form-group<?php echo isset($erros['meta_mensal']) ? ' has-error' : ''; ?>">
                            <label for="meta_mensal">Meta mensal (R$)</label>
                            <input type="text" id="meta_mensal" name="meta_mensal" inputmode="numeric" placeholder="0,00" data-mascara-moeda
                                   autocomplete="off" value="<?php echo htmlspecialchars($valorMeta); ?>" aria-describedby="meta-ajuda">
                            <small class="field-help" id="meta-ajuda">Faturamento esperado por mês (pedidos abertos, pagos e enviados). Em branco = sem meta.</small>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['meta_mensal'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Salvar meta</button>
                        </div>
                    </form>
                </section>

                <section class="form-container">
                    <h3 class="section-title"><?php echo icone('banco'); ?> Backup do banco de dados</h3>
                    <p class="text-muted">Baixa um arquivo <code>.sql</code> com todas as tabelas e dados
                        (cerca de <?php echo number_format($tamanho / 1024, 0, ',', '.'); ?> KB no banco). Guarde-o fora deste computador.</p>
                    <p class="text-muted">
                        <?php if ($ultimoBackup): ?>
                            Último backup: <?php echo date('d/m/Y H:i', strtotime($ultimoBackup['CRIADO_EM'])); ?> por <?php echo htmlspecialchars((string)$ultimoBackup['USUARIO_NOME']); ?>.
                        <?php else: ?>
                            Nenhum backup feito pelo sistema ainda.
                        <?php endif; ?>
                    </p>
                    <form method="POST" action="backup.php">
                        <?php echo csrf_campo(); ?>
                        <button type="submit" class="btn btn-primary"><?php echo icone('download'); ?> Baixar backup agora</button>
                    </form>
                    <p class="field-help">Para restaurar: <code>mysql -u root mini_sistema &lt; backup.sql</code> (substitui as tabelas atuais).</p>
                </section>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/moeda.js"></script>
</body>
</html>
