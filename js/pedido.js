// Formulário de pedido (novo e edição): adicionar/remover linhas de itens e calcular
// subtotal, desconto e total. O cálculo aqui é só para exibição; o servidor recalcula tudo.

document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("linhasItens");
  const botaoAdicionar = document.getElementById("adicionarItem");
  const total = document.getElementById("totalPedido");
  const subtotalEl = document.getElementById("subtotalPedido");
  const descontoEl = document.getElementById("descontoPedido");
  const campoDesconto = document.getElementById("desconto");

  if (!container || !botaoAdicionar || !total) {
    return;
  }

  const formatar = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });

  function linhas() {
    return Array.from(container.querySelectorAll("[data-item-linha]"));
  }

  function atualizar() {
    let soma = 0;
    const todas = linhas();

    todas.forEach(function (linha) {
      const opcao = linha.querySelector("[data-produto]").selectedOptions[0];
      const preco = opcao && opcao.dataset.preco ? parseFloat(opcao.dataset.preco) : 0;
      const qtd = Math.max(0, parseInt(linha.querySelector("[data-quantidade]").value, 10) || 0);
      const subtotal = preco * qtd;

      linha.querySelector("[data-subtotal]").textContent = preco ? formatar.format(subtotal) : "—";
      soma += subtotal;
    });

    // Com uma linha só, não deixa remover (o pedido precisa de pelo menos um item)
    todas.forEach(function (linha) {
      linha.querySelector("[data-remover-item]").disabled = todas.length === 1;
    });

    // Desconto digitado como "1.234,56"
    const desconto = campoDesconto
      ? parseFloat(campoDesconto.value.replace(/\./g, "").replace(",", ".")) || 0
      : 0;
    if (subtotalEl) subtotalEl.textContent = formatar.format(soma);
    if (descontoEl) descontoEl.textContent = "− " + formatar.format(desconto);
    total.textContent = formatar.format(Math.max(0, soma - desconto));
    if (campoDesconto) {
      campoDesconto.closest(".form-group").classList.toggle("has-error", desconto > soma && soma > 0);
    }
  }

  botaoAdicionar.addEventListener("click", function () {
    const modelo = linhas()[0];
    const nova = modelo.cloneNode(true);
    nova.querySelector("[data-produto]").value = "";
    nova.querySelector("[data-quantidade]").value = "1";
    container.appendChild(nova);
    nova.querySelector("[data-produto]").focus();
    atualizar();
  });

  container.addEventListener("click", function (e) {
    const botao = e.target.closest("[data-remover-item]");
    if (botao && linhas().length > 1) {
      botao.closest("[data-item-linha]").remove();
      atualizar();
    }
  });

  container.addEventListener("input", atualizar);
  container.addEventListener("change", atualizar);
  if (campoDesconto) campoDesconto.addEventListener("input", atualizar);

  // Adicionar por código: digite (ou passe no leitor de código de barras) o SKU ou
  // o código de barras e tecle Enter. Se o produto já está no pedido, soma 1 unidade.
  const campoCodigo = document.querySelector("[data-busca-codigo]");
  const statusCodigo = document.getElementById("buscaCodigoStatus");
  if (campoCodigo) {
    campoCodigo.addEventListener("keydown", function (e) {
      if (e.key !== "Enter") return;
      e.preventDefault(); // não envia o formulário
      const codigo = campoCodigo.value.trim().toLowerCase();
      if (!codigo) return;

      const opcao = Array.from(linhas()[0].querySelectorAll("[data-produto] option")).find(function (o) {
        return o.dataset.codigos && o.dataset.codigos.split(" ").indexOf(codigo) !== -1;
      });
      if (!opcao) {
        statusCodigo.textContent = "Nenhum produto com o código “" + campoCodigo.value.trim() + "”.";
        statusCodigo.classList.add("is-erro");
        campoCodigo.select();
        return;
      }

      const existente = linhas().find(function (l) { return l.querySelector("[data-produto]").value === opcao.value; });
      if (existente) {
        const qtd = existente.querySelector("[data-quantidade]");
        qtd.value = (parseInt(qtd.value, 10) || 0) + 1;
      } else {
        // Usa uma linha vazia, se houver; senão, cria uma nova
        let linha = linhas().find(function (l) { return !l.querySelector("[data-produto]").value; });
        if (!linha) {
          botaoAdicionar.click();
          linha = linhas()[linhas().length - 1];
        }
        linha.querySelector("[data-produto]").value = opcao.value;
        linha.querySelector("[data-quantidade]").value = "1";
      }
      statusCodigo.textContent = "Adicionado: " + opcao.textContent.trim().split(" — ")[0];
      statusCodigo.classList.remove("is-erro");
      campoCodigo.value = "";
      campoCodigo.focus();
      atualizar();
    });
  }

  // Botão de salvar acompanha o status escolhido (orçamento x pedido)
  const status = document.querySelector("[data-status-pedido]");
  const rotuloSalvar = document.querySelector("[data-rotulo-salvar]");
  if (status && rotuloSalvar) {
    status.addEventListener("change", function () {
      rotuloSalvar.textContent = status.value === "ORCAMENTO" ? "Salvar Orçamento" : "Registrar Pedido";
    });
  }

  atualizar();
});
