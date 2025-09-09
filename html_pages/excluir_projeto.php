<?php
session_start();
header('Content-Type: application/json'); // Define que a resposta será em formato JSON

// 1. VERIFICAÇÕES DE SEGURANÇA
// Garante que o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Acesso não autorizado.']);
    exit;
}

require_once 'conexao.php';

$idUsuarioLogado = $_SESSION['user_id'];
// Pega o ID do projeto enviado pelo JavaScript
$idProjetoParaExcluir = isset($_POST['id_projeto']) ? (int)$_POST['id_projeto'] : 0;

if ($idProjetoParaExcluir <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do projeto inválido.']);
    exit;
}

try {
    // 2. VERIFICAÇÃO DE PERMISSÃO (AUTORIZAÇÃO)
    // ESSENCIAL: Verifica se o usuário logado é o CRIADOR do projeto.
    $sql_check = "SELECT ID_Usuario_Criador FROM projetos WHERE ID_Projeto = ?";
    $stmt_check = $conexao->prepare($sql_check);
    $stmt_check->bind_param("i", $idProjetoParaExcluir);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $projeto = $result_check->fetch_assoc();

    if (!$projeto || $projeto['ID_Usuario_Criador'] != $idUsuarioLogado) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para excluir este projeto.']);
        exit;
    }

    // 3. PROCESSO DE EXCLUSÃO (COM TRANSAÇÃO)
    $conexao->begin_transaction();

    // Ordem de exclusão para respeitar as chaves estrangeiras:
    
    // a. Excluir as atribuições de usuários às tarefas deste projeto
    $conexao->query("DELETE tau FROM tarefa_atribuida_usuarios tau JOIN projetos_tarefas_atribuidas pta ON tau.ID_Atribuicao = pta.ID_Atribuicao WHERE pta.ID_Projeto = $idProjetoParaExcluir");
    
    // b. Excluir as tarefas atribuídas a este projeto
    $conexao->query("DELETE FROM projetos_tarefas_atribuidas WHERE ID_Projeto = $idProjetoParaExcluir");
    
    // c. Excluir os participantes deste projeto
    $conexao->query("DELETE FROM projetos_usuarios WHERE ID_Projeto = $idProjetoParaExcluir");
    
    // d. Finalmente, excluir o projeto
    $conexao->query("DELETE FROM projetos WHERE ID_Projeto = $idProjetoParaExcluir");

    $conexao->commit(); // Se tudo deu certo, confirma as exclusões

    echo json_encode(['success' => true, 'message' => 'Projeto excluído com sucesso.']);

} catch (Exception $e) {
    $conexao->rollback(); // Se algo deu errado, desfaz tudo
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir o projeto: ' . $e->getMessage()]);
}

$conexao->close();
?>