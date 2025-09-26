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
const chatbotSendBtn = document.getElementById("chatbot-send");

<<<<<<< Updated upstream
// Função para exibir mensagens no chat
function addMessage(sender, content, isHtml = false) {
  const container = document.createElement("div");
  const message = document.createElement("div");

  container.classList.add(
    "chatbot-message-container",
    sender === "Você" ? "user" : "bot"
  );
  message.classList.add("chatbot-message", sender === "Você" ? "user" : "bot");

  if (isHtml) {
    message.innerHTML = content;
  } else {
    message.textContent = content;
  }
  container.appendChild(message);

  chatbotMessages.appendChild(container);
  chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
=======
// Adicionar mensagem ao chat
function addMessage(sender, text) {
  const messageContainer = document.createElement("div");
  const senderClass = sender.toLowerCase() === "você" ? "user" : "bot";
  messageContainer.classList.add("chatbot-message-container", senderClass);

  const messageElement = document.createElement("div");
  messageElement.classList.add("chatbot-message", senderClass);
  messageElement.innerHTML = `<strong>${sender}:</strong> ${text}`;

  messageContainer.appendChild(messageElement);
  chatbotMessages.appendChild(messageContainer);
  chatbotMessages.scrollTop = chatbotMessages.scrollHeight; // Auto-scroll
}

// Formatar resposta para HTML
function formatResponse(text) {
  let formattedText = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  formattedText = formattedText.replace(/\*\s/g, "<br>• ");
  formattedText = formattedText.replace(/\n/g, "<br>");
  formattedText = formattedText.replace(
    /\[(.*?)\]\((.*?)\)/g,
    '<a href="$2" target="_blank">$1</a>'
  );
  if (formattedText.startsWith("<br>")) {
    formattedText = formattedText.substring(4);
  }
  return formattedText;
>>>>>>> Stashed changes
}

// Formatar a resposta da IA
function formatResponse(text) {
  let formattedText = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  formattedText = formattedText.replace(/\*\s/g, "<br>• ");
  formattedText = formattedText.replace(/\n/g, "<br>");
  if (formattedText.startsWith("<br>")) {
    formattedText = formattedText.substring(4);
  }
  return formattedText;
}

function formatResponse(text) {
  let formattedText = text.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
  formattedText = formattedText.replace(/\*\s/g, "<br>• ");
  formattedText = formattedText.replace(/\n/g, "<br>");
  formattedText = formattedText.replace(
    /\[(.*?)\]\((.*?)\)/g,
    '<a href="$2" target="_blank">$1</a>'
  );
  if (formattedText.startsWith("<br>")) {
    formattedText = formattedText.substring(4);
  }
  return formattedText;
}

// Enviar mensagem para API
async function sendMessage() {
  const text = chatbotInput.value.trim();
  if (!text) return;

  // 1. Adiciona a mensagem do usuário
  addMessage("Você", text);
  chatbotInput.value = "";

  // 2. Cria e mostra a animação de loading
  const loadingContainer = document.createElement("div");
  loadingContainer.id = "loading-dots-container";
  loadingContainer.classList.add("chatbot-message-container", "bot");
  loadingContainer.style.display = "flex";
  loadingContainer.innerHTML = `
    <div class="chatbot-message bot">
      <span class="dot"></span>
      <span class="dot"></span>
      <span class="dot"></span>
    </div>
  `;
  chatbotMessages.appendChild(loadingContainer);
  chatbotMessages.scrollTop = chatbotMessages.scrollHeight;

  try {
    const response = await fetch("http://localhost:5000/chat", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
<<<<<<< Updated upstream
      body: JSON.stringify({ message: text }),
=======
      body: JSON.stringify({ message: text, userId: CURRENT_USER_ID })
>>>>>>> Stashed changes
    });

    const data = await response.json();

<<<<<<< Updated upstream
    console.log("Dados do backend:", data);

    const formattedReply = formatResponse(data.reply);

    addMessage("Assistente", formattedReply, true);
  } catch (error) {
    console.error("Erro ao enviar mensagem:", error);
    addMessage("Assistente", "Desculpe, houve um erro. Tente novamente.");
  }
}

chatbotSend.addEventListener("click", sendMessage);
chatbotInput.addEventListener("keypress", (e) => {
  if (e.key === "Enter") sendMessage();
=======
    const formattedReply = formatResponse(data.reply);

    // 3. Remove a animação de loading
    loadingContainer.remove();

    // 4. Adiciona a resposta do assistente
    addMessage("Assistente", formattedReply);
  } catch (error) {
    console.error("Erro ao comunicar com o servidor:", error);

    // Remove a animação mesmo se houver erro
    loadingContainer.remove();
    addMessage(
      "Assistente",
      "Desculpe, não consegui me conectar. Tente novamente mais tarde."
    );
  }
}

chatbotSendBtn.addEventListener("click", sendMessage);
chatbotInput.addEventListener("keydown", (e) => {
  if (e.key === "Enter") {
    sendMessage();
  }
>>>>>>> Stashed changes
});
