// Comportamentos compartilhados por todas as páginas internas:
// - toasts (mensagens que somem sozinhas)
// - modal de confirmação de exclusão

document.addEventListener("DOMContentLoaded", function () {
  configurarToasts();
  configurarModalExclusao();
});

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
    // Navegador sem suporte a <dialog>: usa a confirmação padrão.
    document.addEventListener("click", function (e) {
      const link = e.target.closest("[data-confirmar-exclusao]");
      if (link && !confirm("Tem certeza que deseja apagar " + link.dataset.confirmarExclusao + "?")) {
        e.preventDefault();
      }
    });
    return;
  }

  const nome = modal.querySelector("[data-modal-nome]");
  const confirmar = modal.querySelector("[data-modal-confirmar]");
  const cancelar = modal.querySelector("[data-modal-cancelar]");

  document.addEventListener("click", function (e) {
    const link = e.target.closest("[data-confirmar-exclusao]");
    if (!link) return;

    e.preventDefault();
    nome.textContent = link.dataset.confirmarExclusao || "este registro";
    confirmar.href = link.href;
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
