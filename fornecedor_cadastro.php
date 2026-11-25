<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// Cadastro e edição de fornecedor (mesmo formulário):
//   fornecedor_cadastro.php         → novo fornecedor
//   fornecedor_cadastro.php?id=ID   → editar + histórico de compras (entradas de estoque)

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editando = $id > 0;
$erros = [];
$toasts = [];
$campos = ['nome', 'cpf_cnpj', 'contato', 'email', 'telefone', 'observacoes'];
$valores = array_fill_keys($campos, '');

if ($editando) {
    $stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE ID = ?');
    $stmt->execute([$id]);
    $fornecedor = $stmt->fetch();
    if (!$fornecedor) {
        flash('error', 'Fornecedor não encontrado.');
        header('Location: fornecedores.php');
        exit;
    }
    foreach ($campos as $campo) {
        $valores[$campo] = (string)($fornecedor[strtoupper($campo)] ?? '');
    }
    $valores['cpf_cnpj'] = formatar_documento($valores['cpf_cnpj']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($campos as $campo) {
        $valores[$campo] = trim((string)($_POST[$campo] ?? ''));
    }
    $doc = preg_replace('/\D/', '', $valores['cpf_cnpj']);
    $telefone = preg_replace('/\D/', '', $valores['telefone']);

    if (mb_strlen($valores['nome']) < 2 || mb_strlen($valores['nome']) > 100) {
        $erros['nome'] = 'Informe o nome ou razão social (2 a 100 caracteres).';
    }
    if ($valores['cpf_cnpj'] !== '' && !((strlen($doc) === 11 && cpf_valido($doc)) || (strlen($doc) === 14 && cnpj_valido($doc)))) {
        $erros['cpf_cnpj'] = 'CPF ou CNPJ inválido. Confira os números.';
    }
    if ($valores['email'] !== '' && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Digite um email válido, por exemplo vendas@fornecedor.com.';
    }
    if ($valores['telefone'] !== '' && (strlen($telefone) < 10 || strlen($telefone) > 11)) {
        $erros['telefone'] = 'O telefone deve ter DDD + 8 ou 9 dígitos.';
    }
    if (mb_strlen($valores['contato']) > 100 || mb_strlen($valores['observacoes']) > 2000) {
        $erros['observacoes'] = 'Texto longo demais.';
    }

    if (!$erros) {
        $ouNulo = fn(string $s) => $s === '' ? null : $s;
        $dados = [$valores['nome'], $ouNulo($doc), $ouNulo($valores['contato']), $ouNulo($valores['email']),
                  $ouNulo($valores['telefone']), $ouNulo($valores['observacoes'])];
        try {
            if ($editando) {
                $pdo->prepare('UPDATE fornecedores SET NOME = ?, CPF_CNPJ = ?, CONTATO = ?, EMAIL = ?, TELEFONE = ?, OBSERVACOES = ? WHERE ID = ?')
                    ->execute([...$dados, $id]);
                registrar_atividade($pdo, 'ALTEROU', "Alterou o fornecedor '{$valores['nome']}'", 'fornecedor', $id);
                $toasts[] = ['tipo' => 'success', 'mensagem' => 'Fornecedor atualizado!'];
            } else {
                $pdo->prepare('INSERT INTO fornecedores (NOME, CPF_CNPJ, CONTATO, EMAIL, TELEFONE, OBSERVACOES) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute($dados);
                $novoId = (int)$pdo->lastInsertId();
                registrar_atividade($pdo, 'CRIOU', "Cadastrou o fornecedor '{$valores['nome']}'", 'fornecedor', $novoId);
                flash('success', "Fornecedor '{$valores['nome']}' cadastrado!");
                header('Location: fornecedor_cadastro.php?id=' . $novoId);
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            if (str_contains($e->getMessage(), 'UQ_FORNECEDORES_CPF_CNPJ')) {
                $erros['cpf_cnpj'] = 'Já existe um fornecedor com esse CPF/CNPJ.';
            } else {
                $erros['nome'] = 'Já existe um fornecedor com esse nome.';
            }
        }
    }
}

// Histórico de compras (entradas de estoque deste fornecedor)
$compras = [];
if ($editando) {
    $stmt = $pdo->prepare(
        'SELECT m.CRIADO_EM, m.QUANTIDADE, m.CUSTO_UNITARIO, m.MOTIVO, pr.ID AS PRODUTO_ID, pr.NOME AS PRODUTO
           FROM movimentos_estoque m
          INNER JOIN produtos pr ON pr.ID = m.PRODUTO_ID
          WHERE m.FORNECEDOR_ID = ?
          ORDER BY m.CRIADO_EM DESC, m.ID DESC
          LIMIT 50'
    );
    $stmt->execute([$id]);
    $compras = $stmt->fetchAll();
}

$campos = [
    'nome' => ['rotulo' => 'Nome / razão social', 'obrigatorio' => true, 'classe' => 'campo-duplo',
        'atributos' => 'type="text" maxlength="100" placeholder="Ex.: Distribuidora Exemplo Ltda" autocomplete="organization"', 'ajuda' => ''],
    'cpf_cnpj' => ['rotulo' => 'CNPJ ou CPF', 'obrigatorio' => false,
        'atributos' => 'type="text" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00" autocomplete="off" data-mascara-documento', 'ajuda' => 'Opcional.'],
    'contato' => ['rotulo' => 'Pessoa de contato', 'obrigatorio' => false,
        'atributos' => 'type="text" maxlength="100" placeholder="Ex.: João (vendas)" autocomplete="off"', 'ajuda' => ''],
    'email' => ['rotulo' => 'Email', 'obrigatorio' => false, 'classe' => 'campo-duplo',
        'atributos' => 'type="email" maxlength="100" placeholder="vendas@fornecedor.com" autocomplete="off"', 'ajuda' => ''],
    'telefone' => ['rotulo' => 'Telefone', 'obrigatorio' => false, 'classe' => 'campo-duplo',
        'atributos' => 'type="tel" inputmode="numeric" placeholder="(11) 3333-4444" autocomplete="off"', 'ajuda' => ''],
    'observacoes' => ['rotulo' => 'Observações', 'obrigatorio' => false, 'tipo' => 'textarea', 'classe' => 'campo-largo',
        'atributos' => 'rows="3" maxlength="2000" placeholder="Prazo de entrega, condições de pagamento…"', 'ajuda' => ''],
];
$titulo = $editando ? 'Editar Fornecedor' : 'Novo Fornecedor';
$whats = $editando ? whatsapp_link($valores['telefone']) : null;
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
        <?php $breadcrumb = ['Fornecedores' => 'fornecedores.php', $titulo => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone($editando ? 'editar' : 'caminhao'); ?> <?php echo $titulo; ?>
                        <?php if ($editando): ?><span class="page-title-id">#<?php echo $id; ?></span><?php endif; ?></h2>
                    <?php if ($whats): ?>
                        <a href="<?php echo htmlspecialchars($whats); ?>" class="btn btn-outline btn-whatsapp" target="_blank" rel="noopener"><?php echo icone('whatsapp'); ?> WhatsApp</a>
                    <?php endif; ?>
                </div>

                <?php if ($erros): ?>
                    <div class="alert alert-error" role="alert"><?php echo icone('erro'); ?><span>Não foi possível salvar. Corrija os campos destacados abaixo.</span></div>
                <?php endif; ?>

                <form method="POST" action="" novalidate>
                    <?php echo csrf_campo(); ?>
                    <div class="form-grid form-grid-4">
                        <?php include __DIR__ . '/form_campos.php'; ?>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('salvar'); ?> <?php echo $editando ? 'Salvar Alterações' : 'Cadastrar Fornecedor'; ?></button>
                        <a href="fornecedores.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Fornecedores</a>
                        <?php if ($editando && eh_admin()): ?>
                            <a href="fornecedor_apagar.php?id=<?php echo $id; ?>" class="btn btn-danger-outline"
                               data-confirmar-exclusao="o fornecedor <?php echo htmlspecialchars($valores['nome']); ?>"><?php echo icone('lixeira'); ?> Apagar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($editando): ?>
                <section class="list-container">
                    <h3 class="section-title"><?php echo icone('historico'); ?> Compras deste fornecedor <small class="text-muted">(entradas de estoque)</small></h3>
                    <?php if (!$compras): ?>
                        <p class="painel-vazio">Nenhuma compra registrada. Ao lançar uma <strong>entrada</strong> no estoque de um produto, escolha este fornecedor.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="client-table">
                                <thead>
                                    <tr><th>Produto</th><th>Data</th><th class="num">Qtd.</th><th class="num">Custo unit.</th><th class="num">Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compras as $c): ?>
                                        <tr>
                                            <td class="cell-client">
                                                <a href="produto_estoque.php?id=<?php echo $c['PRODUTO_ID']; ?>" class="client-link">
                                                    <span class="client-name"><?php echo htmlspecialchars($c['PRODUTO']); ?></span>
                                                    <span class="client-id"><?php echo htmlspecialchars($c['MOTIVO']); ?></span>
                                                </a>
                                            </td>
                                            <td data-label="Data"><?php echo date('d/m/Y', strtotime($c['CRIADO_EM'])); ?></td>
                                            <td class="num" data-label="Qtd."><?php echo (int)$c['QUANTIDADE']; ?></td>
                                            <td class="num" data-label="Custo unit."><?php echo $c['CUSTO_UNITARIO'] !== null ? moeda((float)$c['CUSTO_UNITARIO']) : '—'; ?></td>
                                            <td class="num" data-label="Total"><?php echo $c['CUSTO_UNITARIO'] !== null ? moeda((int)$c['QUANTIDADE'] * (float)$c['CUSTO_UNITARIO']) : '—'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script src="js/endereco.js"></script>
</body>
</html>
