// Foto do produto: mostra a prévia e reduz a imagem NO NAVEGADOR antes de enviar
// (lado maior até 1200 px, JPEG com qualidade 85%). Assim uma foto de celular de 5 MB
// vira um arquivo de ~200 KB, e o servidor não precisa de bibliotecas de imagem.
// Se o navegador não conseguir reduzir, o arquivo original é enviado (o servidor confere).
//
// Marcação: [data-foto] com [data-foto-campo] (input file), [data-foto-previa],
// [data-foto-status] e, opcional, [data-foto-remover] (checkbox "Remover foto").

const FOTO_LADO_MAXIMO = 1200;
const FOTO_QUALIDADE = 0.85;

document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("[data-foto]").forEach(configurarFoto);
});

function configurarFoto(caixa) {
  const campo = caixa.querySelector("[data-foto-campo]");
  const previa = caixa.querySelector("[data-foto-previa]");
  const status = caixa.querySelector("[data-foto-status]");
  const remover = caixa.querySelector("[data-foto-remover]");
  const textoOriginal = status ? status.textContent : "";

  function mostrar(arquivo) {
    const img = document.createElement("img");
    img.alt = "Prévia da foto";
    img.src = URL.createObjectURL(arquivo);
    previa.replaceChildren(img);
  }

  function avisar(texto) {
    if (status) status.textContent = texto || textoOriginal;
  }

  if (remover) {
    remover.addEventListener("change", function () {
      previa.classList.toggle("is-removendo", remover.checked);
    });
  }

  campo.addEventListener("change", async function () {
    const arquivo = campo.files && campo.files[0];
    if (!arquivo) return;
    if (!/^image\/(jpeg|png|webp)$/.test(arquivo.type)) {
      avisar("Escolha uma imagem JPG, PNG ou WebP.");
      campo.value = "";
      return;
    }
    if (remover) {
      remover.checked = false;
      previa.classList.remove("is-removendo");
    }
    mostrar(arquivo);
    avisar("Preparando a foto…");

    try {
      const reduzida = await reduzirImagem(arquivo);
      if (reduzida && reduzida.size < arquivo.size) {
        const lista = new DataTransfer();
        lista.items.add(reduzida);
        campo.files = lista.files;
      }
      const final = campo.files[0];
      avisar("Foto pronta (" + Math.max(1, Math.round(final.size / 1024)) + " KB). Clique em salvar para gravar.");
    } catch (e) {
      avisar("Foto escolhida. Clique em salvar para gravar.");
    }
  });
}

/** Desenha a imagem num canvas menor e devolve um File JPEG (ou null se não precisar). */
function reduzirImagem(arquivo) {
  return new Promise(function (resolve, reject) {
    const img = new Image();
    img.onload = function () {
      const escala = Math.min(1, FOTO_LADO_MAXIMO / Math.max(img.naturalWidth, img.naturalHeight));
      if (escala === 1 && arquivo.size < 400 * 1024) {
        resolve(null); // já é pequena
        return;
      }
      const canvas = document.createElement("canvas");
      canvas.width = Math.round(img.naturalWidth * escala);
      canvas.height = Math.round(img.naturalHeight * escala);
      const ctx = canvas.getContext("2d");
      ctx.fillStyle = "#ffffff"; // PNG transparente ganha fundo branco no JPEG
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(function (blob) {
        if (!blob) { reject(new Error("sem blob")); return; }
        const nome = arquivo.name.replace(/\.[^.]+$/, "") + ".jpg";
        resolve(new File([blob], nome, { type: "image/jpeg" }));
      }, "image/jpeg", FOTO_QUALIDADE);
      URL.revokeObjectURL(img.src);
    };
    img.onerror = reject;
    img.src = URL.createObjectURL(arquivo);
  });
}
