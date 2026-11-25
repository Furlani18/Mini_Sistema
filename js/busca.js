// Busca global: Ctrl+K (ou Cmd+K, ou "/") abre uma janela que procura clientes, produtos,
// pedidos e fornecedores ao mesmo tempo (busca_global.php responde em JSON).
// Setas ↑ ↓ escolhem o resultado, Enter abre, Esc fecha.

document.addEventListener("DOMContentLoaded", function () {
  const dialogo = document.getElementById("buscaGlobal");
  if (!dialogo || typeof dialogo.showModal !== "function") return;

  const campo = dialogo.querySelector("[data-busca-campo]");
  const lista = dialogo.querySelector("[data-busca-resultados]");
  const status = dialogo.querySelector("[data-busca-status]");
  let espera = null;
  let controle = null;
  let selecionado = -1;

  function abrir() {
    if (dialogo.open) return;
    dialogo.showModal();
    campo.select();
  }

  function itens() {
    return Array.from(lista.querySelectorAll("[data-busca-item]"));
  }

  function marcar(indice) {
    const todos = itens();
    if (!todos.length) return;
    selecionado = (indice + todos.length) % todos.length;
    todos.forEach(function (item, i) {
      item.classList.toggle("is-ativo", i === selecionado);
      item.setAttribute("aria-selected", i === selecionado ? "true" : "false");
    });
    todos[selecionado].scrollIntoView({ block: "nearest" });
    campo.setAttribute("aria-activedescendant", todos[selecionado].id);
  }

  function mostrar(grupos, termo) {
    lista.innerHTML = "";
    selecionado = -1;
    campo.removeAttribute("aria-activedescendant");
    if (!grupos.length) {
      status.textContent = "Nada encontrado para “" + termo + "”.";
      return;
    }
    let total = 0;
    grupos.forEach(function (grupo) {
      const titulo = document.createElement("li");
      titulo.className = "busca-grupo";
      titulo.setAttribute("role", "presentation");
      titulo.innerHTML = grupo.icone; // SVG gerado pelo servidor (ui.php), não vem do usuário
      titulo.append(" " + grupo.titulo);
      lista.appendChild(titulo);

      grupo.itens.forEach(function (r) {
        const li = document.createElement("li");
        li.setAttribute("role", "option");
        li.id = "buscaItem" + total++;
        li.dataset.buscaItem = "";
        const link = document.createElement("a");
        link.href = r.url;
        link.tabIndex = -1;
        const forte = document.createElement("strong");
        forte.textContent = r.titulo; // textContent: o texto nunca vira HTML
        const detalhe = document.createElement("small");
        detalhe.textContent = r.detalhe;
        link.append(forte, detalhe);
        li.appendChild(link);
        lista.appendChild(li);
      });
    });
    status.textContent = total === 1 ? "1 resultado" : total + " resultados";
    marcar(0);
  }

  function buscar() {
    const termo = campo.value.trim();
    if (controle) controle.abort();
    if (termo.length < 2) {
      lista.innerHTML = "";
      status.textContent = "Digite pelo menos 2 letras: nome, email, nº do pedido, SKU…";
      return;
    }
    controle = new AbortController();
    status.textContent = "Buscando…";
    fetch("busca_global.php?q=" + encodeURIComponent(termo), { signal: controle.signal, headers: { Accept: "application/json" } })
      .then(function (r) {
        if (!r.ok || (r.headers.get("Content-Type") || "").indexOf("json") === -1) throw new Error("sessao");
        return r.json();
      })
      .then(function (dados) { mostrar(dados.grupos || [], termo); })
      .catch(function (erro) {
        if (erro.name === "AbortError") return;
        status.textContent = "Não foi possível buscar. Recarregue a página e tente de novo.";
      });
  }

  campo.addEventListener("input", function () {
    clearTimeout(espera);
    espera = setTimeout(buscar, 200); // espera o usuário parar de digitar
  });

  campo.addEventListener("keydown", function (e) {
    if (e.key === "ArrowDown") { e.preventDefault(); marcar(selecionado + 1); }
    else if (e.key === "ArrowUp") { e.preventDefault(); marcar(selecionado - 1); }
    else if (e.key === "Enter") {
      e.preventDefault();
      const item = itens()[selecionado];
      if (item) window.location.href = item.querySelector("a").href;
    }
  });

  lista.addEventListener("mousemove", function (e) {
    const item = e.target.closest("[data-busca-item]");
    if (item) marcar(itens().indexOf(item));
  });

  dialogo.addEventListener("click", function (e) {
    if (e.target === dialogo) dialogo.close(); // clique fora da caixa
  });

  document.querySelectorAll("[data-abrir-busca]").forEach(function (botao) {
    botao.addEventListener("click", abrir);
  });

  document.addEventListener("keydown", function (e) {
    const digitando = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) || document.activeElement.isContentEditable;
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      abrir();
    } else if (e.key === "/" && !digitando && !dialogo.open) {
      e.preventDefault();
      abrir();
    }
  });
});
