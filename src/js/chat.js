// captura evento de tecla para enviar mensagem ao pressionar ENTER
document
  .getElementById("chat-input")
  .addEventListener("keypress", function (event) {
    if (event.key === "Enter") {
      sendMessage();
    }
  });

////////// Controles de Interacao por Voz - Modo Conversacao /////////////

// Reconhecimento de fala
const SpeechRecognition =
  window.SpeechRecognition || window.webkitSpeechRecognition;
const recognition = new SpeechRecognition();
recognition.lang = "pt-BR";
recognition.interimResults = false;

var modo_conversa = false; //controla a ativação do modo conversacao

const synth = window.speechSynthesis; //sintese de fala para as respostas
var utterance; //instancia da sintese de voz

//quando uma fala e reconhecida, converte em texto, exibe e envia para a Dotty
recognition.onresult = function (event) {
  const spokenText = event.results[0][0].transcript;
  document.getElementById("chat-input").value = spokenText;
  document.getElementById("send-button").click(); // Envia automaticamente após falar
};

recognition.onerror = function (event) {
  // alert("Erro ao reconhecer voz: " + event.error);
  const reply =
    "Não estou conseguindo te ouvir. Vou desativar o modo de conversação. Se quiser retornar, basta acionar o botão novamente.";
  const chat = document.getElementById("chat-messages");
  chat.innerHTML += `<div><strong>Dotty:</strong> ${reply}</div>`;
  document.getElementById("chat-loading").style.display = "none";
  // Fala a resposta da IA se estiver no modo conversacao
  if (modo_conversa) {
    speak(reply);
  }
  ativa_conversa();
};

function ativa_conversa() {
  modo_conversa = !modo_conversa;
  if (modo_conversa) {
    recognition.start();
    document.getElementById("voice-button").style.background = "red";
  } else {
    document.getElementById("voice-button").style.background = "#367a8d";
    synth.cancel();
  }
}

// Texto para fala
function speak(text) {
  //const synth = window.speechSynthesis;
  utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = "pt-BR";

  // Estimar duração baseada em caracteres
  const tempoEstimado = text.length * 70; // 70ms por caractere

  // Fallback: reiniciar reconhecimento após o tempo estimado
  fallbackTimer = setTimeout(() => {
    if (modo_conversa && !synth.speaking) {
      console.warn("Fallback estimado ativado.");
      recognition.start();
    }
  }, tempoEstimado + 500); // Adiciona uma margem extra

  // Se onend funcionar, cancela o fallback
  utterance.onend = function () {
    clearTimeout(fallbackTimer);
    if (modo_conversa) recognition.start();
  };

  synth.speak(utterance);
}

//////////////////////////////////////////////////////////////////////////

function sendMessage(url) {
  // define o caminho completo para o texto extraido da bula
  // urlAction e uma vaiavel global definida em index.js

  let urlBula = "";

  if (url && url.trim() !== "") {
    urlBula = url;
  } else {
    let index = urlAction.indexOf("?");
    urlBula = urlAction.substring(0, index) + ".txt";
  }

  let inputField = document.getElementById("chat-input");
  let message = inputField.value.trim();

  if (message === "") return;

  displayMessage("Você: " + message, "user");
  document.getElementById("chat-loading").style.display = "flex"; //exibe icone piscando para aguardar a resposta

  inputField.value = "";

  fetch("src/php/chat.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ message: message, bula: urlBula }),
    //body: JSON.stringify({ message: message }),
  })
    .then((response) => response.json())
    .then((data) => {
      displayMessage("Dotty: " + data.response, "bot");
      document.getElementById("chat-loading").style.display = "none"; //oculta icone piscando
    })
    .catch((error) => console.error("Erro:", error));
}

function displayMessage(text, sender) {
  let chatMessages = document.getElementById("chat-messages");
  let messageElement = document.createElement("div");

  let textHTML = marked.parse(text); // Converte Markdown em HTML

  //messageElement.textContent = text;
  messageElement.innerHTML = textHTML;
  messageElement.style.padding = "8px";
  messageElement.style.margin = "5px 0";
  messageElement.style.borderRadius = "5px";
  messageElement.style.color = "black";

  messageElement.classList.add("chat-bubble");

  if (sender === "user") {
    messageElement.style.background = "#d1e7fd";
    messageElement.style.alignSelf = "flex-end";
    messageElement.classList.add("user-message");
  } else {
    messageElement.style.background = "#e2e2e2";
    messageElement.style.alignSelf = "flex-start";
    messageElement.classList.add("bot-message");
  }

  chatMessages.appendChild(messageElement);
  //chatMessages.scrollTop = chatMessages.scrollHeight;
  chatMessages.scrollTop = messageElement.offsetTop;

  // Fala a resposta da IA se estiver no modo conversacao
  if (modo_conversa && sender === "bot") {
    function limparMarkdown(texto) {
      return texto
        .replace(/[\u200B-\u200D\uFEFF]/g, "") // Remove caracteres invisíveis
        .replace(/[`*_>#+\-|]/g, "") // Remove markdown básico
        .replace(/\[(.*?)\]\(.*?\)/g, "$1") // Links: [texto](url) → texto
        .replace(/!\[(.*?)\]\(.*?\)/g, "") // Imagens: remove
        .replace(/<\/?[^>]+(>|$)/g, "") // Remove HTML renderizado
        .replace(/^\s*[\W_]+/, "") // Remove símbolos no início
        .replace(/\n{2,}/g, ". ") // Duplas quebras → ponto
        .replace(/\n/g, " ") // Restante → espaço
        .replace(/\s{2,}/g, " ") // Espaços duplos → simples
        .trim(); // Remove espaços finais
    }

    // Remove prefixo "Dotty: " antes de falar
    const textoSemPrefixo = text.replace(/^Dotty:\s*/, "");
    const textoLimpo = limparMarkdown(textoSemPrefixo);
    speak(textoLimpo);
  }
}