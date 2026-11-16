// Máscara de valores em reais para campos com o atributo data-mascara-moeda.
// O usuário digita só os números e o valor se forma da direita para a esquerda,
// como em uma calculadora: 1 → 0,01 | 150 → 1,50 | 150000 → 1.500,00.
// Limite: 10 dígitos = 99.999.999,99 (o máximo da coluna DECIMAL(10,2) do banco).

document.addEventListener("DOMContentLoaded", function () {
  const MAX_DIGITOS = 10;
  const formato = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function formatar(texto) {
    const digitos = texto.replace(/\D/g, "").replace(/^0+(?=\d)/, "").slice(0, MAX_DIGITOS);
    return digitos === "" ? "" : formato.format(Number(digitos) / 100);
  }

  document.querySelectorAll("[data-mascara-moeda]").forEach(function (campo) {
    // Valor vindo do servidor (ex.: "3499,90") já aparece formatado: "3.499,90"
    if (campo.value !== "") {
      const partes = campo.value.split(",");
      const centavos = ((partes[1] || "") + "00").slice(0, 2);
      campo.value = formatar(partes[0] + centavos);
    }

    campo.addEventListener("input", function () {
      campo.value = formatar(campo.value);
      // Mantém o cursor no fim, onde os novos dígitos entram
      campo.setSelectionRange(campo.value.length, campo.value.length);
    });
  });
});
