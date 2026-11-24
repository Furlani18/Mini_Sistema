// Formulário de cliente: máscaras de CPF/CNPJ e CEP e busca automática do endereço pelo CEP.
//
// A busca usa o serviço público ViaCEP (https://viacep.com.br), chamado direto pelo
// navegador. Sem internet, o endereço simplesmente é digitado à mão.
//
// Marcação esperada:
//   <input data-mascara-documento>              CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00)
//   <input data-cep>                            CEP (00000-000)
//   <input data-endereco="logradouro|bairro|localidade|complemento">, <select data-endereco="uf">
//   <p data-cep-status>                         mensagem "Buscando…", "CEP não encontrado"…

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("[data-mascara-documento]").forEach(function (campo) {
    campo.addEventListener("input", function () {
      campo.value = formatarDocumento(campo.value);
    });
  });

  const cep = document.querySelector("[data-cep]");
  if (cep) configurarCep(cep);
});

function formatarDocumento(valor) {
  const d = valor.replace(/\D/g, "").slice(0, 14);
  if (d.length <= 11) {
    // CPF: 000.000.000-00
    return d
      .replace(/^(\d{3})(\d)/, "$1.$2")
      .replace(/^(\d{3})\.(\d{3})(\d)/, "$1.$2.$3")
      .replace(/\.(\d{3})(\d{1,2})$/, ".$1-$2");
  }
  // CNPJ: 00.000.000/0000-00
  return d
    .replace(/^(\d{2})(\d)/, "$1.$2")
    .replace(/^(\d{2})\.(\d{3})(\d)/, "$1.$2.$3")
    .replace(/\.(\d{3})(\d)/, ".$1/$2")
    .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
}

function configurarCep(campo) {
  const status = document.querySelector("[data-cep-status]");
  let ultimoBuscado = campo.value.replace(/\D/g, "");

  function avisar(texto, tipo) {
    if (!status) return;
    status.textContent = texto;
    status.className = "cep-status" + (tipo ? " is-" + tipo : "");
  }

  campo.addEventListener("input", function () {
    const d = campo.value.replace(/\D/g, "").slice(0, 8);
    campo.value = d.length > 5 ? d.slice(0, 5) + "-" + d.slice(5) : d;
    if (d.length === 8 && d !== ultimoBuscado) {
      ultimoBuscado = d;
      buscar(d);
    }
  });

  async function buscar(digitos) {
    avisar("Buscando endereço…", "carregando");
    const controle = new AbortController();
    const tempo = setTimeout(function () { controle.abort(); }, 6000);
    try {
      const resposta = await fetch("https://viacep.com.br/ws/" + digitos + "/json/", { signal: controle.signal });
      const dados = await resposta.json();
      if (dados.erro) {
        avisar("CEP não encontrado. Confira o número ou preencha o endereço à mão.", "erro");
        return;
      }
      document.querySelectorAll("[data-endereco]").forEach(function (alvo) {
        const valor = dados[alvo.dataset.endereco] || "";
        // Complemento só é preenchido se estiver vazio (o do cliente costuma ser mais específico)
        if (alvo.dataset.endereco === "complemento" && alvo.value.trim() !== "") return;
        if (valor !== "" || alvo.dataset.endereco !== "complemento") alvo.value = valor;
      });
      avisar("Endereço preenchido pelo CEP. Confira e informe o número.", "ok");
      const numero = document.getElementById("numero");
      if (numero && numero.value.trim() === "") numero.focus();
    } catch (e) {
      avisar("Não foi possível consultar o CEP agora. Preencha o endereço à mão.", "erro");
    } finally {
      clearTimeout(tempo);
    }
  }
}
