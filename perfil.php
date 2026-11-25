<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// "Meu perfil": o usuário logado altera os próprios dados e a própria senha.
// São dois formulários na mesma página; o campo oculto "acao" diz qual foi enviado.
// Trocar a senha exige a senha atual (evita que alguém com o computador destravado a troque).

$meuId = (int)$_SESSION['usuario_id'];
$stmt = $pdo->prepare('SELECT NOME, EMAIL, TELEFONE, SENHA, CRIADO_EM FROM usuarios WHERE ID = ?');
$stmt->execute([$meuId]);
$eu = $stmt->fetch();

if (!$eu) {
    // O usuário foi apagado por outra pessoa enquanto estava logado
    header('Location: logout.php');
    exit;
}

$toasts = [];
$errosDados = [];
$errosSenha = [];
$valores = ['nome' => $eu['NOME'], 'email' => $eu['EMAIL'], 'telefone' => $eu['TELEFONE'] ?? ''];
$acao = $_POST['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'dados') {
    $valores = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
    ];
    $errosDados = validar_pessoa($valores['nome'], $valores['email'], $valores['telefone'], 'usuário');

    if (!$errosDados) {
        try {
            $stmt = $pdo->prepare('UPDATE usuarios SET NOME = ?, EMAIL = ?, TELEFONE = ? WHERE ID = ?');
            $stmt->execute([$valores['nome'], $valores['email'], $valores['telefone'] ?: null, $meuId]);
            $_SESSION['usuario_nome'] = $valores['nome'];
            $_SESSION['usuario_email'] = $valores['email'];
            registrar_atividade($pdo, 'ALTEROU', 'Atualizou os próprios dados', 'usuario', $meuId);
            $toasts[] = ['tipo' => 'success', 'mensagem' => 'Seus dados foram atualizados!'];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $errosDados['email'] = 'Já existe outro usuário com esse email.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $acao === 'senha') {
    $atual = trim($_POST['senha_atual'] ?? '');
    $nova = trim($_POST['senha'] ?? '');
    $confirmacao = trim($_POST['senha_confirmacao'] ?? '');

    if (!password_verify($atual, $eu['SENHA'])) {
        $errosSenha['senha_atual'] = 'Senha atual incorreta.';
    }
    $errosSenha += validar_senha($nova, $confirmacao, true);
    if (!$errosSenha && $nova === $atual) {
        $errosSenha['senha'] = 'A nova senha deve ser diferente da atual.';
    }

    if (!$errosSenha) {
        $stmt = $pdo->prepare('UPDATE usuarios SET SENHA = ?, TROCAR_SENHA = 0 WHERE ID = ?');
        $stmt->execute([password_hash($nova, PASSWORD_DEFAULT), $meuId]);
        session_regenerate_id(true);
        registrar_atividade($pdo, 'SENHA', 'Trocou a própria senha', 'usuario', $meuId);
        if ($_SESSION['trocar_senha']) {
            // Era a troca obrigatória: libera o sistema e vai para o painel
            $_SESSION['trocar_senha'] = false;
            flash('success', 'Senha definida! Bem-vindo ao sistema.');
            header('Location: index.php');
            exit;
        }
        $toasts[] = ['tipo' => 'success', 'mensagem' => 'Senha alterada com sucesso!'];
    }
}

$pedidosRegistrados = $pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE USUARIO_ID = ?');
$pedidosRegistrados->execute([$meuId]);
$pedidosRegistrados = (int)$pedidosRegistrados->fetchColumn();

$camposDados = [
    'nome' => [
        'rotulo' => 'Nome', 'obrigatorio' => true,
        'atributos' => 'type="text" maxlength="100" autocomplete="name" minlength="3"',
        'ajuda' => 'Aparece no menu e nos pedidos que você registrar.',
    ],
    'email' => [
        'rotulo' => 'Email', 'obrigatorio' => true,
        'atributos' => 'type="email" maxlength="100" autocomplete="email"',
        'ajuda' => 'É o seu login. Se mudar, use o novo email na próxima entrada.',
    ],
    'telefone' => [
        'rotulo' => 'Telefone', 'obrigatorio' => false,
        'atributos' => 'type="tel" placeholder="(11) 99999-9999" inputmode="numeric" autocomplete="tel"',
        'ajuda' => 'Opcional.',
    ],
];
$camposSenha = [
    'senha_atual' => [
        'rotulo' => 'Senha atual', 'obrigatorio' => true, 'sem_valor' => true,
        'atributos' => 'type="password" autocomplete="current-password"',
        'ajuda' => 'Confirme que é você.',
    ],
    'senha' => [
        'rotulo' => 'Nova senha', 'obrigatorio' => true, 'sem_valor' => true,
        'atributos' => 'type="password" autocomplete="new-password" minlength="' . SENHA_MINIMA . '"',
        'ajuda' => 'Mínimo de ' . SENHA_MINIMA . ' caracteres.',
    ],
    'senha_confirmacao' => [
        'rotulo' => 'Confirmar nova senha', 'obrigatorio' => true, 'sem_valor' => true,
        'atributos' => 'type="password" autocomplete="new-password"',
        'ajuda' => 'Digite a nova senha novamente.',
    ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Meu perfil' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <section class="perfil-cabecalho">
                <span class="avatar avatar-lg" aria-hidden="true"><?php echo htmlspecialchars(iniciais($_SESSION['usuario_nome'])); ?></span>
                <div class="perfil-info">
                    <h2><?php echo htmlspecialchars($_SESSION['usuario_nome']); ?></h2>
                    <ul class="perfil-contatos">
                        <li><?php echo icone('email'); ?> <?php echo htmlspecialchars($_SESSION['usuario_email'] ?? $eu['EMAIL']); ?></li>
                        <li><?php echo icone('calendario'); ?> Usuário desde <?php echo date('d/m/Y', strtotime($eu['CRIADO_EM'])); ?></li>
                        <li><?php echo icone('carrinho'); ?> <?php echo $pedidosRegistrados === 1 ? '1 pedido registrado' : "$pedidosRegistrados pedidos registrados"; ?></li>
                    </ul>
                </div>
            </section>

            <?php if ($_SESSION['trocar_senha']): ?>
                <div class="alert alert-warning" role="alert">
                    <?php echo icone('cadeado'); ?>
                    <span>Sua senha foi definida por um administrador. <strong>Escolha uma senha nova</strong> em "Alterar senha" para liberar o sistema.
                        A "senha atual" é a que o administrador passou para você.</span>
                </div>
            <?php endif; ?>

            <div class="paineis paineis-iguais">
                <section class="form-container">
                    <h2 class="page-title"><?php echo icone('usuario'); ?> Meus dados</h2>
                    <?php if ($errosDados): ?>
                        <div class="alert alert-error" role="alert"><?php echo icone('erro'); ?><span>Corrija os campos destacados.</span></div>
                    <?php endif; ?>
                    <form method="POST" action="" data-validar data-entidade="usuário" novalidate>
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="dados">
                        <?php $campos = $camposDados; $erros = $errosDados; include __DIR__ . '/form_campos.php'; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> Salvar Dados</button>
                        </div>
                    </form>
                </section>

                <section class="form-container">
                    <h2 class="page-title"><?php echo icone('cadeado'); ?> Alterar senha</h2>
                    <?php if ($errosSenha): ?>
                        <div class="alert alert-error" role="alert"><?php echo icone('erro'); ?><span>Não foi possível alterar a senha.</span></div>
                    <?php endif; ?>
                    <form method="POST" action="" data-validar data-entidade="usuário" novalidate>
                        <?php echo csrf_campo(); ?>
                        <input type="hidden" name="acao" value="senha">
                        <?php $campos = $camposSenha; $erros = $errosSenha; include __DIR__ . '/form_campos.php'; ?>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><?php echo icone('cadeado'); ?> Alterar Senha</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/formulario.js"></script>
</body>
</html>
