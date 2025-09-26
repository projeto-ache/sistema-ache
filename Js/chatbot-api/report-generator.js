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
<<<<<<< Updated upstream
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
=======
  const sheet = workbook.addWorksheet("Relatório de Projetos e Tarefas");

  // Definir as colunas
  sheet.columns = [
    { header: "Nome do Projeto", key: "NomeProjeto", width: 35 },
    { header: "Descrição do Projeto", key: "DescricaoProjeto", width: 50 },
    { header: "Início do Projeto", key: "DataCriacaoProjeto", width: 20 },
    { header: "Fim do Projeto", key: "DataConclusaoProjeto", width: 20 },
    { header: "Membros do Projeto", key: "MembrosProjeto", width: 40 },
    { header: "Nome da Tarefa", key: "NomeTarefa", width: 40 },
    { header: "Status da Tarefa", key: "StatusTarefa", width: 20 },
    { header: "Prazo da Tarefa", key: "DataPrazo", width: 20 },
    { header: "Fim da Tarefa", key: "DataConclusaoTarefa", width: 20 },
    { header: "Responsáveis", key: "ResponsaveisTarefa", width: 40 },
    { header: "Classificação Aché", key: "Classificacao_Ache", width: 25 },
    { header: "Fase Aché", key: "Fase_Ache", width: 25 },
    { header: "Como Fazer", key: "ComoFazer", width: 50 }
  ];

  // Agrupar tarefas por projeto
  const projetosAgrupados = projetos.reduce((acc, current) => {
    (acc[current.NomeProjeto] = acc[current.NomeProjeto] || []).push(current);
    return acc;
  }, {});

  let currentRow = 2; // Começa na linha 2, após os cabeçalhos

  for (const nomeProjeto in projetosAgrupados) {
    const tarefasDoProjeto = projetosAgrupados[nomeProjeto];
    const primeiroRegistro = tarefasDoProjeto[0];
    const numTarefas = tarefasDoProjeto.length;
    const startRow = currentRow;

    // Adiciona uma linha em branco para separar visualmente os projetos
    if (currentRow > 2) {
      sheet.addRow({});
      currentRow++;
    }

    // Adiciona as informações do projeto na primeira linha do bloco
    sheet.addRow({
      NomeProjeto: primeiroRegistro.NomeProjeto,
      DescricaoProjeto: primeiroRegistro.DescricaoProjeto,
      DataCriacaoProjeto:
        primeiroRegistro.DataCriacaoProjeto.toISOString().slice(0, 10),
      DataConclusaoProjeto:
        primeiroRegistro.DataConclusaoProjeto.toISOString().slice(0, 10),
      MembrosProjeto: primeiroRegistro.MembrosProjeto,
      NomeTarefa:
        primeiroRegistro.NomeTarefaPersonalizado ||
        primeiroRegistro.NomeTarefaOriginal,
      StatusTarefa: primeiroRegistro.StatusTarefa,
      DataPrazo: primeiroRegistro.DataPrazo
        ? primeiroRegistro.DataPrazo.toISOString().slice(0, 10)
        : "",
      DataConclusaoTarefa:
        primeiroRegistro.DataConclusaoTarefa &&
        primeiroRegistro.DataConclusaoTarefa.getFullYear() > 1900
          ? primeiroRegistro.DataConclusaoTarefa.toISOString().slice(0, 10)
          : "",
      ResponsaveisTarefa: primeiroRegistro.ResponsaveisTarefa,
      Classificacao_Ache: primeiroRegistro.Classificacao_Ache,
      Fase_Ache: primeiroRegistro.Fase_Ache,
      ComoFazer: primeiroRegistro.ComoFazer
    });

    currentRow++;

    // Adiciona as demais tarefas (se houver)
    if (numTarefas > 1) {
      for (let i = 1; i < numTarefas; i++) {
        const tarefa = tarefasDoProjeto[i];
        sheet.addRow({
          NomeTarefa:
            tarefa.NomeTarefaPersonalizado || tarefa.NomeTarefaOriginal,
          StatusTarefa: tarefa.StatusTarefa,
          DataPrazo: tarefa.DataPrazo
            ? tarefa.DataPrazo.toISOString().slice(0, 10)
            : "",
          DataConclusaoTarefa:
            tarefa.DataConclusaoTarefa &&
            tarefa.DataConclusaoTarefa.getFullYear() > 1900
              ? tarefa.DataConclusaoTarefa.toISOString().slice(0, 10)
              : "",
          ResponsaveisTarefa: tarefa.ResponsaveisTarefa,
          Classificacao_Ache: tarefa.Classificacao_Ache,
          Fase_Ache: tarefa.Fase_Ache,
          ComoFazer: tarefa.ComoFazer
        });
        currentRow++;
      }

      // Mescla as células das informações do projeto
      const endRow = currentRow - 1;
      sheet.mergeCells(`A${startRow}:A${endRow}`);
      sheet.mergeCells(`B${startRow}:B${endRow}`);
      sheet.mergeCells(`C${startRow}:C${endRow}`);
      sheet.mergeCells(`D${startRow}:D${endRow}`);
      sheet.mergeCells(`E${startRow}:E${endRow}`);
    }
  }

  await workbook.xlsx.writeFile(filePath);
  return `reports/relatorio_${userId}.xlsx`;
