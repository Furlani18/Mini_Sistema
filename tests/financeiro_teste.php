<?php
// Parcelas e custo médio: as contas que mexem com dinheiro.

teste('Parcelas somam exatamente o total (centavos que sobram vão na 1ª)', function () {
    $parcelas = dividir_parcelas(100.00, 3, '2026-01-10');
    igual([33.34, 33.33, 33.33], array_column($parcelas, 'valor'));
    igual(10000, (int)round(array_sum(array_column($parcelas, 'valor')) * 100));
});

teste('Parcelas: vários totais e quantidades nunca perdem centavos', function () {
    foreach ([0.05, 1.00, 99.99, 1234.57, 3499.90] as $total) {
        foreach ([1, 2, 3, 5, 7, 12] as $n) {
            if ($total / $n < 0.01) {
                continue;
            }
            $soma = array_sum(array_map(fn($p) => (int)round($p['valor'] * 100), dividir_parcelas($total, $n, '2026-01-01')));
            igual((int)round($total * 100), $soma, "$total em {$n}x");
        }
    }
});

teste('Vencimentos seguem o intervalo escolhido', function () {
    igual(['2026-01-31', '2026-03-02', '2026-04-01'], array_column(dividir_parcelas(90, 3, '2026-01-31', 30), 'vencimento'));
    igual(['2026-05-01', '2026-05-08'], array_column(dividir_parcelas(10, 2, '2026-05-01', 7), 'vencimento'));
});

teste('Situação da parcela: vence hoje ainda não está vencida', function () {
    igual('a_vencer', situacao_parcela(['PAGO_EM' => null, 'VENCIMENTO' => '2026-06-10'], '2026-06-10'));
    igual('vencida', situacao_parcela(['PAGO_EM' => null, 'VENCIMENTO' => '2026-06-09'], '2026-06-10'));
    igual('paga', situacao_parcela(['PAGO_EM' => '2026-06-01', 'VENCIMENTO' => '2026-05-01'], '2026-06-10'));
});

teste('Custo médio ponderado', function () {
    igual(6.0, custo_medio(10, 5.0, 10, 7.0), '10 a R$5 + 10 a R$7');
    igual(7.0, custo_medio(0, 5.0, 10, 7.0), 'estoque zerado: vale o custo da entrada');
    igual(7.0, custo_medio(10, null, 10, 7.0), 'sem custo anterior');
    igual(5.67, custo_medio(2, 5.0, 1, 7.0), 'arredonda para centavos');
});
