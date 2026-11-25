<?php
// Validação dos dados de cliente e de usuário, usada nos cadastros e nas atualizações.
// As regras são as mesmas do js/formulario.js.

/** Tamanho mínimo da senha dos usuários. */
const SENHA_MINIMA = 6;

/**
 * Valida nome, email e telefone. Retorna um array campo => mensagem de erro
 * (vazio quando está tudo certo). $entidade entra nas mensagens ("do cliente", "do usuário").
 */
function validar_pessoa(string $nome, string $email, string $telefone, string $entidade): array
{
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = "Informe o nome do $entidade.";
    } elseif (mb_strlen($nome) < 3) {
        $erros['nome'] = 'O nome deve ter pelo menos 3 caracteres.';
    }

    if ($email === '') {
        $erros['email'] = "Informe o email do $entidade.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Digite um email válido, por exemplo nome@empresa.com.';
    }

    $digitos = preg_replace('/\D/', '', $telefone);
    if ($telefone !== '' && (strlen($digitos) < 10 || strlen($digitos) > 11)) {
        $erros['telefone'] = 'O telefone deve ter DDD + 8 ou 9 dígitos.';
    }

    return $erros;
}

function validar_cliente(string $nome, string $email, string $telefone): array
{
    return validar_pessoa($nome, $email, $telefone, 'cliente');
}

/**
 * Valida a senha nova e a confirmação.
 * $obrigatoria = false permite deixar as duas em branco (edição sem trocar a senha).
 */
function validar_senha(string $senha, string $confirmacao, bool $obrigatoria): array
{
    $erros = [];

    if ($senha === '' && $confirmacao === '' && !$obrigatoria) {
        return $erros;
    }
    if ($senha === '') {
        $erros['senha'] = 'Informe a senha.';
    } elseif (mb_strlen($senha) < SENHA_MINIMA) {
        $erros['senha'] = 'A senha deve ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    }
    if ($confirmacao !== $senha) {
        $erros['senha_confirmacao'] = 'As senhas não conferem.';
    }

    return $erros;
}

/* ---------- Cliente completo (documento, endereço e observações) ---------- */

/** Campos do cadastro de cliente, todos vazios (formulário novo). */
function cliente_vazio(): array
{
    return array_fill_keys([
        'nome', 'email', 'telefone', 'cpf_cnpj', 'nascimento', 'cep', 'logradouro', 'numero',
        'complemento', 'bairro', 'cidade', 'uf', 'observacoes',
    ], '');
}

/** Lê os campos do cliente enviados pelo formulário (sem espaços nas pontas). */
function cliente_do_post(): array
{
    $valores = [];
    foreach (array_keys(cliente_vazio()) as $campo) {
        $valor = $_POST[$campo] ?? '';
        $valores[$campo] = is_string($valor) ? trim($valor) : '';
    }
    $valores['uf'] = strtoupper($valores['uf']);
    $valores['nascimento'] = normalizar_data($valores['nascimento']);
    return $valores;
}

/** Converte uma linha da tabela clientes (colunas em maiúsculas) para os campos do formulário. */
function cliente_do_banco(array $linha): array
{
    $valores = [];
    foreach (array_keys(cliente_vazio()) as $campo) {
        $valores[$campo] = (string)($linha[strtoupper($campo)] ?? '');
    }
    $valores['cpf_cnpj'] = formatar_documento($valores['cpf_cnpj']);
    $valores['cep'] = formatar_cep($valores['cep']);
    return $valores;
}

/**
 * Valores prontos para gravar, na ordem das colunas:
 * NOME, EMAIL, TELEFONE, CPF_CNPJ, CEP, LOGRADOURO, NUMERO, COMPLEMENTO, BAIRRO, CIDADE, UF, OBSERVACOES, NASCIMENTO.
 * CPF/CNPJ e CEP só com dígitos; campos opcionais vazios viram NULL.
 */
function cliente_para_banco(array $v): array
{
    $ouNulo = fn(string $s) => $s === '' ? null : $s;
    return [
        $v['nome'],
        $v['email'],
        $ouNulo($v['telefone']),
        $ouNulo(preg_replace('/\D/', '', $v['cpf_cnpj'])),
        $ouNulo(preg_replace('/\D/', '', $v['cep'])),
        $ouNulo($v['logradouro']),
        $ouNulo($v['numero']),
        $ouNulo($v['complemento']),
        $ouNulo($v['bairro']),
        $ouNulo($v['cidade']),
        $ouNulo($v['uf']),
        $ouNulo($v['observacoes']),
        $ouNulo($v['nascimento']),
    ];
}

/** Colunas na mesma ordem de cliente_para_banco() (para montar INSERT e UPDATE). */
const CLIENTE_COLUNAS = ['NOME', 'EMAIL', 'TELEFONE', 'CPF_CNPJ', 'CEP', 'LOGRADOURO', 'NUMERO',
                         'COMPLEMENTO', 'BAIRRO', 'CIDADE', 'UF', 'OBSERVACOES', 'NASCIMENTO'];

/** SQL de INSERT de cliente com todas as colunas de CLIENTE_COLUNAS. */
function sql_inserir_cliente(): string
{
    return 'INSERT INTO clientes (' . implode(', ', CLIENTE_COLUNAS) . ') VALUES ('
        . implode(', ', array_fill(0, count(CLIENTE_COLUNAS), '?')) . ')';
}

/** Mensagem amigável quando o banco recusa um cliente repetido (erro 23000). */
function erro_cliente_duplicado(PDOException $e): array
{
    return str_contains($e->getMessage(), 'UQ_CLIENTES_EMAIL')
        ? ['email' => 'Já existe um cliente com esse email.']
        : ['cpf_cnpj' => 'Já existe um cliente com esse CPF/CNPJ.'];
}

/**
 * Aceita a data de nascimento como AAAA-MM-DD (campo de data) ou DD/MM/AAAA (planilha).
 * Retorna AAAA-MM-DD, ou o texto original se não reconhecer (a validação acusa o erro).
 */
function normalizar_data(string $texto): string
{
    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $texto, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    return $texto;
}

/** Siglas dos estados brasileiros. */
function ufs(): array
{
    return ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR',
            'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
}

/** CPF válido: 11 dígitos, não todos iguais, e os 2 dígitos verificadores conferem. */
function cpf_valido(string $d): bool
{
    if (!preg_match('/^\d{11}$/', $d) || preg_match('/^(\d)\1{10}$/', $d)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += (int)$d[$i] * ($t + 1 - $i);
        }
        if ((int)$d[$t] !== ((10 * $soma) % 11) % 10) {
            return false;
        }
    }
    return true;
}

