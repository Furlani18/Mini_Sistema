// Validação e interatividade do formulário de cadastro

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("formCadastro");

  if (form) {
    // Validação em tempo real
    const inputNome = document.getElementById("nome");
    const inputEmail = document.getElementById("email");
    const inputTelefone = document.getElementById("telefone");

    // Validar nome
    if (inputNome) {
      inputNome.addEventListener("blur", function () {
        if (this.value.trim().length < 3) {
          this.style.borderColor = "#e74c3c";
          showTooltip(this, "Nome deve ter pelo menos 3 caracteres");
        } else {
          this.style.borderColor = "#2ecc71";
        }
      });
    }

    // Validar email
    if (inputEmail) {
      inputEmail.addEventListener("blur", function () {
        if (!isValidEmail(this.value)) {
          this.style.borderColor = "#e74c3c";
          showTooltip(this, "Email inválido");
        } else {
          this.style.borderColor = "#2ecc71";
        }
      });
    }

    // Formatar telefone
    if (inputTelefone) {
      inputTelefone.addEventListener("input", function () {
        this.value = formatarTelefone(this.value);
      });
    }

    // Validar ao submeter
    form.addEventListener("submit", function (e) {
      const nome = inputNome.value.trim();
      const email = inputEmail.value.trim();

      if (nome.length < 3) {
        e.preventDefault();
        alert("Nome deve ter pelo menos 3 caracteres");
        inputNome.focus();
        return false;
      }

      if (!isValidEmail(email)) {
        e.preventDefault();
        alert("Email inválido");
        inputEmail.focus();
        return false;
      }

      return true;
    });
  }
});

// Função para validar email
function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

// Função para formatar telefone
function formatarTelefone(valor) {
  valor = valor.replace(/\D/g, "");

  if (valor.length > 11) {
    valor = valor.substring(0, 11);
  }

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

// Função para mostrar tooltip de validação
function showTooltip(element, message) {
  let tooltip = element.parentElement.querySelector(".tooltip");

  if (tooltip) {
    tooltip.remove();
  }

  tooltip = document.createElement("small");
  tooltip.className = "tooltip";
  tooltip.style.color = "#e74c3c";
  tooltip.style.display = "block";
  tooltip.style.marginTop = "5px";
  tooltip.textContent = message;
  element.parentElement.appendChild(tooltip);
}
