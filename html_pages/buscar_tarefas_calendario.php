<?php
session_start();
header('Content-Type: application/json'); // Informa ao navegador que a resposta é em formato JSON

// 1. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

require_once 'conexao.php';

$idUsuarioLogado = $_SESSION['user_id'];
$tarefasParaCalendario = [];

try {
    // 2. CONSULTA SQL PARA BUSCAR AS TAREFAS
    // Esta consulta busca todas as tarefas de projetos onde o usuário é o criador OU um participante.
    $sql = "SELECT 
                pta.ID_Atribuicao,
                pta.DataPrazo,
                pta.Status,
                COALESCE(NULLIF(pta.NomeTarefaPersonalizado, ''), t.NomeTarefa) AS Titulo,
                p.NomeProjeto
            FROM 
                projetos_tarefas_atribuidas pta
            JOIN 
                tarefas t ON pta.ID_Tarefa = t.ID_Tarefa
            JOIN 
                projetos p ON pta.ID_Projeto = p.ID_Projeto
            WHERE 
                pta.ID_Projeto IN (
                    SELECT DISTINCT p.ID_Projeto
                    FROM projetos p
                    LEFT JOIN projetos_usuarios pu ON p.ID_Projeto = pu.ID_Projeto
                    WHERE p.ID_Usuario_Criador = ? OR pu.ID_Usuario = ?
                )
            AND pta.DataPrazo IS NOT NULL"; // Garante que só tarefas com prazo sejam exibidas

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ii", $idUsuarioLogado, $idUsuarioLogado);
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. FORMATAÇÃO DOS DADOS
    // O calendário precisa de um formato específico: 'YYYY-MM-DD' como chave.
    while ($row = $result->fetch_assoc()) {
        $data = $row['DataPrazo']; // Ex: 2025-08-31
        
        // Se a data ainda não existe no nosso array, criamos ela
        if (!isset($tarefasParaCalendario[$data])) {
            $tarefasParaCalendario[$data] = [];
        }

        // Adicionamos a tarefa a essa data
        $tarefasParaCalendario[$data][] = [
            'id' => $row['ID_Atribuicao'],
            'title' => $row['Titulo'],
            'status' => $row['Status'],
            'project' => $row['NomeProjeto']
            // Adicione outros campos aqui se precisar deles no popup
        ];
    }

    $stmt->close();
    $conexao->close();

    // 4. ENVIO DA RESPOSTA
    echo json_encode($tarefasParaCalendario);

} catch (Exception $e) {
    http_response_code(500); // Erro de servidor
    echo json_encode(['error' => 'Erro ao buscar dados do calendário: ' . $e->getMessage()]);
}
?>