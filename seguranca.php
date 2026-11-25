<?php
declare(strict_types=1);

// Funções de segurança compartilhadas:
//  - token CSRF: impede que outro site envie formulários em nome do usuário logado
//  - níveis de acesso (ADMIN / OPERADOR)
//  - limite de tentativas de login
//  - registro de atividades (quem fez o quê e quando)

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/ui.php';

/* ---------- CSRF ---------- */

/** Token secreto da sessão, enviado escondido em todo formulário POST. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Campo oculto com o token, para colocar dentro de cada <form method="POST">. */
function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Confere o token enviado (hash_equals evita ataque de tempo na comparação). */
function csrf_valido(): bool
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && hash_equals(csrf_token(), $enviado);
}

/**
 * Páginas que só executam uma ação (apagar etc.) devem aceitar apenas POST.
 * Um link GET poderia ser disparado sem querer (ou por outro site).
 */
function exigir_post(string $voltarPara): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash('error', 'Ação inválida. Use o botão da página para confirmar.');
        header('Location: ' . $voltarPara);
        exit;
    }
}

/* ---------- Níveis de acesso ---------- */

/** Perfis possíveis (valor no banco => rótulo na tela). */
function perfis(): array
{
    return [
        'ADMIN'    => 'Administrador',
        'OPERADOR' => 'Operador',
    ];
}

function eh_admin(): bool
{
    return ($_SESSION['usuario_perfil'] ?? '') === 'ADMIN';
}

/** Bloqueia a página para quem não é administrador. */
function exigir_admin(string $voltarPara = 'index.php'): void
{
    if (!eh_admin()) {
        flash('error', 'Somente administradores podem acessar essa função.');
        header('Location: ' . $voltarPara);
        exit;
    }
}

/* ---------- Limite de tentativas de login ---------- */

const LOGIN_MAX_POR_EMAIL = 5;   // tentativas erradas por email…
const LOGIN_MAX_POR_IP = 20;     // …ou por computador (IP)…
const LOGIN_JANELA_MINUTOS = 15; // …dentro desta janela de tempo

function ip_cliente(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'desconhecido'), 0, 45);
}

/**
 * Minutos que faltam para poder tentar de novo (0 = liberado).
 * Conta as tentativas erradas recentes por email e por IP.
 */
function login_bloqueado(PDO $pdo, string $email): int
{
    $stmt = $pdo->prepare(
        'SELECT SUM(EMAIL = ?) AS POR_EMAIL, SUM(IP = ?) AS POR_IP,
                MIN(CASE WHEN EMAIL = ? THEN CRIADO_EM END) AS PRIMEIRA_EMAIL,
                MIN(CASE WHEN IP = ? THEN CRIADO_EM END) AS PRIMEIRA_IP
           FROM tentativas_login
          WHERE CRIADO_EM > NOW() - INTERVAL ' . LOGIN_JANELA_MINUTOS . ' MINUTE
            AND (EMAIL = ? OR IP = ?)'
    );
    $ip = ip_cliente();
    $stmt->execute([$email, $ip, $email, $ip, $email, $ip]);
    $r = $stmt->fetch();

    $primeira = null;
    if ((int)$r['POR_EMAIL'] >= LOGIN_MAX_POR_EMAIL) {
        $primeira = $r['PRIMEIRA_EMAIL'];
    } elseif ((int)$r['POR_IP'] >= LOGIN_MAX_POR_IP) {
        $primeira = $r['PRIMEIRA_IP'];
    }
    if ($primeira === null) {
        return 0;
    }
    $liberaEm = strtotime($primeira) + LOGIN_JANELA_MINUTOS * 60;
    return max(1, (int)ceil(($liberaEm - time()) / 60));
}

function registrar_tentativa_falha(PDO $pdo, string $email): void
{
    $pdo->prepare('INSERT INTO tentativas_login (EMAIL, IP) VALUES (?, ?)')->execute([mb_substr($email, 0, 100), ip_cliente()]);
    // Limpeza: tentativas antigas não servem para mais nada
    $pdo->exec('DELETE FROM tentativas_login WHERE CRIADO_EM < NOW() - INTERVAL 1 DAY');
}

function limpar_tentativas(PDO $pdo, string $email): void
{
    $pdo->prepare('DELETE FROM tentativas_login WHERE EMAIL = ?')->execute([$email]);
}

/* ---------- Registro de atividades ---------- */

/** Ações registradas (valor gravado => rótulo na tela). */
function acoes_atividade(): array
{
    return [
        'CRIOU'    => 'Criou',
        'ALTEROU'  => 'Alterou',
        'APAGOU'   => 'Apagou',
        'IMPORTOU' => 'Importou',
        'ESTOQUE'  => 'Estoque',
        'LOGIN'    => 'Entrou',
        'LOGOUT'   => 'Saiu',
        'SENHA'    => 'Senha',
        'PAGAMENTO' => 'Pagamento',
        'RESTAUROU' => 'Restaurou',
        'BACKUP'   => 'Backup',
    ];
}

/** Entidades (tabelas) que aparecem no registro (valor gravado => rótulo). */
function entidades_atividade(): array
{
    return [
        'cliente' => 'Cliente',
        'produto' => 'Produto',
        'pedido'  => 'Pedido',
        'usuario' => 'Usuário',
        'fornecedor' => 'Fornecedor',
        'categoria' => 'Categoria',
        'sistema' => 'Sistema',
    ];
}

/**
 * Grava uma linha no registro de atividades.
 * Ex.: registrar_atividade($pdo, 'APAGOU', "Apagou o cliente 'Ana'", 'cliente', 7);
 * Nunca interrompe a ação principal: se a gravação falhar, apenas ignora.
 */
function registrar_atividade(PDO $pdo, string $acao, string $descricao, ?string $entidade = null, ?int $entidadeId = null): void
{
    try {
        $pdo->prepare(
            'INSERT INTO atividades (USUARIO_ID, USUARIO_NOME, ACAO, ENTIDADE, ENTIDADE_ID, DESCRICAO, IP)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $_SESSION['usuario_id'] ?? null,
            $_SESSION['usuario_nome'] ?? null,
            $acao,
            $entidade,
            $entidadeId,
            mb_substr($descricao, 0, 255),
            ip_cliente(),
        ]);
    } catch (PDOException $e) {
        // O registro é auxiliar: um erro aqui não deve desfazer o que o usuário fez
    }
}
