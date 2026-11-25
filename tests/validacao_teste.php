<?php
// Regras de validação (validacao.php e ui.php): documentos, códigos, valores e datas.

teste('CPF com dígitos corretos é aceito', function () {
    verdadeiro(cpf_valido('52998224725'));
    verdadeiro(cpf_valido('11144477735'));
});

teste('CPF com dígito errado, repetido ou tamanho errado é recusado', function () {
    falso(cpf_valido('52998224724'), 'dígito verificador errado');
    falso(cpf_valido('11111111111'), 'todos os dígitos iguais');
    falso(cpf_valido('5299822472'), '10 dígitos');
});

teste('CNPJ válido e inválido', function () {
    verdadeiro(cnpj_valido('11222333000181'));
    falso(cnpj_valido('11222333000180'));
    falso(cnpj_valido('00000000000000'));
});

teste('Código de barras (GTIN) confere o dígito verificador', function () {
    verdadeiro(gtin_valido('7891234567895'), 'EAN-13');
    verdadeiro(gtin_valido('96385074'), 'EAN-8');
    falso(gtin_valido('7891234567890'), 'dígito errado');
    falso(gtin_valido('12345'), 'tamanho inválido');
    falso(gtin_valido('789123456789A'), 'letra');
});

teste('SKU aceita letras, números, ponto, hífen e sublinhado', function () {
    verdadeiro(sku_valido('MOU-001'));
    verdadeiro(sku_valido('cabo_usb.2m'));
    falso(sku_valido('com espaço'));
    falso(sku_valido(''));
    falso(sku_valido(str_repeat('A', 41)));
});

teste('converter_moeda entende os formatos digitados', function () {
    igual(1499.9, converter_moeda('1.499,90'));
    igual(1499.9, converter_moeda('1499,90'));
    igual(1499.9, converter_moeda('1499.90'));
    igual(1500.0, converter_moeda('1.500'));
    igual(1234.56, converter_moeda('R$ 1.234,56'));
    igual(null, converter_moeda('abc'));
    igual(null, converter_moeda('-10'));
});

teste('Datas: formato brasileiro vira AAAA-MM-DD e datas impossíveis são recusadas', function () {
    igual('1985-03-15', normalizar_data('15/03/1985'));
    igual('1985-03-05', normalizar_data('5/3/1985'));
    igual('1985-03-15', normalizar_data('1985-03-15'));
    verdadeiro(data_valida('2024-02-29'), 'ano bissexto');
    falso(data_valida('2023-02-29'), '29/02 em ano comum');
    falso(data_valida('31/12/2020'));
});

teste('Cliente: nascimento no futuro é recusado', function () {
    $c = cliente_vazio();
    $c['nome'] = 'Ana Teste';
    $c['email'] = 'ana@exemplo.com';
    $c['nascimento'] = date('Y-m-d', strtotime('+1 day'));
    verdadeiro(isset(validar_cliente_completo($c)['nascimento']));
    $c['nascimento'] = '1990-07-01';
    igual([], validar_cliente_completo($c));
});

teste('Formatação de CPF, CNPJ e CEP', function () {
    igual('529.982.247-25', formatar_documento('52998224725'));
    igual('11.222.333/0001-81', formatar_documento('11222333000181'));
    igual('01310-100', formatar_cep('01310100'));
});

teste('Link do WhatsApp: acrescenta o 55 e recusa números incompletos', function () {
    igual('https://wa.me/5511988887777', whatsapp_link('(11) 98888-7777'));
    igual('https://wa.me/5511988887777?text=Ol%C3%A1%21', whatsapp_link('+55 11 98888-7777', 'Olá!'));
    igual(null, whatsapp_link('98888-7777'), 'sem DDD');
    igual(null, whatsapp_link(null));
});

teste('Só ABERTO, PAGO e ENVIADO contam como venda', function () {
    verdadeiro(status_eh_venda('ABERTO'));
    verdadeiro(status_eh_venda('ENVIADO'));
    falso(status_eh_venda('ORCAMENTO'));
    falso(status_eh_venda('CANCELADO'));
});
