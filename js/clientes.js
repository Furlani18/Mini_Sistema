// Busca instantânea na lista de clientes.

document.addEventListener("DOMContentLoaded", function () {
  const busca = document.getElementById("buscaClientes");
  const linhas = Array.from(document.querySelectorAll("[data-cliente-row]"));
  const resultado = document.getElementById("resultadoBusca");
  const semResultados = document.getElementById("semResultados");

  if (!busca || !resultado) {
    return;
  }

  function plural(n) {
    return n + (n === 1 ? " cliente" : " clientes");
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
      ? encontrados + " de " + plural(linhas.length)
      : plural(linhas.length);

    if (semResultados) {
      semResultados.classList.toggle("is-visible", encontrados === 0);
    }
  });
});
