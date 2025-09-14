// backend/server.js
import express from "express";
import dotenv from "dotenv";
import cors from "cors";
import { GoogleGenerativeAI } from "@google/generative-ai";
import PROMPT_CONTENT from "./prompt-content.js";
import { getProjectsByUser, getProjectsByMonth } from "./db-logic.js";
import { gerarExcel, gerarPDF } from "./report-generator.js";
import path from "path";
import fs from "fs";

dotenv.config();

const app = express();
app.use(express.json());
app.use(cors());

// rota para servir relatórios gerados
app.use("/reports", express.static(path.join(process.cwd(), "reports")));

const genAI = new GoogleGenerativeAI(process.env.GEMINI_API_KEY);

app.post("/chat", async (req, res) => {
  const { message } = req.body;
  const userId = 1; // 🔥 depois você pode trocar para o usuário logado

  try {
    // Lógica para pedidos de cronograma e relatórios
    if (
      message.toLowerCase().includes("mês") ||
      message.toLowerCase().includes("cronograma") ||
      message.toLowerCase().includes("projetos") ||
      message.toLowerCase().includes("relatório") ||
      message.toLowerCase().includes("relatorio")
    ) {
      const projetos = message.toLowerCase().includes("mês")
        ? await getProjectsByMonth(userId)
        : await getProjectsByUser(userId);

      if (projetos.length === 0) {
        return res.json({
          reply: "Você não está envolvido em nenhum projeto no momento.",
        });
      }

      // gerar relatórios
      const excelPath = await gerarExcel(projetos, userId);
      const pdfPath = gerarPDF(projetos, userId);

      // garantir caminhos relativos
      const excelUrl = `http://localhost:5000/${excelPath}`;
      const pdfUrl = `http://localhost:5000/${pdfPath}`;

      const respostaFormatada = projetos
        .map(
          (p) =>
            `* **Projeto**: ${
              p.NomeProjeto
            }\n  **Início**: ${p.DataCriacao.toISOString().slice(
              0,
              10
            )}\n  **Fim**: ${p.DataConclusao.toISOString().slice(0, 10)}`
        )
        .join("\n");

      return res.json({
        reply:
          "Aqui está o seu cronograma de projetos:\n" +
          respostaFormatada +
          `\n\n📂 Baixe seus relatórios:\n[Excel](${excelUrl}) | [PDF](${pdfUrl})`,
      });
    }

    // Lógica para interação com o Gemini
    const model = genAI.getGenerativeModel({ model: "gemini-2.5-pro" });
    const fullPrompt = `${PROMPT_CONTENT}${message}`;
    const result = await model.generateContent(fullPrompt);
    const response = await result.response;
    const replyText = response.text();

    res.json({
      reply: replyText,
    });
  } catch (error) {
    console.error("Erro ao processar requisição:", error);
    res.status(500).json({ error: "Erro interno no servidor." });
  }
});

app.listen(5000, () =>
  console.log("Servidor rodando em http://localhost:5000")
);
