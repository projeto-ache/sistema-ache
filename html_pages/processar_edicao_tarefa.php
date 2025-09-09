<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';

// 1. Coleta os dados do formulário
$id_atribuicao = isset($_POST['id_atribuicao']) ? (int)$_POST['id_atribuicao'] : 0;
$idTarefaExistente = isset($_POST['id_tarefa_existente']) ? (int)$_POST['id_tarefa_existente'] : 0;
$nomeTarefaNova = trim($_POST['nome_tarefa_nova'] ?? '');
$descricaoNova = trim($_POST['descricao_nova'] ?? '');
$status = trim($_POST['status'] ?? 'A Fazer');
$dataPrazo = $_POST['data_prazo'] ?? '';
$idsUsuariosAtribuidos = $_POST['usuario_atribuido'] ?? [];

if ($id_atribuicao <= 0) {
    die("Erro: ID da tarefa a ser editada não foi fornecido.");
}
if (empty($dataPrazo)) {
    die("Erro: A data de prazo é obrigatória.");
}

// 2. Transação para garantir que todas as atualizações funcionem ou nenhuma funcione
$conexao->begin_transaction();
try {
    // 2A. Atualiza a tabela principal 'projetos_tarefas_atribuidas'
    $sql_update_tarefa = "UPDATE projetos_tarefas_atribuidas SET 
                            ID_Tarefa = ?, 
                            NomeTarefaPersonalizado = ?, 
                            DescricaoPersonalizada = ?,
                            Status = ?, 
                            DataPrazo = ?
                          WHERE ID_Atribuicao = ?";
    $stmt_update = $conexao->prepare($sql_update_tarefa);
    $stmt_update->bind_param("issssi", $idTarefaExistente, $nomeTarefaNova, $descricaoNova, $status, $dataPrazo, $id_atribuicao);
    $stmt_update->execute();
    $stmt_update->close();

    // 2B. Apaga TODAS as associações de usuários antigas para esta tarefa
    $stmt_delete = $conexao->prepare("DELETE FROM tarefa_atribuida_usuarios WHERE ID_Atribuicao = ?");
    $stmt_delete->bind_param("i", $id_atribuicao);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 2C. Insere as NOVAS associações de usuários
    if (!empty($idsUsuariosAtribuidos)) {
        $sql_insert_users = "INSERT INTO tarefa_atribuida_usuarios (ID_Atribuicao, ID_Usuario) VALUES (?, ?)";
        $stmt_insert = $conexao->prepare($sql_insert_users);
        foreach ($idsUsuariosAtribuidos as $id_usuario) {
            
            // --- ESTA É A CORREÇÃO ---
            // Primeiro, garantimos que o ID do usuário é um inteiro e o guardamos em uma variável
            $id_usuario_int = (int)$id_usuario; 
            // Agora, passamos a variável (a "caixa") para a função
            $stmt_insert->bind_param("ii", $id_atribuicao, $id_usuario_int);
            
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }

    // Se tudo deu certo, confirma a transação
    $conexao->commit();
    header("Location: index.php?status=task_edit_success");
    exit;

} catch (Exception $e) {
    // Se algo deu errado, desfaz tudo
    $conexao->rollback();
    error_log("Erro ao processar edição de tarefa: " . $e->getMessage()); // É uma boa prática registrar o erro
    header("Location: index.php?status=task_edit_error");
    exit;
}

$conexao->close();
?>