>>>>>>> Stashed changes
}

export function gerarPDF(projetos, userId) {
  const reportsDir = ensureReportsDir();
  const filePath = path.join(reportsDir, `relatorio_${userId}.pdf`);

  const doc = new PDFDocument();
  doc.pipe(fs.createWriteStream(filePath));

<<<<<<< Updated upstream
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
=======
  doc.fontSize(18).text("Relatório de Projetos e Tarefas", { align: "center" });
  doc.moveDown();

  // Agrupar tarefas por projeto
  const projetosAgrupados = projetos.reduce((acc, current) => {
    (acc[current.NomeProjeto] = acc[current.NomeProjeto] || []).push(current);
    return acc;
  }, {});

  for (const nomeProjeto in projetosAgrupados) {
    const tarefasDoProjeto = projetosAgrupados[nomeProjeto];
    const primeiroRegistro = tarefasDoProjeto[0];

    // Informações do projeto
    doc
      .fontSize(14)
      .text(`Projeto: ${primeiroRegistro.NomeProjeto}`, { underline: true });
    doc.fontSize(12).text(`Descrição: ${primeiroRegistro.DescricaoProjeto}`);
    doc.text(
      `Início: ${primeiroRegistro.DataCriacaoProjeto.toISOString().slice(
        0,
        10
      )}`
    );
    doc.text(
      `Fim: ${primeiroRegistro.DataConclusaoProjeto.toISOString().slice(0, 10)}`
    );
    doc.text(`Membros da Equipe: ${primeiroRegistro.MembrosProjeto}`);
    doc.moveDown();

    // Informações das tarefas
    doc.fontSize(12).text("Tarefas:", { bold: true });
    tarefasDoProjeto.forEach((tarefa) => {
      doc
        .fontSize(10)
        .text(
          `• Nome: ${
            tarefa.NomeTarefaPersonalizado || tarefa.NomeTarefaOriginal
          }`
        );
      doc.text(`  Status: ${tarefa.StatusTarefa}`);
      if (tarefa.DataPrazo) {
        doc.text(`  Prazo: ${tarefa.DataPrazo.toISOString().slice(0, 10)}`);
      }
      if (
        tarefa.DataConclusaoTarefa &&
        tarefa.DataConclusaoTarefa.getFullYear() > 1900
      ) {
        doc.text(
          `  Conclusão: ${tarefa.DataConclusaoTarefa.toISOString().slice(
            0,
            10
          )}`
        );
      }
      doc.text(`  Responsáveis: ${tarefa.ResponsaveisTarefa}`);
      doc.text(`  Classificação: ${tarefa.Classificacao_Ache}`);
      doc.text(`  Fase: ${tarefa.Fase_Ache}`);
      doc.text(`  Como Fazer: ${tarefa.ComoFazer}`);
      doc.moveDown(0.5);
    });

    doc.moveDown(1);
  }

  doc.end();
  return `reports/relatorio_${userId}.pdf`;
>>>>>>> Stashed changes
}
