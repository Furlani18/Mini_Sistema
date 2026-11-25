<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'validacao.php';

// Importa clientes de uma planilha CSV (exportada do Excel, Google Planilhas etc.).
// - Aceita separador ";" ou "," e arquivos em UTF-8 ou no padrão do Excel (Windows-1252).
// - A 1ª linha deve ter os nomes das colunas; "nome" e "email" são obrigatórias.
// - Cada linha passa pelas mesmas validações do cadastro; linhas com erro ou com
//   email/CPF/CNPJ já cadastrado são ignoradas e listadas no resultado.
// - "Somente verificar" mostra o resultado sem gravar nada.
//   cliente_importar.php?modelo=1 → baixa uma planilha de exemplo

const IMPORTAR_MAX_LINHAS = 1000;
const IMPORTAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

// Nomes de coluna aceitos (sem acento, minúsculos) => campo do cliente
const COLUNAS_IMPORTACAO = [
    'nome' => 'nome', 'nomecompleto' => 'nome', 'razaosocial' => 'nome', 'cliente' => 'nome',
    'email' => 'email',
    'telefone' => 'telefone', 'celular' => 'telefone', 'fone' => 'telefone', 'whatsapp' => 'telefone',
    'cpf' => 'cpf_cnpj', 'cnpj' => 'cpf_cnpj', 'cpfcnpj' => 'cpf_cnpj', 'documento' => 'cpf_cnpj',
    'cep' => 'cep',
    'logradouro' => 'logradouro', 'endereco' => 'logradouro', 'rua' => 'logradouro',
    'numero' => 'numero', 'n' => 'numero', 'num' => 'numero',
    'complemento' => 'complemento',
    'bairro' => 'bairro',
    'cidade' => 'cidade', 'municipio' => 'cidade',
    'uf' => 'uf', 'estado' => 'uf',
    'observacoes' => 'observacoes', 'observacao' => 'observacoes', 'obs' => 'observacoes',
    'nascimento' => 'nascimento', 'datanascimento' => 'nascimento', 'datadenascimento' => 'nascimento', 'aniversario' => 'nascimento',
];

if (isset($_GET['modelo'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo_importacao_clientes.csv"');
    echo "\xEF\xBB\xBF";
    echo "nome;email;telefone;cpf_cnpj;nascimento;cep;logradouro;numero;complemento;bairro;cidade;uf;observacoes\r\n";
    echo "Maria da Silva;maria@exemplo.com;(11) 98888-7777;529.982.247-25;15/03/1985;01310-100;Avenida Paulista;1000;Sala 5;Bela Vista;São Paulo;SP;Cliente desde a feira\r\n";
    echo "Empresa Exemplo Ltda;contato@empresa.com;(21) 3333-4444;11.222.333/0001-81;;;;;;;Rio de Janeiro;RJ;\r\n";
    exit;
}

/** "Observações" → "observacoes", "E-mail" → "email", "cpf_cnpj" → "cpfcnpj" (para reconhecer o cabeçalho). */
function normalizar_cabecalho(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i',
                            'ó' => 'o', 'õ' => 'o', 'ô' => 'o', 'ú' => 'u', 'ç' => 'c', 'º' => '', '°' => '']);
    return preg_replace('/[^a-z0-9]/', '', $texto);
}

/** Telefone só com dígitos → "(11) 98888-7777"; outros formatos ficam como estão. */
function formatar_telefone_importado(string $tel): string
{
    $d = preg_replace('/\D/', '', $tel);
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7);
    }
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6);
    }
    return $tel;
}

