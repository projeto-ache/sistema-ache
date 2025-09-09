<?php 
    // Corrigido para 'localhost'
    $dbHost = 'localhost';
    $dbUsername = 'root';
    $dbPassword = '';
    $dbName = 'ache_db';

    // A conexão continua a mesma
    $conexao = new mysqli($dbHost, $dbUsername, $dbPassword, $dbName);

    // Verificação de erro
    if ($conexao->connect_errno) {
        // É bom dar uma mensagem mais específica no desenvolvimento
        die("Falha na conexão com o banco de dados: " . $conexao->connect_error);
    }
    
    // -> Adicionado para garantir a codificação correta de caracteres
    $conexao->set_charset("utf8mb4");

    /* // Pode remover o "echo" de sucesso. 
    // Geralmente, só queremos um aviso se algo der errado. 
    // Se nada aparecer, assumimos que a conexão foi bem-sucedida.
    else {
        // echo "Conexão efetuada com sucesso";
    }
    */
?>