<?php
session_start();
header('Content-Type: application/json');

// Verificações de segurança
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once 'conexao.php';

$id_atribuicao = isset($_POST['id_atribuicao']) ? (int)$_POST['id_atribuicao'] : 0;
$novo_status = isset($_POST['novo_status']) ? trim($_POST['novo_status']) : '';
$id_usuario_logado = $_SESSION['user_id'];

// Validação dos dados recebidos
$status_validos = ['A Fazer', 'Em Andamento', 'Concluído'];
if ($id_atribuicao <= 0 || !in_array($novo_status, $status_validos)) {
    echo json_encode(['success' => false, 'message' => 'Dados inválidos.']);
    exit;
}

try {
    // A mesma verificação de permissão que usamos na exclusão
    $sql_perm = "SELECT p.ID_Projeto FROM projetos_tarefas_atribuidas pta
                 JOIN projetos p ON pta.ID_Projeto = p.ID_Projeto
                 LEFT JOIN projetos_usuarios pu ON p.ID_Projeto = pu.ID_Projeto
                 WHERE pta.ID_Atribuicao = ? AND (p.ID_Usuario_Criador = ? OR pu.ID_Usuario = ?)";
    $stmt_perm = $conexao->prepare($sql_perm);
    $stmt_perm->bind_param("iii", $id_atribuicao, $id_usuario_logado, $id_usuario_logado);
    $stmt_perm->execute();
    if ($stmt_perm->get_result()->num_rows === 0) {
        throw new Exception('Permissão negada.');
    }
    $stmt_perm->close();

    // Atualiza o status da tarefa no banco de dados
    $stmt_update = $conexao->prepare("UPDATE projetos_tarefas_atribuidas SET Status = ? WHERE ID_Atribuicao = ?");
    $stmt_update->bind_param("si", $novo_status, $id_atribuicao);
    $stmt_update->execute();
    $stmt_update->close();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
}

$conexao->close();
?>