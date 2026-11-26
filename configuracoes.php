<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'loja_funcoes.php';

// Configurações do sistema (tabela configuracoes): meta de vendas, loja online e backup do banco.
// Somente administradores.

exigir_admin();

$erros = [];
$toasts = [];
$meta = config_ler($pdo, 'meta_mensal');
$valorMeta = $meta !== null && (float)$meta > 0 ? number_format((float)$meta, 2, ',', '.') : '';
$loja = loja_config($pdo);
$valoresLoja = ['ativa' => $loja['ativa'], 'nome' => $loja['nome'], 'slogan' => $loja['slogan'], 'whatsapp' => $loja['whatsapp']];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'loja') {
    $valoresLoja = [
        'ativa'    => isset($_POST['loja_ativa']),
        'nome'     => trim((string)($_POST['loja_nome'] ?? '')),
        'slogan'   => trim((string)($_POST['loja_slogan'] ?? '')),
        'whatsapp' => trim((string)($_POST['loja_whatsapp'] ?? '')),
    ];
    if (mb_strlen($valoresLoja['nome']) < 2 || mb_strlen($valoresLoja['nome']) > 60) {
        $erros['loja_nome'] = 'Informe o nome da loja (de 2 a 60 caracteres).';
    }
    if (mb_strlen($valoresLoja['slogan']) > 160) {
        $erros['loja_slogan'] = 'O slogan pode ter até 160 caracteres.';
    }
    if ($valoresLoja['whatsapp'] !== '' && whatsapp_link($valoresLoja['whatsapp']) === null) {
        $erros['loja_whatsapp'] = 'Informe o WhatsApp com DDD, por exemplo (11) 91234-5678.';
    }
    if (!$erros) {
        config_gravar($pdo, 'loja_ativa', $valoresLoja['ativa'] ? '1' : '0');
        config_gravar($pdo, 'loja_nome', $valoresLoja['nome']);
        config_gravar($pdo, 'loja_slogan', $valoresLoja['slogan'] !== '' ? $valoresLoja['slogan'] : null);
        config_gravar($pdo, 'loja_whatsapp', $valoresLoja['whatsapp'] !== '' ? $valoresLoja['whatsapp'] : null);
        registrar_atividade($pdo, 'ALTEROU', 'Alterou as configurações da loja online (' . ($valoresLoja['ativa'] ? 'aberta' : 'fechada') . ')', 'sistema');
        flash('success', $valoresLoja['ativa'] ? 'Configurações da loja salvas. A loja está aberta.' : 'Configurações salvas. A loja está fechada ao público.');
        header('Location: configuracoes.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                    <h3 class="section-title"><?php echo icone('loja'); ?> Loja online</h3>
                    <p class="text-muted">Vitrine pública onde os clientes montam o carrinho e enviam o pedido.
                        <a href="loja.php" class="text-link" target="_blank" rel="noopener">Abrir a loja</a></p>
                    <form method="POST" action="" novalidate>
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="loja">
                        <label class="checkbox-linha loja-aberta">
                            <input type="checkbox" name="loja_ativa" value="1" <?php echo $valoresLoja['ativa'] ? 'checked' : ''; ?>>
                            <span><strong>Loja aberta ao público</strong><small>Desmarcada, os visitantes veem um aviso de "fechado" e não conseguem comprar.</small></span>
                        </label>
                        <div class="form-group<?php echo isset($erros['loja_nome']) ? ' has-error' : ''; ?>">
                            <label for="loja_nome">Nome da loja <span class="required" aria-hidden="true">*</span></label>
                            <input type="text" id="loja_nome" name="loja_nome" maxlength="60" required value="<?php echo htmlspecialchars($valoresLoja['nome']); ?>">
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['loja_nome'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-group<?php echo isset($erros['loja_slogan']) ? ' has-error' : ''; ?>">
                            <label for="loja_slogan">Slogan</label>
                            <input type="text" id="loja_slogan" name="loja_slogan" maxlength="160" placeholder="Opcional. Aparece no banner da página inicial."
                                   value="<?php echo htmlspecialchars($valoresLoja['slogan']); ?>">
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['loja_slogan'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-group<?php echo isset($erros['loja_whatsapp']) ? ' has-error' : ''; ?>">
                            <label for="loja_whatsapp">WhatsApp da loja</label>
                            <input type="tel" id="loja_whatsapp" name="loja_whatsapp" maxlength="20" placeholder="(11) 91234-5678"
                                   value="<?php echo htmlspecialchars($valoresLoja['whatsapp']); ?>" aria-describedby="zap-ajuda">
                            <small class="field-help" id="zap-ajuda">Opcional. Mostra botões "Fale conosco" e permite ao cliente avisar a loja depois do pedido.</small>
                            <small class="field-error"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['loja_whatsapp'] ?? ''); ?></span></small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Salvar loja</button>
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
                    <p class="field-help">As fotos dos produtos ficam na pasta <code>uploads/produtos</code>: copie essa pasta junto com o backup.</p>
                </section>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/moeda.js"></script>
</body>
</html>
