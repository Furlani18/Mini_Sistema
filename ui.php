<?php
// Funções de interface compartilhadas: ícones SVG e mensagens temporárias (toasts).

/** Produtos com estoque até este valor aparecem como "estoque baixo" (painel e lista de produtos). */
const ESTOQUE_BAIXO = 5;

/**
 * Retorna um ícone SVG embutido (traços do conjunto Lucide, licença ISC).
 * Os ícones herdam a cor do texto (currentColor), então seguem o estilo de cada botão/link.
 */
function icone(string $nome, string $classe = ''): string
{
    static $icones = [
        'plus'          => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'lista'         => '<rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/>',
        'editar'        => '<path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z"/><path d="m15 5 4 4"/>',
        'lixeira'       => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>',
        'home'          => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'usuarios'      => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'usuario-mais'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/>',
        'usuario'       => '<circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 0 0-16 0"/>',
        'sair'          => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'entrar'        => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/>',
        'salvar'        => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>',
        'busca'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'fechar'        => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'sucesso'       => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'erro'          => '<circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>',
        'alerta'        => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'pacote'        => '<path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><path d="m3.3 7 7.703 4.734a2 2 0 0 0 1.994 0L20.7 7"/><path d="m7.5 4.27 9 5.15"/>',
        'carrinho'      => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
        'banco'         => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
        'email'         => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'telefone'      => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'calendario'    => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'grafico'       => '<path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
        'download'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'cadeado'       => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'escudo'        => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
        'lua'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sol'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'historico'     => '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/>',
        'upload'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
        'impressora'    => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/><rect x="6" y="14" width="12" height="8" rx="1"/>',
        'mapa'          => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'documento'     => '<path d="M16 10h2"/><path d="M16 14h2"/><path d="M6.17 15a3 3 0 0 1 5.66 0"/><circle cx="9" cy="11" r="2"/><rect x="2" y="5" width="20" height="14" rx="2"/>',
        'movimento'     => '<path d="M7 21 3 17l4-4"/><path d="M3 17h18"/><path d="m17 3 4 4-4 4"/><path d="M21 7H3"/>',
        'cartao'        => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'nota'          => '<path d="M16 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8Z"/><path d="M15 3v4a2 2 0 0 0 2 2h4"/>',
        'olho'          =>'<path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/>',
    ];

    $conteudo = $icones[$nome] ?? '';
    $classes = trim('icon ' . $classe);

    return '<svg class="' . $classes . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $conteudo . '</svg>';
}

/** Iniciais do nome para o avatar (ex.: "Maria da Silva" → "MS"). */
function iniciais(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome)) ?: [];
    $partes = array_values(array_filter($partes, fn($p) => mb_strlen($p) > 2));
    if (!$partes) {
        return mb_strtoupper(mb_substr(trim($nome), 0, 1)) ?: '?';
    }
    $primeira = mb_substr($partes[0], 0, 1);
    $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';
    return mb_strtoupper($primeira . $ultima);
}

/** Formata um valor em reais (ex.: 1234.5 → "R$ 1.234,50"). */
function moeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

/** Maior valor que cabe nas colunas DECIMAL(10,2) (preço, desconto). */
const VALOR_MAXIMO = 99999999.99;

/**
 * Converte um valor em reais digitado em número. Aceita "1.499,90" (formato da máscara),
 * "1499,90", "1499.90" e "1.500" (ponto como milhar). Retorna null se for inválido.
 */
function converter_moeda(string $texto): ?float
{
    $texto = str_replace(['R$', ' '], '', $texto);
    if (strpos($texto, ',') !== false) {
        $texto = str_replace(['.', ','], ['', '.'], $texto);   // "1.499,90" → "1499.90"
    } elseif (!preg_match('/^\d+\.\d{1,2}$/', $texto)) {
        $texto = str_replace('.', '', $texto);                  // "1.500" → "1500"
    }
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $texto)) {
        return null;
    }
    return round((float)$texto, 2);
}

/** Formas de pagamento do pedido (valor no banco => rótulo na tela). */
function formas_pagamento(): array
{
    return [
        'PIX'      => 'Pix',
        'DINHEIRO' => 'Dinheiro',
        'CREDITO'  => 'Cartão de crédito',
        'DEBITO'   => 'Cartão de débito',
        'BOLETO'   => 'Boleto',
    ];
}

/** CPF (11 dígitos) ou CNPJ (14 dígitos) formatado; vazio se não informado. */
function formatar_documento(?string $digitos): string
{
    $d = preg_replace('/\D/', '', (string)$digitos);
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9);
    }
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12);
    }
    return $d;
}

/** CEP formatado (00000-000); vazio se não informado. */
function formatar_cep(?string $cep): string
{
    $d = preg_replace('/\D/', '', (string)$cep);
    return strlen($d) === 8 ? substr($d, 0, 5) . '-' . substr($d, 5) : $d;
}

/**
 * Endereço em uma ou duas linhas a partir das colunas do cliente
 * (LOGRADOURO, NUMERO, COMPLEMENTO, BAIRRO, CIDADE, UF, CEP). Vazio se não houver endereço.
 */
function endereco_cliente(array $c): string
{
    $rua = trim(($c['LOGRADOURO'] ?? '') . (($c['NUMERO'] ?? '') !== '' ? ', ' . $c['NUMERO'] : '')
        . (($c['COMPLEMENTO'] ?? '') !== '' ? ' - ' . $c['COMPLEMENTO'] : ''), ' ,-');
    $cidade = trim(($c['CIDADE'] ?? '') . (($c['UF'] ?? '') !== '' ? '/' . $c['UF'] : ''), '/');
    $partes = array_filter([$rua, $c['BAIRRO'] ?? '', $cidade, ($c['CEP'] ?? '') !== '' ? 'CEP ' . formatar_cep($c['CEP']) : '']);
    return implode(' · ', $partes);
}

/** Status possíveis de um pedido (valor no banco => rótulo na tela). */
function status_pedidos(): array
{
    return [
        'ABERTO'    => 'Aberto',
        'PAGO'      => 'Pago',
        'ENVIADO'   => 'Enviado',
        'CANCELADO' => 'Cancelado',
    ];
}

/** Etiqueta colorida com o status do pedido. */
function badge_status(string $status): string
{
    $rotulo = status_pedidos()[$status] ?? $status;
    return '<span class="badge badge-' . strtolower(htmlspecialchars($status)) . '">' . htmlspecialchars($rotulo) . '</span>';
}

/** Guarda uma mensagem para ser exibida como toast na próxima página carregada. */
function flash(string $tipo, string $mensagem): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensagem' => $mensagem];
}

/** Retorna (e apaga) as mensagens guardadas com flash(). */
function flash_pegar(): array
{
    $mensagens = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensagens;
}
