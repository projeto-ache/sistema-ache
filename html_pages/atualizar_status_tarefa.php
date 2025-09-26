<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json'); // Define que a resposta será em formato JSON

// Verifica se o usuário está logado
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

// Pega os dados enviados pelo JavaScript (via método POST)
$idAtribuicao = $_POST['id_atribuicao'] ?? null;
$novoStatus = $_POST['novo_status'] ?? null;
$idUsuarioLogado = $_SESSION['user_id'];

// Validação básica dos dados recebidos
if (!$idAtribuicao || !$novoStatus) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos.']);
    exit;
}

$conexao->begin_transaction(); // Inicia uma transação para garantir a consistência dos dados

try {
    // --- ETAPA 1: ATUALIZAR O STATUS DA TAREFA (lógica que você já tinha) ---
    $sql_update_status = "UPDATE projetos_tarefas_atribuidas SET Status = ? WHERE ID_Atribuicao = ?";
    $stmt_status = $conexao->prepare($sql_update_status);
    $stmt_status->bind_param("si", $novoStatus, $idAtribuicao);
    $stmt_status->execute();
    $stmt_status->close();

    // --- ETAPA 2: REGISTRAR A MODIFICAÇÃO NO PROJETO (a novidade) ---

    // 2.1: Primeiro, precisamos descobrir a qual projeto esta tarefa pertence.
    $sql_get_project_id = "SELECT ID_Projeto FROM projetos_tarefas_atribuidas WHERE ID_Atribuicao = ?";
    $stmt_get_id = $conexao->prepare($sql_get_project_id);
    $stmt_get_id->bind_param("i", $idAtribuicao);
    $stmt_get_id->execute();
    $result = $stmt_get_id->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $idProjetoDaTarefa = $row['ID_Projeto'];
        $stmt_get_id->close();

        // 2.2: Agora que temos o ID do projeto, "carimbamos" a tabela de projetos.
        $sql_log = "UPDATE projetos 
                    SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() 
                    WHERE ID_Projeto = ?";
        $stmt_log = $conexao->prepare($sql_log);
        $stmt_log->bind_param("ii", $idUsuarioLogado, $idProjetoDaTarefa);
        $stmt_log->execute();
        $stmt_log->close();
    } else {
        // Se, por algum motivo, não encontrar a tarefa, lança um erro para cancelar a transação.
        $stmt_get_id->close();
        throw new Exception("Tarefa não encontrada para registrar a modificação.");
    }
    
    $conexao->commit(); // Se ambas as operações deram certo, confirma as mudanças.
    echo json_encode(['success' => true, 'message' => 'Status atualizado com sucesso.']);

} catch (Exception $e) {
    $conexao->rollback(); // Se qualquer uma das operações falhou, desfaz tudo.
    echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
}

$conexao->close();
?>