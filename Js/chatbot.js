// Cria container do chatbot
const chatbotContainer = document.createElement("div");
chatbotContainer.id = "chatbot-container";
chatbotContainer.innerHTML = `
  <div id="chatbot-header">
    <span>Assistente Virtual</span>
    <button id="chatbot-close">✖</button>
  </div>
  <div id="chatbot-messages"></div>
  <div id="chatbot-input-area">
    <input type="text" id="chatbot-input" placeholder="Digite sua pergunta..." />
    <button id="chatbot-send">➤</button>
  </div>
`;
document.body.appendChild(chatbotContainer);

// Cria barra de pesquisa flutuante para abrir o chat
const searchBar = document.createElement("input");
searchBar.id = "chatbot-bar";
searchBar.type = "text";
searchBar.placeholder = "Pergunte ao assistente...";
document.body.appendChild(searchBar);

// Abre e fecha chatbot
searchBar.addEventListener("focus", () => {
  chatbotContainer.style.display = "flex";
  searchBar.style.display = "none";
});
document.getElementById("chatbot-close").addEventListener("click", () => {
  chatbotContainer.style.display = "none";
  searchBar.style.display = "block";
});

// -------------------------
// Conectar Frontend ao Chatbot
// -------------------------
const chatbotMessages = document.getElementById("chatbot-messages");
const chatbotInput = document.getElementById("chatbot-input");
const chatbotSend = document.getElementById("chatbot-send");

// Função para exibir mensagens no chat
function addMessage(sender, text) {
  const container = document.createElement("div");
  const message = document.createElement("div");

  container.classList.add("chatbot-message-container", sender === "Você" ? "user" : "bot");
  message.classList.add("chatbot-message", sender === "Você" ? "user" : "bot");
  message.textContent = text;
  
  container.appendChild(message);
  chatbotMessages.appendChild(container);
  chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

// Enviar mensagem para API
async function sendMessage() {
  const text = chatbotInput.value.trim();
  if (!text) return;

  addMessage("Você", text);
  chatbotInput.value = "";

  try {
    const response = await fetch("http://localhost:5000/chat", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ message: text })
    });

    const data = await response.json();
    addMessage("Assistente", data.reply || "Não entendi a pergunta.");
  } catch (error) {
    console.error(error);
    addMessage("Assistente", "Erro de conexão com o servidor.");
  }
}

chatbotSend.addEventListener("click", sendMessage);
chatbotInput.addEventListener("keypress", (e) => {
  if (e.key === "Enter") sendMessage();
});