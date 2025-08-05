///////////////////////////////////////////////////////

//// Navegar entre formulaŕios de Login, Registro, TIpos de BUla, Eventos...

//////////////////////////////////////////////////////

function mostrarFormulario(tipo) {
  //fechaVersoes();

  document
    .querySelectorAll(".formulario")
    .forEach((f) => f.classList.remove("ativo"));
  document
    .querySelectorAll(".tab-button")
    .forEach((b) => b.classList.remove("active"));

  document.getElementById(tipo).classList.add("ativo");
  document
    .querySelector(`.tab-button[onclick="mostrarFormulario('${tipo}')"]`)
    .classList.add("active");
}

///////////////////////////////////////////////////////

//// Ativar e Desativar elementos, definindo o tipo de exibicao (flex, block, ...)

//////////////////////////////////////////////////////

function alternar(elementoAtivo, elementoInativo, tipo) {
  document.getElementById(elementoAtivo).style.display = "none"; //invativa o elemento ativo
  document.getElementById(elementoInativo).style.display = tipo; //ativa o elemento inativo
}

// funcao para recarregar (atualizar) a pagina sem perder os dados volateis como controle de sessao
function recarregar(url) {
  // se url nao informada, assume a pagina atual
  if (!url) {
    url = window.location.href;
  }
  // Adiciona um parâmetro para evitar cache
  const cacheBuster = "no_cache=" + Date.now();
  const separator = url.includes("?") ? "&" : "?";
  const freshUrl = url + separator + cacheBuster;

  fetch(freshUrl)
    .then((response) => response.text())
    .then((html) => {
      document.open();
      document.write(html);
      document.close();
    });

  // Atualiza a URL visível no navegador, sem o parâmetro no_cache
  history.replaceState({}, "", url);
}
