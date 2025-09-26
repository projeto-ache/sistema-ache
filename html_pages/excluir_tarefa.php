<?php
session_start();
require_once 'conexao.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

$idAtribuicao = $_POST['id_atribuicao'] ?? null;
$idUsuarioLogado = $_SESSION['user_id'];

if (!$idAtribuicao) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa não fornecido.']);
    exit;
}

$conexao->begin_transaction();
try {
    // 1. Descobrir a qual projeto esta tarefa pertence (ANTES de deletar)
    $sql_get_project_id = "SELECT ID_Projeto FROM projetos_tarefas_atribuidas WHERE ID_Atribuicao = ?";
    $stmt_get_id = $conexao->prepare($sql_get_project_id);
    $stmt_get_id->bind_param("i", $idAtribuicao);
    $stmt_get_id->execute();
    $result = $stmt_get_id->get_result();
    if (!($row = $result->fetch_assoc())) {
        throw new Exception("Tarefa não encontrada.");
    }
    $idProjetoDaTarefa = $row['ID_Projeto'];
    $stmt_get_id->close();

    // 2. Deletar as associações de usuários com a tarefa
    $sql_delete_users = "DELETE FROM tarefa_atribuida_usuarios WHERE ID_Atribuicao = ?";
    $stmt_delete_users = $conexao->prepare($sql_delete_users);
    $stmt_delete_users->bind_param("i", $idAtribuicao);
    $stmt_delete_users->execute();
    $stmt_delete_users->close();
    
    // 3. Deletar a tarefa em si
    $sql_delete_task = "DELETE FROM projetos_tarefas_atribuidas WHERE ID_Atribuicao = ?";
    $stmt_delete_task = $conexao->prepare($sql_delete_task);
    $stmt_delete_task->bind_param("i", $idAtribuicao);
    $stmt_delete_task->execute();
    $stmt_delete_task->close();

    // 4. "Carimbar" a modificação no projeto
    $sql_log = "UPDATE projetos SET ID_UltimoUsuarioModificador = ?, DataHoraUltimaModificacao = NOW() WHERE ID_Projeto = ?";
    $stmt_log = $conexao->prepare($sql_log);
    $stmt_log->bind_param("ii", $idUsuarioLogado, $idProjetoDaTarefa);
    $stmt_log->execute();
    $stmt_log->close();

    $conexao->commit();
    echo json_encode(['success' => true, 'message' => 'Tarefa excluída com sucesso.']);

} catch (Exception $e) {
    $conexao->rollback();
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir a tarefa: ' . $e->getMessage()]);
}

$conexao->close();
?>