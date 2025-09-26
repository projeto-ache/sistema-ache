import mysql from "mysql2";

const connection = mysql.createConnection({
  host: "127.0.0.1",
  user: "root",
  password: "", // se não colocou senha no XAMPP
  database: "ache_db",
<<<<<<< Updated upstream
  port: 3306,
=======
  port: 3306
>>>>>>> Stashed changes
});

export async function getProjectsByMonth(userId) {
  const today = new Date();
  const currentYear = today.getFullYear();
  const currentMonth = today.getMonth() + 1;

  const query = `
    SELECT
        p.NomeProjeto,
        p.DataCriacao,
        p.DataConclusao
    FROM
        projetos AS p
    JOIN
        projetos_usuarios AS pu ON p.ID_Projeto = pu.ID_Projeto
    WHERE
        pu.ID_Usuario = ?
        AND YEAR(p.DataCriacao) = ?
        AND MONTH(p.DataCriacao) = ?;
  `;

  return new Promise((resolve, reject) => {
    connection.query(
      query,
      [userId, currentYear, currentMonth],
      (err, results) => {
        if (err) return reject(err);
        resolve(results);
      }
    );
  });
}

// 🔥 NOVA FUNÇÃO
export async function getProjectsByUser(userId) {
  const query = `
    SELECT
        p.NomeProjeto,
<<<<<<< Updated upstream
        p.DataCriacao,
        p.DataConclusao
    FROM
        projetos AS p
    JOIN
        projetos_usuarios AS pu ON p.ID_Projeto = pu.ID_Projeto
    WHERE
        pu.ID_Usuario = ?;
=======
        p.Descricao AS DescricaoProjeto,
        p.DataCriacao AS DataCriacaoProjeto,
        p.DataConclusao AS DataConclusaoProjeto,
        GROUP_CONCAT(DISTINCT pu.Nome) AS MembrosProjeto,
        pta.NomeTarefaPersonalizado,
        pta.Status AS StatusTarefa,
        pta.DataPrazo,
        pta.DataConclusao AS DataConclusaoTarefa,
        t.NomeTarefa AS NomeTarefaOriginal,
        t.Classificacao_Ache,
        t.Fase_Ache,
        t.ComoFazer,
        GROUP_CONCAT(DISTINCT tu.Nome) AS ResponsaveisTarefa
    FROM
        projetos AS p
    JOIN
        projetos_usuarios AS pu_join ON p.ID_Projeto = pu_join.ID_Projeto
    JOIN
        usuarios AS pu ON pu_join.ID_Usuario = pu.ID_Usuario
    LEFT JOIN
        projetos_tarefas_atribuidas AS pta ON p.ID_Projeto = pta.ID_Projeto
    LEFT JOIN
        tarefas AS t ON pta.ID_Tarefa = t.ID_Tarefa
    LEFT JOIN
        tarefa_atribuida_usuarios AS tau ON pta.ID_Atribuicao = tau.ID_Atribuicao
    LEFT JOIN
        usuarios AS tu ON tau.ID_Usuario = tu.ID_Usuario
    WHERE
        pu_join.ID_Usuario = ?
    GROUP BY
        p.ID_Projeto, pta.ID_Atribuicao
    ORDER BY
        p.DataCriacao DESC;
>>>>>>> Stashed changes
  `;

  return new Promise((resolve, reject) => {
    connection.query(query, [userId], (err, results) => {
      if (err) return reject(err);
      resolve(results);
    });
  });
}
