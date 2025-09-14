import ExcelJS from "exceljs";
import PDFDocument from "pdfkit";
import fs from "fs";
import path from "path";

function ensureReportsDir() {
  const reportsDir = path.join(process.cwd(), "reports");
  if (!fs.existsSync(reportsDir)) {
    fs.mkdirSync(reportsDir);
  }
  return reportsDir;
}

export async function gerarExcel(projetos, userId) {
  const reportsDir = ensureReportsDir();
  const filePath = path.join(reportsDir, `relatorio_${userId}.xlsx`);

  const workbook = new ExcelJS.Workbook();
  const sheet = workbook.addWorksheet("Projetos");

  sheet.columns = [
    { header: "Nome do Projeto", key: "NomeProjeto", width: 30 },
    { header: "Data Início", key: "DataCriacao", width: 20 },
    { header: "Data Fim", key: "DataConclusao", width: 20 },
  ];

  projetos.forEach((p) => {
    sheet.addRow({
      NomeProjeto: p.NomeProjeto,
      DataCriacao: p.DataCriacao.toISOString().slice(0, 10),
      DataConclusao: p.DataConclusao.toISOString().slice(0, 10),
    });
  });

  await workbook.xlsx.writeFile(filePath);
  return `reports/relatorio_${userId}.xlsx`; // caminho relativo pro servidor
}

export function gerarPDF(projetos, userId) {
  const reportsDir = ensureReportsDir();
  const filePath = path.join(reportsDir, `relatorio_${userId}.pdf`);

  const doc = new PDFDocument();
  doc.pipe(fs.createWriteStream(filePath));

  doc.fontSize(18).text("Relatório de Projetos", { align: "center" });
  doc.moveDown();

  projetos.forEach((p) => {
    doc
      .fontSize(12)
      .text(
        `Projeto: ${p.NomeProjeto}\nInício: ${p.DataCriacao.toISOString().slice(
          0,
          10
        )}\nFim: ${p.DataConclusao.toISOString().slice(0, 10)}`
      );
    doc.moveDown();
  });

  doc.end();
  return `reports/relatorio_${userId}.pdf`; // caminho relativo pro servidor
}
