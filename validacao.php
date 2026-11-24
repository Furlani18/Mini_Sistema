<?php
// Validação dos dados de cliente e de usuário, usada nos cadastros e nas atualizações.
// As regras são as mesmas do js/formulario.js.

/** Tamanho mínimo da senha dos usuários. */
const SENHA_MINIMA = 6;

/**
 * Valida nome, email e telefone. Retorna um array campo => mensagem de erro
 * (vazio quando está tudo certo). $entidade entra nas mensagens ("do cliente", "do usuário").
 */
function validar_pessoa(string $nome, string $email, string $telefone, string $entidade): array
{
    $erros = [];

    if ($nome === '') {
        $erros['nome'] = "Informe o nome do $entidade.";
    } elseif (mb_strlen($nome) < 3) {
        $erros['nome'] = 'O nome deve ter pelo menos 3 caracteres.';
    }

    if ($email === '') {
        $erros['email'] = "Informe o email do $entidade.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Digite um email válido, por exemplo nome@empresa.com.';
    }

    $digitos = preg_replace('/\D/', '', $telefone);
    if ($telefone !== '' && (strlen($digitos) < 10 || strlen($digitos) > 11)) {
        $erros['telefone'] = 'O telefone deve ter DDD + 8 ou 9 dígitos.';
    }

    return $erros;
}

function validar_cliente(string $nome, string $email, string $telefone): array
{
    return validar_pessoa($nome, $email, $telefone, 'cliente');
}

/**
 * Valida a senha nova e a confirmação.
 * $obrigatoria = false permite deixar as duas em branco (edição sem trocar a senha).
 */
function validar_senha(string $senha, string $confirmacao, bool $obrigatoria): array
{
    $erros = [];

    if ($senha === '' && $confirmacao === '' && !$obrigatoria) {
        return $erros;
    }
    if ($senha === '') {
        $erros['senha'] = 'Informe a senha.';
    } elseif (mb_strlen($senha) < SENHA_MINIMA) {
        $erros['senha'] = 'A senha deve ter pelo menos ' . SENHA_MINIMA . ' caracteres.';
    }
    if ($confirmacao !== $senha) {
        $erros['senha_confirmacao'] = 'As senhas não conferem.';
    }

    return $erros;
}
