<?php
// Campos do formulário de cliente, usados no cadastro e na atualização.
//
// Variáveis esperadas:
//   $valores = ['nome' => '', 'email' => '', 'telefone' => ''];
//   $erros   = ['campo' => 'mensagem'];  (vazio quando não há erros)

$campos = [
    'nome' => [
        'rotulo'      => 'Nome Completo',
        'obrigatorio' => true,
        'atributos'   => 'type="text" placeholder="Digite o nome completo" autocomplete="name" minlength="3"',
        'ajuda'       => 'Informe nome e sobrenome, com pelo menos 3 caracteres.',
    ],
    'email' => [
        'rotulo'      => 'Email',
        'obrigatorio' => true,
        'atributos'   => 'type="email" placeholder="nome@empresa.com" autocomplete="email"',
        'ajuda'       => 'Usaremos este email para identificar o cadastro.',
    ],
    'telefone' => [
        'rotulo'      => 'Telefone',
        'obrigatorio' => false,
        'atributos'   => 'type="tel" placeholder="(11) 99999-9999" inputmode="numeric" autocomplete="tel"',
        'ajuda'       => 'Opcional. Digite apenas números; a formatação é aplicada automaticamente.',
    ],
];

include __DIR__ . '/form_campos.php';