$resultado = null;   // ['linhas' => [...], 'importados' => n, 'ignorados' => n, 'simulacao' => bool]
$erroArquivo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $arquivo = $_FILES['arquivo'] ?? [];
    $simulacao = !empty($_POST['simular']);
    $codigoUpload = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($codigoUpload === UPLOAD_ERR_NO_FILE) {
        $erroArquivo = 'Escolha um arquivo .csv para importar.';
    } elseif ($codigoUpload !== UPLOAD_ERR_OK) { // inclui envio de vários arquivos (array) e falhas de upload
        $erroArquivo = 'Não foi possível receber o arquivo. Tente novamente.';
    } elseif ($arquivo['size'] > IMPORTAR_MAX_BYTES) {
        $erroArquivo = 'O arquivo é maior que 2 MB. Divida a planilha em partes menores.';
    } elseif (!preg_match('/\.(csv|txt)$/i', (string)$arquivo['name'])) {
        $erroArquivo = 'Envie um arquivo .csv (no Excel: Arquivo → Salvar como → CSV).';
    } else {
        $conteudo = (string)file_get_contents($arquivo['tmp_name']);
        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo); // remove o BOM
        if (!mb_check_encoding($conteudo, 'UTF-8')) {
            $conteudo = mb_convert_encoding($conteudo, 'UTF-8', 'Windows-1252'); // CSV salvo pelo Excel
        }

        // Separador: o mais frequente na primeira linha
        $primeira = strtok($conteudo, "\n") ?: '';
        $contagem = [';' => substr_count($primeira, ';'), ',' => substr_count($primeira, ','), "\t" => substr_count($primeira, "\t")];
        arsort($contagem);
        $separador = (string)array_key_first($contagem);

        $fluxo = fopen('php://memory', 'r+');
        fwrite($fluxo, $conteudo);
        rewind($fluxo);

        $cabecalho = fgetcsv($fluxo, 0, $separador) ?: [];
        $mapa = []; // índice da coluna => campo do cliente
        foreach ($cabecalho as $indice => $nomeColuna) {
            $chave = normalizar_cabecalho((string)$nomeColuna);
            if (isset(COLUNAS_IMPORTACAO[$chave]) && !in_array(COLUNAS_IMPORTACAO[$chave], $mapa, true)) {
                $mapa[$indice] = COLUNAS_IMPORTACAO[$chave];
            }
        }

        if (!in_array('nome', $mapa, true) || !in_array('email', $mapa, true)) {
            $erroArquivo = 'A primeira linha da planilha precisa ter as colunas "nome" e "email". Baixe o modelo para ver o formato.';
        } else {
            // Emails e documentos já cadastrados (para não duplicar)
            $emailsExistentes = array_flip(array_map('mb_strtolower', $pdo->query('SELECT EMAIL FROM clientes')->fetchAll(PDO::FETCH_COLUMN)));
            $docsExistentes = array_flip($pdo->query('SELECT CPF_CNPJ FROM clientes WHERE CPF_CNPJ IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));

            $inserir = $pdo->prepare(sql_inserir_cliente());
            $linhas = [];
            $importados = 0;
            $numeroLinha = 1;
            $pdo->beginTransaction();

            while (($colunas = fgetcsv($fluxo, 0, $separador)) !== false) {
                $numeroLinha++;
                if (count(array_filter($colunas, fn($c) => trim((string)$c) !== '')) === 0) {
                    continue; // linha em branco
                }
                if (count($linhas) >= IMPORTAR_MAX_LINHAS) {
                    $linhas[] = ['linha' => $numeroLinha, 'nome' => '…', 'situacao' => 'erro',
                                 'detalhe' => 'Limite de ' . IMPORTAR_MAX_LINHAS . ' linhas por importação atingido; o restante foi ignorado.'];
                    break;
                }

                $valores = cliente_vazio();
                foreach ($mapa as $indice => $campo) {
                    $valores[$campo] = trim((string)($colunas[$indice] ?? ''));
                }
                $valores['uf'] = strtoupper($valores['uf']);
                $valores['telefone'] = formatar_telefone_importado($valores['telefone']);
                $valores['nascimento'] = normalizar_data($valores['nascimento']);

                $erros = validar_cliente_completo($valores);
                $email = mb_strtolower($valores['email']);
                $doc = preg_replace('/\D/', '', $valores['cpf_cnpj']);

                if ($erros) {
                    $situacao = ['erro', implode(' ', array_unique($erros))];
                } elseif (isset($emailsExistentes[$email])) {
                    $situacao = ['ignorado', 'Já existe um cliente com este email.'];
                } elseif ($doc !== '' && isset($docsExistentes[$doc])) {
                    $situacao = ['ignorado', 'Já existe um cliente com este CPF/CNPJ.'];
                } else {
                    if (!$simulacao) {
                        $inserir->execute(cliente_para_banco($valores));
                    }
                    $emailsExistentes[$email] = true; // evita duplicar dentro da própria planilha
                    if ($doc !== '') {
                        $docsExistentes[$doc] = true;
                    }
                    $importados++;
                    $situacao = ['ok', $simulacao ? 'Pronto para importar.' : 'Importado.'];
                }
                $linhas[] = ['linha' => $numeroLinha, 'nome' => $valores['nome'] ?: '(sem nome)', 'situacao' => $situacao[0], 'detalhe' => $situacao[1]];
            }
            fclose($fluxo);

            $pdo->commit();
            if (!$simulacao && $importados > 0) {
                registrar_atividade($pdo, 'IMPORTOU', "Importou $importados cliente(s) da planilha '" . basename((string)$arquivo['name']) . "'", 'cliente');
            }
            $resultado = [
                'linhas'     => $linhas,
                'importados' => $importados,
                'ignorados'  => count($linhas) - $importados,
                'simulacao'  => $simulacao,
            ];
            if (!$linhas) {
                $erroArquivo = 'A planilha não tem nenhuma linha de cliente abaixo do cabeçalho.';
                $resultado = null;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Clientes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Clientes' => 'clientes.php', 'Importar planilha' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="form-container">
                <h2 class="page-title"><?php echo icone('upload'); ?> Importar Clientes de Planilha</h2>

                <ol class="passos">
                    <li><strong>Baixe o modelo</strong> e preencha uma linha por cliente (ou use sua própria planilha com as mesmas colunas).
                        <a href="cliente_importar.php?modelo=1" class="text-link"><?php echo icone('download'); ?> Baixar modelo .csv</a></li>
                    <li><strong>Salve como CSV</strong> — no Excel: Arquivo → Salvar como → “CSV (separado por vírgulas)”.</li>
                    <li><strong>Envie o arquivo</strong>. Use “Somente verificar” para conferir antes de gravar.</li>
                </ol>
                <p class="text-muted importar-colunas">
                    Colunas reconhecidas: <code>nome</code>* <code>email</code>* <code>telefone</code> <code>cpf_cnpj</code> <code>nascimento</code> <code>cep</code>
                    <code>logradouro</code> <code>numero</code> <code>complemento</code> <code>bairro</code> <code>cidade</code> <code>uf</code> <code>observacoes</code>
                    · Clientes com email ou CPF/CNPJ já cadastrado são ignorados.
                </p>

                <?php if ($erroArquivo): ?>
                    <div class="alert alert-error" role="alert"><?php echo icone('erro'); ?><span><?php echo htmlspecialchars($erroArquivo); ?></span></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" class="importar-form">
                    <?php echo csrf_campo(); ?>
                    <label for="arquivo" class="arquivo-zona">
                        <?php echo icone('upload'); ?>
                        <span><strong>Clique para escolher o arquivo .csv</strong><small data-arquivo-nome>Máximo 2 MB e <?php echo IMPORTAR_MAX_LINHAS; ?> linhas</small></span>
                        <input type="file" id="arquivo" name="arquivo" accept=".csv,.txt,text/csv" required>
                    </label>
                    <label class="checkbox">
                        <input type="checkbox" name="simular" value="1" <?php echo !empty($_POST['simular']) ? 'checked' : ''; ?>>
                        Somente verificar (não grava nada)
                    </label>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?php echo icone('upload'); ?> Enviar planilha</button>
                        <a href="clientes.php" class="btn btn-outline"><?php echo icone('lista'); ?> Ver Clientes</a>
                    </div>
                </form>
            </div>

            <?php if ($resultado): ?>
                <div class="list-container">
                    <h3 class="section-title"><?php echo icone('lista'); ?> Resultado <?php echo $resultado['simulacao'] ? '(verificação — nada foi gravado)' : ''; ?></h3>
                    <div class="alert <?php echo $resultado['importados'] ? 'alert-success' : 'alert-warning'; ?>">
                        <?php echo icone($resultado['importados'] ? 'sucesso' : 'alerta'); ?>
                        <span>
                            <strong><?php echo $resultado['importados']; ?></strong> cliente(s) <?php echo $resultado['simulacao'] ? 'prontos para importar' : 'importados'; ?>
                            · <strong><?php echo $resultado['ignorados']; ?></strong> linha(s) com erro ou já cadastradas.
                            <?php if ($resultado['simulacao'] && $resultado['importados']): ?>Envie de novo sem marcar “Somente verificar” para gravar.<?php endif; ?>
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="client-table">
                            <thead><tr><th>Linha</th><th>Nome</th><th>Situação</th><th>Detalhe</th></tr></thead>
                            <tbody>
                                <?php foreach ($resultado['linhas'] as $l): ?>
                                    <tr>
                                        <td class="cell-client"><span class="client-name"><?php echo $l['linha']; ?></span></td>
                                        <td data-label="Nome"><?php echo htmlspecialchars($l['nome']); ?></td>
                                        <td data-label="Situação">
                                            <span class="badge <?php echo ['ok' => 'badge-pago', 'ignorado' => 'badge-aberto', 'erro' => 'badge-cancelado'][$l['situacao']]; ?>">
                                                <?php echo ['ok' => $resultado['simulacao'] ? 'OK' : 'Importado', 'ignorado' => 'Ignorado', 'erro' => 'Erro'][$l['situacao']]; ?>
                                            </span>
                                        </td>
                                        <td data-label="Detalhe"><?php echo htmlspecialchars($l['detalhe']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
    <script>
        // Mostra o nome do arquivo escolhido
        document.getElementById('arquivo').addEventListener('change', function () {
            const alvo = document.querySelector('[data-arquivo-nome]');
            if (this.files[0]) alvo.textContent = this.files[0].name;
        });
    </script>
</body>
</html>
