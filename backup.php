<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';

// Backup do banco: gera e baixa um arquivo .sql com a estrutura e os dados de todas
// as tabelas (e as visões), sem depender do mysqldump. Para restaurar:
//   mysql -u root mini_sistema < backup.sql
// Só por POST (botão em configuracoes.php) e só administradores — o arquivo tem
// todos os dados do sistema, inclusive os hashes das senhas.

exigir_post('configuracoes.php');
exigir_admin('configuracoes.php');

// Pode demorar em bancos grandes; os dados são enviados aos poucos (sem montar tudo na memória)
set_time_limit(300);

$nomeBanco = $pdo->query('SELECT DATABASE()')->fetchColumn();
$tabelas = [];
$visoes = [];
foreach ($pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as [$nome, $tipo]) {
    if ($tipo === 'VIEW') {
        $visoes[] = $nome;
    } else {
        $tabelas[] = $nome;
    }
}

registrar_atividade($pdo, 'BACKUP', 'Baixou um backup do banco (' . count($tabelas) . ' tabelas)', 'sistema');

$arquivo = 'backup_' . $nomeBanco . '_' . date('Y-m-d_His') . '.sql';
header('Content-Type: application/sql; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: no-store');

/** Nome de tabela/coluna entre crases. */
$crase = fn(string $nome) => '`' . str_replace('`', '``', $nome) . '`';

echo "-- Backup do banco `$nomeBanco` gerado pelo Sistema de Gerenciamento de Clientes\n";
echo '-- Data: ' . date('d/m/Y H:i:s') . ' · Usuário: ' . str_replace("\n", ' ', (string)($_SESSION['usuario_nome'] ?? '')) . "\n";
echo "-- Restaurar: mysql -u root $nomeBanco < $arquivo\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

foreach ($tabelas as $tabela) {
    $criar = $pdo->query('SHOW CREATE TABLE ' . $crase($tabela))->fetch(PDO::FETCH_NUM)[1];
    echo "-- --------------------------------------------------------\n-- Tabela $tabela\n";
    echo 'DROP TABLE IF EXISTS ' . $crase($tabela) . ";\n$criar;\n\n";

    // Linhas em lotes de 100 por INSERT; consulta "sem buffer" lê linha a linha do servidor
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
    $consulta = $pdo->query('SELECT * FROM ' . $crase($tabela));
    $lote = [];
    $colunas = null;
    while ($linha = $consulta->fetch(PDO::FETCH_ASSOC)) {
        $colunas ??= '(' . implode(', ', array_map($crase, array_keys($linha))) . ')';
        $lote[] = '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), $linha)) . ')';
        if (count($lote) === 100) {
            echo 'INSERT INTO ' . $crase($tabela) . " $colunas VALUES\n" . implode(",\n", $lote) . ";\n";
            $lote = [];
        }
    }
    if ($lote) {
        echo 'INSERT INTO ' . $crase($tabela) . " $colunas VALUES\n" . implode(",\n", $lote) . ";\n";
    }
    $consulta->closeCursor();
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    echo "\n";
    flush();
}

foreach ($visoes as $visao) {
    $criar = $pdo->query('SHOW CREATE VIEW ' . $crase($visao))->fetch(PDO::FETCH_NUM)[1];
    // Remove o DEFINER (usuário@servidor de origem), que pode não existir onde o backup for restaurado
    $criar = preg_replace('/\sDEFINER=`[^`]*`@`[^`]*`/', '', $criar);
    $criar = preg_replace('/\sSQL SECURITY DEFINER/', '', $criar);
    echo "-- --------------------------------------------------------\n-- Visão $visao\n";
    echo 'DROP VIEW IF EXISTS ' . $crase($visao) . ";\n$criar;\n\n";
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n-- Fim do backup\n";
