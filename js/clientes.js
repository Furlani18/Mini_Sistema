document.addEventListener("DOMContentLoaded", function () {
  const busca = document.getElementById("buscaClientes");
  const linhas = Array.from(document.querySelectorAll("[data-cliente-row]"));
  const resultado = document.getElementById("resultadoBusca");

  if (!busca || !resultado) {
    return;
  }

  busca.addEventListener("input", function () {
    const termo = this.value.trim().toLowerCase();
    let encontrados = 0;

    linhas.forEach(function (linha) {
      const corresponde = linha.dataset.search.includes(termo);
      linha.style.display = corresponde ? "" : "none";
      if (corresponde) {
        encontrados += 1;
      }
    });

    resultado.textContent = termo
      ? encontrados + (encontrados === 1 ? " cliente encontrado" : " clientes encontrados")
      : "Total de clientes: " + linhas.length;
  });
});