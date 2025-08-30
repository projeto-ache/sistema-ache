// backend/server.js
import express from "express";
import dotenv from "dotenv";
import cors from "cors";
import { GoogleGenerativeAI } from "@google/generative-ai";
import PROMPT_CONTENT from "./prompt-content.js";

dotenv.config();

const app = express();
app.use(express.json());
app.use(cors());

const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

const chats = {};

app.post("/chat", async (req, res) => {
  const { message, sessionId, history } = req.body;

  try {
    const model = genAI.getGenerativeModel({ model: "gemini-1.5-pro-latest" });

    const chat = chats[sessionId] || model.startChat({ history });

    chats[sessionId] = chat;

    const result = await chat.sendMessage(PROMPT_CONTENT + message);
    const response = await result.response;
    const text = response.text();

    res.json({ reply: text });
  } catch (error) {
    console.error("Erro ao processar requisição:", error);
    res.status(500).json({ error: "Erro ao conectar com a API do Gemini" });
  }
});

app.listen(5000, () =>
  console.log("Servidor rodando em http://localhost:5000")
);
