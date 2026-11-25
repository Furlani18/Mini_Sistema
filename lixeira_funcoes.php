<?php
// Lixeira: antes de apagar um cliente, produto ou pedido, uma cópia completa dele
// (em JSON) é guardada na tabela lixeira. Um administrador pode restaurar o registro
// depois — com o mesmo número (ID) — ou excluí-lo de vez.
//
// O que é copiado:
//   cliente → a linha de clientes (só clientes sem pedidos podem ser apagados)
//   produto → a linha de produtos + o histórico de estoque (só produtos nunca vendidos)
//   pedido  → a linha de pedidos + itens + parcelas (restaurar baixa o estoque de novo,
//             se o pedido era de venda)

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/estoque.php';

/** Rótulos das entidades que podem ir para a lixeira. */
function entidades_lixeira(): array
{
    return ['cliente' => 'Cliente', 'produto' => 'Produto', 'pedido' => 'Pedido'];
}

/** Cópia completa de um registro (e dos dados que dependem dele) antes de apagar. */
function lixeira_copia(PDO $pdo, string $entidade, int $id): ?array
{
    $uma = function (string $sql) use ($pdo, $id): ?array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    };
    $varias = function (string $sql) use ($pdo, $id): array {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    };

    return match ($entidade) {
        'cliente' => ($c = $uma('SELECT * FROM clientes WHERE ID = ?')) ? ['clientes' => $c] : null,
        'produto' => ($p = $uma('SELECT * FROM produtos WHERE ID = ?'))
            ? ['produtos' => $p, 'movimentos_estoque' => $varias('SELECT * FROM movimentos_estoque WHERE PRODUTO_ID = ? ORDER BY ID')]
            : null,
        'pedido' => ($p = $uma('SELECT * FROM pedidos WHERE ID = ?'))
            ? [
                'pedidos'      => $p,
                'itens_pedido' => $varias('SELECT * FROM itens_pedido WHERE PEDIDO_ID = ? ORDER BY ID'),
                'parcelas'     => $varias('SELECT * FROM parcelas WHERE PEDIDO_ID = ? ORDER BY NUMERO'),
            ]
            : null,
        default => null,
    };
}

/** Guarda a cópia na lixeira. Chame dentro da mesma transação do DELETE. */
function lixeira_guardar(PDO $pdo, string $entidade, int $id, string $descricao, array $copia): void
{
    $pdo->prepare(
        'INSERT INTO lixeira (ENTIDADE, ENTIDADE_ID, DESCRICAO, DADOS, USUARIO_ID, USUARIO_NOME) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $entidade, $id, mb_substr($descricao, 0, 255),
        json_encode($copia, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $_SESSION['usuario_id'] ?? null, $_SESSION['usuario_nome'] ?? null,
    ]);
}

/** INSERT de uma linha a partir de um array coluna => valor (as colunas vêm da cópia, nunca do usuário). */
function lixeira_inserir(PDO $pdo, string $tabela, array $linha): void
{
    $colunas = array_keys($linha);
    foreach ($colunas as $coluna) {
        if (!preg_match('/^[A-Z_]+$/', $coluna)) {
            throw new RuntimeException('Cópia inválida na lixeira.');
        }
    }
    $sql = "INSERT INTO $tabela (" . implode(', ', $colunas) . ') VALUES (' . implode(', ', array_fill(0, count($colunas), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($linha));
}

/** true se existe a linha $id na tabela (para não restaurar referências quebradas). */
function lixeira_existe(PDO $pdo, string $tabela, ?int $id): bool
{
    if ($id === null) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM $tabela WHERE ID = ?");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Restaura um item da lixeira. Retorna null se deu certo ou a mensagem de erro
 * (nesse caso nada é alterado). Deve ser chamada dentro de uma transação.
 */
function lixeira_restaurar(PDO $pdo, array $item): ?string
{
    $dados = json_decode($item['DADOS'], true);
    $id = (int)$item['ENTIDADE_ID'];
    $opcional = fn(string $tabela, $valor) => $valor !== null && lixeira_existe($pdo, $tabela, (int)$valor) ? $valor : null;

    try {
        switch ($item['ENTIDADE']) {
            case 'cliente':
                lixeira_inserir($pdo, 'clientes', $dados['clientes']);
                return null;

            case 'produto':
                $produto = $dados['produtos'];
                $produto['CATEGORIA_ID'] = $opcional('categorias', $produto['CATEGORIA_ID'] ?? null);
                lixeira_inserir($pdo, 'produtos', $produto);
                foreach ($dados['movimentos_estoque'] as $mov) {
                    unset($mov['ID']);
                    $mov['PEDIDO_ID'] = $opcional('pedidos', $mov['PEDIDO_ID']);
                    $mov['USUARIO_ID'] = $opcional('usuarios', $mov['USUARIO_ID']);
                    $mov['FORNECEDOR_ID'] = $opcional('fornecedores', $mov['FORNECEDOR_ID'] ?? null);
                    lixeira_inserir($pdo, 'movimentos_estoque', $mov);
                }
                return null;

            case 'pedido':
                $pedido = $dados['pedidos'];
                if (!lixeira_existe($pdo, 'clientes', (int)$pedido['CLIENTE_ID'])) {
                    return 'O cliente deste pedido não existe mais. Restaure o cliente primeiro (se ele estiver na lixeira).';
                }
                foreach ($dados['itens_pedido'] as $i) {
                    if (!lixeira_existe($pdo, 'produtos', (int)$i['PRODUTO_ID'])) {
                        return 'Um produto deste pedido (#' . $i['PRODUTO_ID'] . ') não existe mais. Restaure o produto primeiro.';
                    }
                }
                $pedido['USUARIO_ID'] = $opcional('usuarios', $pedido['USUARIO_ID']);
                lixeira_inserir($pdo, 'pedidos', $pedido);
                foreach ($dados['itens_pedido'] as $i) {
                    unset($i['ID']);
                    lixeira_inserir($pdo, 'itens_pedido', $i);
                }
                foreach ($dados['parcelas'] as $p) {
                    unset($p['ID']);
                    lixeira_inserir($pdo, 'parcelas', $p);
                }
                // O estoque foi devolvido ao apagar: pedido de venda baixa de novo
                if (status_eh_venda($pedido['STATUS'])) {
                    $faltando = baixar_estoque($pdo, $id, "Pedido #$id restaurado da lixeira");
                    if ($faltando) {
                        return 'Estoque insuficiente para restaurar o pedido: ' . implode(', ', $faltando) . '.';
                    }
                }
                return null;
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'Já existe um registro com o mesmo número, email, CPF/CNPJ, nome ou código. Altere o registro existente e tente de novo.';
        }
        throw $e;
    }
    return 'Tipo de registro desconhecido.';
}