/** CNPJ válido: 14 dígitos, não todos iguais, e os 2 dígitos verificadores conferem. */
function cnpj_valido(string $d): bool
{
    if (!preg_match('/^\d{14}$/', $d) || preg_match('/^(\d)\1{13}$/', $d)) {
        return false;
    }
    foreach ([[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]] as $n => $pesos) {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int)$d[$i] * $peso;
        }
        $resto = $soma % 11;
        if ((int)$d[12 + $n] !== ($resto < 2 ? 0 : 11 - $resto)) {
            return false;
        }
    }
    return true;
}

/** Valida todos os campos do cliente. Retorna campo => mensagem (vazio = tudo certo). */
function validar_cliente_completo(array $v): array
{
    $erros = validar_cliente($v['nome'], $v['email'], $v['telefone']);

    $doc = preg_replace('/\D/', '', $v['cpf_cnpj']);
    if ($v['cpf_cnpj'] !== '') {
        if (strlen($doc) === 11 && !cpf_valido($doc)) {
            $erros['cpf_cnpj'] = 'CPF inválido. Confira os números.';
        } elseif (strlen($doc) === 14 && !cnpj_valido($doc)) {
            $erros['cpf_cnpj'] = 'CNPJ inválido. Confira os números.';
        } elseif (!in_array(strlen($doc), [11, 14], true)) {
            $erros['cpf_cnpj'] = 'Digite um CPF (11 dígitos) ou CNPJ (14 dígitos).';
        }
    }

    if ($v['cep'] !== '' && !preg_match('/^\d{8}$/', preg_replace('/\D/', '', $v['cep']))) {
        $erros['cep'] = 'O CEP deve ter 8 dígitos.';
    }
    if ($v['uf'] !== '' && !in_array($v['uf'], ufs(), true)) {
        $erros['uf'] = 'Escolha um estado da lista.';
    }
    if ($v['nascimento'] !== '') {
        if (!data_valida($v['nascimento'])) {
            $erros['nascimento'] = 'Data de nascimento inválida (use DD/MM/AAAA).';
        } elseif ($v['nascimento'] > date('Y-m-d') || $v['nascimento'] < '1900-01-01') {
            $erros['nascimento'] = 'A data de nascimento deve estar entre 1900 e hoje.';
        }
    }

    $limites = ['logradouro' => 150, 'numero' => 20, 'complemento' => 100, 'bairro' => 100, 'cidade' => 100, 'observacoes' => 2000];
    foreach ($limites as $campo => $maximo) {
        if (mb_strlen($v[$campo]) > $maximo) {
            $erros[$campo] = "Use no máximo $maximo caracteres.";
        }
    }

    return $erros;
}

/* ---------- Produto: SKU e código de barras ---------- */

/** SKU (código interno): letras, números, ponto, hífen e sublinhado, até 40 caracteres. */
function sku_valido(string $sku): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{1,40}$/', $sku);
}

/**
 * Código de barras GTIN (EAN-8, UPC-A/12, EAN-13 ou GTIN-14) com dígito verificador.
 * O último dígito é calculado com pesos 3 e 1 alternados, da direita para a esquerda.
 */
function gtin_valido(string $codigo): bool
{
    if (!preg_match('/^(\d{8}|\d{12}|\d{13}|\d{14})$/', $codigo)) {
        return false;
    }
    $digitos = array_map('intval', str_split($codigo));
    $verificador = array_pop($digitos);
    $soma = 0;
    foreach (array_reverse($digitos) as $i => $d) {
        $soma += $d * ($i % 2 === 0 ? 3 : 1);
    }
    return (10 - $soma % 10) % 10 === $verificador;
}

/** Data AAAA-MM-DD válida? */
function data_valida(string $data): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $data);
    return $d && $d->format('Y-m-d') === $data;
}
