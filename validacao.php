<?php
// Validação dos dados de cliente, usada no cadastro e na atualização.

/**
 * Valida nome, email e telefone. Retorna um array campo => mensagem de erro
 * (vazio quando está tudo certo). As regras são as mesmas do js/formulario.js.
 */
function validar_cliente(string $nome, string $email, string $telefone): array
{
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = 'Informe o nome do cliente.';
    } elseif (mb_strlen($nome) < 3) {
        $erros['nome'] = 'O nome deve ter pelo menos 3 caracteres.';
    }

    if ($email === '') {
        $erros['email'] = 'Informe o email do cliente.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Digite um email válido, por exemplo nome@empresa.com.';
    }

    $digitos = preg_replace('/\D/', '', $telefone);
    if ($telefone !== '' && (strlen($digitos) < 10 || strlen($digitos) > 11)) {
        $erros['telefone'] = 'O telefone deve ter DDD + 8 ou 9 dígitos.';
    }

    return $erros;
}
