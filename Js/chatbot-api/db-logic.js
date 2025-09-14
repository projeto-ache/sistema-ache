import mysql from "mysql2";

const connection = mysql.createConnection({
  host: "127.0.0.1",
  user: "root",
  password: "", // se não colocou senha no XAMPP
  database: "ache_db",
  port: 3306,
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
        p.DataCriacao,
        p.DataConclusao
    FROM
        projetos AS p
    JOIN
        projetos_usuarios AS pu ON p.ID_Projeto = pu.ID_Projeto
    WHERE
        pu.ID_Usuario = ?;
  `;

  return new Promise((resolve, reject) => {
    connection.query(query, [userId], (err, results) => {
      if (err) return reject(err);
      resolve(results);
    });
  });
}
