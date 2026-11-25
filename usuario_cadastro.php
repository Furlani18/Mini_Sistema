<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// Cadastro e edição de usuário (mesmo formulário):
//   usuario_cadastro.php         → novo usuário (senha obrigatória)
//   usuario_cadastro.php?id=ID   → editar (senha em branco = mantém a atual)
// O próprio usuário logado edita os seus dados em perfil.php (que pede a senha atual).
// Somente administradores. O sistema nunca fica sem pelo menos um administrador.

exigir_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id > 0 && $id === (int)$_SESSION['usuario_id']) {
    header('Location: perfil.php');
    exit;
}

$erros = [];
$toasts = [];
$vazio = ['nome' => '', 'email' => '', 'telefone' => '', 'perfil' => 'OPERADOR'];
$valores = $vazio;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT NOME, EMAIL, TELEFONE, PERFIL FROM usuarios WHERE ID = ?');
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        flash('error', 'Usuário não encontrado.');
        header('Location: usuarios.php');
        exit;
    }
    $valores = ['nome' => $usuario['NOME'], 'email' => $usuario['EMAIL'], 'telefone' => $usuario['TELEFONE'] ?? '', 'perfil' => $usuario['PERFIL']];
}
$editando = $id > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valores = [
        'nome'     => trim($_POST['nome'] ?? ''),
        'email'    => trim($_POST['email'] ?? ''),
        'telefone' => trim($_POST['telefone'] ?? ''),
        'perfil'   => (string)($_POST['perfil'] ?? ''),
    ];
    $senha = trim($_POST['senha'] ?? '');
    $confirmacao = trim($_POST['senha_confirmacao'] ?? '');
    // Senha definida pelo administrador: por padrão, o usuário precisa trocá-la no próximo acesso
    $exigirTroca = isset($_POST['trocar_senha']);

    $erros = validar_pessoa($valores['nome'], $valores['email'], $valores['telefone'], 'usuário')
        + validar_senha($senha, $confirmacao, !$editando);

    if (!isset(perfis()[$valores['perfil']])) {
        $erros['perfil'] = 'Escolha o perfil do usuário.';
    } elseif ($editando && $usuario['PERFIL'] === 'ADMIN' && $valores['perfil'] !== 'ADMIN'
        && (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE PERFIL = 'ADMIN'")->fetchColumn() <= 1) {
        $erros['perfil'] = 'Este é o único administrador. Promova outro usuário antes de mudar este.';
    }

    if (!$erros) {
        $dados = [$valores['nome'], $valores['email'], $valores['telefone'] ?: null, $valores['perfil']];
        try {
            if ($editando) {
                $sql = 'UPDATE usuarios SET NOME = ?, EMAIL = ?, TELEFONE = ?, PERFIL = ?';
                if ($senha !== '') {
                    $sql .= ', SENHA = ?, TROCAR_SENHA = ?';
                    $dados[] = password_hash($senha, PASSWORD_DEFAULT);
                    $dados[] = $exigirTroca ? 1 : 0;
                }
                $stmt = $pdo->prepare($sql . ' WHERE ID = ?');
                $stmt->execute([...$dados, $id]);
                registrar_atividade($pdo, 'ALTEROU', "Alterou o usuário '{$valores['nome']}'"
                    . ($senha !== '' ? ' (redefiniu a senha' . ($exigirTroca ? ', troca obrigatória' : '') . ')' : ''), 'usuario', $id);
                $usuario['PERFIL'] = $valores['perfil'];
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Usuário atualizado'
                    . ($senha !== '' ? ($exigirTroca ? ' (senha redefinida: ele vai escolher uma nova ao entrar)' : ' (senha alterada)') : '') . '!'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO usuarios (NOME, EMAIL, TELEFONE, PERFIL, SENHA, TROCAR_SENHA) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute([...$dados, password_hash($senha, PASSWORD_DEFAULT), $exigirTroca ? 1 : 0]);
                registrar_atividade($pdo, 'CRIOU', "Criou o usuário '{$valores['nome']}' (" . perfis()[$valores['perfil']] . ')',
                    'usuario', (int)$pdo->lastInsertId());
                flash('success', "Usuário '{$valores['nome']}' criado. Ele já pode entrar com o email e a senha informados"
                    . ($exigirTroca ? ' e vai escolher uma senha própria no primeiro acesso.' : '.'));
                header('Location: usuarios.php');
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $erros['email'] = 'Já existe um usuário com esse email.';
        }
    }
}

$campos = [
    'nome' => [
        'rotulo' => 'Nome', 'obrigatorio' => true,
        'atributos' => 'type="text" maxlength="100" placeholder="Nome completo" autocomplete="off" minlength="3"',
        'ajuda' => 'Aparece no menu e nos pedidos que o usuário registrar.',
    ],
    'email' => [
        'rotulo' => 'Email', 'obrigatorio' => true,
        'atributos' => 'type="email" maxlength="100" placeholder="nome@empresa.com" autocomplete="off"',
        'ajuda' => 'Usado para entrar no sistema. Não pode repetir.',
    ],
    'telefone' => [
        'rotulo' => 'Telefone', 'obrigatorio' => false,
        'atributos' => 'type="tel" placeholder="(11) 99999-9999" inputmode="numeric" autocomplete="off"',
        'ajuda' => 'Opcional.',
    ],
    'senha' => [
        'rotulo' => $editando ? 'Nova senha' : 'Senha', 'obrigatorio' => !$editando, 'sem_valor' => true,
        'atributos' => 'type="password" autocomplete="new-password" minlength="' . SENHA_MINIMA . '"',
        'ajuda' => $editando ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de ' . SENHA_MINIMA . ' caracteres.',
    ],
    'senha_confirmacao' => [
        'rotulo' => 'Confirmar senha', 'obrigatorio' => !$editando, 'sem_valor' => true,
        'atributos' => 'type="password" autocomplete="new-password"',
        'ajuda' => 'Digite a mesma senha novamente.',
    ],
];
$titulo = $editando ? 'Editar Usuário' : 'Novo Usuário';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Usuários' => 'usuarios.php', $titulo => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title">
                    <?php echo icone($editando ? 'editar' : 'usuario-mais'); ?>
                    <?php echo $titulo; ?>
                    <?php if ($editando): ?><span class="page-title-id">#<?php echo $id; ?></span><?php endif; ?>
                </h2>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo icone('erro'); ?>
                        <span>Não foi possível salvar. Corrija os campos destacados abaixo.</span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" data-validar data-entidade="usuário" novalidate>
                    <?php echo csrf_campo(); ?>
                    <div class="form-grid">
                        <?php include __DIR__ . '/form_campos.php'; ?>
                        <div class="form-group<?php echo isset($erros['perfil']) ? ' has-error' : ''; ?>">
                            <label for="perfil">Perfil de acesso <span class="required" aria-hidden="true">*</span></label>
                            <select id="perfil" name="perfil" aria-describedby="perfil-ajuda perfil-erro">
                                <?php foreach (perfis() as $valor => $rotulo): ?>
                                    <option value="<?php echo $valor; ?>" <?php echo $valor === $valores['perfil'] ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="field-help" id="perfil-ajuda">Operador não apaga registros nem gerencia usuários.</small>
                            <small class="field-error" id="perfil-erro"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erros['perfil'] ?? ''); ?></span></small>
                        </div>
                    </div>

                    <div class="senha-temporaria">
                        <button type="button" class="btn btn-outline btn-sm" data-gerar-senha><?php echo icone('chave'); ?> Gerar senha temporária</button>
                        <output class="senha-gerada" data-senha-gerada aria-live="polite"></output>
                        <label class="checkbox">
                            <input type="checkbox" name="trocar_senha" value="1" <?php echo $_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['trocar_senha']) ? 'checked' : ''; ?>>
                            <span>Exigir que <?php echo $editando ? 'ele' : 'o usuário'; ?> troque a senha no próximo acesso</span>
                        </label>
                        <small class="field-help"><?php echo $editando
                            ? 'Para redefinir a senha (ex.: o usuário esqueceu), gere uma senha temporária, salve e passe a senha a ele. Só vale se uma nova senha for informada.'
                            : 'Recomendado: você passa a senha temporária e ele escolhe uma só dele ao entrar.'; ?></small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editando ? 'Salvar Alterações' : 'Criar Usuário'; ?></button>
                        <a href="usuarios.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Usuários</a>
                        <?php if ($editando): ?>
                            <a href="usuario_apagar.php?id=<?php echo $id; ?>" class="btn btn-danger-outline"
                               data-confirmar-exclusao="o usuário <?php echo htmlspecialchars($valores['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar Usuário</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/formulario.js"></script>
    <script>
        // Gera uma senha temporária aleatória (sem caracteres parecidos, como 0/O e 1/l)
        // e preenche os dois campos de senha, mostrando-a para o administrador anotar.
        (function () {
            var botao = document.querySelector('[data-gerar-senha]');
            if (!botao || !window.crypto) return;
            botao.addEventListener('click', function () {
                var letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                var aleatorios = new Uint32Array(10);
                crypto.getRandomValues(aleatorios);
                var senha = Array.from(aleatorios, function (n) { return letras[n % letras.length]; }).join('');
                document.getElementById('senha').value = senha;
                document.getElementById('senha_confirmacao').value = senha;
                document.querySelector('[data-senha-gerada]').textContent = senha;
            });
        })();
    </script>
</body>
</html>
