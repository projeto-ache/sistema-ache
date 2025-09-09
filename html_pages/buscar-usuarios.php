<?php
// Arquivo: buscar_usuarios.php

// 1. SEGURANÇA: Inicia a sessão e verifica se o usuário está logado.
// Isso impede que alguém de fora do sistema consiga acesso à sua lista de usuários.
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Se não estiver logado, retorna um erro de acesso negado.
    http_response_code(403); // Forbidden
    echo json_encode(['erro' => 'Acesso negado']);
    exit;
}

// 2. CONEXÃO COM O BANCO
require_once 'conexao.php';

// 3. OBTER O TERMO DA BUSCA
// Pega o parâmetro 'termo' que o JavaScript enviou pela URL (?termo=...)
$termo = isset($_GET['termo']) ? trim($_GET['termo']) : '';

// Se o termo for muito curto, não faz a busca
if (strlen($termo) < 2) {
    echo json_encode([]); // Retorna um array JSON vazio
    exit;
}

// 4. PREPARAR A CONSULTA SQL SEGURA
try {
    // Prepara o termo para ser usado em uma cláusula LIKE
    $termo_busca = "%" . $termo . "%";
    
    // Pega o ID do usuário logado para não incluí-lo nos resultados da busca
    $idUsuarioLogado = $_SESSION['user_id'];

    // A consulta busca por Nome, Sobrenome ou E-mail que correspondam ao termo,
    // e exclui o próprio usuário que está fazendo a busca.
    $sql = "SELECT ID_Usuario, Nome, Sobrenome, Email 
            FROM usuarios 
            WHERE (CONCAT(Nome, ' ', Sobrenome) LIKE ? OR Email LIKE ?) 
            AND ID_Usuario != ?";

    $stmt = $conexao->prepare($sql);
    $stmt->bind_param("ssi", $termo_busca, $termo_busca, $idUsuarioLogado);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $usuarios = [];

    while ($row = $result->fetch_assoc()) {
        $usuarios[] = $row;
    }

    $stmt->close();
    $conexao->close();

    // 5. DEVOLVER OS DADOS EM FORMATO JSON
    // Define o cabeçalho da resposta para indicar que o conteúdo é JSON
    header('Content-Type: application/json');
    // Converte o array PHP em uma string JSON e a envia como resposta
    echo json_encode($usuarios);

} catch (mysqli_sql_exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['erro' => 'Erro no banco de dados: ' . $e->getMessage()]);
}
?>