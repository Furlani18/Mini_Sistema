// Busca instantânea nas listas (clientes, produtos e pedidos), sem recarregar a página.
//
// Marcação esperada na página:
//   <input type="search" data-busca>                         campo de busca
//   <tr data-search="texto em minúsculas">                   cada linha pesquisável
//   <p id="resultadoBusca" data-singular="cliente"
//      data-plural="clientes">                               contador
//   <p id="semResultados">                                   mensagem "nada encontrado"

document.addEventListener("DOMContentLoaded", function () {
  const busca = document.querySelector("[data-busca]");
  const linhas = Array.from(document.querySelectorAll("tr[data-search]"));
  const resultado = document.getElementById("resultadoBusca");
  const semResultados = document.getElementById("semResultados");

  if (!busca || !resultado) {
    return;
  }

  const singular = resultado.dataset.singular || "registro";
  const plural = resultado.dataset.plural || "registros";

  function contar(n) {
    return n + " " + (n === 1 ? singular : plural);
  }

  busca.addEventListener("input", function () {
    const termo = this.value.trim().toLowerCase();
    let encontrados = 0;

    linhas.forEach(function (linha) {
      const corresponde = linha.dataset.search.includes(termo);
      linha.hidden = !corresponde;
      if (corresponde) {
        encontrados += 1;
      }
    });

    resultado.textContent = termo
      ? encontrados + " de " + contar(linhas.length)
      : contar(linhas.length);

    if (semResultados) {
      semResultados.classList.toggle("is-visible", encontrados === 0);
    }
  });
});
