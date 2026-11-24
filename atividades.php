<?php
require_once 'auth.php';
require_once 'conexao.php';
require_once 'ui.php';
require_once 'lista.php';

// Registro de atividades: quem fez o quê e quando (tabela atividades).
// Filtros: ?usuario=ID  ?acao=CRIOU  ?entidade=cliente  ?de=AAAA-MM-DD  ?ate=AAAA-MM-DD  ?q=texto
// Somente administradores.

exigir_admin();

$q = busca_da_url();
$usuarioFiltro = isset($_GET['usuario']) && is_string($_GET['usuario']) ? (int)$_GET['usuario'] : 0;
$acao = is_string($_GET['acao'] ?? null) && isset(acoes_atividade()[$_GET['acao']]) ? $_GET['acao'] : '';
$entidade = is_string($_GET['entidade'] ?? null) && isset(entidades_atividade()[$_GET['entidade']]) ? $_GET['entidade'] : '';
$de = data_da_url('de');
$ate = data_da_url('ate');

$condicoes = [];
$parametros = [];
if ($q !== '') {
    $condicoes[] = '(a.DESCRICAO LIKE ? OR a.USUARIO_NOME LIKE ?)';
    array_push($parametros, like($q), like($q));
}
if ($usuarioFiltro > 0) {
    $condicoes[] = 'a.USUARIO_ID = ?';
    $parametros[] = $usuarioFiltro;
}
if ($acao !== '') {
    $condicoes[] = 'a.ACAO = ?';
    $parametros[] = $acao;
}
if ($entidade !== '') {
    $condicoes[] = 'a.ENTIDADE = ?';
    $parametros[] = $entidade;
}
if ($de) {
    $condicoes[] = 'a.CRIADO_EM >= ?';
    $parametros[] = $de . ' 00:00:00';
}
if ($ate) {
    $condicoes[] = 'a.CRIADO_EM <= ?';
    $parametros[] = $ate . ' 23:59:59';
}
$where = $condicoes ? 'WHERE ' . implode(' AND ', $condicoes) : '';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM atividades a $where");
$stmt->execute($parametros);
$pag = paginacao((int)$stmt->fetchColumn());

$stmt = $pdo->prepare(
    "SELECT a.ACAO, a.ENTIDADE, a.ENTIDADE_ID, a.DESCRICAO, a.IP, a.CRIADO_EM,
            COALESCE(u.NOME, a.USUARIO_NOME) AS USUARIO, a.USUARIO_ID
       FROM atividades a
       LEFT JOIN usuarios u ON u.ID = a.USUARIO_ID
       $where
      ORDER BY a.CRIADO_EM DESC, a.ID DESC
      LIMIT {$pag['limite']} OFFSET {$pag['offset']}"
);
$stmt->execute($parametros);
$atividades = $stmt->fetchAll();

$usuarios = $pdo->query('SELECT ID, NOME FROM usuarios ORDER BY NOME')->fetchAll();
$filtrando = $q !== '' || $usuarioFiltro || $acao || $entidade || $de || $ate;

/** Link para o registro citado na atividade (se ainda fizer sentido abrir). */
function link_entidade(?string $entidade, ?int $id): ?string
{
    if (!$entidade || !$id) {
        return null;
    }
    return [
        'cliente' => "cliente_ver.php?id=$id",
        'produto' => "produto_estoque.php?id=$id",
        'pedido'  => "pedido_ver.php?id=$id",
        'usuario' => "usuario_cadastro.php?id=$id",
    ][$entidade] ?? null;
}

