<?php
session_start();
header('Content-Type: application/json'); // Define que a resposta será em JSON

// Verificações de segurança iniciais
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once 'conexao.php';

$id_atribuicao = isset($_POST['id_atribuicao']) ? (int)$_POST['id_atribuicao'] : 0;
$id_usuario_logado = $_SESSION['user_id'];

if ($id_atribuicao <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID da tarefa inválido.']);
    exit;
}

$conexao->begin_transaction();
try {
    // VERIFICAÇÃO DE SEGURANÇA CRUCIAL:
    // O usuário logado tem permissão para excluir esta tarefa?
    // (Ele pertence ao projeto da tarefa?)
    $sql_perm = "SELECT p.ID_Projeto FROM projetos_tarefas_atribuidas pta
                 JOIN projetos p ON pta.ID_Projeto = p.ID_Projeto
                 LEFT JOIN projetos_usuarios pu ON p.ID_Projeto = pu.ID_Projeto
                 WHERE pta.ID_Atribuicao = ? 
                 AND (p.ID_Usuario_Criador = ? OR pu.ID_Usuario = ?)";
    
    $stmt_perm = $conexao->prepare($sql_perm);
    $stmt_perm->bind_param("iii", $id_atribuicao, $id_usuario_logado, $id_usuario_logado);
    $stmt_perm->execute();
    $result_perm = $stmt_perm->get_result();
    
    if ($result_perm->num_rows === 0) {
        throw new Exception('Permissão negada para excluir esta tarefa.');
    }
    $stmt_perm->close();

    // Se a permissão foi concedida, prosseguimos com a exclusão
    // 1. Excluir as associações de usuários (tabela filha)
    $stmt_delete_users = $conexao->prepare("DELETE FROM tarefa_atribuida_usuarios WHERE ID_Atribuicao = ?");
    $stmt_delete_users->bind_param("i", $id_atribuicao);
    $stmt_delete_users->execute();
    $stmt_delete_users->close();

    // 2. Excluir a tarefa principal (tabela pai)
    $stmt_delete_task = $conexao->prepare("DELETE FROM projetos_tarefas_atribuidas WHERE ID_Atribuicao = ?");
    $stmt_delete_task->bind_param("i", $id_atribuicao);
    $stmt_delete_task->execute();
    $stmt_delete_task->close();

    $conexao->commit();
    echo json_encode(['success' => true, 'message' => 'Tarefa excluída com sucesso!']);

} catch (Exception $e) {
    $conexao->rollback();
    // Em produção, seria bom registrar o erro: error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir a tarefa: ' . $e->getMessage()]);
}

$conexao->close();
?>