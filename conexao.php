<?php
declare(strict_types=1);

// Conexão com o banco. Os dados de acesso ficam em config.php (fora do git);
// sem ele, vale o modelo config.exemplo.php. Variáveis de ambiente têm prioridade.

$config = require (is_file(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.exemplo.php');

$host = getenv('DB_HOST') ?: $config['db_host'];
$dbname = getenv('DB_NOME') ?: $config['db_nome'];
$user = getenv('DB_USUARIO') ?: $config['db_usuario'];
$password = getenv('DB_SENHA') !== false ? (string)getenv('DB_SENHA') : $config['db_senha'];
unset($config);

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados. Verifique se o MySQL está ativo no XAMPP e os dados em config.php.');
}
unset($password);
