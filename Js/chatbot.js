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

// Botão flutuante para abrir o chat
const openButton = document.createElement("button");
openButton.id = "chatbot-open";
openButton.textContent = "💬";
document.body.appendChild(openButton);

// CSS do chatbot (injetado no documento)
const style = document.createElement("style");
style.textContent = `
#chatbot-open {
  position: fixed;
  bottom: 20px;
  right: 20px;
  background: #007bff;
  color: white;
  border: none;
  border-radius: 50%;
  width: 55px;
  height: 55px;
  font-size: 24px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

#chatbot-container {
  position: fixed;
  bottom: 80px;
  right: 20px;
  width: 350px;
  height: 500px;
  background: white;
  border-radius: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.3);
  display: none;
  flex-direction: column;
  overflow: hidden;
}

#chatbot-header {
  background: #007bff;
  color: white;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

#chatbot-messages {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
}

#chatbot-input-area {
  display: flex;
  border-top: 1px solid #ccc;
}

#chatbot-input {
  flex: 1;
  border: none;
  padding: 10px;
}

#chatbot-send {
  background: #007bff;
  color: white;
  border: none;
  padding: 10px;
  cursor: pointer;
}
`;
document.head.appendChild(style);

// Abrir e fechar chatbot
openButton.addEventListener("click", () => {
  chatbotContainer.style.display = "flex";
  openButton.style.display = "none";
});
document.getElementById("chatbot-close").addEventListener("click", () => {
  chatbotContainer.style.display = "none";
  openButton.style.display = "block";
});
