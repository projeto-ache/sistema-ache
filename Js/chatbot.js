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

// CSS do chatbot (injetado no documento)
// CSS atualizado do chatbot
const style = document.createElement("style");
style.textContent = `
#chatbot-bar {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 250px;
  padding: 12px 15px;
  border: 1px solid #ccc;
  border-radius: 25px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  font-size: 14px;
  outline: none;
  background: #fff;
  color: #333;
}

#chatbot-container {
  position: fixed;
  bottom: 80px;
  right: 20px;
  width: 350px;
  height: 500px;
  background: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.3);
  display: none;
  flex-direction: column;
  overflow: hidden;
  font-family: Arial, sans-serif;
}

#chatbot-header {
  background: #f5f5f5; /* cinza claro para combinar */
  color: #333;
  padding: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: bold;
  border-bottom: 1px solid #ddd;
}

#chatbot-messages {
  flex: 1;
  padding: 10px;
  overflow-y: auto;
  background: #fff;
  color: #333;
}

#chatbot-input-area {
  display: flex;
  border-top: 1px solid #ccc;
}

#chatbot-input {
  flex: 1;
  border: none;
  padding: 10px;
  outline: none;
}

#chatbot-send {
  background: #333; /* botão no tom neutro */
  color: #fff;
  border: none;
  padding: 10px 15px;
  cursor: pointer;
  border-radius: 0 0 10px 0;
}

#chatbot-send:hover {
  background: #555;
}
`;
document.head.appendChild(style);

// Abrir e fechar chatbot
searchBar.addEventListener("focus", () => {
  chatbotContainer.style.display = "flex";
  searchBar.style.display = "none";
});
document.getElementById("chatbot-close").addEventListener("click", () => {
  chatbotContainer.style.display = "none";
  searchBar.style.display = "block";
});
