// Loja online: seletor de quantidade (− / +), carrinho que se atualiza sozinho,
// entrega x retirada, máscara de telefone e proteção contra clique duplo no envio.
// Tudo funciona sem JavaScript também (os formulários são enviados normalmente).

document.addEventListener("DOMContentLoaded", function () {
  configurarQuantidades();
  configurarEntrega();
  configurarTelefone();
  configurarEnvioUnico();
});

/* ---------- Quantidade: botões − / + respeitam o mínimo e o máximo do campo ---------- */

function configurarQuantidades() {
  document.querySelectorAll("[data-qtd]").forEach(function (caixa) {
    const campo = caixa.querySelector("input");
    const enviarSozinho = caixa.hasAttribute("data-qtd-enviar");
    let timer = null;

    function limitar(valor) {
      const min = Number(campo.min || 0);
      const max = Number(campo.max || 99);
      return Math.min(max, Math.max(min, isNaN(valor) ? min : valor));
    }

    function mudou() {
      if (!enviarSozinho) return;
      // No carrinho: espera o visitante parar de clicar e atualiza os totais
      campo.form.classList.add("tem-mudanca");
      clearTimeout(timer);
      timer = setTimeout(function () { campo.form.submit(); }, 700);
    }

    caixa.querySelector("[data-qtd-menos]").addEventListener("click", function () {
      const novo = limitar(Number(campo.value) - 1);
      if (novo !== Number(campo.value)) { campo.value = novo; mudou(); }
    });
    caixa.querySelector("[data-qtd-mais]").addEventListener("click", function () {
      const novo = limitar(Number(campo.value) + 1);
      if (novo !== Number(campo.value)) { campo.value = novo; mudou(); }
    });
    campo.addEventListener("change", function () {
      campo.value = limitar(parseInt(campo.value, 10));
      mudou();
    });
  });
}

/* ---------- Finalização: "Retirar na loja" esconde o endereço ---------- */

function configurarEntrega() {
  const endereco = document.querySelector("[data-endereco-entrega]");
  if (!endereco) return;
  document.querySelectorAll("[data-entrega]").forEach(function (opcao) {
    opcao.addEventListener("change", function () {
      endereco.hidden = opcao.value === "RETIRADA" && opcao.checked;
    });
  });
}

/* ---------- Telefone: (11) 91234-5678 ---------- */

function configurarTelefone() {
  document.querySelectorAll("[data-mascara-telefone]").forEach(function (campo) {
    campo.addEventListener("input", function () {
      const d = campo.value.replace(/\D/g, "").slice(0, 11);
      let texto = d;
      if (d.length > 2) texto = "(" + d.slice(0, 2) + ") " + d.slice(2);
      if (d.length > 6) {
        const meio = d.length === 11 ? 7 : 6;
        texto = "(" + d.slice(0, 2) + ") " + d.slice(2, meio) + "-" + d.slice(meio);
      }
      campo.value = texto;
    });
  });
}

/* ---------- Envio do pedido: impede dois pedidos iguais por clique duplo ---------- */

function configurarEnvioUnico() {
  const botao = document.querySelector("[data-enviar-pedido]");
  if (!botao) return;
  botao.form.addEventListener("submit", function () {
    setTimeout(function () {
      botao.disabled = true;
      botao.textContent = "Enviando pedido…";
    }, 0);
  });
}
