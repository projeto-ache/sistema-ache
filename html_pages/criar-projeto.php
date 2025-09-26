<?php
// Arquivo: criar-projeto.php (Versão Corrigida e Refinada)

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
    $membrosStr = trim($_POST['membros']); // Vem como "1,2,3" ou ""
    $idCriador = $_SESSION['user_id'];

    if (empty($nomeProjeto)) {
        die("O nome do projeto é obrigatório.");
    }

    // =======================================================
    //      INÍCIO DA CORREÇÃO
    // =======================================================
    // Converte a string de membros em um array de IDs, com tratamento para o caso de estar vazio
    $membrosIds = []; // Começa com um array de fato vazio
    if (!empty($membrosStr)) {
        $membrosIds = explode(',', $membrosStr);
    }
    // =======================================================
    //      FIM DA CORREÇÃO
    // =======================================================
    
    // 4. INICIAR UMA TRANSAÇÃO NO BANCO DE DADOS
    $conexao->begin_transaction();
    
    try {
        // 5. INSERIR O NOVO PROJETO NA TABELA `projetos`
        $sqlProjeto = "INSERT INTO projetos (NomeProjeto, Descricao, DataConclusao, ID_Usuario_Criador, DataCriacao) VALUES (?, ?, ?, ?, NOW())";
        $stmtProjeto = $conexao->prepare($sqlProjeto);
        $dataConclusaoFinal = empty($dataConclusao) ? NULL : $dataConclusao;
        $stmtProjeto->bind_param("sssi", $nomeProjeto, $descricao, $dataConclusaoFinal, $idCriador);
        $stmtProjeto->execute();
        
        // 6. OBTER O ID DO PROJETO RECÉM-CRIADO
        $idNovoProjeto = $conexao->insert_id;
        $stmtProjeto->close();

        // 7. INSERIR OS MEMBROS NA TABELA `projetos_usuarios`
        
        // Lógica Robusta: Primeiro, adiciona o criador como participante garantido.
        $sqlMembro = "INSERT INTO projetos_usuarios (ID_Projeto, ID_Usuario) VALUES (?, ?)";
        $stmtMembro = $conexao->prepare($sqlMembro);
        $stmtMembro->bind_param("ii", $idNovoProjeto, $idCriador);
        $stmtMembro->execute();

        // Agora, adiciona os outros membros selecionados, se houver, evitando duplicar o criador.
        if (!empty($membrosIds)) {
            foreach ($membrosIds as $idMembro) {
                $idMembroInt = (int)$idMembro;
                // Só insere se o ID for válido e não for o próprio criador (que já foi adicionado)
                if ($idMembroInt > 0 && $idMembroInt != $idCriador) {
                    $stmtMembro->bind_param("ii", $idNovoProjeto, $idMembroInt);
                    $stmtMembro->execute();
                }
            }
        }
        $stmtMembro->close();

        // 8. SE TUDO DEU CERTO, CONCLUIR A TRANSAÇÃO
        $conexao->commit();
        
        header("Location: index.php?status=sucesso");
        exit();

    } catch (mysqli_sql_exception $e) {
        $conexao->rollback();
        die("Erro ao criar o projeto: ". $e->getMessage());
    }
} else {
    echo "Acesso inválido.";
}
?>