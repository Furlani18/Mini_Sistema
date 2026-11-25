<?php
// Parcelas dos pedidos (contas a receber).
//
// Cada pedido de venda pode ser dividido em 1 a 12 parcelas. Quando todas as
// parcelas são recebidas e o pedido ainda está "Aberto", ele passa para "Pago".
// Orçamentos e pedidos cancelados não geram parcelas e não entram no contas a receber.

require_once __DIR__ . '/ui.php';

const PARCELAS_MAXIMO = 12;

/**
 * Divide o total em $quantidade parcelas, com vencimentos a cada $intervaloDias.
 * Os centavos que sobram da divisão vão para a primeira parcela
 * (ex.: R$ 100,00 em 3x → 33,34 + 33,33 + 33,33).
 * Retorna [['numero' => 1, 'valor' => 33.34, 'vencimento' => 'AAAA-MM-DD'], ...].
 */
function dividir_parcelas(float $total, int $quantidade, string $primeiroVencimento, int $intervaloDias = 30): array
{
    $centavos = (int)round($total * 100);
    $base = intdiv($centavos, $quantidade);
    $sobra = $centavos - $base * $quantidade;

    $parcelas = [];
    $data = new DateTime($primeiroVencimento);
    for ($n = 1; $n <= $quantidade; $n++) {
        $parcelas[] = [
            'numero'     => $n,
            'valor'      => ($base + ($n === 1 ? $sobra : 0)) / 100,
            'vencimento' => $data->format('Y-m-d'),
        ];
        $data->modify("+$intervaloDias days");
    }
    return $parcelas;
}

/** Situação de uma parcela: 'paga', 'vencida' ou 'a_vencer' (vence hoje ainda não é vencida). */
function situacao_parcela(array $parcela, ?string $hoje = null): string
{
    if (!empty($parcela['PAGO_EM'])) {
        return 'paga';
    }
    return $parcela['VENCIMENTO'] < ($hoje ?? date('Y-m-d')) ? 'vencida' : 'a_vencer';
}

/** Rótulos das situações de parcela. */
function situacoes_parcela(): array
{
    return ['a_vencer' => 'A vencer', 'vencida' => 'Vencida', 'paga' => 'Recebida'];
}

function badge_parcela(string $situacao): string
{
    return '<span class="badge badge-parcela-' . str_replace('_', '-', $situacao) . '">' . situacoes_parcela()[$situacao] . '</span>';
}

/** Parcelas de um pedido, em ordem. */
function parcelas_do_pedido(PDO $pdo, int $pedidoId): array
{
    $stmt = $pdo->prepare('SELECT * FROM parcelas WHERE PEDIDO_ID = ? ORDER BY NUMERO');
    $stmt->execute([$pedidoId]);
    return $stmt->fetchAll();
}

/**
 * Depois de registrar um recebimento: se todas as parcelas estão pagas e o pedido
 * está "Aberto", muda para "Pago". Retorna true se mudou.
 */
function marcar_pedido_pago_se_quitado(PDO $pdo, int $pedidoId): bool
{
    $stmt = $pdo->prepare(
        "UPDATE pedidos p
            SET p.STATUS = 'PAGO'
          WHERE p.ID = ? AND p.STATUS = 'ABERTO'
            AND EXISTS (SELECT 1 FROM parcelas WHERE PEDIDO_ID = p.ID)
            AND NOT EXISTS (SELECT 1 FROM parcelas WHERE PEDIDO_ID = p.ID AND PAGO_EM IS NULL)"
    );
    $stmt->execute([$pedidoId]);
    return $stmt->rowCount() > 0;
}
