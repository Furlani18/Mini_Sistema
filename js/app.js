// Comportamentos compartilhados por todas as páginas internas:
// - toasts (mensagens que somem sozinhas)
// - modal de confirmação de exclusão
// - filtros que se aplicam sozinhos (select com data-auto-submit)
// - botão de modo claro/escuro (a escolha fica salva no navegador)

document.addEventListener("DOMContentLoaded", function () {
  configurarToasts();
  configurarModalExclusao();
  configurarAutoEnvio();
  configurarTema();
});

/* ---------- Modo claro / escuro ---------- */

function configurarTema() {
  const botao = document.querySelector("[data-alternar-tema]");
  if (!botao) return;
  const raiz = document.documentElement;

  function atualizarRotulo() {
    const escuro = raiz.getAttribute("data-tema") === "escuro";
    botao.setAttribute("aria-pressed", escuro ? "true" : "false");
    botao.title = escuro ? "Mudar para o modo claro" : "Mudar para o modo escuro";
  }

  botao.addEventListener("click", function () {
    const escuro = raiz.getAttribute("data-tema") !== "escuro";
    // Classe temporária: anima só as cores durante a troca
    raiz.classList.add("tema-transicao");
    if (escuro) {
      raiz.setAttribute("data-tema", "escuro");
    } else {
      raiz.removeAttribute("data-tema");
    }
    localStorage.setItem("tema", escuro ? "escuro" : "claro");
    atualizarRotulo();
    setTimeout(function () { raiz.classList.remove("tema-transicao"); }, 300);
  });

  atualizarRotulo();
}

/* ---------- Filtros: enviar o formulário ao trocar a opção ---------- */

function configurarAutoEnvio() {
  document.querySelectorAll("select[data-auto-submit]").forEach(function (select) {
    select.addEventListener("change", function () {
      // Ao mudar um filtro, volta para a página 1 da lista
      const pagina = select.form.querySelector('input[name="pagina"]');
      if (pagina) pagina.remove();
      select.form.submit();
    });
  });
}

/* ---------- Toasts ---------- */

const TEMPO_TOAST = 4500; // milissegundos

function configurarToasts() {
  document.querySelectorAll(".toast").forEach(function (toast) {
    let timer = setTimeout(fechar, TEMPO_TOAST);

    // Pausa enquanto o mouse está em cima, para dar tempo de ler.
    toast.addEventListener("mouseenter", function () {
      clearTimeout(timer);
    });
    toast.addEventListener("mouseleave", function () {
      timer = setTimeout(fechar, 2000);
    });
    toast.querySelector(".toast-close").addEventListener("click", fechar);

    function fechar() {
      clearTimeout(timer);
      toast.classList.add("is-leaving");
      toast.addEventListener("animationend", function () {
        toast.remove();
      }, { once: true });
    }
  });
}

/* ---------- Modal de confirmação de exclusão ---------- */

function configurarModalExclusao() {
  const modal = document.getElementById("modalConfirmar");
  if (!modal || typeof modal.showModal !== "function") {
    // Navegador sem suporte a <dialog>: usa a confirmação padrão e envia o mesmo formulário (POST).
    const formReserva = modal && modal.querySelector("[data-modal-form]");
    document.addEventListener("click", function (e) {
      const link = e.target.closest("[data-confirmar-exclusao]");
      if (!link) return;
      e.preventDefault();
      if (formReserva && confirm("Tem certeza que deseja apagar " + link.dataset.confirmarExclusao + "?")) {
        formReserva.action = link.href;
        formReserva.submit();
      }
    });
    return;
  }

  const nome = modal.querySelector("[data-modal-nome]");
  const aviso = modal.querySelector("[data-modal-aviso]");
  const formulario = modal.querySelector("[data-modal-form]");
  const cancelar = modal.querySelector("[data-modal-cancelar]");

  document.addEventListener("click", function (e) {
    const link = e.target.closest("[data-confirmar-exclusao]");
    if (!link) return;

    e.preventDefault();
    nome.textContent = link.dataset.confirmarExclusao || "este registro";
    // Links com data-lixeira: o registro vai para a lixeira e pode ser restaurado
    aviso.textContent = link.hasAttribute("data-lixeira")
      ? "Ele vai para a Lixeira, de onde um administrador pode restaurá-lo."
      : "Esta ação não pode ser desfeita.";
    formulario.action = link.href; // a página de exclusão só aceita POST com token
    modal.showModal();
    cancelar.focus();
  });

  cancelar.addEventListener("click", function () {
    modal.close();
  });

  // Clicar fora da caixa (no fundo escuro) também fecha.
  modal.addEventListener("click", function (e) {
    if (e.target === modal) modal.close();
  });
}
