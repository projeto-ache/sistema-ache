<?php
// Arquivo: criar_projeto.php

// 1. INICIAR A SESSÃO E CONEXÃO
session_start();
require_once 'conexao.php';

// Proteção: verificar se o usuário está logado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.html");
    exit;
}

// 2. VERIFICAR SE O FORMULÁRIO FOI ENVIADO VIA POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. RECUPERAR E VALIDAR OS DADOS DO FORMULÁRIO
    $nomeProjeto = trim($_POST['nome_projeto']);
    $descricao = trim($_POST['descricao']);
    $dataConclusao = $_POST['data_conclusao'];
    $membrosStr = trim($_POST['membros']);
    $idCriador = $_SESSION['user_id'];

    // Se o nome do projeto estiver vazio, interrompe
    if (empty($nomeProjeto)) {
        die("O nome do projeto é obrigatório.");
    }
    
    // Converte a string de membros em um array de IDs
    $membrosIds = explode(',', $membrosStr);

    // 4. INICIAR UMA TRANSAÇÃO NO BANCO DE DADOS
    $conexao->begin_transaction();
    
    try {
        // 5. INSERIR O NOVO PROJETO NA TABELA `projetos`
        $sqlProjeto = "INSERT INTO projetos (NomeProjeto, Descricao, DataConclusao, ID_Usuario_Criador, DataCriacao) VALUES (?, ?, ?, ?, NOW())";
        $stmtProjeto = $conexao->prepare($sqlProjeto);

        // A data pode ser nula, então tratamos o valor
        $dataConclusaoFinal = empty($dataConclusao) ? NULL : $dataConclusao;
        
        // O "sssi" indica 3 strings e 1 integer para os placeholders (?)
        $stmtProjeto->bind_param("sssi", $nomeProjeto, $descricao, $dataConclusaoFinal, $idCriador);
        
        $stmtProjeto->execute();
        $stmtProjeto->close();

        // 6. OBTER O ID DO PROJETO RECÉM-CRIADO
        $idNovoProjeto = $conexao->insert_id;

        // 7. INSERIR OS MEMBROS NA TABELA `projetos_usuarios`
        // Inclui o criador do projeto como membro. Se ele já estiver na lista, o banco não duplicará o registro devido à chave primária composta.
        $sqlMembro = "INSERT INTO projetos_usuarios (ID_Projeto, ID_Usuario) VALUES (?, ?)";
        $stmtMembro = $conexao->prepare($sqlMembro);
        
        // Garante que o criador está na lista de membros a serem adicionados
        if (!in_array($idCriador, $membrosIds)) {
            $membrosIds[] = $idCriador;
        }

        foreach ($membrosIds as $idMembro) {
            // "ii" indica que esperamos dois integers
            $stmtMembro->bind_param("ii", $idNovoProjeto, $idMembro);
            $stmtMembro->execute();
        }
        $stmtMembro->close();

        // 8. SE TUDO DEU CERTO, CONCLUIR A TRANSAÇÃO
        $conexao->commit();
        
        // Redirecionar para a página principal com uma mensagem de sucesso
        header("Location: index.php?status=sucesso");
        exit();

    } catch (mysqli_sql_exception $e) {
        // 9. EM CASO DE ERRO, DESFAZER TUDO
        $conexao->rollback();
        die("Erro ao criar o projeto: " . $e->getMessage());
    }
} else {
    // Se o acesso foi inválido (não via formulário)
    echo "Acesso inválido.";
}
?>