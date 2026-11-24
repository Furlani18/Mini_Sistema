// Dica (tooltip) do gráfico de colunas dos relatórios.
// Aparece ao passar o mouse ou ao focar uma coluna com o teclado (Tab),
// mostrando o período, o faturamento e a quantidade de pedidos.

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("[data-grafico]").forEach(configurarGrafico);
});

function configurarGrafico(grafico) {
  const dica = grafico.querySelector(".grafico-dica");
  const colunas = grafico.querySelectorAll(".coluna");

  function mostrar(coluna) {
    colunas.forEach(function (c) { c.classList.toggle("is-ativa", c === coluna); });

    dica.innerHTML = "";
    const titulo = document.createElement("strong");
    titulo.textContent = coluna.dataset.titulo;
    const valor = document.createElement("span");
    valor.className = "grafico-dica-valor";
    valor.textContent = coluna.dataset.valor;
    const extra = document.createElement("span");
    extra.textContent = coluna.dataset.extra;
    dica.append(titulo, valor, extra);
    dica.hidden = false;

    // Posiciona acima da barra, centralizada na coluna, sem sair do gráfico
    const caixa = grafico.getBoundingClientRect();
    const barra = coluna.querySelector(".coluna-barra").getBoundingClientRect();
    const largura = dica.offsetWidth;
    let esquerda = barra.left - caixa.left + barra.width / 2 - largura / 2;
    esquerda = Math.max(0, Math.min(esquerda, caixa.width - largura));
    const topo = Math.max(0, barra.top - caixa.top - dica.offsetHeight - 10);
    dica.style.left = esquerda + "px";
    dica.style.top = topo + "px";
  }

  function esconder() {
    dica.hidden = true;
    colunas.forEach(function (c) { c.classList.remove("is-ativa"); });
  }

  colunas.forEach(function (coluna) {
    coluna.addEventListener("mouseenter", function () { mostrar(coluna); });
    coluna.addEventListener("focus", function () { mostrar(coluna); });
    coluna.addEventListener("blur", esconder);
  });
  grafico.addEventListener("mouseleave", esconder);
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") esconder();
  });
}
