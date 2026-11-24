// Validação dos formulários de cliente e de usuário (cadastro, atualização e perfil).
// Mostra o erro embaixo de cada campo, com a borda vermelha, em vez de alert().
// As regras são as mesmas do validacao.php (servidor).
//
// Marcação: <form data-validar data-entidade="usuário">  (padrão: "cliente")
// Só os campos que existem no formulário são validados.

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("form[data-validar]").forEach(configurarFormulario);
});

const SENHA_MINIMA = 6;

const regras = {
  nome: function (valor, input, entidade) {
    if (valor === "") return "Informe o nome do " + entidade + ".";
    if (valor.length < 3) return "O nome deve ter pelo menos 3 caracteres.";
    return "";
  },
  email: function (valor, input, entidade) {
    if (valor === "") return "Informe o email do " + entidade + ".";
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) {
      return "Digite um email válido, por exemplo nome@empresa.com.";
    }
    return "";
  },
  telefone: function (valor) {
    const digitos = valor.replace(/\D/g, "");
    if (valor !== "" && (digitos.length < 10 || digitos.length > 11)) {
      return "O telefone deve ter DDD + 8 ou 9 dígitos.";
    }
    return "";
  },
  // Na edição a senha é opcional (campo sem "required"): em branco = não trocar
  senha: function (valor, input) {
    if (valor === "" && !input.required) return "";
    if (valor === "") return "Informe a senha.";
    if (valor.length < SENHA_MINIMA) return "A senha deve ter pelo menos " + SENHA_MINIMA + " caracteres.";
    return "";
  },
  senha_confirmacao: function (valor, input) {
    const senha = input.form.elements.senha ? input.form.elements.senha.value.trim() : "";
    if (valor !== senha) return "As senhas não conferem.";
    return "";
  },
};

function configurarFormulario(form) {
  const entidade = form.dataset.entidade || "cliente";
  const campos = Object.keys(regras)
    .map(function (nome) { return form.elements[nome]; })
    .filter(Boolean);

  function validar(input) {
    return validarCampo(input, entidade);
  }

  campos.forEach(function (input) {
    // Valida ao sair do campo; depois do primeiro erro, revalida enquanto digita.
    input.addEventListener("blur", function () {
      // A confirmação em branco só é cobrada no envio (o usuário ainda pode estar indo até ela)
      if (input.name === "senha_confirmacao" && input.value === "") return;
      validar(input);
    });
    input.addEventListener("input", function () {
      if (input.name === "telefone") {
        input.value = formatarTelefone(input.value);
      }
      if (input.getAttribute("aria-invalid") === "true") {
        validar(input);
      }
      // Trocar a senha revalida a confirmação já preenchida
      const confirmacao = form.elements.senha_confirmacao;
      if (input.name === "senha" && confirmacao && confirmacao.value !== "") {
        validar(confirmacao);
      }
    });
  });

  form.addEventListener("submit", function (e) {
    let primeiroInvalido = null;
    campos.forEach(function (input) {
      if (!validar(input) && !primeiroInvalido) {
        primeiroInvalido = input;
      }
    });

    if (primeiroInvalido) {
      e.preventDefault();
      primeiroInvalido.focus();
    }
  });
}

function validarCampo(input, entidade) {
  // trim() em tudo, inclusive senhas: o login.php também ignora espaços nas pontas
  const valor = input.value.trim();
  const mensagem = regras[input.name](valor, input, entidade);
  const grupo = input.closest(".form-group");
  const erro = grupo.querySelector(".field-error span");

  grupo.classList.toggle("has-error", mensagem !== "");
  grupo.classList.toggle("is-valid", mensagem === "" && valor !== "");
  input.setAttribute("aria-invalid", mensagem ? "true" : "false");
  erro.textContent = mensagem;

  return mensagem === "";
}

function formatarTelefone(valor) {
  valor = valor.replace(/\D/g, "").substring(0, 11);

  if (valor.length > 10) {
    return "(" + valor.substring(0, 2) + ") " + valor.substring(2, 7) + "-" + valor.substring(7);
  }
  if (valor.length > 6) {
    return "(" + valor.substring(0, 2) + ") " + valor.substring(2, 6) + "-" + valor.substring(6);
  }
  if (valor.length > 2) {
    return "(" + valor.substring(0, 2) + ") " + valor.substring(2);
  }
  return valor;
}
