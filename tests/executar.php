<?php
declare(strict_types=1);

// Executor de testes automatizados (sem dependências: não precisa de Composer nem PHPUnit).
//
//   php tests/executar.php            → roda todos os testes
//   php tests/executar.php estoque    → roda só os arquivos com "estoque" no nome
//
// Cada arquivo tests/*_teste.php registra casos com teste('descrição', function () { ... }).
// Os testes de banco rodam dentro de uma transação que é desfeita no final (rollBack),
// então não deixam dados para trás.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../ui.php';
require_once __DIR__ . '/../validacao.php';
require_once __DIR__ . '/../financeiro.php';
require_once __DIR__ . '/../estoque.php';
require_once __DIR__ . '/../lixeira_funcoes.php';

final class FalhaTeste extends Exception {}

$GLOBALS['casos'] = [];

function teste(string $descricao, callable $caso): void
{
    $GLOBALS['casos'][] = [$GLOBALS['arquivoAtual'], $descricao, $caso];
}

function igual(mixed $esperado, mixed $obtido, string $mensagem = ''): void
{
    if ($esperado !== $obtido) {
        throw new FalhaTeste(($mensagem ? "$mensagem: " : '') . 'esperado ' . var_export($esperado, true) . ', obtido ' . var_export($obtido, true));
    }
}

function verdadeiro(bool $condicao, string $mensagem = 'esperava verdadeiro'): void
{
    if (!$condicao) {
        throw new FalhaTeste($mensagem);
    }
}

function falso(bool $condicao, string $mensagem = 'esperava falso'): void
{
    verdadeiro(!$condicao, $mensagem);
}

/** Conexão para os testes de banco (abre só quando algum teste pede). */
function banco(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        require __DIR__ . '/../conexao.php';
        /** @var PDO $pdo */
    }
    return $pdo;
}

/** Roda $caso dentro de uma transação desfeita no final (o banco fica como estava). */
function no_banco(callable $caso): void
{
    $pdo = banco();
    $pdo->beginTransaction();
    try {
        $caso($pdo);
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

$filtro = $argv[1] ?? '';
foreach (glob(__DIR__ . '/*_teste.php') as $arquivo) {
    if ($filtro === '' || str_contains(basename($arquivo), $filtro)) {
        $GLOBALS['arquivoAtual'] = basename($arquivo, '_teste.php');
        require $arquivo;
    }
}

$_SESSION = ['usuario_id' => null, 'usuario_nome' => 'Testes automatizados'];
$passou = 0;
$falhas = [];
$inicio = microtime(true);
$arquivoAnterior = null;

foreach ($GLOBALS['casos'] as [$arquivo, $descricao, $caso]) {
    if ($arquivo !== $arquivoAnterior) {
        echo "\n$arquivo\n";
        $arquivoAnterior = $arquivo;
    }
    try {
        $caso();
        $passou++;
        echo "  ✓ $descricao\n";
    } catch (Throwable $e) {
        $falhas[] = "$arquivo › $descricao";
        echo "  ✗ $descricao\n      " . ($e instanceof FalhaTeste ? '' : get_class($e) . ': ') . $e->getMessage() . "\n";
    }
}

$total = count($GLOBALS['casos']);
printf("\n%d de %d testes passaram (%.2fs)\n", $passou, $total, microtime(true) - $inicio);
if ($falhas) {
    echo "Falharam:\n  - " . implode("\n  - ", $falhas) . "\n";
}
exit($falhas ? 1 : 0);