/** Agrupa por dia: "Hoje", "Ontem" ou a data. */
function rotulo_dia(string $data): string
{
    $dia = date('Y-m-d', strtotime($data));
    return match ($dia) {
        date('Y-m-d')                     => 'Hoje',
        date('Y-m-d', strtotime('-1 day')) => 'Ontem',
        default                           => date('d/m/Y', strtotime($data)),
    };
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Atividades</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php $breadcrumb = ['Atividades' => null]; include __DIR__ . '/header.php'; ?>

        <main>
            <div class="list-container">
                <div class="list-header">
                    <h2 class="page-title"><?php echo icone('historico'); ?> Registro de Atividades</h2>
                </div>
                <p class="intro">Tudo o que foi criado, alterado, apagado ou importado no sistema, além das entradas e saídas dos usuários. Os registros não podem ser editados.</p>

                <form method="get" class="filtros" role="search">
                    <?php echo campos_ocultos(['q', 'usuario', 'acao', 'entidade', 'de', 'ate', 'pagina']); ?>
                    <label for="buscaAtividades" class="visually-hidden">Buscar</label>
                    <div class="search-input">
                        <?php echo icone('busca'); ?>
                        <input type="search" id="buscaAtividades" name="q" value="<?php echo htmlspecialchars($q); ?>" placeholder="Buscar na descrição" autocomplete="off">
                    </div>
                    <label for="filtroUsuario" class="visually-hidden">Usuário</label>
                    <select id="filtroUsuario" name="usuario" class="filtro-select" data-auto-submit>
                        <option value="">Todos os usuários</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['ID']; ?>" <?php echo (int)$u['ID'] === $usuarioFiltro ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['NOME']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="filtroAcao" class="visually-hidden">Ação</label>
                    <select id="filtroAcao" name="acao" class="filtro-select" data-auto-submit>
                        <option value="">Todas as ações</option>
                        <?php foreach (acoes_atividade() as $valor => $rotulo): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === $acao ? 'selected' : ''; ?>><?php echo $rotulo; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="filtroEntidade" class="visually-hidden">Tipo de registro</label>
                    <select id="filtroEntidade" name="entidade" class="filtro-select" data-auto-submit>
                        <option value="">Tudo</option>
                        <?php foreach (entidades_atividade() as $valor => $rotulo): ?>
                            <option value="<?php echo $valor; ?>" <?php echo $valor === $entidade ? 'selected' : ''; ?>><?php echo $rotulo; ?>s</option>
                        <?php endforeach; ?>
                    </select>
                    <div class="filtro-periodo">
                        <label for="filtroDe">De</label>
                        <input type="date" id="filtroDe" name="de" value="<?php echo $de; ?>">
                        <label for="filtroAte">até</label>
                        <input type="date" id="filtroAte" name="ate" value="<?php echo $ate; ?>">
                    </div>
                    <button type="submit" class="btn btn-outline"><?php echo icone('busca'); ?> Filtrar</button>
                    <?php if ($filtrando): ?>
                        <a href="atividades.php" class="btn-limpar"><?php echo icone('fechar'); ?> Limpar</a>
                    <?php endif; ?>
                </form>

                <?php if (!$atividades): ?>
                    <p class="no-results is-visible">Nenhuma atividade encontrada<?php echo $filtrando ? ' com esses filtros' : ''; ?>.</p>
                <?php else: ?>
                    <ol class="linha-tempo">
                        <?php $diaAnterior = null; ?>
                        <?php foreach ($atividades as $a): ?>
                            <?php $dia = rotulo_dia($a['CRIADO_EM']); ?>
                            <?php if ($dia !== $diaAnterior): ?>
                                <li class="linha-tempo-dia"><?php echo $dia; ?></li>
                                <?php $diaAnterior = $dia; ?>
                            <?php endif; ?>
                            <?php $link = $a['ACAO'] !== 'APAGOU' ? link_entidade($a['ENTIDADE'], $a['ENTIDADE_ID'] !== null ? (int)$a['ENTIDADE_ID'] : null) : null; ?>
                            <li class="atividade atividade-<?php echo strtolower($a['ACAO']); ?>">
                                <span class="atividade-hora"><?php echo date('H:i', strtotime($a['CRIADO_EM'])); ?></span>
                                <span class="atividade-marcador" aria-hidden="true"></span>
                                <div class="atividade-corpo">
                                    <p>
                                        <strong><?php echo $a['USUARIO'] ? htmlspecialchars($a['USUARIO']) : 'Sistema'; ?></strong>
                                        <span class="badge badge-acao"><?php echo acoes_atividade()[$a['ACAO']] ?? $a['ACAO']; ?></span>
                                    </p>
                                    <p class="atividade-descricao">
                                        <?php if ($link): ?>
                                            <a href="<?php echo $link; ?>" class="text-link"><?php echo htmlspecialchars($a['DESCRICAO']); ?></a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($a['DESCRICAO']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i:s', strtotime($a['CRIADO_EM'])); ?> · IP <?php echo htmlspecialchars((string)$a['IP']); ?></small>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>

                <?php echo controles_paginacao($pag); ?>
            </div>
        </main>

        <?php include __DIR__ . '/footer.php'; ?>
    </div>
</body>
</html>
