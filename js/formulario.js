// Validação dos formulários de cliente (cadastro e atualização).
// Mostra o erro embaixo de cada campo, com a borda vermelha, em vez de alert().
// As regras são as mesmas do validacao.php (servidor).

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("form[data-validar]").forEach(configurarFormulario);
});

const regras = {
  nome: function (valor) {
    if (valor === "") return "Informe o nome do cliente.";
    if (valor.length < 3) return "O nome deve ter pelo menos 3 caracteres.";
    return "";
  },
  email: function (valor) {
    if (valor === "") return "Informe o email do cliente.";
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
};

function configurarFormulario(form) {
  const campos = Object.keys(regras)
    .map(function (nome) { return form.elements[nome]; })
    .filter(Boolean);

  campos.forEach(function (input) {
    // Valida ao sair do campo; depois do primeiro erro, revalida enquanto digita.
    input.addEventListener("blur", function () {
      validarCampo(input);
    });
    input.addEventListener("input", function () {
      if (input.name === "telefone") {
        input.value = formatarTelefone(input.value);
      }
      if (input.getAttribute("aria-invalid") === "true") {
        validarCampo(input);
      }
    });
  });

  form.addEventListener("submit", function (e) {
    let primeiroInvalido = null;
    campos.forEach(function (input) {
      if (!validarCampo(input) && !primeiroInvalido) {
        primeiroInvalido = input;
      }
    });

    if (primeiroInvalido) {
      e.preventDefault();
      primeiroInvalido.focus();
    }
  });
}

function validarCampo(input) {
  const mensagem = regras[input.name](input.value.trim());
  const grupo = input.closest(".form-group");
  const erro = grupo.querySelector(".field-error span");

  grupo.classList.toggle("has-error", mensagem !== "");
  grupo.classList.toggle("is-valid", mensagem === "" && input.value.trim() !== "");
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
