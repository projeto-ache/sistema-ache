<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: login.html");
    exit;
}

require_once 'conexao.php';

// 1. Obter e higienizar os dados do formulário
$id_projeto = (int)$_POST['id_projeto'];
$nome_projeto = trim($_POST['nomeProjeto']);
$descricao = trim($_POST['descricao']);
// Garante que participantes seja um array, mesmo que vazio
$participantes = isset($_POST['participantes']) ? $_POST['participantes'] : [];

// Validação simples
if (empty($id_projeto) || empty($nome_projeto)) {
    die("Nome do projeto e ID são obrigatórios.");
}

// 2. Usar uma TRANSAÇÃO para garantir a integridade dos dados
$conexao->begin_transaction();

try {
    // 2A. Atualizar a tabela 'projetos'
    $stmt_update = $conexao->prepare("UPDATE projetos SET NomeProjeto = ?, Descricao = ? WHERE ID_Projeto = ?");
    $stmt_update->bind_param("ssi", $nome_projeto, $descricao, $id_projeto);
    $stmt_update->execute();
    $stmt_update->close();

    // 2B. Apagar todas as associações de participantes ANTERIORES deste projeto
    $stmt_delete = $conexao->prepare("DELETE FROM projetos_usuarios WHERE ID_Projeto = ?");
    $stmt_delete->bind_param("i", $id_projeto);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 2C. Inserir as NOVAS associações de participantes (se houver alguma)
    if (!empty($participantes)) {
        $stmt_insert = $conexao->prepare("INSERT INTO projetos_usuarios (ID_Projeto, ID_Usuario) VALUES (?, ?)");
        foreach ($participantes as $id_usuario) {
            $id_usuario_int = (int)$id_usuario;
            $stmt_insert->bind_param("ii", $id_projeto, $id_usuario_int);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }

    // Se tudo deu certo, efetiva as mudanças
    $conexao->commit();
    header("Location: index.php?status=edit_success");

} catch (Exception $e) {
    // Se algo deu errado, desfaz tudo
    $conexao->rollback();
    die("Erro ao atualizar o projeto: " . $e->getMessage());
    // Poderia redirecionar com uma mensagem de erro:
    // header("Location: index.php?status=edit_error");
}

$conexao->close();
exit;
?>