// Formulário de novo pedido: adicionar/remover linhas de itens e calcular o total.
// O cálculo aqui é só para exibição; o servidor usa o preço gravado em produtos.

document.addEventListener("DOMContentLoaded", function () {
  const container = document.getElementById("linhasItens");
  const botaoAdicionar = document.getElementById("adicionarItem");
  const total = document.getElementById("totalPedido");

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

    total.textContent = formatar.format(soma);
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
  atualizar();
});
